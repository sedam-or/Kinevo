---
id: T-354
title: M7 integration: verify journey J7a end to end and report evidence
milestone: M7
kind: integration
status: todo
depends_on: [T-353, T-352]
requirements: [J7a, TST-06, TST-07, TST-12, TST-19, SEC-AC-35, SEC-AC-36, SEC-AC-37, MOD-07]
branch: m7-t354-m7-integration
budget_lines: 220
assignee: ""
---

# T-354: M7 integration: verify journey J7a end to end and report evidence

## Goal

Notes work as a whole on a clean checkout, including offline edits, links, search and export, and the app is unaffected with `knowledge` off.

## Context

> **Journey J7a** (`standards/user-journeys.md`): write with headings, lists and links → link to a goal and a task → search Indonesian and English terms → edit in two tabs → `note.conflict` → keep mine as a new note. Offline: create and edit a note offline (J5-style) and reconcile.
> **Gates**: TST-06 isolation for `notes` and `note_links`; TST-07 module matrix with `knowledge` off (J0–J6 pass, no notes UI, link kinds degrade); TST-12 jobs; SEC-AC-35 (FTS injection), SEC-AC-36 (stored XSS), SEC-AC-37 (foreign note); the S2 scan test over logs for a note-text canary.
> **Rule**: integration tasks add no features. Defects are fixed in the owning task's files only when trivial (and noted), else raised as a blocking question for the architect.

## Scope

**In:**
- `crates/kinevo-app/tests/m7_integration.rs` (in-process: note CRUD, search, links, delete reaction, offline note ops, export, canary scan), matrix run with `knowledge` off, SEC-AC table 35–37, recorded `just check` and `just e2e`.

**Out:**
- New features or spec edits.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-app/tests/m7_integration.rs` | create |  |
| `tasks/T-354-m7-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e
```

## Business rules

1. Nothing is reported as passing unless it was run; skipped items name the exact command not run and why.
2. The Report includes counts per command, environment-limited items and the SEC-AC table for 35–37.
3. A failing gate blocks the milestone and is listed under Questions with the failing test name.

## Security

- Re-run the privacy grep from AGENTS.md before declaring done and record the result.
- Run `cargo deny check` and `pnpm --dir web audit --prod`; record findings.

## Implementation notes

- The canary string must appear in no log line, audit row or error body.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 | `just check` |
| AC-2 | **Given** the browser suite **When** `just e2e` runs **Then** J7a passes | `e2e::j7a_notes_journey` |
| AC-3 | **Given** the in-process scenario **When** run **Then** notes, links, reactions, offline ops and export match | `m7_integration::j7a_in_process_scenario` |
| AC-4 | **Given** `knowledge` off **When** the matrix runs **Then** J0–J6 pass | `m7_integration::tst_07_knowledge_off_matrix` |
| AC-5 | **Given** a note-text canary **When** logs, audit and errors are scanned **Then** it never appears | `m7_integration::knw_fr_09_canary_scan` |
| AC-6 | **Given** the abuse cases **When** run **Then** SEC-AC-35…37 pass and are listed in the Report | `m7_integration::sec_ac_35_to_37_table` |

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
