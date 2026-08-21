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

Current baseline (pre-R2):

- `server/resources/css/app.css` — Tailwind v4 entry point.
- `server/resources/js/shell/theme.ts` — `Light | Dark | System` persistence.
- `server/resources/js/visualstate/VisualStateBadge.vue` — non-color state vehicle.

R2 converts these to the token system defined here without changing component
behavior; component behavior changes follow `docs/design.md` §97 ordering.

---

## 12. Maintenance

- Token changes are deliberate design decisions: update this document and
  `docs/design.md` together, then regenerate the token modules.
- A component that needs a value outside the token scale MUST justify it in the
  PR and either extend the scale or accept review.