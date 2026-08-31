# Kinevo — First-Week Retention Event Taxonomy (RET-007)

> **Document role:** Semantic contract ONLY. This is the canonical definition of
> the first-week product-event taxonomy produced during P28 (RET-007). It
> describes meaning, triggers, payload shape, deduplication, and privacy — it
> does **NOT** instrument anything. Provider transport/instrumentation happens
> in **P32** (`docs/roadmap/planned/P32-analytics-ai-observability.md`), which
> depends on this document. P32 MAY NOT redefine these semantics without a
> superseding record here.
>
> **Status:** ACTIVE (P28 closure, 2026-08-31). Supersedes the earlier minimum
> event list in
> `docs/roadmap/archive/planning-specs/KINEVO_THIRD_PARTY_ADOPTION_INTEGRATION_AND_RETENTION_UX_SPEC.md`
> §48 (archived planning evidence, not authority).

## North star (unchanged)

**Weekly Goal Progressing Users (WGPU)** — unique users in a seven-day window
who perform at least one meaningful progress action on one or more active goals.
Every event below is chosen because it feeds this north star or a supporting
first-week retention loop; none is added "because analytics is easy".

## Taxonomy rules (applies to every event)

- **Event name** — stable, snake_case, versioned as `name` (v1). P32 introduces
  a version envelope; the semantics in this document are the v1 contract.
- **Meaning** — one canonical product meaning per event. No event is overloaded.
- **Trigger** — the exact user/domain action that fires it. Only real actions;
  no fabricated "onboarding completion" without an actual surface.
- **Required properties** — the minimum content-minimal payload.
- **Optional properties** — additive, never required for the event to be valid.
- **Workspace semantics** — `workspace_id` is required when the action is
  workspace-scoped (Goals/Programs/Tasks/Notes/Canvas); `null` for global
  surfaces (Today/Week/Month, Schedule, Hard Landscape, Review/Progress global
  view, Import). Never add `workspace_id` to Hard Landscape events.
- **Deduplication semantics** — natural dedup key so replays/retries cannot
  double-count (ADR-017 operation UUID, or a domain key below).
- **Privacy constraints** — the payload never contains note/task/canvas content,
  AI prompts, provider keys, or raw provider responses (AGENTS.md AI rule).
- **Success meaning** — what the event proves, i.e. which retention hypothesis
  it evidences.

## Event catalog (v1)

### workspace_created
- Meaning: a user has a usable workspace boundary (default workspace is
  auto-provisioned at registration — `EnsureDefaultWorkspaceUseCase`).
- Trigger: explicit workspace creation OR implicit default provisioning at
  registration.
- Required: `workspace_id`, `workspace_type` (personal/family/team…).
- Optional: `name` (never in P32 raw if it contains PII — prefer `name_length`).
- Workspace: the created workspace's id.
- Dedup: `workspace_id`; provisioning is idempotent (`EnsureDefaultWorkspace`
  returns existing).
- Privacy: no personal context beyond type/length.
- Success: the user has an identity boundary to organize in.

### goal_created
- Meaning: intention is captured as a concrete goal.
- Trigger: `POST /goals` success.
- Required: `goal_id`, `horizon`, `workspace_id`.
- Optional: `has_deadline`, `description_length`, `source` (manual/import).
- Workspace: scoped.
- Dedup: `goal_id`.
- Privacy: never the goal title/description.
- Success: intention is now a first-class entity (WGPU input).

### ai_breakdown_requested
- Meaning: the user asked AI to propose a structure for a goal.
- Trigger: `POST /goals/{id}/breakdown-proposals` success.
- Required: `goal_id`, `workspace_id`.
- Optional: `provider_mode` (hosted/byok/local), `milestone_count`.
- Workspace: scoped.
- Dedup: `proposal_id`.
- Privacy: no goal content, no prompt, no provider response.
- Success: user is willing to try AI assistance (an "aha" entry).

### ai_proposal_accepted / ai_proposal_rejected
- Meaning: the user reviewed the AI proposal and decided (AI proposes, user
  decides).
- Trigger: `POST /ai/proposals/{id}/accept|reject` success.
- Required: `proposal_id`, `proposal_type` (goal_breakdown/canvas_proposal/…),
  `decision` (accept/reject), `workspace_id`.
- Optional: `accepted_milestone_count`, `edited_before_accept` (bool),
  `provider_mode`.
- Workspace: scoped.
- Dedup: `proposal_id` — one terminal decision per proposal.
- Privacy: no proposal content.
- Success: **the AHA** — a user who accepts a breakdown has an executable
  structure (strongest first-week signal; feeds WGPU).

### krs_import_started / krs_import_confirmed
- Meaning: reality-first onboarding — a recurring Hard Landscape is imported
  from a KRS file, parsed, previewed, confirmed.
- Trigger: import upload+parse (`krs_import_started`); confirm/apply
  (`krs_import_confirmed`).
- Required: `import_id`, `workspace_id`.
- Optional: `recurring_event_count`, `parsed_events`, `confirmed_events`,
  `partial` (bool — some rows rejected).
