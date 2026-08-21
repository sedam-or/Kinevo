import { test, expect } from '@playwright/test';
import { login, OWNER_EMAIL, OWNER_PASSWORD } from './helpers';

test.describe('R1 core loop — LOGIN → TODAY', () => {
    test('can log in and land on the Today surface', async ({ page }) => {
        await page.goto('/app');
        await page.getByTestId('login-form').waitFor({ state: 'visible' });
        await page.getByTestId('login-email').fill(OWNER_EMAIL);
        await page.getByTestId('login-password').fill(OWNER_PASSWORD);
        await page.getByTestId('login-form').getByRole('button', { name: /log in/i }).click();

        await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('sync-state')).toBeVisible();
    });

    test('rejects an invalid password', async ({ page }) => {
        await page.goto('/app');
        await page.getByTestId('login-form').waitFor({ state: 'visible' });
        await page.getByTestId('login-email').fill(OWNER_EMAIL);
        await page.getByTestId('login-password').fill('definitely-wrong-password');
        await page.getByTestId('login-form').getByRole('button', { name: /log in/i }).click();

        await expect(page.getByTestId('login-error')).toBeVisible({ timeout: 15_000 });
    });
});
