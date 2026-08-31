# KINEVO — POST-P27 MASTER EXECUTION PROMPT
# P28–P39
# PRODUCT EXPERIENCE → IDENTITY/TRUST → DATA OWNERSHIP → INTELLIGENCE
# → GROWTH → OPEN-SOURCE SPLIT → CLOUD OPERATIONS → ANDROID HARDENING
# → BETA → COMPLIANCE/TRUST → V1.0 RELEASE

> **SUPERSEDED (2026-08-31):** execution authority for P28 closure → P39 RC now lives in
> `KINEVO_MASTER_EXECUTION_PROMPT_V3.md` (owner-issued umbrella prompt). This file is preserved
> as historical planning evidence; its phase numbering/rebaseline decisions referenced by
> `TASK.md` remain historical record. Do not execute new work against this file.

## 0. PURPOSE

This document supersedes prior generic post-P27 roadmaps.
It is the execution authority for all remaining work after P27 unless a later
explicit business decision or ADR supersedes a specific section.

The objective is NOT to add features indefinitely.
The objective is to transform the existing Kinevo implementation into one
coherent, trustworthy, economically controlled, open-source-friendly,
commercially operable personal SaaS.

The core product promise is:

> Kinevo turns intentions into execution.

The core product loop is:

    intention
      ↓
    workspace/context
      ↓
    goal
      ↓
    AI breakdown / planning assistance
      ↓
    milestones / programs
      ↓
    tasks
      ↓
    schedule
      ↓
    today
      ↓
    execution
      ↓
    progress
      ↓
    review
      ↓
    insight
      ↓
    next intention

Every phase below exists to strengthen that loop, not fragment it.

---

# 1. CURRENT STATE AND ASSUMPTIONS

At the beginning of this roadmap:

- P18–P27 have been implemented or substantially implemented.
- P27 is the latest mobile architecture/MVP phase.
- P28 onward have NOT yet been fully implemented.
- Midtrans Sandbox is already used successfully for dummy payment API testing.
- Production Midtrans capability is NOT inferred from Sandbox success.
- Kinevo uses a Laravel + PostgreSQL backend with Vue/TypeScript web frontend.
- Kinevo already has AI provider abstraction, offline architecture, Knowledge,
  Notes, Canvas, scheduling, observability, and entitlement foundations.
- Kinevo is evolving from a personal project into an Indonesia-first SaaS.
- Kinevo remains a single-user/personal product through v1.

---

# 2. LOCKED BUSINESS DECISIONS

These MUST NOT be changed silently by an AI agent.

> **DELTA 2026-08-28 (`revisi-finance.md`, owner):** §2.1 prices are superseded —
> Free = IDR 0; **Pro = IDR 49,900/month; Power = IDR 89,900/month** (launch
> hypotheses, beta-validated; never claim "final market price"). §2.2 hosted AI
> credit numbers (20/150/500) are reclassified **DEPRECATED BASELINE** — the
> exact AI allowance MUST be derived from the required AI cost simulation
> (provider/model pricing; cache behavior; P50–P99 usage; target contribution
> margin ~30–50% configurable; markup ≠ margin) before being locked. Workspace
> baseline (1/5/15) stands. Two separate ledgers (subscription vs AI usage)
> remain mandatory; BYOK matrix unchanged (Free NO / Pro YES / Power YES).
> Current contract: `docs/billing.md` § Pricing Delta + `TASK.md` § COMMERCIAL
> PRICING DELTA (D-001…D-008).

## 2.1 Tiers

FREE
- IDR 0 / month

PRO
- IDR 34,900 / month

POWER
- IDR 49,900 / month

## 2.2 Baseline entitlements

FREE:
- 1 workspace
- 20 hosted AI credits per applicable billing period
- no BYOK
- basic analytics
- basic insights
- basic Wrapped
- 30-day baseline history
- mobile access

PRO:
- 5 workspaces
- 150 hosted AI credits per applicable billing period
- BYOK enabled
- advanced analytics
- advanced insights
- yearly Wrapped
- advanced share capabilities
- 1-year baseline history
- mobile access

POWER:
- 15 workspaces
- 500 hosted AI credits per applicable billing period
- BYOK enabled
- advanced analytics
- advanced insights
- yearly Wrapped
- expanded share capabilities
- 3-year baseline history
- mobile access

These are launch baselines and MUST exist as configurable commercial data,
not scattered literals.

## 2.3 AI accounting

Hosted Kinevo AI:
- consumes Kinevo-hosted AI credits.

BYOK:
- does NOT consume Kinevo-hosted AI credits.
- still subject to rate limits, token/context limits, timeout, abuse controls,
  and request-size limits.

Kinevo AI credits:
- are an internal economic unit.
- are NOT equivalent to a fixed number of tokens.

AI tokens:
- are usage telemetry.

## 2.4 AI economics

The architecture MUST support:
- provider/model pricing catalog
- versioned pricing
- estimated provider cost
- Kinevo customer AI charge where applicable
- usage ledger
- included allowance
- optional prepaid AI balance if implemented
- BYOK classification

Never use:
- one universal hardcoded token price
- floating point for money
- request count as a universal cost unit

## 2.5 Mobile

- Android first.
- Web is the full planning/deep-work environment.
- Android is capture/decide/execute/review oriented.
- One backend only.
- Same entitlement across web and Android.
- V1 mobile billing is WEB-FIRST.
- Android v1 does NOT implement native Google Play subscription checkout.
- Future Apple/Google billing must remain an extension boundary, not an
  assumption.

## 2.6 Product scope

Through v1:
- single user
- no teams
- no organizations
- no shared workspace
- no RBAC
- no enterprise plan

## 2.7 Geography and language

- Indonesia-first
- IDR
- Bahasa Indonesia
- English

## 2.8 Open-source model

Preferred locked architecture:

- Kinevo Core = genuinely open-source/self-hostable
- Kinevo Cloud = managed SaaS
- Product website = separate public marketing property

Cloud MUST sell convenience, managed infrastructure, reliability, support,
managed AI, and managed billing rather than intentionally crippling the core.

## 2.9 Payment provider

- Midtrans is the current Sandbox/testing provider.
- The P24 architecture remains provider-abstracted.
- Production activation/capabilities MUST be verified before release.

## 2.10 Deferred business decisions

Do NOT invent:
- annual price
- annual discount
- trial duration
- coupons/promotions
- Team pricing
- Enterprise pricing
- native Apple subscription launch
- native Google Play subscription launch
- future marketplace pricing

These remain DECISION_REQUIRED / DEFERRED.

---

# 3. ABSOLUTE AGENT RULES

## RULE 3.1 — INSPECT BEFORE MODIFYING

Before changing anything:

1. Read authoritative docs.
2. Inspect the existing implementation.
3. Inspect existing tests.
4. Inspect current migrations.
5. Inspect current API.
6. Inspect current UI.
7. Inspect dependency graph.
8. Classify every target item as:
   - MISSING
   - PARTIAL
   - COMPLETE
   - CONFLICTING
   - OBSOLETE

Do not rebuild COMPLETE systems.

## RULE 3.2 — NEVER CREATE DUPLICATE SUBSYSTEMS

Reuse current:
- auth
- AI provider abstraction
- entitlement engine
- workspace
- offline/sync
- scheduler
- Notes
- Knowledge
- Canvas
- observability
- billing

A new subsystem requires either:
- a clearly new responsibility, or
- an ADR demonstrating why the existing implementation is insufficient.

