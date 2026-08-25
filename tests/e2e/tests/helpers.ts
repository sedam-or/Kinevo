import { test as base, type Page } from '@playwright/test';

export const OWNER_EMAIL = 'sampalan@gmail.com';
export const OWNER_PASSWORD = process.env.E2E_OWNER_PASSWORD || 'KinevoE2E123!';

/** Stable unique suffix so repeated journey runs create distinct fixtures. */
export function unique(prefix: string): string {
    const ts = Date.now().toString(36);
    const rand = Math.random().toString(36).slice(2, 6);
    return `${prefix}-${ts}-${rand}`;
}

/**
 * Log into the SPA through the real login form and wait for the Today
 * surface to render (the primary authenticated landing view).
 *
 * Tolerates an existing session: an in-test `page.reload()` keeps the
 * stored token, so the shell renders directly without a login form
 * (golden journey H).
 */
export async function login(page: Page): Promise<void> {
    await page.goto('/app');
    try {
        await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 5_000 });
        return;
    } catch {
        // Not authenticated yet — take the real login path below.
    }
    await page.getByTestId('login-form').waitFor({ state: 'visible' });
    await page.getByTestId('login-email').fill(OWNER_EMAIL);
    await page.getByTestId('login-password').fill(OWNER_PASSWORD);
    await page.getByTestId('login-form').getByRole('button', { name: /log in/i }).click();
    // Primary landing view is Today.
    await page.getByTestId('today-view').waitFor({ state: 'visible', timeout: 30_000 });
}

export function asAuthenticated(title: string, fn: (page: Page) => Promise<void>): ReturnType<typeof base> {
    return base(title, async ({ page }) => {
        await login(page);
        await fn(page);
    });
}

export interface PlacedCapture {
    taskId: number;
    date: string;
    startAt: Date;
    endAt: Date | null;
}

/**
 * Quick-capture onto the first day (starting at `firstOffsetDays` ahead) with
 * free capacity. The shared dev owner accumulates fixtures across every suite
 * run and the scheduler caps each day with a safety reserve, so ANY fixed day
 * eventually saturates (TASK_NO_CAPACITY) — the deterministic move is to walk
 * forward until the scheduler places the task, then report which day won.
 */
export async function captureOnFreeDay(
    page: Page,
    payload: { title: string; priority_tier?: number; duration_minutes?: number; goal_id?: number; program_id?: number },
    firstOffsetDays = 11,
    maxAttempts = 30,
): Promise<PlacedCapture> {
    const token = await page.evaluate(() => window.localStorage.getItem('kinevo.auth.token'));
    for (let attempt = 0; attempt < maxAttempts; attempt++) {
        const d = new Date();
        d.setDate(d.getDate() + firstOffsetDays + attempt);
        const date = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        const res = await page.evaluate(
            async ({ path, body, token }) => {
                const response = await fetch(path, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        ...(token ? { Authorization: `Bearer ${token}` } : {}),
                    },
                    body: JSON.stringify(body),
                });
                return { status: response.status, text: await response.text() };
            },
            { path: '/api/v1/quick-capture', body: { ...payload, date }, token },
        );
        if (res.status !== 201 && res.status !== 200) {
            throw new Error(`quick-capture ${res.status}: ${res.text}`);
        }
        const parsed = JSON.parse(res.text) as {
            placed?: boolean;
            task?: { id?: number };
            assignment?: { start_at?: string; end_at?: string } | null;
        };
        if (parsed.placed === true && parsed.assignment?.start_at && parsed.task?.id) {
            return {
                taskId: parsed.task.id,
                date,
                startAt: new Date(parsed.assignment.start_at),
                endAt: parsed.assignment.end_at ? new Date(parsed.assignment.end_at) : null,
            };
        }
        // TASK_NO_CAPACITY on this day — walk to the next one.
    }
    throw new Error(`no free capacity within ${maxAttempts} days from offset ${firstOffsetDays}`);
}

/** Authenticated in-page API call against the app under test. */
export async function apiFetch(page: Page, path: string, init: RequestInit = {}): Promise<Record<string, unknown>> {
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
            return { status: response.status, text: await response.text() };
        },
        { path, init, token },
    );
    if (res.status >= 400) {
        throw new Error(`API ${path} -> ${res.status}: ${String(res.text)}`);
    }
    return res.text ? (JSON.parse(res.text) as Record<string, unknown>) : {};
}

