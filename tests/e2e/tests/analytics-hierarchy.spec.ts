import { test, expect } from '@playwright/test';
import { login } from './helpers';

/**
 * P17-021 visual audit — Analytics renders the shared information-hierarchy
 * surfaces (design-tokens.md §4a): boxed Primary decision charts, open
 * Supporting groups, unboxed metadata. Screenshots land under
 * test-results/screenshots/<browser>/ (R6 §87 convention).
 */
test.describe('P17-021 design-system hierarchy — Analytics visual audit', () => {
    for (const theme of ['light', 'dark'] as const) {
        test(`analytics follows the shared surface hierarchy (${theme})`, async ({ page }, testInfo) => {
            await page.addInitScript((t) => window.localStorage.setItem('kinevo.theme', t), theme);
            await login(page);

            await page.getByTestId('nav-analytics').click();
            await expect(page.getByTestId('analytics-presets')).toBeVisible({ timeout: 30_000 });

            // L2 Primary: decision charts stay boxed.
            const summaryBoxed = await page.getByTestId('analytics-summary').evaluate((el) => el.classList.contains('surface-primary'));
            expect(summaryBoxed).toBe(true);

            // L4 Supporting: raw/detail groups are open — never boxed.
            const heatmapOpen = await page.getByTestId('analytics-heatmap').evaluate((el) => el.classList.contains('surface-supporting') && !el.classList.contains('rounded-sm'));
            expect(heatmapOpen).toBe(true);

            await page.screenshot({ path: `test-results/screenshots/${testInfo.project.name}/p17-021-analytics-${theme}.png`, fullPage: true });
        });
    }
});