## RULE 3.3 — BACKEND IS AUTHORITATIVE

The client MUST NOT be authoritative for:
- subscription
- entitlement
- payment success
- AI credit balance
- provider credentials
- canonical user data

## RULE 3.4 — USER-FACING COMPLETENESS

A technically working feature is NOT DONE when:
- purpose is unclear
- CTA is unclear
- empty state is generic
- loading state is blank
- failure state is opaque
- feature exists but has no workflow connection
- user cannot tell what happens next

## RULE 3.5 — NO FAKE EVIDENCE

Do not claim:
- production-ready
- secure
- verified
- browser-tested
- device-tested

without actual evidence.

## RULE 3.6 — BUSINESS DECISION REQUIRED

When a task reaches an explicit unresolved business policy:
- STOP the affected decision path.
- Mark DECISION_REQUIRED.
- Record options and technical impact.
- Continue unrelated work only if dependency-safe.

## RULE 3.7 — DOCUMENTATION MUST REFLECT REALITY

If implementation changes:
- architecture
- API
- data model
- behavior
- entitlements
- pricing
- security

update the appropriate authoritative document.

## RULE 3.8 — NO RAW PRIVATE CONTENT IN PRODUCT TELEMETRY BY DEFAULT

Do not instrument:
- raw Note bodies
- raw Canvas scene
- raw AI prompts
- raw AI output
- API keys
- payment credentials

unless an explicit justified feature requires it and privacy policy covers it.

---

# 4. AUTHORITATIVE DOCUMENTS TO READ FIRST

Read as applicable:

- docs/SRS.md
- docs/design.md
- docs/design-tokens.md
- docs/architecture.md
- docs/domain-model.md
- docs/scheduling-engine.md
- docs/knowledge-layer.md
- docs/offline-sync.md
- docs/ai-architecture.md
- docs/deployment.md
- docs/environment.md
- docs/test-strategy.md
- docs/browser-e2e.md
- docs/ui-audit.md
- docs/api/openapi.yaml
- docs/implementation-status.md
- docs/billing.md
- TASK.md

Inspect:

- server/
- database/
- tests/
- infrastructure/
- scripts/
- .github/
- .opencode/
- Makefile
- composer.json
- package.json

---

# 5. MASTER PHASE STRUCTURE

P28 — Product Experience Audit, UX Rescue, Personalization, IA
P29 — Identity, Email, Recovery, Security Trust
P30 — Data Ownership, Export, Deletion, Privacy, Recovery
P31 — Product Intelligence, Analytics, Insights, Wrapped
P32 — Growth, Experimentation, Feedback, Commercial Analytics
P33 — Open-Source Repository Split, Core/Cloud Boundary, Website
P34 — SaaS Operations, Admin, Support, Observability, Abuse Controls
P35 — Android Production Hardening and Cross-Platform Coherence
P36 — Compliance, Legal/Trust Surfaces, Production Policy Readiness
P37 — Public Beta and Product-Market Validation
P38 — Scale Readiness, Cost/Capacity, Reliability and Release Candidate
P39 — v1.0 Production Release

Important:

P28 is intentionally a dedicated UX/product audit phase.
No major new surface should be added before the existing product experience
is proven understandable.

---

# 6. P28 — PRODUCT EXPERIENCE AUDIT, UX RESCUE, PERSONALIZATION, IA

## OBJECTIVE

Fix the fundamental experience problem:

> Kinevo may be technically powerful while still feeling fragmented,
> boring, unintuitive, or difficult to understand.

P28 is a product-quality gate, not cosmetic polishing.

## P28-001 — Full UX Inventory

Audit:
- Landing page
- Login
- Registration
- Email verification
- Forgot password
- Onboarding
- Today
- Week
- Schedule
- Goals
- Goal detail
- Milestones
- Programs
- Tasks
- Task detail
- Knowledge
- Notes
- Canvas
- Analytics
- Review
- Recovery
- Notifications
- Workspace selector
- Settings
- AI Provider
- AI Usage
- Billing
- Wrapped
- Data export
- Account deletion

For each surface evaluate:
- purpose
- user goal
- primary CTA
- secondary CTA
- information hierarchy
- first-use comprehension
- discoverability
- empty state
- loading state
- success state
- error state
- offline state
- conflict state
- responsive behavior
- accessibility
- micro-interactions
- relationship to other features

DOD:
- [ ] complete inventory
- [ ] every surface classified
- [ ] P0–P3 findings recorded
- [ ] evidence attached

## P28-002 — EMPTY STATE AUDIT (MANDATORY)

This is a first-class task.

Every empty state MUST answer:

1. What is this?
2. Why does it matter?
3. What can I do here?
4. What should I do next?

Examples:

Goals:
- Explain goals as outcomes/intention.
- CTA: Create Goal.
- Secondary: See example.

Tasks:
- Explain tasks as executable units.
- CTA: Create Task.
- Optional: Start from Goal.

Knowledge:
- Explain notes and linked knowledge.
- CTA: Create Note.

Canvas:
- Explain visual thinking/use case.
- CTA: Create Canvas.

Analytics:
- Explain that meaningful history is needed.
- CTA: Start Today / Create Goal / View Review.

Do not use generic copy such as:
- “No data.”
- “Nothing here.”
- “Empty.”
without contextual guidance.

Empty states MUST inherit relevant Workspace context.

DOD:
- [ ] all major empty states audited
- [ ] all major empty states explain purpose
- [ ] CTA works
- [ ] contextual destination works
- [ ] first-run browser evidence exists

## P28-003 — PERSONALIZATION AUDIT

Personalize using verified application state:
- user display name
- local time/day
- current Workspace
- active Goals
- deadlines
- recent progress
- recent activity
- current task context

Do NOT fabricate psychological attributes.

Good:
- “Good morning, Juan.”
- “You have 2 important actions left in Research.”

Forbidden:
- unsupported personality claims
- pseudo-neuroscience
- clinical inference

DOD:
- [ ] shell contextualized
- [ ] Today contextualized
- [ ] current Workspace obvious
- [ ] no unsupported personalization claim

## P28-004 — INFORMATION ARCHITECTURE

Define explicit navigation grouping:

EXECUTE
- Today
- Tasks

PLAN
- Goals
- Milestones
- Programs
- Schedule

KNOWLEDGE
- Notes
- Knowledge
- Canvas

REVIEW
- Progress
- Analytics
- Wrapped
- Recovery

SYSTEM
- Workspace
- AI
- Billing
- Settings

Do not blindly implement this grouping if the actual tested user flow proves a
better hierarchy; document the final decision instead.

DOD:
- [ ] navigation map documented
- [ ] no orphan screen
- [ ] clear parent/context for every surface

## P28-005 — CTA HIERARCHY

Each critical page MUST have:
- one visually obvious primary action
- one secondary action
- optional details/destructive action

Destructive actions MUST NOT compete visually with primary actions.

DOD:
- [ ] Goal primary CTA obvious
- [ ] Task primary CTA obvious
- [ ] Today primary CTA obvious
- [ ] Notes primary CTA obvious
- [ ] Canvas primary CTA obvious
- [ ] Settings subsections understandable

## P28-006 — CROSS-FEATURE WORKFLOW AUDIT

Prove that the user can discover:

Goal → AI Breakdown
Goal → Milestone
Milestone → Task
Task → Today
Task → Note
Task → Canvas
Note → Goal
Note → Task
Canvas → Goal
Canvas → Task
Review → Next Goal

The feature architecture must be experienced as a connected system.

