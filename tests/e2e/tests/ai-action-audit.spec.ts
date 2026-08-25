import { test, expect, type Page } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * TASK-P17-025 — AI action surface audit: failure states + reject path
 * (docs/ai-architecture.md "AI action surface audit matrix").
 *
 * 1. With the provider ENABLED but unreachable, every capability surface
 *    gates honestly (`ai-not-configured` notice — one lazy status read,
 *    TASK-P17-028 posture), fires no doomed request, and mutates nothing.
 * 2. With the real provider reachable, the goal-breakdown REJECT path is
 *    proven in the browser for the first time: the proposal disappears and
 *    the goal keeps zero milestones (FR-62 approval gate).
 *
 * The provider config is a GLOBAL singleton row shared by all matrix
 * browsers, so the config flip is re-asserted immediately before every
 * gated click (dead phase) and the final restore polls to convergence.
 */

const API = '/api/v1';
const GOOD_OLLAMA = process.env.E2E_OLLAMA_URL ?? 'http://ollama:11434';
const DEAD_OLLAMA = 'http://127.0.0.1:9';

async function apiFetch(
    page: Page,
    path: string,
    init: RequestInit = {},
): Promise<Record<string, unknown>> {
    const token = await page.evaluate(() => window.localStorage.getItem('kinevo.auth.token'));
    const res = await page.evaluate(
        async ({ path, init, token }) => {
            const response = await fetch(path, {
                ...init,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(token ? { Authorization: `Bearer ${token}` } : {}),
                    ...(init.headers ?? {}),
                },
            });
            return { ok: response.ok, status: response.status, body: await response.text() };
        },
        { path, init, token },
    );
    if (!res.ok) {
        throw new Error(`API ${path} -> ${res.status}: ${String(res.body)}`);
    }
    return JSON.parse(res.body as string);
}

