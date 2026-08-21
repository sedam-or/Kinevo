/**
 * Kinevo motion tokens (TASK-R2, docs/design-tokens.md §7).
 * Durations per design.md §48; prefers-reduced-motion always wins (design.md §47).
 */
export const motionTokens = {
    micro: 130,
    panel: 190,
    modal: 220,
} as const;

export type MotionToken = keyof typeof motionTokens;