DOD:
- [ ] all intended paths are reachable
- [ ] no major dead end
- [ ] browser evidence for each critical path

## P28-007 — MICRO-INTERACTION SYSTEM

Audit and implement purposeful micro-interactions:
- save feedback
- task completion feedback
- progress update feedback
- AI generation progress
- proposal acceptance confirmation
- workspace switching feedback
- notification read feedback
- billing action feedback
- subtle hover/focus affordances
- meaningful transitions

Rules:
- never use motion for decoration only
- respect reduced motion
- no excessive animation
- state transitions remain understandable without motion

DOD:
- [ ] motion tokens used
- [ ] reduced-motion behavior
- [ ] no blocking animation
- [ ] important actions provide feedback

## P28-008 — DESIGN SYSTEM AUDIT

Use existing design tokens as authority.

Audit:
- colors
- typography
- spacing
- radii
- shadows
- z-index
- motion
- button hierarchy
- form controls
- cards
- badges
- dialogs
- navigation

No new hard-coded visual values on critical surfaces.

DOD:
- [ ] token violations identified/fixed
- [ ] shared components reused
- [ ] visual consistency improved

## P28-009 — ANALYTICS UX AUDIT

Every major chart MUST answer:
- What happened?
- Is it improving or declining?
- Why should I care?
- What can I do next?

A graph without interpretation is incomplete.

DOD:
- [ ] chart has metric definition
- [ ] period visible
- [ ] interpretation present
- [ ] empty/sparse data handled
- [ ] action path where meaningful

## P28-010 — FEATURE EXPLANATION LAYER

Every major feature gets concise contextual education:
- tooltip where necessary
- helper text
- first-use explanation
- contextual “Why?” explanation
- Learn More link where appropriate

Avoid tutorial overload.

DOD:
- [ ] user can understand Goal
- [ ] user can understand Workspace
- [ ] user can understand Knowledge
- [ ] user can understand Canvas
- [ ] user can understand Analytics
- [ ] user can understand AI provider modes

## P28-011 — GLOBAL STATE MATRIX

For each core entity/surface define applicable:
- loading
- ready
- empty
- saving
- saved
- offline
- syncing
- conflict
- error
- unauthorized
- entitlement-limited

Entities:
- Workspace
- Goal
- Task
- Milestone
- Program
- Note
- Canvas
- Schedule
- AI
- Billing
- Wrapped

DOD:
- [ ] matrix exists
- [ ] critical states implemented
- [ ] states tested

## P28-012 — ACCESSIBILITY AUDIT

Audit:
- keyboard
- focus
- semantic landmarks
- heading hierarchy
- dialogs
- screen-reader status
- target sizes
- contrast
- no color-only state
- reduced motion

DOD:
- [ ] core surfaces pass WCAG 2.2 AA baseline
- [ ] keyboard core loop works
- [ ] reduced motion verified

## P28-013 — BROWSER GOLDEN JOURNEYS

Journey A:
- first login → first Goal → breakdown → task → Today → complete

Journey B:
- returning user → current Workspace → Today → execute → review

Journey C:
- Goal → AI proposal → review → accept

Journey D:
- Note → link Goal/Task → discover relationship

Journey E:
- Canvas → linked Goal/Task → persistence

DOD:
- [ ] Chromium
- [ ] Firefox
- [ ] WebKit where runner supports it
- [ ] evidence recorded

## P28-014 — UX RELEASE GATE

P28 is DONE ONLY WHEN:
- [ ] empty states intentional
- [ ] personalization coherent
- [ ] navigation coherent
- [ ] CTA hierarchy obvious
- [ ] micro-interactions meaningful
- [ ] analytics actionable
- [ ] feature explanations available
- [ ] critical browser journeys pass
- [ ] no unresolved P0/P1 UX blocker

---

# 7. P29 — IDENTITY, EMAIL, RECOVERY, SECURITY TRUST

## P29-001 — EMAIL-FIRST IDENTITY

Primary identity:
- verified email

Do not introduce username unless explicitly required.

## P29-002 — EMAIL VERIFICATION

Flow:
- register
- send verification
- verify token
- mark email verified
- expire token
- single use
- resend rate limit

DOD:
- [ ] verification works
- [ ] expired token fails
- [ ] reused token fails
- [ ] enumeration resistance

## P29-003 — FORGOT PASSWORD

Flow:
- Forgot Password
- user submits email
- generic response
- reset email
- secure token
- new password
- invalidate old sessions

Token rules:
- plaintext token MUST NOT be stored
- store hash only
- expiration mandatory
- one-time use mandatory

DOD:
- [ ] valid reset
- [ ] expired reset blocked
- [ ] replay blocked
- [ ] old sessions invalidated
- [ ] non-existing email response indistinguishable

## P29-004 — EMAIL ABSTRACTION

Development:
- Mailpit/local catcher

Production:
- provider selected explicitly before P39

Candidates may include:
- Brevo
- Amazon SES
- Resend

Do not hardcode provider-specific calls throughout the application.

DOD:
- [ ] provider abstraction
- [ ] local catcher
- [ ] template system
- [ ] retry

## P29-005 — TRANSACTIONAL EMAILS

Required before v1:
- email verification
- password reset
- welcome/onboarding
- critical security notification
- subscription activation
- payment/renewal notification
- failed payment notification

Must support:
- Bahasa Indonesia
- English

DOD:
- [ ] templates
- [ ] localization
- [ ] queue
- [ ] retry
- [ ] failure logging

## P29-006 — GOOGLE OAUTH

Implement only if current provider requirements and account-linking policy are
verified.

Do NOT automatically merge accounts.

DOD:
- [ ] OAuth login
- [ ] existing-account linking policy
- [ ] duplicate account handling
- [ ] failure path

## P29-007 — ACCOUNT SECURITY POLICY

V1:
- password
- email verification
- password reset
- session invalidation

2FA/passkey:
- deferred unless explicitly added later

DOD:
- [ ] policy documented

## P29-008 — ACCOUNT DELETION

V1 baseline:
- 30-day deletion grace period

Flow:
- request deletion
- confirm
- optional export
- grace period
- cancel deletion
- permanent deletion

DOD:
- [ ] request works
- [ ] cancellation works
- [ ] final deletion works
- [ ] dependency cleanup verified

## P29-009 — SECURITY NOTIFICATIONS

Notify on relevant security events without leaking sensitive data.

DOD:
- [ ] event generated
- [ ] delivery path
- [ ] read state
- [ ] privacy review

## P29-010 — P29 FINAL GATE

DONE ONLY IF:
- [ ] verification
- [ ] password reset
- [ ] email system
- [ ] account deletion
- [ ] recovery security
- [ ] critical browser evidence

---

# 8. P30 — DATA OWNERSHIP, EXPORT, PRIVACY, RECOVERY

## P30-001 — DATA OWNERSHIP POLICY

Document that user data includes:
- Goals
- Tasks
- Notes
- Canvas
- Knowledge
- Workspace content
- personal progress

## P30-002 — DATA EXPORT

V1 formats:
- JSON
- Markdown
- CSV

PDF is NOT mandatory.

Export MUST be owner-scoped.

## P30-003 — EXPORT JOB

For larger exports:
- queue
- status
- progress where feasible
- secure download
- expiration

## P30-004 — DATA DELETION MAP

Map:
- user
- profile
- workspaces
- goals
- milestones
- programs
- tasks
- subtasks
- notes
- canvas
- knowledge links
- progress
- activity
- AI audit/usage
- billing references
- notifications

