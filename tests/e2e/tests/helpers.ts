import { test as base, type Page } from '@playwright/test';

export const OWNER_EMAIL = 'sampalan@gmail.com';
export const OWNER_PASSWORD = process.env.E2E_OWNER_PASSWORD || 'KinevoE2E123!';

/** Stable unique suffix so repeated journey runs create distinct fixtures. */
export function unique(prefix: string): string {
    const ts = Date.now().toString(36);
    const rand = Math.random().toString(36).slice(2, 6);
    return `${prefix}-${ts}-${rand}`;
}

/**
 * Log into the SPA through the real login form and wait for the Today
 * surface to render (the primary authenticated landing view).
 *
 * Tolerates an existing session: an in-test `page.reload()` keeps the
 * stored token, so the shell renders directly without a login form
 * (golden journey H).
 */
export async function login(page: Page): Promise<void> {
    await page.goto('/app');
    try {
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 5_000 });
        return;
    } catch {
        // Not authenticated yet — take the real login path below.
    }
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
