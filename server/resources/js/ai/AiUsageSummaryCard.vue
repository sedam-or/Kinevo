<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { aiApi, type AiRunRecord, type AiUsageSummary } from './api';

/**
 * TASK-P25-009 — Settings → AI Usage, summary-first (owner brief): plan +
 * credits progress/reset, estimated Kinevo-hosted cost this month, BYOK usage,
 * per-feature breakdown, unread cost alerts, and recent runs. No charts by
 * design — the daily chart is explicitly deferred. Everything is read-only
 * except dismissing alerts.
 */
const summary = ref<AiUsageSummary | null>(null);
const runs = ref<AiRunRecord[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const dismissing = ref(false);

const FEATURE_LABELS: Record<string, string> = {
    goal_breakdown: 'Goal breakdown',
    milestone: 'Milestone planning',
    task_extraction: 'Task extraction',
    canvas: 'Canvas outline',
    summary: 'Note summary',
    text_generation: 'Text generation',
};

function featureLabel(type: string): string {
    return FEATURE_LABELS[type] ?? type.replaceAll('_', ' ');
}

function money(minor: number | null, currency: string): string {
    if (minor === null || minor === undefined) return '—';
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(2)} ${currency}`;
    }
}

const creditsPercent = computed(() => {
    const c = summary.value?.credits;
    return c ? Math.min(100, Math.round(c.percent)) : 0;
});

const periodLabel = computed(() => {
    const s = summary.value;
    if (!s) return '';
    const start = new Date(s.period_start);
    return `${start.toLocaleDateString('en-US', { month: 'long' })} ${start.getFullYear()}`;
});

const alertText = computed(() => {
    const s = summary.value;
    const item = s?.alerts.items[0];
    if (!item) return '';
    if (item.kind === 'user.usage_threshold') {
        const pct = Math.round(Number(item.context.percent ?? 0));
        if (item.threshold === 100 || pct >= 100) {
            return `You have used all ${s.credits.limit} included AI credits this period. Upgrade for a larger allowance, or connect BYOK.`;
        }
        return `You have used ${pct}% of your included AI credits this period.`;
    }
    return 'AI budget warning.';
});

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const [u, r] = await Promise.all([aiApi.usage(), aiApi.runs(10)]);
        summary.value = u;
        runs.value = r.runs;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function dismissAlerts(): Promise<void> {
    dismissing.value = true;
    error.value = null;
    try {
        await aiApi.readAlerts();
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        dismissing.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="flex flex-col gap-4" data-testid="ai-usage-card">
        <div v-if="loading" class="text-sm text-text-muted" data-testid="ai-usage-loading">Loading usage…</div>

        <template v-else-if="summary">
            <h2 class="text-sm font-semibold text-text">AI usage — {{ periodLabel }}</h2>

            <!-- Alerts banner (TASK-P25-010) -->
            <div v-if="summary.alerts.unread_count > 0" class="flex items-center justify-between gap-3 rounded-sm border border-warning/40 bg-warning/5 px-3 py-2" role="alert" data-testid="ai-alert-banner">
                <span class="text-sm text-warning" data-testid="ai-alert-text">{{ alertText }}</span>
                <button type="button" class="text-xs underline whitespace-nowrap min-h-[44px] disabled:opacity-50" :disabled="dismissing" data-testid="ai-alert-dismiss" @click="dismissAlerts">
                    {{ dismissing ? 'Dismissing…' : 'Dismiss' }}
                </button>
            </div>

            <p v-if="error" class="text-sm text-danger" role="alert" data-testid="ai-usage-error">{{ error }}</p>

            <!-- Summary block -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rounded-sm border border-border p-4 flex flex-col gap-2" data-testid="ai-usage-credits">
                    <span class="text-xs text-text-muted">AI credits</span>
                    <span class="text-lg font-semibold" data-testid="ai-usage-credits-value">{{ summary.credits.used }} / {{ summary.credits.limit }}</span>
                    <div class="h-2 rounded-sm bg-surface overflow-hidden" role="progressbar" :aria-valuenow="summary.credits.used" aria-valuemin="0" :aria-valuemax="summary.credits.limit">
                        <div class="h-full bg-[var(--color-primary)]" :style="{ width: creditsPercent + '%' }" />
                    </div>
                    <span class="text-xs text-text-muted">{{ summary.credits.remaining }} left this month</span>
                </div>

                <div class="rounded-sm border border-border p-4 flex flex-col gap-2" data-testid="ai-usage-kinevo">
                    <span class="text-xs text-text-muted">Kinevo AI cost (est.)</span>
                    <span class="text-lg font-semibold" data-testid="ai-usage-kinevo-value">{{ money(summary.kinevo.estimated_cost_minor, summary.kinevo.currency) }}</span>
                    <span class="text-xs text-text-muted">{{ summary.kinevo.request_count }} hosted {{
                        summary.kinevo.request_count === 1 ? 'request' : 'requests' }}</span>
                </div>

                <div class="rounded-sm border border-border p-4 flex flex-col gap-2" data-testid="ai-usage-byok">
                    <span class="text-xs text-text-muted">BYOK usage</span>
                    <span class="text-lg font-semibold" data-testid="ai-usage-byok-value">{{ summary.byok.request_count }}</span>
                    <span class="text-xs text-text-muted">{{ summary.byok.request_count === 1 ? 'request' : 'requests' }} via your own key</span>
                </div>
            </div>

            <!-- This-month breakdown by feature -->
            <div class="flex flex-col gap-2" data-testid="ai-usage-breakdown">
                <h3 class="text-sm font-semibold text-text">This month by feature</h3>
                <ul v-if="summary.breakdown.length > 0" class="flex flex-col gap-1">
                    <li v-for="row in summary.breakdown" :key="row.type" class="flex items-center justify-between text-sm" :data-testid="`ai-usage-breakdown-${row.type}`">
                        <span class="text-text">{{ featureLabel(row.type) }}</span>
                        <span class="text-xs text-text-muted">
                            {{ row.count }} {{ row.count === 1 ? 'call' : 'calls' }}<template v-if="row.kinevo_cost_minor > 0"> · {{ money(row.kinevo_cost_minor, summary.kinevo.currency) }}</template>
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-text-muted" data-testid="ai-usage-breakdown-empty">
                    No hosted AI calls this month yet — hosted AI uses your included
                    monthly AI credit allowance.
                </p>
            </div>

            <!-- Recent runs (View AI runs) -->
            <div class="flex flex-col gap-2" data-testid="ai-usage-runs">
                <h3 class="text-sm font-semibold text-text">Recent AI runs</h3>
                <ul v-if="runs.length > 0" class="flex flex-col gap-1">
                    <li v-for="run in runs" :key="run.id" class="flex items-center justify-between gap-3 text-sm" :data-testid="`ai-usage-run-${run.id}`">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="truncate">{{ featureLabel(run.proposal_type) }}</span>
                            <span class="rounded-sm px-1.5 py-0.5 text-[10px] font-medium"
                                :class="run.billing_ledger === 'byok' ? 'border border-info/40 bg-info-tint text-info' : 'border border-border/30 bg-surface text-text-muted'">
                                {{ run.billing_ledger }}
                            </span>
                        </span>
                        <span class="text-xs text-text-muted whitespace-nowrap">
                            {{ new Date(run.created_at).toLocaleDateString() }}
                            <template v-if="run.credits_consumed > 0"> · {{ run.credits_consumed }} credit</template>
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-text-muted" data-testid="ai-usage-runs-empty">No AI runs yet.</p>
            </div>
        </template>
    </section>
</template>