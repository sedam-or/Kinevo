# P28 — Product Experience Closure (ACTIVE)

> Detailed authority for P28 task execution. TASK.md links here. Status vocabulary per TASK.md.
> Re-audited 2026-08-31 (R0): **21 DONE · 9 TODO · 1 GATED**. Execution authority:
> `docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md` (STOP after P28-014; no auto-P29).

## Objective

Fix the fundamental experience problem — Kinevo may be technically powerful while still feeling
fragmented, unintuitive, or difficult to understand. P28 is a product-quality gate, NOT cosmetic
polishing; no major new surface before the existing experience is proven understandable.

## Completed P28 tasks (evidence preserved)

| Task | Title | Status | Evidence |
|---|---|---|---|
| P28-001 | Full UX Inventory | DONE | docs/ui-audit.md §10.1; p28-ux-audit.spec.ts (33 green, chromium/firefox/webkit) |
| P28-002 | Empty State Audit | DONE | ui-audit §10.2; empty-state browser tests (3 engines) |
| P28-003 | Personalization Audit | DONE | ui-audit §10.3; personalization tests |
| P28-004 | Information Architecture | DONE | ui-audit §10.4; nav walk 13/13 + nav-group contract |
| P28-005 | CTA Hierarchy | DONE | ui-audit §10.5; create-surface CTA tests |
| P28-006 | Cross-Feature Workflow Audit | DONE | ui-audit §10.6; workflow matrix 11/11 reachable |
| P28-007 | Micro-Interaction System | DONE | design-tokens.md §motion; motion scale + tactile feedback |
| P28-008 | Design System Audit | DONE | ui-audit §10.7; 9 token areas audited |
| P28-009 | Analytics Meaning | DONE | ChartMeta.vue (period+unit+legend per chart) |
| P28-012 | Accessibility Audit | DONE | accessibility.spec.ts — axe-core wcag2a+2aa ZERO violations |
| P28-TPI-000..010 | Third-party adoption foundation (matrix, licenses, modes, ownership, adapters, budgets, profiles, exit strategy, UX + retention continuity audits) | DONE (11) | docs/third-party/*; ADR-014; architecture.md §Third-Party |

## Completed stabilization epics related to P28

| Epic | Content | Evidence |
|---|---|---|
| ADR-015 (ES-FIX-00, ES-IMPL-01..08) | Effective Schedule: canonical recurrence + UNTIL, effective landscape in Today/Week/Month/scheduler/ICS, Permanent Shift, One-Time Exception, schedule_assignment_history, locked-task producer, regression | TASK.md ES records (archive/task-legacy); browser JOURNEY B/C/D/LOCK 4/4 |
| ADR-016 (SCHED-01) | Weekly `schedule:prepare-weekly`, `POST /schedule/sync`, reality-change review, run locks, draft lifecycle, Sacred Anchor producer | WeeklyPrepareCommandTest etc.; browser S1–S4 (`make e2e-scheduler`) |
| ADR-017 (OFFLINE-01) | `offline_operations` ledger, `POST /sync/reconcile`, idempotent replay, conflicts, web MutationQueue drain + conflict UX, retention | OfflineReconcileApiTest (16); browser O1–O4 (`tests/e2e/tests/offline-reconcile.spec.ts`) |
| Commercial delta (D-001..D-008) | Pricing config/tests/UX, AI cost simulator, budget firewall, unit economics, regression evidence, final gate — all DONE 2026-08-28 | archive/task-legacy/commercial-pricing-delta-post-p27.md |

## Open P28 tasks (execution order)

| Order | Task | Title | Priority | Depends On | Scope notes |
|---|---|---|---|---|---|
| 1 | P28-010 | Feature Explanation Layer | P2 | P28-002 | Missing subjects: **Workspace, Knowledge, Canvas, AI provider modes**. Contextual education (what is this? why care? what next?); NO intrusive tours; no duplication of Goal/Analytics explanations |
| 2 | P28-011 | Global State Matrix | P1 | P28-001 | Canonical user-facing state matrix: loading/empty/success/partial/offline/stale/conflict/failed/entitlement-blocked per meaningful surface; document required behavior + implement missing high-value states; no mechanical Cartesian build |
| 3 | RET-002 (P28-RET-002) | Contextual Empty States refinement | P2 | P28-002 | Copy already answers the 4 questions partially — deepen contextual guidance |
| 4 | RET-005 | AI Breakdown Aha Moment | P1 | P28-006 | Golden-journey G2 exists; verify continuation CTA leg |
| 5 | RET-006 | First Session Journey | P1 | RET-005 | No forced tutorial; empty states + FeatureHelp are the vehicle |
| 6 | RET-007 | First Week Retention Events | P2 | — | **SEMANTIC taxonomy only in P28**; provider transport/instrumentation in P32 (no dependency cycle) |
| 7 | RET-008 | Progress Feedback System | P1 | P28-007 | Base cascade exists (P17-011); adds the feedback layer |
| 8 | RET-013 | Retention Failure E2E | P1 | RET-002, RET-005, P28-006 | Failure journeys: AI unavailable, network failure, offline queued mutation, offline conflict, stale schedule proposal, no scheduling slot, empty first-session; failures understandable + recoverable. Feeds P28-014 |
| 9 | P28-013 | Browser Golden Journeys | P0 | P28-002/004/005 + steps 1–8 core legs | Journeys A–F (below) + regression legs; **Chromium + Firefox + WebKit** with command/engine/pass/fail/skip/duration/environment recorded |
| 10 | P28-014 | Product Experience Baseline Gate | P0 (GATED) | ALL P28 tasks | Gate checklist below |

## Known browser failures to triage BEFORE final P28-013 evidence

Last full run (docs/browser-e2e.md): **98 passed / 3 failed (14.4m)**:
1. `scheduler` S1 — needs clean+seeded sandbox (`make e2e-scheduler`); ENVIRONMENT/harness
   prerequisite, documented.
2. `p28-ux-audit` goals empty state — classify REAL PRODUCT REGRESSION vs TEST DEFECT; fix the
   correct layer.
3. `theme` mobile 375px — classify; fix legitimate defects.
Never delete meaningful assertions to get green (test policy).

## P28-013 golden journeys

- **A Goal-first**: signup → workspace → goal → AI breakdown → approve → schedule → Today →
  complete → progress.
- **B Reality-first**: signup → workspace → KRS import → parse → review → confirm → recurring
  Hard Landscape → future Week/Today visibility → work scheduled around it.
- **C Permanent Shift**: occurrence → Permanent Shift → preview → Apply → future effective
  schedule changes.
- **D One-Time Exception**: one occurrence → exception → preview → Apply → exactly one occurrence
  changes.
- **E Knowledge continuity**: task → note → knowledge context → Canvas/Goal linkage.
- **F Reflection**: complete work → Progress → Review → next action.
- Regression legs: scheduler Sync Now · weekly draft · locked task · offline reconnect · offline
  replay · offline conflict (existing specs cover these — re-run as evidence).

Journey preconditions: B/C/D validate ADR-015 behavior (landed — no longer provisional); sandbox
contract: `make e2e-clean` + seeded fixtures where a spec requires them (S1/B prerequisites).

## P28-014 gate (definition of done for the phase)

- [ ] all P28 required tasks complete (incl. 1–9 above)
- [ ] all P0/P1 experience blockers resolved
- [ ] golden journeys A–F green
- [ ] 3 browser engines green OR explicitly justified gate exception approved by owner
- [ ] accessibility evidence green
- [ ] offline/scheduler core journeys green
- [ ] state matrix coherent (P28-011 artifact)
- [ ] no known correctness defect in core loop
- Then: checkpoint + tag + archive this phase document → STOP (await owner authorization for P29).

## Explicit non-goals (during P28)

Stitch is NOT implementation authority (V3 §20). No new AI features, no new scheduling algorithms,
no new major domain concepts, no new dependencies (freeze rule), no runtime migration (P30), no
repository split (P34), no full SRS/design rewrite (P29).
