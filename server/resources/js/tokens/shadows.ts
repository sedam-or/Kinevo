/**
 * Kinevo shadow tokens (TASK-R2, docs/design-tokens.md §5).
 * Neo-Brutalist offset shadows: offset communicates physical interaction
 * (design.md §4.3, §49). currentColor-based so shadow inherits the border
 * color per theme.
 */
export const shadowTokens = {
    rest: '4px 4px 0 currentColor',
    hover: '6px 6px 0 currentColor',
    active: '2px 2px 0 currentColor',
} as const;

export type ShadowToken = keyof typeof shadowTokens;