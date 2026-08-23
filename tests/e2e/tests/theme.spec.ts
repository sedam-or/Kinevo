import { test, expect, type Page } from '@playwright/test';
import { login } from './helpers';

/**
 * TASK-P17-013 — theme hardening proven in real browsers (design.md §5.3/
 * §5.4, docs/browser-e2e.md §11 Phase 17 verification item):
 *
 *   light → reload → light; dark → reload → dark (persisted, no pre-hydration
 *   flash); system → switch OS → theme follows live; Excalidraw shell adapts;
 *   native controls readable via color-scheme; keyboard accessible; unauth
 *   gate + mobile width covered.
 */

async function themeLabel(page: Page): Promise<string> {
    return (await page.getByTestId('theme-toggle').innerText()).trim();
}

async function cycleTo(page: Page, target: 'light' | 'dark' | 'system'): Promise<void> {
    for (let i = 0; i < 3; i++) {
        if ((await themeLabel(page)).endsWith(target)) {
            return;
        }
        await page.getByTestId('theme-toggle').click();
    }
    throw new Error(`could not reach theme ${target}`);
}

test.describe('Theme journey (TASK-P17-013)', () => {
    test('unauth gate: toggle reachable and switches theme', async ({ page }) => {
        await page.goto('/app');
        const toggle = page.getByTestId('theme-toggle');
        await expect(toggle).toBeVisible({ timeout: 15_000 });

        // Default preference is 'system'; cycle until the dark preference is
        // reached (system -> light -> dark) and prove the gate re-themes.
        for (let i = 0; i < 2; i++) {
            if ((await page.evaluate(() => document.documentElement.classList.contains('dark')))) {
                break;
            }
            await toggle.click();
        }
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(toggle).toContainText('dark');
    });

    test('light → reload → light; dark → reload → dark without flash; persists', async ({ page }) => {
        await login(page);
        // Capture whether the .dark class was already applied at parse time
        // (the inline head script runs before hydration).
        await page.addInitScript(() => {
            window.addEventListener('DOMContentLoaded', () => {
                (window as unknown as { __kinevoThemeAtStart: boolean }).__kinevoThemeAtStart =
                    document.documentElement.classList.contains('dark');
            });
        });

        await cycleTo(page, 'light');
        await expect(page.locator('html')).not.toHaveClass(/dark/);
        await page.reload();
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
        expect(await page.evaluate(() => (window as unknown as { __kinevoThemeAtStart: boolean }).__kinevoThemeAtStart)).toBe(false);
        await expect(page.locator('html')).not.toHaveClass(/dark/);
        // Native controls stay readable: color-scheme follows the theme.
        await expect(page.locator('html')).toHaveCSS('color-scheme', 'light');

        await cycleTo(page, 'dark');
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(page.locator('html')).toHaveCSS('color-scheme', 'dark');
        await page.reload();
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
        expect(await page.evaluate(() => (window as unknown as { __kinevoThemeAtStart: boolean }).__kinevoThemeAtStart)).toBe(true);
        await expect(page.locator('html')).toHaveClass(/dark/);

        await cycleTo(page, 'light');
    });

    test('system mode follows live OS switches', async ({ page }) => {
        await login(page);
        await cycleTo(page, 'system');
        await page.emulateMedia({ colorScheme: 'dark' });
        await expect(page.locator('html')).toHaveClass(/dark/, { timeout: 10_000 });
        await page.emulateMedia({ colorScheme: 'light' });
        await expect(page.locator('html')).not.toHaveClass(/dark/, { timeout: 10_000 });
    });

    test('toggle is keyboard operable', async ({ page }) => {
        await login(page);
        const toggle = page.getByTestId('theme-toggle');
        await toggle.focus();
        await expect(toggle).toBeFocused();
        const before = await themeLabel(page);
        await page.keyboard.press('Enter');
        expect(await themeLabel(page)).not.toBe(before);
    });

    test('Excalidraw shell adapts to the app theme', async ({ page }) => {
        await login(page);
        await page.getByTestId('nav-canvas').click();
        await page.getByTestId('canvas-create-title').fill('Theme journey board');
        await page.getByTestId('canvas-create-submit').click();
        await page.getByTestId('canvas-workspace').waitFor({ state: 'visible', timeout: 30_000 });
        await page.locator('.kinevo-canvas-island .excalidraw').first().waitFor({ timeout: 30_000 });

        await cycleTo(page, 'dark');
        await expect(page.locator('.kinevo-canvas-island .excalidraw.theme--dark').first()).toBeVisible({ timeout: 15_000 });
        await cycleTo(page, 'light');
        // Light is Excalidraw's default — the dark modifier must be gone.
        await expect(page.locator('.kinevo-canvas-island .theme--dark')).toHaveCount(0, { timeout: 15_000 });
        await cycleTo(page, 'dark');
    });

    test('mobile 375px: theme control reachable on the shell', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });
        await login(page);
        const toggle = page.getByTestId('theme-toggle');
        await expect(toggle).toBeVisible();
        await toggle.click();
    });
});
