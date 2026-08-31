# P28 STABILIZATION PLAN — 2026-08-30

Status: ACTIVE execution order for post-reconciliation work. This plan DEFINES order and
preconditions; it does not implement anything itself.

2026-08-31 — Master Execution Prompt V3 (`KINEVO_MASTER_EXECUTION_PROMPT_V3.md`) authorizes the
P28 closure phase and re-confirms this plan's remaining steps (9–12) with explicit additions:
known browser-failure triage BEFORE final P28-013 evidence (V3 §27), RET-007 semantic taxonomy
within P28 with provider transport deferred to P32 (V3 §26), golden-journey regression legs for
scheduler Sync Now / weekly draft / locked task / offline reconnect+replay+conflict (V3 §28),
and the 3-engine browser matrix (V3 §29). Stitch is NOT implementation authority in P28 (V3 §20).

Entry condition: baseline accepted (docs/convergence/PRE_CONVERGENCE_BASELINE.md), P28 tracking
reconciled (TASK.md phase 28 header: 21 DONE · 9 TODO · 1 GATED).

## Execution order and dependencies

| # | Step | Type | Depends on | Exit criterion |
|---|---|---|---|---|
| 1 | **Effective Schedule ADR** | ADR (decision) | — | **DONE 2026-08-30** — `docs/adr/ADR-015-effective-schedule-resolution-and-override-precedence.md`. Steps 2–5 are decomposed into ADR-015 slices ES-IMPL-01..08 |
| 2 | **Recurrence expansion** | implementation | 1 | **DONE 2026-08-30** — ES-FIX-00 (UNTIL enforced canonically incl. UTC `Z` + COUNT-from-DTSTART normalization) + ES-IMPL-01/02: recurring Hard Landscape appears on occurrence dates in Today/Week/Month/ICS; BLOCKER-ES-01 resolved (evidence: TASK.md ES entries + browser JOURNEY B) |
| 3 | **Override resolution** | implementation | 1, 2 | **DONE 2026-08-30** — Permanent Shift + One-Time Exception (incl. `cancels_occurrence`) alter the effective schedule per ADR precedence; cross-source effective collisions 409; scheduler respects (resolves BLOCKER-ES-02/03; browser JOURNEYS C/D) |
| 4 | **Schedule history** | implementation | 1 | **DONE 2026-08-30** — ES-IMPL-06A/06: `schedule_assignment_history` archive in the same transaction as every supersede/delete (5 mutation paths), 5 new schedule activity events, per-task timeline query (resolves BLOCKER-ES-04) |
| 5 | **Locked-task reachability** | implementation | 1 | **DONE 2026-08-30** — ES-IMPL-07: user lock/unlock endpoints + UI toggle/badge + `isLocked` wired into scheduler/rescheduler input; locked placements never auto-move (resolves BLOCKER-ES-05; browser LOCK journey) |
| 6 | **Scheduler trigger / Sync Now decision** | ADR + decision record | 2-5 landed (or explicitly descoped by ADR) | **DONE 2026-08-31** — ADR-016 recorded AND implemented: weekly `schedule:prepare-weekly` (persisted draft, never auto-applies), per-user cache run locks, `POST /schedule/sync`, reality-change review state, draft lifecycle (pending/applied/discarded/superseded), Sacred Anchor producer (scheduling slice); weekly + sync + impact + lifecycle tests; browser journeys S1–S4 (resolves BLOCKER-SCHED-01) |
| 7 | **Offline reconciliation ADR** | ADR (decision) | — | **DONE 2026-08-31** — ADR-017 recorded AND implemented: server `offline_operations` ledger, idempotent replay, conflict semantics, `/sync/reconcile`, web MutationQueue integration, retention, online convergence; server + client + browser evidence; BLOCKER-OFFLINE-01 resolved (web/server); mobile durable queue formally deferred to Android hardening (ADR-017 §2.19) |
| 8 | **Offline server ledger/reconciliation** | implementation | 7 | **DONE 2026-08-31** — implemented in the same slice as step 7 (ADR-017): `offline_operations` ledger (unique (user_id, operation_id) + payload hash), `POST /sync/reconcile` (batch ≤50, transaction-per-op), idempotent replay / REUSED rejection, task/note version conflicts, online X-Operation-Id convergence, web MutationQueue drain (boot + reconnect); evidence: OfflineReconcileApiTest (16) + browser journeys O1–O4 (resolves BLOCKER-OFFLINE-01) |
| 9 | **Remaining P28 UX tasks** | implementation | — (parallel track; P28-013 evidence waits on 2-4) | P28-010 (4 missing education subjects), P28-011 (state matrix), RET-002/005/006/008 semantic+UX work, RET-007 first-week retention SEMANTIC taxonomy (provider transport/instrumentation deferred to P32 per V3 §26) |
| 10 | **Browser Golden Journeys (P28-013)** | E2E evidence | 2, 3, 4, 5, 9 core loop legs | journeys A-F (below) recorded on chromium+firefox+webkit; recorded under P28-013 |
| 11 | **Retention Failure E2E (P28-RET-013)** | E2E evidence | 9 (RET-002/005), P28-006 | `tests/e2e/tests/retention-failures.spec.ts` green (chromium minimum) |
| 12 | **Product Experience Baseline Gate (P28-014)** | gate | ALL above + all P28 items | gate checklist green with compiled browser evidence; entry into convergence phase |

