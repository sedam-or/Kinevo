import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        scheduleDraftApi: {
            generate: vi.fn(),
            applyDraft: vi.fn(),
            propose: vi.fn(),
            applyProposal: vi.fn(),
        },
    };
});

vi.mock('../../imports/api', () => ({
    importApi: {
        uploadKrs: vi.fn(),
        get: vi.fn(),
        confirm: vi.fn(),
        discard: vi.fn(),
        uploadIcs: vi.fn(),
        getIcs: vi.fn(),
        confirmIcs: vi.fn(),
        discardIcs: vi.fn(),
    },
}));

vi.mock('../../exports/api', () => ({
    downloadScheduleIcs: vi.fn(),
}));

import ScheduleDraftView from '../ScheduleDraftView.vue';
import RescheduleView from '../RescheduleView.vue';
import { useScheduleDraftStore } from '../store';
import { scheduleDraftApi } from '../api';
import { importApi } from '../../imports/api';
import { downloadScheduleIcs } from '../../exports/api';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('ScheduleDraftView', () => {
    it('generates a draft and shows accepted and rejected tasks', async () => {
        vi.mocked(scheduleDraftApi.generate).mockResolvedValue({
            draft: {
                assignments: [{ task_id: '1', title: 'Write', start: '2026-08-17T09:00:00', end: '2026-08-17T10:00:00' }],
                unassigned: [{ task_id: '2', title: 'Call', reason: 'NO_AVAILABLE_SLOT' }],
            },
            base_version: 5,
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ScheduleDraftView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="draft-generate"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="draft-accepted-item"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="draft-rejected-item"]').exists()).toBe(true);
    });

    it('applies a draft', async () => {
        vi.mocked(scheduleDraftApi.generate).mockResolvedValue({
            draft: {
                assignments: [{ task_id: '1', title: 'Write', start: '2026-08-17T09:00:00', end: '2026-08-17T10:00:00' }],
                unassigned: [],
            },
            base_version: 5,
        });
        vi.mocked(scheduleDraftApi.applyDraft).mockResolvedValue({ version: 6, applied: true, assignments: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ScheduleDraftView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="draft-generate"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="draft-apply"]').trigger('click');
        await flushPromises();

        const sd = useScheduleDraftStore();
        expect(sd.draftApplyResult?.applied).toBe(true);
    });
});

describe('RescheduleView', () => {
    it('proposes a reschedule and shows BEFORE/AFTER/REASON', async () => {
        vi.mocked(scheduleDraftApi.propose).mockResolvedValue({
            proposal: {
                base_version: 5,
                new_version: 6,
                moves: [
                    {
                        task_id: '1',
                        title: 'Write',
                        from: { start: '2026-08-17T09:00:00', end: '2026-08-17T10:00:00' },
                        to: { start: '2026-08-18T09:00:00', end: '2026-08-18T10:00:00' },
                    },
                ],
                conflict_task_ids: [],
            },
            has_changes: true,
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(RescheduleView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="reschedule-propose"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="reschedule-move"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="move-before"]').text()).toContain('Aug 17');
        expect(wrapper.find('[data-testid="move-after"]').text()).toContain('Aug 18');
        expect(wrapper.find('[data-testid="move-reason"]').text()).toContain('REASON');
    });

    it('shows conflicts when tasks could not be placed', async () => {
        vi.mocked(scheduleDraftApi.propose).mockResolvedValue({
            proposal: {
                base_version: 5,
                new_version: 6,
                moves: [],
                conflict_task_ids: ['9'],
            },
            has_changes: false,
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(RescheduleView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="reschedule-propose"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="reschedule-conflicts"]').exists()).toBe(true);
    });
});

describe('KRS import', () => {
    it('renders the upload and confirms a parsed preview', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ScheduleDraftView, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="krs-import"]').exists()).toBe(true);

        vi.mocked(importApi.uploadKrs).mockResolvedValue({
            import: {
                id: 7,
                filename: 'krs.pdf',
                status: 'pending',
                confidence: 0.5,
                rows: [
                    { day: 'senin', start_time: '07:30', end_time: '09:00', course: 'Matematika Ruang A', location: null },
                ],
                errors: [{ line: 'SENIN Matematika tanpa jam', error: 'Could not be read as a schedule row (expected a day, HH:MM–HH:MM time range, and course name).' }],
                warnings: [{ course: 'Matematika Ruang A', warning: 'Duplicate entry skipped — an identical row was already staged.' }],
                created_at: '2026-08-20T00:00:00+00:00',
            },
        });

        const input = wrapper.find('[data-testid="krs-import-file"]');
        Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'krs.pdf')], configurable: true });
        await input.trigger('change');
        await flushPromises();

        expect(wrapper.find('[data-testid="krs-import-preview"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="krs-import-row"]')).toHaveLength(1);
        expect(wrapper.get('[data-testid="krs-import-row"]').text()).toContain('Matematika Ruang A');
        expect(wrapper.get('[data-testid="krs-import-error-item"]').text()).toContain('Could not be read');
        expect(wrapper.get('[data-testid="krs-import-warning-item"]').text()).toContain('Duplicate entry skipped');

        vi.mocked(importApi.confirm).mockResolvedValue({
            import: { id: 7, filename: 'krs.pdf', status: 'confirmed', confidence: 0.5, rows: [], errors: [], warnings: [], created_at: '2026-08-20T00:00:00+00:00' },
        });
        await wrapper.find('[data-testid="krs-import-confirm"]').trigger('click');
        await flushPromises();

        expect(importApi.confirm).toHaveBeenCalledWith(7);
        expect(wrapper.get('[data-testid="krs-import-result"]').text()).toContain('confirmed');
    });
});