- Workspace: global (Hard Landscape is global); workspace_id = active at import.
- Dedup: `import_id`.
- Privacy: no KRS row content.
- Success: the user's fixed reality is now respected by scheduling (capacity
  truth).

### schedule_draft_reviewed / schedule_applied
- Meaning: weekly scheduling — draft seen (reviewed) and explicitly applied
  (applied). Never auto-applied (ADR-016).
- Trigger: draft banner rendered (`schedule_draft_reviewed`); apply
  (`schedule_applied`).
- Required: `run_id`, `workspace_id` (null when global).
- Optional: `proposed_assignments`, `changed_assignments`, `reality_impacts`.
- Workspace: schedule is cross-workspace; workspace_id = active.
- Dedup: `run_id` + decision.
- Privacy: no task titles.
- Success: user accepted a plan for their week (capacity → execution bridge).

### sync_now_used
- Meaning: user actively rescheduled via Sync Now rather than waiting for the
  weekly cadence.
- Trigger: `POST /schedule/sync` success.
- Required: `run_id`, `workspace_id` (null when global).
- Optional: `impact_count`, `needs_review` (bool).
- Dedup: `run_id`.
- Privacy: none (no content).
- Success: the user trusts Kinevo enough to re-plan mid-week.

### today_opened
- Meaning: the user entered the execution hub (default landing view).
- Trigger: `TodayView` mount.
- Required: `date`, `workspace_id` (null — Today is cross-workspace).
- Optional: `has_schedule`, `has_capacity`, `first_session` (bool — first-session
  guide shown).
- Dedup: natural `(user_id, date)` once per day per client session; a reload on
  the same day must not re-fire within a short window (e.g. 5 min client-side
  throttle).
- Privacy: none.
- Success: the user is in the place where work happens (LOGIN → TODAY loop).

### quick_capture
- Meaning: reality-first capture — a thought became a task in seconds.
- Trigger: quick-capture submit success.
- Required: `task_id`, `workspace_id`.
- Optional: `priority`, `has_duration`.
- Workspace: scoped (capture lands in active workspace).
- Dedup: `task_id`.
- Privacy: never the captured title.
- Success: low-friction habit loop (first session executable step).

### task_completed / milestone_completed
- Meaning: meaningful progress — a task or milestone reached a terminal state.
- Trigger: `SetTaskStatusUseCase`/`SetMilestoneStatusUseCase` completion
  (progress-event cascade).
- Required: `task_id`/`milestone_id`, `workspace_id`, `goal_id` (when linked).
- Optional: `scheduled_to_completed` (bool — executed on plan), `used_timer`
  (bool), `milestone_advanced`.
- Workspace: scoped.
- Dedup: natural domain key `(task_id, completed_at)` — the progress event is
  already idempotent server-side (ADR-017 replay semantics).
- Privacy: never the task title.
- Success: **the loop closes** — ACTION → visible consequence → progress
  (WGPU primary input).

### progress_viewed
- Meaning: the user looked at where progress moved (analytics/Workspace home).
- Trigger: Analytics surface mount OR WorkspaceHome current-goal render.
- Required: `workspace_id` (null for global analytics).
- Optional: `scope` (today/week/all), `goal_count`.
- Dedup: client throttle (once per session per surface).
- Privacy: none.
- Success: PROGRESS leg of the §99 loop (RET-008 feedback visible).

### review_completed
- Meaning: reflection — the user completed a Morning Review / weekly review
  (design.md §99 REVIEW leg).
- Trigger: review flow completion.
- Required: `review_id`, `workspace_id` (null when global).
- Optional: `recovered_tasks`, `rescheduled_tasks`.
- Dedup: `review_id`.
- Privacy: no note content.
- Success: reflection closes the loop (user understands what happened + next
  action).

### offline_mutation_reconciled
- Meaning: an offline queued mutation reached the server and reconciled cleanly
  (ADR-017).
- Trigger: `/sync/reconcile` success for a queued operation.
- Required: `operation_id`, `outcome` (applied/conflict_resolved), `kind`
  (create/update/delete).
- Optional: `conflicts` (count).
- Workspace: `workspace_id` from the operation target.
- Dedup: `operation_id` — idempotent replay already prevents double count.
- Privacy: none.
- Success: offline work is never lost — trust in the product even without a
  connection (mobile/web).

## Semantics NOT in v1 (deliberately)

- **Wrapped** (yearly review) — P32 defers; evidence-driven, not invented.
- **Streak / XP / gamification events** — rejected by design (design.md §99:
  no confetti, no streak pressure). Never add engagement-bait events.
- **Provider/telemetry spam** — AI proposal *generation attempt* (failed) is a
  RET-013 failure event, not a retention event; failures are triaged separately.

## Governance

- P28 instruments nothing. P32 builds a versioned envelope on these semantics,
  adds transport adapters (OpenPanel), and must keep `docs/ai-economics/` ledger
  truth (billing) separate from retention analytics.
- Any change to an event's meaning/required props is a taxonomy change: record it
  here with a date and reason; do not silently edit history.
