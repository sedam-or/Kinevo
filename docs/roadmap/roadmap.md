# Kinevo Roadmap — Baseline & P29–P39 Overview

> Detail per fase: `active/` + `planned/`. Authority: `KINEVO_MASTER_EXECUTION_PROGRAM.md`.

## Current baseline (2026-08-31)

Verified implementation milestones (evidence in ADRs + `docs/browser-e2e.md` + TASK.md archive):

- ADR-015 Effective Schedule: canonical recurrence + UNTIL, Effective Landscape across
  Today/Week/Month/scheduler/ICS, Permanent Shift, One-Time Exception, assignment history,
  locked-task producer. Browser journeys B/C/D/LOCK 4/4.
- ADR-016 Scheduler trigger & lifecycle: weekly draft (never auto-applies), Sync Now
  (`POST /schedule/sync`), reality-impact review, run locks, draft lifecycle, Sacred Anchor
  producer. Browser S1–S4 4/4.
- ADR-017 Offline reconciliation: `offline_operations` ledger, `POST /sync/reconcile`, idempotent
  replay, optimistic conflicts, web MutationQueue drain + conflict UX, retention. Browser O1–O4 4/4.
- Blockers ES-01..05, SCHED-01, OFFLINE-01 RESOLVED; BLOCKER-DOC-01 resolved via ADR-009/010/011
  reconstruction.
- Verification: backend 1125 tests · Vitest 531 · PHPStan 0 · typecheck/build/audit green.

## Phase ladder

| Phase | Name | One-line purpose |
|---|---|---|
| P28 | Product Experience Closure | finish UX/RET work, golden journeys A–F, 3-engine matrix, P28-014 gate |
| P29 | Product & Architecture Convergence | **EXECUTED 2026-08-31** — one canonical authority per truth type; SRS v3; Stitch reconciled |
| P30 | Runtime, Identity & Communication | Octane+FrankenPHP (benchmarked), identity, EmailProvider |
| P31 | Assets & Content Infrastructure | Uppy→Pic Smaller→AssetStorage→object storage pipeline |
| P32 | Analytics, AI Observability & Retention | Kinevo-owned event taxonomy; OpenPanel/Langfuse adapters; ledger stays truth |
| P33 | Commercial Runtime & FinOps | Midtrans production; entitlements authoritative; AI FinOps → quota lock |
| P34 | Repository Boundary & Distribution | Core/Cloud/Site split (never-delete-first, pre-split tag) |
| P35 | Production Operations & Reliability | monitoring, error tracking, backup RESTORE drill, runbooks |
| P36 | Android Production | secure auth, durable offline (ADR-017 protocol), CI release pipeline |
| P37 | Security & Privacy Gate | cross-user/workspace/replay/webhook tests; deletion+export; privacy docs |
| P38 | Performance & Capacity Gate | benchmarked capacity envelope; P50/P95/P99; soak |
| P39 | Release Candidate | RC1 dogfood → burn-down → RC2 rehearsal → GO/NO-GO |

Sequence is default; a dependency-safe exception must be recorded in TASK.md before deviating.
STOP at every phase gate.