Some billing records may require retention; document exceptions.

## P30-005 — PRIVACY POLICY SURFACE

Document:
- data collected
- AI processing
- BYOK processing
- payment processing
- analytics telemetry
- retention
- deletion
- export
- third-party services

Do not claim legal certification without counsel/audit.

## P30-006 — AI DATA CONTROL

User-facing:
- explain hosted AI vs BYOK
- explain which content is sent to AI for a request
- provide controls where technically supported
- never imply private content is never processed if it is processed

## P30-007 — DATA RETENTION MATRIX

Define retention for:
- AI runs
- AI proposals
- usage records
- billing events
- email logs
- notifications
- deleted account records
- audit records

Where legal/operational retention is uncertain:
- mark review required
- do not invent legal policy

## P30-008 — BACKUP/RESTORE COVERAGE

Backups must include required SaaS state subject to retention policy.

Verify restore of:
- workspace
- goals
- tasks
- notes
- canvas
- subscriptions
- entitlements
- AI usage
- billing events

## P30-009 — P30 FINAL GATE

[ ] ownership
[ ] export
[ ] deletion
[ ] privacy
[ ] AI data transparency
[ ] retention
[ ] backup/recovery

---

# 9. P31 — PRODUCT INTELLIGENCE, ANALYTICS, INSIGHTS, WRAPPED

## P31-001 — METRIC SOURCE MATRIX

Authoritative sources:
- Goals
- Milestones
- Tasks
- Activity Logs
- Progress Events
- Focus Sessions
- scheduling outcomes
- Workspace context

No UI-only metric is authoritative.

## P31-002 — METRIC CATALOG

Every metric MUST define:
- name
- purpose
- source
- formula
- date range
- timezone
- inclusions
- exclusions
- null behavior
- aggregation method

Metrics include:
- goals created
- goals completed
- milestones advanced
- milestones completed
- tasks completed
- completion ratio
- focus minutes
- active days
- streak where supported
- planned vs completed
- goal progress

## P31-003 — INSIGHT ENGINE

Deterministic insight categories:
- trend
- consistency
- goal alignment
- planning/execution gap
- workload pattern

Each insight MUST expose:
- stable code
- evidence references
- explanation
- confidence/basis where meaningful

## P31-004 — USER ANALYTICS VS FOUNDER ANALYTICS

USER:
- personal performance
- progress
- review

FOUNDER:
- activation
- retention
- revenue
- AI spend
- payment failures

Never mix permissions.

## P31-005 — AI NARRATIVE

AI consumes validated metric/insight payloads, not the full private database.

AI MUST NOT invent:
- numbers
- dates
- goals
- causal explanations
- diagnoses

If AI fails:
- deterministic fallback

## P31-006 — MONTHLY REVIEW

Show:
- progress
- activity
- strongest areas
- stalled areas
- notable changes
- next focus

## P31-007 — YEARLY WRAPPED

FREE:
- basic summary

PRO:
- advanced yearly Wrapped
- richer comparisons
- AI narrative where available

POWER:
- deeper history
- advanced share customization
- expanded comparative insights

## P31-008 — SHAREABLE ARTIFACTS

Formats:
- vertical story
- square
- downloadable card/image

Preview is mandatory.

Default:
- privacy safe
- no raw Notes
- no raw Canvas
- no sensitive task detail

## P31-009 — PUBLIC SHARE LINKS

If implemented:
- non-guessable token
- revocation
- optional expiration
- restricted payload

## P31-010 — REFLECTION → NEXT GOAL

User may convert insight into Goal.

Must require explicit confirmation.

Never auto-create a Goal from analytics.

## P31-011 — P31 FINAL GATE

[ ] metric catalog
[ ] deterministic insight engine
[ ] analytics UI
[ ] AI narrative fallback
[ ] Wrapped
[ ] safe sharing
[ ] next-goal loop

---

# 10. P32 — GROWTH, EXPERIMENTATION, FEEDBACK, COMMERCIAL ANALYTICS

## P32-001 — PRODUCT EVENT TAXONOMY

Track safe events such as:
- signup
- verification
- onboarding_complete
- workspace_created
- goal_created
- breakdown_requested
- proposal_accepted
- task_completed
- goal_progressed
- review_opened
- Wrapped_opened
- Wrapped_shared
- checkout_started
- subscription_active

Do not capture raw private content by default.

## P32-002 — NORTH STAR METRIC

Primary:

> Weekly Goal Progressing Users (WGPU)

Definition:
- unique users in a 7-day window who perform at least one meaningful progress
  action on an active Goal.

Secondary:
- Goal-to-Execution Rate
- Activation Rate
- D7 retention
- D30 retention

## P32-003 — ACTIVATION FUNNEL

signup
→ workspace
→ goal
→ task/milestone
→ first meaningful execution

## P32-004 — RETENTION

Measure:
- D1
- D7
- D30
- WAU
- recurring core-loop use

## P32-005 — PRICING ANALYTICS

Locked:
- Free = 0
- Pro = 34,900
- Power = 49,900

Measure:
- upgrade intent
- checkout start
- conversion
- cancellation
- downgrade
- churn

## P32-006 — UNIT ECONOMICS

Track separately:
- subscription revenue
- AI revenue
- hosted AI cost
- infrastructure cost
- payment fees
- support cost when measurable

BYOK cost is not Kinevo-hosted AI COGS.

## P32-007 — AI COST SIMULATOR

MUST support at minimum:
- provider
- model
- input tokens
- cached input tokens
- output tokens
- pricing version
- request frequency
- plan
- P50 scenario
- P95 scenario
- abuse scenario

Output:
- provider cost
- Kinevo estimated charge
- credit consumption
- margin signal

This simulator determines whether the current AI quota is economically safe.

## P32-008 — AI COST/REVENUE ALERTING

User alerts:
- 50%
- 75%
- 90%
- 100% of hosted allowance

Founder alerts:
- AI spend spike
- per-user anomaly
- payment failure spike
- provider cost anomaly

Do not depend on Notification Center if operational alerting can be simpler.

## P32-009 — FEATURE FEEDBACK

Support:
- “Was this useful?”
- bug report
- feature feedback

Attach safe metadata only:
- route
- app version
- browser/device
- request ID

## P32-010 — EXPERIMENTS / FEATURE FLAGS

Use minimal internal database-backed flags initially.

Do not add external feature flag service unless need is proven.

Every experiment must document:
- hypothesis
- metric
- eligibility
- duration
- result

## P32-011 — REFERRAL/GROWTH LOOP

Wrapped may support referral attribution.

Do NOT invent reward amounts.

## P32-012 — P32 FINAL GATE

[ ] event taxonomy
[ ] WGPU
[ ] activation
[ ] retention
[ ] pricing metrics
[ ] AI cost simulator
[ ] cost alerts
[ ] feedback
[ ] experiments

---

# 11. P33 — OPEN-SOURCE REPOSITORY SPLIT, CORE/CLOUD BOUNDARY, WEBSITE

## OBJECTIVE

Perform repository separation BEFORE P28–P39 accumulate more coupling.

## TARGET REPOSITORIES

PUBLIC:
- github.com/sedam-or/Kinevo
  - Kinevo Core

PUBLIC:
- github.com/sedam-or/kinevo-site
  - product website

PRIVATE:
- github.com/sedam-or/kinevo-cloud
  - hosted SaaS/cloud-only infrastructure

Do not create a separate docs repository unless operational evidence later
shows it is necessary.

