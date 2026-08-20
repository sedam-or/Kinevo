# Changelog

All notable changes to Kinevo are documented here.

This project follows Semantic Versioning. Format based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Version numbers below
refer to the **application**. The **SRS version** (currently 2.0.0) and **API
version** (v1) are tracked separately in `docs/SRS.md` and
`docs/api/openapi.yaml` respectively.

Release governance: see `docs/release-management.md`.

## [Unreleased]

### Added

- Added the Work-Life Ratio analytics (TASK-126): the Analytics view aggregates
  the user's already-recorded productive (focus) and Recharge minutes over a
  period and derives the normative WorkRatio/RechargeRatio (`GET
  /analytics/work-life`, FR-05). The result is presented as a time-balance
  indicator with a per-day series and a disclaimer — it is never framed as a
  health diagnosis. The Recharge timer now derives its day ratio from the same
  domain formula.
- Added the Boost Mode (TASK-125): during a confirmed Break Mode period the
  Today view can set a holiday boost target as a percentage of daily capacity
  (`GET/POST /boost`), capped at the 70% safety limit with an explicit warning
  (FR-37). The recommendation reuses the Capacity feedback loop — Boost Mode is
  offered when recent realization exceeds 90% with no burnout signal, and the
  suggestion is suppressed while a burnout signal is active (FR-49). The target
  is scoped by start/end datetime within the active break and used as a
  temporary capacity ceiling when generating schedule drafts; ending it returns
  the scheduler to the baseline target (FR-38).
- Added the Break Mode (TASK-124): the Today view can now start a break/holiday
  over a date range (`POST /break`) and end it early (`POST /break/end`). The
  covered weeks are tagged exceptional for capacity feedback (FR-49), EOD
  notifications are suppressed during an active break, and a scheduled
  `break:notify-end` job (daily 20:30 local) creates exactly one holiday-end
  notification per break period three days before it ends (FR-39, FR-41). The
  day/week schedule queries expose the recovery state (`break`), and the Today
  view shows a Break Mode banner with an End Break action and a start/end summary
  (FR-36).
- Added the Emergency Pause (TASK-123): the Today view can now tag the current
  week as an exceptional recovery period, keep user-selected tasks in place, and
  shift every other eligible task +1 week to the same weekday
  (`POST /schedule/emergency-pause`). Locked tasks are never auto-moved; tasks
  with no feasible slot next week stay in place and are reported as conflicts;
  tasks are never deleted and ownership is preserved. The week is tagged in a new
  `pause_events` table, EOD notifications are suppressed while audit data is
  preserved, the day/week schedule queries expose the recovery state (`pause`),
  and the Today view shows a recovery banner during the exceptional week (FR-07,
  FR-47, FR-49).
- Added the Mini Pause (TASK-122): the Today view can now move every eligible
  task scheduled today to the first feasible slot on the next day in one action
  (`POST /schedule/mini-pause`). Locked tasks are never auto-moved; tasks with
  no feasible next-day slot stay in place and are reported as conflicts. The
  change is persisted atomically at the next schedule version, logged as a
  `mini_pause` activity event, and explained to the user (FR-07).
- Added the Recharge Timer (TASK-121): after every two completed focus
  sessions, the Today view offers a 15-minute Recharge timer (Start/Pause/
  Resume/Complete/Abandon). Recharge is persisted server-side in a new
  `recharge_sessions` table and counts as Recharge, never Productive Time; the
  recorded duration is the tracked duration, and the day's RechargeMinutes feed
  the WorkRatio/RechargeRatio shown alongside the timer (FR-05). New REST
  endpoints under `/recharge` with a 409 conflict when a timer is already
  running.
- Added the Execution Timer (TASK-120): tasks can now be worked with a persisted
  execution timer (`start`, `pause`, `resume`, `complete`, `abandon`) shown in
  the Today NOW card. Timer state lives server-side in a new `execution_sessions`
  table and elapsed time is always derived from persisted timestamps, never a
  client-only model; completing a timer records a `FocusSession` from the tracked
  duration, advances the task (completed when no subtasks remain, otherwise
  `continued` with a scheduled continuation), and logs `task_started` /
  `task_abandoned` / `task_completed` / `task_continued` activity (FR-05/FR-06/
  FR-18/FR-25). New REST endpoints under `/execution` with a 409 conflict when a
  timer is already running.
- Added the Offline Synchronization UX (TASK-115): the shell now presents eight
  visible sync states (Online, Offline, Queued, Syncing, Saved, Conflict,
  Retrying, Failed) with plain-language explanations of whether a mutation is
  persisted server-side, stored locally, waiting for synchronization, or in
  conflict. A `SyncStatusController` bridges the general offline mutation queue
  into the shell store, a new `HttpMutationApplier` replays queued task/note/
  quick-capture/canvas mutations through the existing API client, and a visible
  `SyncStatusPanel` shows badge + queued count + a "Retry sync" action for
  retrying/failed states; `retrying` joined the color-independent visual states
  (FR-44/FR-57, SRS §9).
