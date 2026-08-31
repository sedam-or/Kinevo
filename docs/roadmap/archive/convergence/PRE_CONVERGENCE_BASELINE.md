> ARCHIVED 2026-08-31 (R0): convergence register COMPLETE — all registered blockers resolved
> (ES-01..05, SCHED-01, OFFLINE-01 with evidence; BLOCKER-DOC-01 resolved via ADR-009/010/011
> reconstruction). Historical status artifact — NOT current authority. Current status: TASK.md +
> docs/roadmap/active/P28-product-experience-closure.md.

# KINEVO PRE-CONVERGENCE BASELINE — 2026-08-30

Status: ACTIVE · Authority: governance record for the convergence phase ·
Source: acceptance of the 2026-08-30 repository reality audit.

## 1. Audit artifacts are FROZEN (immutable historical snapshots)

The following files are accepted as the official pre-convergence implementation baseline and
MUST NOT be edited to reflect desired future behavior:

- `docs/audit/KINEVO_IMPLEMENTATION_BASELINE_2026-08-30.md`
- `docs/audit/P28_REALITY_MATRIX_2026-08-30.md`
- `docs/audit/DOCUMENTATION_DRIFT_REGISTER_2026-08-30.md`
- `docs/audit/PRODUCTION_READINESS_GAP_2026-08-30.md`

Rule: audit snapshots are historical evidence. Future audits receive NEW dated files
(`docs/audit/*_YYYY-MM-DD.md`); corrections to reality happen in code or in living documents
(TASK.md, docs/), never by rewriting a past snapshot. Desired future state lives in
`docs/convergence/TARGET_DECISION_REGISTER.md`, clearly separated from CURRENT.

## 2. Verified baseline snapshot (STEP 0)

| Field | Verified value (2026-08-30, at reconciliation) |
|---|---|
| Branch | `main` |
| HEAD | `ce64edbc10f9cf0f96640082814b23b2044ec14e` (unchanged since audit) |
| Working tree | DIRTY — 38 modified tracked files + 6 untracked paths |
| Tags | none |

### Dirty-tree safety assessment

