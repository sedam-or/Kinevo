---
id: T-151
title: Implement the pure recurrence expansion and the effective-landscape resolver
milestone: M3
kind: feature
status: todo
depends_on: [T-150]
requirements: [LAND-FR-01, LAND-FR-02, LAND-FR-04, CA-01, CA-16, CQ-08]
branch: m3-t151-landscape-recurrence-and-resolver
budget_lines: 400
assignee: ""
---

# T-151: Implement the pure recurrence expansion and the effective-landscape resolver

## Goal

Recurrence expansion and override resolution are two pure, deterministic functions with golden fixtures taken from the legacy tests and reviewed differences.

## Context

> **Time and recurrence rules** (`spec/modules/landscape.md` §3): LAND-R1 a source event has instants plus an IANA zone, and the wall-clock time in that zone recurs. LAND-R2 `rrule = { freq: DAILY|WEEKLY, interval 1–52, by_day? (WEEKLY only), count? 1–1000, until? date or instant, until_date_only }`; `count` and `until` are mutually exclusive; `until` must not precede `start_at`; WEEKLY without `by_day` uses the weekday of `start_at` in the event zone. LAND-R3 expansion works on local dates in the event zone (weeks step from Monday), then converts each local start to an instant. LAND-R4 DST: a nonexistent local time moves forward by the gap length; an ambiguous time takes the earlier instant; duration is the absolute `end_at − start_at`. LAND-R5 `until_date_only=true` includes every occurrence whose local start date ≤ `until`; else start instant ≤ `until`. LAND-R6 a window `[from, to)` includes an occurrence iff `occ.start < to AND occ.end > from`. LAND-R7 an unparseable or unsupported stored recurrence degrades to the single base occurrence plus warning `rrule_degraded` with the event id.
> **Resolution** (§4): for each occurrence `O` with original local start date `d`: (1) among `one_time` overrides with `effective_from == d` choose the greatest `(created_at, id)`: cancel → `cancelled` (provenance `cancelled`), else the occurrence becomes `[override_start_at, override_end_at)` (`excepted`); (2) else among `permanent` overrides with `effective_from ≤ d ≤ effective_to` choose the greatest `(effective_from, created_at, id)`; `delta` = whole calendar days from `shift.effective_from` to `d`; the occurrence becomes `[shift.override_start_at + delta days, shift.override_end_at + delta days)` adding calendar days in the event zone, duration = shift duration (`shifted`); (3) else unchanged (`base`); (4) return only occurrences overlapping the window after shifting, cancelled ones in `cancelled`. Output order: `(start_at, end_at, event_id, original_start_at)`. Precedence labels low→high: `ordinary < recurring < explicit_override < locked_task < hard_landscape`.
> **LAND-FR-01** pure, total, deterministic; golden fixtures come from legacy tests read with `git show legacy-v0:server/tests/Unit/Scheduling/...Resolver...` and `...Recurrence...`; deviations listed in §9 (zone-aware expansion, local-date exception matching, permanent as override kind only). **LAND-FR-02** one-time exceptions need `effective_from == effective_to`; permanent shifts need `effective_to ≥ effective_from`; both need `override_end_at > override_start_at`; permanent shifts apply only to recurring events. **LAND-FR-04** results capped at 5 000 occurrences with warning `occurrence_cap_reached`.
> **Time zones**: use `jiff` (tz database) inside the module's `domain` (the kernel stays zone-free); DST rules above are implemented explicitly and tested on `Europe/Berlin` and `America/New_York` transitions and a UTC+7 zone without DST.
> **Fixtures policy**: where the Rust result differs from a legacy expectation, keep both in the fixture file with a `legacy_differs` note explaining the intended deviation (§9); never silently follow either.

## Scope

**In:**
- `domain/recurrence.rs`: `Rrule` (validated newtype), `expand(event, window) -> Expansion { occurrences, warnings }`.
- `domain/resolver.rs`: `resolve(sources, overrides, from, to) -> Effective { occurrences, cancelled, warnings }` and `Provenance`.
- `domain/validate.rs`: override and rrule validation functions returning registry errors (`rrule_invalid`, `override_requires_recurring`, `override_range_invalid`).
- Golden fixture files under `crates/kinevo-mod-landscape/tests/fixtures/` (JSON, canonical), proptests for idempotence, ordering and window containment.

**Out:**
- Persistence, HTTP and ICS: T-152…T-154.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-landscape/src/domain/{recurrence,resolver,validate,model}.rs` | create | pure code |
| `crates/kinevo-mod-landscape/tests/{recurrence,resolver,golden}.rs` | create | AC-1…AC-9 |
| `crates/kinevo-mod-landscape/tests/fixtures/*.json` | create | golden inputs/outputs |

## Interfaces

```rust
pub fn expand(ev: &SourceEvent, window: TimeRange, cap: usize) -> Expansion;
pub fn resolve(src: &[SourceEvent], ov: &[Override], window: TimeRange, cap: usize) -> Effective;
pub struct Occurrence { pub event_id: EventId, pub original_start: Instant, pub start: Instant, pub end: Instant, pub provenance: Provenance }
```

## Business rules

1. Everything above is normative; each LAND-R rule has at least one table test.
2. `expand` and `resolve` never read the clock or the environment; the zone database is the only external data.
3. Output is sorted by the total order; a property test shuffles inputs and compares.
4. Degradation never drops an event (LAND-R7).

## Security

- Counts and loops are bounded by the cap and by the window before expansion; a 100 000-instance `COUNT` request finishes under 1 s (SEC-AC-16 groundwork).

## Implementation notes

- Represent local dates with `jiff::civil::Date`; convert to instants with explicit disambiguation settings and test both DST cases.
- Dependencies: add `jiff` with the `tz-system` feature disabled and the bundled tz data enabled so results do not depend on the host.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a DAILY rule with interval 2 and count 5 **When** expanded **Then** five occurrences on every second local day | `recurrence::land_r3_daily_interval_count` |
| AC-2 | **Given** WEEKLY `by_day [MO, WE]` **When** expanded across a year boundary **Then** weeks step from Monday | `recurrence::land_r3_weekly_by_day` |
| AC-3 | **Given** a 02:30 local event on a spring-forward date **When** expanded **Then** it moves forward by the gap and keeps its absolute duration; on fall-back it takes the earlier instant | `recurrence::land_r4_dst_gap_and_overlap` |
| AC-4 | **Given** `until_date_only` true vs false **When** expanded **Then** the last day is included vs excluded by instant | `recurrence::land_r5_until_modes` |
| AC-5 | **Given** a window touching an occurrence end exactly **When** queried **Then** it is excluded (half-open) | `recurrence::land_r6_half_open_overlap` |
| AC-6 | **Given** an unsupported stored rrule **When** expanded **Then** one base occurrence and warning `rrule_degraded` | `recurrence::land_r7_degrades_with_warning` |
| AC-7 | **Given** exception, permanent shift and base cases **When** resolved **Then** provenance and times match the rules including precedence by `(created_at, id)` | `resolver::land_fr_01_resolution_table` |
| AC-8 | **Given** the legacy-derived golden fixtures **When** resolved **Then** outputs equal the expected files, with each `legacy_differs` documented | `golden::land_fr_01_golden_fixtures` |
| AC-9 | **Given** shuffled inputs and a 5 000 cap **When** resolved **Then** output is identical and the cap warning appears | `resolver::land_fr_04_cap_and_order_property` |

## Tests

```
cargo nextest run -p kinevo-mod-landscape --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
