---
id: T-044
title: Generate design tokens, self-hosted fonts and the contrast gate input
milestone: M1
kind: feature
status: review
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

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [x] Files listed under *Files* are implemented; necessary wiring deviations and budget overage are recorded below.
- [x] Report below is filled in.

## Report

### Summary

The token JSON now generates the light/dark CSS variables, Tailwind theme bindings, TypeScript token declarations, and the contrast-gate input. The SPA loads the reset and self-hosted variable fonts, with the focus and reduced-motion rules covered by the named tests.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `tokens::tst_10_contrast_gate_reads_json` | pass |
| AC-2 | `tokens::contrast_failure_is_reported` | pass |
| AC-3 | `tokens::generated_css_matches_committed` | pass |
| AC-4 | `tokens::oklch_with_hex_fallback` | pass |
| AC-5 | `tokens::ds_a11y_02_focus_ring_and_reduced_motion` | pass |
| AC-6 | `tokens::fe_13_fonts_self_hosted_with_licences` | pass |

### Commands run

All builds and tests below were preceded by `df -h /`; every captured checkpoint was `468G` total, `342G` used, `120G` available, `75%` use. No container was started for T-044 itself; the existing `kinevo-dev-pg` PostgreSQL 17 container was reused.

| Command | Exit | Result |
|---|---:|---|
| `pnpm --dir web test --reporter=verbose` (test-first run, before implementation) | 1 | Expected red run: all 6 new T-044 acceptance tests failed because the token JSON/CSS/reset/dist assets did not exist; the 3 existing T-043 tests passed. |
| `pnpm --dir web test --reporter=verbose` (before the first web build) | 1 | 8 passed, 1 failed because `web/dist/fonts` did not yet exist. `just check` runs the web build before this suite. |
| `pnpm --dir web lint` (first run) | 1 | Biome reported 9 errors and 5 warnings: formatting/import order and Tailwind `@theme` parsing. Fixed the formatting and enabled the Tailwind CSS 4 parser in the lint command. |
| `pnpm --dir web exec biome check --write --css-parse-tailwind-directives=true .` | 0 | Formatted 4 files; retained the required `!important` reduced-motion overrides, which Biome reports as 4 warnings. |
| `pnpm --dir web tokens` | 0 | Regenerated and formatted `tokens.css` and `tokens.d.ts`. |
| `pnpm --dir web typecheck` | 0 | TypeScript check passed. |
| `pnpm --dir web lint` (final) | 0 | 22 files checked, no errors; 4 intentional `noImportantStyles` warnings on reduced-motion overrides; generated-file drift check clean. |
| `python3 scripts/contrast.py --markdown` | 0 | Generated §3 tables; zero gamut or contrast failures. |
| `just check` (without `KINEVO_TEST_DATABASE_URL`) | 100 | First four database-backed CLI tests failed with `shared PostgreSQL should create a disposable database: Unavailable`; stopped the suite. |
| `eval "$(scripts/dev-postgres.sh env)" && just check` | 0 | Full gate passed: 385 Rust tests passed, 1 skipped; 9 Vitest tests passed; spec check 0 findings in 60 files; contrast 0 failures; task index 0 problems in 132 tasks; OpenAPI drift check clean. `cargo deny` exited 0 with duplicate-package and unmatched-allowance warnings. Vite/Lightning CSS also reported the generated Tailwind `@theme` rule as unknown; the rule is retained in the CSS output. |

### Files changed

Counts below describe the implementation relative to the T-044 claim commit, before this Report and task-index update. Generated CSS/types and the lockfile are shown but excluded from the D-55 line-budget guidance.