- All 38 modified tracked files PREDATE this reconciliation task (P28/TPI work sessions of
  2026-08-28/29: TASK.md, docs/{SRS,architecture,deployment,design,ui-audit,documentation-inventory}.md,
  docs/third-party/*, infrastructure/docker-compose.yml + entrypoint, server css/views/tokens,
  Makefile, .opencode config). They appear INTENTIONAL (they are the evidence trail the P28 DONE
  records cite: adoption matrix, p28-ux-audit.spec.ts, ui-audit §10, compose profile contract).
- Untracked inventory: `KINEVO_THIRD_PARTY_ADOPTION_INTEGRATION_AND_RETENTION_UX_SPEC.md`,
  `docs/adr/ADR-014-third-party-adoption-strategy.md`, `docs/third-party/adoption-matrix.md`,
  `server/config/api.php`, `tests/e2e/tests/p28-ux-audit.spec.ts` — all predate this task and are
  required by P28 DONE evidence; plus `docs/audit/` (created by the audit) and
  `docs/convergence/` (created by this reconciliation).
- This reconciliation pass modified ONLY: `TASK.md` (bounded P28 edits) + new files under
  `docs/audit/` and `docs/convergence/`. No other tracked file was touched.

### Checkpoint recommendation (no commit was made)

1. Do NOT auto-commit the 38 modified WIP files with this reconciliation: the AGENTS.md
   Pre-Commit Mandatory Verification Protocol (npm audit / typecheck / build / phpstan /
   composer test) cannot currently complete in the audit environment (local PHP lacks
   pdo_sqlite), and committing unrelated WIP is not authorized by repository policy.
2. A STABLE BASELINE COMMIT is safe to make once the owner runs the full verification protocol.
   Recommended: two commits — (a) the pre-existing P28/TPI WIP as-is (it is coherent,
   evidence-complete), (b) this reconciliation (TASK.md + docs/audit/ + docs/convergence/).
3. A TEMPORARY CONVERGENCE BRANCH (e.g. `convergence/baseline-2026-08-30`) is RECOMMENDED for
   the stabilization work (steps 1-12 of P28_STABILIZATION_PLAN.md), branching from the baseline
   commit, merged back after the Product Experience Baseline Gate. Not created in this pass.

## 3. P0 CORRECTNESS BLOCKER REGISTER (STEP 7)

These are product-correctness blockers established by audit evidence. NONE are implemented in
this pass. Each must be resolved (or explicitly re-decided via ADR) during stabilization.

| ID | Blocker | Evidence (frozen audit) | Resolution owner |
|---|---|---|---|
| BLOCKER-ES-01 | Recurring Hard Landscape is not expanded into effective future Today/Week/Month scheduling — a confirmed weekly KRS course never appears on later occurrence dates | `ExportScheduleIcsUseCase.php:100-114` is the ONLY expansion consumer; `ScheduleQueryService::dayView`/draft match raw timestamps; no test asserts occurrence-date visibility | **RESOLVED 2026-08-30** — ADR-015 + ES-FIX-00/01/02/03: canonical resolver consumed by Today/Week/Month/range, scheduler, rescheduler and ICS export; KRS weekly courses visible on future dates (browser JOURNEY B green) |
| BLOCKER-ES-02 | Permanent Shift is stored but does not alter effective occurrences | `schedule_overrides` written+validated; `SchedulePrecedence` zero consumers; `ScheduleQueryService` never reads overrides | **RESOLVED 2026-08-30** — ES-IMPL-04: latest-applicable-shift precedence; shifted occurrences on effective dates (browser JOURNEY C green) |
| BLOCKER-ES-03 | One-Time Exception is stored but does not alter effective occurrences | same as ES-02 (`ScheduleOverrideType.php:19`; no resolution consumer) | **RESOLVED 2026-08-30** — ES-IMPL-05: exception > shift > base; cancellation via `cancels_occurrence` (browser JOURNEY D green) |
| BLOCKER-ES-04 | Schedule history preservation contradicts current hard-delete behavior | `EloquentScheduleAssignmentRepository:147-157` hard-deletes superseded assignments; no history table; no schedule-change activity events (SRS FR-25 history postcondition) | **RESOLVED 2026-08-30** — ES-IMPL-06A/06: `schedule_assignment_history` same-transaction archive across all 5 delete paths + 5 schedule activity events + per-task timeline query (rollback + idempotency tested) |
| BLOCKER-ES-05 | Locked-task enforcement exists but no reachable producer sets `locked=true` | `ScheduleDraftController:336-337` hardcodes `isLocked:false`; `withLocked` zero callers | **RESOLVED 2026-08-30** — ES-IMPL-07: user lock/unlock endpoints + UI; lock state wired into scheduler/rescheduler input (`isLocked` no longer hardcoded); locked never auto-moved (browser LOCK journey) |
| BLOCKER-SCHED-01 | Weekly scheduling job / scheduler run lock / Sync Now contract unresolved (SRS FR-27/FR-29) | no `app/Jobs`, no `Cache::lock` in scheduling, no `/sync` route; draft is synchronous manual HTTP | **RESOLVED 2026-08-31** — ADR-016: `schedule:prepare-weekly` weekly trigger (persisted pending draft, never auto-applied, idempotent per week anchor), per-user cache run locks (weekly + sync), `POST /schedule/sync` (no_changes/proposal/run_in_progress), reality-change review state + notifications; evidence: WeeklyPrepareCommandTest/ScheduleSyncApiTest/ScheduleDraftsApiTest/ScheduleImpactTest + browser journeys S1–S4 (`make e2e-scheduler`) |
| BLOCKER-OFFLINE-01 | Client offline queue exists (IndexedDB MutationQueue, mobile `queued` states) but server operation-ledger reconciliation does not | no sync endpoints, no operation ledger migration; `KinevoApi::operationId()` unconsumed server-side | **RESOLVED 2026-08-31** — ADR-017: server `offline_operations` ledger (unique (user_id, operation_id), payload hash, bounded result), idempotent replay (response-loss safe), same-id/different-payload REUSED rejection, version-conflict semantics for task/note updates, `POST /sync/reconcile` batch endpoint, web MutationQueue wired (offline allowlist enqueues, boot+reconnect drain, aggregate UX, conflict review/discard), online X-Operation-Id convergence, auth offline-reload fix, ledger retention (90d) + prune command; evidence: OfflineReconcileApiTest (16) + offline applier/submit vitest (8+1) + browser journeys O1–O4 (`offline-reconcile.spec.ts`) + offline-sync.md rewritten to match reality. Mobile durable queue formally deferred (ADR-017 §2.19) as a MOBILE production-hardening gap — does not keep the server/web blocker open |
| BLOCKER-BILLING-01 | Paid→paid upgrade contract contradicts one-active subscription guard | docs/TASK P24-021 "upgrade = new checkout" vs `BillingService.php:62-68` `ACTIVE_SUBSCRIPTION_EXISTS` rejection; no test covers paid→paid | Billing decision (owner) + contract/test |
| BLOCKER-DOC-01 | ADR-009/010/011 referenced in ≥5 authoritative files but absent from `docs/adr/` | references in docs/SRS.md, deployment.md, browser-e2e.md, TASK.md, docker-compose.yml | Convergence doc pass (reconstruct or de-reference) |

## 4. Corrected P28 state (STEPS 2-6)

- Denominator corrected 30 → **31** items: 19 DONE + 11 TODO + 1 GATED (as audited), then
  **P28-009 and P28-012 re-audited to DONE** on acceptance evidence → current: **21 DONE ·
  9 TODO · 1 GATED**. Recorded in the PHASE 28 header.
- P28-010 re-audit: stays open (2/6 subjects covered: Goal, Analytics; Workspace/Knowledge/
  Canvas/AI-provider-modes missing — scoped in task Notes).
- RET mapping rows RET-004/009/011/012 synchronized to DONE (owner-equivalence proven).
- P28-RET-007 dependency cycle removed (semantics in P28; transport in P32-001).
- P28-014 reclassified: **Product Experience Baseline Gate** — not a production-launch gate.

## 5. Companion governance documents

- `docs/convergence/TARGET_DECISION_REGISTER.md` — CURRENT vs TARGET_LOCKED vs
  TARGET_DECISION_REQUIRED.
- `docs/convergence/P28_STABILIZATION_PLAN.md` — 12-step execution order, dependencies, and
  golden-journey preconditions (A-F).
- `docs/convergence/DOCUMENT_DRIFT_TRIAGE.md` — per-item disposition of the drift register.
