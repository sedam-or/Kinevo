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

        // Local Ollama path — no API key required (§104). The endpoint is
        // environment-dependent; default is the compose ai-profile service
        // (reboot-proof, reachable from the app container), override with
        // E2E_OLLAMA_URL.
        const ollamaUrl = process.env.E2E_OLLAMA_URL ?? 'http://ollama:11434';
        await page.getByTestId('ai-provider-select').selectOption('ollama');
        await page.getByTestId('ai-model-input').fill('qwen2.5-coder:7b');
        await page.getByTestId('ai-base-url-input').fill(ollamaUrl);
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
        // Privacy blurb is always present on this surface (§104 contract,
        // P18-013 copy: encrypted server-side, never echoed back).
        await expect(page.getByTestId('ai-privacy-copy')).toContainText('encrypted on the Kinevo server');
    });
});test.describe('R17 golden journey G2 — Breakdown proposal: review → edit → accept (P17-004)', () => {
    // Preconditions: a reachable AI provider is configured in Settings → AI &
    // Providers (journey H). Generation happens SERVER-side, so this journey
    // cannot be route-mocked in the browser; it skips loudly otherwise.
    test('generates a breakdown, reviews/edits/accepts it inline — milestones appear (P17-026)', async ({ page }) => {
        // Real generation on a local 7B model: the first request may pay a
        // cold model load (keep-alive expiry between browsers), so this test
        // gets tripled budget (test.slow → 3× the global timeout).
        test.slow();
        await login(page);
        const name = unique('r17-g2');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(name);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
        await page.getByTestId('goal-breakdown-ai').click();

        // P17-026: the proposal is reviewed right here — still on the Goals
        // surface, no navigation to the goal detail page. Generous timeout:
        // the first generation may pay a cold model load on the provider.
        await expect(page.getByTestId('proposal-review')).toBeVisible({ timeout: 120_000 });
        await expect(page.getByTestId('goal-detail')).not.toBeVisible();
        const milestoneCount = await page.getByTestId('proposal-milestones').locator('li').count();
        expect(milestoneCount).toBeGreaterThan(0);

        // P17-027: explanation content assertions — decision summary plus
        // high-level assumptions/inputs/constraints render when the AI supplies
        // them, and are never hidden raw JSON or chain-of-thought.
        for (const explanation of ['proposal-rationale', 'proposal-assumptions', 'proposal-inputs', 'proposal-constraints']) {
            const block = page.getByTestId(explanation);
            if (await block.count()) {
                await expect(block).toBeVisible();
                await expect(block).not.toContainText('"goal_breakdown_proposal"');
            }
        }

        // Edit the first milestone title before accepting (FR-62 approval gate).
        await page.getByTestId('proposal-edit').click();
        const firstTitle = page.getByTestId('proposal-milestone-title-0');
        const originalTitle = await firstTitle.inputValue();
        await firstTitle.fill(`${originalTitle} (edited)`);
        await page.getByTestId('proposal-save-edits').click();
        await expect(page.getByTestId('proposal-edited-badge')).toBeVisible();

        // Accept stays inline; the panel confirms the goal kept the milestones.
        await page.getByTestId('proposal-accept').click();
        await expect(page.getByTestId('goal-proposal-accepted')).toBeVisible({ timeout: 15_000 });
        await expect(page.getByTestId('goal-detail')).not.toBeVisible();

        // Open the goal from the accepted panel — milestones are on the goal.
        await page.getByTestId('goal-breakdown-open').click();
        await expect(page.getByTestId('goal-detail')).toBeVisible();
        await expect(page.getByTestId('goal-milestones')).toContainText(`${originalTitle} (edited)`, { timeout: 15_000 });
        await expect(page.getByTestId('milestone-timeline').locator('[data-testid="milestone-item"]')).toHaveCount(milestoneCount);
    });
});
test.describe('R17 golden journey G3 — Post-goal invocation entry points (P17-005)', () => {
    // Discoverability smoke: breakdown must be invokable where the goal lives.
    // (Generation itself needs a reachable provider — covered by journey G2.)
    test('goal detail exposes Break Down with AI without visiting Settings', async ({ page }) => {
        await login(page);
        const name = unique('r17-g3');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(name);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        // Success state already offers the action (P17-003); take the manual
        // path into the detail surface and verify both remaining entries.
        await page.getByTestId('goal-breakdown-manual').click();
        await expect(page.getByTestId('goal-detail')).toBeVisible();
        await expect(page.getByTestId('goal-detail-breakdown')).toBeVisible();
        await expect(page.getByTestId('milestones-empty')).toBeVisible();
        await expect(page.getByTestId('milestones-empty-breakdown')).toBeVisible();
    });
});
test.describe('R17 golden journey H2 — AI discoverability gate (P17-028)', () => {
    // Runs last on purpose: it toggles the singleton provider off, then
    // restores it so no earlier journey's state is affected.
    test('unconfigured AI routes "Generate with AI" to Settings instead of erroring', async ({ page }) => {
        await login(page);
        // Force the canonical unconfigured/off state via Settings (§104).
        await page.getByTestId('nav-ai-settings').click();
        await expect(page.getByTestId('ai-settings-view')).toBeVisible();
        await page.getByTestId('ai-enabled-toggle').uncheck();
        await page.getByTestId('ai-save-button').click();
        await expect(page.getByTestId('ai-settings-saved')).toBeVisible();

        // Create a goal and try the AI path — must NOT fire a doomed request.
        const name = unique('r17-i');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(name);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
        await page.getByTestId('goal-breakdown-ai').click();

        // The gate: honest message + one-tap route to Settings → AI & Providers.
        await expect(page.getByTestId('ai-not-configured')).toContainText('AI is not configured.');
        await page.getByTestId('configure-ai').click();
        await expect(page.getByTestId('ai-settings-view')).toBeVisible();

        // Restore: leave AI enabled so later suites see the configured state.
        await page.getByTestId('ai-enabled-toggle').check();
        await page.getByTestId('ai-save-button').click();
        await expect(page.getByTestId('ai-settings-saved')).toBeVisible();
    });
});
test.describe('R17 golden journey K — contextual AI entry points per surface (P17-029)', () => {
    // Runs after H2 on purpose and restores the provider state at the end.
    // Deterministic without a live provider: entry points are asserted per
    // surface, and the P17-028 gate is proven on the note surface with AI
    // explicitly disabled via Settings first.
    test('Goal/Note/Canvas/Task expose their contextual AI action where the object lives', async ({ page }) => {
        await login(page);

        // Force the unconfigured/off state via the control plane (§104).
        await page.getByTestId('nav-ai-settings').click();
        await expect(page.getByTestId('ai-settings-view')).toBeVisible();
        await page.getByTestId('ai-enabled-toggle').uncheck();
        await page.getByTestId('ai-save-button').click();
        await expect(page.getByTestId('ai-settings-saved')).toBeVisible();

        const name = unique('r17-k');

        // GOAL → Break down with AI (post-create suggestion).
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(name);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-ai')).toBeVisible();

        // NOTE → Summarize / Extract tasks where the note lives; gate click.
        await page.getByTestId('nav-knowledge').click();
        await expect(page.getByTestId('notes-view')).toBeVisible();
        await page.getByTestId('note-create-title').fill(`${name} note`);
        await page.getByTestId('note-create-submit').click();
        await expect(page.getByTestId('note-detail')).toBeVisible();
        await expect(page.getByTestId('note-ai-summarize')).toBeVisible();
        await expect(page.getByTestId('note-ai-extract')).toBeVisible();
        await page.getByTestId('note-ai-summarize').click();
        await expect(page.getByTestId('ai-not-configured')).toContainText('AI is not configured.');
        await page.getByTestId('configure-ai').click();
        await expect(page.getByTestId('ai-settings-view')).toBeVisible();

        // CANVAS → Suggest structure at the boards index.
        await page.getByTestId('nav-canvas').click();
        await expect(page.getByTestId('canvas-view')).toBeVisible();
        await expect(page.getByTestId('canvas-suggest-prompt')).toBeVisible();
        await expect(page.getByTestId('canvas-suggest-submit')).toBeVisible();

        // TASK → Clarify task on the detail surface.
        await page.getByTestId('nav-tasks').click();
        await expect(page.getByTestId('task-view')).toBeVisible();
        await page.getByTestId('task-create-title').fill(`${name} task`);
        await page.getByTestId('task-create-submit').click();
        await page.getByTestId('task-item').filter({ hasText: `${name} task` }).first().getByTestId('task-open').click();
        await expect(page.getByTestId('task-detail')).toBeVisible();
        await expect(page.getByTestId('task-detail-clarify')).toBeVisible();

        // Restore: leave AI enabled so later suites see the configured state.
        await page.getByTestId('nav-ai-settings').click();
        await expect(page.getByTestId('ai-settings-view')).toBeVisible();
        await page.getByTestId('ai-enabled-toggle').check();
        await page.getByTestId('ai-save-button').click();
        await expect(page.getByTestId('ai-settings-saved')).toBeVisible();
    });
});