function isoDate(daysFromNow: number): string {
    const d = new Date();
    d.setDate(d.getDate() + daysFromNow);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

async function putProvider(page: Page, baseUrl: string): Promise<void> {
    await apiFetch(page, `${API}/ai/config`, {
        method: 'PUT',
        body: JSON.stringify({
            provider: 'ollama',
            enabled: true,
            model: 'qwen2.5-coder:7b',
            base_url: baseUrl,
        }),
    });
}

async function providerState(page: Page): Promise<string | undefined> {
    const status = (await apiFetch(page, `${API}/ai/status`)) as { status?: { state?: string } };
    return status.status?.state;
}

/** Flip the config and wait until the server reports the wanted state. */
async function ensureProviderState(page: Page, baseUrl: string, want: string, timeoutMs = 30_000): Promise<void> {
    const deadline = Date.now() + timeoutMs;
    for (;;) {
        await putProvider(page, baseUrl);
        if ((await providerState(page)) === want) {
            return;
        }
        if (Date.now() > deadline) {
            throw new Error(`provider never reached "${want}" (base_url=${baseUrl})`);
        }
        await page.waitForTimeout(500);
    }
}

test.describe('P17-025 AI action surface audit', () => {
    test('enabled-but-unreachable provider gates every AI surface honestly and mutates nothing', async ({ page }) => {
        await login(page);

        // GOAL breakdown entry gates on the Goals list suggestion panel.
        await ensureProviderState(page, DEAD_OLLAMA, 'unavailable');
        const goalName = unique('audit-goal');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(goalName);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();
        await ensureProviderState(page, DEAD_OLLAMA, 'unavailable');
        await page.getByTestId('goal-breakdown-ai').click();
        await expect(page.getByTestId('ai-not-configured')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('proposal-review')).toHaveCount(0);

        // NOTE summarize + extract entries gate inside the editor; nothing
        // pending is produced.
        const noteTitle = unique('audit-note');
        await page.getByTestId('nav-knowledge').click();
        await expect(page.getByTestId('notes-view')).toBeVisible();
        await page.getByTestId('note-create-title').fill(noteTitle);
        await page.getByTestId('note-create-submit').click();
        await expect(page.getByTestId('note-detail')).toBeVisible();
        await ensureProviderState(page, DEAD_OLLAMA, 'unavailable');
        await page.getByTestId('note-ai-summarize').click();
        await expect(page.getByTestId('ai-not-configured')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('note-ai-summary')).toHaveCount(0);
        await ensureProviderState(page, DEAD_OLLAMA, 'unavailable');
        await page.getByTestId('note-ai-extract').click();
        await expect(page.getByTestId('ai-not-configured')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('note-ai-extraction-proposal')).toHaveCount(0);

        // CANVAS structure suggestion gates on the boards index.
        await page.getByTestId('nav-canvas').click();
        await expect(page.getByTestId('canvas-view')).toBeVisible();
        await ensureProviderState(page, DEAD_OLLAMA, 'unavailable');
        await page.getByTestId('canvas-suggest-prompt').fill('Quarterly planning board');
        await page.getByTestId('canvas-suggest-submit').click();
        await expect(page.getByTestId('ai-not-configured')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('canvas-suggest-proposal')).toHaveCount(0);

        // TASK clarify gates on the task detail.
        const taskTitle = unique('audit-task');
        await page.getByTestId('nav-tasks').click();
        await expect(page.getByTestId('task-view')).toBeVisible();
        await page.getByTestId('task-create-title').fill(taskTitle);
        await page.getByTestId('task-create-submit').click();
        await expect(page.getByTestId('task-list').getByText(taskTitle, { exact: false })).toBeVisible();
        await page.getByTestId('task-item').filter({ hasText: taskTitle }).first().getByTestId('task-open').click();
        await expect(page.getByTestId('task-detail')).toBeVisible();
        await ensureProviderState(page, DEAD_OLLAMA, 'unavailable');
        await page.getByTestId('task-detail-clarify').click();
        await expect(page.getByTestId('ai-not-configured')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('task-detail-clarify-result')).toHaveCount(0);

        // Restore the reachable provider; converge on server-truth state
        // even if a sibling browser re-flips the shared row meanwhile.
        await ensureProviderState(page, GOOD_OLLAMA, 'connected');
    });

    test('rejecting a real goal-breakdown proposal applies nothing', async ({ page }) => {
        // Real generation on a local 7B model may pay a cold load.
        test.slow();
        await login(page);

        const goalName = unique('audit-reject');
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-create-title').fill(goalName);
        await page.getByTestId('goal-create-horizon').selectOption('Quarterly');
        await page.getByTestId('goal-create-submit').click();
        await expect(page.getByTestId('goal-breakdown-suggestion')).toBeVisible();

        // Generation resolves the provider per request — pin the reachable
        // config right before firing.
        await ensureProviderState(page, GOOD_OLLAMA, 'connected');
        await page.getByTestId('goal-breakdown-ai').click();

        await expect(page.getByTestId('proposal-review')).toBeVisible({ timeout: 120_000 });
        const proposed = await page.getByTestId('proposal-milestones').locator('li').count();
        expect(proposed).toBeGreaterThan(0);

        // Reject (FR-62): the card unmounts, nothing is applied.
        await page.getByTestId('proposal-reject').click();
        await expect(page.getByTestId('proposal-review')).toHaveCount(0, { timeout: 10_000 });

        // The goal carries no milestones after rejection.
        await page.getByTestId('nav-goals').click();
        await expect(page.getByTestId('goals-view')).toBeVisible();
        await page.getByTestId('goal-item').filter({ hasText: goalName }).first().getByTestId('goal-open').click();
        await expect(page.getByTestId('goal-detail')).toBeVisible();
        await expect(page.getByTestId('milestones-empty')).toBeVisible();
        await expect(page.getByTestId('goal-milestones').getByTestId('milestone-item')).toHaveCount(0);

        // Leave the shared row reachable for whichever browser runs next.
        await ensureProviderState(page, GOOD_OLLAMA, 'connected');
    });
});
