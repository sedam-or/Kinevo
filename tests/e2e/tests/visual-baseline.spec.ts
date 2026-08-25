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

const BASELINE: Record<string, { setup: (page: import('@playwright/test').Page) => Promise<void>; fullPage: boolean }> = {
    // fullPage only where the surface is bounded; the shared dev owner's
    // lists accumulate fixtures across runs, so unbounded lists are captured
    // as the reviewable above-the-fold viewport instead of a 1×N sliver.
    today: {
        fullPage: true,
        setup: async (page) => {
            await page.goto('/app');
            await page.getByTestId('today-view').waitFor({ state: 'visible' });
        },
    },
    task: {
        fullPage: false,
        setup: async (page) => {
            await page.getByTestId('nav-tasks').click();
            await page.getByTestId('task-view').waitFor({ state: 'visible' });
        },
    },
    goals: {
        fullPage: false,
        setup: async (page) => {
            await page.getByTestId('nav-goals').click();
            await page.getByTestId('goals-view').waitFor({ state: 'visible' });
        },
    },
    notes: {
        fullPage: false,
        setup: async (page) => {
            await page.getByTestId('nav-knowledge').click();
            await page.getByTestId('notes-view').waitFor({ state: 'visible' });
        },
    },
    canvas: {
        fullPage: false,
        setup: async (page) => {
            await page.getByTestId('nav-canvas').click();
            await page.getByTestId('canvas-view').waitFor({ state: 'visible' });
        },
    },
    analytics: {
        fullPage: false,
        setup: async (page) => {
            await page.getByTestId('nav-analytics').click();
            await page.getByTestId('analytics-presets').waitFor({ state: 'visible' });
        },
    },
    aiSettings: {
        fullPage: true,
        setup: async (page) => {
            await page.getByTestId('nav-ai-settings').click();
            await page.getByTestId('ai-settings-view').waitFor({ state: 'visible' });
        },
    },
    quickCapture: {
        fullPage: false,
        setup: async (page) => {
            await page.getByTestId('global-quick-capture').click();
            await page.getByTestId('quick-capture-modal').waitFor({ state: 'visible' });
        },
    },
} as const;

const SURFACES = Object.keys(BASELINE);

// Run in the Chromium project only; screenshots are environment-fragile across
// engines and this is a human-review baseline, not a pixel gate.
test.describe('R6 visual-regression baseline (manual review artifacts)', () => {
    for (const name of SURFACES) {
        test(`captures ${name} surface baseline`, async ({ page }, testInfo) => {
            await login(page);
            await BASELINE[name]!.setup(page);
            // Let the surface settle (lazy chunks, autosave indicators).
            await page.waitForTimeout(400);
            const shot = await page.screenshot({ fullPage: BASELINE[name]!.fullPage });
            const outDir = join('test-results', 'screenshots', testInfo.project.name);
            mkdirSync(outDir, { recursive: true });
            writeFileSync(join(outDir, `${name}.png`), shot);
            await testInfo.attach(`${name}.png`, { body: shot, contentType: 'image/png' });
        });
    }
});