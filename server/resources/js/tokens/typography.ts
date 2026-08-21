/**
 * Kinevo typography tokens (TASK-R2, docs/design-tokens.md §6).
 * Scale/weights per design.md §6.2/§6.3. Family stays the existing
 * Instrument Sans (app.css @theme).
 */
export const typographyTokens = {
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
    scale: {
        display: '2.5rem',
        h1: '2rem',
        h2: '1.5rem',
        h3: '1.25rem',
        body: '1rem',
        small: '0.875rem',
        micro: '0.75rem',
    },
    weights: {
        heading: 700,
        body: 500,
        secondary: 400,
        label: 600,
        numeric: 700,
    },
} as const;

export type TypographyRole = keyof typeof typographyTokens.scale;