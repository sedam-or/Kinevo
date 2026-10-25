---
id: T-204
title: Create the progress module: migration, event subscribers and the rebuild command
milestone: M4
kind: feature
status: todo
depends_on: [T-201]
requirements: [PRG-FR-01, PRG-FR-02, PRG-FR-04, DOM-20, D-44, SEC-AC-01, SEC-AC-23]
branch: m4-t204-progress-schema-and-subscribers
budget_lines: 380
assignee: ""
---

# T-204: Create the progress module: migration, event subscribers and the rebuild command

## Goal

Facts about what moved are recorded once per source event, with the local date fixed at event time, and can be rebuilt from source tables.

## Context

> **Module** (`spec/modules/progress.md`): kind core, depends on `tasks` and `goals`; table `progress_events`; permissions `progress.summary.read`, `progress.goals.read`, `progress.review.read`.
> **DDL** (`06-domain-model.md` §7): `progress_events(id, account_id, source_event_id uuid, kind in (task_completed, task_partial, task_missed, task_reopened, milestone_done, goal_achieved, session_completed), workspace_id, goal_id, milestone_id, task_id, minutes int, occurred_at, local_date date, UNIQUE (account_id, source_event_id, kind))`, index `progress_by_day (account_id, local_date)`, standard RLS. Migration `crates/kinevo-mod-progress/migrations/20270201000100_progress_progress.sql`.
> **PRG-FR-01** subscriber of `TaskCompleted`, `TaskReopened`, `TaskStatusChanged` (to `partial` or `missed`), `MilestoneStatusChanged` (to `done`), `GoalStatusChanged` (to `achieved`), `SessionCompleted`; `ON CONFLICT (account_id, source_event_id, kind) DO NOTHING` makes replays no-ops. **PRG-FR-02** `local_date` = `occurred_at` in the account timezone **at event time**, stored. **PRG-FR-04** `minutes` for `session_completed` comes from the event payload (`floor((ended_at − started_at)/60 s)`), never recomputed.
> **Rebuild** (§4): `kinevo admin rebuild-progress <account>` recomputes `progress_events` from source tables (completed/reopened tasks via `completed_at`, sessions from `focus_sessions`) idempotently; it uses the same insert path with deterministic synthetic `source_event_id`s (UUIDv5 of kind + entity id + version) so a rebuild and live events do not double-count when run together. Subscribers use their own delivery rows (T-014); payloads carry ids, enums and integers only.

## Scope

**In:**
- Crate skeleton, descriptor (consumes the six events, no emits), migration, grants, subscribers, rebuild use case and the `admin rebuild-progress` subcommand in `kinevo-app` (T-040 CLI module), wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Read endpoints: T-205.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-progress/**` | create |  |
| `crates/kinevo-mod-progress/migrations/20270201000100_progress_progress.sql` | create |  |
| `crates/kinevo-app/src/cli/admin.rs` | modify | add `rebuild-progress` |
| `crates/kinevo-mod-progress/tests/{isolation,subscribers,rebuild}.rs` | create | AC-1…AC-8 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```text
$ kinevo admin rebuild-progress <account-id>     # exit 0; prints counts per kind
```

## Business rules

1. Replays are no-ops; deleting a source entity does not delete history.
2. A timezone change later never rewrites stored `local_date` values.

## Security

- `run_isolation` covers `progress_events`.
- The rebuild command is operator-only and audited (`audit_log`, action `progress.rebuilt`).

## Implementation notes

- Add the audit action string to the `AuditAction` enum (T-012) and to the audit action catalogue in `spec/standards/security-by-design.md` only if such a list exists; otherwise the enum is the registry.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal | `isolation::dom_progress_events_matches_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean | `isolation::sec_ac_01_progress_isolated` |
| AC-3 | **Given** each of the six events delivered twice **When** handled **Then** one row each | `subscribers::prg_fr_01_replay_is_noop` |
| AC-4 | **Given** an event at 23:30 UTC for a UTC+7 account **When** handled **Then** `local_date` is the next local day and stays after a timezone change | `subscribers::prg_fr_02_local_date_fixed_at_event_time` |
| AC-5 | **Given** a `SessionCompleted` payload with minutes **When** handled **Then** the row carries those minutes | `subscribers::prg_fr_04_minutes_from_payload` |
| AC-6 | **Given** a `TaskStatusChanged` to a non-tracked status **When** handled **Then** no row | `subscribers::untracked_status_ignored` |
| AC-7 | **Given** source tables with history **When** the rebuild runs twice **Then** identical rows and counts | `rebuild::rebuild_is_idempotent` |
| AC-8 | **Given** live events and a rebuild **When** both ran **Then** no double counting | `rebuild::rebuild_and_live_do_not_double_count` |

## Tests

```
cargo nextest run -p kinevo-mod-progress --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
