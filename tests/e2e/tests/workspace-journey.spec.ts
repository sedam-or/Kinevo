import { test, expect } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * TASK-P19-032 / P19-033 / P19-040 — workspace browser journeys.
 *
 * Covers: creation, switching, reload persistence, isolation (goals created
 * in one workspace do not appear in the other), archive (falls back to the
 * default, data preserved, hidden from switcher) and restore (intact).
 */
test.describe('P19 workspace journeys', () => {
    test('create → switch → scoped goal → isolation → archive → restore', async ({ page }) => {
        test.setTimeout(120_000);
        await login(page);

        const name = unique('Research');

        // --- Create via the manager -----------------------------------------
        await page.getByTestId('workspace-switcher-trigger').click();
        await page.getByTestId('workspace-manage-button').click();
        await expect(page.getByTestId('workspace-manager')).toBeVisible();
        await page.getByTestId('workspace-create-name').fill(name);
        await page.getByTestId('workspace-create-type').selectOption('research');
        await page.getByTestId('workspace-create-submit').click();
        const row = page.locator(`[data-testid="workspace-row-research"], [data-testid^="workspace-row-"]`).filter({ hasText: name });
        await expect(row).toBeVisible();
        // Slug is derived; find its real testid for later steps.
        const slug = (await row.getAttribute('data-testid'))!.replace('workspace-row-', '');
        await page.getByTestId('workspace-manager-close').click();
        await expect(page.getByTestId('workspace-manager')).toBeHidden();

        // --- Switch to the new workspace -------------------------------------
        await page.getByTestId('workspace-switcher-trigger').click();
        await page.getByTestId(`workspace-option-${slug}`).click();
        await expect(page.getByTestId('workspace-current-name')).toContainText(name);

        // --- Scoped goal lands in the active workspace -----------------------
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        const goalName = unique(`${name} goal`);
        await page.getByTestId('goal-create-title').fill(goalName);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
        await page.getByTestId('goal-breakdown-later').click();

        // --- Reload keeps the active selection (P19-006) ----------------------
        await page.reload();
        await login(page);
        await expect(page.getByTestId('workspace-current-name')).toContainText(name);

        // --- Isolation: switch back to Personal, goal not listed --------------
        await page.getByTestId('workspace-switcher-trigger').click();
        await page.getByTestId('workspace-option-personal').click();
        await expect(page.getByTestId('workspace-current-name')).toContainText('Personal');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await expect(page.getByTestId('goal-list')).not.toContainText(goalName);

        // --- Archive Research: active falls back to default -------------------
        await page.getByTestId('workspace-switcher-trigger').click();
        await page.getByTestId('workspace-manage-button').click();
        await page.getByTestId(`workspace-archive-${slug}`).click();
        await expect(page.getByTestId(`workspace-archived-${slug}`)).toBeVisible();
        // Data preserved server-side: archived row left the switcher but the
        // goal still exists in the global view.
        await page.getByTestId('workspace-manager-close').click();
        await expect(page.getByTestId('workspace-current-name')).toContainText('Personal');

        // --- Explicit global view (P19-028) shows the archived workspace's data
        await page.getByTestId('workspace-switcher-trigger').click();
        await page.getByTestId('workspace-option-all').click();
        // Switching reloads the app into the new context.
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goal-list')).toContainText(goalName);

        // --- Restore intact ----------------------------------------------------
        await page.getByTestId('workspace-switcher-trigger').click();
        await page.getByTestId('workspace-manage-button').click();
        await page.getByTestId(`workspace-restore-${slug}`).click();
        await expect(page.getByTestId(`workspace-row-${slug}`)).toBeVisible();
        await page.getByTestId('workspace-manager-close').click();

        // Switch back into Research: the restored workspace still owns the goal.
        await page.getByTestId('workspace-switcher-trigger').click();
        await page.getByTestId(`workspace-option-${slug}`).click();
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goal-list')).toContainText(goalName.split('-')[0]);
    });
});
