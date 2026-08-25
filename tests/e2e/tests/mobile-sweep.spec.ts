import { test, expect, type Page } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * TASK-P17-034 — Mobile UX re-audit: Playwright width sweep.
 *
 * Every core Kinevo-owned surface must fit its viewport with NO horizontal
 * document overflow (design.md §88) at 375 / 390 / 412 / 768 / 1024 / 1440 —
 * CTA/nav included, since the shell renders on every surface. Extends the
 * Today-only checks in journey-i to the full surface set plus detail views.
 *
 * Navigation is width-aware: below the lg breakpoint the shell swaps the
 * sidebar for the mobile bottom bar + "More" drawer (§104 P17-001), so the
 * sweep exercises that real navigation model instead of force-clicking
 * hidden links.
 */

const WIDTHS = [375, 390, 412, 768, 1024, 1440];
const MOBILE_PRIMARY = ['today', 'tasks', 'goals', 'knowledge'];

/** Navigate via the nav model that is actually visible at this width. */
async function goTo(page: Page, key: string, width: number): Promise<void> {
    if (width >= 1024) {
        await page.getByTestId(`nav-${key}`).click();
        return;
    }
    if (MOBILE_PRIMARY.includes(key)) {
        await page.getByTestId(`mobile-nav-${key}`).click();
        return;
    }
    await page.getByTestId('mobile-more-toggle').click();
    const drawer = page.getByTestId('mobile-more-drawer');
    await expect(drawer).toBeVisible();
    await drawer.getByTestId(`more-${key}`).click();
    await expect(drawer).not.toBeVisible();
}

async function assertNoHorizontalOverflow(page: Page, surface: string, width: number): Promise<void> {
    const bleed = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(bleed, `${surface} overflows horizontally by ${bleed}px at ${width}w`).toBeLessThanOrEqual(1);
}

test.describe('P17-034 mobile UX width sweep', () => {
    for (const width of WIDTHS) {
        test(`width ${width}: every core surface fits without horizontal overflow`, async ({ browser }) => {
            // Sixteen seeded/navigated surfaces: tripled default budget.
            test.slow();
            const context = await browser.newContext({ viewport: { width, height: 900 } });
            const page = await context.newPage();
            await login(page);

            // Seed one entity per detail-bearing surface through the UI.
            const goalName = unique('sweep-goal');
            await goTo(page, 'goals', width);
            await expect(page.getByTestId('goals-view')).toBeVisible();
            await page.getByTestId('goal-create-title').fill(goalName);
            await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
            await page.getByTestId('goal-create-submit').click();
            await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
            await page.getByTestId('goal-breakdown-later').click();

            const taskTitle = unique('sweep-task');
            await goTo(page, 'tasks', width);
            await page.getByTestId('task-create-title').fill(taskTitle);
            await page.getByTestId('task-create-submit').click();
            await expect(page.getByTestId('task-list').getByText(taskTitle, { exact: false })).toBeVisible();

            const noteTitle = unique('sweep-note');
            await goTo(page, 'knowledge', width);
            await expect(page.getByTestId('notes-view')).toBeVisible();
            await page.getByTestId('note-create-title').fill(noteTitle);
            await page.getByTestId('note-create-submit').click();
            await expect(page.getByTestId('note-detail')).toBeVisible();

            // EXECUTE group
            await goTo(page, 'today', width);
            await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 30_000 });
            await assertNoHorizontalOverflow(page, 'Today', width);

            await goTo(page, 'week', width);
            await expect(page.getByTestId('week-view')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Week', width);

            await goTo(page, 'calendar', width);
            await expect(page.getByTestId('calendar-view')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Calendar', width);

            // PLAN group + goal detail
            await goTo(page, 'goals', width);
            await expect(page.getByTestId('goals-view')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Goals list', width);
            await page.getByTestId('goal-item').filter({ hasText: goalName }).first().getByTestId('goal-open').click();
            await expect(page.getByTestId('goal-detail')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Goal detail', width);

            await goTo(page, 'tasks', width);
            await expect(page.getByTestId('task-view')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Tasks list', width);
            await page.getByTestId('task-item').filter({ hasText: taskTitle }).first().getByTestId('task-open').click();
            await expect(page.getByTestId('task-detail')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Task detail', width);

            await goTo(page, 'schedule', width);
            await expect(page.getByTestId('schedule-draft-view')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Schedule draft', width);

            // KNOWLEDGE group
            await goTo(page, 'knowledge', width);
            await expect(page.getByTestId('notes-view')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Knowledge desk', width);
            await page.getByTestId('note-item').filter({ hasText: noteTitle }).first().getByTestId('note-open').click();
            await expect(page.getByTestId('note-detail')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Note editor', width);

            await goTo(page, 'canvas', width);
            await expect(page.getByTestId('canvas-view')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Canvas boards', width);

            // REVIEW group
            await goTo(page, 'analytics', width);
            await expect(page.getByTestId('analytics-presets')).toBeVisible({ timeout: 30_000 });
            await assertNoHorizontalOverflow(page, 'Analytics', width);

            // SYSTEM group
            await goTo(page, 'settings', width);
            await expect(page.getByTestId('profile-form')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'Settings', width);

            await goTo(page, 'ai-settings', width);
            await expect(page.getByTestId('ai-settings-view')).toBeVisible();
            await assertNoHorizontalOverflow(page, 'AI & Providers', width);

            await context.close();
        });
    }
});
