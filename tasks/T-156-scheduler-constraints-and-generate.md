---
id: T-156
title: Implement the hard constraints, validate and generate in the scheduler crate
milestone: M3
kind: feature
status: todo
depends_on: [T-155]
requirements: [SCH-FR-01, SCH-FR-03, SCH-FR-04, SCH-FR-13, SCH-FR-16, TST-05, CA-06]
branch: m3-t156-scheduler-constraints-and-generate
budget_lines: 400
assignee: ""
---

# T-156: Implement the hard constraints, validate and generate in the scheduler crate

## Goal

`generate` and `validate` are complete, deterministic and explainable, with golden fixtures reviewed against the legacy expectations.

## Context

> **Algorithm** (`spec/modules/scheduler.md` §2.2, normative): 1 rank non-anchor tasks; 2 the anchor is the task with `is_anchor` (at most one; if already in `fixed[]`, skip 4a); 3 `occupied = busy ∪ fixed`; 4 for each day ascending: `slots = free_slots`; `ceiling`; a) if the anchor is unplaced, `find_anchor_slot(slots, anchor_not_before, anchor_duration)` and, if found and it fits the cap, place it (reason `ANCHOR_FIRST_SLOT`); b) for each unplaced ranked task: if it does not fit the cap → remember `CAPACITY_CAP`, continue; else for each slot ascending (recomputed after each placement) with length ≥ duration, build the candidate at the slot start and place it if feasible; 5 unplaced tasks become `unassigned` with `CAPACITY_CAP` if ever blocked by the cap, else `NO_AVAILABLE_ANCHOR_SLOT` (anchor) or `NO_AVAILABLE_SLOT`; 6 moves/conflicts from `current_slot`; 7 assignments sorted by `(start, task_id)`, unassigned by `task_id`. Slots are recomputed from `occupied` after every placement.
> **Hard constraints** (§2.6, all evaluated, order = precedence): 1 `HARD_LANDSCAPE_COLLISION` overlaps any busy; 2 `LOCKED_TASK_MOVE` (validator only); 3 `ANCHOR_VIOLATION` duration ≠ anchor duration, or starts before its day's `anchor_not_before`, or not locked on apply; 4 `TEMPORAL_VALIDITY` outside the horizon or not inside one planning window; 5 `DEADLINE_FEASIBILITY` `task_deadline` set and end > deadline; 6 `DURATION_FIT` (validator only); 7 `ILLEGAL_OVERLAP` overlaps any fixed/placed range or another candidate; 8 `SAFETY_RESERVE` merged busy minutes (busy ∪ fixed ∪ placed ∪ candidate, **clipped to the planning windows**) > `floor(capacity_minutes * (10000 − reserve_bp) / 10000)` where `capacity_minutes` = total minutes of all planning windows in the horizon.
> **Reasons** (§2.7): per placed task, in order: `RANK_PRIORITY{tier}`, then those present of `RANK_GOAL_DEADLINE{at}`, `RANK_MILESTONE_DEADLINE{at}`, `RANK_TASK_DEADLINE{at}`, `RANK_PROGRESS{pct}` if > 0, then exactly one of `ANCHOR_FIRST_SLOT` or `FIRST_FITTING_SLOT{day_index}`. No free text.
> **Reschedule** (§2.8): not a separate algorithm; `moves` = tasks with `current_slot` placed elsewhere, new placements = tasks without `current_slot`, `conflicts` = tasks with a `current_slot` and no new placement. **SCH-FR-13** locked tasks arrive only in `fixed[]`. **SCH-FR-16** the application passes a clamped horizon; the crate never sees the clock.
> **Parity** (§12): fixtures are produced by running the crate and reviewing diffs against legacy expectations read with `git show legacy-v0:server/tests/Unit/Scheduling/...` (ranking, hard-constraint, anchor and draft-generator tests). Differences (integer reserve, day windows, anchor never falls through to a rule violation, ranking reduced to five keys) are recorded in the fixture's `legacy_differs` field with the reason; no fixture silently follows either side.
> **TST-05**: native and wasm32 give identical output bytes; the equality test runs where the wasm target and a runner are available (T-252 binds it), the native half is here.

