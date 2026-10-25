---
id: T-403
title: M8 integration: verify journey J7b end to end and report evidence
milestone: M8
kind: integration
status: todo
depends_on: [T-402, T-401]
requirements: [J7b, TST-06, TST-07, TST-12, SEC-AC-38, SEC-AC-39, SEC-AC-40, MOD-07]
branch: m8-t403-m8-integration
budget_lines: 220
assignee: ""
---

# T-403: M8 integration: verify journey J7b end to end and report evidence

## Goal

Canvas works as a whole on a clean checkout including uploads, links, export and offline read-only, and the app is unaffected with `canvas` off.

## Context

> **Journey J7b** (`standards/user-journeys.md`): draw, add a PNG, reload, link to a goal, rename; offline read-only; SVG upload refused.
> **Gates**: TST-06 isolation for `canvases`, `canvas_files`, `canvas_links`; TST-07 module matrix with `canvas` off (J0–J7a pass; note links to canvases degrade); TST-12 jobs; SEC-AC-38 (SVG as PNG), SEC-AC-39 (20 MB scene), SEC-AC-40 (foreign file key); S2 canary scan; the export contains scenes and files.
> **Rule**: integration tasks add no features. Defects are fixed in the owning task's files only when trivial (and noted), else raised as a blocking question for the architect.

## Scope

**In:**
- `crates/kinevo-app/tests/m8_integration.rs` (in-process: board CRUD, upload, links, reactions, export, canary), matrix run with `canvas` off, SEC-AC table 38–40, recorded `just check` and `just e2e`.

**Out:**
- New features or spec edits.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-app/tests/m8_integration.rs` | create |  |
| `tasks/T-403-m8-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e
```

## Business rules

1. Nothing is reported as passing unless it was run; skipped items name the exact command not run and why.
2. The Report includes counts per command, environment-limited items and the SEC-AC table for 38–40.
3. A failing gate blocks the milestone and is listed under Questions with the failing test name.

## Security

- Re-run the privacy grep from AGENTS.md before declaring done and record the result.
- Run `cargo deny check` and `pnpm --dir web audit --prod`; record findings.

## Implementation notes

- The 20 MB body in SEC-AC-39 is streamed from a generator; do not allocate it in memory.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 | `just check` |
| AC-2 | **Given** the browser suite **When** `just e2e` runs **Then** J7b passes | `e2e::j7b_canvas_journey` |
| AC-3 | **Given** the in-process scenario **When** run **Then** boards, files, links and export match | `m8_integration::j7b_in_process_scenario` |
| AC-4 | **Given** `canvas` off **When** the matrix runs **Then** J0–J7a pass | `m8_integration::tst_07_canvas_off_matrix` |
| AC-5 | **Given** the abuse cases **When** run **Then** SEC-AC-38…40 pass and are listed in the Report | `m8_integration::sec_ac_38_to_40_table` |

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
