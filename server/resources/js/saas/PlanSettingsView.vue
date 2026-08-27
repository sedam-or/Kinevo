<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { apiClient } from '../api/client';
import KButton from '../components/KButton.vue';

/**
 * TASK-P23-008 + COMMERCIAL PRICING DELTA — Settings → Plan.
 * Prices and entitlement bullets come from the backend snapshot
 * (`pricing` + `catalog`), never hardcoded in the frontend. Tier
 * positioning copy per revisi-finance.md §1/§14 (launch price hypotheses).
 */
interface PlanOverview {
    plan: { code: string; name: string; entitlements: Record<string, number | boolean> };
    catalog: Record<string, { name: string; entitlements: Record<string, number | boolean> }>;
    subscription: { state: string; provider: string };
    usage: Record<string, { allowance: number; used: number; remaining: number; period: string }>;
    pricing: Record<string, { currency: string; amount_minor: number; interval: string; interval_count: number; launch_hypothesis: boolean }>;
}

const PLAN_ORDER = ['free', 'pro', 'power'] as const;

/** Candy-position line per tier (revisi-finance.md §1). */
const POSITION: Record<string, string> = {
    free: 'Experience the system.',
    pro: 'For serious personal use.',
    power: 'For intensive personal use.',
};

/** Power differentiation must read as capacity/depth/intelligence (§14), not cosmetics. */
const POWER_VALUE = [
    'Largest included AI allowance',
    'Deepest history for analysis, insights & Review',
    'Yearly Wrapped with advanced share',
    'Higher workspace capacity',
    'Advanced analytics & insights',
];

const overview = ref<PlanOverview | null>(null);
const loading = ref(false);
const switching = ref<string | null>(null);
const error = ref<string | null>(null);
const checkoutUrl = ref<string | null>(null);
const lastPlan = ref<string | null>(null);

function priceLabel(code: string): string {
    const p = overview.value?.pricing[code];
    if (!p) return '—';
    if (p.amount_minor === 0) return 'Rp0';
    try {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: p.currency, maximumFractionDigits: 0 }).format(p.amount_minor / 100);
    } catch {
        return `Rp${(p.amount_minor / 100).toLocaleString('id-ID')}`;
    }
}

/** Rp40.000-style gap between Pro and Power, computed — never hardcoded. */
const powerDifference = computed(() => {
    const p = overview.value?.pricing;
    if (!p?.power || !p?.pro) return '';
    const gap = (p.power.amount_minor - p.pro.amount_minor) / 100;
    return `Rp${gap.toLocaleString('id-ID')} lebih per bulan dari Pro`;
});

function bullets(code: string): string[] {
    const e = overview.value?.catalog[code]?.entitlements;
    if (!e) return [];
    const out = [`${e.max_workspaces} workspace${(e.max_workspaces as number) > 1 ? 's' : ''}`];
    out.push(`${e.ai_credits} AI credits/month (included allowance)`);
    if (e.custom_provider) out.push('BYOK — bring your own provider');
    if (e.export) out.push('Export (JSON/Markdown/CSV)');
    if (e.advanced_analytics) out.push('Advanced analytics');
    if (code === 'power' && e.wrapped) return POWER_VALUE;
    return out;
}

/** True when moving to a more expensive tier (upgrade ⇒ purchase). */
function isUpgrade(code: string): boolean {
    const current = overview.value?.pricing[overview.value.plan.code]?.amount_minor ?? 0;
    const target = overview.value?.pricing[code]?.amount_minor ?? 0;

    return target > current;
}

function switchTo(code: string): void {
    switching.value = code;
    error.value = null;
    apiClient
        .request<PlanOverview>('/saas/plan', { method: 'PATCH', body: JSON.stringify({ plan_code: code }) })
        .then((data) => {
            overview.value = data;
        })
        .catch((e) => {
            error.value = (e as Error).message;
        })
        .finally(() => {
            switching.value = null;
        });
}

/** TASK-P24-010 — backend creates the provider subscription; browser follows redirect only. */
function subscribe(code: string): void {
    switching.value = code;
    error.value = null;
    apiClient
        .request<{ redirect_url: string | null }>('/billing/checkout', { method: 'POST', body: JSON.stringify({ plan_code: code }) })
        .then((res) => {
            lastPlan.value = code;
            if (res.redirect_url) {
                window.location.href = res.redirect_url;

                return;
            }
            load();
        })
        .catch((e) => {
            error.value = (e as Error).message;
        })
        .finally(() => {
            switching.value = null;
        });
}

function load(): Promise<void> {
    loading.value = true;
    return apiClient
        .request<PlanOverview>('/saas/plan')
        .then((data) => {
            overview.value = data;
        })
        .catch((e) => {
            error.value = (e as Error).message;
        })
        .finally(() => {
            loading.value = false;
        });
}

onMounted(load);
</script>

