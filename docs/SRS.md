# Software Requirements Specification (SRS)
# Kinevo

**Status:** AUTHORITATIVE / Canonical Requirements / Single Source of Truth
**SRS Version:** 3.0.0 (P29 convergence, 2026-08-31)
**Supersedes:** SRS v2.0.0 (archived verbatim at `docs/archive/srs-v2.0.0-2026-08-31.md`; its acceptance-criteria text remains the historical detail record for every requirement classified UNCHANGED below)
**Hierarchy:** legal/security constraints → Product Constitution (`docs/product/product-constitution.md`) → **this SRS** → domain/architecture → UX/design → commercial policy → ADRs → roadmap
**Normative language:** MUST/SHALL = mandatory; SHOULD = high-priority; MAY = optional; WON'T = excluded.
**Status labels:** CURRENT = implemented and verified · TARGET = decided, not yet built · DEFERRED = explicitly postponed (phase noted).

## 0. Revision principles (v3)

1. One canonical authority per truth type; competing versions were removed, not layered.
2. Every v2 requirement (69 FR + 15 NFR) is classified: UNCHANGED / REFINED / SUPERSEDED / DEPRECATED / NEW. Full v2 text stays discoverable in the archive.
3. Workspace is now a first-class requirement domain (v2 gap — closed).
4. The offline specification is the implemented ADR-017 contract (the v2 §9.4 last-write-wins paragraph is SUPERSEDED — there is no LWW and client clocks never decide precedence).
5. Information-architecture requirements match the implemented navigation (EXECUTE/PLAN/KNOWLEDGE/REVIEW/SYSTEM; the v2 `Calendar` nav item is superseded by Week/Month semantics — see FR-11/FR-15 and `docs/ux/information-architecture.md`).
6. Future-phase capabilities (FrankenPHP/Octane runtime, Resend email, asset pipeline, analytics providers, repository split) are labeled TARGET/DEFERRED — never presented as CURRENT.
7. Hosted-AI allowance numbers are DECISION_REQUIRED (P33); no quota values appear as policy anywhere in this SRS.

## 1. Product definition

Kinevo is a workspace-scoped personal operating system that reconciles intention, reality, and context into executable action. What Kinevo is/is not, the core loop, user authority, and ownership model are defined in `docs/product/product-constitution.md` and are normative context for every requirement below. Tagline: "Kinevo — Turn intentions into execution."

In-scope capability domains (single-user, workspace-scoped): Today execution, Week/Month planning, Goals/Milestones/Programs, Tasks, Hard Landscape (global reality) + KRS/ICS import, Effective Schedule (recurrence, overrides, drafts, Sync Now), Knowledge (Notes/Canvas/Links), Progress/Review, AI proposals (provider abstraction, BYOK), offline reconciliation, notifications, subscriptions/entitlements, mobile contractual surface.

