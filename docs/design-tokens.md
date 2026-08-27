# Kinevo — Design Tokens

> **Document role:** Centralized token reference for the UI/UX design system.
>
> **Status:** Proposed stabilization baseline (lifecycle: ACTIVE). Implemented as
> R2 (Design System) of the rescue plan; until then the current
> `resources/css/app.css` + `shell/theme.ts` styling is the baseline.
>
> **Contract:** `docs/design.md` §4–§7, §48, §58, §65–§66. Tokens are the single
> source of truth for visual values. No component hard-codes a raw visual value
> once R2 lands.

---

## 1. Purpose

Centralize every visual value Kinevo uses so that:

- Neo-Brutalist structure is consistent across all surfaces;
- light, dark, and system themes are semantic, not inverted;
- density modes and reduced-motion are token-driven;
- a visual regression baseline can be enforced.

Implementation target (R2):

```text
colors.ts
spacing.ts
radius.ts
shadows.ts
typography.ts
motion.ts
zindex.ts
```

with CSS custom properties (`--k-*`) hydrated in `resources/css/app.css` and,
where applicable, Tailwind v4 theme mapping.

---

## 2. Color tokens

### 2.1 Semantic roles (design.md §5.1)

| Token                   | Role                                   |
| ----------------------- | -------------------------------------- |
| `--color-bg`            | Page background                        |
| `--color-surface`       | Card / panel surface                   |
| `--color-surface-raised`| Raised / hovered / active surface      |
| `--color-text`          | Primary text                           |
| `--color-text-muted`    | Secondary text                         |
| `--color-border`        | Border for containers and inputs       |
| `--color-primary`       | Primary accent (one strong accent)     |
| `--color-primary-contrast` | Text on primary                    |
| `--color-success`       | Success semantics                      |
| `--color-warning`       | Warning semantics                      |
| `--color-danger`        | Danger / destructive semantics         |
| `--color-danger-contrast` | Text on danger fills (buttons)       |
| `--color-danger-tint`   | Danger alert surface background        |
| `--color-success-tint`  | Success alert surface background       |
| `--color-info-tint`     | Information alert surface background   |
| `--color-warning-tint`  | Warning alert surface background       |
| `--color-info`          | Information semantics                  |
| `--color-focus`         | Visible focus indicator                |

### 2.2 State signals

Colors are NEVER the only state signal (design.md §5.2). Every state indicator
combines at least two of icon/glyph, text, pattern, border treatment, position,
or shape. The `VisualStateBadge` component is the canonical vehicle.

### 2.3 Light theme (design.md §5.3)

```text
bg            warm neutral (not pure white)
surface       one step darker/warmer than bg
raised        visibly distinct from surface
text          deep ink
border        high-contrast dark
primary       one strong accent
semantics     success / warning / danger / info
```

### 2.4 Dark theme (design.md §5.4)

```text
bg            deep neutral
surface       dark-raised (lighter than bg, never bright)
raised        lighter still
text          bright
border        high-contrast (lighter than bg)
primary       controlled accent saturation (not neon)
```

`--color-danger-contrast` exists because the danger role inverts between
themes: light `#D20812` carries white text (5.9:1), dark `#FF5A4E` requires a
deep ink fill text (`#1D0002`, ≥6:1). White-on-danger fails WCAG AA in dark
mode — never pair `text-white` with `bg-danger`; use `text-danger-contrast`
(R7, ui-audit UI-011).

### 2.5 Theme resolution

`Light | Dark | System` persists locally (`shell/theme.ts` already stores
`kinevo.theme`) and MUST be restored on load. System follows
`prefers-color-scheme`.

---

## 3. Spacing tokens (design.md §7)

```text
space-1: 4px
space-2: 8px
space-3: 12px
space-4: 16px
space-5: 20px
space-6: 24px
space-7: 32px
space-8: 40px
space-9: 48px
space-10: 64px
space-11: 80px
```

No arbitrary spacing values unless justified by component-specific geometry.

---

## 4. Border tokens (design.md §4.2)

```text
border-width-1: 1px   secondary containers
border-width-2: 2px   normal interactive component
border-width-3: 3px   primary cards / strong emphasis
border-width-4: 4px   hero / modal / important state
```

Border hierarchy is part of information hierarchy — do not make everything 4px.

### Border-radius (design.md §4.4)

```text
radius-0:  0px   tables, timeline segments, canvas overlays
radius-1:  4px   inputs, buttons, badges
radius-2:  8px   cards, drawers
radius-3: 12px   large modal / workspace surfaces
```

Avoid fully rounded controls except where semantics require it.

### 4a. Information-hierarchy surfaces (TASK-P17-021)

Five shared levels implement §4's border doctrine as utilities
(`resources/css/app.css`, `@layer components`). Weight concentrates on
Hero/Primary; Supporting and Metadata stay open — Neo-Brutalism is not
"everything boxed". All levels derive from theme vars and follow light/dark
automatically; never hard-code grays alongside them.

