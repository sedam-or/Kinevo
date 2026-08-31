> Planned phase document (R0 rebaseline 2026-08-31). Authority: docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.
> Detailed microtasks are authored at phase activation — never fabricated in advance.

# P36 — Android Production
Status: PLANNED · Depends On: P35 gate

## Objective
Release-grade Android companion: same account, same entitlement, same backend/domain authority; web-first billing acceptable for v1; NO duplicate mobile backend.

## Scope / major workstreams
- Durable mobile offline queue using the EXISTING ADR-017 protocol (envelope + ledger + /sync/reconcile) — durable persistence, replay, conflict, cross-account safety, offline capture, rehydration. NEVER a second sync protocol.
- Companion flows: Today, Capture, Tasks, Goals, Workspace, Hard Landscape visibility, Import where suitable, offline, notifications, assets, entitlement, deep links, review/progress essentials. Canvas full authoring may remain desktop/tablet.
- Mobile security: remove plaintext token storage (secure storage); verify logout cleanup, account switching, token revocation, backup exclusion, deep-link authorization.
- Delivery: repeatable CI build, signed release process, versioning, artifact verification, release notes, crash monitoring, device matrix. No manual PHP-version lock hacks.

## Gate
Secure auth; durable offline; core companion flows; assets; notifications; entitlements; device accessibility; CI build; release pipeline; failure handling; docs. STOP.

## Known open decisions
iOS timing (explicitly deferred); device matrix floor.

## P29 convergence refinements (2026-08-31)
- Durable mobile offline MUST reuse the ADR-017 protocol (no second protocol) — `docs/architecture.md` §5.
- Visual direction: Stitch mobile frames (APPROVED_WITH_REFINEMENT, `docs/ux/stitch-convergence-matrix.md` §4); locked mobile IA (`docs/mobile-architecture.md`) wins over mockups.
- Store-listing source: `kinevo_android_aso_metadata.txt` (local export) + claims registry.
