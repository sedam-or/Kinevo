# Kinevo — Design System (Product)

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Canonical design authority for the
> Kinevo application. Consolidates and supersedes: `docs/design.md` (archived →
> `docs/archive/design-legacy-2026-08-31/`), `docs/design-tokens.md`,
> `docs/brand.md`. Product language: **Kinevo Tactile Editorial** — calm,
> focused, tactile, precise, high-information (Swiss grid discipline + refined
> neo-brutalist tactility + technical information design). Marketing language
> (Editorial Constructivism) is a separate authority: `docs/marketing/site-specification.md`.
> Token truth = implemented code (`server/resources/js/tokens/`,
> `server/resources/css/app.css`); the Stitch `design_constitution` export is
> directional evidence only (its Inter/#000 palette does NOT override implemented
> values). Companion docs: interaction-states, content-design, motion,
> accessibility (same directory). Intensity per surface (directional): Today
> low-medium · Week/Month/Goals medium · Tasks low-medium · Knowledge/Canvas/
> Settings low. Never make routine product screens look like posters.

## 1. Visual principles

1. **Content is the interface** — surfaces are open structures, not boxes; boxes
   only where a boundary carries meaning.
2. **Tactile honesty** — hard offset shadows, near-square radii, visible focus;
   the UI feels physical, not glassy. No gradients-as-decoration, no glassmorphism,
   no card soup, no 3D gimmicks.
3. **Information first** — every chart carries period+unit+legend (ChartMeta);
   every number is tabular where alignment matters.
4. **Hierarchy: NOW → NEXT → Timeline → context** on execution surfaces.
5. **State is never color-only** — every visual state carries a glyph + label.

## 2. Color (implemented tokens — single truth)

CSS variables `--color-*` in `app.css` (semantic), mirrored in `tokens/colors.ts`:

| Role | Light | Dark |
|---|---|---|
| bg | `#FDFDFC` | `#0a0a0a` |
| surface | `#EDEDEC` | `#131313` |
| surface-raised | `#FFFFFF` | `#1b1b18` |
| primary (+contrast) | `#F53003` / `#FFFFFF` | `#F53003` / `#FFFFFF` |
| success | `#157A45` | `#3BB573` |
| warning | `#8A5A00` | `#E8A13A` |
| danger (+contrast) | `#D20812` / `#FFFFFF` | `#FF5A4E` / `#1D0002` |
| tints | success/info/warning tint tokens | — |

Rules: semantic roles only (never raw hex in components); dark/light via
`html.dark` + `color-scheme`; states pair color with glyph+label; tints for
backgrounds, full tones for text/strokes.

## 3. Typography

- **Family:** Instrument Sans (`--font-sans`) — not Inter (Stitch mock divergence
  recorded in `stitch-convergence-matrix.md`). Mono (labels/metadata/numbers):
  Tailwind default mono stack (`font-mono` utility).
- **Scale (implemented baseline):** display/titles bold tracking-tight; body
  text-sm; metadata `text-xs`/`text-[11px]` uppercase mono tracking-widest.
- **Roles:** Display (surface heroes, e.g. NOW title text-2xl bold) · Headline
  (section titles text-sm font-bold) · Body (text-sm) · Label mono (kickers,
  table heads, metadata) · Editorial numbers (tabular-nums).
- Line-height relaxed for body; tight for display.

## 4. Spacing, grid, layout

- Spacing scale: Tailwind 4-point rhythm (gap-1/2/3/4/6/8); surface padding
  p-4/p-5; page gutters px-3 sm:px-4.
- Grid: single-column flow max-w-3xl (workspace surfaces); card grids
  `grid-cols-1 sm:grid-cols-2 xl:grid-cols-3`; timeline canvas absolute-positioned
  (Today); side navigation w-56 desktop, bottom-bar + More drawer mobile
  (`MOBILE_PRIMARY_KEYS`).
- Density: comfortable lists (py-3/py-4 rows); forms `min-h-[44px]` touch targets.

## 5. Border, radius, shadow, z-index (implemented)

- Border: `border-border` (+alpha variants `/20`, `/30`, `/40`); dashed borders
  mark empty/quiet states; `border-l-4` accent marks actionable banners.
- Radius: `--radius-none 0 · sm 4px · md 8px · lg 12px` — near-square.
- Shadow (hard offset, tactile): `--shadow-rest: 4px 4px 0 currentColor` ·
  `--shadow-hover: 6px 6px 0` · `--shadow-active: 2px 2px 0`. Surface heroes use
  thick border + offset shadow at rest; **never animate on hover** (design rule).
- Z-index tokens: sticky 100 · dropdown 200 · popover 300 · drawer 400 · modal 500.

## 6. Icons & imagery

- Icons: KIcon inline set, 14–16px, stroke-consistent; `KLogo` mark for brand.
- Product UI: no photography, no illustration walls — imagery is a MARKETING
  language (Editorial Constructivism) only.
- Texture: paper grain belongs to marketing; product surfaces stay clean.

## 7. Navigation & structure

- Topbar: brand (wordmark hidden <sm), current-section, SyncStatusPanel,
  notifications, workspace switcher, theme toggle; wraps on small screens.
- Desktop side nav groups + mobile bottom bar (Today/Tasks/Goals/Knowledge) +
  More drawer — canonical mapping in `information-architecture.md` §2.

## 8. Forms & controls

- KInput: border, rounded-sm, `focus-visible:ring-2 ring-focus` — one focus
  language everywhere.
- KButton variants: primary (one per surface — staged-primary rule), secondary,
  ghost; disabled states explicit; destructive actions confirm when material.
- Selects native; date inputs native; labels always visible (placeholder never
  the only label).

## 9. Data display

- Tables: border-b rows, `surface-metadata`, uppercase mono headers.
- Charts: ChartMeta contract (title, period, unit, legend); axis + legend
  accessible (sr-only lists where applicable); heatmap with legend + toggleable
  accessible list.
- Badges: VisualStateBadge (glyph + label + tone).

## 10. Schedule UI patterns

- Hard Landscape blocks: solid muted blocks behind the timeline; sr-only
  descriptions; never color-only.
- Events: border-coded (primary=locked, danger=conflict) + title/time; locked
  and conflict carry badge + glyph.
- Empty slots: dashed outlines with sr-only time ranges.

## 11. Dark / light / responsive

- Both themes are first-class (no-flash inline head script; system-follow live).
- Responsive: mobile-first; topbar wraps; nav collapses to bottom bar + More;
  375px is a tested width (theme + mobile-sweep suites); full-page captures stay
  under the 32767px capture cap.

## 12. Disposition note

Superseded sources and their migration: design.md §1–§105 → this doc +
interaction-states + motion + accessibility + site-specification (useful content
migrated; roadmap-feature sections — Wrapped/pricing — moved to marketing scope);
design-tokens.md §2–§11 → §2–§5 here (its two factual errors corrected: prefix is
`--color-*`/`--radius-*`, primary is `#F53003`); brand.md §1–§6 → §2–§3 + §6 here
(Inter claim corrected to Instrument Sans).
