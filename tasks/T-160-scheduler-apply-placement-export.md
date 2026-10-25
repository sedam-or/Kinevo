---
id: T-160
title: Implement safe apply, manual placement, locks, removal, the schedule command port and ICS export
milestone: M3
kind: feature
status: todo
depends_on: [T-159]
requirements: [SCH-FR-09, SCH-FR-13, SCH-FR-14, SEC-AC-18, TST-19, LAND-FR-17, CA-10]
branch: m3-t160-scheduler-apply-placement-export
budget_lines: 420
assignee: ""
---

# T-160: Implement safe apply, manual placement, locks, removal, the schedule command port and ICS export

## Goal

Applying a draft is atomic and re-validated on the server, manual placements follow the same rules, and the tasks module's schedule hooks have a real implementation.

## Context

> **Apply** (`spec/modules/scheduler.md` SCH-FR-09, §6): `POST /schedule/drafts/{id}/apply {base_version, task_ids?}` + `Idempotency-Key`; in one account transaction: `SELECT schedule_states FOR UPDATE`, `SELECT draft FOR UPDATE`; guard `status = pending` (an `applied` draft returns the stored result with 200, idempotent) and `base_version = state.version` (else the draft becomes `stale`, committed as a marker, and 409 `schedule_version_conflict`); rebuild the context from the **current** landscape, fixed set and policies (not the snapshot); for each assignment (optionally filtered by `task_ids`, ≤ 500, else 422 `task_ids_not_in_draft`) run `validate`; any violation → the draft becomes `stale`, marker committed, 409 `draft_infeasible` with `details.violations[]`, nothing applied; then supersede replaced assignments (`state='superseded'`, `superseded_at`, `superseded_by_draft`), insert new ones (anchors `locked=true`, `source='auto'`, `schedule_version = new version`), transition tasks to `scheduled` through `TaskCommandPort::mark_scheduled(tx, task_id)` in the same transaction, bump `schedule_states.version`, set `review_state='clean'`, mark the draft `applied` with `decided_at`, mark older pending drafts of the same kind+horizon `stale`, and write outbox `AssignmentsApplied` (and `AssignmentSuperseded` per superseded one). Response `{version, applied, skipped}`. Any error rolls everything back; the draft stays `pending`.
> **SEC-AC-18**: the client body carries only `base_version` and `task_ids`; the server ignores any other field (422 `unknown_field`) and re-validates the **stored** draft.
> **Manual placement** (SCH-FR-14): `PUT /schedule/assignments/by-task/{task_id} {start_at, end_at, locked?, base_version}`: validates rules 1, 4 (valid range inside 24 h and ≥ `min_slot_minutes`), 7 and the DB overlap; ignores 5 and 8 but returns `warnings` `deadline_missed`/`reserve_exceeded`; result `source='manual'`, `locked` default true; errors `placement_invalid` 422 (`details.violations`), `schedule_version_conflict`, `task_not_schedulable` 409; bumps the version, supersedes the task's previous assignment.
> **Locks and removal** (SCH-FR-13): `POST /schedule/assignments/{id}/lock {locked, base_version}` (`version_conflict`); `DELETE /schedule/assignments/{id}` (204, task → `backlog`; `task_in_progress` 409); locking a past assignment is allowed; locked and manual assignments are never moved or superseded by drafts.
> **Command port** (`kinevo-ports`, T-107): implement `ScheduleCommandPort` (`has_current_assignment`, `supersede_assignment`, `on_task_deleted`) and add `TaskCommandPort::{mark_scheduled, mark_backlog}` implemented by `tasks` using its domain transition with `Actor::Scheduler` (the allowed transitions table already permits it); replace the no-op wiring in `kinevo-app`.
> **Export**: `GET /schedule/export.ics?from=&to=` — one VEVENT per current assignment (`UID = <assignment id>@<host>`, `SUMMARY` = task title as escaped text), reusing the escaping and folding module of T-153; permission `scheduler.export.read`.
> **TST-19**: fault injection at each statement of apply; concurrency: two applies race → exactly one succeeds and the other replays or conflicts.

## Scope

