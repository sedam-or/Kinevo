/**
 * Kinevo z-index tokens (TASK-R2, docs/design-tokens.md §8).
 * Centralized per design.md §66. Never use raw 99999 in components.
 */
export const zIndexTokens = {
    base: 0,
    sticky: 100,
    dropdown: 200,
    popover: 300,
    drawer: 400,
    modal: 500,
    toast: 600,
    commandPalette: 700,
    criticalOverlay: 800,
} as const;

export type ZIndexToken = keyof typeof zIndexTokens;