## Scope

**In:**
- `constraints.rs` (`validate(candidate, ctx) -> Vec<Violation>`), `anchor.rs` (`find_anchor_slot`), `generate.rs`, `reasons.rs`.
- Golden fixtures under `crates/kinevo-scheduler/tests/fixtures/` (input, expected output, optional `legacy_differs`), table tests per rule, proptests: permutation invariance of `tasks`/`busy`/`fixed`, no assignment overlaps busy/fixed, all assignments inside windows, determinism across 100 random inputs.

**Out:**
- Persistence and assembly: T-157+. WASM bindings: T-252.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-scheduler/src/{constraints,anchor,generate,reasons}.rs` | create |  |
| `crates/kinevo-scheduler/tests/{constraints,generate,golden,props}.rs` | create | AC-1…AC-10 |
| `crates/kinevo-scheduler/tests/fixtures/*.json` | create | goldens |

## Interfaces

```rust
pub fn generate(input: &SchedulerInput) -> SchedulerOutput;
pub fn validate(candidate: &Candidate, ctx: &ValidationContext) -> Vec<Violation>;   // used by apply and manual placement
```

## Business rules

1. Every rule above has at least one failing and one passing table case.
2. The anchor is never placed by the ranked loop (§12.5).
3. `SAFETY_RESERVE` uses integer basis points only.
4. `validate` and `generate` share the same constraint functions.

## Security

- Work is bounded by the input bounds from T-155; a 500-task, 28-day input generates under 1 s natively (benchmark recorded, not a gate).

## Implementation notes

- `ValidationContext` carries horizon, days, busy, fixed, already-placed, params and the optional own range to exclude from overlap checks (used for reschedule and apply).
- Keep the loop allocation-light; clarity first, then the benchmark.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a fixture per hard rule **When** validated **Then** exactly the expected violation codes in precedence order | `constraints::sch_fr_03_each_rule_has_table_cases` |
| AC-2 | **Given** an anchor fitting a day **When** generated **Then** it is placed first with `ANCHOR_FIRST_SLOT`; with no slot, `NO_AVAILABLE_ANCHOR_SLOT` and no fall-through | `generate::sch_12_5_anchor_never_falls_through` |
| AC-3 | **Given** a daily cap **When** generated **Then** tasks beyond the cap go to the next day or end `CAPACITY_CAP` | `generate::capacity_cap_codes` |
| AC-4 | **Given** a deadline earlier than any slot **When** generated **Then** `unassigned` with `DEADLINE_FEASIBILITY` among the violations | `generate::deadline_infeasible_reported` |
| AC-5 | **Given** `current_slot` values **When** generated **Then** moves, new placements and conflicts are classified as specified | `generate::sch_2_8_moves_and_conflicts` |
| AC-6 | **Given** placed tasks **When** reasons are read **Then** the codes and order match §2.7 with params | `generate::sch_fr_04_reasons_order` |
| AC-7 | **Given** the golden fixtures **When** generated **Then** canonical output equals the expected bytes, with every `legacy_differs` documented | `golden::sch_fr_03_golden_fixtures` |
| AC-8 | **Given** shuffled `tasks`/`busy`/`fixed` **When** generated **Then** identical bytes | `props::sch_fr_01_permutation_invariance` |
| AC-9 | **Given** random inputs **When** generated **Then** no overlap with busy/fixed and everything inside windows | `props::placements_respect_hard_rules` |
| AC-10 | **Given** reserve basis points **When** a candidate crosses the limit **Then** `SAFETY_RESERVE`, with the one-minute boundary tested in integers | `constraints::sch_12_4_reserve_integer_boundary` |

## Tests

```
cargo nextest run -p kinevo-scheduler
cargo build -p kinevo-scheduler --target wasm32-unknown-unknown   # environment-limited if the target is absent
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
