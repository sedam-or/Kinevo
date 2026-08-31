# Kinevo — Asset Provenance Inventory

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Provenance ledger for design assets
> from the Stitch export. Complements `docs/third-party/licenses.md` (which
> governs third-party CODE). Rule: nothing enters the repository without a row
> here marked SAFE_TO_COMMIT + a license/provenance entry.

## 1. Source & blanket classification

Source: `stitch_kinevo_personal_operating_system/` (LOCAL_REFERENCE_ONLY,
gitignored). 124 PNG (~120 MiB) + 60 HTML + 2 md/txt. **No license, README, or
provenance files exist in the export; no font binaries exist on disk** (HTML
loads Inter/JetBrains Mono/Material Symbols from Google Fonts CDN).

Blanket verdicts:
- **PNG imagery (all):** PROVENANCE_REQUIRED — generated via an AI design tool;
  the export carries no license grant. Do NOT commit any PNG in P29.
- **HTML mockups (60):** REFERENCE_ONLY — hand-authored static mockups (Tailwind
  Play CDN); design evidence, never shipped.
- **Fonts:** none embedded ✓ (and Google Fonts OFL families are the candidates
  if/when marketing self-hosts type — re-check license at that time; never
  commit font files merely because the export references them).

## 2. Family inventory

| Family | Items (dirs) | Purpose | Format | Provenance | Production suitability | Target repo / phase | Status |
|---|---|---|---|---|---|---|---|
| Logo & icons | logo, favicon…, pwa_icon… | brand marks | PNG (large rasters) | generated, no grant | needs re-export/vectorization | kinevo-site / P31+ | PROVENANCE_REQUIRED |
| Grid system assets | kinevo_asset_b01–b04, brand_asset_axial/radial/timeline grids | Editorial Constructivism language | PNG | generated | reference for regeneration | kinevo-site / P31+ | PROVENANCE_REQUIRED |
| Photography direction | c01/c02/c04, brand_asset_photographic_direction | monochrome people-at-work imagery | PNG | generated (AI "photo") | marketing only; **do not present as real photography** | kinevo-site / P31+ | PROVENANCE_REQUIRED |
| Texture | brand_asset paper grain, print overlays | marketing backgrounds | PNG | generated | marketing only | kinevo-site / P31+ | PROVENANCE_REQUIRED |
| Motifs | e01 movement lines, e02 progress arcs, progress motif | brand motifs | PNG | generated | regenerable as SVG | kinevo-site / P31+ | PROVENANCE_REQUIRED |
| Canonical diagrams | d01_d08 loop, d02 goal radial, d03_d04 hard landscape/import, d07 knowledge network | product-model illustrations for docs/site | PNG | generated | regenerate as SVG at production resolution | docs/site / P31+ | PROVENANCE_REQUIRED |
| Landing diagrams | dia_01–07 (intention→execution, decomposition, hard landscape, import, override, knowledge, retention) | site sections | PNG | generated | regenerate as SVG | kinevo-site / P31+ | PROVENANCE_REQUIRED |
| Documentation/site support | documentation_header_asset, technical_background_asset | docs/site chrome | PNG | generated | optional | kinevo-site / P31+ | PROVENANCE_REQUIRED |
| System identity | loading_identity, maintenance_graphic, status_page_identity, 404_error_graphic | future system/error states | PNG | generated | product-adjacent; P35 candidates | infra / P35 | PROVENANCE_REQUIRED |
| Social/store campaign | mk01–mk12 set, github/linkedin/x/instagram visuals, 3 Play Store screenshots, social + github previews | launch campaign, store listing | PNG | generated | campaign-ready AFTER claims clearance | kinevo-site / P36 (store) | PROVENANCE_REQUIRED + claims-registry gate |
| Wrapped campaign | kinevo_wrapped_campaign_mk12, goal_story_kinevo_wrapped | Wrapped direction | PNG/HTML | generated | deferred concept | P32+ decision | MARKETING_ONLY |
| HTML mockups (all) | 60 product/marketing/design screens | design evidence | HTML | hand-authored mockups | none (never shipped) | — | REFERENCE_ONLY |
| Motion experiments | motion_system_showcase, animated_svg_1–3, three.js | motion exploration | HTML | hand-authored | ideas only | motion.md | EXPERIMENTAL |

## 3. Disposition

- **Blanket:** the raw export remains LOCAL_REFERENCE_ONLY (gitignored, P29 §57).
- **SAFE_TO_COMMIT today:** none of the binary assets (provenance absent).
  Documentation-embedded diagrams may be REGENERATED as original SVG artwork by
  Kinevo (then SAFE_TO_COMMIT as Kinevo-original) when docs/site need them.
- **Regenerate candidates (high value):** canonical diagrams D01–D08 + landing
  dia_01–07 (they illustrate the canonical model) — recreate as original SVGs in
  P31.
- **Prohibited forever:** committing font binaries from third parties without
  license verification; presenting generated photography as real photography;
  shipping any asset whose claim content violates `claims-registry.md`.