Out of scope through v1 (WON'T): team collaboration/RBAC, enterprise administration, AI-autonomous scheduling, chat/social features, CalDAV, annual billing (undecided), production quotas (P33).

## 2. Functional requirements

Status legend per requirement: **[UNCHANGED]** = v2 text still canonical · **[REFINED]** = v2 intent kept, wording/semantics tightened · **[NEW]** = introduced in v3 · **[SUPERSEDED]** = replaced by a named requirement/ADR.

### 2.1 Workspace (NEW domain — v2 gap closed)

- **FR-70 Workspace Entity [NEW/CURRENT]** — The system MUST provide first-class Workspaces: named, archivable (except the immutable default) contextual boundaries owned by the user; a default workspace SHALL be provisioned at registration and unassigned scoped rows adopted once.
- **FR-71 Workspace Scoping [NEW/CURRENT]** — Goals, Programs, Tasks, Notes, and Canvases MUST be workspace-scoped from creation; scoped list endpoints SHALL filter by the declared active workspace (absent = global view). Full semantics: `docs/product/workspace-model.md`.
- **FR-72 Hard Landscape Globality [NEW/CURRENT]** — Hard Landscape events and schedule placements MUST NOT carry workspace_id; Hard Landscape is global personal reality.
- **FR-73 Cross-Workspace Execution Views [NEW/CURRENT]** — Today, Week, and Month MUST be cross-workspace by default; a workspace filter layer for them is TARGET (MIGRATION_REQUIRED wiring, server `?workspace_id` already accepted on analytics).
- **FR-74 Workspace Filtered Progress/Review [NEW/TARGET]** — Progress and Review surfaces MUST support global and workspace-filtered perspectives (server support CURRENT; UI wiring TARGET).
- **FR-75 Active Workspace Authority [NEW/CURRENT]** — Active-workspace selection precedence (deep link > stored preference > server default) SHALL be identical across web and mobile; the server contract owns fallback resolution.

### 2.2 Today & execution

- **FR-01 24-Hour Timeline [UNCHANGED/CURRENT]** — Today renders the day 06:00–24:00 with placed events, hard landscape blocks, and empty slots.
- **FR-02 Dynamic Free Slots [UNCHANGED/CURRENT]** — free capacity is computed from the effective landscape and shown as empty slots.
- **FR-03 Quick Capture [REFINED/CURRENT]** — capture a task in seconds; lands in the active workspace; first-session Today additionally offers the start-here guide (capture vs goal paths).
- **FR-04 Sacred Anchor Multi-Track [UNCHANGED/CURRENT]** — anchor tasks are protected placement constraints; the Sacred Anchor producer feeds the scheduler (ADR-016).
- **FR-05 Recharge Timer [UNCHANGED/CURRENT]** — recharge/break periods are tracked and reflected in the Work-Life Ratio.
- **FR-06 Social Anchor [UNCHANGED/CURRENT]** — social commitments may be anchored into the day structure.
- **FR-07 Emergency / Mini Pause [UNCHANGED/CURRENT]** — pausing halts execution honestly and records the event; the week can be marked exceptional and recovered.
- **FR-08 Manual Lock [REFINED/CURRENT]** — users lock/unlock individual placements (`POST /tasks/{id}/assignment/lock|unlock`); locked placements are never moved by scheduler or rescheduler (ADR-015/016; browser LOCK journey).
- **FR-09 Subtask, Partial Completion & Promote [UNCHANGED/CURRENT]** — tasks decompose into subtasks; partial progress and promotion are supported.
- **FR-10 Manual Drag-and-Drop [UNCHANGED/CURRENT]** — manual reordering/adjustment with conflict protection.
- **FR-47 End-of-Day Reconciliation [REFINED/CURRENT]** — the deadline reconciliation phase moves unfinished work honestly to `missed` (seeded browser-proven); Morning Recovery (FR-48) offers guilt-free recovery.

### 2.3 Week / Month / planning surfaces

- **FR-11 Week View (7-day plan) [REFINED/CURRENT]** — the 7-day planning surface is named **Week** (v2 name "Kalender 7 Hari" superseded; no `Calendar` nav item exists). Cross-workspace by default.
- **FR-12 Four-Pillar Chart [UNCHANGED/CURRENT]** — life-pillar balance visualization with ChartMeta period+unit+legend semantics.
- **FR-13 Deadline Color Coding [UNCHANGED/CURRENT]** — deadline proximity is encoded accessibly (never color-only).
- **FR-14 Overload Detection [REFINED/CURRENT]** — capacity compares load against real recent behavior; overload is surfaced with a cut recommendation before the day cuts it for you.
- **FR-15 Month View [REFINED/CURRENT]** — the full-month grid is named **Month** (canonical name; cross-workspace by default).
- **FR-16 Month Markers [UNCHANGED/CURRENT]** — month cells carry goal/landscape markers.
- **FR-17 Day Navigation [UNCHANGED/CURRENT]** — keyboard-accessible day navigation across surfaces.
- **FR-18 Cross-Day Drag-and-Drop [UNCHANGED/CURRENT]** — moving work between days re-enters the scheduling contract (versioned apply, 409 on stale versions).

### 2.4 Goals, milestones, programs

- **FR-19 Yearly Goals CRUD [REFINED/CURRENT]** — goals carry horizon (Yearly/Quarterly/Monthly with ACTIVE caps) and are workspace-scoped.
- **FR-20 Monthly Goals & Auto-Breakdown [SUPERSEDED by FR-52 + FR-45]** — automatic breakdown is an AI *proposal* the user approves (FR-52); the deterministic structure tools remain (FR-45).
- **FR-21 Contribution Matrix [UNCHANGED/CURRENT]** — goal contribution visualization.
- **FR-22 Program Lifecycle [UNCHANGED/CURRENT]** — programs organize repeated work inside a goal; workspace-scoped.
- **FR-26 Program Intake [UNCHANGED/CURRENT]** — structured program creation flow.
- **FR-45 Hierarchical Decomposition [UNCHANGED/CURRENT]** — goal → milestone → task/subtask deterministic decomposition tools.
- **FR-50 Goal Horizon & Deadline [UNCHANGED/CURRENT]** — horizons + deadlines with deadline-health analytics.
- **FR-51 First-Class Milestones [UNCHANGED/CURRENT]** — milestones have their own lifecycle (planned/active/blocked/completed/dropped) and progress events.

### 2.5 AI proposals (authority boundary)

- **FR-52 Goal Breakdown Proposal [REFINED/CURRENT]** — AI proposes milestone structure; review is inline (list or detail surface); nothing is applied until explicit accept; edit-before-accept is supported; reject leaves the goal untouched. Post-accept continuation: milestones visible → Next Action points to Today.
- **FR-60 AI Provider Abstraction [UNCHANGED/CURRENT]** — providers behind one contract (disabled/ollama/openai-compatible/mock); capability-driven settings UI; connection test; resolution precedence persisted config > env defaults > disabled (ADR-011).
- **FR-61 Structured Output Validation [UNCHANGED/CURRENT]** — AI output is schema-validated then domain-validated; invalid output is an `AiOutputException` (422), never a silent mutation.
- **FR-62 AI Proposal Approval [UNCHANGED/CURRENT]** — pending-only proposals; accept/reject endpoints only; no auto-accept path exists.
- **FR-63 Explainable Scheduler Decisions [UNCHANGED/CURRENT]** — every placement can explain why (WhyThis/scheduling explanation surfaces).
- **FR-67 AI Usage Firewall [UNCHANGED/CURRENT]** — request budget firewall + runtime guards protect cost; usage summary distinguishes hosted vs BYOK.
- **FR-68 Provider Price Catalog [UNCHANGED/CURRENT]** — per-provider model pricing metadata; no quota numbers are policy.

### 2.6 Scheduling engine & reality

- **FR-27 Auto-Scheduling Engine [REFINED/CURRENT]** — deterministic generator over the effective landscape + capacity; hard constraints first, soft ranking second; same inputs → same draft; weekly automation may calculate but MUST NOT auto-apply (ADR-016).
- **FR-23 Conflict Rescheduler Priority [UNCHANGED/CURRENT]** — conflicts resolve by explicit priority rules.
- **FR-25 Override Reschedule [REFINED/CURRENT]** — **Permanent Shift** (moves the series from a date forward) and **One-Time Exception** (changes exactly one occurrence) with preview → Apply; source history preserved in `schedule_assignment_history`; precedence: exception > latest shift > base (ADR-015; browser journeys B/C/D).
- **FR-28 Dynamic Rescheduler [UNCHANGED/CURRENT]** — minimal-move proposals with before/after and reasons, never silent application.
- **FR-29 Sync Now [REFINED/CURRENT]** — on-demand recalculation (`POST /schedule/sync`); produces a reviewable draft/impact; never silently mutates accepted work; run locks prevent concurrent runs (ADR-016; browser S1–S4).
- **FR-64 Hard/Soft Scheduler Separation [UNCHANGED/CURRENT]** — hard constraints are inviolable; soft signals only rank.
- **FR-24 KRS PDF Import [REFINED/CURRENT]** — stage → parse → preview/edit → confirm → recurring Hard Landscape; per-row errors/warnings surfaced; partial import is explicit; discard supported. (Browser evidence: backend KrsImportApiTest + Hard-Landscape journeys.)
- **FR-30 iCal Integration [UNCHANGED/CURRENT]** — ICS import (stage/preview/confirm; conflicts never overwrite) and ICS export of the effective schedule.
- **FR-36 Holiday Detection [UNCHANGED/CURRENT]** — holidays affect capacity honestly.
- **FR-37 Boost Mode [UNCHANGED/CURRENT]** — temporary intentional overload with explicit setup/end.
- **FR-38/FR-39 Holiday Auto-Scheduling & End [UNCHANGED/CURRENT]** — holiday periods reschedule work and notify on end.

### 2.7 Tasks

- **FR-42 Task Notes [UNCHANGED/CURRENT]** — tasks carry notes/context.
- **FR-43 Evidence Attachments [UNCHANGED/CURRENT]** — completion evidence attachments (asset pipeline TARGET, P31 — current storage is direct upload).
- **FR-46 Task Templates [UNCHANGED/CURRENT]** — reusable task patterns.
- **FR-49 Dynamic Capacity Feedback Loop [REFINED/CURRENT]** — capacity derives from recent real completion behavior, not idealized plans.

### 2.8 Knowledge & context

- **FR-53 Knowledge Item Lifecycle [UNCHANGED/CURRENT]** — notes create/edit/archive; Tiptap-owned editing behind the Kinevo boundary.
- **FR-54 Knowledge Links [UNCHANGED/CURRENT]** — typed links (supports/references/derived_from/evidence_for/related_to) connect Goal/Task/Milestone/Program/Canvas/Note.
- **FR-55 Canvas Lifecycle [UNCHANGED/CURRENT]** — canvases (boards) with create/open/archive; Excalidraw owns drawing only (ADR-010); implementation detail is never user-facing.
- **FR-56 Canvas Version Conflict Protection [UNCHANGED/CURRENT]** — optimistic versioning with explicit reload-to-reconcile.
- **FR-57 Offline Knowledge/Canvas Mutations [REFINED/CURRENT]** — offline note/canvas edits queue and reconcile through the ADR-017 contract.
- **FR-58 Adaptive Context Capture [UNCHANGED/CURRENT]** — lightweight energy check-ins.
- **FR-59 Adaptive Context as Soft Signal [UNCHANGED/CURRENT]** — check-ins rank, never constrain (soft signal only).

### 2.9 Progress, review, notifications

- **FR-31 Annual Heatmap [UNCHANGED/CURRENT]** — activity heatmap with legend + accessible list.
- **FR-32 Monthly Realization vs Target [UNCHANGED/CURRENT]** — realization tracking.
- **FR-33 Achievement Badges [UNCHANGED/CURRENT]** — honest milestone recognition (no streak pressure).
- **FR-34 Daily Activity Log [UNCHANGED/CURRENT]** — the honest record of what happened.
- **FR-35 End-of-Day Prompt [UNCHANGED/CURRENT]** — reflection trigger.
- **FR-48 Morning Recovery [REFINED/CURRENT]** — missed tasks recover without guilt: reschedule, complete, or archive to backlog.
- **FR-40 Undo 30 Seconds [UNCHANGED/CURRENT]** — immediate undo window for destructive-ish actions.
- **FR-41 In-App Notifications [UNCHANGED/CURRENT]** — grouped unread/today/earlier notifications; global with workspace context.

### 2.10 Progress events & product analytics boundary

- **FR-69 Product Event Taxonomy & Analytics Boundary [REFINED/CURRENT]** — semantic, content-minimal events; retention taxonomy v1 is canonical at `docs/retention-events.md`; provider instrumentation is TARGET (P32); no sensitive note/task content in payloads.

### 2.11 Offline & synchronization

- **FR-44 Offline Support [REFINED/CURRENT]** — the ADR-017 contract is canonical: client mutation → durable IndexedDB queue (operation UUID) → reconnect → `POST /sync/reconcile` → idempotent operation ledger (same payload replays safely; different payload rejected) → optimistic version conflicts (base_version, stable 409) → canonical server outcome → client rehydration. **There is NO last-write-wins; client clocks NEVER decide precedence.** Web MutationQueue drain + conflict UX are CURRENT; durable mobile queue follows the same protocol in P36 (no second protocol).
- **FR-06/FR-44 interplay** — pause states and offline queue states coexist; sync status is app-wide (`SyncStatusPanel`).

### 2.12 Assets, uploads, notifications-transport (TARGET boundaries)

- **FR-65 Upload & Asset Pipeline [DEFERRED → P31]** — Uppy → validation → Pic Smaller → AssetStorage → object storage; no large binaries inside Note/Canvas JSON. Current: direct attachment upload only.
- **FR-66 Notification Provider Abstraction [DEFERRED → P30+]** — provider boundary defined; Gotify is a reference transport, not installed.

### 2.13 Subscriptions & entitlements

- **FR-71b Plan Entitlements [NEW/CURRENT]** — Free/Pro/Power entitlement matrix (`config/saas.php`) is enforced server-side (EntitlementService): workspace caps 1/5/15, AI request limits, reserved Power keys (advanced_analytics/wrapped/mobile_access — unenforced, P33 evidence-driven). Prices are launch hypotheses (`docs/product/commercial-model.md`).
- **FR-72b Subscription Lifecycle [NEW/CURRENT]** — subscription state (active/past due/canceled) drives effective plan code; the retired `personal` tier degrades to catalog defaults; downgrade safety protects existing work (billing.md).
- **FR-73b BYOK Entitlement [NEW/CURRENT]** — BYOK on Pro/Power only; BYOK usage never consumes hosted allowance; both ledgers remain Kinevo-owned billing truth.
- **FR-74b Payment Provider Boundary [NEW/CURRENT]** — Midtrans sandbox CURRENT; production flip is P33. Webhooks signed + idempotent; redirects never grant entitlement.

## 3. Non-functional requirements

- **NFR-01 Performance [REFINED]** — responsive interaction on commodity hardware; runtime migration (FrankenPHP/Octane) is TARGET P30 with BENCHMARK_REQUIRED status; P38 owns capacity envelopes.
- **NFR-02 Security [UNCHANGED]** — authentication, ownership scoping on every query, encrypted AI credentials, signed webhooks; P37 owns the full security gate (cross-user/IDOR/replay/upload/secret tests).
- **NFR-03 Privacy & Data Minimization [UNCHANGED]** — AI sends only minimal working-set context; no note/task content in analytics payloads; no behavioral sale of data.
- **NFR-04 Availability [UNCHANGED]** — self-hostable, infrastructure-portable; offline-first client behavior.
- **NFR-05 Backup & DR [UNCHANGED]** — backup + restore drill required as evidence (P35; unrestored backup ≠ evidence).
- **NFR-06 Scalability [UNCHANGED]** — single-user scale by design; multi-user is out of scope through v1.
- **NFR-07 Usability & Accessibility [REFINED]** — WCAG 2.2 AA baseline (axe-verified across Chromium/Firefox/WebKit in P28); keyboard operation, focus visibility, reduced-motion, color-independent states, 44px touch targets.
- **NFR-08 Reliability [REFINED]** — failure states explain, preserve data, and offer recovery (`docs/ux/interaction-states.md` §4 failure matrix is the canonical UX contract).
- **NFR-09 Maintainability [UNCHANGED]** — modular monolith boundaries; domain validation outside controllers/components.
- **NFR-10 Observability [REFINED]** — CURRENT: structured logs, AI ledger, health endpoints. TARGET: error tracking/metrics/alerting (P35), AI observability (P32).
- **NFR-11 Architecture Portability [UNCHANGED]** — no infrastructure lock-in.
- **NFR-12 Explainability of Automation [UNCHANGED]** — scheduling and AI decisions are explainable in user language.
- **NFR-13 AI Safety [UNCHANGED]** — AI output untrusted until validated+approved; no autonomous mutation.
- **NFR-14 Knowledge/Canvas Integrity [UNCHANGED]** — versioned conflict protection; Excalidraw/Tiptap never own business semantics.
- **NFR-15 Offline Integrity [UNCHANGED]** — ledger idempotency; queue is cache, server is canonical.

## 4. Architecture & data summary

Normative pointers (canonical, not duplicated here): `docs/architecture.md` (modular monolith, layering, provider boundaries, CURRENT/TARGET split), `docs/domain-model.md` (aggregates, invariants, Workspace first-class, EffectiveLandscape/OfflineOperation/AIProposal/Subscription/Entitlement), `docs/scheduling-engine.md` (deterministic contract), `docs/offline-sync.md` (ADR-017), `docs/ai-architecture.md` (provider abstraction + metering), `docs/api/openapi.yaml` (API contract of record), `docs/third-party/adoption-matrix.md` (third-party governance).

Data-model highlights (details in domain-model/migrations): workspaces, goals, milestones, programs, tasks + task_assignments, hard_landscape_events, schedule_overrides, schedule_assignment_history, scheduler_runs, notes, knowledge_links, canvas_documents/canvases, progress_events, adaptive_context, ai_proposals/ai_runs, offline_operations, subscriptions.

## 5. Mobile contractual boundaries

The NativePHP Android surface (Phase 27) consumes the same OpenAPI contract: locked mobile IA (Today · Tasks · Capture · Workspace · More), billing web-first, `operation_id` on every mutating call, offline status labels only (durable mobile offline = P36 via the SAME ADR-017 protocol — no second protocol). Authority: `docs/mobile-architecture.md`.

## 6. UI/UX requirements

- **IA** — canonical navigation: NOW (Today/Week/Month) · BUILD (Goals/Tasks) · THINK (Knowledge/Canvas) · REFLECT (Progress/Review) + Import & Sync, Notifications, Settings + Capture (`docs/ux/information-architecture.md`). The v2 `Calendar` nav item is superseded.
- **States** — loading/empty/success/partial/offline/queued/syncing/stale/needs-review/conflict/failed/unavailable/entitlement-blocked per surface: `docs/ux/interaction-states.md` (P28-011 matrix merged; archived matrix: `docs/archive/design-legacy-2026-08-31/state-machine-ui.md`).
- **Design language** — product: Kinevo Tactile Editorial (calm/focused/tactile); marketing: Editorial Constructivism. Canonical: `docs/ux/design-system.md`.
- **Content** — canonical terminology + bilingual readiness: `docs/ux/content-design.md`.
- **Motion** — mechanical/editorial/precise; prefers-reduced-motion respected: `docs/ux/motion.md`.

## 7. Security & privacy

Normative pointers: NFR-02/03 above; `docs/product/product-constitution.md` §6 (data ownership); claims that may be published are governed by `docs/marketing/claims-registry.md` — unverified claims (GDPR/SOC2/zero-knowledge/encrypted-everywhere) are PROHIBITED until P37 evidence exists.

## 8. Requirement migration table (v2.0.0 → v3.0.0)

| Classification | Count | Requirements |
|---|---|---|
| UNCHANGED | 44 | FR-01,02,04,05,06,07,09,10,12,13,16,17,18,21,22,26,45,50,51,60,61,62,63,64,67,68,23,28,30,36,37,38,39,42,43,46,53,54,55,56,58,59,31,32,33,34,35,40,41 · NFR-02,03,04,05,06,09,11,12,13,14,15 |
| REFINED | 17 | FR-03,08,11,14,15,19,24,25,27,29,44,47,48,49,52,57,69 · NFR-01,07,08,10 |
| SUPERSEDED | 1 | FR-20 (auto-breakdown → FR-52 proposal flow) |
| DEPRECATED | 0 | — |
| NEW | 9 | FR-70…FR-75 (Workspace domain) + FR-71b…FR-74b (entitlements/billing) |
| (v2 total) | 84 | 69 FR + 15 NFR — all preserved and traceable in `docs/archive/srs-v2.0.0-2026-08-31.md` |

Detailed flow-level traceability: `docs/requirements/requirements-traceability.md`.

## 9. ADR baseline (resolved)

ADR-001 architecture · ADR-002 frontend (amended: Inertia planned, never installed) · ADR-003 scheduler · ADR-004 knowledge · ADR-005 canvas · ADR-006 AI · ADR-007 deployment · ADR-008 mobile · ADR-009 knowledge editor boundary · ADR-010 Excalidraw adapter · ADR-011 AI provider abstraction · ADR-012 payment gateway · ADR-013 product tiers/pricing (historical; superseded values in billing.md) · ADR-014 third-party adoption strategy · ADR-015 effective schedule resolution · ADR-016 scheduler trigger/Sync Now/draft lifecycle · ADR-017 offline mutation reconciliation.

## 10. Implementation roadmap status

CURRENT: everything in §2 without a TARGET/DEFERRED label (P28-verified baseline: backend 1125 tests, Vitest 535, 3-engine browser matrix, axe-clean accessibility). TARGET: FrankenPHP/Octane (P30), email (P30), assets (P31), analytics/observability (P32), production commercial runtime (P33), repo split (P34), ops (P35), Android production (P36), security gate (P37), capacity gate (P38), RC (P39).

## 11. Source-of-truth declaration

This file is the **Kinevo SRS v3.0.0 Single Source of Truth**. It supersedes SRS v2.0.0 (archived). Requirement IDs are stable; changes require a recorded revision here. No other document may present itself as the requirements authority.

**End of SRS v3.0.0.**
