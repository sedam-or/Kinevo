import { test } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { login } from './helpers';

/**
 * R6 visual-regression baseline (design.md §87).
 *
 * Captures full-page screenshots of the critical surfaces for INTENTIONAL
 * human review. Snapshots are never compared or auto-accepted here — they are
 * artifacts written to test-results/screenshots/<browser>/ and reviewed
 * against design.md §81–§87; see docs/browser-e2e.md §9.
 */

const BASELINE = {
    today: async (page: import('@playwright/test').Page) => {
        await page.goto('/app');
        await page.getByTestId('today-view').waitFor({ state: 'visible' });
    },
    task: async (page: import('@playwright/test').Page) => {
        await page.getByTestId('nav-tasks').click();
        await page.getByTestId('task-view').waitFor({ state: 'visible' });
    },
    goals: async (page: import('@playwright/test').Page) => {
        await page.getByTestId('nav-goals').click();
        await page.getByTestId('goals-view').waitFor({ state: 'visible' });
    },
    notes: async (page: import('@playwright/test').Page) => {
        await page.getByTestId('nav-knowledge').click();
        await page.getByTestId('notes-view').waitFor({ state: 'visible' });
    },
    canvas: async (page: import('@playwright/test').Page) => {
        await page.getByTestId('nav-canvas').click();
        await page.getByTestId('canvas-view').waitFor({ state: 'visible' });
    },
    analytics: async (page: import('@playwright/test').Page) => {
        await page.getByTestId('nav-analytics').click();
        await page.getByTestId('analytics-presets').waitFor({ state: 'visible' });
    },
} as const;

const SURFACES = Object.keys(BASELINE);

// Run in the Chromium project only; screenshots are environment-fragile across
// engines and this is a human-review baseline, not a pixel gate.
test.describe('R6 visual-regression baseline (manual review artifacts)', () => {
    for (const name of SURFACES) {
        test(`captures ${name} surface baseline`, async ({ page }, testInfo) => {
            await login(page);
            await BASELINE[name as keyof typeof BASELINE](page);
            // Let the surface settle (lazy chunks, autosave indicators).
            await page.waitForTimeout(400);
            const shot = await page.screenshot({ fullPage: true });
            const outDir = join('test-results', 'screenshots', testInfo.project.name);
            mkdirSync(outDir, { recursive: true });
            writeFileSync(join(outDir, `${name}.png`), shot);
            await testInfo.attach(`${name}.png`, { body: shot, contentType: 'image/png' });
        });
    }
});