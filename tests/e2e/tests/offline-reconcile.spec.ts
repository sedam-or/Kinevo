import { test, expect, type Page, type BrowserContext } from '@playwright/test';
import { login } from './helpers';

/**
 * ADR-017 — Offline Mutation Reconciliation browser journeys (O1–O4).
 * Single-engine (chromium): the sandbox is single-owner and offline simulation
 * is context-wide.
 *
 * Offline is simulated with Playwright's `context.setOffline(true)`, which
 * flips `navigator.onLine` — the same signal the api client and AuthHost use.
 * Server-committed-response-lost replay (O2) and version-conflict (O3) are
 * driven through the real `/sync/reconcile` endpoint in-browser with the
 * session token (the same client path the MutationQueue drain uses).
 */

function isoDate(d: Date): string {
    return d.toISOString().slice(0, 10);
}

async function api(page: Page, method: string, path: string, body?: unknown, headers: Record<string, string> = {}): Promise<any> {
    return page.evaluate(async ({ method, path, body, headers }) => {
        const token = localStorage.getItem('kinevo.auth.token');
        const response = await fetch(`/api/v1${path}`, {
            method,
            headers: {
                Authorization: `Bearer ${token}`,
                'Content-Type': 'application/json',
                ...headers,
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });

        return { status: response.status, json: await response.json().catch(() => null) };
    }, { method, path, body, headers });
}

async function openTasks(page: Page): Promise<void> {
    await page.getByTestId('nav-tasks').click();
    await page.getByTestId('task-list').waitFor({ state: 'visible' });
}

async function createTaskViaUi(page: Page, title: string): Promise<void> {
    await openTasks(page);
    await page.getByTestId('task-create-title').fill(title);
    await page.getByTestId('task-create-submit').click();
    await expect(page.getByTestId('task-item').first()).toBeVisible();
}

test.describe('ADR-017 — Offline mutation reconciliation journeys', () => {
    test('JOURNEY O1 — offline mutation enqueues, reconciles on reconnect, and the queue clears', async ({ page, context }) => {
        await login(page);

        // Establish an online baseline: a task list with a known row.
        await openTasks(page);
        await page.getByTestId('task-create-title').fill('O1 baseline task');
        await page.getByTestId('task-create-submit').click();
        await expect(page.getByTestId('task-item').filter({ hasText: 'O1 baseline task' })).toBeVisible();

        // Simulate offline; make a SUPPORTED mutation (task:create).
        await context.setOffline(true);
        await page.getByTestId('task-create-title').fill('O1 offline task');
        await page.getByTestId('task-create-submit').click();

        // The change is durably queued — "Waiting to sync" surfaces via the
        // shell aggregate (not a toast spam).
        await expect(page.getByTestId('sync-status-badge')).toHaveText(/queued/i);
        await expect(page.getByTestId('sync-queued-count')).toHaveText('1 queued');

        // Restore network: reconnect drain runs the reconcile protocol.
        await context.setOffline(false);
        await expect(page.getByTestId('sync-queued-count')).toBeHidden();

        // Canonical server state is visible after rehydration.
        await expect(page.getByTestId('task-item').filter({ hasText: 'O1 offline task' })).toBeVisible({ timeout: 15_000 });
    });

    test('JOURNEY O2 — response-loss replay is idempotent: the same operation_id never duplicates', async ({ page }) => {
        await login(page);

        const runId = `o2-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
        const op = { protocol_version: 1, operation_id: runId, operation_type: 'task:create', entity_type: 'task', entity_id: null, payload: { title: `O2 replay task ${runId}`, estimated_minutes: 30 }, workspace_id: null, client_created_at: new Date().toISOString() };

        const first = await api(page, 'POST', '/sync/reconcile', { operations: [op] });
        expect(first.status).toBe(200);
        expect(first.json.outcomes[0].status).toBe('applied');

        // Same operation_id + identical payload (the response was "lost" client
        // side, so the client retries): the ledger returns the recorded outcome.
        const retry = await api(page, 'POST', '/sync/reconcile', { operations: [op] });
        expect(retry.status).toBe(200);
        expect(retry.json.outcomes[0].status).toBe('applied');
        expect(retry.json.outcomes[0].replay).toBe(true);

        // The domain mutation exists exactly once.
        const tasks = await api(page, 'GET', '/tasks');
        const matches = (tasks.json.tasks ?? []).filter((t: any) => String(t.title).startsWith(`O2 replay task ${runId}`));
        expect(matches.length).toBe(1);
    });

    test('JOURNEY O3 — a stale offline edit conflicts; server state wins and the user sees a review state', async ({ page, context }) => {
        await login(page);

        // Create + load a task (client caches version 1).
        await createTaskViaUi(page, 'O3 original');
        await page.getByTestId('task-item').filter({ hasText: 'O3 original' }).getByTestId('task-open').click();
        await page.getByTestId('task-detail').waitFor({ state: 'visible' });

        // Server advances to version 2 WITHOUT the client knowing (external fetch).
        const tasks = await api(page, 'GET', '/tasks');
        const task = (tasks.json.tasks ?? []).find((t: any) => String(t.title) === 'O3 original');
        expect(task).toBeDefined();
        const advanced = await api(page, 'PUT', `/tasks/${task.id}`, { title: 'O3 server-newer' });
        expect(advanced.status).toBe(200);

        // Offline: edit the (stale) client copy → queued with base_version 1.
        await context.setOffline(true);
        await page.getByTestId('task-edit-title').fill('O3 offline edit');
        await page.getByTestId('task-edit-save').click();
        await expect(page.getByTestId('sync-status-badge')).toHaveText(/queued/i);

        // Reconnect → drain → the reconcile hits a VERSION_CONFLICT.
        await context.setOffline(false);
        await expect(page.getByTestId('sync-status-badge')).toHaveText(/conflict/i, { timeout: 15_000 });
        await expect(page.getByTestId('sync-discard-conflicts')).toBeVisible();

        // Server canonical state was NOT overwritten.
        const after = await api(page, 'GET', '/tasks');
        const serverTask = (after.json.tasks ?? []).find((t: any) => t.id === task.id);
        expect(String(serverTask.title)).toBe('O3 server-newer');

        // User reviews and discards the local change; the queue clears.
        await page.getByTestId('sync-discard-conflicts').click();
        await expect(page.getByTestId('sync-queued-count')).toBeHidden();
    });

    test('JOURNEY O4 — a queued offline mutation survives reload and reconciles afterwards', async ({ page, context }) => {
        await login(page);

        // Offline: queue a task create.
        await context.setOffline(true);
        await openTasks(page);
        await page.getByTestId('task-create-title').fill('O4 reload task');
        await page.getByTestId('task-create-submit').click();
        await expect(page.getByTestId('sync-queued-count')).toHaveText('1 queued');

        // Reload while the mutation is queued. The IndexedDB queue is durable
        // across reloads (ADR-017 §2.15) — if it were lost, the reload would
        // have nothing to drain and the task would never appear. Reload ONLINE
        // (the boot drain then reconciles the surviving queue deterministically).
        await context.setOffline(false);
        await page.reload();
        await page.getByTestId('today-view').waitFor({ state: 'visible' });

        // The queued mutation survived the reload and reconciled: canonical
        // state appears and the queue clears.
        await page.getByTestId('nav-tasks').click();
        await page.getByTestId('task-list').waitFor({ state: 'visible' });
        await expect(page.getByTestId('task-item').filter({ hasText: 'O4 reload task' })).toBeVisible({ timeout: 15_000 });
        await expect(page.getByTestId('sync-queued-count')).toBeHidden();
    });
});