describe('ICS import', () => {
    it('renders the upload and previews parsed events with conflicts', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ScheduleDraftView, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="ics-import"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="ics-import-fallback"]').text()).toContain('manually as Hard Landscape');

        vi.mocked(importApi.uploadIcs).mockResolvedValue({
            import: {
                id: 21,
                filename: 'holidays.ics',
                status: 'pending',
                confidence: 0.5,
                rows: [
                    {
                        uid: 'h1',
                        summary: 'National Holiday',
                        location: null,
                        start_at: '2026-08-19T00:00:00+07:00',
                        end_at: '2026-08-19T23:59:00+07:00',
                        type: 'one_time',
                        recurrence: null,
                        tzid: 'Asia/Jakarta',
                        conflict: false,
                        conflict_with: null,
                    },
                    {
                        uid: 'h2',
                        summary: 'Team Standup',
                        location: 'Room 1',
                        start_at: '2026-08-19T09:00:00+07:00',
                        end_at: '2026-08-19T09:30:00+07:00',
                        type: 'recurring',
                        recurrence: 'FREQ=WEEKLY',
                        tzid: 'Asia/Jakarta',
                        conflict: true,
                        conflict_with: 'Existing Class',
                    },
                ],
                errors: [{ index: 2, summary: 'Broken', error: 'Malformed date-time value.' }],
                warnings: [{ index: 3, summary: 'Holiday', warning: 'All-day events are not imported.' }],
                created_at: '2026-08-20T00:00:00+00:00',
            },
        });

        const input = wrapper.find('[data-testid="ics-import-file"]');
        Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'holidays.ics')], configurable: true });
        await input.trigger('change');
        await flushPromises();

        expect(wrapper.find('[data-testid="ics-import-preview"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="ics-import-row"]')).toHaveLength(2);
        expect(wrapper.get('[data-testid="ics-import-row"]').text()).toContain('National Holiday');
        expect(wrapper.findAll('[data-testid="ics-import-row-conflict"]')[1].text()).toContain('Conflicts with');
        expect(wrapper.get('[data-testid="ics-import-error-item"]').text()).toContain('Malformed date-time');
        expect(wrapper.get('[data-testid="ics-import-warning-item"]').text()).toContain('All-day events are not imported.');

        vi.mocked(importApi.confirmIcs).mockResolvedValue({
            import: { id: 21, filename: 'holidays.ics', status: 'confirmed', confidence: 0.5, rows: [], errors: [], warnings: [], created_at: '2026-08-20T00:00:00+00:00' },
        });
        await wrapper.find('[data-testid="ics-import-confirm"]').trigger('click');
        await flushPromises();

        expect(importApi.confirmIcs).toHaveBeenCalledWith(21);
        expect(wrapper.get('[data-testid="ics-import-result"]').text()).toContain('confirmed');
    });
});

describe('ICS export', () => {
    it('renders the export panel and triggers a download', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ScheduleDraftView, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="ics-export"]').exists()).toBe(true);

        await wrapper.find('[data-testid="ics-export-download"]').trigger('click');
        await flushPromises();

        expect(downloadScheduleIcs).toHaveBeenCalledTimes(1);
        expect(wrapper.get('[data-testid="ics-export-success"]').text()).toContain('exported');
    });

    it('shows an error when the export fails', async () => {
        vi.mocked(downloadScheduleIcs).mockRejectedValue(new Error('Calendar export failed.'));
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ScheduleDraftView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="ics-export-download"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="ics-export-error"]').text()).toContain('Calendar export failed.');
    });
});
