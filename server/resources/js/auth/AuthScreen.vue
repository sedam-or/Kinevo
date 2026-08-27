<script setup lang="ts">
/**
 * Auth gateway chrome.
 *
 * Design contract (design-taste-frontend + ui-ux-pro-max consult):
 * - Split gate: accent panel carries ONE hero stack (brand strip, display
 *   headline from the official banner voice, the GOAL→TODAY→ADAPT chain);
 *   no second marketing voice on the form side.
 * - Ghost-brand texture echoes docs/assets/banner-kinevo-dark.svg instead of
 *   meaningless decoration.
 * - Entry cascade is 160ms transform/opacity only; app.css collapses every
 *   animation under prefers-reduced-motion.
 */
import { useShellStore } from '../shell/store';
import KLogo from '../components/KLogo.vue';

const shell = useShellStore();

const CHAIN = ['GOAL', 'BREAKDOWN', 'SCHEDULE', 'TODAY', 'ADAPT'];
</script>

<template>
    <div class="grid min-h-[100dvh] bg-bg text-text lg:grid-cols-2" data-testid="auth-gate">
        <!-- Brand panel: one accent statement, desktop only -->
        <aside
            class="relative hidden select-none overflow-hidden bg-primary text-primary-contrast lg:flex lg:flex-col"
            aria-hidden="true"
        >
            <!-- Ghost brand texture: same device as the banner asset -->
            <div class="pointer-events-none absolute -right-16 -top-16">
                <KLogo :size="380" variant="outline" class="opacity-[0.08]" />
            </div>

            <div class="relative flex flex-1 flex-col justify-between p-10 xl:p-14">
                <div class="flex items-center gap-3">
                    <KLogo :size="44" variant="outline" />
                    <span class="font-mono text-xs font-medium tracking-[0.32em]">KINEVO</span>
                </div>

                <div class="max-w-lg">
                    <p class="text-6xl leading-none font-black tracking-tighter xl:text-7xl">
                        Plan.<br />Schedule.<br />Focus.<br /><span class="underline decoration-primary-contrast decoration-[6px] underline-offset-8">Adapt.</span>
                    </p>
                </div>

                <ul class="flex items-center gap-0 font-mono text-[13px] tracking-[0.12em]">
                    <template v-for="(step, i) in CHAIN" :key="step">
                        <li v-if="i > 0" class="px-2 opacity-70">&#8594;</li>
                        <li :class="i === CHAIN.length - 1 ? 'font-bold' : ''">{{ step }}</li>
                    </template>
                </ul>
            </div>
        </aside>

        <!-- Form column -->
        <main class="relative flex items-center justify-center px-5 py-14 sm:px-8">
            <button
                type="button"
                class="absolute top-4 right-4 rounded-sm px-2.5 py-1.5 font-mono text-xs font-medium text-text-muted transition-colors hover:bg-surface hover:text-text"
                data-testid="theme-toggle"
                @click="shell.cycleTheme()"
            >
                {{ shell.theme }}
            </button>

            <div class="w-full max-w-md animate-[kinevo-rise_160ms_ease-out_both]">
                <!-- In-flow mobile brand row -->
                <div class="mb-8 flex items-center gap-2.5 lg:hidden" aria-hidden="true">
                    <KLogo :size="30" />
                    <span class="font-mono text-xs font-medium tracking-[0.28em]">KINEVO</span>
                </div>

                <div class="surface-hero animate-[kinevo-rise_200ms_ease-out_80ms_both] p-7 sm:p-10">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
@keyframes kinevo-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
