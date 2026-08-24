import { test, expect, type Page } from '@playwright/test';
import { login, unique } from './helpers';
/**
 * TASK-P17-023 — canonical end-to-end product journey (primary P17 success
 * criterion) in ONE continuous real-browser session:
 *
 *   LOGIN → GOAL → AI breakdown → MILESTONES → PROGRAMS → TASKS → SCHEDULE →
 *   TODAY (NOW) → START → PROGRESS → COMPLETE → ANALYTICS → ADAPTATION.
 *
 * Every product-owned step drives the live UI. The single API read-back is
 * the day-view lookup for the exact assignment slot (the capture panel only
 * renders a locale-formatted time), mirroring journey-i's deterministic
 * seeding contract: a future empty day always has free capacity, and the
 * client clock is installed on the assigned slot so it renders as Today.
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

async function apiFetch(page: Page, path: string): Promise<Record<string, unknown>> {
    const token = await page.evaluate(() => window.localStorage.getItem('kinevo.auth.token'));
    const res = await page.evaluate(
        async ({ path, token }) => {
            const response = await fetch(path, {
                headers: {
                    Accept: 'application/json',
                    ...(token ? { Authorization: `Bearer ${token}` } : {}),
                },
            });
            return { ok: response.ok, status: response.status, body: await response.text() };
        },
        { path, token },
    );
    if (!res.ok) {
        throw new Error(`API ${path} -> ${res.status}: ${String(res.body)}`);
    }
    return JSON.parse(res.body as string) as Record<string, unknown>;
}

test.describe('TASK-P17-023 — canonical end-to-end product journey', () => {
    test('login → goal → AI breakdown → milestones → program → scheduled task → today start/complete → analytics → schedule', async ({ page }) => {
        // Real generation on a local 7B model may pay a cold model load;
        // tripled budget like golden journey G2.
        test.slow();
        await login(page);

        // ---- GOAL → AI → MILESTONES -------------------------------------
        const goalName = unique('cj-goal');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(goalName);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
        await page.getByTestId('goal-breakdown-ai').click();

        // Inline proposal review (P17-026) — no navigation away from Goals.
        await expect(page.getByTestId('proposal-review')).toBeVisible({ timeout: 120_000 });
        const milestoneCount = await page.getByTestId('proposal-milestones').locator('li').count();
        expect(milestoneCount).toBeGreaterThan(0);

        // Approval gate (FR-62): edit before accepting.
        await page.getByTestId('proposal-edit').click();
        const firstTitle = page.getByTestId('proposal-milestone-title-0');
        const milestoneTitle = `${await firstTitle.inputValue()} (edited)`;
        await firstTitle.fill(milestoneTitle);
        await page.getByTestId('proposal-save-edits').click();
        await expect(page.getByTestId('proposal-edited-badge')).toBeVisible();
        await page.getByTestId('proposal-accept').click();
        await expect(page.getByTestId('goal-proposal-accepted')).toBeVisible({ timeout: 15_000 });

        // Accepted milestones live on the goal.
        await page.getByTestId('goal-breakdown-open').click();
        await expect(page.getByTestId('goal-detail')).toBeVisible();
        await expect(page.getByTestId('goal-milestones')).toContainText(milestoneTitle, { timeout: 15_000 });

        // ---- PROGRAM ------------------------------------------------------
        await page.getByTestId('goal-detail-back').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        const programName = unique('cj-program');
        await page.getByTestId('program-create-name').fill(programName);
        await page.getByTestId('program-create-workload').selectOption('flexible');
        await page.getByTestId('program-create-submit').click();
        await expect(
            page.getByTestId('program-list').getByTestId('program-item').filter({ hasText: programName }),
        ).toHaveCount(1);

        // ---- TASK → SCHEDULE (Quick Capture with date + links) ------------
        // A future day always has free capacity; computed BEFORE any clock
        // installation so it stays a real future date.
        const day = isoDate(4 + (Date.now() % 10));
        const taskTitle = unique('cj-task');
        await page.getByTestId('global-quick-capture').click();
        await expect(page.getByTestId('quick-capture-modal')).toBeVisible();
        await page.getByTestId('qc-title').fill(taskTitle);
        await page.getByTestId('qc-priority').selectOption('3');
        await page.getByTestId('qc-duration').fill('45');
        await page.getByTestId('qc-date').fill(day);
        await page.getByTestId('qc-program').selectOption({ label: programName });
        await page.getByTestId('qc-goal').selectOption({ label: goalName });
        // Milestones load per goal once the goal is chosen.
        await page.getByTestId('qc-milestone').selectOption({ label: milestoneTitle });
        await page.getByTestId('qc-submit').click();
        await expect(page.getByTestId('qc-placed')).toBeVisible({ timeout: 20_000 });

        // Exact slot for the clock install (capture panel shows a formatted
        // time only).
        const view = (await apiFetch(page, `${API}/schedule?date=${day}`)) as {
            events?: Array<{ task?: { title?: string } | null; assignment?: { start_at?: string } | null }>;
        };
        const mine = (view.events ?? []).find((e) => e.task?.title === taskTitle);
        if (!mine?.assignment?.start_at) {
            throw new Error(`canonical journey task not placed on ${day}: ${JSON.stringify(view.events?.length ?? 0)} events`);
        }
        const slotStart = new Date(mine.assignment.start_at as string);

        // ---- TODAY (NOW) + START + PROGRESS + COMPLETE --------------------
        await page.clock.install({ time: new Date(slotStart.getTime() + 60_000) });
        await page.goto('/app');
        await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('now-card')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('now-title')).toHaveText(taskTitle);

        const counts = await page
            .getByTestId('today-progress-count')
            .innerText()
            .then((s) => /([\d]+)\s*\/\s*([\d]+)/.exec(s));
        if (!counts) throw new Error('progress strip missing counts');

        await page.getByTestId('execution-start').click();
        await expect(page.getByTestId('execution-status')).toHaveText('Running', { timeout: 15_000 });

        // Elapsed accrues from the server-side start timestamp.
        await page.clock.setSystemTime(new Date());
        await expect(page.getByTestId('execution-elapsed')).not.toHaveText('00:00', { timeout: 20_000 });
        await page.getByTestId('execution-complete').click();
        await expect(page.getByTestId('toast-host')).toContainText('Task completed', { timeout: 15_000 });

        // PROGRESS: exactly one more task done on the shared day.
        await expect(page.getByTestId('today-progress-count')).toContainText(
            `${Number(counts[1]) + 1}/${counts[2]}`,
            { timeout: 20_000 },
        );

        // ---- ANALYTICS → ADAPTATION ---------------------------------------
        // Back to the seeded day so the default 7-day range covers the
        // completed session and its assignment.
        await page.clock.setSystemTime(new Date(slotStart.getTime() + 60_000));
        await page.getByTestId('nav-analytics').click();
        await expect(page.getByTestId('analytics-presets')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('analytics-summary')).toBeVisible({ timeout: 30_000 });

        // Adaptation loop closes: capacity recommendation lands in Schedule.
        await expect(page.getByTestId('analytics-capacity')).toBeVisible({ timeout: 30_000 });
        await page.getByTestId('analytics-review-schedule').click();
        await expect(page.getByTestId('schedule-draft-view')).toBeVisible({ timeout: 30_000 });
    });
});
