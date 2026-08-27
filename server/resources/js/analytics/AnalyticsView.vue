<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useAnalyticsStore } from './store';
import { executiveSignal, executionIsLow, interpretCapacity, interpretDays, interpretExecution, interpretGoals, interpretHeatmap, interpretPillars, interpretWorkLife, type Interpretation } from './interpretation';
import type { DeadlineHealth, PillarKey } from './types';
import type { ApiError } from '../api/types';
import FeatureHelp from '../components/FeatureHelp.vue';
import InterpretationStrip from '../components/InterpretationStrip.vue';
import ChartMeta from '../components/ChartMeta.vue';
import { useShellStore } from '../shell/store';

const analytics = useAnalyticsStore();
const shell = useShellStore();

type RangePreset = '7d' | '30d' | 'week' | 'month';

const presets: { key: RangePreset; label: string }[] = [
    { key: '7d', label: '7 days' },
    { key: '30d', label: '30 days' },
    { key: 'week', label: 'This week' },
    { key: 'month', label: 'This month' },
];

const activePreset = ref<RangePreset>('7d');

const heatmapRanges: { key: '3m' | '6m' | 'year'; label: string }[] = [
    { key: '3m', label: '3 months' },
    { key: '6m', label: '6 months' },
    { key: 'year', label: 'This year' },
];

const activeHeatmapRange = ref<'3m' | '6m' | 'year'>('6m');
const heatmapPillarFilter = ref<PillarKey | ''>('');

const pillarOptions: { key: PillarKey; label: string }[] = [
    { key: 'karier', label: 'Karier' },
    { key: 'kesehatan', label: 'Kesehatan' },
    { key: 'bahasa', label: 'Bahasa' },
    { key: 'branding', label: 'Branding' },
    { key: 'uncategorized', label: 'Uncategorized' },
];

// Single-accent intensity ramp: empty cells ride on the border token at low
// opacity; activity steps climb the primary token via opacity utilities so
// light and dark themes stay coherent without per-theme variants.
const heatmapColor = computed(() => [
    'bg-border/20',
    'bg-primary/15',
    'bg-primary/35',
    'bg-primary/60',
    'bg-primary',
]);

const showHeatmapList = ref(false);

