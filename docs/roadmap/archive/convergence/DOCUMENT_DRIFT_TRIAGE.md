> ARCHIVED 2026-08-31 (R0): drift triage superseded by the R0 rebaseline
> (docs/roadmap/rebaseline-2026-08.md). Historical evidence.

# DOCUMENT DRIFT TRIAGE — 2026-08-30

Status: ACTIVE · disposition of every item in `docs/audit/DOCUMENTATION_DRIFT_REGISTER_2026-08-30.md`.
Buckets: **FIX_NOW** (cheap, factual, prevents immediate agent confusion — perform before
stabilization starts) · **FIX_DURING_STABILIZATION** · **FIX_DURING_CONVERGENCE** ·
**FIX_DURING_PRODUCTION_HARDENING** · **HISTORICAL_ONLY** · **RESOLVED_THIS_PASS**.

Scope rule applied: this pass edited ONLY TASK.md (bounded P28 reconciliation) and created
`docs/convergence/*`. No other existing document was rewritten. SRS/architecture comprehensive
updates belong to full convergence.

## Already resolved by this reconciliation

| Drift item | Disposition | Resolution |
|---|---|---|
| 1.3 analytics implemented vs P28-009 TODO | RESOLVED_THIS_PASS | P28-009 re-audited DONE (evidence backfilled) |
| 1.5 a11y suite vs P28-012 TODO | RESOLVED_THIS_PASS | P28-012 re-audited DONE (evidence backfilled) |
| 1.4 FeatureHelp vs P28-010 TODO | PARTIALLY RESOLVED | P28-010 stays open with scoped notes (4 missing subjects) — full resolution = step 9 |
| 3.1 TASK-096/097 DONE vs effective scheduling gap | PARTIALLY RESOLVED | BLOCKER-ES-01..03 registered; TASK.md unchanged for 096/097 (full correction lands with Effective Schedule ADR) |
| 3.3 RET-007 P31/P32 ambiguity | RESOLVED_THIS_PASS | dependency cycle removed; semantics/transport split recorded |
| 4.1 code ahead of task tracking | RESOLVED_THIS_PASS | P28 status corrections + phase header count |

## FIX_NOW (next small session, before stabilization work starts)

| Drift item | Action | Why cheap/safe |
|---|---|---|
| 1.1 openapi missing `/saas/plan` | add path + response schema to `docs/api/openapi.yaml` from SaasController | factual contract sync; no behavior change |
| 2.12 `.env.example` missing `AI_MAX_*` + `MIDTRANS_*` names | append documented var names (no secrets) | onboarding confusion fix |
| 2.11 billing.md cites nonexistent "BillingDomainTest" | one-line doc correction | stale reference |
| 3.2 browser-e2e.md missing 2026-08-29 P28-006 run records | backfill run record section from TASK.md evidence | evidence recording only |

## FIX_DURING_STABILIZATION (executed with the stabilization steps)

| Drift item | Action |
|---|---|
| 2.1 offline-sync.md overpromise | rewrite to implemented contract when step 8 lands |
| 2.2 FR-29 Sync Now | step 6 decision |
| 2.3 FR-27 weekly job + lock | step 6 decision |
| 2.4 FR-25/2.5 recurrence+override semantics | rewrite scheduling-engine.md/SRS sections when steps 2-3 land |
| 5.1/5.2 history + locked producer | step 4/5 |
| 2.6 identity flows (SRS ↔ P29) | P29 execution |
| 2.10 mobile offline SQLite promise | align mobile-architecture.md with offline ADR (step 7) |
| 9.2 empty `infrastructure/scripts/{backup,restore}.sh` shadow files | delete/redirect during stabilization housekeeping (compose mounts real scripts) |
| 9.3 CI node 22 vs Dockerfile.prod node 20 | pin one version during stabilization |

## FIX_DURING_CONVERGENCE

| Drift item | Action |
|---|---|
| 1.2/6.1 nav §9 vs code (+3 SYSTEM items) | design.md §9 update or code alignment decision |
| 1.6 client offline layer undocumented | document queue contract with step 7/8 |
| 2.7 account deletion / full export docs | P30 |
| 2.9 Inertia-era flow language in AGENTS.md/README | governance-file wording pass |
| 5.3 product-analytics events taxonomy | P31/P32 (consumes P28-RET-007 semantics) |
| 6.2 Review surface definition | review reflection design (step 9) |
| 6.3 Programs/Milestones web surfaces | design decision |
| 7.1 AI credits decision | TARGET_DECISION_REQUIRED #24 → then docs+config sync |
| 7.2 paid→paid upgrade | BLOCKER-BILLING-01 decision → docs+tests |
| 8.1-8.5 workspace ambiguity | record CURRENT/TARGET semantics into architecture.md/domain-model.md from TARGET_DECISION_REGISTER #2-9 |
| 9.1 ADR-009/010/011 dangling | BLOCKER-DOC-01: reconstruct or de-reference |
| 9.5 documentation-inventory.md stale vs root planning docs | inventory refresh (full convergence) |
| 9.6/9.7 root layout + migration-loading coupling | convergence restructuring (explicitly deferred) |

## FIX_DURING_PRODUCTION_HARDENING

| Drift item | Action |
|---|---|
| 2.8 Cloudflare docs-only | edge decision at hardening |
| 7.3 ChargebackResolved unmapped | hardening slice with ops runbook |
| 7.5 retired `personal` tier sandbox evidence | annotate as historical |
| 9.4 CHANGELOG versions without git tags | first real release exercise |

## HISTORICAL_ONLY

| Drift item | Action |
|---|---|
| 7.4 ADR-013 superseded prices | correct governance behavior already (ADR preserved as history) — no action |

## Guardrail

No SRS v3 rewrite, no architecture rewrite, and no audit-artifact edits are authorized by this
triage. Each FIX_NOW item is a bounded factual edit with its own verification (doc-link check,
openapi validation, secret-scan).
