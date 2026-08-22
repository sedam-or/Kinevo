import { test, expect } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * Golden user journeys (design.md §73, §99) driven through the REAL browser:
 * LOGIN → TODAY → create → verify. Journeys mutate the dev database by
 * creating uniquely-named fixtures; assertions use the products'
 * data-testids so a journey fails loudly if the surface regresses.
 */

test.describe('R6 golden journey A — Plan', () => {
    test('creates a Goal then a Task and both surface in their lists', async ({ page }) => {
        await login(page);
        const name = unique('r6-plan');

        // PLAN group → Goals: create a goal. Use Quarterly — yearly/monthly
        // have ACTIVE caps (FR-19/FR-20) that repeated runs would exhaust.
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(`${name} goal`);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-list').getByText(`${name} goal`, { exact: false })).toBeVisible();

        // PLAN group → Tasks: create a task.
        await page.getByTestId('nav-tasks').click();
        await expect(page.getByTestId('task-view')).toBeVisible();
        await page.getByTestId('task-create-title').fill(`${name} task`);
        await page.getByTestId('task-create-submit').click();
        await expect(page.getByTestId('task-list').getByText(`${name} task`, { exact: false })).toBeVisible();
    });
});

test.describe('R6 golden journey B — Execute', () => {
    test('opens Today and can quick-capture a task', async ({ page }) => {
        await login(page);
        const name = unique('r6-exec');

        // EXECUTE group → Today is the landing surface.
        await expect(page.getByTestId('today-view')).toBeVisible();
        // Until a task runs, Today must still show its functional shell.
        await expect(page.getByTestId('quick-capture')).toBeVisible();

        await page.getByTestId('qc-title').fill(name);
        await page.getByTestId('qc-submit').click();
        await expect(page.getByTestId('qc-title')).toHaveValue('');
        // The capture round-trips to the server; the surface reloads.
        await expect(page.getByTestId('today-view')).toBeVisible();
    });
});

test.describe('R6 golden journey D — Knowledge', () => {
    test('creates a Note, edits it (autosave surfaces), and creates a Canvas', async ({ page }) => {
        await login(page);
        const name = unique('r6-know');

        // KNOWLEDGE group → Knowledge (Notes).
        await page.getByTestId('nav-knowledge').click();
        await expect(page.getByTestId('notes-view')).toBeVisible();
        await page.getByTestId('note-create-title').fill(name);
        await page.getByTestId('note-create-submit').click();

        // The note opens in the editor; autosave indicator is top-right (§32).
        await expect(page.getByTestId('note-detail')).toBeVisible();
        await expect(page.getByTestId('note-save-state')).toBeVisible();
        await page.getByTestId('note-title-input').fill(`${name} (edited)`);
        // Saving is debounced; the indicator shows a transitional badge.
        await expect(page.getByTestId('note-save-state')).toBeVisible({ timeout: 15_000 });

        // KNOWLEDGE group → Canvas: create one (lazy-loaded Excalidraw chunk).
        await page.getByTestId('nav-canvas').click();
        await expect(page.getByTestId('canvas-view')).toBeVisible();
        await page.getByTestId('canvas-create-title').fill(name);
        await page.getByTestId('canvas-create-submit').click();
        // Workspace mounts the product shell; the save state is always visible §34.4.
        await expect(page.getByTestId('canvas-workspace')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('canvas-save-state')).toBeVisible({ timeout: 30_000 });
    });
});

test.describe('R17 golden journey G — Plan, then decide how to break down (P17-003)', () => {
    test('creating a goal shows the breakdown suggestion; "I\'ll do it myself" opens the goal', async ({ page }) => {
        await login(page);
        const name = unique('r17-g');
        // PLAN group → Goals: create a goal with Outcome/Description/Deadline.
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(name);
        await page.getByTestId('goal-create-description').fill('Outcome description for the journey');
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        // The planning workflow shows the breakdown suggestion (no auto-mutation).
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
        await expect(page.getByTestId('goal-breakdown-ai')).toBeVisible();
        await expect(page.getByTestId('goal-breakdown-manual')).toBeVisible();
        await expect(page.getByTestId('goal-breakdown-later')).toBeVisible();
        // Choosing to do it yourself opens the goal detail for manual planning.
        await page.getByTestId('goal-breakdown-manual').click();
        await expect(page.getByTestId('goal-detail')).toBeVisible();
        await expect(page.getByTestId('goal-detail-title')).toContainText(name, { exact: false });
    });
});