function formatDate(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function rangeFor(preset: RangePreset): { from: string; to: string } {
    const to = new Date();
    const from = new Date();
    if (preset === '7d') {
        from.setDate(to.getDate() - 6);
    } else if (preset === '30d') {
        from.setDate(to.getDate() - 29);
    } else if (preset === 'week') {
        const day = to.getDay(); // 0 = Sunday
        from.setDate(to.getDate() - day);
    } else {
        from.setDate(1);
    }
    return { from: formatDate(from), to: formatDate(to) };
}

async function load(preset: RangePreset): Promise<void> {
    activePreset.value = preset;
    const { from, to } = rangeFor(preset);
    await analytics.load(from, to);
}

const workPercent = computed(() => Math.round(analytics.workRatio * 100));
const rechargePercent = computed(() => Math.round(analytics.rechargeRatio * 100));

const bandLabel = computed(() => {
    switch (analytics.band) {
        case 'work_leaning':
            return 'Mostly work';
        case 'recharge_leaning':
            return 'Mostly recharge';
        case 'balanced':
            return 'Balanced';
        default:
            return 'No data yet';
    }
});

const goalHealthLabel = computed(() => {
    const h = analytics.goalDeadlineHealth;
    const labelled: [DeadlineHealth, string][] = [
        ['on_track', 'On track'],
        ['at_risk', 'At risk'],
        ['overdue', 'Overdue'],
        ['completed', 'Completed'],
    ];
    return labelled
        .filter(([key]) => h[key] > 0)
        .map(([key, label]) => `${label} ${h[key]}`)
        .join(' · ');
});

// Bounded render: the goal list summarizes — it never dumps every goal
// (unbounded rows broke mobile layout + full-page captures at scale).
const GOAL_LIST_LIMIT = 8;
const visibleGoals = computed(() => analytics.goals.slice(0, GOAL_LIST_LIMIT));
const hiddenGoalCount = computed(() => Math.max(0, analytics.goals.length - GOAL_LIST_LIMIT));

function deadlineLabel(health: DeadlineHealth, daysRemaining: number | null): string {
    switch (health) {
        case 'completed':
            return 'Completed';
        case 'overdue':
            return `Overdue ${Math.abs(daysRemaining ?? 0)}d`;
        case 'at_risk':
            return `At risk · ${daysRemaining ?? 0}d left`;
        case 'on_track':
            return `On track · ${daysRemaining ?? 0}d left`;
        default:
            return 'No deadline';
    }
}

const recommendationLabel = computed(() => {
    switch (analytics.capacityRecommendation) {
        case 'BOOST_AVAILABLE':
            return 'Boost / backlog available';
        case 'REDUCE_LOAD':
            return 'Reduce next load';
        default:
            return 'Maintain';
    }
});

const workLifeInterpretation = computed<Interpretation>(() => interpretWorkLife({
    workRatio: analytics.workRatio,
    previous: analytics.previous,
    band: analytics.band,
}));

const goalsInterpretation = computed<Interpretation>(() => interpretGoals({
    completionRate: analytics.goalCompletionRate,
    totalMilestones: analytics.goalTotalMilestones,
    completedMilestones: analytics.goalCompletedMilestones,
    overdue: analytics.goalDeadlineHealth.overdue,
    atRisk: analytics.goalDeadlineHealth.at_risk,
}));

const capacityInterpretation = computed<Interpretation>(() => interpretCapacity({
    realization: analytics.capacityRealization,
    confidence: analytics.capacityConfidence,
    recommendation: analytics.capacityRecommendation,
    reason: analytics.capacityReason,
    overloadedDays: analytics.capacityDays.filter((d) => d.status === 'overload').length,
}));

const pillarInterpretation = computed<Interpretation>(() => interpretPillars(
    analytics.pillars.map((p) => ({ label: p.label, percent: p.percent })),
));

const heatmapInterpretation = computed<Interpretation>(() => interpretHeatmap({
    totalProductiveMinutes: analytics.heatmapDays.reduce((sum, d) => sum + d.productive_minutes, 0),
    activeDays: analytics.heatmapDays.filter((d) => d.intensity > 0).length,
}));

const daysInterpretation = computed<Interpretation>(() => interpretDays({
    totalDays: analytics.days.length,
}));

const signal = computed(() => executiveSignal({
    overdue: analytics.goalDeadlineHealth.overdue,
    atRisk: analytics.goalDeadlineHealth.at_risk,
    overloadedDays: analytics.capacityDays.filter((d) => d.status === 'overload').length,
    band: analytics.band,
}));

const signalClass = computed(() => ({
    danger: 'border-danger/40 text-danger',
    warn: 'border-warning/40 text-warning',
    ok: 'border-success/40 text-success',
}[signal.value.severity]));

const periodLabel = computed(() => (analytics.from && analytics.to ? `${analytics.from} – ${analytics.to}` : ''));

// TASK-P17-020 — every section drives an action.
const goalPressure = computed(() => analytics.goalDeadlineHealth.overdue + analytics.goalDeadlineHealth.at_risk);
const imbalanced = computed(() => analytics.band === 'work_leaning' || analytics.band === 'recharge_leaning');
const executionInput = computed(() => ({
    completionRate: analytics.taskCompletionRate,
    completedTasks: analytics.taskCompleted,
    totalTasks: analytics.taskTotal,
}));
const lowCompletion = computed(() => executionIsLow(executionInput.value));
const executionInterpretation = computed<Interpretation>(() => interpretExecution(executionInput.value));

function percentLabel(percent: number | null): string {
    if (percent === null) {
        return 'N/A';
    }
    return `${Math.round(percent * 100)}%`;
}

function minutesLabel(minutes: number): string {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

function heatmapRangeFor(key: '3m' | '6m' | 'year'): { from: string; to: string } {
    const to = new Date();
    const from = new Date();
    if (key === '3m') {
        from.setMonth(to.getMonth() - 3);
    } else if (key === '6m') {
        from.setMonth(to.getMonth() - 6);
    } else {
        from.setMonth(0);
        from.setDate(1);
    }
    return { from: formatDate(from), to: formatDate(to) };
}

async function loadHeatmap(): Promise<void> {
    const { from, to } = heatmapRangeFor(activeHeatmapRange.value);
    await analytics.loadHeatmap(from, to, heatmapPillarFilter.value || undefined);
}

function heatmapDayLabel(day: { date: string; productive_minutes: number; recharge_minutes: number; completion_count: number; progress_events: number; intensity: number }): string {
    const p = minutesLabel(day.productive_minutes);
    const r = minutesLabel(day.recharge_minutes);
    return `${day.date}: ${p} focus, ${r} recharge, ${day.completion_count} completed, ${day.progress_events} progress events`;
}

function heatmapWeeks(): { weekStart: string; days: typeof analytics.heatmapDays }[] {
    const weeks: { weekStart: string; days: typeof analytics.heatmapDays }[] = [];
    const byDay = new Map<string, typeof analytics.heatmapDays[number]>();
    for (const day of analytics.heatmapDays) {
        byDay.set(day.date, day);
    }
    if (analytics.heatmapDays.length === 0) {
        return weeks;
    }
    const from = new Date(`${analytics.heatmapDays[0].date}T00:00:00`);
    const to = new Date(`${analytics.heatmapDays[analytics.heatmapDays.length - 1].date}T00:00:00`);
    const cursor = new Date(from);
    cursor.setDate(cursor.getDate() - ((cursor.getDay() + 6) % 7)); // align to Monday
    while (cursor <= to) {
        const days: typeof analytics.heatmapDays = [];
        for (let i = 0; i < 7; i++) {
            const d = formatDate(cursor);
            days.push(byDay.get(d) ?? { date: d, productive_minutes: 0, recharge_minutes: 0, completion_count: 0, progress_events: 0, intensity: 0 });
            cursor.setDate(cursor.getDate() + 1);
        }
        weeks.push({ weekStart: days[0].date, days });
    }
    return weeks;
}

onMounted(() => {
    void load(activePreset.value);
    void loadHeatmap();
});

watch([activeHeatmapRange, heatmapPillarFilter], () => {
    void loadHeatmap();
});

onUnmounted(() => {
    analytics.clear();
});

function run(fn: () => Promise<void>): void {
    void fn();
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center gap-2" data-testid="analytics-presets">
            <button
                v-for="preset in presets"
                :key="preset.key"
                type="button"
                class="border rounded-sm px-3 py-1 text-sm"
                :class="activePreset === preset.key ? 'border-border bg-surface-raised font-semibold text-text' : 'border-transparent text-text-muted hover:text-text'"
                :disabled="analytics.loading"
                data-testid="analytics-preset"
                @click="run(() => load(preset.key))"
            >
                {{ preset.label }}
            </button>
        </div>

        <div v-if="analytics.loading" class="text-sm text-text-muted" data-testid="analytics-loading">
            Loading…
        </div>

        <div v-else-if="analytics.error" class="text-sm text-danger" role="alert" data-testid="analytics-error">
            {{ (analytics.error as ApiError).message }}
        </div>

        <div v-else-if="!analytics.hasData" class="text-sm text-text-muted flex flex-col gap-2" data-testid="analytics-empty">
            <span>No tracked time in this period yet.</span>
            <FeatureHelp
                id="analytics-accumulates"
                variant="block"
                title="Analytics accumulates as you work"
                body="Completions, focus sessions, and check-ins land here automatically. Work through today's plan for a few days and this view comes alive."
            />
        </div>

        <template v-else>
            <div
                class="flex flex-wrap items-center justify-between gap-3 border rounded-sm px-3 py-2"
                :class="signalClass"
                data-testid="analytics-executive-signal"
            >
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wide opacity-80">Signal</div>
                    <p class="text-sm font-medium" data-testid="analytics-signal-headline">{{ signal.headline }}</p>
                </div>
                <button
                    v-if="signal.action"
                    type="button"
                    class="shrink-0 text-sm underline underline-offset-2 hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
                    data-testid="analytics-signal-action"
                    @click="shell.setView(signal.action)"
                >
                    {{ signal.actionLabel }} →
                </button>
            </div>

            <div class="surface-primary p-4" data-testid="analytics-summary">
                <div class="text-xs uppercase font-bold tracking-tight text-text-muted">Work-Life Ratio</div>
                <ChartMeta
                    id="summary"
                    :period="periodLabel"
                    unit="% of tracked time · minutes"
                    :legend="[
                        { swatch: 'bg-primary', label: 'Work' },
                        { swatch: 'bg-success', label: 'Recharge' },
                    ]"
                />
                <div class="text-lg font-semibold" data-testid="analytics-ratio">
                    Work {{ workPercent }}% · Recharge {{ rechargePercent }}%
                </div>
                <div class="text-sm text-text-muted" data-testid="analytics-band">{{ bandLabel }}</div>
                <div class="text-xs text-text-muted">
                    {{ minutesLabel(analytics.productiveMinutes) }} focus · {{ minutesLabel(analytics.rechargeMinutes) }} recharge
                </div>
                <div class="mt-2 flex h-3 w-full overflow-hidden rounded-sm bg-surface" data-testid="analytics-bar">
                    <div class="bg-primary" :style="{ width: `${workPercent}%` }" data-testid="analytics-work-segment" />
                    <div class="bg-success" :style="{ width: `${rechargePercent}%` }" data-testid="analytics-recharge-segment" />
                </div>

                <div v-if="analytics.previous" class="mt-2 text-xs text-text-muted" data-testid="analytics-period-comparison">
                    vs previous period ({{ analytics.previous.from }} – {{ analytics.previous.to }}):
                    Work {{ Math.round(analytics.previous.work_ratio * 100) }}% · Recharge {{ Math.round(analytics.previous.recharge_ratio * 100) }}%
                </div>

                <div v-if="analytics.workLifeTrend.length > 0" class="mt-2">
                    <div class="text-xs uppercase text-text-muted mb-1">Weekly trend</div>
                    <ul class="space-y-1">
                        <li v-for="week in analytics.workLifeTrend" :key="week.week_start" class="flex items-center justify-between text-xs text-text-muted" data-testid="analytics-worklife-trend">
                            <span>Week of {{ week.week_start }}</span>
                            <span>Work {{ Math.round(week.work_ratio * 100) }}% · Recharge {{ Math.round(week.recharge_ratio * 100) }}%</span>
                        </li>
                    </ul>
                </div>

                <div v-if="analytics.workLifeExceptions.length > 0" class="mt-2">
                    <div class="text-xs uppercase text-text-muted mb-1">Notable days</div>
                    <ul class="space-y-1">
                        <li v-for="exception in analytics.workLifeExceptions" :key="exception.date" class="text-xs text-text-muted" data-testid="analytics-worklife-exception">
                            {{ exception.date }} — {{ exception.description }}
                        </li>
                    </ul>
                </div>

                <p class="mt-2 text-xs text-text-muted" data-testid="analytics-disclaimer">
                    {{ analytics.disclaimer }}
                </p>

                <InterpretationStrip id="summary" :interpretation="workLifeInterpretation" />

                <button
                    v-if="imbalanced"
                    type="button"
                    class="mt-2 text-xs underline underline-offset-2 text-[var(--color-primary)] hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
                    data-testid="analytics-recovery-action"
                    @click="shell.setView('today')"
                >
                    {{ analytics.band === 'work_leaning' ? 'Plan a recharge block' : 'Plan a focus block' }} →
                </button>
            </div>

            <div v-if="analytics.hasGoals" class="surface-primary p-4" data-testid="analytics-goals">
                <div class="text-xs uppercase font-bold tracking-tight text-text-muted mb-2">Goal progress</div>
                <ChartMeta
                    id="goals"
                    :period="periodLabel"
                    unit="% complete · milestones · tasks"
                    :legend="[{ swatch: 'bg-primary', label: 'Progress' }]"
                />

                <div class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-sm" data-testid="analytics-goal-summary">
                    <span class="text-text-muted">
                        {{ Math.round(analytics.goalCompletionRate * 100) }}% complete
                    </span>
                    <span v-if="goalHealthLabel" class="text-text-muted" data-testid="analytics-goal-health">{{ goalHealthLabel }}</span>
                    <span class="text-text-muted">
                        {{ analytics.goalCompletedMilestones }}/{{ analytics.goalTotalMilestones }} milestones
                    </span>
                    <span v-if="analytics.goalWorkloadCompletion > 0" class="text-text-muted">
                        {{ Math.round(analytics.goalWorkloadCompletion * 100) }}% tasks completed
                    </span>
                </div>

                <ul class="space-y-2">
                    <li v-for="goal in visibleGoals" :key="goal.id" class="text-sm" data-testid="analytics-goal">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-text">{{ goal.title }}</span>
                            <span class="shrink-0 text-xs text-text-muted" data-testid="analytics-goal-deadline">
                                {{ deadlineLabel(goal.deadline_health, goal.days_remaining) }}
                            </span>
                        </div>
                        <div class="mt-1 flex h-2 w-full overflow-hidden rounded-sm bg-surface">
                            <div class="bg-primary" :style="{ width: `${goal.progress}%` }" data-testid="analytics-goal-bar" />
                        </div>
                        <div class="mt-0.5 text-xs text-text-muted">
                            {{ goal.progress }}% · {{ goal.milestones_completed }}/{{ goal.milestones_total }} milestones · {{ goal.tasks_completed }}/{{ goal.tasks_total }} tasks
                        </div>
                    </li>
                </ul>
                <div v-if="hiddenGoalCount > 0" class="mt-2 text-xs text-text-muted" data-testid="analytics-goals-more">
                    +{{ hiddenGoalCount }} more goals
                </div>

                <div v-if="analytics.programs.length > 0" class="mt-4 border-t border-border pt-3">
                    <div class="text-xs uppercase text-text-muted mb-1">Programs</div>
                    <ul class="space-y-1">
                        <li v-for="program in analytics.programs" :key="program.id" class="flex items-center justify-between text-sm" data-testid="analytics-program">
                            <span class="truncate text-text">{{ program.name }}</span>
                            <span class="shrink-0 text-xs text-text-muted">
                                {{ Math.round(program.workload_completion * 100) }}% · {{ program.tasks_completed }}/{{ program.tasks_total }}
                            </span>
                        </li>
                    </ul>
                </div>

                <InterpretationStrip id="goals" :interpretation="goalsInterpretation" />

                <button
                    v-if="goalPressure > 0"
                    type="button"
                    class="mt-2 text-xs underline underline-offset-2 text-[var(--color-primary)] hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
                    data-testid="analytics-review-milestone"
                    @click="shell.setView('goals')"
                >
                    Review milestone →
                </button>
            </div>

            <div v-if="analytics.capacityDays.length > 0" class="surface-primary p-4" data-testid="analytics-capacity">
                <div class="text-xs uppercase font-bold tracking-tight text-text-muted mb-2">Capacity</div>
                <ChartMeta
                    id="capacity"
                    :period="periodLabel"
                    unit="% of planned load realized · minutes"
                    :legend="[
                        { swatch: 'bg-primary', label: 'Scheduled' },
                        { swatch: 'bg-danger', label: 'Overload' },
                    ]"
                />

                <div class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-sm" data-testid="analytics-capacity-summary">
                    <span class="text-text-muted">
                        {{ Math.round(analytics.capacityRealization * 100) }}% realized
                    </span>
                    <span class="text-text-muted">
                        {{ analytics.capacityDays.filter((d) => d.status === 'overload').length }} overloaded days
                    </span>
                    <span class="text-text-muted">{{ recommendationLabel }} ({{ analytics.capacityConfidence }})</span>
                </div>

                <ul class="space-y-1">
                    <li v-for="day in analytics.capacityDays" :key="day.date" class="flex items-center gap-2 text-sm" data-testid="analytics-capacity-day">
                        <span class="w-24 shrink-0 text-text-muted">{{ day.date }}</span>
                        <div class="flex h-2.5 flex-1 overflow-hidden rounded-sm bg-surface">
                            <div
                                class="h-full"
                                :class="day.status === 'overload' ? 'bg-danger' : 'bg-primary'"
                                :style="{ width: `${Math.min(100, (day.scheduled_minutes / Math.max(1, day.available_minutes + day.scheduled_minutes)) * 100)}%` }"
                                data-testid="analytics-capacity-load"
                            />
                        </div>
                        <span class="w-32 shrink-0 text-right text-xs" :class="day.status === 'overload' ? 'text-danger' : 'text-text-muted'">
                            {{ minutesLabel(day.scheduled_minutes) }}{{ day.status === 'overload' ? ` / ${minutesLabel(day.overload_minutes)} overload` : '' }}
                        </span>
                    </li>
                </ul>

                <div v-if="analytics.capacityWeeks.length > 0" class="mt-4 border-t border-border pt-3">
                    <div class="text-xs uppercase text-text-muted mb-1">Trend</div>
                    <ul class="space-y-1">
                        <li v-for="week in analytics.capacityWeeks" :key="week.week_start" class="flex items-center justify-between text-sm" data-testid="analytics-capacity-week">
                            <span class="text-text-muted">Week of {{ week.week_start }}</span>
                            <span class="text-xs text-text-muted">
                                {{ Math.round(week.realization * 100) }}% · {{ minutesLabel(week.completed_minutes) }} / {{ minutesLabel(week.planned_minutes) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <p v-if="analytics.capacityReason" class="mt-2 text-xs text-text-muted" data-testid="analytics-capacity-reason">
                    {{ analytics.capacityReason }}
                </p>

                <InterpretationStrip id="capacity" :interpretation="capacityInterpretation" />

                <button
                    type="button"
                    class="mt-2 text-xs underline underline-offset-2 text-[var(--color-primary)] hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
                    data-testid="analytics-review-schedule"
                    @click="shell.setView('schedule')"
                >
                    Review schedule →
                </button>
            </div>

            <div v-if="analytics.taskTotal > 0" class="surface-primary p-4" data-testid="analytics-execution">
                <div class="text-xs uppercase font-bold tracking-tight text-text-muted mb-2">Execution</div>
                <ChartMeta
                    id="execution"
                    :period="periodLabel"
                    unit="% of scheduled tasks completed · counts"
                    :legend="[{ swatch: 'bg-primary', label: 'Completion' }]"
                />

                <div class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-sm" data-testid="analytics-execution-summary">
                    <span :class="lowCompletion ? 'text-danger' : 'text-text-muted'" data-testid="analytics-execution-rate">
                        {{ Math.round(analytics.taskCompletionRate * 100) }}% complete
                    </span>
                    <span class="text-text-muted">
                        {{ analytics.taskCompleted }}/{{ analytics.taskTotal }} tasks
                    </span>
                    <span class="text-text-muted">
                        {{ analytics.taskCompletedInPeriod }} completed in period
                    </span>
                </div>

                <div class="flex h-2.5 w-full overflow-hidden rounded-sm bg-surface">
                    <div
                        class="h-full"
                        :class="lowCompletion ? 'bg-danger' : 'bg-primary'"
                        :style="{ width: `${Math.min(100, Math.round(analytics.taskCompletionRate * 100))}%` }"
                        data-testid="analytics-execution-bar"
                    />
                </div>

                <InterpretationStrip id="execution" :interpretation="executionInterpretation" />

                <button
                    v-if="lowCompletion"
                    type="button"
                    class="mt-2 text-xs underline underline-offset-2 text-[var(--color-primary)] hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
                    data-testid="analytics-reduce-workload"
                    @click="shell.setView('schedule')"
                >
                    Reduce workload →
                </button>
            </div>

            <div v-if="analytics.pillars.length > 0" class="surface-supporting" data-testid="analytics-pillars">
                <div class="text-xs uppercase font-bold tracking-tight text-text-muted mb-2">Life pillars</div>
                <ChartMeta
                    id="pillars"
                    :period="periodLabel"
                    unit="% of pillar target · minutes"
                    :legend="[{ swatch: 'bg-primary', label: 'Realized' }]"
                />

                <ul class="space-y-2">
                    <li v-for="pillar in analytics.pillars" :key="pillar.key" class="text-sm" data-testid="analytics-pillar">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-text">{{ pillar.label }}</span>
                            <span class="shrink-0 text-xs text-text-muted" data-testid="analytics-pillar-percent">
                                {{ percentLabel(pillar.percent) }}
                            </span>
                        </div>
                        <div class="mt-1 flex h-2 w-full overflow-hidden rounded-sm bg-surface">
                            <div
                                v-if="pillar.percent !== null"
                                class="h-full bg-primary"
                                :style="{ width: `${Math.min(100, pillar.percent * 100)}%` }"
                                data-testid="analytics-pillar-bar"
                            />
                        </div>
                        <div class="mt-0.5 text-xs text-text-muted">
                            {{ minutesLabel(pillar.realization_minutes) }} completed{{ pillar.target_minutes > 0 ? ` vs ${minutesLabel(pillar.target_minutes)} target` : ' · no target' }}
                        </div>
                    </li>
                </ul>

                <InterpretationStrip id="pillars" :interpretation="pillarInterpretation" />
            </div>

            <div class="surface-supporting" data-testid="analytics-heatmap">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2">
                        <div class="text-xs uppercase font-bold tracking-tight text-text-muted">Activity heatmap</div>
                        <FeatureHelp id="progress-events" title="Progress Events" body="Completions and focus sessions feed this view. Progress events show movement toward your goals — not just raw hours spent." />
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <div class="flex gap-1">
                            <button
                                v-for="range in heatmapRanges"
                                :key="range.key"
                                type="button"
                                class="border rounded-sm px-2 py-0.5 text-xs"
                                :class="activeHeatmapRange === range.key ? 'border-border bg-surface-raised font-semibold text-text' : 'border-transparent text-text-muted hover:text-text'"
                                data-testid="analytics-heatmap-range"
                                @click="activeHeatmapRange = range.key"
                            >
                                {{ range.label }}
                            </button>
                        </div>
                        <label class="flex items-center gap-1 text-xs text-text-muted">
                            Pillar
                            <select
                                v-model="heatmapPillarFilter"
                                class="border border-border rounded-sm px-1 py-0.5 text-xs bg-transparent"
                                data-testid="analytics-heatmap-pillar"
                            >
                                <option value="">All</option>
                                <option v-for="option in pillarOptions" :key="option.key" :value="option.key">{{ option.label }}</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div v-if="analytics.heatmapLoading" class="text-sm text-text-muted" data-testid="analytics-heatmap-loading">
                    Loading…
                </div>
                <div v-else-if="analytics.heatmapError" class="text-sm text-danger" role="alert" data-testid="analytics-heatmap-error">
                    {{ (analytics.heatmapError as ApiError).message }}
                </div>
                <div v-else>
                    <div class="overflow-x-auto">
                        <div class="inline-flex flex-col gap-1" data-testid="analytics-heatmap-grid">
                            <div v-for="week in heatmapWeeks()" :key="week.weekStart" class="flex gap-1" data-testid="analytics-heatmap-week">
                                <span class="w-8 shrink-0 text-[10px] text-text-muted" />
                                <button
                                    v-for="day in week.days"
                                    :key="day.date"
                                    type="button"
                                    class="h-3.5 w-3.5 rounded-sm"
                                    :class="heatmapColor[day.intensity]"
                                    :aria-label="heatmapDayLabel(day)"
                                    :title="heatmapDayLabel(day)"
                                    data-testid="analytics-heatmap-cell"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 flex items-center gap-1 text-xs text-text-muted" data-testid="analytics-heatmap-legend">
                        <span>Less</span>
                        <span v-for="item in analytics.heatmapLegend" :key="item.level" class="h-3 w-3 rounded-sm" :class="heatmapColor[item.level]" :title="item.label" />
                        <span>More</span>
                        <span class="ml-1 text-text-muted">{{ analytics.heatmapLegend.filter((i) => i.level > 0).map((i) => i.label).join(' · ') }}</span>
                    </div>

                    <button
                        type="button"
                        class="mt-2 text-xs underline text-text-muted"
                        data-testid="analytics-heatmap-list-toggle"
                        @click="showHeatmapList = !showHeatmapList"
                    >
                        {{ showHeatmapList ? 'Hide' : 'Show' }} list view (accessible)
                    </button>
                    <ul v-if="showHeatmapList" class="mt-2 space-y-1" data-testid="analytics-heatmap-list">
                        <li v-for="day in analytics.heatmapDays" :key="day.date" class="text-xs text-text-muted">
                            {{ heatmapDayLabel(day) }}
                        </li>
                    </ul>

                    <InterpretationStrip id="heatmap" :interpretation="heatmapInterpretation" />
                </div>
            </div>

            <div v-if="analytics.days.length > 0" class="surface-supporting" data-testid="analytics-days">
                <div class="text-xs uppercase font-bold tracking-tight text-text-muted mb-2">Per day</div>
                <ChartMeta
                    id="days"
                    :period="periodLabel"
                    unit="minutes per day"
                    :legend="[
                        { swatch: 'bg-primary', label: 'Work' },
                        { swatch: 'bg-success', label: 'Recharge' },
                    ]"
                />
                <ul class="space-y-1">
                    <li
                        v-for="day in analytics.days"
                        :key="day.date"
                        class="flex items-center gap-2 text-sm"
                        data-testid="analytics-day"
                    >
                        <span class="w-24 shrink-0 text-text-muted">{{ day.date }}</span>
                        <div class="flex h-2.5 flex-1 overflow-hidden rounded-sm bg-surface">
                            <div class="bg-primary" :style="{ width: `${Math.round(day.work_ratio * 100)}%` }" data-testid="analytics-day-work" />
                            <div class="bg-success" :style="{ width: `${Math.round(day.recharge_ratio * 100)}%` }" data-testid="analytics-day-recharge" />
                        </div>
                        <span class="w-28 shrink-0 text-right text-xs text-text-muted">
                            {{ minutesLabel(day.productive_minutes) }} / {{ minutesLabel(day.recharge_minutes) }}
                        </span>
                    </li>
                </ul>

                <InterpretationStrip id="days" :interpretation="daysInterpretation" />
            </div>
        </template>
    </div>
</template>