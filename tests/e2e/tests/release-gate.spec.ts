import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { login } from './helpers';

/**
 * TASK-R7 — Release-readiness gate proofs (design.md §102).
 *
 * Closes the remaining audit rows that earlier phases left open:
 * - Canvas keyboard-only flow (ui-audit §4 Canvas 🟡 → proven);
 * - Mobile viewport smoke (§8 responsive row; 375px, no horizontal overflow);
 * - Dark mode WCAG scans (§5.4 dark tokens under emulated colorScheme);
 * - Screen-reader smoke for state regions (§90/§45 live semantics).
 *
 * Excalidraw internals stay excluded from axe scope (external engine
 * boundary); Kinevo owns the shell chrome around them.
 */

test.describe.configure({ timeout: 120_000 });

async function axeIds(page: Page): Promise<string[]> {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .exclude('.kinevo-canvas-island')
        .analyze();
    return results.violations
        .filter((v) => v.impact === 'critical' || v.impact === 'serious')
        .map((v) => `${v.id} -> ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`);
}

async function openCanvasWorkspace(page: Page): Promise<void> {
    await page.getByTestId('nav-canvas').click();
    await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });
    await page.getByTestId('canvas-create-title').fill('R7 gate canvas');
    await page.getByTestId('canvas-create-submit').click();
    await page.getByTestId('canvas-workspace').waitFor({ timeout: 45_000 });
}

test.describe('R7 — Canvas keyboard-only flow (§46)', () => {
    test('canvas is creatable, leavable, and never strands the keyboard user', async ({ page }) => {
        await login(page);
        await page.getByTestId('today-view').waitFor({ timeout: 30_000 });

        // Keyboard-only: reach the Canvas nav button by tabbing, activate with
        // Enter (no canvas G-chord exists — §46 chords cover T/W/C/G/K).
        let reached = false;
        for (let i = 0; i < 40 && !reached; i++) {
            await page.keyboard.press('Tab');
            const focused = await page.evaluate(() => {
                const el = document.activeElement as HTMLElement | null;
                return el?.getAttribute('data-testid') ?? '';
            });
            if (focused === 'nav-canvas') {
                reached = true;
            }
        }
        expect(reached, 'nav-canvas must be keyboard-reachable').toBe(true);
        await page.keyboard.press('Enter');
        await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });

        // Create a canvas without touching the mouse.
        await page.getByTestId('canvas-create-title').focus();
        await page.keyboard.type('R7 keyboard canvas');
        await page.getByTestId('canvas-create-submit').focus();
        await page.keyboard.press('Enter');
        await page.getByTestId('canvas-workspace').waitFor({ timeout: 45_000 });

        // The workspace must expose a visible focus target and a keyboard
        // exit path back to the list.
        await page.getByTestId('canvas-back').focus();
        await expect(page.getByTestId('canvas-back')).toBeFocused();
        await page.keyboard.press('Enter');
        await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });
    });
});

test.describe('R7 — mobile viewport smoke (§8, §58)', () => {
    const MOBILE = { width: 375, height: 667 };
    /** P17-001 IA: mobile bottom bar carries the primary subset; Canvas and
     * Schedule live behind the More drawer (key → primary?). */
    const MOBILE_NAV: Array<[string, boolean]> = [
        ['tasks', true],
        ['goals', true],
        ['knowledge', true],
        ['canvas', false],
        ['schedule', false],
    ];

    async function openMobileNavItem(page: Page, key: string, primary: boolean): Promise<void> {
        if (primary) {
            await page.getByTestId(`mobile-nav-${key}`).click();
            return;
        }
        await page.getByTestId('mobile-more-toggle').click();
        await page.getByTestId(`more-${key}`).click();
    }

    async function noHorizontalOverflow(page: Page): Promise<boolean> {
        return page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        );
    }

    test('core surfaces render without horizontal overflow at 375px', async ({ browser }) => {
        const context = await browser.newContext({ viewport: MOBILE });
        const page = await context.newPage();
        await login(page);
        await page.getByTestId('today-view').waitFor({ timeout: 30_000 });
        // The desktop rail hides on small screens; the bottom bar takes over.
        await expect(page.getByTestId('mobile-nav')).toBeVisible();

        for (const [key, primary] of MOBILE_NAV) {
            await openMobileNavItem(page, key, primary);
            await page.waitForTimeout(400);
            expect(await noHorizontalOverflow(page), `${key} overflows at 375px`).toBe(true);
            if (!primary) {
                await page.getByTestId('mobile-more-toggle').click();
                await page.getByTestId('mobile-more-close').click().catch(() => {});
                await page.waitForTimeout(200);
            }
        }
        await context.close();
    });

    async function openMobileCanvasWorkspace(page: Page): Promise<void> {
        await page.getByTestId('mobile-more-toggle').click();
        await page.getByTestId('more-canvas').click();
        await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });
        await page.getByTestId('canvas-create-title').fill('R7 gate mobile canvas');
        await page.getByTestId('canvas-create-submit').click();
        await page.getByTestId('canvas-workspace').waitFor({ timeout: 45_000 });
    }

    test('canvas workspace stays usable at 375px', async ({ browser }) => {
        const context = await browser.newContext({ viewport: MOBILE });
        const page = await context.newPage();
        await login(page);
        await openMobileCanvasWorkspace(page);
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1),
            'canvas workspace overflows at 375px',
        ).toBe(true);
        await context.close();
    });
});

test.describe('R7 — dark mode WCAG scans (§5.4)', () => {
    test('core surfaces have no critical/serious violations in dark mode', async ({ browser }) => {
        const context = await browser.newContext({ colorScheme: 'dark' });
        const page = await context.newPage();
        await login(page);
        await page.getByTestId('today-view').waitFor({ timeout: 30_000 });
        // System preference resolves through theme.ts → .dark on <html>.
        const isDark = await page.evaluate(() =>
            document.documentElement.classList.contains('dark'),
        );
        expect(isDark, 'emulated colorScheme=dark must apply the .dark class').toBe(true);

        for (const key of ['today', 'knowledge']) {
            await page.getByTestId(`nav-${key}`).click();
            await page.waitForTimeout(500);
            const ids = await axeIds(page);
            expect(ids, `${key} has dark-mode WCAG violations`).toEqual([]);
        }

        // Canvas shell (island excluded).
        await page.getByTestId('nav-canvas').click();
        await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });
        expect(await axeIds(page), 'canvas shell has dark-mode WCAG violations').toEqual([]);
        await context.close();
    });
});

test.describe('R7 — screen-reader state smoke (§45, §90)', () => {
    test('save/sync state regions are announced and topbar controls are named', async ({ page }) => {
        await login(page);
        await page.getByTestId('today-view').waitFor({ timeout: 30_000 });

        // Notification bell must expose an accessible name (UI-010 fix stays fixed).
        const bellName = await page.locator('header [aria-label]').first().getAttribute('aria-label');
        expect(bellName?.length ?? 0).toBeGreaterThan(0);

        // Sync panel region exists with live semantics somewhere in the shell
        // (SyncStatusPanel carries role="status" aria-live="polite").
        const liveRegions = await page.locator('[role="status"][aria-live="polite"]').count();
        expect(liveRegions).toBeGreaterThan(0);

        // Canvas save badge keeps its live region after entering a workspace.
        await openCanvasWorkspace(page);
        const saveBadge = page.getByTestId('canvas-save-state');
        await expect(saveBadge).toHaveAttribute('role', 'status');
        await expect(saveBadge).toHaveAttribute('aria-live', 'polite');
    });
});