test.describe('R17 golden journey H — Settings → AI & Providers (P17-006)', () => {
    test('configures the local Ollama provider: test, save, survive reload', async ({ page }) => {
        await login(page);

        // SYSTEM group → AI & Providers.
        await page.getByTestId('nav-ai-settings').click();
        await expect(page.getByTestId('ai-settings-view')).toBeVisible();

        // Local Ollama path — no API key required (§104).
        await page.getByTestId('ai-provider-select').selectOption('ollama');
        await page.getByTestId('ai-model-input').fill('qwen2.5-coder:7b');
        await page.getByTestId('ai-base-url-input').fill('http://localhost:11434');
        await expect(page.getByTestId('ai-ollama-no-key')).toContainText('does not require an API key');

        // Test BEFORE saving: candidate settings ping without persisting.
        await page.getByTestId('ai-test-button').click();
        await expect(page.getByTestId('ai-test-result')).toContainText('Connected to ollama', { timeout: 20_000 });

        // Save.
        await page.getByTestId('ai-save-button').click();
        await expect(page.getByTestId('ai-settings-saved')).toBeVisible();

        // Reload → configuration persists from the server (masked).
        await page.reload();
        await login(page);
        await page.getByTestId('nav-ai-settings').click();
        await expect(page.getByTestId('ai-provider-select')).toHaveValue('ollama');
        await expect(page.getByTestId('ai-model-input')).toHaveValue('qwen2.5-coder:7b');
        // Privacy blurb is always present on this surface (§104 contract).
        await expect(page.getByTestId('ai-privacy-blurb')).toContainText('never stored in browser storage');
    });
});test.describe('R17 golden journey G2 — Breakdown proposal: review → edit → accept (P17-004)', () => {
    // Preconditions: a reachable AI provider is configured in Settings → AI &
    // Providers (journey H). Generation happens SERVER-side, so this journey
    // cannot be route-mocked in the browser; it skips loudly otherwise.
    test('generates a breakdown, edits the proposal, accepts it — milestones appear', async ({ page }) => {
        await login(page);
        const name = unique('r17-g2');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(name);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
        await page.getByTestId('goal-breakdown-ai').click();

        // Loading state → the goal list confirms the pending proposal exists.
        await expect(page.getByTestId('goal-proposal-ready')).toBeVisible({ timeout: 30_000 });

        // Open the goal: the review card renders the validated proposal.
        await page.getByTestId('goal-list').getByText(name, { exact: false }).first().click();
        await expect(page.getByTestId('goal-detail')).toBeVisible();
        await expect(page.getByTestId('proposal-review')).toBeVisible({ timeout: 10_000 });
        const milestoneCount = await page.getByTestId('proposal-milestones').locator('li').count();
        expect(milestoneCount).toBeGreaterThan(0);

        // Edit the first milestone title before accepting (FR-62 approval gate).
        await page.getByTestId('proposal-edit').click();
        const firstTitle = page.getByTestId('proposal-milestone-title-0');
        const originalTitle = await firstTitle.inputValue();
        await firstTitle.fill(`${originalTitle} (edited)`);
        await page.getByTestId('proposal-save-edits').click();
        await expect(page.getByTestId('proposal-edited-badge')).toBeVisible();

        // Accept → milestones land on the goal through the accepted contract.
        await page.getByTestId('proposal-accept').click();
        await expect(page.getByTestId('goal-milestones')).toContainText(`${originalTitle} (edited)`, { timeout: 15_000 });
        await expect(page.getByTestId('milestone-timeline').locator('[data-testid="milestone-item"]')).toHaveCount(milestoneCount);
    });
});
