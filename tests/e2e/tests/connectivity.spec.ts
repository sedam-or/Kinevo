import { test, expect } from '@playwright/test';
import { captureOnFreeDay, login, unique } from './helpers';

/**
 * TASK-P17-024 — Feature Interconnectivity Audit (§104 matrix, Downstream
 * column). For each walked pair the user must be able to:
 *
 *   navigate to the related object → understand the relationship (the target
 *   names the origin) → perform a next meaningful action there → return.
 *
 * Extends TASK-P17-002 continuity (which proved chips render) with the
 * LINKED-task upstream walk and the knowledge-link graph — both previously
 * unit-covered only.
 */

function isoDate(daysFromNow: number): string {
    const d = new Date();
    d.setDate(d.getDate() + daysFromNow);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

test.describe('P17-024 feature interconnectivity walk', () => {
    test('linked task walks upstream to its goal, acts, and returns', async ({ page }) => {
        await login(page);

        // A goal plus a task linked to it through Quick Capture. Far-future
        // day: the shared dev owner's near days accumulate fixtures across
        // suite runs, so walk deep into the safety reserve instead.
        const goalName = unique('conn-goal');
        const day = isoDate(11 + (Date.now() % 14));
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(goalName);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();

        const taskTitle = unique('conn-task');
        const token = await page.evaluate(() => localStorage.getItem('kinevo.auth.token'));
        const goalsView = (await page.evaluate(async (t) => {
            const res = await fetch('/api/v1/goals', { headers: { Accept: 'application/json', Authorization: `Bearer ${t}` } });
            return res.json();
        }, token)) as { goals?: Array<{ id: number; title: string }> };
        const goalId = (goalsView.goals ?? []).find((g) => g.title === goalName)?.id;
        if (!goalId) {
            throw new Error(`connectivity goal not found: ${goalName}`);
        }
        await captureOnFreeDay(page, { title: taskTitle, priority_tier: 3, duration_minutes: 45, goal_id: goalId });

        // Open the task detail — the Goal chip must name the relationship.
        await page.getByTestId('nav-tasks').click();
        await expect(page.getByTestId('task-view')).toBeVisible();
        await page.getByTestId('task-item').filter({ hasText: taskTitle }).first().getByTestId('task-open').click();
        await expect(page.getByTestId('task-detail')).toBeVisible();
        const goalChip = page.getByTestId('entity-links').getByTestId('entity-link-goals');
        await expect(goalChip).toBeVisible();
        await expect(goalChip).toHaveText('Goal');

        // Navigate upstream: deep-open lands on THIS goal, not the list.
        await goalChip.click();
        await expect(page.getByTestId('goal-detail')).toBeVisible({ timeout: 15_000 });
        await expect(page.getByTestId('goal-detail-title')).toContainText(goalName);

        // Next meaningful action on the target surface: plan a milestone.
        await page.getByTestId('milestone-title').fill('Upstream action milestone');
        await page.getByTestId('milestone-add').click();
        await expect(
            page.getByTestId('goal-milestones').getByTestId('milestone-item').filter({ hasText: 'Upstream action milestone' }),
        ).toHaveCount(1);

        // Return path: back to Tasks, reopen the same task intact.
        await page.getByTestId('goal-detail-back').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('nav-tasks').click();
        await page.getByTestId('task-item').filter({ hasText: taskTitle }).first().getByTestId('task-open').click();
        await expect(page.getByTestId('task-detail')).toBeVisible();
        await expect(page.getByTestId('entity-link-goals')).toBeVisible();
    });

    test('goal walks downstream to Tasks, acts, and returns', async ({ page }) => {
        await login(page);
        const goalName = unique('conn-dwn');

        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(goalName);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
        await page.getByTestId('goal-breakdown-manual').click();
        await expect(page.getByTestId('goal-detail')).toBeVisible();

        // Downstream chip lands on Tasks; act by triaging a new task there.
        await page.getByTestId('entity-link-tasks').click();
        await expect(page.getByTestId('task-view')).toBeVisible();
        const taskTitle = unique('conn-dwn-task');
        await page.getByTestId('task-create-title').fill(taskTitle);
        await page.getByTestId('task-create-submit').click();
        await expect(page.getByTestId('task-list').getByText(taskTitle, { exact: false })).toBeVisible();

        // Return to the originating object.
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-item').filter({ hasText: goalName }).first().getByTestId('goal-open').click();
        await expect(page.getByTestId('goal-detail-title')).toContainText(goalName);
    });

    test('note links to a goal; the link opens the goal and survives return', async ({ page }) => {
        await login(page);

        const goalName = unique('conn-link-goal');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(goalName);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();

        // Create the note; it opens in the editor where LinkManager lives.
        const noteTitle = unique('conn-note');
        await page.getByTestId('nav-knowledge').click();
        await expect(page.getByTestId('notes-view')).toBeVisible();
        await page.getByTestId('note-create-title').fill(noteTitle);
        await page.getByTestId('note-create-submit').click();
        await expect(page.getByTestId('note-detail')).toBeVisible();

        // Understand + create the relationship: note → goal.
        await page.getByTestId('link-target-type').selectOption('goal');
        await page.getByTestId('link-target-id').selectOption({ label: goalName });
        await page.getByTestId('link-type').selectOption('related_to');
        await page.getByTestId('link-submit').click();
        const linkItem = page.getByTestId('knowledge-links').getByTestId('knowledge-link-item').filter({ hasText: goalName });
        await expect(linkItem).toHaveCount(1);

        // The link navigates to the related object (deep-open).
        await linkItem.getByTestId(/link-open-goal-\d+/).click();
        await expect(page.getByTestId('goal-detail')).toBeVisible({ timeout: 15_000 });
        await expect(page.getByTestId('goal-detail-title')).toContainText(goalName);

        // Return: the note still carries the relationship.
        await page.getByTestId('nav-knowledge').click();
        await expect(page.getByTestId('notes-view')).toBeVisible();
        await page.getByTestId('note-item').filter({ hasText: noteTitle }).first().getByTestId('note-open').click();
        await expect(page.getByTestId('note-detail')).toBeVisible();
        await expect(
            page.getByTestId('knowledge-links').getByTestId('knowledge-link-item').filter({ hasText: goalName }),
        ).toHaveCount(1);
    });
});
