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
