/**
 * Kinevo radius tokens (TASK-R2, docs/design-tokens.md §4.1).
 * design.md §4.4.
 */
export const radiusTokens = {
    none: 0,
    sm: 4,
    md: 8,
    lg: 12,
} as const;

export type RadiusToken = keyof typeof radiusTokens;