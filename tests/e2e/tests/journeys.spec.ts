import { test, expect } from '@playwright/test';
import { login } from './helpers';

const NAV_LABEL_TO_TESTID: Record<string, string> = {
    Today: 'today-view',
    Week: 'week-view',
    Schedule: 'schedule-draft-view',
    'Goals / Roadmap': 'goals-view',
    Tasks: 'task-view',
    Knowledge: 'notes-view',
    Canvas: 'canvas-view',
    Analytics: 'analytics-presets',
    Settings: 'profile-form',
};

test.describe('R1 shell navigation', () => {
    test('all primary navigation destinations render their surface', async ({ page }) => {
        await login(page);
        const nav = page.getByTestId('desktop-nav');

        for (const [label, testid] of Object.entries(NAV_LABEL_TO_TESTID)) {
            await nav.getByRole('link', { name: label, exact: false }).click();
            await expect(page.getByTestId(testid)).toBeVisible({ timeout: 30_000 });
        }
    });
});
