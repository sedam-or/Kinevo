import { expect } from '@playwright/test';
import { asAuthenticated } from './helpers';

/**
 * TASK-P17-008 — contextual feature education. Explanations appear on first
 * use, and a dismissal is remembered locally so they are not repeated.
 */
asAuthenticated('P17-008: first-use explanation appears once and stays dismissed', async (page) => {
    const trigger = page.getByTestId('feature-help-hard-landscape-trigger');

    // First use: the Hard Landscape explanation is offered on Today.
    await expect(trigger).toBeVisible();
    await trigger.click();
    const panel = page.getByTestId('feature-help-hard-landscape-panel');
    await expect(panel).toBeVisible();
    await expect(panel).toContainText('Hard Landscape');

    // Dismiss — the callout disappears immediately…
    await page.getByTestId('feature-help-hard-landscape-dismiss').click();
    await expect(trigger).toHaveCount(0);

    // …and does not repeat after a reload (persisted local preference).
    await page.reload();
    await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId('feature-help-hard-landscape-trigger')).toHaveCount(0);
});

asAuthenticated('P17-008: explanations are opt-in context, never blocking', async (page) => {
    // The info control is visible but nothing is opened or blocked by default.
    await expect(page.getByTestId('feature-help-hard-landscape-trigger')).toBeVisible();
    await expect(page.getByTestId('feature-help-hard-landscape-panel')).toHaveCount(0);
    await expect(page.getByTestId('today-timeline')).toBeVisible();
});

asAuthenticated('P17-009: empty-state education across Today, Goal, Task, Analytics', async (page) => {
    // Today (empty NOW slot) explains the core loop.
    await expect(page.getByTestId('feature-help-today-flow')).toBeVisible();

    // Goals: fresh owner has none — the roadmap explanation is in the empty state.
    await page.getByTestId('nav-goals').click();
    const goalHelp = page.getByTestId('feature-help-goal-roadmap');
    await expect(goalHelp).toBeVisible();
    await expect(goalHelp).toContainText('Goals are the start of the roadmap');

    // Tasks: same pattern.
    await page.getByTestId('nav-tasks').click();
    await expect(page.getByTestId('task-empty')).toBeVisible();
    await expect(page.getByTestId('feature-help-tasks-feed-schedule')).toContainText('Tasks feed the schedule');

    // Analytics: empty period still teaches what will accumulate here.
    await page.getByTestId('nav-analytics').click();
    await expect(page.getByTestId('analytics-empty')).toBeVisible();
    await expect(page.getByTestId('feature-help-analytics-accumulates')).toBeVisible();

    // Dismissal is honored per feature: dismiss the goal callout, reload,
    // it stays gone while the others keep teaching.
    await page.getByTestId('nav-goals').click();
    await page.getByTestId('feature-help-goal-roadmap-dismiss').click();
    await expect(page.getByTestId('feature-help-goal-roadmap')).toHaveCount(0);

    await page.reload();
    await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId('feature-help-goal-roadmap')).toHaveCount(0);
});
