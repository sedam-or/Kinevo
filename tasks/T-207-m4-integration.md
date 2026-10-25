---
id: T-207
title: M4 integration: verify journey J1 end to end and report evidence
milestone: M4
kind: integration
status: todo
depends_on: [T-206, T-203, T-205]
requirements: [J1, TST-06, TST-07, TST-12, TST-19, SEC-AC-21, SEC-AC-22, SEC-AC-23, SEC-AC-24, MOD-07]
branch: m4-t207-m4-integration
budget_lines: 220
assignee: ""
---

# T-207: M4 integration: verify journey J1 end to end and report evidence

## Goal

The first-love loop works as a whole on a clean checkout: J1 passes in the browser, isolation covers the new tables, and the abuse cases are proven.

## Context

> **Journey J1** (`standards/user-journeys.md`): sign in → Today loads with Now/Next and a landscape item → Start → timer running → reload keeps elapsed time → Complete "Done" → toast with Undo, counts `1 of N`, Progress shows the completion, Next becomes Now → Undo reopens, counts revert, progress net 0 → a second start from another tab → `session.already_running` → keyboard-only `g t`, Tab, Enter works with announcements.
> **Gates**: TST-06 isolation for `focus_sessions` and `progress_events`; TST-07 module matrix; TST-12 jobs/retention unaffected; TST-19 fault injection for start and complete; SEC-AC-21…24.
> **Rule**: integration tasks add no features. Defects found are fixed in the owning task's files only when trivial (and noted), else raised as a blocking question for the architect.

## Scope

**In:**
- `web/tests/e2e/journeys/j1.spec.ts` extended to the full seven steps with a fake clock, `crates/kinevo-app/tests/m4_integration.rs` (in-process: assignments → start → complete → progress events → summary; undo nets zero; stale session path), the SEC-AC table for 21–24, recorded `just check` and `just e2e`.

**Out:**
- New features or spec edits.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/tests/e2e/journeys/j1.spec.ts` | modify | full seven steps |
| `crates/kinevo-app/tests/m4_integration.rs` | create |  |
| `tasks/T-207-m4-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e
```

## Business rules

1. Nothing is reported as passing unless it was run; skipped items name the exact command not run and why.
2. The Report includes counts per command, environment-limited items and the SEC-AC table for 21–24.
3. A failing gate blocks the milestone and is listed under Questions with the failing test name.

## Security

- Re-run the privacy grep from AGENTS.md before declaring done and record the result.
- Run `cargo deny check` and `pnpm --dir web audit --prod`; record findings.

## Implementation notes

- Journey J1's 2-second load target is `[ASSUMED]`: record the measured time, do not fail on it.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 | `just check` |
| AC-2 | **Given** the browser suite **When** `just e2e` runs **Then** J1 passes with every step asserted | `e2e::j1_first_love_loop_full` |
| AC-3 | **Given** the in-process scenario **When** run **Then** rows, events and counts match the journey including undo net zero | `m4_integration::j1_in_process_scenario` |
| AC-4 | **Given** the abuse cases **When** run **Then** SEC-AC-21…24 pass and are listed in the Report | `m4_integration::sec_ac_21_to_24_table` |

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
