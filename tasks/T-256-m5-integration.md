---
id: T-256
title: M5 integration: verify journey J5 end to end and report evidence
milestone: M5
kind: integration
status: todo
depends_on: [T-255, T-254, T-252]
requirements: [J5, TST-05, TST-06, TST-07, TST-12, TST-19, SEC-AC-25, SEC-AC-26, SEC-AC-27, SEC-AC-28, SEC-AC-29, MOD-07]
branch: m5-t256-m5-integration
budget_lines: 240
assignee: ""
---

# T-256: M5 integration: verify journey J5 end to end and report evidence

## Goal

Offline editing works as a whole on a clean checkout: J5 passes in the browser, the ledger is proven exactly-once, and the module matrix passes with `sync` off.

## Context

> **Journey J5** (`standards/user-journeys.md`): load Today online, go offline (banner, cached Today with "Last updated") → create a task, complete another, edit a title (optimistic, 3 pending) → reload offline (shell loads, queue persists) → reconnect (queue drains, `state.synced`, each op ledgered once) → edit task X offline while edited online elsewhere (`sync.conflict` card, no silent overwrite) → lost response resent with the same `operation_id` (`replay=true`, no duplicate) → offline schedule preview labelled "Preview — not saved", Apply disabled.
> **Gates**: TST-05 native-vs-WASM parity; TST-06 isolation for `offline_operations`; TST-07 module matrix with `sync` off (core journeys J0–J3 pass, and the web sends direct mutations); TST-19 fault injection; SEC-AC-25…29. Browsers: Chromium, Firefox, WebKit for J5 (FE-07).
> **Rule**: integration tasks add no features. Defects are fixed in the owning task's files only when trivial (and noted), else raised as a blocking question for the architect.

## Scope

**In:**
- `web/tests/e2e/journeys/j5.spec.ts` (all seven steps, three browsers), `crates/kinevo-app/tests/m5_integration.rs` (in-process: offline batch with dependencies, conflicts, replay, prune), matrix job run with `sync` off, SEC-AC table 25–29, recorded `just check` and `just e2e`.

**Out:**
- New features or spec edits.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/tests/e2e/journeys/j5.spec.ts` | create |  |
| `crates/kinevo-app/tests/m5_integration.rs` | create |  |
| `tasks/T-256-m5-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e
```

## Business rules

1. Nothing is reported as passing unless it was run; skipped items name the exact command not run and why.
2. The Report includes counts per command, browsers run, environment-limited items and the SEC-AC table for 25–29.
3. A failing gate blocks the milestone and is listed under Questions with the failing test name.

## Security

- Re-run the privacy grep from AGENTS.md before declaring done and record the result.
- Run `cargo deny check` and `pnpm --dir web audit --prod`; record findings.

## Implementation notes

- If a browser engine is not installed, install it with `pnpm exec playwright install --with-deps`; only mark it environment-limited if installation fails, with the error.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 | `just check` |
| AC-2 | **Given** the browser suite **When** `just e2e` runs **Then** J5 passes on the installed engines with every step asserted | `e2e::j5_offline_journey` |
| AC-3 | **Given** the in-process scenario **When** run **Then** ledger rows, replays and conflicts match the journey | `m5_integration::j5_in_process_scenario` |
| AC-4 | **Given** the module matrix **When** `sync` is off **Then** J0–J3 still pass | `m5_integration::tst_07_sync_off_matrix` |
| AC-5 | **Given** the abuse cases **When** run **Then** SEC-AC-25…29 pass and are listed in the Report | `m5_integration::sec_ac_25_to_29_table` |

## Tests

```
just check
just e2e
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
