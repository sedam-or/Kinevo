import { test, expect } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * TASK-P17-002 — Workflow Continuity Layer (design.md §104 P17-B).
 *
 * Proves in the real browser that entity pages are no longer dead ends:
 * a goal detail offers its downstream execution surfaces, and a task detail
 * offers upstream planning context plus downstream knowledge/execution
 * surfaces. Deep-open plumbing (focus targets) is unit-covered; here we
 * verify the visible continuity and navigation hand-off.
 */

test.describe('P17-002 workflow continuity', () => {
    test('goal detail links to downstream execution surfaces', async ({ page }) => {
        await login(page);
        const name = unique('p17-cont');

        // Create a goal (Quarterly — ACTIVE caps on yearly/monthly, FR-19/20).
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(`${name} goal`);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-list').getByText(`${name} goal`, { exact: false })).toBeVisible();

        // Open its detail — continuity strip must be present.
        await page.getByTestId('goal-item').filter({ hasText: `${name} goal` }).first().getByTestId('goal-open').click();
        await expect(page.getByTestId('goal-detail')).toBeVisible();
        await expect(page.getByTestId('entity-links')).toBeVisible();

        // Downstream chips navigate to their surfaces.
        await page.getByTestId('entity-link-tasks').click();
        await expect(page.getByTestId('task-view')).toBeVisible();

        await page.getByTestId('nav-goals').click();
        await page.getByTestId('goal-item').filter({ hasText: `${name} goal` }).first().getByTestId('goal-open').click();
        await page.getByTestId('entity-link-schedule').click();
        await expect(page.getByTestId('schedule-draft-view')).toBeVisible();

        await page.getByTestId('nav-goals').click();
        await page.getByTestId('goal-item').filter({ hasText: `${name} goal` }).first().getByTestId('goal-open').click();
        await page.getByTestId('entity-link-analytics').click();
        await expect(page.getByTestId('analytics-presets')).toBeVisible();
    });

    test('task detail links to schedule, knowledge, and canvas surfaces', async ({ page }) => {
        await login(page);
        const name = unique('p17-task');

        await page.getByTestId('nav-tasks').click();
        await expect(page.getByTestId('task-view')).toBeVisible();
        await page.getByTestId('task-create-title').fill(`${name} task`);
        await page.getByTestId('task-create-submit').click();
        await expect(page.getByTestId('task-list').getByText(`${name} task`, { exact: false })).toBeVisible();

        // Open the task detail.
        await page.getByTestId('task-item').filter({ hasText: `${name} task` }).first().getByTestId('task-open').click();
        await expect(page.getByTestId('task-detail')).toBeVisible();

        // Unlinked task: downstream surfaces present, no Goal chip.
        await expect(page.getByTestId('entity-links')).toBeVisible();
        await expect(page.getByTestId('entity-link-schedule')).toBeVisible();
        await expect(page.getByTestId('entity-link-knowledge')).toBeVisible();
        await expect(page.getByTestId('entity-link-canvas')).toBeVisible();
        await expect(page.getByTestId('entity-link-goals')).toHaveCount(0);

        // Leave the detail so each loop iteration re-enters through the list
        // (nav-tasks is a no-op while the Tasks surface is already active).
        await page.getByTestId('task-detail-back').click();
        await expect(page.getByTestId('task-list')).toBeVisible();

        for (const [chip, surface] of [
            ['entity-link-schedule', 'schedule-draft-view'],
            ['entity-link-knowledge', 'notes-view'],
            ['entity-link-canvas', 'canvas-view'],
        ] as const) {
            await page.getByTestId('nav-tasks').click();
            await page.getByTestId('task-item').filter({ hasText: `${name} task` }).first().getByTestId('task-open').click();
            await page.getByTestId(chip).click();
            await expect(page.getByTestId(surface)).toBeVisible();
        }
    });
});
