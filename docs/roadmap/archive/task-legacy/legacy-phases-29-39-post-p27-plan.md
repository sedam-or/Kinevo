# Legacy Post-P27 Plan — Phases 29–39 (old numbering)

> ARCHIVED 2026-08-31 (R0 documentation rebaseline). SUPERSEDED by the V3 rebaseline (docs/roadmap/rebaseline-2026-08.md): old P29→P30, old P30→P37, old P31→P32 (Wrapped scope noted), old P32→P32/P33, old P33→P34, old P34→P35, old P35→P36, old P36→P37, old P37→P39 (RC dogfood), old P38→P38, old P39→P39. Contains DONE records SCHED-01 (ADR-016) and OFFLINE-01 (ADR-017) at its top; remaining microtask detail retired — detailed tasks are (re)created at phase activation. IDs preserved verbatim.
> Task IDs are immutable and preserved verbatim below. This file is historical
> evidence — NOT an execution authority. Authority: TASK.md (control plane) +
> docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.

---

## PHASE 29 — IDENTITY, EMAIL, RECOVERY, SECURITY TRUST

### SCHED-01 — Scheduler Trigger, Sync Now & Draft Approval Lifecycle (ADR-016)
- Status: DONE · Priority: P0 · Depends On: ES-IMPL-08
- Business Decision: ADR-016 — weekly trigger prepares a persisted pending draft
  (never auto-applies); manual Sync Now returns no_changes/proposal/run_in_progress
  (read-only diff via the SAME DynamicRescheduler); reality changes produce a bounded
  review-needed state; per-user cache run locks; apply keeps optimistic 409 semantics;
  AI excluded from scheduling authority
- Files: docs/adr/ADR-016-*.md; database/migrations/2026_08_31_* (schedule_drafts,
  schedule_states, tasks.is_sacred_anchor); app/Console/Commands/PrepareWeeklyScheduleCommand.php;
  app/Application/Scheduling/{AssembleScheduleInput,PrepareWeeklyDraftUseCase,SyncNowUseCase,
  ScheduleImpactService,DiscardScheduleDraftUseCase}.php; ScheduleDraftController (sync/drafts/
  discard endpoints); routes/api.php (+3); NotificationType (+2); TaskController + Tasks domain
  (is_sacred_anchor); ScheduleQueryService (+schedule_needs_review); bootstrap/app.php (schedule
  entry); Makefile (e2e-scheduler); frontend: schedulerdraft store/api/types + views (Sync Now,
  weekly banner), today (needs-review pill), task detail (Sacred Anchor toggle)
- Acceptance: [x] weekly draft generated, never auto-applied (WeeklyPrepareCommandTest ×6) ·
  [x] duplicate weekly run idempotent · [x] stale draft refreshed in place · [x] applied week not
  regenerated · [x] no-work user gets nothing · [x] Sync Now no_changes/proposal/run_in_progress
  (ScheduleSyncApiTest ×7) · [x] locked work never proposed for a move · [x] synced proposal
  applies via existing endpoint · [x] horizon bounded 14 days · [x] reality change flags review
  + one notification only, outside-window no-op, manual placements exempt (ScheduleImpactTest ×5)
  · [x] draft lifecycle apply/discard/stale/owner-scoped (ScheduleDraftsApiTest ×6) · [x] Sacred
  Anchor producer at-most-one validation + generator placement (SacredAnchorApiTest ×4) ·
  [x] OpenAPI synchronized (sync/drafts/discard, needs_review, is_sacred_anchor) · [x] browser
  journeys S1–S4 green (make e2e-scheduler, see docs/browser-e2e.md)
- Verification: [x] Feature (backend 1109/1109 container) · [x] Vitest 522/522 · [x] typecheck ·
  [x] build (container) · [x] PHPStan 0 · [x] Pint · [x] openapi check · [x] browser (chromium)
  · Known Limitations: FR-04 XP/study-modes/multi-track anchors deferred; S1 requires clean+seeded
  sandbox (`make e2e-scheduler`); notification channel stays DB-poll (no push/email per boundary)
  · Notes: BLOCKER-SCHED-01 RESOLVED; queue infra intentionally NOT adopted (synchronous bounded
  computation suffices); scheduler_runs extended as telemetry

### OFFLINE-01 — Offline Mutation Reconciliation & Operation Ledger (ADR-017)
- Status: DONE · Priority: P0 · Depends On: SCHED-01
- Business Decision: ADR-017 — server-authoritative operation ledger; bounded
  allowlist (task create/update/status, subtask create, note create/update);
  idempotent replay by (user_id, operation_id) + payload hash; version conflicts
  (task/note base_version) never overwrite; quick capture + all schedule
  mutations stay ONLINE_ONLY; client clocks never decide precedence; no LWW/CRDT
