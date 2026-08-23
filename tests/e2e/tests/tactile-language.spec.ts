import { test, expect } from '@playwright/test';
import { OWNER_EMAIL, OWNER_PASSWORD } from './helpers';

/**
 * TASK-P17-012 — neo-brutalist tactile language (design.md §104):
 * interactive primaries rest at a 4px offset shadow, hover 6px, press 2px.
 * Proven against the live scheduler surface (the last raw-button holdout,
 * finding UI-013) rather than mocked DOM.
 */
test('scheduler actions carry the offset-shadow language (TASK-P17-012)', async ({ page }) => {
    await page.goto('/app');
    await page.getByTestId('login-email').fill(OWNER_EMAIL);
    await page.getByTestId('login-password').fill(OWNER_PASSWORD);
    await page.getByTestId('login-form').getByRole('button', { name: /log in/i }).click();
    await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
    await page.getByTestId('nav-schedule').click();
    await page.getByTestId('schedule-draft-view').waitFor({ state: 'visible' });
    const btn = page.getByTestId('draft-generate');
    await expect(btn).toBeVisible({ timeout: 15_000 });
    // rest = 4px offset
    await expect(btn).toHaveCSS('box-shadow', /4px 4px/);
    // hover = 6px offset
    await btn.hover();
    await expect(btn).toHaveCSS('box-shadow', /6px 6px/);
    // pressed = 2px offset (real pointer press sets :active)
    await page.mouse.down();
    await expect(btn).toHaveCSS('box-shadow', /2px 2px/, { timeout: 5000 });
    await page.mouse.up();
});