- Added the Canvas Context / Linking (TASK-114): canvases can now be attached to
  Goals, Milestones, Programs, Tasks, and Notes through the shared
  `knowledge_links` relation (canvas as link source), surfaced in a Context
  panel in the canvas workspace with create/remove and milestone-aware target
  selection; the backend gains `GET/POST /canvases/{id}/links` and
  `DELETE /canvases/{id}/links/{linkId}`, and `note` is now a valid link target
  (FR-54/FR-55, SRS §10.5).
- Added the Canvas Workspace UI (TASK-113): a `canvas` shell view with canvas
  list and create, an Excalidraw workspace opened through the Vue → CanvasHost →
  CanvasAdapter → React Island boundary (ADR-005) with autosave, Saved/Saving/
  Error/Offline/Conflict save states, read-only mode, light/dark/auto theme, and
  archive-with-confirmation; the backend gains rename (`PATCH /canvases/{id}`)
  and archive (`POST /canvases/{id}/archive`) endpoints and `canvases.archived_at`
  (FR-55/FR-56/FR-57, SRS §7.5, §8.5).
- Added the Knowledge Linking UI (TASK-112): create and remove Note → Goal /
  Milestone / Program / Task / Canvas links from the note editor, with a typed
  link store and a LinkManager that resolves the target context (milestones
  dependent on the selected goal) and surfaces duplicate/validation errors;
  the backend link target set now also supports Canvas (FR-54, SRS §10.5).
- Added the Tiptap Vue binding (TASK-111): an `EditorHost` component that
  connects Vue → EditorAdapter → Tiptap behind the replaceable editor boundary
  (canonical structured JSON, derived markdown/plain text, readOnly/theme), now
  powering note editing with autosave (FR-53, SRS §10).
- Added the Notes UI (TASK-110): note list and full-text search, create and
  edit with debounced autosave and Saved/Saving/Error/Offline/Conflict states
  (optimistic versioning, 409 conflict), and linked-entity display, wired into
  the Knowledge view (FR-53/FR-54).
- Added a consistent Conflict / Lock / Explainability UI (TASK-109): a shared
  visual-state badge system (glyph + text + non-color pattern for locked,
  conflict, overdue, draft, proposed, offline, syncing, queued, failed) applied
  across Today, the task list, and the sync indicator, plus scheduler
  explanation reason codes surfaced in the Schedule Draft view (FR-63).
- Added the Schedule Draft / Dynamic Rescheduler UI (TASK-108): generate and
  preview a deterministic weekly draft (accepted/rejected tasks with reasons),
  apply it atomically at the next schedule version, and propose/apply a dynamic
  reschedule showing per-move BEFORE / AFTER / REASON and conflict flags —
  backed by new `POST /schedule/draft[/apply]` and
  `POST /schedule/reschedule[/apply]` endpoints (FR-27/FR-28).
- Added the global Quick Capture UI (TASK-107): a modal reachable from any
  authenticated view with title, priority, size/duration, program, goal,
  milestone, and due-date fields; when the day has no free slot it presents the
  SRS order Manual Swap / Auto Swap / Schedule Later as actionable strategies
  (FR-03).
- Added the Goals / Milestones / Programs UI (TASK-106): goal list and detail
  (outcome, deadline, progress, status actions), a sequence-ordered milestone
  timeline with status transitions, and a program list with creation, wired to
  the Goal/Milestone/Program APIs (FR-19/20/22/26/50/51).
- Added the Task UI (TASK-105): task list, creation, detail and edit, backend-
  validated status transitions, subtasks (add/toggle/promote), and partial
  completion, wired to the Task APIs (FR-09/FR-45).
- Added the Week / Calendar UI (TASK-104): a 7-day week grid and monthly
  calendar with date navigation, capacity/overload indication, per-day deadline
  markers and task assignments, wired to the real `GET /week`, `GET /calendar`,
  and `GET /schedule` APIs (FR-11/FR-15).
- Added the Today UI (TASK-103): the primary execution surface with a dated
  header, sync state, NOW and NEXT cards, a 06:00–24:00 timeline (scheduled
  tasks, Hard Landscape, empty slots, lock/conflict states), capacity
  indication, and global Quick Capture wired to `GET /today` and
  `POST /quick-capture` (FR-01/FR-02/FR-27).
- Added the global typed API / state client (TASK-102): a shared `fetch`-based
  client with a canonical error taxonomy (401/403/404/409/422/429/503),
  automatic retry with backoff, offline detection, and a global API state store
  for loading/error/connectivity — now shared by the auth client.
