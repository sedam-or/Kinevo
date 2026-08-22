/**
 * Kinevo color tokens (TASK-R2, docs/design-tokens.md §2).
 *
 * Semantic roles follow design.md §5.1; light/dark follow §5.3/§5.4; state
 * signals are never color-only (design.md §5.2). `primary` matches the
 * existing #F53003 accent used across the current UI so token adoption does
 * not change rendered colors.
 */
export const colorTokens = {
    light: {
        bg: '#FDFDFC',
        surface: '#EDEDEC',
        'surface-raised': '#FFFFFF',
        text: '#1b1b18',
        'text-muted': '#636361',
        border: '#1b1b18',
        primary: '#F53003',
        'primary-contrast': '#FFFFFF',
        success: '#1D8A4E',
        warning: '#B7730F',
        danger: '#D20812',
        'danger-contrast': '#FFFFFF',
        info: '#2C5FA8',
        focus: '#2C5FA8',
    },
    dark: {
        bg: '#0a0a0a',
        surface: '#131313',
        'surface-raised': '#1b1b18',
        text: '#EDEDEC',
        'text-muted': '#A1A19C',
        border: '#343430',
        primary: '#F53003',
        'primary-contrast': '#FFFFFF',
        success: '#3BB573',
        warning: '#E8A13A',
        danger: '#FF5A4E',
        'danger-contrast': '#1D0002',
        info: '#7FA6E0',
        focus: '#EDEDEC',
    },
} as const;

export type ColorRole = keyof (typeof colorTokens)['light'];
export type ThemeName = 'light' | 'dark';