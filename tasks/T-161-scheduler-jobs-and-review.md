---
id: T-161
title: Implement the weekly draft job, the lease sweep, pruning and the needs-review reactions
milestone: M3
kind: feature
status: todo
depends_on: [T-160]
requirements: [SCH-FR-11, SCH-FR-12, MOD-07, TST-12, D-51]
branch: m3-t161-scheduler-jobs-and-review
budget_lines: 380
assignee: ""
---

# T-161: Implement the weekly draft job, the lease sweep, pruning and the needs-review reactions

## Goal

The server prepares next week's draft on schedule without ever applying it, and every relevant change flags the schedule for review without moving anything.

## Context

> **Weekly job** (`spec/modules/scheduler.md` SCH-FR-11): `scheduler.weekly_draft` is a recurring tick; per account, at `scheduler.weekly_draft.weekday` + `local_time` in the account timezone and if `scheduler.weekly_draft.enabled`, create a `weekly` full draft for the next planning week unless a pending one exists (unique `(account_id, horizon_start)`) or the week has no eligible tasks; never auto-applies; double ticks and worker restarts create no duplicate (`ON CONFLICT DO NOTHING` plus the job's unique key `(kind, account_id, scheduled_for)`).
> **D-51** (`spec/decisions.md`): the in-app notice `schedule.weekly_draft_ready` is **derived**, not stored: `GET /schedule/drafts?status=pending` returns the weekly draft and the web shows a banner while one is pending. There is no `notices` table in 1.0. The outbox event `DraftCreated` stays for future channels.
> **Sweep and prune** (§8): `scheduler.lease.sweep` every tick marks expired `running` rows `failed` with `error_code=lease_expired`; `scheduler.drafts.prune` daily deletes non-pending drafts older than 30 days.
> **Needs review** (SCH-FR-12): on `LandscapeChanged`, `TaskSchedulingInputsChanged`, `TaskStatusChanged`, `TaskDeleted`, `GoalStatusChanged`, `GoalDeleted` and `PolicyChanged` (scheduler keys only): re-validate the current assignments against current state (locked ones are not moved but all rules are checked); if any violation exists set `review_state='needs_review'` with `review_reason` = the first violation code and emit `ScheduleNeedsReview`; **never move anything**. Handlers are idempotent and run from the outbox dispatcher with their own delivery rows (T-014).
> **TST-12** retention/jobs: jobs registered through the descriptor and handled; **MOD-07** with `scheduler` the registry still passes.

## Scope

**In:**
- Job handlers and registration in the descriptor; event subscribers; `ReviewChecker` use case reusing `validate` and the input builder.

**Out:**
- UI: T-163.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-scheduler/src/app/{weekly,sweep,prune,review}.rs, src/infra/subscribers.rs` | create |  |
| `crates/kinevo-mod-scheduler/src/lib.rs` | modify | register jobs and subscribers |
| `crates/kinevo-mod-scheduler/tests/{weekly_job,sweep_prune,review_state}.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
pub async fn run_weekly(ctx: &JobCtx, clock: &dyn Clock) -> Result<(), JobError>;   // registered as "scheduler.weekly_draft"
```

## Business rules

1. The job uses the account's own timezone and policies (account scope overrides instance defaults).
2. No eligible tasks → no draft and no error.
3. The reaction never writes assignments; it only writes `schedule_states.review_*`.
4. Non-scheduler policy changes do not flag review.

## Security

- Jobs and subscribers run in a `SystemTx` only to enumerate account ids, then per-account `Tx` for all reads/writes (RLS applies).
- No task titles or other S2 values in job logs.

## Implementation notes

- Use the injected clock in tests and the e2e fake-clock endpoint compiled only with feature `e2e-fake-clock`.
- A needs-review flag is cleared by apply or a sync `no_changes` (T-159/T-160).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the configured weekday/time in the account zone **When** the tick runs **Then** one weekly draft is created and not applied | `weekly_job::sch_fr_11_creates_pending_weekly_draft` |
| AC-2 | **Given** a double tick or restart **When** the job runs again **Then** no second draft | `weekly_job::no_double_draft_after_restart` |
| AC-3 | **Given** no eligible tasks or the job disabled **When** the tick runs **Then** no draft | `weekly_job::no_draft_when_empty_or_disabled` |
| AC-4 | **Given** an expired lease **When** the sweep runs **Then** the row is `failed` with `lease_expired` | `sweep_prune::lease_sweep_expires` |
| AC-5 | **Given** old non-pending drafts **When** the prune runs **Then** only those older than 30 days are deleted | `sweep_prune::drafts_prune_retention` |
| AC-6 | **Given** each trigger event **When** delivered and a violation exists **Then** `needs_review` with the first violation code and no assignment changed | `review_state::sch_fr_12_each_trigger_flags_review` |
| AC-7 | **Given** a non-scheduler policy change **When** delivered **Then** no flag | `review_state::unrelated_policy_does_not_flag` |
| AC-8 | **Given** the descriptor **When** the registry check runs **Then** jobs have handlers and events are declared | `weekly_job::mod_07_jobs_registered_with_handlers` |

## Tests

```
cargo nextest run -p kinevo-mod-scheduler --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