<template>
    <div class="max-w-3xl flex flex-col gap-4" data-testid="plan-settings-view">
        <div v-if="loading" class="text-sm text-text-muted">Loading…</div>

        <template v-else-if="overview">
            <header data-testid="plan-current">
                <p class="text-xs uppercase tracking-wide text-text-muted">Current plan</p>
                <h1 class="text-xl font-semibold" data-testid="plan-current-name">{{ overview.plan.name }}</h1>
                <p class="text-sm text-text-muted">
                    {{ priceLabel(overview.plan.code) }} / month ·
                    State: {{ overview.subscription.state }}
                    <span v-if="overview.subscription.provider === 'manual'">(billing arrives in a later release)</span>
                </p>
            </header>

            <!-- Metered usage -->
            <section class="surface-secondary p-4 flex flex-col gap-2" data-testid="plan-usage">
                <h2 class="text-sm font-semibold uppercase tracking-wide">AI credits — {{ overview.usage.ai_credits.period }}</h2>
                <div class="h-2 rounded-sm bg-surface overflow-hidden" role="progressbar" :aria-valuenow="overview.usage.ai_credits.used" aria-valuemin="0" :aria-valuemax="overview.usage.ai_credits.allowance">
                    <div class="h-full bg-primary" :style="{ width: Math.min(100, Math.round(100 * overview.usage.ai_credits.used / Math.max(1, overview.usage.ai_credits.allowance))) + '%' }" />
                </div>
                <p class="text-xs text-text-muted">
                    {{ overview.usage.ai_credits.used }} / {{ overview.usage.ai_credits.allowance }} used this month
                </p>
            </section>

            <p v-if="error" class="text-sm text-danger" role="alert" data-testid="plan-error">{{ error }}</p>

            <!-- Tier comparison -->
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-3" data-testid="plan-grid">
                <div v-for="code in PLAN_ORDER" :key="code"
                    class="surface-secondary p-4 flex flex-col gap-2"
                    :class="code === overview.plan.code ? 'border-primary' : ''"
                    :data-testid="`plan-card-${code}`">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">{{ overview.catalog[code].name }}</h2>
                        <span v-if="code === overview.plan.code" class="rounded-sm px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide bg-primary text-primary-contrast" data-testid="plan-current-badge">current</span>
                    </div>
                    <p class="text-xs text-text-muted" :data-testid="`plan-position-${code}`">{{ POSITION[code] }}</p>
                    <p class="text-lg font-semibold" :data-testid="`plan-price-${code}`">{{ priceLabel(code) }}<span class="text-xs font-normal text-text-muted"> / month</span></p>

                    <ul class="text-xs text-text-muted list-disc ml-4 flex flex-col gap-1" :data-testid="`plan-bullets-${code}`">
                        <li v-for="(b, i) in bullets(code)" :key="i">{{ b }}</li>
                    </ul>

                    <!-- Power differentiation explains the Rp40.000 gap (§14). -->
                    <p v-if="code === 'power' && powerDifference" class="text-xs" data-testid="plan-power-gap">{{ powerDifference }}</p>

                    <div class="flex flex-col gap-1 mt-auto pt-2">
                        <template v-if="code !== overview.plan.code">
                            <KButton v-if="code !== 'free' && isUpgrade(code)" variant="primary" class="self-start min-h-[44px]"
                                :disabled="switching !== null" :data-testid="`plan-upgrade-${code}`" @click="subscribe(code)">
                                {{ switching === code ? 'Preparing…' : `Upgrade to ${overview.catalog[code].name}` }}
                            </KButton>
                            <KButton v-else variant="secondary" class="self-start min-h-[44px]"
                                :disabled="switching !== null"
                                :data-testid="`plan-switch-${code}`" @click="switchTo(code)">
                                {{ switching === code ? 'Switching…' : (code === 'free' ? 'Switch to Free' : `Switch to ${overview.catalog[code].name}`) }}
                            </KButton>
                        </template>
                        <p v-else class="text-xs text-text-muted" data-testid="plan-current-note">You are on this plan.</p>

                        <a v-if="checkoutUrl && lastPlan === code" :href="checkoutUrl" target="_blank" rel="noopener"
                            class="underline self-start min-h-[44px]" data-testid="plan-checkout-link">
                            Complete payment →
                        </a>
                        <p v-if="error && lastPlan === code" class="text-xs text-danger" role="alert">{{ error }}</p>
                    </div>
                </div>
            </section>

            <!-- Billing transparency + launch-hypothesis honesty (§24/§14). -->
            <aside class="text-xs text-text-muted flex flex-col gap-1" data-testid="plan-delta-note">
                <p>
                    Launch pricing is a hypothesis and is measured during beta — never a locked market price.
                    Subscription bills monthly, per month, and cancellation always stays one step away.
                </p>
            </aside>
        </template>
    </div>
</template>