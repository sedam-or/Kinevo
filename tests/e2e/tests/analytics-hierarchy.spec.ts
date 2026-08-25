import { test, expect } from '@playwright/test';
import { login, unique, apiFetch } from './helpers';

/**
 * P17-021 visual audit — Analytics renders the shared information-hierarchy
 * surfaces (design-tokens.md §4a): boxed Primary decision charts, open
 * Supporting groups, unboxed metadata. Screenshots land under
 * test-results/screenshots/<browser>/ (R6 §87 convention).
 *
 * Self-seeding: the dashboard sections under audit only render once the
 * period has tracked time and at least one goal exists (analytics-empty
 * short-circuits otherwise), so each theme pass seeds one focus session
 * (today) + one goal through the API instead of relying on leftover
 * fixtures from other suites.
 */
test.describe('P17-021 design-system hierarchy — Analytics visual audit', () => {
    for (const theme of ['light', 'dark'] as const) {
        test(`analytics follows the shared surface hierarchy (${theme})`, async ({ page }, testInfo) => {
            await page.addInitScript((t) => window.localStorage.setItem('kinevo.theme', t), theme);
            await login(page);

            await apiFetch(page, '/api/v1/focus-sessions', {
                method: 'POST',
                body: JSON.stringify({
                    started_at: new Date(new Date().setHours(12, 0, 0, 0) - 45 * 60 * 1000).toISOString(),
                    ended_at: new Date(new Date().setHours(12, 0, 0, 0)).toISOString(),
                }),
            });
            await apiFetch(page, '/api/v1/goals', {
                method: 'POST',
                body: JSON.stringify({ title: unique('p17-021-goal'), horizon: 'quarterly' }),
            });

            await page.getByTestId('nav-analytics').click();
            await expect(page.getByTestId('analytics-presets')).toBeVisible({ timeout: 30_000 });
            await expect(page.getByTestId('analytics-summary')).toBeVisible({ timeout: 30_000 });

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
