<script setup lang="ts">
/**
 * Official Kinevo brand mark (docs/assets/banner-kinevo-dark.svg).
 *
 * Geometry: rounded square + stem, rising milestone steps, forward leg.
 * Token-driven: square uses --color-primary, glyph uses
 * --color-primary-contrast. Use `variant="outline"` on primary-accent
 * backgrounds where a filled square would disappear.
 */
withDefaults(defineProps<{ size?: number; variant?: 'brand' | 'outline' }>(), {
    size: 32,
    variant: 'brand',
});
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 176 176" aria-hidden="true">
        <rect
            width="176"
            height="176"
            rx="28"
            :fill="variant === 'brand' ? 'var(--color-primary)' : 'none'"
            :stroke="variant === 'brand' ? 'none' : 'var(--color-primary-contrast)'"
            stroke-width="10"
        />
        <rect x="48" y="40" width="18" height="96" rx="2" fill="var(--color-primary-contrast)" />
        <path
            d="M80 92 h22 v-24 h24 v-24"
            fill="none"
            stroke="var(--color-primary-contrast)"
            stroke-width="18"
            stroke-linecap="square"
            stroke-linejoin="miter"
        />
        <path
            d="M80 106 L128 142"
            fill="none"
            stroke="var(--color-primary-contrast)"
            stroke-width="18"
            stroke-linecap="square"
        />
    </svg>
</template>
