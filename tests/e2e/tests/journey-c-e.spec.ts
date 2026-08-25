import { test, expect } from '@playwright/test';
import { login } from './helpers';

/**
 * TASK-R7 — closes the remaining golden-journey gaps in docs/browser-e2e.md:
 *
 * - Journey C (Recover): missed-task state is seeded by
 *   `tests/e2e/scripts/seed-journey-c.sh` + `php artisan eod:reconcile
 *   --phase=deadline` (run before this suite). Single-engine (chromium)
 *   because the seeded state is global; recorded as such in §7.
 * - Journey E (Offline): a scene committed while offline persists through the
 *   IndexedDB queue, flushes on reconnect, and survives reload (§34.6, §90).
 *
 * Drawing uses the dev-only `window.__kinevoCanvasAdapter` seam (§82) — the
 * same boundary walk proven in R4's canvas matrix. `make e2e` rebuilds assets
 * with KINEVO_E2E_SEAM=1 so this seam exists.
 */

const RECT_SCENE = {
    elements: [
        {
            id: 'r7-offline-rect',
            type: 'rectangle',
            x: 100,
            y: 100,
            width: 120,
            height: 80,
            angle: 0,
            strokeColor: '#1e1e1e',
            backgroundColor: 'transparent',
            fillStyle: 'solid',
            strokeWidth: 2,
            strokeStyle: 'solid',
            roughness: 1,
            opacity: 100,
            groupIds: [],
            roundness: null,
            seed: 7,
            version: 1,
            versionNonce: 1,
            isDeleted: false,
            boundElements: null,
            updated: 1,
            link: null,
            locked: false,
            frameId: null,
        },
    ],
    appState: { viewBackgroundColor: '#ffffff' },
    files: {},
} as const;

async function waitForAdapter(page: import('@playwright/test').Page): Promise<void> {
    await page.locator('.excalidraw__canvas:not(.static)').first().waitFor({ timeout: 90_000 });
    await page.waitForFunction(
        () => (window as unknown as { __kinevoCanvasAdapter?: unknown }).__kinevoCanvasAdapter !== undefined,
        undefined,
        { timeout: 90_000 },
    );
}

test.describe('R7 Journey C — Recover (missed task → recovery surface)', () => {
    test.skip(({ browserName }) => browserName !== 'chromium', 'seeded-state journey; chromium record only');

    test('missed task is visible and the reschedule surface responds explicitly', async ({ page }) => {
        await login(page);

        // The seeded task was flipped to `missed` by eod:reconcile (deadline phase).
        await page.getByTestId('nav-tasks').click();
        await page.getByTestId('task-view').waitFor({ timeout: 30_000 });
        const item = page.getByTestId('task-item').filter({ hasText: 'JourneyC missed report' });
        await expect(item).toBeVisible({ timeout: 30_000 });
        await expect(item.getByTestId('task-status')).toHaveText(/missed/i);

        // Detail offers the recommended recovery action (design.md §19: missed → scheduled).
        await item.getByTestId('task-open').click();
        await page.getByTestId('task-detail').waitFor({ timeout: 30_000 });

        // Recovery path: generate a draft for the default window, then open
        // the reschedule surface and propose — moves or an explicit
        // no-changes state, never silence (§11.2).
        await page.getByTestId('nav-schedule').click();
        await page.getByTestId('schedule-draft-view').waitFor({ timeout: 30_000 });
        await page.getByTestId('draft-generate').click();
        await page.getByTestId('draft-reschedule').waitFor({ timeout: 30_000 });
        await page.getByTestId('draft-reschedule').click();
        await page.getByTestId('reschedule-view').waitFor({ timeout: 30_000 });
        await page.getByTestId('reschedule-propose').click();
        await expect(
            page.getByTestId('reschedule-moves').or(page.getByTestId('reschedule-no-changes')),
        ).toBeVisible({ timeout: 30_000 });
    });
});

test.describe('R7 Journey E — Offline (draw offline → queued → reconnect → synced)', () => {
    test('offline scene survives reconnect and reload', async ({ page, context }) => {
        await login(page);
        await page.getByTestId('nav-canvas').click();
        await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });
        const title = 'R7 Offline Journey';
        await page.getByTestId('canvas-create-title').fill(title);
        await page.getByTestId('canvas-create-submit').click();
        await page.getByTestId('canvas-workspace').waitFor({ timeout: 90_000 });
        await waitForAdapter(page);

        // Go OFFLINE and commit a scene through the real adapter boundary.
        await context.setOffline(true);
        await page.evaluate((scene) => {
            (
                window as unknown as {
                    __kinevoCanvasAdapter?: { load(scene: unknown): void };
                }
            ).__kinevoCanvasAdapter?.load(scene);
        }, RECT_SCENE);
        // §34.6: offline must be VISIBLE — the save badge leaves "saved".
        await expect(page.getByTestId('canvas-save-state')).toContainText(/offline|saving|queued/i, {
            timeout: 15_000,
        });

        // Reconnect: attach the PUT waiter FIRST (the flush may race ahead of
        // any wait), then restore connectivity so the queue drains.
        const flushPut = page.waitForResponse(
            (res) =>
                /\/api\/v1\/canvases\/\d+$/.test(res.url()) &&
                res.request().method() === 'PUT' &&
                res.ok(),
            { timeout: 45_000 },
        );
        await context.setOffline(false);
        await flushPut;

        // Reload (lands on today/login — the workspace route is not kept),
        // reopen from the list, verify the scene came back FROM THE SERVER.
        await page.reload({ waitUntil: 'domcontentloaded' });
        if (await page.getByTestId('login-form').isVisible({ timeout: 5_000 }).catch(() => false)) {
            await login(page);
        } else {
            await page.getByTestId('today-view').waitFor({ timeout: 30_000 });
        }
        await page.getByTestId('nav-canvas').click();
        await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });
        const item = page.locator('[data-testid="canvas-item"]').filter({ hasText: title }).first();
        await item.waitFor({ timeout: 45_000 });
        const reopened = page.waitForResponse(
            (res) => res.url().match(/\/api\/v1\/canvases\/\d+$/) !== null && res.request().method() === 'GET',
            { timeout: 30_000 },
        );
        await item.getByTestId('canvas-open').click();
        const res2 = await reopened;
        // Canvas show returns { canvas, document } — elements live in
        // document.scene_json (no data envelope).
        const body = (await res2.json()) as {
            document?: { scene_json?: { elements?: Array<{ id?: string }> } };
        };
        const elementIds = (body?.document?.scene_json?.elements ?? []).map((e) => e.id);
        expect(elementIds).toContain('r7-offline-rect');
    });
});