## P33-001 — REPOSITORY OWNERSHIP MATRIX

For EVERY current root path record:
- source path
- destination repository
- destination path
- retain/copy/move/archive/delete
- dependency reason
- license implication

Required inventory includes:
- README.md
- LICENSE
- AGENTS.md
- TASK.md
- docs/
- server/
- database/
- tests/
- infrastructure/
- scripts/
- .github/
- .opencode/
- environment/config files

## P33-002 — CORE/CLOUD BOUNDARY

Preferred model:

    Kinevo Core
        ↓
    stable package/module boundary
        ↓
    Kinevo Cloud

The agent MUST identify the actual technical seam from the existing source.
Do not invent a package boundary before inspecting imports and dependencies.

## P33-003 — MIGRATION SAFETY PLAN

Perform in this exact conceptual order:

1. Freeze non-essential feature work.
2. Inventory all files.
3. Classify ownership.
4. Detect cross-repository dependencies.
5. Create destination repositories.
6. Copy/migrate safe content.
7. Establish dependency boundary.
8. Run tests in source and destination.
9. Run builds.
10. Validate docs/links.
11. Validate license/attribution.
12. Compare functional behavior.
13. Publish migration notes.
14. Only then remove/archive obsolete content.

NEVER delete first.

## P33-004 — AGENTS.MD / TASK.MD DISPOSITION

Do NOT blindly expose private AI development instructions in the public product
repo.

Recommended:
- contributor-facing rules remain public where useful
- agent-specific operational instructions move to an explicit AI/development
  area
- private workflow instructions stay private if they contain non-public
  operational detail

TASK.md:
- preserve active contributor/release-relevant portions where useful
- archive historical execution records if they become excessive

No data loss.

## P33-005 — SRS/DESIGN DOCUMENT DISPOSITION

Classify each document:
- public product contract
- public architecture
- contributor development
- private SaaS implementation
- historical archive

Do not delete merely because it is a development document.

## P33-006 — GIT HISTORY

Do not rewrite public history for cosmetic purposes.

If history preservation across repos is required:
- document source tags/commits
- create migration commit
- preserve provenance

Exception:
- actual credential/secret exposure may require history rewriting and immediate
  credential rotation.

## P33-007 — OPEN-SOURCE LICENSE AUDIT

Verify:
- MIT
- Tiptap/ProseMirror
- Excalidraw
- all dependencies
- fonts/assets

Update:
- docs/third-party/licenses.md
- docs/third-party/attributions.md

## P33-008 — CORE README

Core README must explain:
- what Kinevo is
- core value
- architecture
- screenshots
- self-hosting
- development
- contributing
- license
- Cloud option

## P33-009 — PRODUCT WEBSITE

Target:
- https://kinevo.app
- https://app.kinevo.app
- https://docs.kinevo.app
- https://status.kinevo.app if implemented

Do not claim DNS/domain readiness until actually configured.

## P33-010 — LANDING PAGE IA

Sections:
1. problem
2. transformation
3. how it works
4. Goal → AI → Task → Today flow
5. Workspace
6. Knowledge
7. Canvas
8. Analytics
9. Wrapped
10. open source
11. pricing
12. FAQ
13. security/trust
14. CTA

Hero positioning:
- intention → execution

Do not lead with a feature dump.

## P33-011 — PRICING PAGE

Show:
- Free — IDR 0
- Pro — IDR 34,900/month
- Power — IDR 49,900/month

Annual price is omitted until approved.

## P33-012 — OSS VS CLOUD

Explain:
- self-host Kinevo Core
- or use Kinevo Cloud

Do not imply self-hosting is intentionally degraded.

## P33-013 — MIGRATION VALIDATION

Core:
- clean clone
- install
- migrate
- test
- build
- run

Cloud:
- clean clone
- resolves Core dependency
- test
- build

Site:
- clean clone
- build
- preview

## P33-014 — P33 FINAL GATE

[ ] ownership matrix
[ ] core/cloud seam
[ ] migration
[ ] docs disposition
[ ] licenses
[ ] README
[ ] product website
[ ] pricing
[ ] OSS/Cloud explanation
[ ] reproducible builds

---

# 12. P34 — SAAS OPERATIONS, ADMIN, SUPPORT, OBSERVABILITY, ABUSE CONTROL

## P34-001 — ADMIN ACCESS MODEL

Admin access separate from users.

V1:
- no arbitrary user impersonation
- no direct raw Note browsing
- no raw Canvas browsing
- no raw AI prompt browsing
- no BYOK plaintext visibility

## P34-002 — ADMIN DASHBOARD

Minimum:
- users
- active subscriptions
- plan distribution
- MRR snapshot
- hosted AI spend
- payment failures
- webhook failures
- email failures
- backup status
- system health

## P34-003 — SUBSCRIPTION/BILLING DIAGNOSTICS

Show:
- internal subscription ID
- plan
- provider
- provider subscription reference
- last billing event
- entitlement state
- last payment status

## P34-004 — AI OPERATIONS

Show aggregate/safe:
- provider status
- model
- request counts
- tokens
- estimated spend
- credit consumption
- error rate

Never expose secrets.

## P34-005 — EMAIL OPERATIONS

Show:
- queued
- sent
- failed
- retrying
- template ID

Do not expose raw tokens in admin UI.

## P34-006 — ABUSE/FRAUD CONTROLS

At minimum protect:
- signup
- login
- password reset
- AI generation
- checkout creation
- webhook endpoints
- public share links

Use:
- rate limiting
- request quotas
- suspicious activity logging
- provider-side controls where available

Do not build a full fraud platform without need.

## P34-007 — ENVIRONMENT SEPARATION

Explicit environments:
- local
- development
- staging
- production

Each has appropriate:
- database
- AI credential
- payment credential
- email configuration
- storage

Production credentials MUST NOT be used in local tests.

## P34-008 — INCIDENT RUNBOOKS

Create/runbooks for:
- AI outage
- payment outage
- webhook failure
- email failure
- DB outage
- storage outage
- queue outage
- backup failure
- security incident
- account recovery issue
- entitlement mismatch

## P34-009 — HEALTH/ALERTING

Monitor:
- app health
- DB
- queue
- scheduler
- storage
- AI
- billing
- email
- backup
- abnormal spend

## P34-010 — ADMIN AUDIT LOG

Audit:
- entitlement correction
- subscription correction
- billing reconciliation
- account administrative action

## P34-011 — SUPPORT CHANNEL

V1 recommendation:
- support@kinevo.app
- GitHub Issues/Discussions for open-source

Separate SaaS support from public issue tracker when appropriate.

## P34-012 — HELP CENTER BASELINE

Create concise docs for:
- getting started
- Goals
- Today
- Workspace
- AI
- BYOK
- Billing
- data export
- account deletion
- troubleshooting

## P34-013 — P34 FINAL GATE

[ ] admin
[ ] billing diagnostics
[ ] AI ops
[ ] email ops
[ ] abuse controls
[ ] environment separation
[ ] runbooks
[ ] support
[ ] alerts

---

# 13. P35 — ANDROID PRODUCTION HARDENING & CROSS-PLATFORM COHERENCE

## P35-001 — ANDROID RELEASE BUILD

Verify:
- debug
- release-like
- signed release procedure
- versioning

## P35-002 — ANDROID CORE LOOP

Must pass:
- login
- Workspace
- Goal
- AI Breakdown
- Today
- Task
- Complete
- Review

## P35-003 — ANDROID OFFLINE

