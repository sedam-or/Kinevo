# Kinevo — Stitch Reference

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Records the Stitch design-source
> location, access policy, and authority rule. Full inventory + classification:
> `stitch-convergence-matrix.md` (same directory).

## 1. Source

- **Local export:** `stitch_kinevo_personal_operating_system/` at the repository
  root — 129 screen/asset dirs + 2 root files (`kinevo_marketing_copy_constitution.md`,
  `kinevo_android_aso_metadata.txt`), 124 PNG + 60 HTML, ~121 MiB.
- **Git status:** untracked and **gitignored** — LOCAL_REFERENCE_ONLY (P29 §57).
  Never bulk-committed: no provenance files, generated imagery of unclear
  licensing, size inappropriate for the MIT repo.

## 2. Stitch MCP

Optional. The complete local export exists, so P29/P30 work MUST prefer it. MCP
may be connected (READ-ONLY) later only if a specific export artifact proves
incomplete or project metadata is materially needed. **No project ID is recorded
in this repository** — if MCP is ever connected, the owner supplies the project
ID out-of-band; API credentials are never committed.

## 3. Authority rule

Stitch frames are **visual reference and design evidence** — never product,
domain, business, pricing, security, or API authority. When a frame contradicts
canonical documentation, the repository documentation wins and the conflict is
recorded in `stitch-convergence-matrix.md` (STITCH_OUTDATED), not silently
adopted. Claim-bearing marketing frames require clearance through
`docs/marketing/claims-registry.md` before any copy or asset is reused.

## 4. Timing rule (permanent)

Stitch may inform IA/design/marketing work only AFTER canonical product truth
exists (Constitution → SRS → domain → architecture → IA first). This sequencing
prevented old pricing/quotas/navigation and unsupported security claims from
silently becoming requirements during P29 — the same rule binds future phases.
