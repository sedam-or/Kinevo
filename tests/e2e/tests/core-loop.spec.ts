import { test, expect, type Page } from '@playwright/test';
import { OWNER_EMAIL, OWNER_PASSWORD, unique } from './helpers';

/**
 * R1 core loop (design.md §99 — highest priority) driven through the REAL
 * browser, not unit mocks:
 *
 *   LOGIN → TODAY → NOW TASK → START → COMPLETE → PROGRESS → NEXT TASK
 *
 * Deterministic by design:
 * - Tasks are quick-captured onto a *future empty day* (the shared dev owner's
 *   "today" accumulates fixtures throughout the suite, so its 30% safety
 *   reserve fills up; a future day always has free capacity).
 * - The NOW card renders only when a schedule assignment covers the client's
 *   clock. Playwright's clock is installed at the assigned slot so the card
 *   appears; for the PROGRESS tick the clock is restored to real time so
 *   elapsed secrades from the server-side start timestamp.
 *
 * Runs in every matrix browser; R1 acceptance requires Chromium + Firefox.
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

async function loginOnly(page: Page): Promise<void> {
    await page.goto('/app');
    await page.getByTestId('login-form').waitFor({ state: 'visible' });
    await page.getByTestId('login-email').fill(OWNER_EMAIL);
    await page.getByTestId('login-password').fill(OWNER_PASSWORD);
    await page.getByTestId('login-form').getByRole('button', { name: /log in/i }).click();
    await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
}

/**
 * Quick-capture two tasks onto a future empty day and return the assigned
 * slot times plus titles. The scheduler places them deterministically.
 */
async function seedFutureDay(page: Page, daysFromNow: number): Promise<{
    date: string;
    now: { title: string; startAt: Date; endAt: Date };
    next: { title: string };
}> {
    const date = isoDate(daysFromNow);
    const titleNow = unique('coreloop-now');
    const titleNext = unique('coreloop-next');

    const r1 = (await apiFetch(page, `${API}/quick-capture`, {
        method: 'POST',
        body: JSON.stringify({ title: titleNow, priority_tier: 3, duration_minutes: 45, date }),
    })) as { placed?: boolean; assignment?: { start_at?: string; end_at?: string } };
    const r2 = (await apiFetch(page, `${API}/quick-capture`, {
        method: 'POST',
        body: JSON.stringify({ title: titleNext, priority_tier: 3, duration_minutes: 45, date }),
    })) as { placed?: boolean };

    if (r1.placed !== true || !r1.assignment?.start_at) {
        throw new Error(`first quick-capture not placed: ${JSON.stringify(r1)}`);
    }
    if (r2.placed !== true) {
        throw new Error(`second quick-capture not placed: ${JSON.stringify(r2)}`);
    }

    return {
        date,
        now: {
            title: titleNow,
            startAt: new Date(r1.assignment.start_at as string),
            endAt: new Date(r1.assignment.end_at as string),
        },
        next: { title: titleNext },
    };
}

test.describe('R1 core loop — LOGIN → TODAY → NOW → START → COMPLETE → NEXT (real browser)', () => {
    test('executes the first-love loop through the live UI', async ({ page }) => {
        await loginOnly(page);

        // Seed two tasks on a future day; the product assigns slots there (a
        // future day is always within the safety reserve). The offset varies
        // per run so repeated executions spread across days.
        const seeded = await seedFutureDay(page, 5 + (Date.now() % 10));

        // Install the browser clock at the midpoint of the NOW task's slot.
        // The today view derives its date from the client clock, so it renders
        // the seeded day with the seeded task as the current event.
        const midNow = new Date(seeded.now.startAt.getTime() + 60_000);
        await page.clock.install({ time: midNow });
        await page.goto('/app');

        // TODAY + NOW TASK: the seeded task is the current event.
        await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('now-card')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('now-title')).toHaveText(seeded.now.title);

        // NEXT TASK: the second task is queued as the upcoming event.
        await expect(page.getByTestId('next-card')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('next-card')).toContainText(seeded.next.title);

        // START: begin the execution session (server-authoritative timestamp).
        await expect(page.getByTestId('execution-start')).toBeVisible();
        await page.getByTestId('execution-start').click();
        await expect(page.getByTestId('execution-status')).toHaveText('Running', { timeout: 15_000 });

        // PROGRESS: restore the real clock so elapsed seconds accrue from the
        // server-side start timestamp (client and server share a timezone).
        await page.clock.setSystemTime(new Date());
        await expect(page.getByTestId('execution-elapsed')).not.toHaveText('00:00', { timeout: 20_000 });

        // COMPLETE: finish the session through the UI; the timer resets.
        await page.getByTestId('execution-complete').click();
        // Complete cascade (TASK-P17-011): activity toast answers
        // "did my action work?" and the upcoming task gets a brief spotlight.
        await expect(page.getByTestId('toast-host')).toContainText('Task completed', { timeout: 15_000 });
        await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('execution-status')).toHaveText('Ready', { timeout: 20_000 });
        await expect(page.getByTestId('next-card')).toHaveClass(/ring-2/, { timeout: 10_000 });
        await expect(page.getByTestId('today-error')).not.toBeVisible();
    });
});