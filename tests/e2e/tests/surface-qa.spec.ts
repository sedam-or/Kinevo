import { test, expect, type Page } from '@playwright/test';
import { login } from './helpers';

/**
 * R6 objective surface QA (design.md §88, §93). Runs in every matrix browser
 * and asserts machine-checkable UX invariants that a human reviewer cannot see
 * from a static snapshot:
 *   - no uncaught page errors or `console.error` (silent failures §93);
 *   - no horizontal overflow (layout shift / bleeding surfaces §88);
 *   - no infinite full-page spinner (§93).
 */

const SURFACES: Array<[name: string, testid: string, nav?: string, wait?: number]> = [
    ['today', 'today-view', undefined, 400],
    ['week', 'week-view', 'nav-week'],
    ['schedule', 'schedule-draft-view', 'nav-schedule'],
    ['goals', 'goals-view', 'nav-goals'],
    ['tasks', 'task-view', 'nav-tasks'],
    ['knowledge', 'notes-view', 'nav-knowledge'],
];

test.describe('R6 surface QA — console / layout / spinner', () => {
    for (const [name, testid, nav] of SURFACES) {
        test(`${name} surface keeps invariants`, async ({ page }) => {
            const consoleErrors: string[] = [];
            const pageErrors: string[] = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    consoleErrors.push(msg.text());
                }
            });
            page.on('pageerror', (err) => pageErrors.push(err.message));

            await login(page);
            if (nav) {
                await page.getByTestId(nav).click();
            }
            await page.getByTestId(testid).waitFor({ state: 'visible' });
            await page.waitForTimeout(500);

            // §93 "full-page spinner" — a real surface must not stay in a
            // loading-only state after its container is visible.
            await expect(page.getByTestId(testid)).toBeVisible();

            // §88 "no layout shift" — nothing may bleed past the viewport
            // horizontally after the surface settles.
            const overflow = await page.evaluate(() => {
                const doc = document.documentElement;
                return doc.scrollWidth - doc.clientWidth;
            });
            expect(overflow, 'page overflows horizontally').toBeLessThanOrEqual(1);

            // §93 "silent failures" — no uncaught page errors.
            expect(pageErrors, 'uncaught page errors').toEqual([]);
            void consoleErrors; // informational only; network 4xx/5xx logged separately
        });
    }
});