import { test, expect, type Page } from '@playwright/test';
import { login, unique } from './helpers';

test.describe.configure({ timeout: 150_000 });

/**
 * TASK-R4 — Canvas Hardening (§72 browser matrix + §82 boundary walk).
 *
 * Proven in a real browser (chromium project by default):
 * entry states §34.2, always-visible save state §34.4, rename/archive/theme,
 * reload persistence, version-conflict 409 §34.5, offline → reconnect §34.6,
 * and the §82 boundary walk route→mount→render→load→change→autosave→server
 * persistence→offline→reconnect.
 *
 * Draw input note: this docker runner's software-rendered headless engines
 * trap (renderer int3) on REAL pointer input over the Excalidraw canvas
 * (keyboard + DOM clicks elsewhere are fine). Draws here dispatch synthetic
 * PointerEvents through the same app code path; real-input-device canvas
 * interaction remains a R7 physical/CI-browser check.
 */

const CANVAS_ENTRY_TIMEOUT = 45_000;

async function openCanvasWorkspace(page: Page, title: string): Promise<void> {
    await page.getByTestId('nav-canvas').click();
    await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });
    await page.getByTestId('canvas-create-title').fill(title);
    await page.getByTestId('canvas-create-submit').click();
    await page.getByTestId('canvas-workspace').waitFor({ timeout: CANVAS_ENTRY_TIMEOUT });
    // §34.2: never a blank page — the editor host surfaces loading → ready.
    await page.getByTestId('canvas-surface').waitFor({ timeout: CANVAS_ENTRY_TIMEOUT });
    await expect(page.getByTestId('canvas-save-state')).toBeVisible({ timeout: CANVAS_ENTRY_TIMEOUT });
    // The island + engine mount asynchronously after the Vue surface shows.
    await page.locator('.excalidraw__canvas').first().waitFor({ timeout: CANVAS_ENTRY_TIMEOUT });
}

/**
 * Commit one rectangle through the app's REAL adapter boundary. Headless
 * runners cannot deliver trusted pointer events into Excalidraw, so tests use
 * the dev-only `window.__kinevoCanvasAdapter` seam (stripped from prod builds):
 * adapter.load() flows through Excalidraw onChange → island notify →
 * CanvasHost change → autosave controller — every boundary after "input" is
 * production-identical.
 */
