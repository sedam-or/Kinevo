import { test, expect } from '@playwright/test';
import { login } from './helpers';

/**
 * TASK-P20-033 — Command Palette browser proof.
 * Ctrl/Cmd+Shift+K opens the unified palette; navigation command executes
 * and closes; Escape dismisses. Knowledge search reuses the existing API.
 */
test.describe('P20 command palette journey', () => {
    test('opens, navigates via command, escape dismisses', async ({ page }) => {
        await login(page);

        // Open with the real shortcut.
        await page.keyboard.press('ControlOrMeta+Shift+KeyK');
        await expect(page.getByTestId('command-palette')).toBeVisible();

        // Navigation commands are listed.
        await expect(page.getByTestId('command-nav-goals')).toBeVisible();
        await expect(page.getByTestId('command-nav-today')).toBeVisible();

        // Execute one: jump to Goals.
        await page.getByTestId('command-nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible({ timeout: 15_000 });
        await expect(page.getByTestId('command-palette')).toBeHidden();

        // Reopen and dismiss with Escape.
        await page.keyboard.press('ControlOrMeta+Shift+KeyK');
        await expect(page.getByTestId('command-palette')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByTestId('command-palette')).toBeHidden();
    });
});
