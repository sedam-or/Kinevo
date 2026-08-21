/**
 * Kinevo spacing tokens (TASK-R2, docs/design-tokens.md §3).
 * Scale follows design.md §7.
 */
export const spacingTokens = {
    1: 4,
    2: 8,
    3: 12,
    4: 16,
    5: 20,
    6: 24,
    7: 32,
    8: 40,
    9: 48,
    10: 64,
    11: 80,
} as const;

export type SpacingStep = keyof typeof spacingTokens;