Dependency rationale:
- Steps 2-4 form the "effective schedule" spine: expansion must exist before override
  resolution (overrides target occurrences), history must exist before either can rewrite
  placements safely, and locked-task reachability must be decided in the same ADR to avoid a
  second scheduling-semantic change later.
- Step 6 depends on 2-5 because trigger semantics (what a weekly job would recompute) only make
  sense over the corrected effective-schedule model.
- Step 8 is independent of 2-6 (different subsystem) but its ADR (7) must not conflict with the
  schedule version/conflict model already implemented.
- Step 9 is parallelizable immediately (pure UX/documentation track) EXCEPT that journey-style
  acceptance evidence for schedule-adjacent surfaces stays provisional until 2-5 land.
- Steps 10-12 are evidence gates and must be last: golden journeys are only meaningful once the
  effective schedule is correct (see §Journey preconditions).

## Golden journey preconditions (STEP 10)

**P28-013 must NOT be treated as final evidence while BLOCKER-ES-01..05 remain open.**
Journey B/C/D are direct validations of those blockers and cannot pass meaningfully before
steps 2-5. Recorded results before that point are provisional diagnostics only.

- **JOURNEY A — Goal-first**: signup → workspace → goal → AI breakdown → approve → schedule →
  Today → complete → progress. (Valid: current engine; re-run after 2-5 for final evidence.)
- **JOURNEY B — Reality-first**: signup → workspace → KRS import → parse → review → confirm →
  recurring Hard Landscape → **future Week/Today visibility** → schedule work around it.
  (Requires step 2; this journey is the acceptance test for BLOCKER-ES-01.)
- **JOURNEY C — Permanent Shift**: Hard Landscape occurrence → Permanent Shift → preview impact →
  apply → future effective schedule changes. (Requires step 3; acceptance test for BLOCKER-ES-02.)
- **JOURNEY D — One-Time Exception**: single occurrence → exception → preview → apply → only the
  target occurrence changes. (Requires step 3; acceptance test for BLOCKER-ES-03.)
- **JOURNEY E — Knowledge continuity**: Task → Note → Canvas/Goal context. (Valid now; part of
  P28-013 D/E legs.)
- **JOURNEY F — Reflection**: complete work → Progress → Review → next action. (Partially valid;
  review reflection surface completion lands with step 9.)

Engine coverage: chromium + firefox + webkit (playwright projects already configured), evidence
recorded under P28-013 in TASK.md and (as runs happen) docs/browser-e2e.md.

## Explicit non-goals of this plan document

No implementation is performed by this document. Steps are executed one bounded slice at a time
per AGENTS.md (tests + static analysis + doc sync each slice). Runtime migration (FrankenPHP/
Octane) is NOT part of steps 1-12 and follows TARGET_DECISION_REGISTER #18/#19 separately.
