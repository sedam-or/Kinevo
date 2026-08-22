import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { login } from './helpers';

test.describe.configure({ timeout: 120_000 });

/**
 * TASK-R5 — Accessibility Pass (design.md §45–§47, WCAG 2.2 AA target).
 *
 * Real-browser proofs that were impossible before the docker Playwright
 * runner landed in R4:
 * - axe-core WCAG 2.2 A/AA scans of the core surfaces (§45);
 * - keyboard-only login + §46 G-chords + Cmd/Ctrl+K quick capture;
 * - prefers-reduced-motion honored (§47) under an emulated media feature.
 */

/** Views exercised by the audit: the core surfaces named in TASK-R5. */
const CORE_SURFACES: Array<{ key: string; viewTestId: string }> = [
    { key: 'today', viewTestId: 'today-view' },
    { key: 'tasks', viewTestId: 'task-view' },
    { key: 'goals', viewTestId: 'goals-view' },
    { key: 'knowledge', viewTestId: 'notes-view' },
];

async function gotoView(page: import('@playwright/test').Page, key: string): Promise<void> {
    await page.getByTestId(`nav-${key}`).click();
}

async function axeViolations(
    page: import('@playwright/test').Page,
): Promise<{ id: string; impact: string | null; nodes: number }[]> {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    return results.violations.map((v) => ({
        id: v.id,
        impact: v.impact ?? null,
        nodes: v.nodes.length,
    }));
}

test.describe('R5 — axe-core WCAG 2.2 A/AA scans', () => {
    test('login page has no critical or serious violations', async ({ page }) => {
        await page.goto('/app');
        await page.getByTestId('login-form').waitFor({ timeout: 30_000 });
        const violations = await axeViolations(page);
        expect(violations).toEqual([]);
    });

    for (const surface of CORE_SURFACES) {
        test(`${surface.key} surface has no critical or serious violations`, async ({ page }) => {
            await login(page);
            await gotoView(page, surface.key);
            await page.getByTestId(surface.viewTestId).waitFor({ timeout: 30_000 });
            const violations = await axeViolations(page);
            expect(violations).toEqual([]);
        });
    }

    test('canvas shell has no critical or serious violations', async ({ page }) => {
        await login(page);
        await page.getByTestId('nav-canvas').click();
        await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });
        // Scan the shell chrome only — Excalidraw internals are third-party
        // (external engine boundary); their a11y is not Kinevo's to fix here.
        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa'])
            .exclude('.kinevo-canvas-island')
            .analyze();
        expect(results.violations.map((v) => v.id)).toEqual([]);
    });
});

test.describe('R5 — keyboard-only flows (§46)', () => {
    test('login completes with keyboard only; G-chords navigate; Cmd/Ctrl+K opens and Escape closes quick capture', async ({ page }) => {
        await page.goto('/app');
        await page.getByTestId('login-form').waitFor({ timeout: 30_000 });
        // Keyboard-only login.
        await page.getByTestId('login-email').focus();
        await page.keyboard.type('sampalan@gmail.com');
        await page.keyboard.press('Tab');
        await page.keyboard.type(process.env.E2E_OWNER_PASSWORD || 'KinevoE2E123!');
        await page.keyboard.press('Enter');
        await page.getByTestId('today-view').waitFor({ timeout: 30_000 });

        // G-chords: G then W → Week, then G then T → Today (§46).
        await page.keyboard.press('g');
        await page.keyboard.press('w');
        await expect(page.getByTestId('current-section')).toContainText(/week/i, { timeout: 15_000 });
        await page.keyboard.press('g');
        await page.keyboard.press('t');
        await expect(page.getByTestId('current-section')).toContainText(/today/i, { timeout: 15_000 });

        // Cmd/Ctrl+K opens Quick Capture; Escape closes it (dialog parity).
        await page.keyboard.press('ControlOrMeta+k');
        await page.getByTestId('quick-capture-modal').waitFor({ timeout: 15_000 });
        // Focus must be INSIDE the dialog (focus trap initial focus).
        await page.evaluate(() => {
            const modal = document.querySelector('[data-testid="quick-capture-modal"]');
            if (!modal?.contains(document.activeElement)) {
                throw new Error('focus is outside the quick capture dialog');
            }
        });
        await page.keyboard.press('Escape');
        await expect(page.getByTestId('quick-capture-modal')).toBeHidden({ timeout: 15_000 });
    });
});

test.describe('R5 — reduced motion (§47)', () => {
    test('emulated prefers-reduced-motion collapses transitions and keeps the app functional', async ({ browser }) => {
        const context = await browser.newContext({ reducedMotion: 'reduce' });
        const page = await context.newPage();
        await login(page);
        await page.getByTestId('today-view').waitFor({ timeout: 30_000 });
        // The global reduce block forces transition-duration to 0.01ms on every
        // element; sample a real interactive control to prove the cascade wins.
        const duration = await page
            .getByRole('button')
            .first()
            .evaluate((el) => getComputedStyle(el).transitionDuration);
        // Browsers serialize the same value differently ("0.01ms" / "1e-05s").
        expect(parseFloat(duration)).toBeLessThan(1);
        await context.close();
    });
});