- Added the Authentication UI (TASK-101): login and first-owner registration
  forms, session restoration on load, logout, and profile/settings (display
  name, timezone, locale, week start) wired to the existing Sanctum bearer-token
  API, behind a new `/app` SPA host.
- Added the Vue application shell (TASK-100): responsive layout with persistent
  desktop side navigation and mobile bottom navigation (Today/Week/Calendar/
  Goals/Roadmap/Knowledge/Analytics/Settings), global error boundary, global
  loading state, sync and notification indicators, and light/dark/system theme
  handling.
- Added Auto Swap (`POST /tasks/{taskId}/auto-swap`): explicitly move the
  lowest-priority unlocked task on the target day (farthest deadline as
  tie-breaker) to a feasible slot on the following day, then place a task into
  the vacated slot — locked tasks are never moved, Hard Landscape is never
  violated, and the swap commits atomically with a user-visible explanation
  (FR-03/FR-23/FR-28).
- Added Quick Capture placement (`POST /quick-capture`): capture a task and
  attempt immediate placement into the first feasible empty slot today,
  returning a task + assignment on success, or `TASK_NO_CAPACITY` with the
  Manual Swap / Auto Swap / Schedule Later strategies when no slot fits
  (FR-03).
- Added Schedule Overrides (CRUD under `/schedule-overrides`) supporting
  permanent shifts and one-time exceptions on recurring Hard Landscape series,
  with explicit placement precedence (hard landscape > locked task > explicit
  override > recurrence > ordinary) and no silent mutation of source history
  (FR-25).
- Added a deterministic, timezone-aware recurrence engine (RFC-5545 subset:
  daily/weekly, BYDAY, INTERVAL, COUNT/UNTIL) with bounded occurrence
  generation, duplicate prevention, and exception/cancelled-occurrence support
  (FR-46/FR-25).
- Added the Hard Landscape domain and CRUD API (`GET/POST /hard-landscape`,
  `GET/PATCH/DELETE /hard-landscape/{id}`) for defining non-negotiable schedule
  boundaries, with type (permanent/recurring/one-time), recurrence support, and
  overlap conflict detection (FR-27/FR-28). Hard Landscape is now reported in
  the Today schedule view.
- Added the Schedule Query API (`GET /today`, `GET /schedule`, `GET /week`,
  `GET /calendar`) exposing the canonical Today/range/week/month schedule with
  task, program/goal/milestone context, lock and conflict state, capacity
  indicators, and empty (fillable) slots (FR-01/FR-11/FR-15).
- Added release-management lifecycle documentation and release validation tooling.

## [0.4.0] — 2026-08-17

### Added

- Identity/profile baseline:
  - Sanctum bearer-token authentication (register, login, logout, me).
  - Owner `profiles` table with settings (display_name, locale, timezone,
    week_start_day).
  - Domain layer: `Profile` entity, `ProfileSettings` value object,
    `ProfileRepository` contract, Eloquent implementation.
  - Application use cases: RegisterUser, LoginUser, LogoutUser, GetProfile,
    UpdateProfile.
  - API endpoints under `api/v1`: `/auth/register`, `/auth/login`,
    `/auth/me`, `/auth/logout`, `/profile`.
- OpenAPI `Identity` tag and schema additions.
- Feature tests for auth and profile ownership (17 tests total).

## [0.3.0] — 2026-08-17

### Added

- Environment/config/secrets baseline:
  - `docs/environment.md` contract.
  - `server/.env.example` annotated with secret vs non-secret defaults.
  - `scripts/check-secrets.sh` enforced in CI.

## [0.2.0] — 2026-08-17

### Added

- Docker development environment:
  - `infrastructure/docker/` (PHP 8.4-FPM Alpine image, entrypoint).
  - `infrastructure/docker-compose.yml` (app + PostgreSQL 17).
  - Makefile targets: `up`, `down`, `logs`, `migrate`, `shell`.

## [0.1.0] — 2026-08-17

### Added

- Repository skeleton:
  - Laravel 13 modular monolith under `server/` (PHP 8.4+, PostgreSQL).
  - Migrations canonicalized under `database/migrations/`.
  - CI pipeline (Pint, PHPStan, PHPUnit, repository validation).
- Architecture baseline: SRS v2.0.0, design, architecture, domain model,
  scheduling, knowledge, offline, AI, deployment, test strategy docs.
- ADR baseline (ADR-001..ADR-007).
- Repository bootstrap tooling (`scripts/`, `Makefile`).

[0.4.0]: https://github.com/sedam-or/Kinevo/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/sedam-or/Kinevo/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/sedam-or/Kinevo/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/sedam-or/Kinevo/releases/tag/v0.1.0