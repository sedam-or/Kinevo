import { test, expect, type Page } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * Journey I (TASK-P17-014, docs/browser-e2e.md §11) — Task → Today →
 * Progress through the live UI, proving Today as the control center:
 *
 *   task created (quick capture) → scheduled → appears in Today under the
 *   strict hierarchy NOW → NEXT → Timeline → supporting context → complete
 *   → progress strip updates.
 */

const API = '/api/v1';

function isoDate(daysFromNow: number): string {
    const d = new Date();
    d.setDate(d.getDate() + daysFromNow);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

async function apiFetch(page: Page, path: string, init: RequestInit = {}): Promise<unknown> {
    const token = await page.evaluate(() => window.localStorage.getItem('kinevo.auth.token'));
    const res = await page.evaluate(
        async ({ path, init, token }) => {
            const response = await fetch(path, {
                ...init,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(token ? { Authorization: `Bearer ${token}` } : {}),
                    ...(init.headers ?? {}),
                },
            });
            return { ok: response.ok, status: response.status, body: await response.text() };
        },
        { path, init, token },
    );
    if (!res.ok) {
        throw new Error(`API ${path} -> ${res.status}: ${String(res.body)}`);
    }
    return res.body ? JSON.parse(res.body as string) : {};
}

async function seedTodayPair(page: Page): Promise<{ nowTitle: string; nextTitle: string; nowStart: Date }> {
    // A future day always has free capacity (the shared dev owner's "today"
    // accumulates fixtures across runs); the client clock is then installed
    // on that day so it renders as Today.
    const date = isoDate(4 + (Date.now() % 10));
    const nowTitle = unique('jI-now');
    const nextTitle = unique('jI-next');

    const r1 = (await apiFetch(page, `${API}/quick-capture`, {
        method: 'POST',
        body: JSON.stringify({ title: nowTitle, priority_tier: 3, duration_minutes: 45, date }),
    })) as { placed?: boolean; assignment?: { start_at?: string } };
    const r2 = (await apiFetch(page, `${API}/quick-capture`, {
        method: 'POST',
        body: JSON.stringify({ title: nextTitle, priority_tier: 3, duration_minutes: 45, date }),
    })) as { placed?: boolean };

    if (r1.placed !== true || !r1.assignment?.start_at) {
        throw new Error(`journey-I first capture not placed: ${JSON.stringify(r1)}`);
    }
    if (r2.placed !== true) {
        throw new Error(`journey-I second capture not placed: ${JSON.stringify(r2)}`);
    }
    return { nowTitle, nextTitle, nowStart: new Date(r1.assignment.start_at as string) };
}

test.describe('Journey I — Task → Today → Progress (TASK-P17-014)', () => {
    test('control-center hierarchy holds and progress updates on complete', async ({ page }) => {
        await login(page);
        const seeded = await seedTodayPair(page);

        // Render the seeded day as Today with the first task in the NOW slot.
        await page.clock.install({ time: new Date(seeded.nowStart.getTime() + 60_000) });
        await page.goto('/app');
        await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('now-title')).toHaveText(seeded.nowTitle, { timeout: 20_000 });

        // STRICT HIERARCHY: NOW before NEXT before Timeline before any
        // supporting context (progress / check-in / quick capture).
        const order = await page.evaluate(() =>
            ['now-card', 'next-card', 'today-timeline', 'today-progress', 'quick-capture'].map((testid) => {
                const el = document.querySelector(`[data-testid="${testid}"]`);
                return { testid, top: el ? el.getBoundingClientRect().top : Number.POSITIVE_INFINITY };
            }),
        );
        for (let i = 1; i < order.length; i++) {
            expect(
                order[i - 1].top,
                `${order[i - 1].testid} must sit above ${order[i].testid}`,
            ).toBeLessThanOrEqual(order[i].top);
        }

        // WHY THIS (P17-015 / FR-63): collapsed by default, expands with
        // deterministic content derived from the captured fields.
        const whyToggle = page.getByTestId('why-this-toggle');
        await expect(whyToggle).toBeVisible();
        await expect(page.getByTestId('why-this-content')).toHaveCount(0);
        await whyToggle.click();
        const why = page.getByTestId('why-this-content');
        await expect(why).toBeVisible();
        await expect(why.getByTestId('why-tier')).toContainText('P3');
        await expect(why.getByTestId('why-deadline')).toContainText('No deadline pressure');
        await expect(why.getByTestId('why-slot')).toContainText('45m');
        await expect(whyToggle).toHaveAttribute('aria-expanded', 'true');
        await whyToggle.click();
        await expect(page.getByTestId('why-this-content')).toHaveCount(0);

        // PROGRESS before completion: read the shared day's current counts
        // (the dev owner's days accumulate fixtures across runs).
        const before = await page
            .getByTestId('today-progress-count')
            .innerText()
            .then((s) => /([\d]+)\s*\/\s*([\d]+)/.exec(s));
        if (!before) throw new Error('progress strip missing counts');
        const doneBefore = Number(before[1]);
        const totalBefore = Number(before[2]);

        // START → COMPLETE through the product.
        await page.getByTestId('execution-start').click();
        await expect(page.getByTestId('execution-status')).toHaveText('Running', { timeout: 15_000 });
        await page.clock.setSystemTime(new Date());
        await page.getByTestId('execution-complete').click();
        await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 30_000 });

        // Progress updates itself (§99 loop closes at PROGRESS): exactly one
        // more task done, planned total unchanged.
        await expect(page.getByTestId('today-progress-count')).toContainText(
            `${doneBefore + 1}/${totalBefore}`,
            { timeout: 20_000 },
        );
        await expect(page.getByTestId('today-error')).not.toBeVisible();
    });

    for (const width of [375, 390, 412]) {
        test(`mobile ${width}px: Today keeps hierarchy without overflow`, async ({ browser }) => {
            const context = await browser.newContext({ viewport: { width, height: 800 } });
            const page = await context.newPage();
            await login(page);
            await page.getByTestId('today-view').waitFor({ timeout: 30_000 });

            // No horizontal bleed (design.md §88).
            const overflow = await page.evaluate(
                () => document.documentElement.scrollWidth > window.innerWidth + 1,
            );
            expect(overflow, `Today overflows horizontally at ${width}px`).toBe(false);

            // The execution hub renders above its supporting context.
            const top = (testid: string) =>
                page.evaluate(
                    (id) => document.querySelector(`[data-testid="${id}"]`)?.getBoundingClientRect().top ?? -1,
                    testid,
                );
            expect(await top('now-card')).toBeLessThanOrEqual(await top('today-timeline'));
            await context.close();
        });
    }
});