Verify:
- Today cache
- mutation queue
- Note mutation where supported
- reconnect
- conflict

Reuse existing synchronization architecture.

## P35-004 — ANDROID ENTITLEMENT

Test:
- Free
- Pro
- Power
- expired
- canceled/grace behavior where applicable

Mobile MUST NOT forge an entitlement locally.

## P35-005 — WEB-FIRST BILLING

Android:
- view plan
- view subscription
- manage subscription
- receive web entitlement

Android v1 has no native subscription checkout.

## P35-006 — ANDROID AI SECURITY

The Android app MUST NEVER contain:
- DeepSeek secret
- OpenCode secret
- OmniRouter secret
- Midtrans secret
- production SMTP secret

Flow:
Android → Kinevo backend → provider/gateway

## P35-007 — ANDROID DEVICE MATRIX

Test at minimum:
- small phone
- typical phone
- large phone

Record exact devices and Android API versions.

## P35-008 — P35 FINAL GATE

[ ] release build
[ ] core loop
[ ] offline
[ ] entitlement
[ ] AI security
[ ] billing boundary
[ ] device evidence

---

# 14. P36 — COMPLIANCE, LEGAL/TRUST SURFACES, PRODUCTION POLICY READINESS

## OBJECTIVE

Close the non-code trust gaps before public paid launch.

This phase does NOT invent legal conclusions. It converts known operational
requirements into explicit product surfaces and review items.

## P36-001 — TERMS OF SERVICE SURFACE

Create:
- Terms of Service page

Document owner/provider identity, service scope, subscription basics, user
responsibilities, acceptable-use boundary, termination, support path, and
limitations subject to legal review.

## P36-002 — PRIVACY NOTICE

Cover:
- account data
- product usage telemetry
- AI requests
- BYOK processing
- payment provider
- email provider
- analytics provider
- retention
- deletion
- exports

## P36-003 — AI USE POLICY

Explain:
- hosted AI
- BYOK
- provider routing
- what content can be sent for a requested AI operation
- AI is assistive, not authoritative
- proposal approval workflow

## P36-004 — ACCEPTABLE USE / ABUSE

Define prohibited behaviors appropriate to the product:
- automated abuse
- credential theft
- malicious payloads
- payment fraud
- prompt abuse for prohibited content where applicable

Do not make legal claims beyond reviewable policy text.

## P36-005 — COOKIE/ANALYTICS POLICY

Document actual cookies/storage technologies used.

Do not ship consent mechanisms for technologies that are not actually used.

## P36-006 — DATA PROCESSOR / THIRD-PARTY INVENTORY

Inventory:
- Midtrans
- AI gateway/provider
- email service
- hosting
- object storage
- analytics

For each:
- purpose
- category of data
- region/hosting info if known
- link to provider privacy/terms where appropriate

## P36-007 — TAX / INVOICE REVIEW FLAG

Do not invent tax treatment.

Record:
- tax requirements requiring professional/accounting review
- invoice/receipt behavior required by the chosen payment provider

## P36-008 — PAYMENT USER TRUST

Billing UI must clearly show:
- price
- recurring interval
- current period
- renewal date
- cancellation status
- payment status

## P36-009 — P36 FINAL GATE

[ ] Terms
[ ] Privacy
[ ] AI policy
[ ] Acceptable use
[ ] analytics/cookie documentation
[ ] provider inventory
[ ] tax review list
[ ] billing transparency

---

# 15. P37 — PUBLIC BETA & PRODUCT-MARKET VALIDATION

## P37-001 — TARGET USER

Primary:
- serious individual knowledge worker
- builder
- researcher
- student
- creator
- professional

## P37-002 — BETA COHORT

Define:
- acquisition source
- cohort size
- test window
- support method
- consent/communications where relevant

## P37-003 — ACTIVATION

Canonical candidate:

signup
→ workspace
→ goal
→ breakdown/task
→ meaningful execution

## P37-004 — NORTH STAR

Measure WGPU.

## P37-005 — RETENTION

Measure D1/D7/D30 and recurring core-loop use.

## P37-006 — PRICING VALIDATION

Keep launch prices unchanged during measurement unless explicitly changed.

Measure:
- upgrade intent
- checkout completion
- conversion
- cancellation
- downgrade
- churn

## P37-007 — POWER VALIDATION

Validate whether users understand:
- Pro = serious capability
- Power = higher capacity + deeper intelligence

Do NOT add arbitrary features merely because users do not understand Power.
First test messaging and packaging.

## P37-008 — USER COMPREHENSION STUDY

Ask:
- What is Kinevo?
- What is Workspace?
- What is a Goal?
- What does AI Breakdown do?
- What should you do next?
- What did you expect to happen?
- Why did you return?
- Why did you leave?

## P37-009 — FAILURE/CHURN TAXONOMY

Categories:
- technical
- UX
- product value
- pricing
- AI quality
- performance
- workflow gap

## P37-010 — BETA FEATURE FREEZE

Only:
- P0/P1
- proven UX blockers
- reliability
- security

## P37-011 — P37 FINAL GATE

[ ] beta cohort
[ ] activation
[ ] WGPU
[ ] retention
[ ] pricing evidence
[ ] Power differentiation
[ ] UX research
[ ] churn taxonomy

---

# 16. P38 — SCALE READINESS, COST/CAPACITY, RELIABILITY, RELEASE CANDIDATE

## P38-001 — AI CAPACITY REVIEW

Evaluate:
- concurrent AI requests
- queue behavior
- provider rate limits
- model latency
- token limits
- worst-case user

## P38-002 — AI CREDIT SAFETY REVIEW

For each plan evaluate:
- P50 cost
- P95 cost
- abuse cost
- worst supported request
- monthly revenue
- gross AI margin

If unsafe:
- change configurable limits before release
- record decision

## P38-003 — STORAGE/BANDWIDTH REVIEW

Evaluate:
- workspace count
- note storage
- canvas files
- exports
- analytics retention
- mobile payload size

## P38-004 — DATABASE REVIEW

Check:
- indexes
- slow queries
- ownership filters
- large-table growth path
- migration safety

## P38-005 — QUEUE REVIEW

Check:
- retries
- poison jobs
- maximum attempts
- dead-letter/recovery behavior
- observability

## P38-006 — CACHE REVIEW

Ensure:
- cache does not become canonical
- tenant/user/workspace isolation preserved
- invalidation strategy explicit

## P38-007 — LOAD / SOAK TEST BASELINE

Test representative workload.

Do not claim scale numbers that were not tested.

## P38-008 — SECURITY REGRESSION

Run:
- auth
- IDOR
- workspace isolation
- entitlement bypass
- API key leak checks
- payment webhook spoof
- export leak
- public share leak

## P38-009 — RELEASE CANDIDATE

Freeze major behavior.

Allowed:
- P0/P1
- security
- data integrity
- release blockers

## P38-010 — P38 FINAL GATE

[ ] AI capacity
[ ] economics
[ ] storage
[ ] DB
[ ] queue
[ ] cache
[ ] load evidence
[ ] security regression
[ ] RC freeze

---

# 17. P39 — V1.0 PRODUCTION RELEASE

## OBJECTIVE

Release Web + Android as a coherent Indonesia-first, single-user SaaS.

## P39-001 — VERSION POLICY

Use Semantic Versioning:
- MAJOR
- MINOR
- PATCH

Define:
- breaking
- feature
- bugfix
- security

## P39-002 — CHANGELOG

Use categories:
- Added
- Changed
- Fixed
- Security
- Deprecated
- Removed

