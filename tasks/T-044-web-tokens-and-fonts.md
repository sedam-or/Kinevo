---
id: T-044
title: Generate design tokens, self-hosted fonts and the contrast gate input
milestone: M1
kind: feature
status: in_progress
depends_on: [T-043]
requirements: [DS-A11Y-01, DS-A11Y-02, DS-A11Y-09, TST-10, FE-13]
branch: m1-t044-web-tokens-and-fonts
budget_lines: 360
assignee: "OpenAI Codex"
---

# T-044: Generate design tokens, self-hosted fonts and the contrast gate input

## Goal

`web/src/styles/tokens.json` is the single source of colour, type, elevation and motion tokens; a build script generates CSS variables, Tailwind theme bindings and TypeScript types, and `scripts/contrast.py` reads the same JSON.

## Context

> **§10 Token source and build** (`spec/09-design-system.md`): from M1, `web/src/styles/tokens.json` (W3C Design Tokens format) is the single source; a build script generates `tokens.css` (custom properties with `oklch()` and a hex fallback via `@supports`) and the TypeScript token types; `scripts/contrast.py` is updated to read the JSON. Changing a colour means editing the JSON and re-running the script.
> **Colour (§3)**: light and dark OKLCH tokens (`paper`, `surface`, `sunken`, `ink`, `ink-muted`, `ink-subtle`, `rule`, `rule-strong`, `accent`, `accent-ink`, `accent-subtle`, `success`, `warning`, `danger`, `info`, `focus-ring`); the current palette lives in `scripts/contrast.py` and the spec tables generated from it; every pair meets its required contrast and every colour is inside sRGB.
> **Typography (§4)**: Fraunces (display), Inter (UI), JetBrains Mono (mono), self-hosted, subset to Latin + Latin Extended, `font-display: swap`; scale `text-xs`…`display`; rem units; tabular numerals for times and counts.
> **Elevation and states (§5)**: `shadow-rest`, `shadow-raised`, `shadow-overlay`, `edge-primary`, `pressed`, `inset-field`. **Motion (§6)**: `motion-fast/base/slow` = 120/200/320 ms, easing tokens, reduced-motion rules.
> **TST-10**: `python3 scripts/contrast.py` exits 0; from M1 it reads `tokens.json`. **DS-A11Y-02**: visible 2 px focus ring, offset 2 px. **FE-13**: fonts are self-hosted; provenance entries go to `brand/ASSETS.md` (created in Phase 5; until then recorded in the Report).

## Scope

**In:**
- `tokens.json` converted from the palette in `scripts/contrast.py` (same values), plus type, space, radius, shadow, motion tokens.
- `web/scripts/build-tokens.ts` (run by `pnpm tokens`) generating `tokens.css` (light default, `[data-theme=dark]` and `prefers-color-scheme` handling with explicit override), `tokens.d.ts`, and the Tailwind 4 `@theme` mapping.
- `scripts/contrast.py` updated to read the JSON (keeping `--markdown`), with the spec tables in `09-design-system.md` §3 regenerated from it in the same commit.
- Font files (variable WOFF2 subsets) under `web/public/fonts/` with licence texts, `@font-face` rules, preload tags for the two above-the-fold faces.
- `base.css`: reset, focus ring, reduced-motion rules, skip-link style, forced-colors support.

**Out:**
- Components: T-045/T-046. Logo and brand assets: Phase 5.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/styles/tokens.json` | create | source of truth |
| `web/scripts/build-tokens.ts` | create | generator |
| `web/src/styles/tokens.css, base.css, fonts.css` | create | generated + hand-written |
| `web/public/fonts/*.woff2, FONT-LICENSES.txt` | create | self-hosted subsets |
| `scripts/contrast.py` | modify | read tokens.json |
| `spec/09-design-system.md` | modify | regenerate §3 tables only |
| `web/src/styles/tokens.test.ts` | create | token tests |

## Interfaces

```text
pnpm --dir web tokens          # writes tokens.css, tokens.d.ts
python3 scripts/contrast.py [--markdown]   # reads web/src/styles/tokens.json
```

## Business rules

1. Every colour in the JSON has an OKLCH value; the hex fallback is computed (culori), never typed.
2. A pair listed as required in the script must meet its ratio in both themes; a failing pair or an out-of-gamut colour exits 1.
3. Dark theme values come from the JSON `dark` group, not computed at runtime.
4. Generated files carry a header comment and are committed; a drift check (`pnpm tokens && git diff --exit-code`) is part of `pnpm lint`.

## Security

- Fonts load from the app origin only (CSP `font-src` is covered by `default-src 'self'`).
- No remote font CSS.

## Implementation notes

- Subsetting: use `glyphhanger`/`pyftsubset` once offline and commit the output; record the command in the Report.
- Licence files for the three families are required (SIL OFL-1.1).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** `tokens.json` **When** `contrast.py` runs **Then** exit 0, and `--markdown` output equals the tables in `09-design-system.md` §3 | `tokens::tst_10_contrast_gate_reads_json` |
| AC-2 | **Given** a token changed to a failing value **When** the script runs **Then** exit 1 naming the pair (seeded test) | `tokens::contrast_failure_is_reported` |
| AC-3 | **Given** the JSON **When** `build-tokens` runs **Then** `tokens.css` defines every token for light and dark and matches the committed file | `tokens::generated_css_matches_committed` |
| AC-4 | **Given** the CSS **When** parsed **Then** each custom property has an `oklch()` value and a hex fallback in `@supports not (color: oklch(0 0 0))` | `tokens::oklch_with_hex_fallback` |
| AC-5 | **Given** `base.css` **When** rendered in a test browser **Then** the focus ring is 2 px with 2 px offset and reduced-motion removes transforms | `tokens::ds_a11y_02_focus_ring_and_reduced_motion` |
| AC-6 | **Given** `dist` **When** scanned **Then** every font URL is same-origin and each family has its licence file | `tokens::fe_13_fonts_self_hosted_with_licences` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
just check
python3 scripts/contrast.py
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