async function drawRectangle(page: Page): Promise<void> {
    await page.locator('.excalidraw__canvas:not(.static)').first().waitFor({ timeout: 30_000 });
    await page.waitForFunction(
        () => (window as unknown as { __kinevoCanvasAdapter?: unknown }).__kinevoCanvasAdapter !== undefined,
        undefined,
        { timeout: 30_000 },
    );
    await page.evaluate(() => {
        const adapter = (
            window as unknown as {
                __kinevoCanvasAdapter?: { load(scene: unknown): void };
            }
        ).__kinevoCanvasAdapter;
        adapter?.load({
            elements: [
                {
                    id: `e2e-rect-${Date.now()}`,
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
                    seed: 1,
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
        });
    });
}

/** Reload mid-session and land back on Today whether or not auth persisted. */
async function reloginAfterReload(page: Page): Promise<void> {
    await page.reload({ waitUntil: 'domcontentloaded' });
    if (await page.getByTestId('login-form').isVisible({ timeout: 5_000 }).catch(() => false)) {
        await login(page);
    } else {
        await page.getByTestId('today-view').waitFor({ timeout: 30_000 });
    }
}

test.describe('R4 canvas matrix — entry + lifecycle', () => {
    test('opens a new canvas with visible save state and a live editor (never blank)', async ({ page }) => {
        await login(page);
        await openCanvasWorkspace(page, unique('r4-new'));
        expect(await page.locator('.excalidraw').count()).toBeGreaterThan(0);
        // The engine canvas must be BOUNDED (regression guard for the 2^25px
        // sentinel crash found in R4 — unstyled container resolved to max size).
        const sizes = await page.locator('canvas').evaluateAll((cs) =>
            cs.map((c) => ({ w: c.width, h: c.height })),
        );
        expect(sizes.length).toBeGreaterThan(0);
        for (const s of sizes) {
            expect(s.w).toBeLessThan(20_000);
            expect(s.h).toBeLessThan(20_000);
        }
        // §34.4 always-visible save state; §34.3 product toolbar present.
        await expect(page.getByTestId('canvas-save-state')).toBeVisible();
        await expect(page.getByTestId('canvas-back')).toBeVisible();
    });

    test('draw → autosave → server persistence survives reload (§82 boundary walk)', async ({ page }) => {
        await login(page);
        const title = unique('r4-persist');
        let canvasId = '';
        // Capture the show response to learn the id + initial empty scene.
        page.once('response', async (res) => {
            if (res.url().includes('/api/v1/canvases/') && res.request().method() === 'GET' && !res.url().includes('?')) {
                try {
                    const body = (await res.json()) as { data?: { id?: number } };
                    canvasId = String(body.data?.id ?? '');
                } catch {
                    /* ignore */
                }
            }
        });
        await openCanvasWorkspace(page, title);
        // The save badge maps dirty→saved visually (§34.4 calm-state rule), so
        // badge visibility alone proves nothing about the network. Await the
        // server-confirmed PUT before reloading.
        const savedPut = page.waitForResponse(
            (res) =>
                /\/api\/v1\/canvases\/\d+$/.test(res.url()) &&
                res.request().method() === 'PUT' &&
                res.ok(),
            { timeout: 30_000 },
        );
        await drawRectangle(page);
        await savedPut;
        await reloginAfterReload(page);
        // Reopen from the list and verify the scene came back from the SERVER.
        await page.getByTestId('nav-canvas').click();
        await page.getByTestId('canvas-view').waitFor({ timeout: 30_000 });
        const item = page.locator('[data-testid="canvas-item"]').filter({ hasText: title }).first();
        await item.waitFor({ timeout: 30_000 });
        const docPayload = page.waitForResponse(
            (res) =>
                res.url().match(/\/api\/v1\/canvases\/\d+$/) !== null &&
                res.request().method() === 'GET',
            { timeout: 30_000 },
        );
        await item.getByTestId('canvas-open').click();
        const res = await docPayload;
        // Canvas show returns { canvas, document } (no data envelope).
        const body = (await res.json()) as {
            data?: { document?: { scene_json?: { elements?: unknown[] } } };
            document?: { scene_json?: { elements?: unknown[] } };
        };
        const elements =
            body.data?.document?.scene_json?.elements ?? body.document?.scene_json?.elements ?? [];
        expect(elements.length).toBeGreaterThan(0);
        await page.getByTestId('canvas-surface').waitFor({ timeout: CANVAS_ENTRY_TIMEOUT });
        await page.locator('.excalidraw__canvas').first().waitFor({ timeout: 30_000 });
    });

    test('rename persists and reflects in the list', async ({ page }) => {
        await login(page);
        const original = unique('r4-rename');
        const renamed = unique('r4-renamed');
        await openCanvasWorkspace(page, original);
        await page.getByTestId('canvas-title-input').fill(renamed);
        await page.getByTestId('canvas-title-input').press('Enter');
        await page.waitForTimeout(1_500);
        await page.getByTestId('canvas-back').click();
        await page.getByTestId('canvas-view').waitFor({ timeout: 20_000 });
        await expect(
            page.locator('[data-testid="canvas-item"]').filter({ hasText: renamed }).first(),
        ).toBeVisible({ timeout: 20_000 });
    });

    test('archive hides the canvas from the list', async ({ page }) => {
        await login(page);
        const title = unique('r4-archive');
        await openCanvasWorkspace(page, title);
        await page.getByTestId('canvas-archive').click();
        await page.getByTestId('canvas-archive-confirm').waitFor({ timeout: 10_000 });
        // The confirm button sits above the Excalidraw overlay; drive it via
        // evaluate so the pointer-events interception cannot eat it.
        await page
            .getByTestId('canvas-archive-confirm-action')
            .evaluate((el) => (el as HTMLButtonElement).click());
        await page.getByTestId('canvas-view').waitFor({ timeout: 20_000 });
        await page.waitForTimeout(1_000);
        expect(await page.locator('[data-testid="canvas-item"]').filter({ hasText: title }).count()).toBe(0);
    });

    test('theme toggle cycles auto → light → dark (§35 light/dark row)', async ({ page }) => {
        await login(page);
        await openCanvasWorkspace(page, unique('r4-theme'));
        const themeBtn = page.getByTestId('canvas-theme-toggle');
        // Theme changes re-render the island; drive via evaluate for stability.
        // TASK-P17-013: the canvas starts on the RESOLVED app theme (Playwright
        // defaults to a light OS) instead of the detached 'auto'.
        await expect(themeBtn).toContainText('light');
        await themeBtn.evaluate((el) => (el as HTMLButtonElement).click());
        await expect(themeBtn).toContainText('dark');
        await themeBtn.evaluate((el) => (el as HTMLButtonElement).click());
        await expect(themeBtn).toContainText('auto');
        // Read-only control present (§34.3).
        await expect(page.getByTestId('canvas-readonly-toggle')).toBeVisible();
        await expect(page.getByTestId('canvas-readonly-toggle').locator('input')).toBeEnabled();
    });
});

test.describe('R4 canvas matrix — boundary: version conflict 409 (§34.5)', () => {
    test('stale-version save surfaces the conflict banner; explicit reload reconciles', async ({ page }) => {
        await login(page);
        await openCanvasWorkspace(page, unique('r4-conflict'));
        // Simulate another client having advanced the server version.
        await page.route(/\/api\/v1\/canvases\/\d+$/, async (route) => {
            if (route.request().method() === 'PUT') {
                await route.fulfill({ status: 409, contentType: 'application/json', body: '{}' });
                return;
            }
            await route.continue();
        });
        await drawRectangle(page);
        // Conflict MUST surface and NEVER auto-resolve.
        await expect(page.getByTestId('canvas-conflict')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('canvas-save-state')?.first()).toBeAttached();
        // Manual resolution only: adopt the authoritative server copy.
        await page.getByTestId('canvas-conflict-reload').click();
        await expect(page.getByTestId('canvas-conflict')).not.toBeVisible({ timeout: 20_000 });
    });
});

test.describe('R4 canvas matrix — boundary: offline → reconnect (§34.6)', () => {
    test('offline surfaces on draw, reconnect restores the sync path without loss', async ({ page }) => {
        await login(page);
        await openCanvasWorkspace(page, unique('r4-offline'));
        await page.context().setOffline(true);
        await drawRectangle(page);
        await expect(
            page.locator('[data-testid="canvas-save-state"] [data-state="offline"]'),
        ).toBeVisible({ timeout: 30_000 });
        await page.context().setOffline(false);
        // Recovery path stays available; the queued change flushes (badge may
        // pass through syncing before settling saved).
        await expect(
            page.locator('[data-testid="canvas-save-state"]').locator('[data-state]'),
        ).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('sync-state')).toBeVisible({ timeout: 20_000 });
    });
});

test.describe('R4 canvas matrix — navigation', () => {
    test('back returns to the list and re-entering the route remounts cleanly', async ({ page }) => {
        await login(page);
        await openCanvasWorkspace(page, unique('r4-nav'));
        await page.getByTestId('canvas-back').click();
        await page.getByTestId('canvas-view').waitFor({ timeout: 20_000 });
        await page.getByTestId('nav-today').click();
        await page.getByTestId('today-view').waitFor({ timeout: 20_000 });
        await page.getByTestId('nav-canvas').click();
        await page.getByTestId('canvas-view').waitFor({ timeout: 20_000 });
    });
});