Include SaaS-relevant changes:
- pricing
- entitlement
- AI usage policy
- mobile
- known limitations

## P39-003 — RELEASE NOTES

Publish:
- product summary
- core workflows
- Free/Pro/Power
- pricing
- AI/BYOK
- Android
- Wrapped
- open-source boundary
- known limitations
- support

## P39-004 — PRODUCTION MIGRATION DRY RUN

Perform:
- restore representative backup
- migrate
- validate
- smoke

## P39-005 — WEB E2E

Required journeys:
- login
- verification
- password reset
- onboarding
- Workspace
- Goal
- AI Breakdown
- Milestone
- Task
- Today
- Notes
- Canvas
- Analytics
- Review
- Billing
- AI Usage
- Wrapped
- Export
- Account deletion

Browser matrix:
- Chromium
- Firefox
- WebKit

## P39-006 — ANDROID E2E

Required:
- install
- login
- Workspace
- Goal
- AI
- Today
- Task
- Note
- offline
- reconnect
- entitlement

## P39-007 — BILLING E2E

Sandbox evidence:
- Free
- Pro checkout
- webhook
- subscription activation
- entitlement
- Android access
- cancellation
- downgrade
- expiration

Midtrans Sandbox MUST be labeled SANDBOX evidence.

Before production:
- production merchant status verified
- production webhook endpoint verified
- production credential separation verified
- current provider capability verified

## P39-008 — AI ECONOMICS E2E

Verify:
- Free hosted AI
- Pro hosted AI
- Power hosted AI
- Pro BYOK
- Power BYOK

Expected:
- hosted consumes Kinevo hosted credits
- BYOK does not consume hosted credits
- both obey safety limits

## P39-009 — EMAIL E2E

Verify:
- verification
- reset
- welcome
- security
- billing
- failure

## P39-010 — DATA OWNERSHIP E2E

Verify:
- export
- account deletion
- deletion grace period
- recovery/cancel deletion

## P39-011 — SECURITY FINAL AUDIT

Verify:
- authentication
- authorization
- IDOR
- workspace isolation
- entitlement bypass
- fake payment success
- webhook spoofing
- API key exposure
- BYOK exposure
- share links
- export
- account deletion

## P39-012 — BACKUP/RESTORE FINAL DRILL

Verify recovery of:
- core user data
- workspace
- AI usage
- subscription
- entitlement
- billing events

## P39-013 — MONITORING

Confirm alerts for:
- app down
- DB unhealthy
- queue failure
- scheduler failure
- AI failure
- webhook failure
- email failure
- backup failure
- abnormal AI cost

## P39-014 — SUPPORT/INCIDENT READINESS

Runbooks available for:
- account
- billing
- entitlement mismatch
- AI
- mobile
- data
- security

## P39-015 — ENVIRONMENT VERIFICATION

Confirm:
- APP_KEY
- DB
- storage
- mail
- AI gateway
- payment gateway
- TLS
- backups
- monitoring

No production secrets in build artifacts.

## P39-016 — PRODUCTION RC CHECKLIST

ALL applicable:
- [ ] product
- [ ] UX
- [ ] identity
- [ ] email
- [ ] data ownership
- [ ] analytics
- [ ] AI
- [ ] billing
- [ ] open-source split
- [ ] website
- [ ] admin
- [ ] Android
- [ ] security
- [ ] backup
- [ ] monitoring
- [ ] support
- [ ] documentation

## P39-017 — V1.0.0 TAG

Only after all gates pass.

## P39-018 — ROLLBACK PROCEDURE

Document:
- trigger
- owner
- deployment rollback
- DB migration implications
- billing implications
- customer communications

## P39-019 — POST-RELEASE REVIEW

Within the release process record:
- actual incidents
- unresolved P1s
- early usage
- AI cost
- conversion
- support volume

Do not immediately begin feature expansion.

## P39-020 — P39 FINAL RELEASE GATE

V1.0 is releasable only when:

TECHNICAL
- [ ] CI green
- [ ] migrations verified
- [ ] production smoke pass

PRODUCT
- [ ] core loop coherent
- [ ] onboarding works
- [ ] feature purposes clear

UX
- [ ] empty states
- [ ] CTA hierarchy
- [ ] personalization
- [ ] micro-interactions
- [ ] accessibility

IDENTITY
- [ ] email verification
- [ ] password reset
- [ ] account deletion

AI
- [ ] provider abstraction
- [ ] credits
- [ ] cost metering
- [ ] BYOK
- [ ] safeguards

BILLING
- [ ] Free
- [ ] Pro 34,900
- [ ] Power 49,900
- [ ] Midtrans production readiness

MOBILE
- [ ] Android release-like build
- [ ] same entitlement
- [ ] offline/reconnect

OPEN SOURCE
- [ ] core repo
- [ ] cloud repo
- [ ] product site

TRUST
- [ ] Terms
- [ ] Privacy
- [ ] AI policy
- [ ] provider inventory

OPERATIONS
- [ ] admin
- [ ] monitoring
- [ ] backups
- [ ] support

BUSINESS
- [ ] activation instrumentation
- [ ] retention instrumentation
- [ ] unit economics

---

# 18. CROSS-PHASE 38-GAP TRACEABILITY MATRIX

The previously identified missing items MUST map to this roadmap.

1. Product positioning
   → P28, P33

2. Onboarding
   → P28

3. Empty states
   → P28-002

4. Search/command center
   → P28 audit; implement only if discoverability evidence requires it

5. Data ownership/export
   → P30

6. Backup/recovery
   → P30, P39

7. AI memory/privacy governance
   → P30; AI memory remains gated until explicit product/privacy design exists

8. User feedback loop
   → P32

9. Experimentation
   → P32

10. Referral/growth
   → P32, P37

11. Feature flags
   → P32

12. Database migration strategy
   → P33, P38, P39

13. Security hardening
   → P29, P34, P38, P39

14. File storage
   → P30/P34 where applicable

15. AI model routing
   → existing P25 + P32/P34 operationalization

16. Founder/admin dashboard
   → P34

17. Support/help
   → P34

18. Retention loop
   → P31/P32/P37

19. Universal search
   → P28 audit/remediation

20. Onboarding
   → P28

21. Analytics meaning
   → P28/P31

22. Notification architecture
   → P29/P34

23. Account deletion
   → P29/P30

24. Google OAuth
   → P29

25. 2FA/passkey
   → P29 deferred

26. Legal/trust surfaces
   → P36

27. Admin impersonation
   → NO for v1

28. Environment separation
   → P34

29. Open-source repository strategy
   → P33

30. Product website
   → P33

31. OSS vs Cloud boundary
   → P33

32. Release engineering
   → P38/P39

33. Pricing/entitlement architecture
   → existing P25 + P32/P39

34. AI cost engine
   → P32/P38

35. Billing/payment gateway
   → existing P24 + P39

36. Mobile architecture
   → existing P26/P27 + P35

37. Wrapped
   → P31

38. Public beta / PMF validation
   → P37

---

# 19. ADDITIONAL GAPS IDENTIFIED IN SECOND-PASS AUDIT

These items were not sufficiently explicit in the prior 38-item list and are
now covered by the roadmap.

39. Transactional email deliverability
   → P29

40. Abuse/fraud/rate protection
   → P34

41. Terms/privacy/AI policy
   → P36

42. Third-party data processor inventory
   → P36

43. Tax/invoice review boundary
   → P36

44. Founder unit-economics visibility
   → P32/P34

