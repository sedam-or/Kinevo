> Planned phase document (R0 rebaseline 2026-08-31). Authority: docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.
> Detailed microtasks are authored at phase activation — never fabricated in advance.

# P39 — Release Candidate
Status: PLANNED · EVIDENCE-DRIVEN (no calendar-forced launch) · Depends On: P38 gate

## Objective
RC1 internal dogfood → bug burn-down → RC2 production-like rehearsal → GO/NO-GO.

## Scope / major workstreams
- RC1: deploy complete candidate; run all canonical journeys with real monitoring; collect bugs/UX friction/billing/email/AI cost/scheduler quality/offline/mobile/performance. No feature expansion during RC unless fixing a release blocker.
- Bug classification: P0 release-blocked; P1 release-blocked unless explicit owner waiver; P2 may ship with documented plan; P3 post-launch backlog. No hidden P0/P1 debt.
- RC2 rehearsal from zero: fresh deployment, migration, seed/bootstrap, domain configuration, email, payment, AI provider, storage, analytics, observability, backup, restore, rollback, mobile connection, site, status/health. Runbooks verified from zero.
- Final golden journeys: new user → Workspace → Goal → AI breakdown → schedule → Today → complete → Progress → Review; KRS import/recurrence/Permanent Shift/One-Time Exception; offline reconnect+conflict; weekly draft + Sync Now + locked/Sacred Anchor; Note/Canvas/assets; Free→Pro, Pro→Power (where supported), cancel/resume, payment reconciliation; email verification/reset; Android core journey; data export/delete.

## GO only if
Correctness/security/privacy/accessibility/browser matrix/Android/performance/backup-restore/observability/billing/email/AI FinOps/license compliance/runbooks ALL green; no unresolved P0; no unresolved unapproved P1. Otherwise NO-GO — there is no requirement to ship because a date was written.

## Gate
v1.0.0 tag ONLY with operator approval (agent never tags/releases autonomously); changelog tied; release notes; reproducible build evidence. STOP.

## Known open decisions
Launch market scope (Indonesia-first); support channel commitments.

## P29 convergence refinements (2026-08-31)
- Pre-GO sweep: `docs/marketing/claims-registry.md` re-audit (no PROHIBITED claim published anywhere).