- Files: docs/adr/ADR-017-*.md; database/migrations/2026_08_31_110000_create_offline_operations_table.php;
  app/Domain/OfflineSync/* (OperationType allowlist, OperationEnvelope, OperationOutcome,
  OfflineOperationRecord, contracts); app/Application/OfflineSync/OfflineReconciliationService.php
  (dispatch to real use cases); app/Http/Controllers/Api/SyncReconcileController.php;
  routes/api.php (+/sync/reconcile); app/Console/Commands/PruneOfflineLedgerCommand.php;
  config/offline.php; bootstrap/app.php (prune schedule); TaskController/NoteController
  (+X-Operation-Id online convergence, task base_version, TaskVersionConflict, task version
  bump on content updates); web: offline/reconcile-applier.ts (→/sync/reconcile),
  offline/reconcile-submit.ts (offline-aware submit), offline/queue-access.ts, queue
  listFailed+discardConflicts, auth/store.ts offline-reload fix, task/note stores wired,
  SyncStatusPanel conflict review button, AuthHost boot drain + rehydration; Makefile
  e2e-clean (+offline_operations); docs/offline-sync.md + mobile-architecture.md rewritten
- Acceptance: [x] first-apply once · [x] same id + identical payload replay (no dup) ·
  [x] same id + different payload REUSED · [x] stale task/note update conflict, server wins ·
  [x] task:status semantic idempotency · [x] ownership isolation · [x] workspace context
  snapshotted + verified · [x] unsupported type rejected · [x] batch ≤50 + payload bounds ·
  [x] per-op transaction (one bad op doesn't corrupt batch) · [x] retention 90d + prune ·
  [x] online X-Operation-Id ledger convergence · [x] web queue enqueues offline, drains on
  boot/reconnect, conflict review + discard · [x] auth offline-reload keeps session ·
  [x] OpenAPI /sync/reconcile + schemas
- Verification: [x] Feature (OfflineReconcileApiTest ×16) · [x] Vitest 531/531 (offline
  applier/submit/auth +8) · [x] typecheck · [x] build (container) · [x] PHPStan 0 · [x] Pint ·
  [x] openapi check · [x] browser O1–O4 (offline-reconcile.spec.ts, chromium) · Known
  Limitations: mobile durable queue deferred to Android hardening (ADR-017 §2.19); quick
  capture + subtask toggle + canvas + goal/program offline deferred (documented in capability
  matrix); note documents > 64KB rejected offline (per-op payload bound) · Notes:
  BLOCKER-OFFLINE-01 resolved (server/web); server protocol reusable by mobile unchanged

> Objective: verified identity, reliable transactional email, safe recovery. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §7 (execution authority).

### P29-001 — Email-First Identity
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: primary identity = verified email; do NOT introduce usernames unless explicitly required
- SRS: identity · Design: docs/design.md auth surfaces · Files: docs (identity policy) + registration review
- Acceptance:
  - [ ] identity policy documented (email-first; no username)
  - [ ] registration/login flows consistent with policy
- Verification: [ ] Unit(existing auth suite) · [ ] Browser registration walk
- Evidence: — · Known Limitations: — · Notes: —

### P29-002 — Email Verification
- Status: TODO · Priority: P0 · Depends On: P29-001
- Business Decision: —
- SRS: identity · Design: docs/design.md verification flow · Files: verification flow + tests
- Acceptance:
  - [ ] register → send verification → verify token → mark email verified
  - [ ] token expires · single use · resend rate limited
  - [ ] expired token fails · reused token fails · enumeration resistance
- Verification: [ ] Unit(token lifecycle) · [ ] Integration · [ ] E2E · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P29-003 — Forgot Password
- Status: TODO · Priority: P0 · Depends On: P29-001
- Business Decision: generic response for non-existing email (indistinguishable)
- SRS: identity · Design: docs/design.md reset flow · Files: reset flow + tests
- Acceptance:
  - [ ] submit email → generic response → reset email → secure token → new password
  - [ ] old sessions invalidated
  - [ ] plaintext token MUST NOT be stored (hash only) · expiration mandatory · one-time use mandatory
  - [ ] valid reset works · expired reset blocked · replay blocked · non-existing email indistinguishable
- Verification: [ ] Unit(token) · [ ] Integration · [ ] E2E · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P29-004 — Email Abstraction
- Status: TODO · Priority: P0 · Depends On: P29-001
- Business Decision: development = Mailpit/local catcher; production provider selected EXPLICITLY
  before P39 (candidates: Brevo / Amazon SES / Resend) — no provider-specific calls hardcoded
- SRS: — · Design: docs/environment.md mail config · Files: mail abstraction + template system
- Acceptance:
  - [ ] provider abstraction · [ ] local catcher · [ ] template system · [ ] retry
- Verification: [ ] Unit · [ ] Integration(queue)
- Evidence: — · Known Limitations: — · Notes: production choice = owner decision, recorded before P39

### P29-005 — Transactional Emails
- Status: TODO · Priority: P0 · Depends On: P29-004
- Business Decision: Bahasa Indonesia + English required
- SRS: identity/billing · Design: docs/design.md email templates · Files: templates + queue + retry + failure logging
- Acceptance — required before v1:
  - [ ] email verification · [ ] password reset · [ ] welcome/onboarding · [ ] critical security notification
  - [ ] subscription activation · [ ] payment/renewal notification · [ ] failed payment notification
  - [ ] localization (ID/EN) · [ ] queue · [ ] retry · [ ] failure logging
- Verification: [ ] Unit(templates) · [ ] Integration(queue) · [ ] E2E
- Evidence: — · Known Limitations: — · Notes: —

### P29-006 — Google OAuth
- Status: DEFERRED · Priority: P2 · Depends On: P29-001
- Business Decision: implement ONLY if provider requirements and account-linking policy are verified;
  NEVER auto-merge accounts
- SRS: identity · Design: — · Files: OAuth flow + linking policy + tests
- Acceptance:
  - [ ] OAuth login · [ ] existing-account linking policy · [ ] duplicate account handling
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P29-007 — Account Security Policy
- Status: TODO(doc) · Priority: P1 · Depends On: P29-002; P29-003
- Business Decision: V1 = password + email verification + password reset + session invalidation;
  2FA/passkey deferred unless explicitly added later
- SRS: security · Design: — · Files: security policy doc
- Acceptance:
  - [ ] policy documented
- Verification: doc review
- Evidence: — · Known Limitations: — · Notes: —

### P29-008 — Account Deletion
- Status: TODO · Priority: P0 · Depends On: P29-001; P30-002
- Business Decision: V1 baseline = 30-day deletion grace period
- SRS: data ownership · Design: docs/design.md account surfaces · Files: deletion flow + tests
- Acceptance:
  - [ ] request deletion → confirm → optional export → grace period → cancel deletion → permanent deletion
  - [ ] request works · cancellation works · final deletion works · dependency cleanup verified
- Verification: [ ] Unit · [ ] Integration · [ ] E2E · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P29-009 — Security Notifications
- Status: TODO · Priority: P1 · Depends On: P29-005
- Business Decision: notify without leaking sensitive data
- SRS: security · Design: — · Files: notification events + delivery path
- Acceptance:
  - [ ] event generated · [ ] delivery path · [ ] read state · [ ] privacy review
- Verification: [ ] Integration
- Evidence: — · Known Limitations: — · Notes: —

### P29-010 — P29 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P29 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §7 P29-010):
  - [ ] verification · [ ] password reset · [ ] email system · [ ] account deletion
  - [ ] recovery security · [ ] critical browser evidence
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 30 — DATA OWNERSHIP, EXPORT, PRIVACY, RECOVERY

> Objective: user owns their data — export, deletion, privacy, retention, backup coverage. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §8 (execution authority).

### P30-001 — Data Ownership Policy
- Status: TODO(doc) · Priority: P0 · Depends On: —
- Business Decision: user data includes Goals; Tasks; Notes; Canvas; Knowledge; Workspace content;
  personal progress
- SRS: data ownership · Design: — · Files: ownership policy doc
- Acceptance:
  - [ ] ownership policy documented
- Verification: doc review
- Evidence: — · Known Limitations: — · Notes: —

### P30-002 — Data Export
- Status: TODO · Priority: P0 · Depends On: P30-001
- Business Decision: V1 formats = JSON + Markdown + CSV; PDF NOT mandatory; owner-scoped only
- SRS: data ownership · Design: docs/design.md export surface · Files: export service + tests
- Acceptance:
  - [ ] JSON · Markdown · CSV exports
  - [ ] owner-scoped (no cross-user leakage)
- Verification: [ ] Unit(formats) · [ ] Integration(scoping) · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P30-003 — Export Job
- Status: TODO · Priority: P1 · Depends On: P30-002
- Business Decision: —
- SRS: — · Design: — · Files: queued export job + download link handling
- Acceptance — for larger exports:
  - [ ] queue · [ ] status · [ ] progress where feasible · [ ] secure download · [ ] expiration
- Verification: [ ] Integration(queue) · [ ] E2E
- Evidence: — · Known Limitations: — · Notes: —

### P30-004 — Data Deletion Map
- Status: TODO(doc) · Priority: P0 · Depends On: P30-001
- Business Decision: some billing records may require retention — document exceptions
- SRS: — · Design: — · Files: deletion map doc + deletion implementation tests
- Acceptance — map ALL of: user · profile · workspaces · goals · milestones · programs · tasks ·
  subtasks · notes · canvas · knowledge links · progress · activity · AI audit/usage · billing
  references · notifications:
  - [ ] every entity mapped (delete vs retain; retention exceptions justified)
  - [ ] deletion honors the map (dependency cleanup)
- Verification: [ ] Integration(deletion across entities)
- Evidence: — · Known Limitations: — · Notes: —

### P30-005 — Privacy Policy Surface
- Status: TODO · Priority: P1 · Depends On: P30-001
- Business Decision: do NOT claim legal certification without counsel/audit
- SRS: — · Design: docs/design.md trust surfaces · Files: privacy surface content
- Acceptance — document: data collected · AI processing · BYOK processing · payment processing ·
  analytics telemetry · retention · deletion · export · third-party services:
  - [ ] all areas covered
- Verification: doc review
- Evidence: — · Known Limitations: legal review pending · Notes: overlaps P36-002 — keep one source

### P30-006 — AI Data Control
- Status: TODO · Priority: P1 · Depends On: P30-005
- Business Decision: NEVER imply private content is unprocessed when it is processed
- SRS: AI chapters · Design: docs/ai-architecture.md · Files: AI data-control surface + copy
- Acceptance:
  - [ ] explain hosted AI vs BYOK · [ ] explain which content is sent for a request
  - [ ] provide controls where technically supported
- Verification: [ ] Browser evidence
- Evidence: — · Known Limitations: — · Notes: —

### P30-007 — Data Retention Matrix
- Status: TODO(doc) · Priority: P1 · Depends On: P30-004
- Business Decision: where legal/operational retention is uncertain → mark REVIEW REQUIRED; do not
  invent legal policy
- SRS: — · Design: — · Files: retention matrix doc
- Acceptance — define retention for: AI runs · AI proposals · usage records · billing events ·
  email logs · notifications · deleted account records · audit records:
  - [ ] all categories defined · [ ] uncertain items flagged for review
- Verification: doc review
- Evidence: — · Known Limitations: — · Notes: —

### P30-008 — Backup/Restore Coverage
- Status: TODO · Priority: P1 · Depends On: P30-007
- Business Decision: —
- SRS: durability NFRs · Design: docs/deployment.md · Files: backup scope + restore test
- Acceptance — backups include required SaaS state subject to retention policy; restore verified for:
  - [ ] workspace · [ ] goals · [ ] tasks · [ ] notes · [ ] canvas · [ ] subscriptions
  - [ ] entitlements · [ ] AI usage · [ ] billing events
- Verification: [ ] Integration(restore drill, local/staging)
- Evidence: — · Known Limitations: full production drill in P39-012 · Notes: —

### P30-009 — P30 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P30 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §8 P30-009):
  - [ ] ownership · [ ] export · [ ] deletion · [ ] privacy · [ ] AI data transparency
  - [ ] retention · [ ] backup/recovery
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 31 — PRODUCT INTELLIGENCE, ANALYTICS, INSIGHTS, WRAPPED

> Objective: evidence-based reflection and shareable storytelling. Deterministic first, AI bounded.
> Renumbered from old "PHASE 28" per KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md (execution authority §0/§9).

### P31-001 — Intelligence Source Matrix
- Status: TODO · Priority: High · Depends On: P23 data foundations
- Business Decision: Wrapped FREE=basic, PRO=advanced/yearly, POWER=expanded (locked)
- SRS: analytics chapters · Design: docs/mobile-architecture.md §9 upcoming; docs/domain-model.md
- Files: docs/… intelligence matrix section (owner doc: TASK note + analytics module doc)
- Acceptance:
  - [ ] sources documented: Goals; Milestones; Tasks; Activity Logs; Progress Events; Focus Sessions;
        scheduling outcomes; Workspace context
  - [ ] every metric source documented
  - [ ] no metric uses UI-only state
- Verification: Unit(review of sources) / Integration(n/a) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: mirrors JOURNEY H inputs

### P31-002 — Metric Catalog
- Status: TODO · Priority: High · Depends On: P31-001
- Business Decision: — 
- SRS: analytics chapter · Design: —
- Files: MetricCatalog class + tests (Application/Intelligence)
- Acceptance (metrics with exact formulas defined):
  - [ ] goals created/completed · [ ] milestones advanced/completed · [ ] tasks completed
  - [ ] completion ratio · [ ] focus minutes · [ ] active days · [ ] streak where supported
  - [ ] goal progress · [ ] planned vs completed work
  - [ ] every metric defines: source, formula, date range, timezone, inclusion, exclusion, null behavior
  - [ ] tests for all metrics
- Verification: Unit(all formulas) / Integration(source queries) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: deterministic; timezone-aware (profile timezone)

### P31-003 — Insight Engine
- Status: TODO · Priority: High · Depends On: P31-002
- Business Decision: AI assists, never silently controls; deterministic insights precede narrative
- SRS: insights · Design: docs/ai-architecture.md boundary
- Files: InsightEngine service + types
- Acceptance (every insight implemented):
  - [ ] positive trend · [ ] negative trend · [ ] consistency · [ ] goal alignment
  - [ ] planning/execution gap · [ ] workload pattern
  - [ ] every insight has evidence attached
  - [ ] deterministic output (same inputs → same outputs)
  - [ ] tests
- Verification: Unit(each insight) / Integration(metrics feed) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: non-diagnostic language rules apply

### P31-004 — User Analytics vs Founder Analytics
- Status: TODO · Priority: High · Depends On: P31-002
- Business Decision: permissions NEVER mixed — USER surfaces personal performance/progress/review;
  FOUNDER surfaces activation/retention/revenue/AI spend/payment failures (founder-only access)
- SRS: analytics · Design: docs/design.md analytics surfaces
- Files: founder analytics surface (separate from user Analytics) + authorization tests
- Acceptance:
  - [ ] USER analytics shows only the signed-in user's own data
  - [ ] FOUNDER analytics (activation; retention; revenue; AI spend; payment failures) is access-controlled
  - [ ] no metric from one surface leaks into the other
  - [ ] authorization tests for founder-only access
- Verification: Unit / Integration(authorization) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: master prompt §9 P31-004 (canonical; was missing from old roadmap)

### P31-005 — AI Narrative
- Status: TODO · Priority: Medium · Depends On: P31-003; AI gateway
- Business Decision: AI receives ONLY validated metrics/insights (untrusted-input pipeline applies)
- SRS: AI chapters (FR-60..62) · Design: docs/ai-architecture.md
- Files: NarrativeUseCase (structured output schema)
- Acceptance:
  - [ ] bounded context (validated metrics package only)
  - [ ] schema validation for structured response
  - [ ] invalid response rejected (no silent fallback to invented data)
  - [ ] AI-unavailable fallback keeps deterministic summary usable
  - [ ] AI MUST NOT invent numbers/dates/goals/medical/psych/causation (guardrails tested)
- Verification: Unit(schema reject cases) / Integration(provider stub) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: quality depends on provider; costs metered via existing ledger
- Notes: 

### P31-006 — Monthly Review
- Status: TODO · Priority: High · Depends On: P31-002/003
- Business Decision: FREE gets basic monthly/yearly summary when enough data exists
- SRS: review · Design: docs/design.md review
- Files: MonthlyReview screen/API composition
- Acceptance:
  - [ ] activity section (metrics) · [ ] progress section · [ ] notable changes
  - [ ] stalled items surfaced · [ ] suggested next focus (from insights, actionable)
  - [ ] deterministic metrics underlying everything · [ ] evidence visible · [ ] next action present
- Verification: Unit / Integration / E2E(web) / Device(mobile review later)
- Evidence: — · Known Limitations: — · Notes: feeds Reflection→Goal (P31-010)

### P31-007 — Yearly Wrapped
- Status: TODO · Priority: High · Depends On: P31-006
- Business Decision: ENTITLEMENT SPLIT LOCKED — FREE basic summary; PRO advanced yearly + richer
  comparisons + AI narrative where available; POWER all PRO + deeper history + expanded share
- SRS: wrapped chapters · Design: docs/design.md wrapped surface
- Files: Wrapped flow (sections + composition)
- Acceptance (required sections):
  - [ ] opening · [ ] goals · [ ] milestones · [ ] execution · [ ] focus · [ ] knowledge
  - [ ] major progress · [ ] patterns · [ ] reflection · [ ] next direction
  - [ ] FREE: basic summary rendered
  - [ ] PRO: advanced yearly Wrapped rendered
  - [ ] POWER: expanded insights/share customization rendered
- Verification: Unit(per-section builders) / Integration(entitlement branches) / E2E(Journey H) / Device
- Evidence: — · Known Limitations: — · Notes: uses history.depth entitlement for depth ceilings

### P31-008 — Shareable Artifact
- Status: TODO · Priority: Medium · Depends On: P31-007
- Business Decision: privacy-safe by default; explicit user confirmation required
- SRS: sharing/security · Design: docs/design.md share cards
- Files: ShareCard renderer (vertical story / square / downloadable card-image)
- Acceptance:
  - [ ] user sees EXACT share payload before confirming
  - [ ] privacy-safe by default (no raw private task/note/canvas content)
  - [ ] explicit confirmation step required
  - [ ] formats: vertical story · square · download image/card
  - [ ] preview required before any external action
- Verification: Unit(payload builder) / Integration(storage/export) / E2E(preview→confirm) / Device(save image)
- Evidence: — · Known Limitations: — · Notes: POWER expands customization options only

### P31-009 — Public Share Link
- Status: OPTIONAL/DECISION_REQUIRED (owner confirmed public links?) · Priority: Low · Depends On: P31-008
- Business Decision: IF implemented — non-guessable token; revocable; privacy-safe payload
- SRS: security NFRs · Design: docs/api/openapi.yaml additions
- Files: PublicShareLink model/controller/tests (+ migration)
- Acceptance:
  - [ ] unauthorized access test passes
  - [ ] revoke test passes
  - [ ] no private data leakage in public payload
  - [ ] token non-guessable (entropy documented)
- Verification: Unit(token) / Integration(revoke) / E2E(link visit logged-out) / Device(n/a)
- Evidence: — · Known Limitations: rate limiting + abuse monitoring required before enabling
- Notes: skip unless owner explicitly green-lights public sharing

### P31-010 — Reflection to Goal
- Status: TODO · Priority: Medium · Depends On: P31-006
- Business Decision: NO automatic Goal creation — explicit confirmation mandatory
- SRS: goals · Design: docs/design.md reflection loop
- Files: Insight→Goal composer dialog
- Acceptance:
  - [ ] no automatic Goal creation anywhere
  - [ ] explicit confirmation step present
  - [ ] workspace context preserved (target workspace selectable/correct default)
  - [ ] Goal creation succeeds through normal use case
- Verification: Unit / Integration / E2E(insight→confirmed goal) / Device
- Evidence: — · Known Limitations: — · Notes: closes the intention loop (final product definition §16)

### P31-011 — Wrapped Entitlement
- Status: TODO · Priority: High · Depends On: P31-007
- Business Decision: FREE basic / PRO advanced+AI / POWER deeper history + share customization
- SRS: entitlements · Design: config/saas.php keys (wrapped=true currently Power; extend keys per catalog)
- Files: entitlement keys expansion (wrapped.yearly, wrapped.advanced_share, insights.*, history.depth)
- Acceptance:
  - [ ] backend enforcement tests (each tier × each capability)
  - [ ] frontend enforcement (gates hidden/disabled states)
  - [ ] downgrade does NOT delete historical data
  - [ ] expired/canceled subscription degrades gracefully to Free view
- Verification: Unit(policy matrix) / Integration(API denials) / E2E(Journey G interplay) / Device(view-only)
- Evidence: — · Known Limitations: exact numeric limits come from approved catalog (never invented)
- Notes: reuse P23 EntitlementService — NO second entitlement system (Rule 0.1)

### P31-012 — Behavioral Archetype
- Status: OPTIONAL (implement only after P31-003 proves out) · Priority: Low · Depends On: P31-003
- Business Decision: behavior-derived ONLY; explainable; NON-diagnostic. Master prompt §21 forbids
  personality/mental-state claims without explicit validated product/research basis — treat as
  DECISION_REQUIRED before any implementation
- SRS: — · Design: —
- Files: Archetype classifier + label copy
- Acceptance:
  - [ ] archetypes Builder/Finisher/Explorer/Strategist/Deep Worker derive from behavior evidence
  - [ ] evidence shown to user (why this archetype)
  - [ ] no health/psychology claims (copy review)
  - [ ] non-deterministic personality claims prohibited
- Verification: Unit(classifier thresholds) / Integration / E2E / Device(n/a)
- Evidence: — · Known Limitations: skip entirely if evidence rules cannot be met cleanly
- Notes: 

### P31-013 — P31 FINAL GATE
- Status: GATED · Priority: High · Depends On: ALL P31 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md
- Acceptance:
  - [ ] metric catalog · [ ] deterministic insight engine · [ ] analytics UI · [ ] AI narrative fallback
  - [ ] Wrapped · [ ] safe sharing · [ ] next-goal loop · [ ] entitlement · [ ] privacy · [ ] E2E
- Verification: Unit/Integration/E2E suites green + browser evidence for wrapped flow
- Evidence: — · Known Limitations: — · Notes: —

## PHASE 32 — GROWTH, EXPERIMENTATION, FEEDBACK, COMMERCIAL ANALYTICS

> Objective: measure the loop honestly (activation; retention; pricing; AI economics) with safe,
> privacy-first events. Source: KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §10 (execution authority).
> Note: funnel/retention instrumentation is shared with P37-003/P37-005 — ONE subsystem, two
> consumers (RULE 3.2: no duplicate subsystems).

### P32-001 — Product Event Taxonomy
- Status: TODO · Priority: P1 · Depends On: —
- Business Decision: safe events only; do NOT capture raw private content by default (master RULE 3.8)
- SRS: — · Design: docs/ai-architecture.md privacy boundary · Files: event catalog + instrumentation
- Acceptance — track safe events:
  - [ ] signup · verification · onboarding_complete · workspace_created · goal_created
  - [ ] breakdown_requested · proposal_accepted · task_completed · goal_progressed · review_opened
  - [ ] Wrapped_opened · Wrapped_shared · checkout_started · subscription_active
  - [ ] event names/properties/timestamp semantics documented · server-emitted · privacy-safe
- Verification: [ ] Unit(event builders) · [ ] Integration(DB write)
- Evidence: — · Known Limitations: — · Notes: no PII beyond ids; complements P37-003 instrumentation

### P32-002 — North Star Metric (WGPU)
- Status: TODO · Priority: P1 · Depends On: P32-001
- Business Decision: primary = Weekly Goal Progressing Users (WGPU): unique users in a 7-day window
  with ≥1 meaningful progress action on an active Goal
- SRS: — · Design: — · Files: WGPU definition + query/report
- Acceptance:
  - [ ] WGPU definition implemented (window; "meaningful progress action" semantics documented)
  - [ ] secondary defined: Goal-to-Execution Rate · Activation Rate · D7/D30 retention
- Verification: [ ] Unit · [ ] Integration(fixtures across week boundaries)
- Evidence: — · Known Limitations: — · Notes: shared subsystem with P37-004 adoption metrics

### P32-003 — Activation Funnel
- Status: TODO · Priority: P1 · Depends On: P32-001
- Business Decision: —
- SRS: — · Design: — · Files: funnel query/report (shared with P37-003)
- Acceptance:
  - [ ] funnel: signup → workspace → goal → task/milestone → first meaningful execution
  - [ ] report/query available (ops command or SQL view)
- Verification: [ ] Integration(seed+query)
- Evidence: — · Known Limitations: — · Notes: implementation co-located with P37-003 (single subsystem)

### P32-004 — Retention
- Status: TODO · Priority: P1 · Depends On: P32-001
- Business Decision: —
- SRS: — · Design: — · Files: retention definitions + query (shared with P37-005)
- Acceptance:
  - [ ] D1 · D7 · D30 · WAU · recurring core-loop use (timezone semantics documented)
- Verification: [ ] Integration(fixtures across day boundaries)
- Evidence: — · Known Limitations: — · Notes: implementation co-located with P37-005 (single subsystem)

### P32-005 — Pricing Analytics
- Status: TODO · Priority: P1 · Depends On: P32-001; P24 billing events
- Business Decision: LOCKED — Free = 0; Pro = 34,900; Power = 49,900 (never invent annual/trial/coupons)
- SRS: billing · Design: docs/adr/ADR-013-product-tiers-pricing.md · Files: pricing report queries
- Acceptance:
  - [ ] measure: upgrade intent · checkout start · conversion · cancellation · downgrade · churn
- Verification: [ ] Integration(billing events→report)
- Evidence: sandbox only until production · Known Limitations: — · Notes: P37-006 consumes for validation

### P32-006 — Unit Economics
- Status: TODO · Priority: P1 · Depends On: P32-005
- Business Decision: BYOK cost stays separate from Kinevo-hosted spend forever (BYOK cost is NOT
  Kinevo-hosted AI COGS); NO profitability claims (absorbed from old P29-008)
- SRS: — · Design: — · Files: economics worksheet/query (internal only)
- Acceptance — track separately:
  - [ ] subscription revenue · AI revenue · hosted AI cost · infrastructure cost · payment fees
  - [ ] support cost when measurable · storage/bandwidth if material
  - [ ] gross contribution signal computable
  - [ ] no unsupported profitability claim published anywhere
- Verification: [ ] Integration(cost tables populated)
- Evidence: — · Known Limitations: — · Notes: —

### P32-007 — AI Cost Simulator
- Status: TODO · Priority: P1 · Depends On: P25 pricing catalog/ledger
- Business Decision: the simulator determines whether the current AI quota is economically safe
- SRS: AI economics · Design: docs/ai-architecture.md · Files: simulator (internal command/report)
- Acceptance — MUST support at minimum:
  - [ ] provider · model · input tokens · cached input tokens · output tokens
  - [ ] pricing version · request frequency · plan
  - [ ] P50 scenario · P95 scenario · abuse scenario
  - [ ] output: provider cost · Kinevo estimated charge · credit consumption · margin signal
- Verification: [ ] Unit(scenarios) · [ ] Integration(pricing catalog)
- Evidence: — · Known Limitations: — · Notes: feeds P38-002 credit safety review

### P32-008 — AI Cost/Revenue Alerting
- Status: TODO · Priority: P1 · Depends On: P32-007
- Business Decision: do not depend on Notification Center if operational alerting can be simpler
- SRS: — · Design: — · Files: alert configs + tests
- Acceptance:
  - [ ] user alerts at 50% · 75% · 90% · 100% of hosted allowance
  - [ ] founder alerts: AI spend spike · per-user anomaly · payment failure spike · provider cost anomaly
- Verification: [ ] Integration(threshold crossing)
- Evidence: — · Known Limitations: — · Notes: ties to P25-010 ops alerts — extend, no duplicate

### P32-009 — Feature Feedback
- Status: TODO · Priority: P2 · Depends On: P32-001
- Business Decision: safe metadata only (route · app version · browser/device · request ID)
- SRS: — · Design: docs/design.md feedback affordances · Files: feedback endpoints + UI affordance
- Acceptance:
  - [ ] "Was this useful?" · bug report · feature feedback
  - [ ] metadata attached safely · no private content captured
- Verification: [ ] Unit · [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P32-010 — Experiments / Feature Flags
- Status: TODO · Priority: P2 · Depends On: P32-001
- Business Decision: minimal INTERNAL database-backed flags initially; no external flag service
  unless need is proven
- SRS: — · Design: — · Files: flag table + read path + admin toggle
- Acceptance:
  - [ ] flag primitive exists (DB-backed; cache-safe; entitlement-adjacent flags documented)
  - [ ] every experiment documents: hypothesis · metric · eligibility · duration · result
- Verification: [ ] Unit · [ ] Integration
- Evidence: — · Known Limitations: — · Notes: —

### P32-011 — Referral/Growth Loop
- Status: DECISION_REQUIRED · Priority: P3 · Depends On: P31-008
- Business Decision: Wrapped MAY support referral attribution; reward amounts MUST NOT be invented
  (deferred business decision — master §2.10)
- SRS: — · Design: — · Files: attribution note (design only until approved)
- Acceptance:
  - [ ] attribution design recorded · [ ] no reward amounts implemented without owner approval
- Verification: doc review
- Evidence: — · Known Limitations: — · Notes: —

### P32-012 — P32 FINAL GATE
- Status: GATED · Priority: P1 · Depends On: ALL P32 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §10 P32-012):
  - [ ] event taxonomy · [ ] WGPU · [ ] activation · [ ] retention · [ ] pricing metrics
  - [ ] AI cost simulator · [ ] cost alerts · [ ] feedback · [ ] experiments
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 33 — OPEN-SOURCE REPOSITORY SPLIT, CORE/CLOUD BOUNDARY, WEBSITE

> Objective: repository separation BEFORE P28–P39 accumulate more coupling. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §11 (execution authority). Target repos: PUBLIC
> github.com/sedam-or/Kinevo (Core) · PUBLIC github.com/sedam-or/kinevo-site (website) · PRIVATE
> github.com/sedam-or/kinevo-cloud (hosted SaaS/cloud-only infra). No separate docs repo unless
> operational evidence requires it.

### P33-001 — Repository Ownership Matrix
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: —
- SRS: — · Design: — · Files: ownership matrix doc
- Acceptance — for EVERY current root path record (source path · destination repository ·
  destination path · retain/copy/move/archive/delete · dependency reason · license implication):
  - [ ] inventory includes: README.md · LICENSE · AGENTS.md · TASK.md · docs/ · server/ · database/
        tests/ · infrastructure/ · scripts/ · .github/ · .opencode/ · environment/config files
- Verification: doc review + path inventory script
- Evidence: — · Known Limitations: — · Notes: master §26 file decision rule (8 questions) applies per file

### P33-002 — Core/Cloud Boundary
- Status: TODO · Priority: P0 · Depends On: P33-001
- Business Decision: repo open-source; SaaS infra + gateway providers externalized (absorbed from
  old P30-004). Model: Kinevo Core → stable package/module boundary → Kinevo Cloud
- SRS: — · Design: docs/architecture.md · docs/third-party/licenses.md · Files: boundary doc section
  (docs/billing.md or deployment.md) + seam implementation notes
- Acceptance:
  - [ ] actual technical seam identified FROM existing source (inspect imports first — do NOT invent
        a package boundary)
  - [ ] open-source source documented · SaaS-only infrastructure documented
  - [ ] external providers/payment gateway/AI gateway/BYOK documented
  - [ ] licensing review done · third-party attribution current · boundary documented
- Verification: [ ] license checker · [ ] boundary review
- Evidence: — · Known Limitations: — · Notes: —

### P33-003 — Migration Safety Plan
- Status: TODO · Priority: P0 · Depends On: P33-002
- Business Decision: NEVER delete first
- SRS: — · Design: — · Files: migration plan doc + execution log
- Acceptance — conceptual order (master §11 P33-003):
  - [ ] 1 freeze non-essential feature work · 2 inventory files · 3 classify ownership
  - [ ] 4 detect cross-repo dependencies · 5 create destination repos · 6 copy/migrate safe content
  - [ ] 7 establish dependency boundary · 8 tests in source+destination · 9 builds
  - [ ] 10 docs/links validation · 11 license/attribution validation · 12 functional comparison
  - [ ] 13 migration notes published · 14 only then remove/archive obsolete content
- Verification: process gate · Evidence: step log · Known Limitations: — · Notes: —

### P33-004 — AGENTS.md / TASK.md Disposition
- Status: TODO · Priority: P1 · Depends On: P33-001
- Business Decision: do NOT blindly expose private AI development instructions in the public product repo
- SRS: — · Design: — · Files: contributor-facing rules (public) + private AI/development area
- Acceptance:
  - [ ] contributor-facing rules public where useful · agent-specific operational instructions moved
        to explicit AI/development area · private workflow instructions stay private if non-public
  - [ ] TASK.md: preserve active contributor/release-relevant portions; archive excessive history
  - [ ] no data loss
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P33-005 — SRS/Design Document Disposition
- Status: TODO · Priority: P1 · Depends On: P33-001
- Business Decision: do not delete merely because it is a development document
- SRS: — · Design: — · Files: classification table
- Acceptance — classify each document: public product contract · public architecture · contributor
  development · private SaaS implementation · historical archive:
  - [ ] every doc classified
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P33-006 — Git History
- Status: TODO · Priority: P1 · Depends On: P33-003
- Business Decision: no cosmetic history rewrites; credential/secret exposure exception requires
  rewriting + immediate rotation
- SRS: — · Design: — · Files: provenance notes
- Acceptance:
  - [ ] source tags/commits documented · migration commit created · provenance preserved (if history
        spans repos)
- Verification: process review · Evidence: — · Known Limitations: — · Notes: —

### P33-007 — Open-Source License Audit
- Status: TODO · Priority: P0 · Depends On: P33-002
- Business Decision: —
- SRS: — · Design: docs/third-party/licenses.md · Files: licenses.md + attributions.md updates
- Acceptance — verify: MIT · Tiptap/ProseMirror · Excalidraw · all dependencies · fonts/assets:
  - [ ] ledger current · attributions current
- Verification: [ ] license checker
- Evidence: — · Known Limitations: — · Notes: —

### P33-008 — Core README
- Status: TODO · Priority: P1 · Depends On: P33-002
- Business Decision: —
- SRS: — · Design: — · Files: README.md (Core repo)
- Acceptance — explains: what Kinevo is · core value · architecture · screenshots · self-hosting ·
  development · contributing · license · Cloud option:
  - [ ] all sections present
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P33-009 — Product Website
- Status: TODO · Priority: P1 · Depends On: P33-002
- Business Decision: —
- SRS: — · Design: docs/design.md (public surfaces follow design authority; consult taste/
  ui-ux-pro-max skills) · Files: kinevo-site repo (pages; content; assets; components; tests)
- Acceptance:
  - [ ] https://kinevo.app · https://app.kinevo.app · https://docs.kinevo.app · status subdomain IF
        implemented
  - [ ] no DNS/domain readiness claims until actually configured
- Verification: [ ] build + preview
- Evidence: — · Known Limitations: — · Notes: —

### P33-010 — Landing Page IA
- Status: TODO · Priority: P1 · Depends On: P33-009
- Business Decision: hero positioning = intention → execution; do NOT lead with a feature dump
- SRS: — · Design: docs/design.md · Files: landing page sections
- Acceptance — sections: 1 problem · 2 transformation · 3 how it works · 4 Goal→AI→Task→Today flow ·
  5 Workspace · 6 Knowledge · 7 Canvas · 8 Analytics · 9 Wrapped · 10 open source · 11 pricing ·
  12 FAQ · 13 security/trust · 14 CTA:
  - [ ] section order per master §11 P33-010
- Verification: [ ] Browser preview evidence
- Evidence: — · Known Limitations: — · Notes: UI/UX skill consult REQUIRED (AGENTS.md)

### P33-011 — Pricing Page
- Status: TODO · Priority: P1 · Depends On: P33-010
- Business Decision: show Free — IDR 0 · Pro — IDR 34,900/month · Power — IDR 49,900/month; annual
  price omitted until approved (master §2.10)
- SRS: billing · Design: docs/design.md pricing · Files: pricing page
- Acceptance:
  - [ ] three tiers rendered · [ ] no invented pricing
- Verification: [ ] Browser evidence
- Evidence: — · Known Limitations: — · Notes: —

### P33-012 — OSS vs Cloud
- Status: TODO(doc) · Priority: P1 · Depends On: P33-009
- Business Decision: Cloud sells convenience/managed infra/reliability/support/managed AI/managed
  billing — NEVER implies self-hosting is intentionally degraded
- SRS: — · Design: — · Files: site copy section
- Acceptance:
  - [ ] self-host Kinevo Core explained · [ ] Kinevo Cloud explained
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P33-013 — Migration Validation
- Status: GATED(repos exist) · Priority: P0 · Depends On: P33-003
- Business Decision: —
- SRS: — · Design: — · Files: validation logs per repo
- Acceptance:
  - [ ] Core: clean clone → install → migrate → test → build → run
  - [ ] Cloud: clean clone → resolves Core dependency → test → build
  - [ ] Site: clean clone → build → preview
- Verification: [ ] reproducible builds all three repos
- Evidence: — · Known Limitations: — · Notes: —

### P33-014 — P33 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P33 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §11 P33-014):
  - [ ] ownership matrix · [ ] core/cloud seam · [ ] migration · [ ] docs disposition · [ ] licenses
  - [ ] README · [ ] product website · [ ] pricing · [ ] OSS/Cloud explanation · [ ] reproducible builds
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 34 — SAAS OPERATIONS, ADMIN, SUPPORT, OBSERVABILITY, ABUSE CONTROL

> Objective: operate the SaaS honestly — admin visibility without privacy violations, abuse controls,
> environment separation, runbooks. Source: KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §12 (execution
> authority).

### P34-001 — Admin Access Model
- Status: TODO(doc) · Priority: P0 · Depends On: —
- Business Decision: V1 — NO arbitrary user impersonation; NO direct raw Note browsing; NO raw Canvas
  browsing; NO raw AI prompt browsing; NO BYOK plaintext visibility
- SRS: security · Design: — · Files: admin policy doc
- Acceptance:
  - [ ] policy documented
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P34-002 — Admin Dashboard
- Status: TODO · Priority: P1 · Depends On: P34-001
- Business Decision: —
- SRS: operations · Design: docs/design.md (admin surface — minimal, token-compliant) · Files: admin dashboard
- Acceptance — minimum: users · active subscriptions · plan distribution · MRR snapshot · hosted AI
  spend · payment failures · webhook failures · email failures · backup status · system health:
  - [ ] all tiles present
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P34-003 — Subscription/Billing Diagnostics
- Status: TODO · Priority: P1 · Depends On: P34-002
- Business Decision: —
- SRS: billing · Design: — · Files: diagnostics view
- Acceptance — show: internal subscription ID · plan · provider · provider subscription reference ·
  last billing event · entitlement state · last payment status:
  - [ ] all fields present
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P34-004 — AI Operations
- Status: TODO · Priority: P1 · Depends On: P34-002
- Business Decision: aggregate/safe data only; NEVER expose secrets
- SRS: AI · Design: — · Files: AI ops view
- Acceptance — show: provider status · model · request counts · tokens · estimated spend · credit
  consumption · error rate:
  - [ ] all aggregates present
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P34-005 — Email Operations
- Status: TODO · Priority: P1 · Depends On: P34-002
- Business Decision: do NOT expose raw tokens in admin UI
- SRS: — · Design: — · Files: email ops view
- Acceptance — show: queued · sent · failed · retrying · template ID:
  - [ ] all states present
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P34-006 — Abuse/Fraud Controls
- Status: TODO · Priority: P0 · Depends On: P34-001
- Business Decision: do not build a full fraud platform without need
- SRS: security NFRs · Design: — · Files: rate limiting + quotas + suspicious activity logging
- Acceptance — protect at minimum: signup · login · password reset · AI generation · checkout
  creation · webhook endpoints · public share links:
  - [ ] rate limiting · request quotas · suspicious activity logging · provider-side controls where available
- Verification: [ ] Integration(limit enforcement)
- Evidence: — · Known Limitations: — · Notes: —

### P34-007 — Environment Separation
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: production credentials MUST NOT be used in local tests
- SRS: operations · Design: docs/environment.md · Files: env matrix + config separation
- Acceptance — explicit environments: local · development · staging · production; each with
  appropriate database · AI credential · payment credential · email configuration · storage:
  - [ ] matrix documented · [ ] separation enforced
- Verification: [ ] config review
- Evidence: — · Known Limitations: — · Notes: —

### P34-008 — Incident Runbooks
- Status: TODO · Priority: P1 · Depends On: P34-007
- Business Decision: —
- SRS: operations · Design: — · Files: runbooks/ folder
- Acceptance — runbooks for: AI outage · payment outage · webhook failure · email failure · DB
  outage · storage outage · queue outage · backup failure · security incident · account recovery
  issue · entitlement mismatch:
  - [ ] all runbooks exist · [ ] owner identified per runbook
- Verification: tabletop walkthrough (≥1) · Evidence: — · Known Limitations: — · Notes: extends P39-014

### P34-009 — Health/Alerting
- Status: TODO · Priority: P1 · Depends On: P34-007
- Business Decision: —
- SRS: operations · Design: docs/deployment.md · Files: monitor/alert configs
- Acceptance — monitor: app health · DB · queue · scheduler · storage · AI · billing · email ·
  backup · abnormal spend:
  - [ ] all monitored
- Verification: induced-failure drill per alert · Evidence: fired alerts log · Known Limitations: — · Notes: —

### P34-010 — Admin Audit Log
- Status: TODO · Priority: P1 · Depends On: P34-002
- Business Decision: —
- SRS: security · Design: — · Files: audit log table + viewer
- Acceptance — audit: entitlement correction · subscription correction · billing reconciliation ·
  account administrative action:
  - [ ] all events audited
- Verification: [ ] Integration
- Evidence: — · Known Limitations: — · Notes: —

### P34-011 — Support Channel
- Status: TODO(doc) · Priority: P2 · Depends On: —
- Business Decision: V1 recommendation — support@kinevo.app; GitHub Issues/Discussions for
  open-source; separate SaaS support from public tracker when appropriate
- SRS: — · Design: — · Files: support doc
- Acceptance:
  - [ ] channels documented
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P34-012 — Help Center Baseline
- Status: TODO · Priority: P2 · Depends On: P33-009
- Business Decision: —
- SRS: — · Design: — · Files: concise docs (site/docs subdomain)
- Acceptance — concise docs for: getting started · Goals · Today · Workspace · AI · BYOK · Billing ·
  data export · account deletion · troubleshooting:
  - [ ] all topics present
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P34-013 — P34 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P34 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §12 P34-013):
  - [ ] admin · [ ] billing diagnostics · [ ] AI ops · [ ] email ops · [ ] abuse controls
  - [ ] environment separation · [ ] runbooks · [ ] support · [ ] alerts
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 35 — ANDROID PRODUCTION HARDENING & CROSS-PLATFORM COHERENCE

> Objective: release-grade Android with web-first billing and zero secrets in the APK. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §13 (execution authority).

### P35-001 — Android Release Build
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: —
- SRS: mobile · Design: docs/mobile-architecture.md · Files: build procedures + signing checklist
- Acceptance — verify: debug · release-like · signed release procedure · versioning:
  - [ ] all build variants verified
- Verification: [ ] Device build evidence
- Evidence: — · Known Limitations: — · Notes: extends P26/P27 mobile work

### P35-002 — Android Core Loop
- Status: TODO · Priority: P0 · Depends On: P35-001
- Business Decision: —
- SRS: journeys · Design: — · Files: device test checklist
- Acceptance — must pass: login · Workspace · Goal · AI Breakdown · Today · Task · Complete · Review:
  - [ ] full loop green on device
- Verification: [ ] Device transcript
- Evidence: — · Known Limitations: — · Notes: P27 device-gate findings (UI-021) are the baseline

### P35-003 — Android Offline
- Status: TODO · Priority: P0 · Depends On: P35-002
- Business Decision: reuse existing synchronization architecture — NO second sync subsystem
- SRS: offline · Design: docs/offline-sync.md · Files: device offline tests
- Acceptance — verify: Today cache · mutation queue · Note mutation where supported · reconnect ·
  conflict:
  - [ ] all offline behaviors verified
- Verification: [ ] Device offline transcript
- Evidence: — · Known Limitations: — · Notes: IndexedDB/queue is cache, never canonical (AGENTS offline rule)

### P35-004 — Android Entitlement
- Status: TODO · Priority: P0 · Depends On: P35-002
- Business Decision: mobile MUST NOT forge entitlement locally
- SRS: billing · Design: — · Files: entitlement device tests
- Acceptance — test: Free · Pro · Power · expired · canceled/grace behavior where applicable:
  - [ ] all states verified against backend
- Verification: [ ] Device transcript per state
- Evidence: — · Known Limitations: — · Notes: —

### P35-005 — Web-First Billing
- Status: TODO · Priority: P0 · Depends On: P35-004
- Business Decision: Android v1 has NO native subscription checkout
- SRS: billing · Design: docs/design.md · Files: Android billing boundary UI
- Acceptance — Android can: view plan · view subscription · manage subscription (web hand-off) ·
  receive web entitlement:
  - [ ] all flows verified
- Verification: [ ] Device evidence
- Evidence: — · Known Limitations: — · Notes: user communication for web-first billing (master gap #47)

### P35-006 — Android AI Security
- Status: TODO · Priority: P0 · Depends On: P35-001
- Business Decision: the Android app MUST NEVER contain DeepSeek/OpenCode/OmniRouter/Midtrans/
  production SMTP secrets. Flow: Android → Kinevo backend → provider/gateway
- SRS: security · Design: docs/ai-architecture.md · Files: APK inspection evidence
- Acceptance:
  - [ ] APK/release artifact scanned — no secrets
  - [ ] all AI traffic via backend
- Verification: [ ] artifact scan + network evidence
- Evidence: — · Known Limitations: — · Notes: —

### P35-007 — Android Device Matrix
- Status: TODO · Priority: P1 · Depends On: P35-002
- Business Decision: —
- SRS: — · Design: — · Files: device matrix record
- Acceptance — test at minimum: small phone · typical phone · large phone:
  - [ ] exact devices + Android API versions recorded
- Verification: [ ] Device transcripts
- Evidence: — · Known Limitations: — · Notes: —

### P35-008 — P35 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P35 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §13 P35-008):
  - [ ] release build · [ ] core loop · [ ] offline · [ ] entitlement · [ ] AI security
  - [ ] billing boundary · [ ] device evidence
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 36 — COMPLIANCE, LEGAL/TRUST SURFACES, PRODUCTION POLICY READINESS

> Objective: close non-code trust gaps before public paid launch. Does NOT invent legal conclusions —
> converts known operational requirements into explicit product surfaces and review items. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §14 (execution authority).

### P36-001 — Terms of Service Surface
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: no legal claims beyond reviewable policy text; final wording subject to legal review
- SRS: — · Design: docs/design.md trust surfaces · Files: ToS page
- Acceptance — document: owner/provider identity · service scope · subscription basics · user
  responsibilities · acceptable-use boundary · termination · support path · limitations:
  - [ ] all areas present
- Verification: doc review · Evidence: — · Known Limitations: legal review pending · Notes: —

### P36-002 — Privacy Notice
- Status: TODO · Priority: P0 · Depends On: P30-005
- Business Decision: keep one source of truth with P30-005 (no duplicate policy)
- SRS: — · Design: — · Files: privacy notice page
- Acceptance — cover: account data · product usage telemetry · AI requests · BYOK processing ·
  payment provider · email provider · analytics provider · retention · deletion · exports:
  - [ ] all areas covered
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P36-003 — AI Use Policy
- Status: TODO · Priority: P1 · Depends On: P36-002
- Business Decision: AI is assistive, NOT authoritative; proposal approval workflow applies
- SRS: AI chapters · Design: docs/ai-architecture.md · Files: AI policy page
- Acceptance — explain: hosted AI · BYOK · provider routing · what content is sent for a requested
  AI operation · assistive-not-authoritative · proposal approval:
  - [ ] all areas present
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P36-004 — Acceptable Use / Abuse
- Status: TODO · Priority: P1 · Depends On: P36-001
- Business Decision: —
- SRS: security · Design: — · Files: acceptable use section
- Acceptance — define prohibited behaviors appropriate to the product: automated abuse · credential
  theft · malicious payloads · payment fraud · prompt abuse for prohibited content where applicable:
  - [ ] all areas present
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P36-005 — Cookie/Analytics Policy
- Status: TODO · Priority: P1 · Depends On: P36-002
- Business Decision: document ACTUAL cookies/storage used; do NOT ship consent mechanisms for
  technologies not actually used
- SRS: — · Design: — · Files: cookie/storage policy section
- Acceptance:
  - [ ] actual technologies documented
- Verification: doc review + technical audit · Evidence: — · Known Limitations: — · Notes: —

### P36-006 — Data Processor / Third-Party Inventory
- Status: TODO(doc) · Priority: P0 · Depends On: P36-002
- Business Decision: —
- SRS: — · Design: docs/third-party/licenses.md (adjacent) · Files: processor inventory doc
- Acceptance — inventory: Midtrans · AI gateway/provider · email service · hosting · object storage ·
  analytics — for each: purpose · category of data · region/hosting info if known · provider
  privacy/terms link where appropriate:
  - [ ] all processors listed
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P36-007 — Tax / Invoice Review Flag
- Status: TODO(doc) · Priority: P1 · Depends On: P36-001
- Business Decision: do NOT invent tax treatment
- SRS: billing · Design: — · Files: review list doc
- Acceptance:
  - [ ] tax requirements needing professional/accounting review recorded
  - [ ] invoice/receipt behavior required by the chosen payment provider recorded
- Verification: doc review · Evidence: — · Known Limitations: DECISION_REQUIRED (professional review) · Notes: —

### P36-008 — Payment User Trust
- Status: TODO · Priority: P0 · Depends On: P24/P35-005 surfaces
- Business Decision: never hide recurring nature
- SRS: billing · Design: docs/design.md billing · Files: billing UI audit + fixes
- Acceptance — billing UI clearly shows: price · recurring interval · current period · renewal date ·
  cancellation status · payment status:
  - [ ] all fields present and understandable
- Verification: [ ] Browser evidence
- Evidence: — · Known Limitations: — · Notes: master §24 billing UX standard applies

### P36-009 — P36 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P36 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §14 P36-009):
  - [ ] Terms · [ ] Privacy · [ ] AI policy · [ ] Acceptable use · [ ] analytics/cookie documentation
  - [ ] provider inventory · [ ] tax review list · [ ] billing transparency
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 37 — PUBLIC BETA & PRODUCT-MARKET VALIDATION

> Objective: validate whether users repeatedly obtain value. NOT uncontrolled feature development
> (Rule 0.7). Requires REAL USERS — measurement/instrumentation tasks are buildable; conclusions
> require beta traffic. Renumbered from old "PHASE 29" per KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md
> (execution authority §0/§15).

### P37-001 — Target User
- Status: TODO(doc) · Priority: Medium · Depends On: —
- Business Decision: Indonesia-first; individual users; multiple goals/projects; fragmented tools
- SRS: product definition · Design: —
- Files: docs/… persona/profile note
- Acceptance:
  - [ ] target user profile documented
  - [ ] exclusions documented (teams/enterprise explicitly OUT per locked decisions)
- Verification: Doc review · Evidence: — · Known Limitations: refined by research (P37-008) · Notes: —

- Verification: Doc review · Evidence: — · Known Limitations: refined by research (P37-008) · Notes: —

### P37-002 — Beta Cohort
- Status: TODO(doc) · Priority: High · Depends On: P37-001
- Business Decision: cohort parameters are an owner decision — agent records, never invents
- SRS: — · Design: — · Files: beta plan section (acquisition; size; window; support method)
- Acceptance:
  - [ ] acquisition source defined · [ ] cohort size defined · [ ] test window defined
  - [ ] support method defined · [ ] consent/communications where relevant documented
- Verification: doc review · Evidence: — · Known Limitations: execution GATED on real users · Notes: —

### P37-003 — Activation
- Status: TODO · Priority: High · Depends On: P37-001
- Business Decision: canonical activation = signup → workspace → Goal → Task/milestone → first
  meaningful execution (master prompt §15 P37-003)
- SRS: — · Design: — · Files: event instrumentation service + tests; funnel query/report command
- Acceptance:
  - [ ] exact event definition written (event names, properties, timestamp semantics)
  - [ ] instrumentation implemented (server-emitted, privacy-safe)
  - [ ] test event verified end-to-end
  - [ ] funnel measures: Goal created → Breakdown → Task created → Task executed → Task completed → repeat use
        (merged from old P29-003)
  - [ ] report/query available (ops command or SQL view)
- Verification: Unit(event builders) / Integration(DB write; seed+query) / E2E(signup→activation emits) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: no PII in event payloads beyond ids; sample funnel run captured

### P37-005 — Retention
- Status: TODO · Priority: High · Depends On: P37-003
- Business Decision: — · SRS: — · Design: — · Files: retention query/command
- Acceptance:
  - [ ] definitions documented (D1/D7/D30/WAU/recurring core-loop usage with timezone semantics)
  - [ ] report/query available
- Verification: Integration(fixtures across day boundaries) · Evidence: — · Known Limitations: — · Notes: —

### P37-004 — North Star WGPU + Adoption Metrics
- Status: TODO · Priority: High · Depends On: P37-003; P25 ledger
- Business Decision: primary north star = Weekly Goal Progressing Users (WGPU — unique users in a
  7-day window with ≥1 meaningful progress action on an active Goal, master prompt §10 P32-002/§15
  P37-004). Secondary: Goal-to-Execution Rate; AI adoption; Workspace adoption. BYOK adoption tracked
  WITHOUT consuming hosted credits — distinction preserved (merged from old P29-005/006)
- SRS: AI chapters · Design: docs/ai-architecture.md · Files: WGPU + adoption counters/reports
- Acceptance:
  - [ ] WGPU definition implemented (7-day window; meaningful progress action semantics documented)
  - [ ] secondary: Goal-to-Execution Rate · Activation Rate · D7/D30 retention (retention detail in P37-005)
  - [ ] tracks: AI provider setup; Goal Breakdown usage; proposal acceptance; hosted credit consumption;
        BYOK adoption
  - [ ] tracks: workspace creation; second-workspace creation; switching frequency; scoped work share
  - [ ] no unnecessary raw prompt storage (metadata only)
- Verification: Unit / Integration(ai_runs aggregates) / E2E / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: extends P25 observability, no duplication

### P37-006 — Pricing Validation
- Status: TODO(instrumentation)/GATED(real signups) · Priority: High · Depends On: P24 billing live
- Business Decision: LOCKED PRICES — Free Rp0; Pro Rp34,900/month; Power Rp49,900/month.
  Annual price/discount MUST NOT be invented (DECISION_REQUIRED blocklist §13)
- SRS: billing · Design: docs/adr/ADR-013-product-tiers-pricing.md · Files: pricing report query
- Acceptance:
  - [ ] pricing catalog is authoritative (config/billing.php + saas.php — already locked)
  - [ ] metrics instrumented: signup by tier; upgrade intent; conversion; cancellation; downgrade; churn;
        AI cost/user
- Verification: Integration(billing events→report) · Evidence: sandbox transactions only · Notes: —

- Verification: Integration(billing events→report) · Evidence: sandbox transactions only · Notes: —

### P37-007 — Power Validation
- Status: TODO · Priority: High · Depends On: P37-006
- Business Decision: Power = higher capacity + deeper intelligence; do NOT add arbitrary features when
  users misunderstand Power — test messaging/packaging FIRST (master prompt §15 P37-007)
- SRS: — · Design: docs/design.md pricing surfaces · Files: messaging test notes + findings
- Acceptance:
  - [ ] users understand Pro = serious capability (validated)
  - [ ] users understand Power = higher capacity + deeper intelligence (validated)
  - [ ] messaging/packaging tested before any feature response
- Verification: n/a (research; GATED real users) · Evidence: test findings · Known Limitations: — · Notes: —

### P37-008 — User Comprehension Study
- Status: GATED(real users) · Priority: High · Depends On: beta cohort
- Business Decision: — · SRS: — · Design: — · Files: interview script + findings doc
- Acceptance:
  - [ ] validate: what Kinevo is; first-value comprehension; Workspace understanding;
        Goal Breakdown understanding; Today usefulness; AI trust; pricing comprehension
  - [ ] research summary written · [ ] top blockers ranked
- Verification: n/a (research) · Evidence: interviews/sessions recordings notes · Known Limitations: — · Notes: —

### P37-009 — Failure/Churn Taxonomy
- Status: TODO · Priority: Medium · Depends On: P37-008 start
- Business Decision: — · SRS: — · Design: — · Files: taxonomy section in research doc
- Acceptance:
  - [ ] taxonomy categories: technical; UX; product value; pricing; AI quality; performance; missing workflow
  - [ ] incidents classified as they occur (living log)
- Verification: n/a · Evidence: classification log · Known Limitations: — · Notes: —

### P37-010 — Beta Feature Freeze
- Status: ACTIVE ON BETA START · Priority: High · Depends On: P37 go-live
- Business Decision: freeze respects rescue-phase-style discipline
- SRS: — · Design: — · Files: TASK.md hold-list section
- Acceptance:
  - [ ] only P0/P1 defects, validated UX fixes, reliability fixes allowed
  - [ ] new features require explicit owner decision
  - [ ] beta hold-list enforced (recorded here)
- Verification: process gate · Evidence: exemption log · Known Limitations: — · Notes: —

### P37-011 — P37 FINAL GATE
- Status: GATED · Priority: High · Depends On: ALL P37 + real cohort
- Business Decision: — · SRS: — · Design: — · Files: TASK.md
- Acceptance (master prompt §15 P37-011):
  - [ ] beta cohort · [ ] activation · [ ] WGPU · [ ] retention · [ ] pricing evidence
  - [ ] Power differentiation · [ ] UX research/comprehension · [ ] churn taxonomy · [ ] feature freeze
- Verification: reports present + research concluded · Evidence: dashboard/exports · Notes: gate binary

## PHASE 38 — SCALE READINESS, COST/CAPACITY, RELIABILITY, RELEASE CANDIDATE

> Objective: prove the system can carry real load and safe economics, then freeze. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §16 (execution authority).

### P38-001 — AI Capacity Review
- Status: TODO · Priority: P0 · Depends On: P25/P32-007
- Business Decision: —
- SRS: AI · Design: docs/ai-architecture.md · Files: capacity review doc
- Acceptance — evaluate: concurrent AI requests · queue behavior · provider rate limits · model
  latency · token limits · worst-case user:
  - [ ] all evaluated with evidence
- Verification: [ ] Integration/measurements
- Evidence: — · Known Limitations: — · Notes: —

### P38-002 — AI Credit Safety Review
- Status: TODO · Priority: P0 · Depends On: P32-007
- Business Decision: if unsafe → change CONFIGURABLE limits before release and record the decision
  (never silent)
- SRS: AI economics · Design: — · Files: safety review per plan
- Acceptance — for each plan evaluate:
  - [ ] P50 cost · P95 cost · abuse cost · worst supported request · monthly revenue · gross AI margin
- Verification: [ ] simulator runs recorded
- Evidence: — · Known Limitations: — · Notes: —

### P38-003 — Storage/Bandwidth Review
- Status: TODO · Priority: P1 · Depends On: —
- Business Decision: —
- SRS: — · Design: — · Files: storage review doc
- Acceptance — evaluate: workspace count · note storage · canvas files · exports · analytics
  retention · mobile payload size:
  - [ ] all evaluated
- Verification: measurements recorded · Evidence: — · Known Limitations: — · Notes: —

### P38-004 — Database Review
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: —
- SRS: — · Design: database/migrations/ · Files: review doc
- Acceptance — check: indexes · slow queries · ownership filters · large-table growth path ·
  migration safety:
  - [ ] all checked
- Verification: [ ] EXPLAIN/audit evidence
- Evidence: — · Known Limitations: — · Notes: —

### P38-005 — Queue Review
- Status: TODO · Priority: P1 · Depends On: —
- Business Decision: —
- SRS: reliability · Design: docs/deployment.md · Files: review doc
- Acceptance — check: retries · poison jobs · maximum attempts · dead-letter/recovery behavior ·
  observability:
  - [ ] all checked
- Verification: [ ] induced-failure evidence
- Evidence: — · Known Limitations: — · Notes: —

### P38-006 — Cache Review
- Status: TODO · Priority: P1 · Depends On: —
- Business Decision: cache is NEVER canonical
- SRS: — · Design: docs/architecture.md · Files: review doc
- Acceptance:
  - [ ] cache not canonical · tenant/user/workspace isolation preserved · invalidation strategy explicit
- Verification: [ ] review + tests
- Evidence: — · Known Limitations: — · Notes: —

### P38-007 — Load / Soak Test Baseline
- Status: TODO · Priority: P1 · Depends On: P38-004; P38-005
- Business Decision: do NOT claim scale numbers that were not tested
- SRS: — · Design: — · Files: load test scripts + results
- Acceptance:
  - [ ] representative workload tested
- Verification: [ ] load run transcripts
- Evidence: — · Known Limitations: — · Notes: —

### P38-008 — Security Regression
- Status: TODO · Priority: P0 · Depends On: features frozen (P38-009)
- Business Decision: —
- SRS: security NFRs · Design: — · Files: regression test matrix
- Acceptance — run: auth · IDOR · workspace isolation · entitlement bypass · API key leak checks ·
  payment webhook spoof · export leak · public share leak:
  - [ ] all negative cases green
- Verification: [ ] targeted test matrix
- Evidence: — · Known Limitations: — · Notes: —

### P38-009 — Release Candidate (Freeze)
- Status: TODO · Priority: P0 · Depends On: P28–P37 gates
- Business Decision: freeze major behavior; allowed exceptions: P0/P1 · security · data integrity ·
  release blockers (absorbs old P30-001 Release Freeze)
- SRS: — · Design: — · Files: freeze announcement note + exception log
- Acceptance:
  - [ ] freeze announced · [ ] exceptions recorded
- Verification: process gate · Evidence: exception log · Known Limitations: — · Notes: —

### P38-010 — P38 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P38 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §16 P38-010):
  - [ ] AI capacity · [ ] economics · [ ] storage · [ ] DB · [ ] queue · [ ] cache
  - [ ] load evidence · [ ] security regression · [ ] RC freeze
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 39 — V1.0 PRODUCTION RELEASE

> Objective: stable Indonesia-first Kinevo SaaS with Web + Android. Operator approval REQUIRED for
> the tag; agent never tags/releases autonomously (AGENTS release lifecycle).
> Renumbered from old "PHASE 30" per KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md (execution authority
> §0/§17). Old P30-001 Release Freeze moved to P38-009; old P30-004 OSS/SaaS boundary moved to P33-002.

### P39-001 — Semantic Versioning
- Status: TODO(doc) · Priority: Medium · Depends On: —
- Business Decision: SemVer; app version from latest v* git tag (AGENTS lifecycle)
- SRS: — · Design: docs/release-management.md (exists — align, do not duplicate)
- Files: docs/release-management.md updates
- Acceptance:
  - [ ] major/minor/patch policy documented · [ ] manifests aligned
- Verification: make version-check green · Evidence: — · Notes: —

### P39-002 — Changelog
- Status: TODO · Priority: High · Depends On: P39-001
- Business Decision: pricing changes; AI policy; mobile availability; known limitations included
- SRS: — · Design: Keep a Changelog · Files: CHANGELOG.md release cut
- Acceptance:
  - [ ] categories Added/Changed/Fixed/Security/Deprecated/Removed complete
  - [ ] pricing · AI policy · mobile availability · known limitations present
- Verification: make changelog-check green · Evidence: — · Notes: —

### P39-003 — Release Notes
- Status: TODO · Priority: Medium · Depends On: P39-002
- Business Decision: publishes Free/Pro/Power pricing; AI usage policy; BYOK; Android availability;
  Wrapped; known limitations; support channels
- SRS: — · Design: — · Files: RELEASE_NOTES or GitHub Release body draft
- Acceptance:
  - [ ] release summary written · [ ] reviewed before publish
- Verification: doc review · Evidence: — · Notes: publishing remains manual/operator action

### P39-004 — Production Migration Dry Run
- Status: BLOCKED(needs prod-like backup) · Priority: High · Depends On: infrastructure access
- Business Decision: — · SRS: — · Design: docs/deployment.md · Files: drill script/log
- Acceptance:
  - [ ] restore backup succeeds · [ ] migrate succeeds · [ ] validate · [ ] smoke
  - [ ] data integrity verified
- Verification: drill transcript · Evidence: timestamps/log excerpts · Known Limitations: — · Notes: RPO/RTO noted

### P39-005 — Web E2E
- Status: TODO · Priority: High · Depends On: suites stable
- Business Decision: — · SRS: journeys · Design: docs/browser-e2e.md
- Files: Playwright/spec expansion
- Acceptance — critical journeys all green:
  - [ ] Login · [ ] Workspace · [ ] Goal · [ ] AI · [ ] Milestone · [ ] Task · [ ] Today · [ ] Note
  - [ ] Canvas · [ ] Schedule · [ ] Analytics · [ ] Billing · [ ] Entitlement · [ ] Wrapped
  - [ ] Chromium green · [ ] Firefox green · [ ] WebKit green
- Verification: E2E x3 engines · Evidence: CI/local run logs · Notes: —

### P39-006 — Android E2E
- Status: BLOCKED(device+release APK) · Priority: High · Depends On: P26-011/P27 gates · P35 evidence
- Business Decision: Android-first v1 · SRS: — · Design: — · Files: E2E device checklist
- Acceptance:
  - [ ] install · [ ] login · [ ] Workspace · [ ] Goal · [ ] AI · [ ] Today · [ ] Task · [ ] Note
  - [ ] offline · [ ] reconnect · [ ] entitlement
  - [ ] release-like APK used · [ ] representative device · [ ] no P0/P1 crash
- Verification: Device smoke transcript · Evidence: — · Notes: —

### P39-007 — Billing E2E
- Status: BLOCKED(sandbox credentials + real webhook run) · Priority: High · Depends On: P24 + P26-006
- Business Decision: web checkout only; one subscription covers Web+Android. Midtrans Sandbox
  evidence MUST be labeled SANDBOX; production merchant/webhook/credential separation verified
  before launch (master prompt §17 P39-007)
- SRS: billing · Design: docs/adr/ADR-012 · Files: E2E spec
- Acceptance:
  - [ ] Free→Pro purchase on web → verified webhook → subscription active → entitlement active
        → Android login → Pro access
  - [ ] Power path · [ ] cancellation · [ ] downgrade · [ ] expiration covered
  - [ ] production readiness verification recorded (merchant status; webhook endpoint; credential
        separation; current provider capability)
- Verification: sandbox evidence (labeled SANDBOX) · webhook evidence · entitlement evidence · cross-device evidence
- Evidence: — · Known Limitations: — · Notes: mirrors JOURNEY B/C/E/G/F

### P39-008 — AI Economics E2E
- Status: TODO · Priority: High · Depends On: P25 ledger + P39-007 env
- Business Decision: hosted consumes credits; BYOK does NOT; both stay safeguarded
- SRS: AI chapters · Design: docs/ai-architecture.md · Files: E2E assertions on ledger
- Acceptance:
  - [ ] Free hosted AI · [ ] Pro hosted AI · [ ] Power hosted AI · [ ] Pro BYOK · [ ] Power BYOK
  - [ ] usage ledger proves correct classification (ledger=kinevo|byok, credits_consumed correctness)
  - [ ] no raw secret leak anywhere in responses/logs
- Verification: Unit(existing AiUsage/AiAlerts suites) / Integration / E2E / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: extends JOURNEY D

### P39-009 — Email E2E
- Status: TODO · Priority: High · Depends On: P29-005 templates + P39-004 env
- Business Decision: — · SRS: identity · Design: — · Files: E2E assertions (mail catcher/provider)
- Acceptance:
  - [ ] verification · [ ] reset · [ ] welcome · [ ] security · [ ] billing · [ ] failure path
- Verification: Integration(queue→send) / E2E · Evidence: send transcripts · Known Limitations: — · Notes: —

### P39-010 — Data Ownership E2E
- Status: TODO · Priority: High · Depends On: P30-002/P30-003/P29-008 implementation
- Business Decision: — · SRS: data ownership · Design: — · Files: E2E spec
- Acceptance:
  - [ ] export · [ ] account deletion · [ ] deletion grace period · [ ] recovery/cancel deletion
- Verification: E2E · Evidence: run logs · Known Limitations: — · Notes: —

### P39-011 — Security Final Audit
- Status: TODO · Priority: High · Depends On: features frozen
- Business Decision: — · SRS: security NFRs · Design: SECURITY.md (disclosure) · Files: audit report doc
- Acceptance — all negative cases have expected results:
  - [ ] IDOR · [ ] cross-workspace · [ ] entitlement bypass · [ ] price tampering
  - [ ] fake payment success · [ ] invalid webhook · [ ] duplicate webhook · [ ] BYOK leak
  - [ ] billing-secret leak · [ ] Wrapped leak · [ ] unauthorized deep link · [ ] expired-subscription bypass
- Verification: targeted test matrix executed · Evidence: report · Notes: no open P0/P1 findings allowed

### P39-012 — Backup/Restore Final Drill
- Status: BLOCKED(prod infra) · Priority: High · Depends On: P39-004 environment
- Business Decision: — · SRS: durability NFRs · Design: docs/deployment.md · Files: drill log
- Acceptance:
  - [ ] restore success across: user; workspace; goals; tasks; Notes; Canvas; subscription; entitlement;
        AI usage; billing events
  - [ ] integrity verification · [ ] RPO/RTO evidence
- Verification: drill transcript · Evidence: — · Notes: —

### P39-013 — Monitoring and Alerts
- Status: TODO · Priority: High · Depends On: infra
- Business Decision: — · SRS: operations · Design: docs/deployment.md · Files: alert configs + test log
- Acceptance (alerts configured AND tested):
  - [ ] app down · [ ] database unhealthy · [ ] queue failure · [ ] scheduler failure · [ ] AI outage
  - [ ] payment webhook failure · [ ] backup failure · [ ] abnormal AI spend (ties to P25-010 ops alerts)
  - [ ] abnormal payment failure
- Verification: induced-failure drill per alert · Evidence: fired alerts log · Notes: —

### P39-014 — Support/Incident Runbooks
- Status: TODO · Priority: Medium · Depends On: P39-013
- Business Decision: — · SRS: — · Design: — · Files: runbooks/ folder
- Acceptance:
  - [ ] runbooks exist: payment mismatch; AI unavailable; account issue; entitlement mismatch;
        mobile issue; data issue; rollback
  - [ ] owner identified per runbook
- Verification: tabletop walkthrough (at least rollback) · Evidence: — · Notes: —

### P39-015 — Production Configuration Verification
- Status: BLOCKED(prod secrets/infra) · Priority: High · Depends On: deploy target
- Business Decision: never commit secrets · SRS: — · Design: docs/environment.md · Files: checklist
- Acceptance:
  - [ ] APP_KEY · DB · storage · mail · AI gateway · payment gateway · TLS · backup · monitoring verified
  - [ ] no secrets baked into image · [ ] health endpoint passes · [ ] production smoke passes
- Verification: checklist transcript · Evidence: — · Notes: —

### P39-016 — Production RC Checklist
- Status: GATED · Priority: High · Depends On: ALL phase gates (P28–P38)
- Business Decision: — · SRS: — · Design: — · Files: RC checklist compilation
- Acceptance — ALL applicable checked (master prompt §17 P39-016):
  - [ ] product · [ ] UX · [ ] identity · [ ] email · [ ] data ownership · [ ] analytics · [ ] AI
  - [ ] billing · [ ] open-source split · [ ] website · [ ] admin · [ ] Android · [ ] security
  - [ ] backup · [ ] monitoring · [ ] support · [ ] documentation
- Verification: checklist signed with per-item evidence links · Evidence: — · Known Limitations: — · Notes: —

### P39-017 — v1.0.0 Tag
- Status: BLOCKED(operator approval mandatory) · Priority: High · Depends On: P39-020 gate green
- Business Decision: tag only after ALL gates · SRS: — · Design: docs/release-management.md · Files: git tag v1.0.0
- Acceptance:
  - [ ] tag created · [ ] changelog tied · [ ] release notes attached · [ ] reproducible build evidenced
- Verification: make release-dry-run green beforehand · Evidence: — · Notes: agent NEVER auto-tags

### P39-018 — Rollback Procedure
- Status: TODO(doc)+drill · Priority: High · Depends On: P39-014
- Business Decision: — · SRS: reliability · Design: docs/deployment.md · Files: rollback runbook
- Acceptance:
  - [ ] trigger criteria · operator roles · deploy rollback steps · DB considerations · customer impact ·
        communications template — all documented
  - [ ] tabletop OR real drill performed
- Verification: drill log · Evidence: — · Notes: —

### P39-019 — Post-Release Review
- Status: GATED(post-release) · Priority: Medium · Depends On: v1.0.0 tag
- Business Decision: no immediate feature expansion after release (master prompt §17 P39-019)
- SRS: — · Design: — · Files: post-release review record
- Acceptance:
  - [ ] actual incidents recorded · [ ] unresolved P1s recorded · [ ] early usage noted
  - [ ] AI cost noted · [ ] conversion noted · [ ] support volume noted
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P39-020 — P39 FINAL RELEASE GATE
- Status: GATED · Priority: High · Depends On: EVERYTHING above
- Business Decision: v1.0 releases ONLY when all gates green
- SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §17 P39-020):
  - [ ] TECHNICAL: CI green · migrations verified · production smoke pass
  - [ ] PRODUCT: core loop coherent · onboarding works · feature purposes clear
  - [ ] UX: empty states · CTA hierarchy · personalization · micro-interactions · accessibility
  - [ ] IDENTITY: email verification · password reset · account deletion
  - [ ] AI: provider abstraction · credits · cost metering · BYOK · safeguards
  - [ ] BILLING: Free · Pro 34,900 · Power 49,900 · Midtrans production readiness
  - [ ] MOBILE: release-like build · same entitlement · offline/reconnect
  - [ ] OPEN SOURCE: core repo · cloud repo · product site
  - [ ] TRUST: Terms · Privacy · AI policy · provider inventory
  - [ ] OPERATIONS: admin · monitoring · backups · support
  - [ ] BUSINESS: activation instrumentation · retention instrumentation · unit economics
- Verification: compiled gate report citing each subsystem's evidence · Evidence: —
- Known Limitations: — · Notes: no silent weakening of any gate

Execution rule: sequential P21→P22→…; P26/P27 may parallelize only after API/security stability per
roadmap §3. Post-P27 execution authority: KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md — phases run
P28→P39 in order unless a dependency-safe exception is recorded here.
