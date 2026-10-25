---
id: T-104
title: Implement the milestone endpoints, reordering and the all-done event
milestone: M2
kind: feature
status: todo
depends_on: [T-103]
requirements: [GOAL-FR-02, GOAL-FR-05, GOAL-FR-06, SEC-AC-14]
branch: m2-t104-goals-milestones
budget_lines: 360
assignee: ""
---

# T-104: Implement the milestone endpoints, reordering and the all-done event

## Goal

Milestones can be added, edited, completed, reordered and deleted under a goal, with the date rule and the all-done event.

## Context

> **Endpoints** (`spec/modules/goals.md` §2): `GET /goals/{id}/milestones` (ordered by `position, id`); `POST /goals/{id}/milestones {title, description?, target_date?, position?}` (201; `goal_not_active` for achieved/abandoned goals); `PATCH /milestones/{id}` (If-Match); `POST /milestones/{id}/status {to: open|done, base_version}`; `POST /goals/{id}/milestones/reorder {ids}` (`reorder_set_mismatch` 422 unless the ids are exactly the goal's milestones); `DELETE /milestones/{id}` (If-Match; tasks keep `goal_id` and lose `milestone_id`, done by the tasks module reacting through `TaskDetachPort::detach_milestone`).
> **GOAL-FR-02**: a milestone's `target_date` must not be after its goal's `target_date` when both are set → 422 `milestone_after_goal`. **GOAL-FR-05**: milestone `done` is manual; when the last open milestone becomes done, `GoalMilestonesAllDone` is emitted (nothing changes automatically). **Events**: `MilestoneCreated`, `MilestoneStatusChanged`, `MilestoneDeleted`, `GoalMilestonesAllDone`.
> **Validation**: milestone `position` int 0–10000; title bounded by `goals.title_max_chars`; description `long_text`.
> **SEC-AC-14**: mass assignment attempts (`goal_id`, `account_id`, `id`, `version`) → 422 `unknown_field`.
> **Reorder**: the server assigns positions 0..n-1 in the given order within one transaction and bumps each changed milestone's `version`.

## Scope

**In:**
- Domain: `Milestone`, date rule, reorder validation; use cases; handlers for the six endpoints; `TaskDetachPort::detach_milestone` (add to the port from T-103 with a fake).

**Out:**
- UI: T-110.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-goals/src/domain/milestone.rs` | create | pure rules |
| `crates/kinevo-mod-goals/src/{app,infra,http}/milestones.rs` | create |  |
| `crates/kinevo-ports/src/goals.rs` | modify | `detach_milestone` |
| `crates/kinevo-mod-goals/tests/milestones_api.rs` | create | AC-1…AC-8 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub fn check_dates(goal: Option<Date>, milestone: Option<Date>) -> Result<(), DomainError>;   // milestone_after_goal
pub fn validate_reorder(current: &[MilestoneId], requested: &[MilestoneId]) -> Result<(), DomainError>;   // reorder_set_mismatch
```

## Business rules

1. Adding to an achieved or abandoned goal is refused; paused goals accept milestones.
2. Reorder replaces positions atomically; a missing or extra id fails the whole request.
3. Setting `done` on the last open milestone emits `GoalMilestonesAllDone` exactly once per transition.
4. Updating a goal's `target_date` earlier than an existing milestone date is allowed; the UI warns (no server rule).

## Security

- Foreign goal or milestone ids → 404.
- `deny_unknown_fields` on all request DTOs.

## Implementation notes

- Milestone titles use the same policy bound as goals (`goals.title_max_chars`).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a goal date and a later milestone date **When** creating **Then** 422 `milestone_after_goal`; equal or earlier passes | `milestones_api::goal_fr_02_date_rule_table` |
| AC-2 | **Given** an achieved goal **When** adding a milestone **Then** 409 `goal_not_active` | `milestones_api::goal_not_active_blocks_add` |
| AC-3 | **Given** three milestones **When** reordering with all ids **Then** positions follow; with one missing id 422 `reorder_set_mismatch` | `milestones_api::reorder_exact_set` |
| AC-4 | **Given** the last open milestone **When** marked done **Then** one `GoalMilestonesAllDone` event; marking again emits none | `milestones_api::goal_fr_05_all_done_event_once` |
| AC-5 | **Given** a milestone with tasks (fake) **When** deleted **Then** `detach_milestone` is called in the same transaction | `milestones_api::goal_fr_06_delete_detaches_tasks` |
| AC-6 | **Given** `PATCH` with `goal_id` **When** sent **Then** 422 `unknown_field` | `milestones_api::sec_ac_14_mass_assignment` |
| AC-7 | **Given** a stale `If-Match` **When** patching **Then** 409 `version_conflict` | `milestones_api::patch_version_conflict` |
| AC-8 | **Given** another account's milestone id **When** accessed **Then** 404 | `milestones_api::foreign_milestone_not_found` |

## Tests

```
cargo nextest run -p kinevo-mod-goals --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
