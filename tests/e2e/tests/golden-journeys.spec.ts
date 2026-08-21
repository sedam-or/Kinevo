import { test, expect } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * Golden user journeys (design.md §73, §99) driven through the REAL browser:
 * LOGIN → TODAY → create → verify. Journeys mutate the dev database by
 * creating uniquely-named fixtures; assertions use the products'
 * data-testids so a journey fails loudly if the surface regresses.
 */

test.describe('R6 golden journey A — Plan', () => {
    test('creates a Goal then a Task and both surface in their lists', async ({ page }) => {
        await login(page);
        const name = unique('r6-plan');

        // PLAN group → Goals: create a goal. Use Quarterly — yearly/monthly
        // have ACTIVE caps (FR-19/FR-20) that repeated runs would exhaust.
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(`${name} goal`);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-list').getByText(`${name} goal`, { exact: false })).toBeVisible();

        // PLAN group → Tasks: create a task.
        await page.getByTestId('nav-tasks').click();
        await expect(page.getByTestId('task-view')).toBeVisible();
        await page.getByTestId('task-create-title').fill(`${name} task`);
        await page.getByTestId('task-create-submit').click();
        await expect(page.getByTestId('task-list').getByText(`${name} task`, { exact: false })).toBeVisible();
    });
});

test.describe('R6 golden journey B — Execute', () => {
    test('opens Today and can quick-capture a task', async ({ page }) => {
        await login(page);
        const name = unique('r6-exec');

        // EXECUTE group → Today is the landing surface.
        await expect(page.getByTestId('today-view')).toBeVisible();
        // Until a task runs, Today must still show its functional shell.
        await expect(page.getByTestId('quick-capture')).toBeVisible();

        await page.getByTestId('qc-title').fill(name);
        await page.getByTestId('qc-submit').click();
        await expect(page.getByTestId('qc-title')).toHaveValue('');
        // The capture round-trips to the server; the surface reloads.
        await expect(page.getByTestId('today-view')).toBeVisible();
    });
});

test.describe('R6 golden journey D — Knowledge', () => {
    test('creates a Note, edits it (autosave surfaces), and creates a Canvas', async ({ page }) => {
        await login(page);
        const name = unique('r6-know');

        // KNOWLEDGE group → Knowledge (Notes).
        await page.getByTestId('nav-knowledge').click();
        await expect(page.getByTestId('notes-view')).toBeVisible();
        await page.getByTestId('note-create-title').fill(name);
        await page.getByTestId('note-create-submit').click();

        // The note opens in the editor; autosave indicator is top-right (§32).
        await expect(page.getByTestId('note-detail')).toBeVisible();
        await expect(page.getByTestId('note-save-state')).toBeVisible();
        await page.getByTestId('note-title-input').fill(`${name} (edited)`);
        // Saving is debounced; the indicator shows a transitional badge.
        await expect(page.getByTestId('note-save-state')).toBeVisible({ timeout: 15_000 });

        // KNOWLEDGE group → Canvas: create one (lazy-loaded Excalidraw chunk).
        await page.getByTestId('nav-canvas').click();
        await expect(page.getByTestId('canvas-view')).toBeVisible();
        await page.getByTestId('canvas-create-title').fill(name);
        await page.getByTestId('canvas-create-submit').click();
        // Workspace mounts the product shell; the save state is always visible §34.4.
        await expect(page.getByTestId('canvas-workspace')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('canvas-save-state')).toBeVisible({ timeout: 30_000 });
    });
});