45. Release-candidate economic review
   → P38

46. Cross-platform entitlement consistency
   → P35/P39

47. Web-first billing user communication on Android
   → P35/P39

48. Documentation/public-vs-private repository hygiene
   → P33

These additions are mandatory to consider before v1 release.

---

# 20. PRODUCT WORKFLOW ACCEPTANCE STANDARD

Every major feature MUST demonstrate at least one upstream and one downstream
relationship.

Examples:

Goal:
- upstream: Workspace/intention
- downstream: AI Breakdown/Milestone/Task/Today

Task:
- upstream: Goal/Milestone/Program or capture
- downstream: Schedule/Today/Progress

Note:
- upstream: user context/task/goal
- downstream: Knowledge links/AI/Review

Canvas:
- upstream: Goal/Task/Knowledge context
- downstream: visual planning/execution support

Analytics:
- upstream: actual execution data
- downstream: review/next decision

Wrapped:
- upstream: validated historical data
- downstream: reflection/sharing/next goal

A feature with no meaningful relationship must be reviewed for product necessity.

---

# 21. PRODUCT PERSONALIZATION STANDARD

Kinevo must feel personal because it uses actual context, not because it invents
personality.

Acceptable personalization:
- name
- workspace
- current time
- today's plan
- deadline
- progress
- recent activity
- personal history

Not acceptable without explicit validated product/research basis:
- mental-state inference
- personality diagnosis
- neurological claims
- health claims
- “you are naturally a...” statements

---

# 22. EMPTY STATE STANDARD

For every empty primary screen, include:

TITLE:
- simple explanation

DESCRIPTION:
- why the surface matters

PRIMARY CTA:
- exact next action

OPTIONAL SECONDARY:
- example/help/learn more

CONTEXT:
- active workspace or relevant parent entity when applicable

SUCCESS PATH:
- user should understand what happens after clicking the CTA

Failure:
- CTA must never lead to a dead-end screen.

---

# 23. CTA STANDARD

Primary CTA:
- unique visual emphasis
- explicit verb
- matches expected outcome

Examples:
- Create Goal
- Break Down with AI
- Start Task
- Complete Task
- Create Note
- Create Canvas
- Review Progress
- Upgrade to Pro

Avoid vague:
- Continue
- Submit
- Manage
- Process
when a more explicit action name is possible.

---

# 24. BILLING UX STANDARD

Billing page MUST clearly show:
- current plan
- price
- billing interval
- next billing date
- usage
- cancellation state
- payment status
- upgrade/downgrade action

Never hide recurring nature.

The user should understand:
- what they pay
- why
- what they get
- when they are charged
- what happens after cancellation

---

# 25. AI UX / CREDIT STANDARD

Never say simply:
- “AI unavailable.”

Explain:
- whether provider is unavailable
- whether credits are exhausted
- whether request exceeded token/context limits
- whether BYOK is misconfigured
- what the user can do next

Example:

Hosted AI:
- “You’ve used your included AI allowance for this period.”
- “Use BYOK or upgrade if available.”

BYOK:
- “Your provider connection failed.”
- “Your Kinevo hosted AI credits were not consumed.”

Never expose:
- provider secret
- raw exception
- infrastructure stack trace

---

# 26. OPEN-SOURCE MIGRATION FILE DECISION RULE

For every file being moved, answer:

1. Who needs it?
2. Is it product code, operational code, marketing content, or development
   instruction?
3. Is it public-safe?
4. Does it contain SaaS secrets or private deployment information?
5. Does Cloud import it?
6. Does Core remain functional without it?
7. Is it historical?
8. What license governs it?

No file is deleted until all seven relevant dependency questions are answered.

---

# 27. REPOSITORY FINAL SHAPE

Kinevo Core:

    /
    ├── README.md
    ├── LICENSE
    ├── CONTRIBUTING.md
    ├── CODE_OF_CONDUCT.md
    ├── SECURITY.md
    ├── CHANGELOG.md
    ├── docs/
    ├── server/
    ├── database/
    ├── tests/
    ├── infrastructure/
    ├── scripts/
    └── .github/

Kinevo Cloud:

    /
    ├── README.md
    ├── docs/
    ├── app/cloud-specific modules
    ├── billing/
    ├── admin/
    ├── operations/
    ├── integrations/
    └── infrastructure/cloud

Kinevo Site:

    /
    ├── README.md
    ├── pages/
    ├── content/
    ├── assets/
    ├── components/
    └── tests/

The exact framework/layout MUST follow actual implementation choices.

---

# 28. FINAL TASK BOARD FORMAT

Every task entered into TASK.md MUST use:

### Pxx-xxx — Title
- Status: TODO / READY / IN_PROGRESS / IN_REVIEW / DONE / BLOCKED / DEFERRED
- Priority: P0 / P1 / P2 / P3
- Depends On: ...
- Business Decision: ...
- SRS: ...
- Design: ...
- Files: ...
- Acceptance:
  - [ ] ...
- Verification:
  - [ ] Unit
  - [ ] Integration
  - [ ] E2E
  - [ ] Browser/Device
  - [ ] Security where applicable
- Evidence: ...
- Known Limitations: ...
- Notes: ...

DONE without evidence is invalid.

---

# 29. FINAL AGENT REPORT FORMAT

For every completed task:

## Task
Pxx-xxx

## Status
DONE / IN_REVIEW / BLOCKED / DEFERRED

## Objective
...

## Existing Implementation Reused
...

## Files Created
...

## Files Modified
...

## Files Moved
...

## Files Deleted/Archived
...

## Database Changes
...

## API Changes
...

## UI/UX Changes
...

## AI Impact
...

## Billing/Entitlement Impact
...

## Security Impact
...

## Tests
...

## Browser/Device Evidence
...

## Documentation
...

## Business Decision Used
...

## Known Limitations
...

## Open Decisions
...

## Evidence
...

## Next READY Task
...

---

# 30. FINAL NO-HALLUCINATION CHECKLIST

Before marking any task DONE:

1. What exact existing code was inspected?
2. What exact files changed?
3. What acceptance criterion is proven?
4. What test proves it?
5. What browser/device evidence proves it?
6. What business decision was used?
7. Which values are configurable?
8. What external provider behavior was verified?
9. What remains deferred?
10. Is the user-facing workflow actually understandable?

If any answer is unknown:
- do not mark DONE.

---

# 31. FINAL RELEASE PRINCIPLE

Kinevo v1.0 is not defined by the number of features.

It is defined by whether a new user can independently understand and complete:

    discover Kinevo
      ↓
    understand value
      ↓
    create account
      ↓
    verify email
      ↓
    onboard
      ↓
    choose Workspace
      ↓
    create Goal
      ↓
    use AI Breakdown
      ↓
    review proposal
      ↓
    accept
      ↓
    receive actionable work
      ↓
    execute Today
      ↓
    complete work
      ↓
    observe progress
      ↓
    review
      ↓
    understand insight
      ↓
    continue

And a paid user can independently understand:

    Free
      ↓
    Pro / Power
      ↓
    hosted AI / BYOK
      ↓
    billing
      ↓
    usage
      ↓
    cancellation
      ↓
    recovery

And an open-source user can independently understand:

    Kinevo Core
      ↓
    self-host
      ↓
    own data
      ↓
    optionally move to Kinevo Cloud

The system must feel like ONE PRODUCT, not a collection of unrelated tools.

# END OF KINEVO POST-P27 MASTER EXECUTION PROMPT
