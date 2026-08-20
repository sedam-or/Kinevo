import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        rechargeApi: {
            status: vi.fn(),
            start: vi.fn(),
            pause: vi.fn(),
            resume: vi.fn(),
            complete: vi.fn(),
            abandon: vi.fn(),
        },
    };
});

import RechargeTimer from '../RechargeTimer.vue';
import { rechargeApi } from '../api';
import type { RechargeSession, RechargeStatusResponse } from '../types';

const running: RechargeSession = {
    id: 1,
    user_id: 1,
    status: 'running',
    started_at: '2026-08-20T09:00:00',
    last_resumed_at: '2026-08-20T09:00:00',
    accumulated_seconds: 0,
    elapsed_seconds: 0,
    duration_minutes: null,
    ended_at: null,
};

function status(overrides: Partial<RechargeStatusResponse> = {}): RechargeStatusResponse {
    return {
        recharge: null,
        cue_available: false,
        completed_focus_today: 0,
        due_recharges: 0,
        completed_recharges_today: 0,
        recharge_minutes_today: 0,
        productive_minutes_today: 0,
        work_ratio: 0,
        recharge_ratio: 0,
        ...overrides,
    };
}

function mountWith(statusResponse: RechargeStatusResponse): ReturnType<typeof mount> {
    vi.mocked(rechargeApi.status).mockResolvedValue(statusResponse);
    const pinia = createPinia();
    setActivePinia(pinia);

    return mount(RechargeTimer, {
        props: { date: '2026-08-20' },
        global: { plugins: [pinia] },
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    vi.useRealTimers();
});

describe('RechargeTimer', () => {
    it('is hidden when no recharge is due and nothing was recorded', async () => {
        const wrapper = mountWith(status());
        await flushPromises();

        expect(wrapper.find('[data-testid="recharge-timer"]').exists()).toBe(false);
    });

    it('shows the Start CTA when the second focus session enables it', async () => {
        const wrapper = mountWith(status({ cue_available: true, completed_focus_today: 2, due_recharges: 1 }));
        await flushPromises();

        expect(wrapper.find('[data-testid="recharge-timer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="recharge-cue"]').text()).toBe('Time to recharge');
        expect(wrapper.find('[data-testid="recharge-start"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="recharge-complete"]').exists()).toBe(false);
    });

    it('starts a recharge and shows running controls', async () => {
        const wrapper = mountWith(status({ cue_available: true }));
        await flushPromises();

        vi.mocked(rechargeApi.start).mockResolvedValue({ recharge: running });
        vi.mocked(rechargeApi.status).mockResolvedValue(status({ recharge: running, cue_available: true }));

        await wrapper.find('[data-testid="recharge-start"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="recharge-status"]').text()).toBe('Running');
        expect(wrapper.find('[data-testid="recharge-pause"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="recharge-complete"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="recharge-start"]').exists()).toBe(false);
    });

    it('pauses and then resumes', async () => {
        const wrapper = mountWith(status({ recharge: running }));
        await flushPromises();

        vi.mocked(rechargeApi.pause).mockResolvedValue({
            recharge: { ...running, status: 'paused', last_resumed_at: null, accumulated_seconds: 60 },
        });

        await wrapper.find('[data-testid="recharge-pause"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="recharge-status"]').text()).toBe('Paused');
        expect(wrapper.find('[data-testid="recharge-resume"]').exists()).toBe(true);

        vi.mocked(rechargeApi.resume).mockResolvedValue({ recharge: running });
        await wrapper.find('[data-testid="recharge-resume"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="recharge-status"]').text()).toBe('Running');
    });

    it('emits completed and shows the ratio after completing', async () => {
        const wrapper = mountWith(status({ recharge: running }));
        await flushPromises();

        vi.mocked(rechargeApi.complete).mockResolvedValue({
            recharge: { ...running, status: 'completed', accumulated_seconds: 900, duration_minutes: 15, ended_at: '2026-08-20T09:15:00' },
        });
        vi.mocked(rechargeApi.status).mockResolvedValue(status({
            recharge: { ...running, status: 'completed', accumulated_seconds: 900, duration_minutes: 15, ended_at: '2026-08-20T09:15:00' },
            completed_recharges_today: 1,
            recharge_minutes_today: 15,
            productive_minutes_today: 50,
            work_ratio: 0.7692,
            recharge_ratio: 0.2308,
        }));

        await wrapper.find('[data-testid="recharge-complete"]').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('completed')).toBeTruthy();
        expect(wrapper.find('[data-testid="recharge-ratio"]').text()).toContain('Work 77%');
    });

    it('shows an error when an action fails', async () => {
        const wrapper = mountWith(status({ cue_available: true }));
        await flushPromises();

        vi.mocked(rechargeApi.start).mockRejectedValue({ code: 'CONFLICT', status: 409, message: 'A recharge timer is already running.', retryable: false });

        await wrapper.find('[data-testid="recharge-start"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="recharge-error"]').text()).toContain('already running');
    });
});