import { test as base, type Page } from '@playwright/test';

export const OWNER_EMAIL = 'sampalan@gmail.com';
export const OWNER_PASSWORD = process.env.E2E_OWNER_PASSWORD || 'KinevoE2E123!';

/**
 * Log into the SPA through the real login form and wait for the Today
 * surface to render (the primary authenticated landing view).
 */
export async function login(page: Page): Promise<void> {
    await page.goto('/app');
    await page.getByTestId('login-form').waitFor({ state: 'visible' });
    await page.getByTestId('login-email').fill(OWNER_EMAIL);
    await page.getByTestId('login-password').fill(OWNER_PASSWORD);
    await page.getByTestId('login-form').getByRole('button', { name: /log in/i }).click();
    // Primary landing view is Today.
    await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
}

export function asAuthenticated(title: string, fn: (page: Page) => Promise<void>): ReturnType<typeof base> {
    return base(title, async ({ page }) => {
        await login(page);
        await fn(page);
    });
}
