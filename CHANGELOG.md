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