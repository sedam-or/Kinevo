# DOCUMENTATION DRIFT REGISTER — 2026-08-30

READ-ONLY audit output. Each entry: direction of drift, evidence both sides, severity, status (OPEN — nothing was fixed).

Severity: HIGH = misleads implementation/roadmap decisions · MED = contract/doc inaccuracy · LOW = cosmetic/stale wording.

## 1. CODE > DOCS (implementation ahead of documentation)

| # | Item | Code evidence | Doc evidence | Severity |
|---|---|---|---|---|
| 1.1 | `/saas/plan` API implemented + E2E-exercised but missing from OpenAPI contract | server/routes/api.php:82-84; SaasApiTest; PlanSettingsView | docs/api/openapi.yaml (131 paths; /billing/checkout present at :3312, /saas/plan absent) | MED (violates AGENTS.md API rule: no undocumented API) |
| 1.2 | Web navigation adds 3 SYSTEM items beyond design.md §9 | server/resources/js/shell/navigation.ts:15-57 (plan-settings, ai-settings, workspace-home) | docs/design.md §9 groups | LOW |
| 1.3 | Analytics interpretation/ChartMeta/NextAction implemented (P17-017/019) while P28-009 tracked TODO | analytics/interpretation.ts; InterpretationStrip.vue; ChartMeta.vue; AnalyticsView.vue:371 | TASK.md P28-009 TODO with all boxes unchecked | HIGH (roadmap distortion) |
| 1.4 | FeatureHelp explanation system wired in 7 views while P28-010 tracked TODO | FeatureHelp.vue + usages; feature-education.spec.ts | TASK.md P28-010 TODO | HIGH |
| 1.5 | Accessibility suite (axe sweep, keyboard-only, reduced-motion) exists while P28-012 tracked TODO | accessibility.spec.ts:41-113 | TASK.md P28-012 TODO | MED |
| 1.6 | Client offline layer (IndexedDB MutationQueue, SW, today cache) implemented but docs/server contract absent | resources/js/offline/* | docs/offline-sync.md describes server reconciliation that does not exist (see 2.1) | MED |

## 2. DOCS > CODE (documented but not implemented)

| # | Item | Doc evidence | Code evidence | Severity |
|---|---|---|---|---|
| 2.1 | Offline sync server reconciliation + operation-UUID ledger | docs/offline-sync.md (mutation envelope, sync cursor); AGENTS.md offline rule | NO /sync endpoints, no ledger migration; only client queue + billing/EOD idempotency (grep evidence) | HIGH |
| 2.2 | FR-29 Sync Now | docs/SRS.md:928-950 | no route/controller/use case (grep SyncNow = 0) | HIGH |
| 2.3 | FR-27 weekly scheduler job + scheduler run lock | docs/SRS.md:879; docs/scheduling-engine.md:14 | no Jobs directory, no dispatch(, no Cache::lock; draft is synchronous manual HTTP | HIGH |
| 2.4 | FR-25 Permanent Shift deactivates original occurrences | docs/SRS.md:835; ScheduleOverrideType.php docblock | schedule_overrides stored but never applied (SchedulePrecedence unused; no resolution consumer) | HIGH |
| 2.5 | Recurring Hard Landscape visible on occurrence dates | docs/scheduling-engine.md recurrence semantics; KRS confirm creates FREQ=WEEKLY events | expansion only in ICS export (ExportScheduleIcsUseCase:100-114); dayView/draft match raw timestamps | HIGH (KRS-confirmed weekly courses invisible on future days) |
| 2.6 | Email verification / password reset / welcome email | docs/SRS.md identity; P29 tasks | no routes/use cases; email_verified_at unused; no mail channel for notifications | HIGH (P29 scope, listed for convergence) |
| 2.7 | Account deletion / full data export | SRS data ownership; P30 | only activity-log export + ICS; no deletion endpoint | MED (P30 scope) |
| 2.8 | Cloudflare edge assumptions | docs/deployment.md §Cloudflare; architecture.md | nothing wired in compose/scripts | LOW |
| 2.9 | AGENTS.md transaction flow mentions "Controller / Inertia endpoint" | AGENTS.md domain rule | app is fetch+Sanctum SPA; Inertia not in lockfiles (licenses.md:21 "not yet installed") | LOW |
| 2.10 | Mobile offline: on-device SQLite + sync queue promised | docs/mobile-architecture.md | CaptureScreen only marks status='queued' in state; no persisted store | MED |
| 2.11 | billing console commands documented as evidence-bearing | docs/billing.md:150 cites "BillingDomainTest" | file does not exist; transition matrix has no dedicated unit test | LOW |
| 2.12 | AI_MAX_* runtime limits absent from .env.example | config/ai.php:71-81 supports them | server/.env.example:95-112 documents base set only; MIDTRANS_* also absent | MED (onboarding gap) |

## 3. TASK > CODE (TASK.md claims not backed by code)

| # | Item | Task evidence | Code evidence | Severity |
|---|---|---|---|---|
| 3.1 | TASK-096/097 (Recurring Schedule / Schedule Overrides) marked DONE at capability level | TASK.md:1281-1305 | effective-schedule application absent (see 2.4/2.5); no test asserts occurrence-date visibility | HIGH |
| 3.2 | P28-006 browser runs of 2026-08-29 recorded | TASK.md:7925-7929 | docs/browser-e2e.md last record 2026-08-26 (living-baseline doc not updated) | MED |
| 3.3 | P28-RET-007 "depends on P31 event taxonomy" vs "P32-001" | TASK.md:8257,8265 | P32-001 defined at TASK.md:8696 (P31 has no taxonomy task) | LOW (ambiguous reference) |

## 4. CODE > TASK (implemented but untracked/undocumented in tasks)

| # | Item | Code evidence | Task evidence |
|---|---|---|---|
| 4.1 | Analytics interpretation + explanation + accessibility suites (see 1.3-1.5) | code | tracked as TODO → gate baseline distorted |

## 5. ARCHITECTURE CONFLICTS WITH SRS

| # | Item | SRS | Architecture/code |
|---|---|---|---|
| 5.1 | Schedule history preservation | SRS FR-25 "without losing source history" | superseded task_assignments hard-deleted (EloquentScheduleAssignmentRepository:147-157); no history table; no activity event types for schedule changes |
| 5.2 | Locked-task producer | SRS locked-task immutability intent | enforcement tested but no API/command sets locked=true (ScheduleDraftController:336-337 hardcodes false) |
| 5.3 | Product analytics events | SRS FR-69 first-week events; adoption matrix OpenPanel planned | only progress_events/activity_logs (product feature), no product-analytics event taxonomy (P31/P32) |

## 6. DESIGN CONFLICTS WITH IMPLEMENTATION

| # | Item | Design | Implementation |
|---|---|---|---|
| 6.1 | nav §9 vs code (+3 SYSTEM items) | design.md §9 | navigation.ts (see 1.2) |
| 6.2 | Review surface | design.md REVIEW group implies Progress/Analytics/Wrapped/Recovery | web REVIEW = Analytics only; review reflection native-only; Wrapped NOT_STARTED |
| 6.3 | Programs/Milestones surfaces | design.md navigation lists them | no dedicated web screens (milestones inline in GoalDetail; programs only via QuickCapture dropdown) |

## 7. COMMERCIAL DOCS CONFLICT WITH SYSTEM

| # | Item | Docs | System |
|---|---|---|---|
| 7.1 | AI credit quotas | docs/billing.md:28-32 (20/150/500, DECISION_REQUIRED); revisi-finance.md:175-180 (deprecated, don't invent numbers) | config/saas.php:42/54/66 = 20/300/1000 (self-labeled deprecated-baseline) — live values diverge from every documented baseline |
| 7.2 | Upgrade path | docs/billing.md/TASK P24-021 "upgrade = new checkout to higher plan" | BillingService.php:62-68 one-active guard rejects checkout while active/past_due/cancel_at_period_end exists → paid→paid upgrade unreachable; no test covers it |
| 7.3 | ChargebackResolved | MidtransGateway::capabilities() says manual dashboard resolution | ChargebackOpened handled (uncertain flag); ChargebackResolved has NO mapping in stateFor()/mapStatus() |
| 7.4 | ADR-013 prices | superseded (34.9k/49.9k) | config = 49.9k/89.9k — consistent with revisi-finance; ADR preserved as history (correct governance, no action) |
| 7.5 | Sandbox evidence tier | docs/billing.md:156-167 uses retired `personal` tier | effectivePlanCode degrades personal→free; evidence-only |

## 8. WORKSPACE SEMANTICS AMBIGUITY

| # | Item | Evidence |
|---|---|---|
| 8.1 | Active workspace authority split: web = device-local localStorage; mobile = server default flag | resources/js/workspace/store.ts:20,131-140 vs NativeComponents/WorkspacesScreen.php:55-58 — two devices on one account can disagree until default is set |
| 8.2 | Cross-workspace reads are deliberate (Today/Week/Month/HL/Notifications user-scoped) but docs never state this explicitly | ScheduleQueryService dayView:49; hard_landscape_events has no workspace_id; features/registry.ts:49 comment |
| 8.3 | AI context workspace-bounded only for goal breakdown; ai_runs/ai_proposals carry no workspace dimension | CreateGoalBreakdownProposalUseCase:76-84 vs AiController (no workspace param anywhere) |
| 8.4 | Analytics API supports ?workspace_id but web UI never sends it | AnalyticsController:71-80 vs resources/js/analytics/api.ts |
| 8.5 | Milestones/subtasks/attachments inherit parent scoping implicitly; no workspace_id columns | migration inventory (only 5 scoped tables) |

## 9. EXTRA STRUCTURAL DRIFT FOUND

| # | Item | Evidence | Severity |
|---|---|---|---|
| 9.1 | ADR-009/010/011 referenced but files absent | referenced in docs/SRS.md, deployment.md, browser-e2e.md, TASK.md, docker-compose.yml; docs/adr/ contains 001-008+012-014 only | HIGH (dangling authority) |
| 9.2 | infrastructure/scripts/backup.sh + restore.sh are 0-byte files shadowing real scripts/ | ls -la infrastructure/scripts vs scripts/ | MED |
| 9.3 | Node version drift CI(22) vs Dockerfile.prod(20) | .github/workflows/ci.yml vs Dockerfile.prod:8 | LOW |
| 9.4 | CHANGELOG versions exist without any git tag; no VERSION file (per governance) | CHANGELOG.md [0.4.0]; git tag empty | LOW |
| 9.5 | Root-level tracked planning/patch docs not in documentation-inventory (which predates them) | KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md, KINEVO_THIRD_PARTY_...SPEC.md, revisi-finance.md (tracked, dated 08-28) vs documentation-inventory.md (2026-08-26, claims "No TEMPORARY files remain tracked") | MED |
| 9.6 | Root layout mixture: canonical migrations + Playwright suite at ROOT, Laravel app under server/; root composer.json installs only Pint (root vendor/ + node_modules present) | git ls-files; root composer.json | LOW (structural confusion risk) |
| 9.7 | phpunit.xml has no explicit migration path; canonical migrations loaded via AppServiceProvider loadMigrationsFrom(root database/migrations) | AppServiceProvider.php:181 | LOW (fragile coupling) |

All items OPEN. Nothing was corrected in this audit.
