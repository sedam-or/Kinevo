import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', () => ({
    analyticsApi: {
        workLife: vi.fn(),
    },
}));

import AnalyticsView from '../AnalyticsView.vue';
import { analyticsApi } from '../api';
import type { WorkLifeAnalyticsResponse } from '../types';

const mockedWorkLife = vi.mocked(analyticsApi.workLife);

function result(overrides: Partial<WorkLifeAnalyticsResponse> = {}): WorkLifeAnalyticsResponse {
    return {
        from: '2026-08-17',
        to: '2026-08-20',
        productive_minutes: 75,
        recharge_minutes: 15,
        total_minutes: 90,
        work_ratio: 75 / 90,
        recharge_ratio: 15 / 90,
        band: 'balanced',
        days: [
            {
                date: '2026-08-18',
                productive_minutes: 50,
                recharge_minutes: 0,
                work_ratio: 1,
                recharge_ratio: 0,
                band: 'work_leaning',
            },
            {
                date: '2026-08-19',
                productive_minutes: 25,
                recharge_minutes: 15,
                work_ratio: 25 / 40,
                recharge_ratio: 15 / 40,
                band: 'balanced',
            },
        ],
        disclaimer: 'Time-balance indicator. Not a health diagnosis.',
        ...overrides,
    };
}

describe('AnalyticsView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-20T12:00:00'));
        mockedWorkLife.mockReset();
        mockedWorkLife.mockResolvedValue(result());
    });

    it('loads the default 7-day range on mount', async () => {
        mount(AnalyticsView);
        await flushPromises();

        expect(mockedWorkLife).toHaveBeenCalledWith('2026-08-14', '2026-08-20');
    });

    it('renders the ratio summary and disclaimer', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        expect(wrapper.get('[data-testid="analytics-ratio"]').text()).toBe('Work 83% · Recharge 17%');
        expect(wrapper.get('[data-testid="analytics-band"]').text()).toBe('Balanced');
        expect(wrapper.get('[data-testid="analytics-disclaimer"]').text()).toBe(
            'Time-balance indicator. Not a health diagnosis.',
        );
        expect(wrapper.findAll('[data-testid="analytics-day"]')).toHaveLength(2);
    });

    it('switches period presets', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const buttons = wrapper.findAll('[data-testid="analytics-preset"]');
        expect(buttons).toHaveLength(4);

        await buttons[3].trigger('click');
        await flushPromises();

        expect(mockedWorkLife).toHaveBeenLastCalledWith('2026-08-01', '2026-08-20');
    });

    it('shows an empty state without tracked time', async () => {
        mockedWorkLife.mockResolvedValue(result({
            productive_minutes: 0,
            recharge_minutes: 0,
            total_minutes: 0,
            work_ratio: 0,
            recharge_ratio: 0,
            band: 'no_data',
            days: [],
        }));

        const wrapper = mount(AnalyticsView);
        await flushPromises();

        expect(wrapper.find('[data-testid="analytics-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="analytics-summary"]').exists()).toBe(false);
    });

    it('renders the error state on failure', async () => {
        mockedWorkLife.mockRejectedValue({ message: 'Server error' });

        const wrapper = mount(AnalyticsView);
        await flushPromises();

        expect(wrapper.get('[data-testid="analytics-error"]').text()).toBe('Server error');
    });
});