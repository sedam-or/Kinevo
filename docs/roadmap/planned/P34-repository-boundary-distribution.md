> Planned phase document (R0 rebaseline 2026-08-31). Authority: docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.
> Detailed microtasks are authored at phase activation — never fabricated in advance.

# P34 — Repository Boundary & Distribution
Status: PLANNED · Depends On: P33 gate · FIRST repository split happens HERE (not before)

## Objective
Split into: PUBLIC sedam-or/Kinevo (Core, MIT, genuinely self-hostable) · PRIVATE kinevo-cloud · PUBLIC kinevo-site.

## Scope / major workstreams
- Split safety: freeze → inventory ownership → classify files → dependency analysis → destination mapping → copy/migrate → build/test destinations → verify boundaries → compare functionality → update docs → migration notes → ONLY THEN remove from original. Pre-split tag; temporary split branches allowed; no long-lived Core/Cloud/Site branch model.
- Core must remain genuinely usable/self-hostable (never hollowed to force Cloud adoption). Cloud value: managed hosting/AI/sync/storage/backup/operations/commerce.
- kinevo-site implemented from the P29 marketing/site/design spec + approved Stitch frames; no unsupported marketing claims (claims registry).

## Non-goals
No feature development inside the split; no license boundary violations (AGPL internals never copied into MIT Core).

## Gate
Three boundaries coherent; Core/Cloud/Site all build; license boundaries valid; secret separation; CI works; docs links updated; installation works; functional comparison complete. STOP.

## Known open decisions
Cloud secret/config distribution mechanism; site stack choice.

## P29 convergence refinements (2026-08-31)
- Core/Cloud boundary restated in `docs/architecture.md` §13; Core stays MIT (Constitution §8). Site spec: `docs/marketing/site-specification.md`.
