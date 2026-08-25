import { test, expect } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * TASK-P18-020 — AI browser E2E over the REMOTE provider path.
 *
 * Chain under proof (Ollama NOT required):
 *   Settings → provider + base URL → credential (SecretField) →
 *   masked display → real inference connection test →
 *   goal breakdown proposal → accept commits milestones.
 *
 * Credentials are INJECTED through the environment and never committed:
 *   E2E_AI_API_KEY   required (test skips without it)
 *   E2E_AI_BASE_URL  optional (default: host-gateway LLM gateway)
 *   E2E_AI_MODEL     optional (default: auto/best-free)
 */
const API_KEY = process.env.E2E_AI_API_KEY ?? '';
const BASE_URL = process.env.E2E_AI_BASE_URL ?? 'http://172.17.0.1:20128/v1';
const MODEL = process.env.E2E_AI_MODEL ?? 'auto/best-free';

test.describe('P18 AI remote runtime journey', () => {
    test.skip(!API_KEY, 'E2E_AI_API_KEY not set — remote AI journey requires injected credentials');

    test('settings → credential → masked → inference test → breakdown → accept', async ({ page }) => {
        test.setTimeout(240_000);
        await login(page);

        // --- Settings surface -------------------------------------------------
        await page.getByTestId('nav-ai-settings').click();
        await expect(page.getByTestId('ai-settings-view')).toBeVisible();
        for (const section of ['ai-section-status', 'ai-section-provider', 'ai-section-test', 'ai-section-privacy']) {
            await expect(page.getByTestId(section)).toBeVisible();
        }

        // Provider + endpoint (capability-driven fields).
        await page.getByTestId('ai-provider-select').selectOption('openai');
        await page.getByTestId('ai-model-input').fill(MODEL);
        const baseUrlInput = page.getByTestId('ai-base-url-input');
        if ((await baseUrlInput.count()) > 0) {
            await baseUrlInput.fill(BASE_URL);
        }

        // Credential via SecretField: write-only, masked by default.
        const secret = page.getByTestId('secret-input');
        await expect(secret).toHaveAttribute('type', 'password');
        await secret.fill(API_KEY);
        await page.getByTestId('secret-reveal').click();
        await expect(secret).toHaveAttribute('type', 'text');
        await page.getByTestId('secret-reveal').click();
        await expect(secret).toHaveAttribute('type', 'password');

        // Save settings + rotate credential through the dedicated endpoint.
        await page.getByTestId('ai-save-button').click();
        await expect(page.getByTestId('ai-settings-saved')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('ai-api-key-hint')).toContainText('…', { timeout: 15_000 });
        // The raw key NEVER reappears anywhere on the surface after save.
        const bodyAfterSave = await page.content();
        expect(bodyAfterSave).not.toContain(API_KEY);

        // Connection test proves MODEL usability through minimal inference.
        await page.getByTestId('ai-test-button').click();
        await expect(page.getByTestId('ai-test-result')).toContainText('Connected to', { timeout: 180_000 });

        // Persisted state survives reload; only the masked hint returns.
        await page.reload();
        await login(page);
        await page.getByTestId('nav-ai-settings').click();
        await expect(page.getByTestId('ai-provider-select')).toHaveValue('openai');
        await expect((page.getByTestId('ai-model-input') as any)).toHaveValue(MODEL);
        await expect(page.getByTestId('ai-api-key-hint')).toContainText('…');
        expect(await page.content()).not.toContain(API_KEY);

        // --- Goal breakdown with the REMOTE provider --------------------------
        const goalName = unique('P18 AI goal');
        await page.getByTestId('nav-goals').click();
        await page.getByTestId('goal-create-title').fill(goalName);
        await page.getByTestId('goal-create-description').fill('Ship the P18 remote AI journey end to end');
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();

        // Post-goal creation entry point: choose AI breakdown.
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
        await page.getByTestId('goal-breakdown-ai').click();

        // Proposal review: explicit AI GENERATED / NOT YET COMMITTED markers,
        // then acceptance commits milestones to the timeline.
        await expect(page.getByTestId('proposal-review')).toBeVisible({ timeout: 240_000 });
        await expect(page.getByTestId('proposal-ai-generated-badge')).toHaveText('AI GENERATED');
        await expect(page.getByTestId('proposal-not-committed-badge')).toHaveText('NOT YET COMMITTED');
        await page.getByTestId('proposal-accept').click();
        await expect(page.getByTestId('proposal-review')).toBeHidden({ timeout: 60_000 });
        // Acceptance lands back on the goals list with a success dialog;
        // open the goal to prove milestones were actually committed.
        await expect(page.getByText(/Breakdown accepted — milestones were added/i)).toBeVisible({ timeout: 60_000 });
        await page.getByRole('button', { name: /open goal/i }).click();
        await expect(page.getByTestId('goal-detail')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('goal-milestones')).toBeVisible();
        await expect(page.getByTestId('goal-milestones').locator('li, article, [data-testid^="milestone-"]').first()).toBeVisible({ timeout: 30_000 });
    });
});
