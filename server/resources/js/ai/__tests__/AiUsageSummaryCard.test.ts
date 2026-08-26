import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { aiApi, type AiUsageSummary } from '../api';
import AiUsageSummaryCard from '../AiUsageSummaryCard.vue';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        aiApi: {
            usage: vi.fn(),
            runs: vi.fn(),
            readAlerts: vi.fn(),
        },
    };
});

function summary(partial: Partial<AiUsageSummary> = {}): AiUsageSummary {
    return {
        period: '2026-08',
        period_start: '2026-08-01T00:00:00.000Z',
        period_end: '2026-08-31T23:59:59.000Z',
        plan: { code: 'free', name: 'Free' },
        credits: { used: 3, limit: 20, remaining: 17, percent: 15 },
        kinevo: { request_count: 3, estimated_cost_minor: 250, currency: 'USD' },
        byok: { request_count: 1 },
        breakdown: [
            { type: 'goal_breakdown', count: 2, kinevo_cost_minor: 150 },
            { type: 'summary', count: 1, kinevo_cost_minor: 100 },
        ],
        alerts: { unread_count: 0, items: [] },
        ...partial,
    };
}

async function mountCard(): Promise<ReturnType<typeof mount>> {
    const wrapper = mount(AiUsageSummaryCard);
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    vi.mocked(aiApi.usage).mockReset();
    vi.mocked(aiApi.runs).mockReset();
    vi.mocked(aiApi.readAlerts).mockReset();
});

describe('AiUsageSummaryCard', () => {
    it('renders the summary-first blocks and formats money in minor units', async () => {
        vi.mocked(aiApi.usage).mockResolvedValue(summary());
        vi.mocked(aiApi.runs).mockResolvedValue({
            runs: [
                {
                    id: 7,
                    request_id: 'req-1',
                    provider: 'openai',
                    model: 'gpt-4o-mini',
                    proposal_type: 'goal_breakdown',
                    input_tokens: 100,
                    output_tokens: 50,
                    credits_consumed: 1,
                    estimated_cost_minor: 150,
                    cost_currency: 'USD',
                    billing_ledger: 'kinevo',
                    status: 'success',
                    latency_ms: 40,
                    created_at: '2026-08-26T10:00:00.000Z',
                },
            ],
        });

        const wrapper = await mountCard();

        expect(wrapper.find('[data-testid="ai-usage-credits-value"]').text()).toBe('3 / 20');
        expect(wrapper.find('[data-testid="ai-usage-kinevo-value"]').text()).toBe('$2.50');
        expect(wrapper.find('[data-testid="ai-usage-byok-value"]').text()).toBe('1');
        expect(wrapper.find('[data-testid="ai-usage-breakdown-goal_breakdown"]').text()).toContain('2 calls');
        expect(wrapper.find('[data-testid="ai-usage-breakdown-goal_breakdown"]').text()).toContain('$1.50');
        expect(wrapper.find('[data-testid="ai-usage-run-7"]').text()).toContain('Goal breakdown');
        expect(wrapper.find('[data-testid="ai-usage-run-7"]').text()).toContain('kinevo');
    });

    it('shows empty states and no credits cost when nothing ran yet', async () => {
        vi.mocked(aiApi.usage).mockResolvedValue(summary({
            credits: { used: 0, limit: 20, remaining: 20, percent: 0 },
            kinevo: { request_count: 0, estimated_cost_minor: 0, currency: 'USD' },
            byok: { request_count: 0 },
            breakdown: [],
        }));
        vi.mocked(aiApi.runs).mockResolvedValue({ runs: [] });

        const wrapper = await mountCard();

        expect(wrapper.find('[data-testid="ai-usage-breakdown-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="ai-usage-runs-empty"]').exists()).toBe(true);
    });

    it('renders the unread alert banner and dismisses through the read endpoint', async () => {
        vi.mocked(aiApi.usage)
            .mockResolvedValueOnce(summary({
                alerts: {
                    unread_count: 1,
                    items: [{ id: 1, kind: 'user.usage_threshold', threshold: 50, context: { percent: 50 }, seen_at: null, created_at: '2026-08-26T10:00:00.000Z' }],
                },
            }))
            .mockResolvedValue(summary());
        vi.mocked(aiApi.runs).mockResolvedValue({ runs: [] });
        vi.mocked(aiApi.readAlerts).mockResolvedValue({ marked_read: 1 });

        const wrapper = await mountCard();

        const banner = wrapper.find('[data-testid="ai-alert-banner"]');
        expect(banner.exists()).toBe(true);
        expect(wrapper.find('[data-testid="ai-alert-text"]').text()).toContain('50%');

        await wrapper.find('[data-testid="ai-alert-dismiss"]').trigger('click');
        await flushPromises();

        expect(aiApi.readAlerts).toHaveBeenCalledTimes(1);
        expect(wrapper.find('[data-testid="ai-alert-banner"]').exists()).toBe(false);
    });
});