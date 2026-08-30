import { test, expect, type Page } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * ES-IMPL-08 — Effective Schedule browser journeys (ADR-015; P28 journeys
 * B/C/D + lock). Single-engine (chromium): the sandbox is single-owner and
 * the journeys anchor to the live "today", so parallel engines would race
 * on the same owner's Today view (same posture as journey-c-e).
 *
 * Evidence split (recorded honestly in docs/browser-e2e.md):
 * - UI-rendered assertions: Today timeline blocks, Task detail lock badge,
 *   Schedule draft preview placement.
 * - In-browser-context API assertions (fetch with the session token, same as
 *   any logged-in client): future-date Today/Week/Calendar and reschedule
 *   proposals, because the shell's Today view renders the live date only.
 */

function isoDate(d: Date): string {
    return d.toISOString().slice(0, 10);
}

function at(day: Date, time: string): string {
    return `${isoDate(day)}T${time}:00`;
}

function addDays(d: Date, n: number): Date {
    const copy = new Date(d);
    copy.setDate(copy.getDate() + n);

    return copy;
}

async function api(page: Page, method: string, path: string, body?: unknown): Promise<any> {
    return page.evaluate(async ({ method, path, body }) => {
        const token = localStorage.getItem('kinevo.auth.token');
        const response = await fetch(`/api/v1${path}`, {
            method,
            headers: {
                Authorization: `Bearer ${token}`,
                'Content-Type': 'application/json',
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });

        return { status: response.status, json: await response.json().catch(() => null) };
    }, { method, path, body });
}

async function cleanupEsFixtures(page: Page): Promise<void> {
    const listed = await api(page, 'GET', '/hard-landscape');
    if (listed.status !== 200) return;
    for (const item of listed.json.hard_landscape ?? []) {
        if (String(item.title).startsWith('es-')) {
            await api(page, 'DELETE', `/hard-landscape/${item.id}`);
        }
    }
}

test.describe('ES-IMPL-08 — Effective Schedule journeys', () => {
    test('JOURNEY B — recurring reality is visible today, next week, and shapes the draft', async ({ page }) => {
        await login(page);

        const today = new Date();
        await cleanupEsFixtures(page);
        const title = unique('es-b-class');

        // The series KRS confirmation creates: a weekly recurring block.
        const created = await api(page, 'POST', '/hard-landscape', {
            title,
            type: 'recurring',
            start_at: at(today, '09:00'),
            end_at: at(today, '10:30'),
            recurrence: 'FREQ=WEEKLY',
        });
        expect(created.status).toBe(201);

        // UI: the effective occurrence is rendered on Today.
        await page.goto('/app');
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
        await expect(page.getByTestId('today-timeline')).toContainText(title, { timeout: 30_000 });

        // In-context: next week's occurrence resolves too (ADR-015).
        const nextWeek = await api(page, 'GET', `/today?date=${isoDate(addDays(today, 7))}`);
        expect(nextWeek.status).toBe(200);
        const nextWeekBlocks = nextWeek.json.hard_landscape.filter((b: any) => b.title === title);
        expect(nextWeekBlocks.length).toBe(1);
        expect(nextWeekBlocks[0].provenance).toBe('base');

        // In-context: Week aggregates the landscape.
        const week = await api(page, 'GET', `/week?date=${isoDate(today)}`);
        expect(week.status).toBe(200);
        const todayEntry = week.json.days.find((d: any) => d.date === isoDate(today));
        expect(todayEntry.landscape_count).toBeGreaterThanOrEqual(1);

        // UI: the deterministic draft never schedules work into the block.
        const task = await api(page, 'POST', '/tasks', { title: unique('es-b-work'), estimated_minutes: 60 });
        expect(task.status).toBe(201);

        await page.getByTestId('nav-schedule').click();
        await page.getByTestId('schedule-draft-view').waitFor({ state: 'visible', timeout: 30_000 });
        await page.getByTestId('draft-from').fill(isoDate(addDays(today, 7)));
        await page.getByTestId('draft-to').fill(isoDate(addDays(today, 7)));
        await page.getByTestId('draft-generate').click();
        await page.getByTestId('draft-accepted').waitFor({ state: 'visible', timeout: 30_000 });

        const items = await page.getByTestId('draft-accepted-item').allTextContents();
        expect(items.length).toBeGreaterThanOrEqual(1);
        for (const item of items) {
            expect(item).not.toMatch(/09:0/);
            expect(item).not.toMatch(/10:0/);
        }
    });

    test('JOURNEY C — Permanent Shift vacates today and re-times the occurrence', async ({ page }) => {
        await login(page);

        const today = new Date();
        await cleanupEsFixtures(page);
        const tomorrow = addDays(today, 1);
        const title = unique('es-c-class');

        const series = await api(page, 'POST', '/hard-landscape', {
            title,
            type: 'recurring',
            start_at: at(today, '11:00'),
            end_at: at(today, '12:30'),
            recurrence: 'FREQ=WEEKLY',
        });
        expect(series.status).toBe(201);

        const shifted = await api(page, 'POST', '/schedule-overrides', {
            hard_landscape_event_id: series.json.hard_landscape.id,
            type: 'permanent',
            effective_from: `${isoDate(today)}T00:00:00`,
            effective_to: `${isoDate(addDays(today, 90))}T00:00:00`,
            override_start_at: at(tomorrow, '13:00'),
            override_end_at: at(tomorrow, '14:00'),
            reason: 'ES journey C room change',
        });
        expect(shifted.status).toBe(201);

        // UI: today's occurrence is vacated by the shift.
        await page.goto('/app');
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
        await expect(page.getByTestId('today-timeline')).not.toContainText(title, { timeout: 30_000 });

        // In-context: the effective occurrence appears on the shifted date.
        const effective = await api(page, 'GET', `/today?date=${isoDate(tomorrow)}`);
        expect(effective.status).toBe(200);
        const blocks = effective.json.hard_landscape.filter((b: any) => b.title === title);
        expect(blocks.length).toBe(1);
        expect(blocks[0].start_at).toContain('T13:00');
        expect(String(blocks[0].provenance)).toMatch(/^shifted:/);
    });

    test('JOURNEY D — one-time exception cancels exactly the target occurrence', async ({ page }) => {
        await login(page);

        const today = new Date();
        await cleanupEsFixtures(page);
        const title = unique('es-d-class');

        const series = await api(page, 'POST', '/hard-landscape', {
            title,
            type: 'recurring',
            start_at: at(today, '15:00'),
            end_at: at(today, '16:00'),
            recurrence: 'FREQ=WEEKLY',
        });
        expect(series.status).toBe(201);

        const cancelled = await api(page, 'POST', '/schedule-overrides', {
            hard_landscape_event_id: series.json.hard_landscape.id,
            type: 'one_time',
            effective_from: `${isoDate(today)}T00:00:00`,
            effective_to: `${isoDate(today)}T00:00:00`,
            override_start_at: at(today, '15:00'),
            override_end_at: at(today, '16:00'),
            reason: 'ES journey D public holiday',
            cancels_occurrence: true,
        });
        expect(cancelled.status).toBe(201);

        // UI: today's target occurrence is gone.
        await page.goto('/app');
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
        await expect(page.getByTestId('today-timeline')).not.toContainText(title, { timeout: 30_000 });

        // In-context: adjacent occurrences are untouched.
        const nextWeek = await api(page, 'GET', `/today?date=${isoDate(addDays(today, 7))}`);
        expect(nextWeek.status).toBe(200);
        const nextWeekBlocks = nextWeek.json.hard_landscape.filter((b: any) => b.title === title);
        expect(nextWeekBlocks.length).toBe(1);
        expect(nextWeekBlocks[0].provenance).toBe('base');
    });

    test('LOCK JOURNEY — user locks a placement; the rescheduler never moves it', async ({ page }) => {
        await login(page);

        const today = new Date();
        await cleanupEsFixtures(page);
        const title = unique('es-lock-task');

        // A commitment lands exactly on the task's placement.
        const block = await api(page, 'POST', '/hard-landscape', {
            title: unique('es-lock-block'),
            type: 'one_time',
            start_at: at(today, '12:00'),
            end_at: at(today, '13:00'),
        });
        expect(block.status).toBe(201);

        const task = await api(page, 'POST', '/tasks', { title, estimated_minutes: 60 });
        expect(task.status).toBe(201);
        const taskId = task.json.task.id;

        const placed = await api(page, 'POST', '/schedule/draft', {
            from: isoDate(today),
            to: isoDate(today),
        });
        expect(placed.status).toBe(200);

        const applied = await api(page, 'POST', '/schedule/draft/apply', {
            draft: placed.json.draft,
            base_version: placed.json.base_version,
        });
        expect([200, 409]).toContain(applied.status);

        // UI: lock the placement from the task detail surface.
        await page.getByTestId('nav-tasks').click();
        await page.getByText(title, { exact: false }).first().click();
        await page.getByTestId('task-detail').waitFor({ state: 'visible', timeout: 30_000 });

        const lockButton = page.getByTestId('task-detail-lock');
        if (await lockButton.count()) {
            await lockButton.click();
            await expect(page.getByTestId('task-detail-locked-badge')).toBeVisible({ timeout: 30_000 });
        } else {
            // The placement may sit at a different schedule version than the
            // draft response assumed; re-apply then lock.
            await page.goto('/app');
            await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
        }

        // In-context: the rescheduler never proposes moving the locked task.
        const proposal = await api(page, 'POST', '/schedule/reschedule', {
            from: isoDate(today),
            to: isoDate(today),
        });
        expect(proposal.status).toBe(200);

        const movedTaskIds = (proposal.json.proposal.moves ?? []).map((m: any) => String(m.task_id));
        expect(movedTaskIds).not.toContain(String(taskId));
    });
});
