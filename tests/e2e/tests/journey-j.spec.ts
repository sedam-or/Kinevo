import { test, expect, type Page } from '@playwright/test';
import { login, unique } from './helpers';

/**
 * Journey J — Analytics → Action (TASK-P17-017, docs/browser-e2e.md §11).
 *
 *   analytics data seeded (scheduled task + focus session) → Analytics shows
 *   the What changed / Why it matters / What to do interpretation → capacity
 *   card carries the recommendation → user clicks [Review schedule] → lands in
 *   the Schedule workflow.
 */
const API = '/api/v1';

async function apiFetch(page: Page, path: string, init: RequestInit = {}): Promise<Record<string, unknown>> {
    const token = await page.evaluate(() => window.localStorage.getItem('kinevo.auth.token'));
    const res = await page.evaluate(
        async ({ path, init, token }) => {
            const response = await fetch(path, {
                ...init,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
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
    return res.body ? (JSON.parse(res.body as string) as Record<string, unknown>) : {};
}

function dateStamp(offsetDays: number): string {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

/**
 * A future day always has free capacity (the shared dev owner's "today"
 * accumulates fixtures across runs — TASK_NO_CAPACITY otherwise). The spec
 * then installs the client clock on that day so the Analytics default
 * 7-day window covers both seeds.
 */
async function seedAnalytics(page: Page): Promise<Date> {
    const offsetDays = 4 + (Date.now() % 10);
    const day = dateStamp(offsetDays);

    // A scheduled task on that day creates a ScheduleAssignment → capacity days.
    const captured = (await apiFetch(page, `${API}/quick-capture`, {
        method: 'POST',
        body: JSON.stringify({ title: unique('jJ-cap'), priority_tier: 3, duration_minutes: 45, date: day }),
    })) as { placed?: boolean };
    if (captured.placed !== true) {
        throw new Error(`journey-J capture not placed: ${JSON.stringify(captured)}`);
    }

    // A completed focus session on that day → Work-Life productive minutes.
    const noon = new Date(`${day}T12:00:00`);
    await apiFetch(page, `${API}/focus-sessions`, {
        method: 'POST',
        body: JSON.stringify({
            started_at: new Date(noon.getTime() - 45 * 60 * 1000).toISOString(),
            ended_at: noon.toISOString(),
        }),
    });
    return new Date(noon.getTime() + 60_000);
}

test.describe('Journey J — Analytics → Action (TASK-P17-017)', () => {
    /**
     * TASK-P17-020: each analytics section drives an action. Seeded-owner
     * state varies between runs (goal pressure / imbalance / completion rate),
     * so optional section actions are followed only when rendered.
     */
    async function followIfVisible(page: Page, actionTestid: string, targetTestid: string): Promise<void> {
        const button = page.getByTestId(actionTestid);
        if (!(await button.isVisible().catch(() => false))) {
            return;
        }
        await button.click();
        await expect(page.getByTestId(targetTestid)).toBeVisible({ timeout: 30_000 });
        await page.getByTestId('nav-analytics').click();
        await expect(page.getByTestId('analytics-presets')).toBeVisible({ timeout: 30_000 });
    }

    test('analytics interprets charts and capacity action lands in Schedule', async ({ page }) => {
        await login(page);
        const now = await seedAnalytics(page);

        // Render the seeded day as "now" so the default 7-day range covers it.
        await page.clock.install({ time: now });
        await page.goto('/app');
        await page.getByTestId('nav-analytics').click();
        await expect(page.getByTestId('analytics-presets')).toBeVisible({ timeout: 30_000 });

        // Wait for a chart carrying the What/Why/What-to-do interpretation.
        await expect(page.getByTestId('analytics-summary')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('interpretation-summary-changed')).toContainText(/Work share/i);

        // TASK-P17-019 audit: every chart exposes period + unit + legend.
        await expect(page.getByTestId('chart-meta-summary')).toBeVisible();
        await expect(page.getByTestId('chart-period-summary')).toContainText(/20\d\d-\d\d-\d\d – 20\d\d-\d\d-\d\d/);
        await expect(page.getByTestId('chart-unit-summary')).toContainText('% of tracked time');
        await expect(page.getByTestId('chart-legend-summary')).toHaveCount(2);
        await expect(page.getByTestId('chart-legend-capacity')).toHaveCount(2);

        // TASK-P17-018 visual audit: executive signal leads, then aggregate
        // charts, raw data last — whatever rendered must follow this order.
        const order = await page.evaluate(() => ['analytics-executive-signal', 'analytics-summary', 'analytics-goals', 'analytics-capacity', 'analytics-pillars', 'analytics-heatmap', 'analytics-days']
            .map((id) => ({ id, el: document.querySelector(`[data-testid="${id}"]`) }))
            .filter((entry): entry is { id: string; el: Element } => entry.el !== null)
            .map((entry) => ({ id: entry.id, top: entry.el.getBoundingClientRect().top })));
        expect(order.length, 'executive signal plus rendered sections').toBeGreaterThan(2);
        expect(order[0]!.id).toBe('analytics-executive-signal');
        for (let i = 1; i < order.length; i++) {
            expect(order[i]!.top, `${order[i]!.id} must sit below ${order[i - 1]!.id}`).toBeGreaterThanOrEqual(order[i - 1]!.top);
        }

                // Capacity card: recommendation label + interpretation + Review schedule.
        await expect(page.getByTestId('analytics-capacity')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByTestId('interpretation-capacity-changed')).toContainText(/realized/i);
        const review = page.getByTestId('analytics-review-schedule');
        await expect(review).toBeVisible();
        await expect(review).toContainText('Review schedule');

        // TASK-P17-020 — section actions land in their related workflows.
        await followIfVisible(page, 'analytics-review-milestone', 'goals-view');
        await followIfVisible(page, 'analytics-recovery-action', 'today-view');
        await followIfVisible(page, 'analytics-reduce-workload', 'schedule-draft-view');

        // The capacity action lands in the Schedule workflow.
        await review.click();
        await expect(page.getByTestId('schedule-draft-view')).toBeVisible({ timeout: 30_000 });
    });
});