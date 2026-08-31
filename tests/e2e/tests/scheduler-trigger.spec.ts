import { test, expect, type Page } from '@playwright/test';
import { login } from './helpers';

/**
 * ADR-016 — Scheduler Trigger, Sync Now & Draft Approval Lifecycle browser
 * journeys (S1–S4). Single-engine (chromium): the sandbox is single-owner and
 * the journeys anchor to the live "today" (same posture as the ES/CE specs).
 *
 * Prerequisite: `tests/e2e/scripts/seed-journey-s.sh` runs the REAL weekly
 * trigger (`schedule:prepare-weekly`) for the owner, so JOURNEY S1 reviews a
 * draft produced by the actual console command.
 *
 * Evidence split (recorded in docs/browser-e2e.md):
 * - UI-rendered assertions: weekly draft banner + Apply, Sync Now button +
 *   status text, proposal diff, Today review pill.
 * - In-browser-context API assertions (fetch with the session token):
 *   Today payload needs_review flag, placement coordinates, locks.
 */

function isoDate(d: Date): string {
    return d.toISOString().slice(0, 10);
}

function at(day: Date, time: string): string {
    return `${isoDate(day)}T${time}:00`;
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

async function cleanupSFixtures(page: Page, keepWeeklyDraft = false): Promise<void> {
    const listed = await api(page, 'GET', '/hard-landscape');
    if (listed.status === 200) {
        for (const item of listed.json.hard_landscape ?? []) {
            if (String(item.title).startsWith('JourneyS')) {
                await api(page, 'DELETE', `/hard-landscape/${item.id}`);
            }
        }
    }
    if (!keepWeeklyDraft) {
        const drafts = await api(page, 'GET', '/schedule/drafts');
        for (const record of drafts.json.drafts ?? []) {
            await api(page, 'POST', `/schedule/drafts/${record.id}/discard`);
        }
    }
    await api(page, 'POST', '/schedule/sync');
}

async function currentVersion(page: Page): Promise<number> {
    const today = await api(page, 'GET', `/today?date=${isoDate(new Date())}`);
    return Number(today.json.schedule_version ?? 1);
}

async function fetchToday(page: Page): Promise<any> {
    return api(page, 'GET', `/today?date=${isoDate(new Date())}`);
}

async function openSchedule(page: Page): Promise<void> {
    await page.getByTestId('nav-schedule').click();
}

test.describe('ADR-016 — Scheduler trigger, Sync Now & draft lifecycle journeys', () => {
    test('JOURNEY S1 — weekly draft is generated, reviewed, applied, and reflected on Today', async ({ page }) => {
        await login(page);
        await cleanupSFixtures(page, true);
        // The seed script already drove `schedule:prepare-weekly`; the Schedule
        // surface must surface the pending draft as a reviewable banner.
        await openSchedule(page);
        const banner = page.getByTestId('weekly-draft-banner');
        await expect(banner).toBeVisible();

        const proposed = page.getByTestId('weekly-draft-item');
        await expect(proposed.first()).toBeVisible();

        // Apply through the UI — the ONLY path to accepted placement.
        await page.getByTestId('weekly-draft-apply').click();
        await expect(page.getByTestId('weekly-draft-applied')).toBeVisible();

        // Today reflects the accepted change.
        const today = await fetchToday(page);
        expect(today.status).toBe(200);
        const events = today.json.events ?? [];
        const placed = events.some((e: any) => String(e.task?.title ?? '').startsWith('JourneyS'));
        expect(placed).toBe(true);
    });

    test('JOURNEY S2 — reality change marks the schedule impacted; preview then Cancel leaves the accepted schedule unchanged', async ({ page }) => {
        await login(page);
        await cleanupSFixtures(page);

        // Accepted placement (draft apply = the explicit approval path).
        const created = await api(page, 'POST', '/tasks', { title: 'JourneyS s2 report', estimated_minutes: 60 });
        expect(created.status).toBe(201);
        const taskId = created.json.task.id;
        const today = isoDate(new Date());
        const applied = await api(page, 'POST', '/schedule/draft/apply', {
            draft: { assignments: [{ task_id: String(taskId), title: 'JourneyS s2 report', start: at(new Date(), '19:00'), end: at(new Date(), '20:00') }], unassigned: [] },
            base_version: await currentVersion(page),
        });
        expect(applied.status).toBe(200);

        // Reality change overlapping the placement (Hard Landscape is the KRS-
        // confirmed reality layer).
        const block = await api(page, 'POST', '/hard-landscape', {
            title: 'JourneyS s2 lecture',
            type: 'one_time',
            start_at: at(new Date(), '19:30'),
            end_at: at(new Date(), '21:00'),
        });
        expect(block.status).toBe(201);

        // Today is marked "needs review" (bounded impact detection).
        const impacted = await fetchToday(page);
        expect(impacted.json.schedule_needs_review).toBe(true);

        // UI: the attention state is visible (text, not color-only). The shell
        // already sits on Today — reload so the view refetches the state.
        await page.reload();
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 20_000 });
        await expect(page.getByTestId('today-needs-review')).toBeVisible();

        // Sync Now produces a previewable diff; nothing is written yet.
        await openSchedule(page);
        await page.getByTestId('draft-reschedule').click();
        await page.getByTestId('schedule-sync').click();
        await expect(page.getByTestId('sync-status')).toContainText('Changes found');
        await expect(page.getByTestId('reschedule-move').first()).toBeVisible();

        // Cancel → accepted schedule unchanged.
        await page.getByTestId('reschedule-cancel').click();
        const after = await fetchToday(page);
        const assignment = (after.json.events ?? []).find((e: any) => String(e.task?.title ?? '') === 'JourneyS s2 report')?.assignment;
        expect(String(assignment?.start_at ?? '')).toContain('19:00');
    });

    test('JOURNEY S3 — reality change → Sync Now → Apply moves flexible work safely', async ({ page }) => {
        await login(page);
        await cleanupSFixtures(page);

        const created = await api(page, 'POST', '/tasks', { title: 'JourneyS s3 report', estimated_minutes: 60 });
        expect(created.status).toBe(201);
        const taskId = created.json.task.id;
        await api(page, 'POST', '/schedule/draft/apply', {
            draft: { assignments: [{ task_id: String(taskId), title: 'JourneyS s3 report', start: at(new Date(), '19:00'), end: at(new Date(), '20:00') }], unassigned: [] },
            base_version: await currentVersion(page),
        });
        const block = await api(page, 'POST', '/hard-landscape', {
            title: 'JourneyS s3 lecture',
            type: 'one_time',
            start_at: at(new Date(), '19:30'),
            end_at: at(new Date(), '21:00'),
        });
        expect(block.status).toBe(201);

        await openSchedule(page);
        await page.getByTestId('draft-reschedule').click();
        await page.getByTestId('schedule-sync').click();
        await expect(page.getByTestId('sync-status')).toContainText('Changes found');

        // Apply the reviewed diff.
        await page.getByTestId('reschedule-apply').click();
        await expect(page.getByTestId('reschedule-applied')).toBeVisible();

        // The work now sits OUTSIDE the new reality (no overlap with the block).
        const after = await fetchToday(page);
        const assignment = (after.json.events ?? []).find((e: any) => String(e.task?.title ?? '') === 'JourneyS s3 report')?.assignment;
        expect(assignment).toBeDefined();
        const start = new Date(assignment.start_at).getTime();
        const end = new Date(assignment.end_at).getTime();
        const bs = new Date(at(new Date(), '19:30')).getTime();
        const be = new Date(at(new Date(), '21:00')).getTime();
        const overlaps = start < be && end > bs;
        expect(overlaps).toBe(false);
    });

    test('JOURNEY S4 — locked work is retained by Sync Now and never moved', async ({ page }) => {
        await login(page);
        await cleanupSFixtures(page);

        const created = await api(page, 'POST', '/tasks', { title: 'JourneyS s4 fixed', estimated_minutes: 60 });
        expect(created.status).toBe(201);
        const taskId = created.json.task.id;
        await api(page, 'POST', '/schedule/draft/apply', {
            draft: { assignments: [{ task_id: String(taskId), title: 'JourneyS s4 fixed', start: at(new Date(), '19:00'), end: at(new Date(), '20:00') }], unassigned: [] },
            base_version: await currentVersion(page),
        });
        const lock = await api(page, 'POST', `/tasks/${taskId}/assignment/lock`);
        expect(lock.status).toBe(200);

        // Reality collides with the LOCKED placement.
        await api(page, 'POST', '/hard-landscape', {
            title: 'JourneyS s4 lecture',
            type: 'one_time',
            start_at: at(new Date(), '19:30'),
            end_at: at(new Date(), '21:00'),
        });

        await openSchedule(page);
        await page.getByTestId('draft-reschedule').click();
        await page.getByTestId('schedule-sync').click();

        // The diff never proposes moving locked work; conflicts stay visible.
        const syncStatus = await page.getByTestId('sync-status').textContent();
        expect(String(syncStatus)).toMatch(/Changes found|Conflicts remain|up to date/);
        const moves = page.getByTestId('reschedule-move');
        const moveCount = await moves.count();
        for (let i = 0; i < moveCount; i++) {
            const text = await moves.nth(i).textContent();
            expect(String(text)).not.toContain('JourneyS s4 fixed');
        }

        // The locked placement survives at its accepted slot on Today.
        const after = await fetchToday(page);
        const assignment = (after.json.events ?? []).find((e: any) => String(e.task?.title ?? '') === 'JourneyS s4 fixed')?.assignment;
        expect(String(assignment?.start_at ?? '')).toContain('19:00');
    });
});
