---
id: T-164
title: M3 integration: verify journey J3 end to end and report evidence
milestone: M3
kind: integration
status: todo
depends_on: [T-163, T-161, T-154, T-156]
requirements: [J3, TST-06, TST-07, TST-12, TST-19, SEC-AC-16, SEC-AC-17, SEC-AC-18, SEC-AC-19, SEC-AC-20, MOD-07]
branch: m3-t164-m3-integration
budget_lines: 240
assignee: ""
---

# T-164: M3 integration: verify journey J3 end to end and report evidence

## Goal

Landscape and scheduling work as a whole on a clean checkout: J3 passes in the browser, isolation covers every new table, and the abuse cases are proven.

## Context

> **Journey J3 — reality and schedule** (`standards/user-journeys.md`): import a sample `.ics` (weekly meetings, one all-day, one monthly) → preview with `{new}` counts, `rrule_unsupported` on the monthly, all-day unchecked → commit; cancel next Tuesday's meeting → effective view shows `cancelled` → generate weekly draft with "Why here?" reasons, nothing applied → apply (version bumps, tasks `scheduled`, `review_state=clean`) → shift a recurring meeting +1 day permanently (review banner, assignments unchanged) → Sync Now (moves only conflicting tasks; apply; banner clears) → apply a stale draft from another tab (`schedule.draft.stale`) → export landscape `.ics` and re-import (identical landscape).
> **Gates**: TST-06 isolation for the three landscape and four scheduler tables; TST-07 the module matrix (modules that can be switched off are off in a matrix job and the core journeys still pass; `landscape` and `scheduler` are core); TST-12 jobs/retention; TST-19 fault injection for ICS commit and apply; abuse cases SEC-AC-16 (ICS bomb), SEC-AC-17 (ICS injection), SEC-AC-18 (forged draft), SEC-AC-19 (input isolation), SEC-AC-20 (10 concurrent sync).
> **Rule**: integration tasks add no features. Defects found are fixed in the owning task's files only when trivial (and noted), else raised as a blocking question for the architect.

## Scope

**In:**
- `web/tests/e2e/journeys/j3.spec.ts` (composes the steps from T-162/T-163 in one run with a fake clock), `crates/kinevo-app/tests/m3_integration.rs` (in-process: import → landscape → tasks → draft → apply → change landscape → needs review → sync → apply; asserts rows, events and versions), a benchmark target `scheduler_bench` (500 tasks over 28 days, result recorded, not a gate).
- Run and record `just check`, `just e2e`, the isolation suite, the wasm32 build of `kinevo-scheduler`, and the SEC-AC table for 16–20.

**Out:**
- New features or spec edits.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/tests/e2e/journeys/j3.spec.ts` | create |  |
| `crates/kinevo-app/tests/m3_integration.rs` | create |  |
| `crates/kinevo-scheduler/benches/scheduler_bench.rs` | create | bench, not gated |
| `tasks/T-164-m3-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e
```

## Business rules

1. Nothing is reported as passing unless it was run; skipped items name the exact command not run and why.
2. The Report includes counts per command, environment-limited items, the benchmark numbers and the SEC-AC table for 16–20.
3. A failing gate blocks the milestone and is listed under Questions with the failing test name.

## Security

- Re-run the privacy grep from AGENTS.md before declaring done and record the result.
- Run `cargo deny check` and `pnpm --dir web audit --prod`; record findings.

## Implementation notes

- The integration task is the last of the stack for M3; the run stops here for this milestone unless the maintainer authorised the next one (the Codex run prompt does).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 | `just check` |
| AC-2 | **Given** the browser suite **When** `just e2e` runs **Then** J3 passes with every step asserted | `e2e::j3_reality_and_schedule` |
| AC-3 | **Given** the in-process scenario **When** run **Then** rows, events, versions and review states match the journey | `m3_integration::j3_in_process_scenario` |
| AC-4 | **Given** the abuse cases **When** run **Then** SEC-AC-16…20 pass and are listed in the Report | `m3_integration::sec_ac_16_to_20_table` |
| AC-5 | **Given** `kinevo-scheduler` **When** built for `wasm32-unknown-unknown` **Then** it builds, or the Report says `environment-limited` | `scheduler_bench::wasm_build_recorded` |

## Tests

```
just check
just e2e
cargo bench -p kinevo-scheduler --bench scheduler_bench   # recorded, not a gate
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
