import { test, expect, type Page } from '@playwright/test';
import { login, unique, apiFetch } from './helpers';

/**
 * P28-013 — golden journeys that were NOT already proven by the existing
 * suites, so they are NOT duplicated here (per P28 phase doc §12):
 *
 *   Journey A (Goal-first) full loop incl. AI breakdown accept → milestones →
 *   scheduled task → Today → complete → analytics: canonical-journey.spec.ts +
 *   golden-journeys G2.
 *   Journey B (Reality-first / KRS import): KrsImportApiTest (backend) +
 *   es-effective-schedule hard-landscape journeys.
 *   Journey C (Permanent Shift) / D (One-Time Exception): es-effective-schedule.
 *   Journey F (Reflection / complete → progress → next): core-loop + journey-i.
 *
 * What is added here:
 *   - Journey E — Knowledge continuity: task → note → knowledge link → canvas.
 *   - RET-005 continuation: after a goal has an executable structure, the
 *     goal detail points the user toward Today ("work-on-milestone").
 */
const API = '/api/v1';

test.describe('P28 golden journeys (missing evidence only)', () => {
    test('JOURNEY E — Knowledge continuity: task → note → linked → canvas reachable', async ({ page }) => {
        await login(page);

        // 1) Create a task (reality: the work exists).
        const taskTitle = unique('p28-e-task');
        const created = (await apiFetch(page, `${API}/tasks`, {
            method: 'POST',
            body: JSON.stringify({ title: taskTitle }),
        })) as { task?: { id: number } };
        expect(created.task?.id).toBeDefined();

        // 2) Create a note → the editor opens (context preservation).
        await page.getByTestId('nav-knowledge').click();
        await expect(page.getByTestId('notes-view')).toBeVisible();
        await page.getByTestId('note-create-title').fill(unique('p28-e-note'));
        await page.getByTestId('note-create-submit').click();
        await expect(page.getByTestId('note-editor')).toBeVisible({ timeout: 20_000 });

        // 3) Link the note to the task (thinking → execution connection).
        await page.getByTestId('link-target-type').selectOption('task');
        await page.getByTestId('link-target-id').selectOption(String(created.task!.id));
        await page.getByTestId('link-type').selectOption('references');
        await page.getByTestId('link-submit').click();
        await expect(page.getByTestId('knowledge-link-item')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('knowledge-link-item')).toContainText(taskTitle);

        // 4) Canvas remains reachable from the knowledge context (synthesis).
        await page.getByTestId('nav-canvas').click();
        await expect(page.getByTestId('canvas-view')).toBeVisible();
        await expect(page.getByTestId('canvas-create')).toBeVisible();
    });

    test('RET-005 — goal with an executable structure points the user to Today', async ({ page }) => {
        await login(page);
        const title = unique('p28-005-goal');
        const created = (await apiFetch(page, `${API}/goals`, {
            method: 'POST',
            body: JSON.stringify({ title, horizon: 'quarterly' }),
        })) as { goal?: { id: number } };
        expect(created.goal?.id).toBeDefined();
        // Executable structure = a milestone (AI breakdown accept creates these;
        // G2 proves that leg; here the continuation is exercised).
        await apiFetch(page, `${API}/goals/${created.goal!.id}/milestones`, {
            method: 'POST',
            body: JSON.stringify({ title: unique('p28-005-ms') }),
        });

        await page.getByTestId('nav-goals').click();
        const item = page.locator('[data-testid="goal-item"]').filter({ hasText: title }).first();
        await item.getByTestId('goal-open').click();
        await expect(page.getByTestId('goal-detail')).toBeVisible({ timeout: 20_000 });
        // The next action resolves to "work on the milestone in Today".
        const action = page.getByTestId('next-action');
        await expect(action).toHaveAttribute('data-action', 'work-on-milestone', { timeout: 20_000 });
        await page.getByTestId('next-action-button').click();
        await expect(page.getByTestId('today-view')).toBeVisible({ timeout: 20_000 });
    });
});
