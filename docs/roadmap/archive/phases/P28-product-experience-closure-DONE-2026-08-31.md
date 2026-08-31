# P28 — Product Experience Closure (ACTIVE — GATE GREEN 2026-08-31)

> Detailed authority for P28 task execution. TASK.md links here. Status vocabulary per TASK.md.
> Re-audited 2026-08-31 (R0): **21 DONE · 9 TODO · 1 GATED**. Execution authority:
> `docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md` (STOP after P28-014; no auto-P29).
> **2026-08-31 closure:** all remaining tasks DONE; P28-014 gate GREEN. P28 is complete;
> the phase document is archived and P29 awaits owner authorization.

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

Status as of 2026-08-31 closure: all remaining implementation tasks are **DONE**
(evidence in `docs/browser-e2e.md` §P28-013, `docs/state-machine-ui.md` §P28-011,
`docs/retention-events.md`, and Vitest). P28-014 remains **GATED** pending the
gate evaluation at the end of this document.

| Order | Task | Title | Priority | Depends On | Status · Evidence |
|---|---|---|---|---|---|
| 1 | P28-010 | Feature Explanation Layer | P2 | P28-002 | **DONE** — registry adds `knowledge`/`canvas`/`ai-provider-modes` (`features/registry.ts`); FeatureHelp wired to Notes, Canvas, Workspace home, AI Settings (provider modes block). Vitest `registry.test.ts` + `NoteViews`/`WorkspaceHome`/`CanvasListView` tests; browser `p28-ux-audit.spec.ts` §P28-010 (3 engines) |
| 2 | P28-011 | Global State Matrix | P1 | P28-001 | **DONE** — canonical matrix in `docs/state-machine-ui.md` §Global user-facing state matrix (surface × LOADING/EMPTY/SUCCESS/PARTIAL/OFFLINE/QUEUED/SYNCING/STALE/NEEDS_REVIEW/CONFLICT/FAILED/UNAVAILABLE/ENTITLEMENT_BLOCKED, N/A explicit); implemented high-value missing states: `InlineError.vue` (error + retry) on Today/Goals/Tasks/Notes/Canvas/Analytics |
| 3 | RET-002 (P28-RET-002) | Contextual Empty States refinement | P2 | P28-002 | **DONE** — Canvas + Notes empty states deepened to answer where/why/next/after; Goals/Tasks/Today/Analytics already satisfied; verified browser (p28-ux-audit §P28-002, 3 engines) + Vitest |
| 4 | RET-005 | AI Breakdown Aha Moment | P1 | P28-006 | **DONE** — post-accept continuation verified: accepted → milestones → "Open goal" → NextActionBanner `work-on-milestone` → Today. G2 (AI accept) + new `p28-golden-journeys.spec.ts` RET-005 leg (3 engines); GoalViews.test.ts continuation assertion |
| 5 | RET-006 | First Session Journey | P1 | RET-005 | **DONE** — Today empty day shows a `first-session-guide` (RET-006): reality-first quick-capture focus + goal-first path; no forced tutorial. Vitest `TodayView.test.ts`; browser p28-ux-audit (3 engines, pristine baseline) |
| 6 | RET-007 | First Week Retention Events | P2 | — | **DONE** — semantic taxonomy ONLY: `docs/retention-events.md` (13 events: meaning/trigger/required+optional props/workspace/dedup/privacy/success). No instrumentation (P32 consumes it) |
| 7 | RET-008 | Progress Feedback System | P1 | P28-007 | **DONE** — completion toast now reports the concrete progress delta (`Task completed · X/Y done`) after reload; existing progress bar + NEXT emphasis retained. Vitest cascade test |
| 8 | RET-013 | Retention Failure E2E | P1 | RET-002, RET-005, P28-006 | **DONE** — retention-critical failure matrix in `docs/state-machine-ui.md` §Retention-critical failure matrix (what user sees / data preserved / recovery / retry / escape); implemented uniform `InlineError` retry across data surfaces; AI-gate + offline/conflict/review surfaces already canonical |
| 9 | P28-013 | Browser Golden Journeys | P0 | P28-002/004/005 + steps 1–8 core legs | **DONE** — A–F evidence matrix across Chromium/Firefox/WebKit recorded in `docs/browser-e2e.md` §P28-013; new spec `p28-golden-journeys.spec.ts` (Journey E + RET-005); known browser failures triaged (see §Known browser failures below) |
| 10 | P28-014 | Product Experience Baseline Gate | P0 (GATED) | ALL P28 tasks | Gate checklist below |

## Known browser failures — TRIAGED 2026-08-31 (closure evidence)

Last full run (docs/browser-e2e.md): **98 passed / 3 failed (14.4m)**:
1. `scheduler` S1 — **HARNESS_DEFECT**. Needs clean+seeded sandbox
   (`make e2e-scheduler`); documented prerequisite. Verified **PASS** through the
   documented harness.
2. `p28-ux-audit` goals empty state — **HARNESS_DEFECT**. The spec's documented
   clean-DB precondition was violated by alphabetical spec ordering in a single
   `make e2e` run. Fix at the correct layer: `make e2e` now runs
   `p28-ux-audit.spec.ts` as its own first phase on the `e2e-clean` baseline.
   Verified **PASS** (3 engines). Empty-state copy also deterministic in Vitest.
3. `theme` mobile 375px — **PRODUCT_DEFECT** (UI-012 responsive overflow). Fixed
   `shell/AppShell.vue` topbar (`flex-wrap` + `min-w-0` + wordmark hidden <sm).
   Verified **PASS**: theme.spec.ts + mobile-sweep.spec.ts (3 engines).
Never delete meaningful assertions to get green (test policy) — none were weakened.

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

## P28-014 gate (definition of done for the phase) — EVALUATED 2026-08-31

- [x] all P28 required tasks complete (incl. 1–9 above) — 21 DONE + 9 new DONE
- [x] all P0/P1 experience blockers resolved — triaged above, all green
- [x] golden journeys A–F green — evidence matrix in docs/browser-e2e.md §P28-013
- [x] 3 browser engines green (no gate exception needed) — Chromium/Firefox/WebKit PASS
- [x] accessibility evidence green — accessibility.spec.ts (axe wcag2a+2aa) 3 engines
- [x] offline/scheduler core journeys green — offline O1–O4 (chromium, documented single-engine) + scheduler S1–S4 (seeded harness)
- [x] state matrix coherent (P28-011 artifact) — docs/state-machine-ui.md §Global user-facing state matrix
- [x] no known correctness defect in core loop — full verification green (backend 1125 · Vitest 535 · PHPStan 0 · Pint · typecheck/build · npm audit 0 · OpenAPI · doc links)

**GATE RESULT: GREEN.** Per execution authority (STOP-after-gate), P28-014 is
marked DONE and Phase P28 closes here. **P29 is NOT activated** — checkpoint +
tag only; await explicit owner authorization. See TASK.md for next phase.

## Explicit non-goals (during P28)

Stitch is NOT implementation authority (V3 §20). No new AI features, no new scheduling algorithms,
no new major domain concepts, no new dependencies (freeze rule), no runtime migration (P30), no
repository split (P34), no full SRS/design rewrite (P29).
