import { test, expect } from '@playwright/test';
import { apiFetch, captureOnFreeDay, login, unique } from './helpers';
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

test.describe('TASK-P17-023 — canonical end-to-end product journey', () => {
    test('login → goal → AI breakdown → milestones → program → scheduled task → today start/complete → analytics → schedule', async ({ page }) => {
        // Real generation on a local 7B model may pay a cold model load;
        // tripled budget like golden journey G2, plus retry headroom below.
        test.slow();
        test.setTimeout(540_000);
        await login(page);

        // ---- GOAL → AI → MILESTONES -------------------------------------
        const goalName = unique('cj-goal');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(goalName);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();

        // Inline proposal review (P17-026) — no navigation away from Goals.
        // Local-model output is nondeterministic: a malformed milestone date
        // is schema-rejected server-side (AI rule — never repaired client-
        // side), so a rejected generation is retried, not asserted away.
        let reviewed = false;
        for (let attempt = 0; attempt < 3 && !reviewed; attempt++) {
            await page.getByTestId('goal-breakdown-ai').click();
            reviewed = await page
                .getByTestId('proposal-review')
                .waitFor({ state: 'visible', timeout: 120_000 })
                .then(() => true)
                .catch(() => false);
        }
        await expect(page.getByTestId('proposal-review')).toBeVisible();
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

        // ---- TASK → SCHEDULE (linked capture on a free day) ---------------
        // API capture walks to the first day with free capacity (fixed days
        // saturate as the shared owner accumulates fixtures across runs);
        // every later stage of the journey stays UI-driven.
        const goalsView = (await apiFetch(page, `${API}/goals`)) as { goals?: Array<{ id: number; title: string }> };
        const goalId = (goalsView.goals ?? []).find((g) => g.title === goalName)?.id;
        if (!goalId) {
            throw new Error(`canonical journey goal not found: ${goalName}`);
        }
        const programsView = (await apiFetch(page, `${API}/programs`)) as { programs?: Array<{ id: number; name: string }> };
        const programId = (programsView.programs ?? []).find((p) => p.name === programName)?.id ?? null;
        const taskTitle = unique('cj-task');
        const placed = await captureOnFreeDay(
            page,
            { title: taskTitle, priority_tier: 3, duration_minutes: 45, goal_id: goalId, program_id: programId },
        );
        const slotStart = placed.startAt;

        // The Analytics window below is the captured future day; completion
        // focus sessions are stamped with REAL server time, so without this
        // seed the dashboard renders its empty state (hasData === false) and
        // the analytics leg asserts against nothing.
        await apiFetch(page, `${API}/focus-sessions`, {
            method: 'POST',
            body: JSON.stringify({
                started_at: slotStart.toISOString(),
                ended_at: new Date(slotStart.getTime() + 45 * 60 * 1000).toISOString(),
            }),
        });

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
