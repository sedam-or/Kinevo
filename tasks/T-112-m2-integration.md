---
id: T-112
title: M2 integration: verify journey J2 end to end and report evidence
milestone: M2
kind: integration
status: todo
depends_on: [T-111, T-108, T-104]
requirements: [J2, TST-06, TST-07, TST-12, TST-19, SEC-AC-12, SEC-AC-13, SEC-AC-14, SEC-AC-15, MOD-07]
branch: m2-t112-m2-integration
budget_lines: 220
assignee: ""
---

# T-112: M2 integration: verify journey J2 end to end and report evidence

## Goal

The planning core works as a whole on a clean checkout: J2 passes in the browser, isolation covers every new table, and the module matrix still passes with toggleable modules off.

## Context

> **Journey J2 — plan a project** (`standards/user-journeys.md`): create workspace "Thesis" → create a goal with a target date → add 3 milestones (reorder with Move up/down) → add tasks with estimates and one due date → set one anchor task → archive and unarchive the task's workspace → delete a goal with tasks (choose keep tasks). Each step shows its toast or banner and the data is consistent after reload.
> **Gates**: TST-06 isolation for `workspaces`, `goals`, `milestones`, `tasks`, `subtasks`; TST-07 the core journeys still pass with every toggleable module off (`sync`, `ai`, `knowledge`, `canvas`, `billing` are not built yet, so the matrix job contains only the core run); TST-12 abuse cases SEC-AC-12…15; TST-19 fault injection is not required for M2 (no multi-step transaction beyond goal delete with detach, covered by a fault test here for `goal delete + detach`).
> **Rule**: integration tasks add no features. Defects found are fixed in the owning task's files only when trivial (and noted), else raised as a blocking question for the architect.

## Scope

**In:**
- Add `web/tests/e2e/journeys/j2.spec.ts` and extend the a11y gate with the Goals and Tasks routes.
- Add `crates/kinevo-app/tests/m2_integration.rs`: in-process scenario (workspace → goal → milestones → tasks → status changes → archive → delete goal with detach) asserting rows, events and `allowed_transitions`; a fault-injection test for goal delete with detach.
- Run and record `just check`, `just e2e`, the isolation suite and the abuse-case coverage table for SEC-AC-12…15.

**Out:**
- New features or spec edits.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/tests/e2e/journeys/j2.spec.ts` | create |  |
| `web/tests/e2e/gates/a11y.spec.ts` | modify | add routes |
| `crates/kinevo-app/tests/m2_integration.rs` | create |  |
| `tasks/T-112-m2-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e
```

## Business rules

1. Nothing is reported as passing unless it was run; skipped items name the exact command not run and why.
2. The Report includes counts per command, environment-limited items and the SEC-AC table for 12–15.
3. A failing gate blocks the milestone and is listed under Questions with the failing test name.

## Security

- Re-run the privacy grep from AGENTS.md before declaring done and record the result.
- Run `cargo deny check` and `pnpm --dir web audit --prod`; record findings.

## Implementation notes

- Use `FixedClock` and `SeqIdGen` in the in-process scenario for reproducibility.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 | `m2_integration::just_check_green` |
| AC-2 | **Given** two accounts **When** the generated isolation test runs **Then** zero leaks for all five M2 tables | `m2_integration::tst_06_isolation_m2_tables` |
| AC-3 | **Given** goal deletion with detach **When** a failure is injected at each statement **Then** goal and tasks are unchanged | `m2_integration::tst_19_goal_delete_detach_fault_injection` |
| AC-4 | **Given** the in-process scenario **When** run **Then** events and `allowed_transitions` match the specs | `m2_integration::planning_core_scenario` |
| AC-5 | **Given** the browser **When** J2 runs **Then** every step passes on three engines | `m2_integration::j2_plan_a_project` |
| AC-6 | **Given** the abuse-case registry **When** matched **Then** SEC-AC-12…15 each have a test | `m2_integration::tst_12_abuse_cases_12_to_15` |

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
