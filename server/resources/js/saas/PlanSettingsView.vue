<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiClient } from '../api/client';

/**
 * TASK-P23-008 — Settings → Plan: current plan, entitlements, metered usage,
 * and self-serve switching (manual provider until P24 billing lands).
 */
interface PlanOverview {
    plan: { code: string; name: string; entitlements: Record<string, number | boolean> };
    subscription: { state: string; provider: string };
    usage: Record<string, { allowance: number; used: number; remaining: number; period: string }>;
}

const overview = ref<PlanOverview | null>(null);
const loading = ref(false);
const switching = ref<string | null>(null);
const error = ref<string | null>(null);
const checkoutUrl = ref<string | null>(null);
const lastPlan = ref<string | null>(null);

/** TASK-P24-010 — backend creates the provider subscription; browser follows redirect only. */
async function subscribe(code: string): Promise<void> {
    switching.value = code;
    error.value = null;
    try {
        const res = await apiClient.request<{ redirect_url: string | null; payment_type: string | null }>('/billing/checkout', {
            method: 'POST',
            body: JSON.stringify({ plan_code: code }),
        });
        lastPlan.value = code;
        if (res.redirect_url) {
            window.location.href = res.redirect_url;

            return;
        }
        // No hosted redirect (e.g. GoPay app flow): show pending state.
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        switching.value = null;
    }
}

const PLANS = [
    { code: 'free', name: 'Free' },
    { code: 'pro', name: 'Pro' },
    { code: 'power', name: 'Power' },
];

async function load(): Promise<void> {
    loading.value = true;
    try {
        overview.value = await apiClient.request<PlanOverview>('/saas/plan');
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function switchTo(code: string): Promise<void> {
    switching.value = code;
    error.value = null;
    try {
        overview.value = await apiClient.request<PlanOverview>('/saas/plan', {
            method: 'PATCH',
            body: JSON.stringify({ plan_code: code }),
        });
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        switching.value = null;
    }
}

const PLAN_SUMMARY: Record<string, string> = {
    free: '2 workspaces · 20 AI credits/mo',
    pro: '10 workspaces · 300 AI credits/mo · BYOK · export',
    power: '25 workspaces · 1000 AI credits/mo · Wrapped',
};
function summaryFor(code: string): string {
    return PLAN_SUMMARY[code] ?? '';
}

function currentEntitlements(): string {
    const e = overview.value?.plan.entitlements;
    if (!e) return '';

    return `${e.max_workspaces} workspaces · ${e.ai_credits} AI credits/mo${e.export ? ' · export' : ''}`;
}

onMounted(load);
</script>

<template>
    <div class="max-w-2xl flex flex-col gap-4" data-testid="plan-settings-view">
        <div v-if="loading" class="text-sm text-text-muted">Loading…</div>
        <template v-else-if="overview">
            <header>
                <h1 class="text-xl font-semibold" data-testid="plan-current-name">{{ overview.plan.name }}</h1>
                <p class="text-sm text-text-muted">
                    State: {{ overview.subscription.state }} · provider: {{ overview.subscription.provider }}
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

            <div v-if="error" class="text-sm text-danger" role="alert" data-testid="plan-error">{{ error }}</div>

            <!-- Plan switcher -->
            <section class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-testid="plan-grid">
                <div v-for="p in PLANS" :key="p.code" class="rounded-sm border p-4 flex flex-col gap-2"
                    :class="p.code === overview.plan.code ? 'border-primary bg-surface' : 'border-border/30'"
                    :data-testid="`plan-card-${p.code}`">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ p.name }}</span>
                        <span v-if="p.code === overview.plan.code" class="text-xs uppercase text-text-muted">current</span>
                    </div>
                    <ul class="text-xs text-text-muted list-disc ml-4">
                        <li>{{ p.code === overview.plan.code ? currentEntitlements() : summaryFor(p.code) }}</li>
                    </ul>
                    <div class="flex flex-col gap-1">
                        <button type="button" class="underline self-start min-h-[44px] disabled:opacity-50"
                            :disabled="p.code === overview.plan.code || switching !== null"
                            :data-testid="`plan-switch-${p.code}`" @click="switchTo(p.code)">
                            {{ switching === p.code ? 'Switching…' : 'Switch to this plan' }}
                        </button>
                        <!-- TASK-P24-027 — paid plans go through Midtrans checkout. -->
                        <button v-if="p.code !== overview.plan.code && p.code !== 'free'" type="button"
                            class="underline self-start min-h-[44px] text-primary disabled:opacity-50"
                            :disabled="switching !== null" :data-testid="`plan-subscribe-${p.code}`" @click="subscribe(p.code)">
                            {{ switching === p.code ? 'Preparing…' : 'Subscribe (Midtrans)' }}
                        </button>
                        <a v-if="checkoutUrl && lastPlan === p.code" :href="checkoutUrl" target="_blank" rel="noopener"
                            class="underline self-start min-h-[44px]" data-testid="plan-checkout-link">
                            Complete payment →
                        </a>
                        <p v-if="error && lastPlan === p.code" class="text-xs text-danger" role="alert">{{ error }}</p>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>