| Path | Lines added/removed or asset size |
|---|---:|
| `scripts/contrast.py` | +33 / -20 |
| `spec/09-design-system.md` | +0 / -2 (generated §3 table spacing) |
| `spec/standards/open-source-stack.md` | +1 / -0 |
| `web/index.html` | +2 / -1 |
| `web/package.json` | +4 / -1 |
| `web/pnpm-lock.yaml` | +17 / -0 (lockfile; excluded) |
| `web/src/main.tsx` | +3 / -0 |
| `web/tsconfig.json` | +1 / -1 |
| `web/scripts/build-tokens.ts` | +266 / -0 |
| `web/src/styles/tokens.json` | +377 / -0 |
| `web/src/styles/tokens.css` | +345 / -0 (generated; excluded) |
| `web/src/styles/tokens.d.ts` | +41 / -0 (generated; excluded) |
| `web/src/styles/base.css` | +76 / -0 |
| `web/src/styles/fonts.css` | +32 / -0 |
| `web/src/styles/tokens.test.ts` | +177 / -0 |
| `web/public/fonts/FONT-LICENSES.txt` | +301 / -0 |
| `web/public/fonts/*.woff2` | 3 generated subsets: 173,760 B, 188,888 B, 50,768 B |

Font provenance is pinned in `FONT-LICENSES.txt` to `google/fonts` commit `6157dfaa640ca5738b4d1a7070fe389c3036d7fc`, with each upstream TTF path and SHA-256. The generated subset SHA-256 values are Fraunces `d6587e531ca8cb129542474be57b395c60af9df2bf695815386cfe1d41944b2c`, Inter `12b69a2d948170ae4b53d3a63e055b843fc91db0520091a36587371373a96989`, and JetBrains Mono `dcbe795e982d4d39d95e2bf5c78dac88fe4e458cdc0b10dbfdcebc1950a99239`. `pyftsubset` was run offline once per family; its exact argument strings were not retained in the continuation log, so the source and output hashes are the reproducible provenance evidence available here.

### Deviations

- Added the package scripts/dependency wiring, `web/tsconfig.json` generator include, SPA stylesheet imports and font preloads, and the `@types/culori` standards/lockfile row. These are the minimal wiring needed to run the token generator, type-check it, load the task's styles/fonts, and license the added type package; the task's Files table did not list these wiring files.
- The dark elevation section specifies darker ink-tinted shadows with lower alpha qualitatively, without exact numbers. Used low-alpha warm ink shadows and retained the chosen values in the token source for architect review.
- Source and license text exceed the 360-line guidance (about 1,300 changed lines before this report/index update, excluding generated CSS/types and the lockfile). The work is kept together because the task requires the token schema, generator, test coverage, fonts and licences as one coherent source-of-truth change; D-55 says the budget is guidance, not a blocker.
- Formatting the generated CSS/types is part of `pnpm tokens`; lint passes `--css-parse-tailwind-directives=true` so Biome can parse the required Tailwind 4 theme block.

### Spec issues noticed

- The current Vite/Lightning CSS build does not process Tailwind `@theme` directives and emits an “Unknown at rule: @theme” warning. The requested mapping remains present in generated CSS, but a Tailwind CSS 4 processor is not configured by this task; later utility-class use should verify that processor wiring is provided.
- The dark shadow guidance has no numeric values; see the implementer choice above.

### Not done / not verified

No environment-limited checks. A browser-based accessibility audit was not run; AC-5 was exercised in JSDOM, as scoped by the acceptance test. Android checks are outside T-044. The standalone web test was not rerun in isolation after `just check`; the full gate built `dist` first and passed the same 9-test Vitest suite.

### Follow-up suggestions

Verify Tailwind CSS 4 processor integration before relying on `@theme` utilities in components.

## Questions

- Q-1 (implementer, 2026-10-30): §5 describes dark-theme shadows as darker, ink-tinted and lower-alpha, but does not give exact values.
  Options: (a) keep the low-alpha warm ink values in `tokens.json`; (b) wait for numeric values from the architect.
  Recommendation: (a), because the spec defines the direction and these are presentation tokens, not operator-controlled business values.
  Resolved by implementer, pending architect review: used the values recorded in the token JSON and continued the task.
