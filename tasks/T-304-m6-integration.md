---
id: T-304
title: M6 integration: verify journey J6 end to end and report evidence
milestone: M6
kind: integration
status: todo
depends_on: [T-303, T-302]
requirements: [J6, TST-06, TST-07, TST-12, TST-19, SEC-AC-30, SEC-AC-31, SEC-AC-32, SEC-AC-33, SEC-AC-34, MOD-07]
branch: m6-t304-m6-integration
budget_lines: 220
assignee: ""
---

# T-304: M6 integration: verify journey J6 end to end and report evidence

## Goal

AI breakdown works as a whole on a clean checkout with a fake provider, the SSRF and secret protections are proven, and the app is unaffected with AI off.

## Context

> **Journey J6** (`standards/user-journeys.md`): set BYOK (fake provider in CI) → open a goal → `ai.sent.preview` → generate → review → edit one task → accept → milestones and tasks exist; reject path; invalid output (`ai.output_invalid`); limit path (`ai.limit`); private-IP BYOK URL refused.
> **Gates**: TST-06 isolation for the three AI tables; TST-07 module matrix with `ai` off (J0–J5 pass, no AI UI); TST-12 jobs; TST-19 fault injection for accept; SEC-AC-30…34 including the key-leak scan over logs, DB and responses.
> **Rule**: integration tasks add no features. Defects are fixed in the owning task's files only when trivial (and noted), else raised as a blocking question for the architect.

## Scope

**In:**
- `crates/kinevo-app/tests/m6_integration.rs` (in-process with the fake provider: breakdown → edit → accept; invalid output; limits; key scan), matrix run with `ai` off, SEC-AC table 30–34, recorded `just check` and `just e2e`.

**Out:**
- New features or spec edits.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-app/tests/m6_integration.rs` | create |  |
| `tasks/T-304-m6-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e
```

## Business rules

1. Nothing is reported as passing unless it was run; skipped items name the exact command not run and why.
2. The Report includes counts per command, environment-limited items and the SEC-AC table for 30–34.
3. A failing gate blocks the milestone and is listed under Questions with the failing test name.

## Security

- Re-run the privacy grep from AGENTS.md before declaring done and record the result.
- Run `cargo deny check` and `pnpm --dir web audit --prod`; record findings.

## Implementation notes

- No real provider is contacted anywhere in this milestone.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 | `just check` |
| AC-2 | **Given** the browser suite **When** `just e2e` runs **Then** J6 passes | `e2e::j6_ai_breakdown` |
| AC-3 | **Given** the in-process scenario **When** run **Then** proposals, runs and created items match the journey | `m6_integration::j6_in_process_scenario` |
| AC-4 | **Given** `ai` off **When** the matrix runs **Then** J0–J5 pass | `m6_integration::tst_07_ai_off_matrix` |
| AC-5 | **Given** the abuse cases **When** run **Then** SEC-AC-30…34 pass and are listed in the Report | `m6_integration::sec_ac_30_to_34_table` |

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
