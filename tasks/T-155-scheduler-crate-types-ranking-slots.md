---
id: T-155
title: Create the pure kinevo-scheduler crate: wire types, ranking, free slots and capacity ceiling
milestone: M3
kind: feature
status: todo
depends_on: [T-042]
requirements: [SCH-FR-01, SCH-FR-02, SCH-FR-03, CA-06, CQ-08, D-17]
branch: m3-t155-scheduler-crate-types-ranking-slots
budget_lines: 380
assignee: ""
---

# T-155: Create the pure kinevo-scheduler crate: wire types, ranking, free slots and capacity ceiling

## Goal

A dependency-free crate defines the scheduler wire types with canonical JSON and the pure ranking, free-slot and ceiling functions with table tests.

## Context

> **Contract** (`spec/modules/scheduler.md` §2.1): `generate(input) -> output` is total and pure: no I/O, clock, randomness, time zones, floating point, hash-map iteration order or `async`; native and `wasm32-unknown-unknown`. Instants are `i64` epoch seconds, durations `u32` minutes. `algorithm_version = "sched-1"`.
> **Wire** (`standards/contracts.md` §4): `scheduler_input_v1` {schema, horizon, days[{start,end,anchor_not_before}], busy[], fixed[{task_id,start,end}], tasks[{task_id,duration_minutes,priority_tier,goal_deadline,milestone_deadline,task_deadline,progress_pct,is_anchor,current_slot}], params{min_slot_minutes,reserve_bp,anchor_duration_minutes,daily_capacity_bp}}; `scheduler_output_v1` {schema, algorithm_version, assignments[{task_id,start,end,reasons[{code,params}]}], unassigned[{task_id,code,violations[]}], moves[], conflicts[]}. The wire carries RFC 3339 instants; the crate converts at its boundary. Arrays are sorted as in §2.2; golden tests compare the canonical (JCS) bytes.
> **Ranking** (§2.3): `RANK_KEY` lexicographic, all integers: `priority_tier` asc; `goal_deadline` asc, absent last; `milestone_deadline` asc, absent last; `task_deadline` asc, absent last; `progress_pct` **desc**; `task_id` asc.
> **Free slots** (§2.4): `free_slots(D, occupied)` = day range minus merged occupied ranges (adjacent and overlapping ranges merge); keep gaps ≥ `min_slot_minutes`; ranges are half-open, so a task may start exactly when a busy range ends.
> **Ceiling** (§2.5): `ceiling = floor(sum(slot_minutes) * daily_capacity_bp / 10000)` in integer arithmetic on the day's free minutes before the anchor step; `null` ⇒ no ceiling; `fits_cap(c, used, d) = c is null OR used + d ≤ c`.
> **SCH-FR-02**: the crate depends only on `kinevo-kernel` (ids and `Instant`), `serde`, `serde_json` and a JCS serialiser (`serde_jcs`, or an in-repo canonicaliser of at most 150 lines if the crate is unsuitable for wasm32); a dependency-tree test fails if `tokio`, `sqlx`, `jiff`, `rand`, `uuid::now_v7` or `std::time::SystemTime` appears.

## Scope

**In:**
- Crate `kinevo-scheduler` with `model.rs` (typed input/output, serde with `deny_unknown_fields`, schema field checked), `canonical.rs` (canonical JSON bytes), `rank.rs`, `slots.rs`, `ceiling.rs`.
- Dependency-tree test and the wasm32 build check recipe in `just check` (skipped with a printed notice if the target is not installed).

**Out:**
- Constraints, placement and `generate`: T-156. WASM bindings: T-252.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-scheduler/**` | create | new pure crate |
| `Cargo.toml` | modify | member and workspace dependency |
| `justfile` | modify | wasm32 check line, skipped when target missing |
| `crates/kinevo-scheduler/tests/{rank,slots,ceiling,canonical,deps}.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
pub fn rank_key(t: &SchedTask) -> RankKey;                              // derives Ord
pub fn free_slots(day: Range, occupied: &[Range], min_slot_minutes: u32) -> Vec<Range>;
pub fn daily_ceiling(slots: &[Range], daily_capacity_bp: Option<u32>) -> Option<u32>;
pub fn canonical_bytes<T: Serialize>(v: &T) -> Vec<u8>;
```

## Business rules

1. No `f32`/`f64` anywhere; durations and instants are integers.
2. Sorting uses total orders only; no `HashMap` iteration affects output.
3. Unknown JSON fields and a wrong `schema` value are errors.

## Security

- Parsing is bounded: more than 5 000 tasks or 100 000 ranges in one input is rejected with a typed error before processing (SEC-AC-16 groundwork).

## Implementation notes

- Keep the crate `#![forbid(unsafe_code)]` with the workspace lints.
- `Range` is `[start, end)` with a private constructor rejecting `end <= start`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** tasks differing in each key **When** ranked **Then** the order follows the five keys, absent deadlines last, progress descending, id as tie-break | `rank::sch_fr_03_rank_key_order` |
| AC-2 | **Given** overlapping and adjacent busy ranges **When** free slots are computed **Then** ranges merge and gaps under the minimum are dropped | `slots::sch_fr_03_free_slots_merge_and_min` |
| AC-3 | **Given** a busy range ending at 10:00 **When** a slot is computed **Then** it starts at 10:00 (half-open) | `slots::half_open_start_exactly_at_busy_end` |
| AC-4 | **Given** 600 free minutes and 5000 bp **When** the ceiling is computed **Then** 300; with `null` no ceiling; the cap predicate holds at the boundary | `ceiling::sch_fr_03_ceiling_integer_math` |
| AC-5 | **Given** the same input value **When** serialised twice **Then** byte-identical canonical JSON with sorted keys | `canonical::sch_fr_01_canonical_bytes_stable` |
| AC-6 | **Given** unknown fields or a wrong schema **When** parsed **Then** an error | `canonical::unknown_field_and_schema_rejected` |
| AC-7 | **Given** an input over the bounds **When** parsed **Then** a typed bound error | `canonical::sec_ac_16_input_bounds` |
| AC-8 | **Given** the crate manifest **When** its dependency tree is read **Then** none of the banned crates appear | `deps::sch_fr_02_dependency_allow_list` |

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