**In:**
- Use cases `ApplyDraft`, `PlaceAssignment`, `SetLock`, `RemoveAssignment`, `ExportSchedule`; handlers; DTOs; `ScheduleCommandPort` and `TaskCommandPort` implementations; wiring; OpenAPI regeneration.

**Out:**
- Reaction to changes (needs review) and jobs: T-161.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-scheduler/src/{app,infra,http}/apply.rs, placement.rs, export.rs` | create |  |
| `crates/kinevo-ports/src/tasks_command.rs` | create | `TaskCommandPort` and fake |
| `crates/kinevo-mod-tasks/src/infra/command_port.rs` | create | implementation |
| `crates/kinevo-mod-scheduler/src/infra/schedule_command_port.rs` | create | implementation |
| `crates/kinevo-mod-scheduler/tests/{apply_api,placement_api,export_ics}.rs` | create | AC-1…AC-10 |
| `crates/kinevo-app/src/modules.rs` | modify | replace no-op ports |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
#[async_trait] pub trait TaskCommandPort<T: Send>: Send + Sync {
    async fn mark_scheduled(&self, tx: &mut T, task: TaskId) -> Result<(), AppError>;
    async fn mark_backlog(&self, tx: &mut T, task: TaskId) -> Result<(), AppError>;
}
// POST /schedule/drafts/{id}/apply → 200 { version, applied: [task_id], skipped: [task_id] }
```

## Business rules

1. Apply validates against current state, never the snapshot.
2. A stale or infeasible draft is marked `stale` in its own committed marker so the UI can show `schedule.draft.stale`.
3. Re-applying an applied draft returns 200 with the same body.
4. Anchors are inserted `locked=true`.
5. Manual placement never moves other assignments.

## Security

- Forged draft body fields are rejected; only the stored draft is trusted (SEC-AC-18).
- `task_ids` bounded at 500.
- All writes use the account `Tx`; the overlap constraint is the last line of defence and its violation maps to a registry error, never a 500.

## Implementation notes

- The `stale` marker commit uses a second short transaction after the failed validation rolled back the main one.
- `mark_scheduled` is idempotent for tasks already `scheduled`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a pending draft and a matching `base_version` **When** applied **Then** assignments inserted, tasks `scheduled`, version +1, `review_state=clean`, draft `applied`, outbox event written | `apply_api::sch_fr_09_apply_happy_path` |
| AC-2 | **Given** a changed version **When** applied **Then** 409 `schedule_version_conflict` and the draft is `stale` | `apply_api::sch_fr_09_version_conflict_marks_stale` |
| AC-3 | **Given** a landscape change after the draft **When** applied **Then** 409 `draft_infeasible` with violations, nothing applied, draft `stale` | `apply_api::sch_fr_09_revalidates_against_current_state` |
| AC-4 | **Given** a client body containing assignments **When** applied **Then** 422 `unknown_field`; the stored draft is what is validated | `apply_api::sec_ac_18_forged_draft_ignored` |
| AC-5 | **Given** an applied draft **When** applied again with the same key **Then** 200 with the same body | `apply_api::apply_is_idempotent` |
| AC-6 | **Given** two concurrent applies **When** raced **Then** exactly one succeeds and the other replays or conflicts | `apply_api::sch_fr_09_concurrent_apply` |
| AC-7 | **Given** failure injected at each statement **When** applying **Then** nothing is committed and the draft stays pending | `apply_api::tst_19_fault_injection_apply` |
| AC-8 | **Given** a manual placement with a rule 1/7 violation **When** put **Then** 422 `placement_invalid`; with a missed deadline only warnings | `placement_api::sch_fr_14_manual_validation_and_warnings` |
| AC-9 | **Given** locked and manual assignments **When** a full draft is applied **Then** they are untouched | `placement_api::sch_fr_13_locks_are_never_moved` |
| AC-10 | **Given** assignments **When** exported **Then** VEVENTs with escaped titles and stable UIDs | `export_ics::scheduler_export_format` |

## Tests

```
cargo nextest run -p kinevo-mod-scheduler --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
cargo nextest run -p kinevo-mod-tasks --features kinevo-db/testing
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
