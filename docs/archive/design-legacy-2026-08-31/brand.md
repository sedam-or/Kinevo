# Kinevo — Brand & Design System Architecture (Phase 20)

> TASK-P20-001/002/003/004 deliverable. The existing identity remains
> authoritative; this document formalizes it, it does not replace it.

## 1. Brand usage inventory (audit result, 2026-08-26)

| Asset | Location | Status |
|---|---|---|
| Favicon | `server/public/favicon.ico` | present, referenced by app layout |
| Logo / wordmark | GitHub social banner + README hero SVGs | repository-level; no in-app logo surface by design (product-first shell) |
| Primary color | `--color-primary` (#DE3005 family, AA-tuned in R5) | tokenized |
| Neutrals | `--color-bg/surface/surface-raised/border/text/text-muted` | tokenized |
| Semantic status | `--color-success/warning/danger/info` (+ `-contrast`) | tokenized |
| Focus ring | `--color-focus` | tokenized, `:focus-visible` global |
| Typography | Inter stack via tokens (`--font-*`), scale in `tokens/typography.ts` | tokenized |
| Motion | `tokens/motion.ts` + `prefers-reduced-motion` override | tokenized |

## 2. Color architecture — three levels

```text
brand/primitive   --color-primary, -contrast; neutral ramp
semantic          surface.*, border.*, text.*, status(success/warning/danger/info), focus
component         consumed ONLY via Tailwind utilities/util classes that read the
                  semantic layer (bg-surface, text-danger, border-border, …)
```

Rules (P20-003):
- Components MUST NOT hardcode hex values. R2 swept new code; UI-004 documents
  legacy visual-churn debt as non-blocking.
- Workspace accents (P19) are IDENTITY dots only — never semantic colors.

## 3. Theme architecture (P20-005)

One system: light / dark / system. Implemented in `shell/theme.ts`, hydrated
pre-paint by an inline head script (no flash), live-follows OS in system mode,
persisted per device. Browser-proven: `theme.spec.ts` 18/18 (TASK-P17-033).

## 4. Typography hierarchy (P20-006)

| Role | Usage |
|---|---|
| display | landing hero only |
| page title | `text-xl font-semibold` (Today date, view headings) |
| section | `text-sm font-semibold uppercase tracking-wide` section headers |
| body | default 14–16px |
| metadata | `text-xs text-gray-500` timestamps/hints |
| label | form labels (always paired with control) |
| mono/code | editor code blocks, ids |

## 5. Spacing/Radius/Shadow/Z/Motion (P20-007)

All formalized in `resources/js/tokens/{spacing,radius,shadows,motion,zindex}.ts`
+ `app.css @theme`. Neo-brutalist offset language: rest 4px / hover 6px /
pressed 2px (P17-012). No arbitrary visual values in new code (R2 gate).

## 6. Logo rules (P20-002)

- Minimum size: favicon 16px; banner mark ≥ 24px height.
- Clear space: ≥ 1× mark height on all sides.
- Monochrome variants: primary-on-light, white-on-dark.
- Forbidden: stretching, recoloring outside brand/semantic tokens, placing
  the mark on low-contrast imagery.
