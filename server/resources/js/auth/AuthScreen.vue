<script setup lang="ts">
/**
 * Auth gateway chrome (UI restructure slice).
 *
 * Branded split-screen: primary-accent brand panel + hero-level form card.
 * Owns the guest-screen layout so Login and Register stay identical;
 * the surrounding testid ("auth-gate") lives here. Copy kept minimal:
 * one headline + one sub-line, no trust-strip filler.
 */
import { useShellStore } from '../shell/store';

const shell = useShellStore();
</script>

<template>
    <div class="grid min-h-[100dvh] bg-bg text-text lg:grid-cols-2" data-testid="auth-gate">
        <!-- Brand panel: single accent statement, desktop only -->
        <aside
            class="relative hidden select-none overflow-hidden bg-primary text-primary-contrast lg:flex lg:flex-col lg:justify-between"
            aria-hidden="true"
        >
            <div class="flex items-center gap-3 p-10">
                <span class="flex size-10 items-center justify-center border-2 border-primary-contrast bg-transparent text-lg font-black">
                    K
                </span>
                <span class="text-lg font-black tracking-[0.28em]">KINEVO</span>
            </div>

            <div class="max-w-md p-10 pb-16">
                <p class="text-5xl leading-none font-black tracking-tight xl:text-6xl">Start today.</p>
                <p class="mt-5 max-w-[34ch] text-base leading-relaxed opacity-90">
                    Plan the week, focus on now. Kinevo turns goals into today's moves.
                </p>
            </div>

            <!-- Brutalist color-block decoration -->
            <div class="pointer-events-none absolute right-0 bottom-0 flex">
                <span class="size-14 border-4 border-primary-contrast bg-transparent"></span>
                <span class="size-14 bg-text"></span>
                <span class="size-14 border-4 border-text bg-primary"></span>
            </div>
        </aside>

        <!-- Form column -->
        <main class="relative flex items-center justify-center px-5 py-12 sm:px-8">
            <button
                type="button"
                class="absolute top-4 right-4 rounded-sm border border-border bg-bg px-2.5 py-1.5 text-xs font-semibold text-text hover:bg-surface"
                data-testid="theme-toggle"
                @click="shell.cycleTheme()"
            >
                {{ shell.theme }}
            </button>

            <!-- Mobile mini-brand (desktop uses the accent panel) -->
            <div class="absolute top-4 left-5 flex items-center gap-2 lg:hidden" aria-hidden="true">
                <span class="flex size-7 items-center justify-center border-2 border-border bg-primary text-sm font-black text-primary-contrast">
                    K
                </span>
                <span class="text-sm font-black tracking-[0.24em]">KINEVO</span>
            </div>

            <div class="surface-hero mt-8 w-full max-w-sm p-7 sm:p-9 lg:mt-0">
                <slot />
            </div>
        </main>
    </div>
</template>
