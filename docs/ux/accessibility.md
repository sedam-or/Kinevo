# Kinevo — Accessibility

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Canonical accessibility contract
> (consolidates SRS NFR-07 + design.md a11y rules + P28-012 evidence).

## 1. Baseline

**WCAG 2.2 AA** is the product baseline — verified per release-candidate surface
with axe-core (wcag2a + wcag2aa, zero violations across Chromium/Firefox/WebKit,
P28 evidence in `docs/browser-e2e.md`).

## 2. Contracts

1. **Keyboard complete:** every action reachable and operable by keyboard;
   focus-visible ring (`focus:outline-none focus-visible:ring-2 ring-focus`) on
   every interactive element; logical tab order; Escape closes popovers.
2. **Focus visibility:** never `outline: none` without a visible replacement;
   focus is moved programmatically where actions hand off (e.g. first-session
   capture CTA → input focus).
3. **Screen-reader semantics:** landmarks (`banner`, `navigation[aria-label]`,
   `main`, skip-to-content link); `role="status"` + `aria-live="polite"` for
   async sync/progress transitions; `role="alert"` for errors; sr-only
   descriptions for visual-only data (timeline blocks, empty slots, charts).
4. **Color-independence:** every state pairs color with glyph + label
   (VisualStateBadge); deadline proximity and load levels never rely on hue alone.
5. **Forms:** visible labels (placeholder is never the only label); native
   controls where possible (`color-scheme` for native readability); errors in
   `role="alert"` near the field.
6. **Touch targets:** `min-h-[44px]` on interactive controls (mobile sweep
   verifies).
7. **Motion:** `prefers-reduced-motion` respected everywhere (`motion.md` §5).
8. **Charts/data-viz:** legend + period + unit (ChartMeta); accessible list
   alternates for heatmaps/matrixes.

## 3. Verification protocol

- axe-core suite over touched surfaces (regression gate; P28-012 baseline).
- Keyboard-only journey in the browser suite.
- Mobile width sweep (375px) for reachability and overflow.
- New P29 canonical UI (first-session guide, InlineError, FeatureHelp additions)
  follows these contracts; the p28-ux-audit browser evidence covers them.
