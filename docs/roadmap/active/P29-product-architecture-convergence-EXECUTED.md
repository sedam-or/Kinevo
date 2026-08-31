# P29 — Product & Architecture Convergence (ACTIVE — EXECUTED 2026-08-31)

> Detailed authority for P29 execution. TASK.md links here. Execution authority:
> `docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md` (STOP after P29; no auto-P30).
> Owner epic: "P29 PRODUCT & ARCHITECTURE CONVERGENCE — authoritative
> source-of-truth reconstruction including full Stitch export design convergence"
> (2026-08-31). Feature implementation: FORBIDDEN (tiny factual corrections only
> — none required; the convergence found no code change that would have made the
> new canonical documentation false).

## Result summary

ONE canonical authority per truth type, established by reconstruction (not
patching), with the complete local Stitch export reconciled as design evidence.

## Deliverables (created / rewritten / migrated / archived)

**Created (canonical):**
- `docs/product/product-constitution.md` — highest product authority (identity, problem, user, core loop, user authority, data ownership, AI role, Core/Cloud, non-goals, principles, North Star, migrated register decisions)
- `docs/product/vision-mission.md` — vision/mission/ICP/jobs/differentiation/anti-positioning/brand promise/activation
- `docs/product/product-model.md` — loop entities: why/owns/does-not-own/loop role
- `docs/product/workspace-model.md` — locked workspace semantics (register #2–#9 migrated; CURRENT vs TARGET filter wiring explicit)
- `docs/product/commercial-model.md` — business model, plans (launch hypotheses), AI commercial boundary, separated billing concepts, open decisions (P33)
- `docs/SRS.md` **v3.0.0** — in-place reconstruction: 69 FR + 15 NFR classified (44 UNCHANGED · 17 REFINED · 1 SUPERSEDED · 0 DEPRECATED) + 9 NEW (FR-70…75 Workspace; FR-71b…74b billing/entitlements); LWW §9.4 conflict fixed; IA updated; CURRENT/TARGET/DEFERRED labels; migration table
- `docs/requirements/requirements-traceability.md` — 10 core flows fully chained (requirement→domain→API→UX→evidence→gap)
- `docs/domain-model.md` — full rewrite: 13 domains, Workspace first-class, all 9 previously-missing concepts (HardLandscapeOccurrence, EffectiveLandscapeResolver, ScheduleDraft, schedule_assignment_history, OfflineOperation, ScheduleState, AIProposal, Subscription, Entitlement)
- `docs/architecture.md` — full rewrite: CURRENT/TARGET/MIGRATION_REQUIRED on every element; provider boundary table (AI/Email/Assets/Analytics/AIObservability/Notification/Billing); runtime split; third-party CURRENT vs TARGET; invariants
- `docs/ux/information-architecture.md` — canonical NOW/BUILD/THINK/REFLECT model; 16-surface classification; settled decisions (Calendar→Month, Analytics→Progress = TARGET renames; Review surface = TARGET; Import & Sync lives in Schedule)
- `docs/ux/stitch-reference.md` + `docs/ux/stitch-convergence-matrix.md` — full 131-unit inventory + classification (55 approved/refined · 30 marketing-only · 46 asset-only · 5 experimental · claim-level REJECTs recorded); raw export = LOCAL_REFERENCE_ONLY
- `docs/ux/design-system.md`, `interaction-states.md`, `content-design.md`, `motion.md`, `accessibility.md` — canonical design set (implemented tokens = truth; mock palette/font divergence recorded)
- `docs/marketing/site-specification.md`, `claims-registry.md` (25 claims: allowed/prohibited/deferred), `asset-provenance.md` (blanket PROVENANCE_REQUIRED; nothing committed)

**Rewritten in place (narrow):** `mobile-architecture.md` (3 stale Inertia refs + ADR mis-cite fixed, status header), `knowledge-layer.md` (base_version/409 contract + asset-pipeline linkage), `ai-architecture.md`/`scheduling-engine.md` (status headers), `billing.md` (commercial-model pointer)

**Migrated then archived:** `convergence/TARGET_DECISION_REGISTER.md` (25 decisions → product-constitution §12, workspace-model, commercial-model, architecture) → `docs/archive/TARGET_DECISION_REGISTER-2026-08-31.md`

**Archived (evidence, not authority):** SRS v2.0.0 (`archive/srs-v2.0.0-2026-08-31.md`), design.md + design-tokens.md + brand.md + state-machine-ui.md + ui-audit.md (`archive/design-legacy-2026-08-31/` + README), implementation-status.md, hardening-evidence.md, OFFLINE_CAPABILITY_MATRIX, P28 phase doc (`roadmap/archive/phases/…DONE-2026-08-31.md`)

**Updated:** `docs/README.md` (final authority index), root `README.md` (docs list), `AGENTS.md` (hierarchy: product constitution first; bridge-closed note; freeze-lifted note), `CONTRIBUTING.md`, `docs/browser-e2e.md` (authority pointers), ADR-008 (Inertia amendment), P30–P39 planned docs (P29 refinement notes)

## Stitch authority outcome

Stitch = design evidence only. Nav "Today·Week·Month" mocks REINFORCE the IA
decision; Rp price hypotheses matched; but USD billing mock, zero-knowledge/E2EE
claims, Rust claim, biometric privacy framing, and fabricated entitlements were
REJECTED at claim level and gated via the claims registry. No Stitch content
became a requirement.

## Known dispositions intentionally NOT taken

- Nav label renames (Calendar→Month, Analytics→Progress) and the dedicated
  Review surface are DECIDED but left as TARGET implementation tasks (P29 is not
  a feature phase) — recorded in `docs/ux/information-architecture.md` §2/§3.
- No asset binaries committed (provenance absent) — regeneration path in P31.

## Gate

See TASK.md §Active gate for the P29 gate checklist result.