```text
L1 .surface-hero        hero / modal / important state   border-width-4 + radius-2 + raised bg + shadow-rest
L2 .surface-primary     primary card / strong emphasis   border-width-3 + radius-1 + raised bg
L3 .surface-secondary   grouped container                border-width-2 + radius-1 + raised bg
L4 .surface-supporting  supporting group                 NO box — hairline top border + open whitespace above
L5 .surface-metadata    metadata rows                    no chrome at all; rides on the enclosing surface
```

Rules:

- A page leads with at most one Hero surface; Primary is for decision content.
- Supporting groups separate major sections through whitespace (space-6+)
  plus a hairline — not through additional boxes.
- Interactive components keep their own width-2 borders (KButton et al.);
  the L-system classifies containers only.
- Reference adoption: Analytics (`AnalyticsView.vue`) — summary/goals/
  capacity/execution are L2 Primary; pillars/heatmap/per-day are L4
  Supporting. Other surfaces adopt incrementally; do not re-box open groups.

---

## 5. Shadow tokens (design.md §4.3, §49)

Neo-Brutalist offset shadows. Offset communicates physical interaction.

```text
shadow-rest:    4px 4px 0 currentColor
shadow-hover:   6px 6px 0 currentColor
shadow-active:  2px 2px 0 currentColor
```

Pressed state additionally translates the control `2px 2px` so it physically
depresses.

---

## 6. Typography tokens (design.md §6)

### Scale

```text
display: 40–56px
h1:      32–40px
h2:      24–32px
h3:      20–24px
body:    15–17px
small:   13–14px
micro:   11–12px   (never essential information)
```

### Weights

```text
heading:       700–900
primary body:  450–550
secondary:     400–500
label:         600–750
numeric:       700–900
```

### Font family

Highly legible sans-serif with strong x-height, readable numerals, good UI
punctuation, excellent weight range. No novelty font for body text.

---

## 7. Motion tokens (design.md §47–§48)

```text
motion-micro:   100–160ms   micro interactions
motion-panel:   160–220ms   panel transitions
motion-modal:   180–260ms   modal
```

`prefers-reduced-motion` reduces to opacity / small transform / minimal movement
or removes the animation.

---

## 8. Z-index tokens (design.md §66)

```text
z-base:             0
z-sticky:           100
z-dropdown:         200
z-popover:          300
z-drawer:           400
z-modal:            500
z-toast:            600
z-command-palette:  700
z-critical-overlay: 800
```

No scattered `z-index: 99999`.

---

## 9. Breakpoints (design.md §58)

```text
xs < 640
sm 640
md 768
lg 1024
xl 1280
2xl 1536
```

Avoid designing against exact device models.

---

## 10. Density (design.md §59)

```text
default:    Comfortable
optional:   Compact (desktop power users)
```

Compacts spacing scale by ~½ step while preserving legibility.

---

## 11. Relationship to existing frontend

Baseline + R2 (TASK-R2, 2026-08-21):

- `server/resources/js/tokens/` — implemented token modules: `colors.ts`,
  `spacing.ts`, `radius.ts`, `shadows.ts`, `typography.ts`, `motion.ts`,
  `zindex.ts` + `index.ts` barrel. Light and dark palettes; `primary`
  preserves the brand accent hue — deepened from `#F53003` to `#DE3005` in
  TASK-R5 so white-on-primary passes WCAG 2.2 AA (4.63:1; the old value was
  3.96:1).
- `server/resources/css/app.css` — Tailwind v4 `@theme` hydrates the semantic
  colors (`--color-*`), radius, offset shadows, and z-index tokens as CSS
  custom properties; `.dark` overrides apply the dark palette on the `.dark`
  class set by `shell/theme.ts`.
- `server/resources/js/components/` — component library v0: `KButton` (three
  variants: primary/secondary/danger + ghost) and `KInput`. Remaining surfaces
  migrate to the library in TASK-R3 (`docs/ui-audit.md` §8 duplication hunt).
  UI-consistency slice (2026-08-27) adds `KIcon` — one shared icon set
  (Heroicons outline 24px paths, MIT, see
  `docs/third-party/licenses.md`) implementing design.md §67 "one consistent
  icon set"; emoji-as-icon (🔔) and unicode pager/close glyphs are replaced,
  while the §39 milestone glyph map stays sanctioned.
- `server/resources/js/diagnostics/` + `offline/diagnostics.ts` — dev-only
  runtime diagnostics (design.md §78) rendered by `DiagnosticsPanel` under
  `import.meta.env.DEV`; production builds drop it (design.md §36).

---

## 12. Maintenance

- Token changes are deliberate design decisions: update this document and
  `docs/design.md` together, then regenerate the token modules.
- A component that needs a value outside the token scale MUST justify it in the
  PR and either extend the scale or accept review.