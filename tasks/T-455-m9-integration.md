---
id: T-455
title: M9 integration: verify journey J8 end to end and report evidence
milestone: M9
kind: integration
status: todo
depends_on: [T-454, T-453, T-452]
requirements: [J8, TST-06, TST-07, TST-12, TST-19, SEC-AC-41, SEC-AC-42, SEC-AC-43, SEC-AC-44, MOD-07]
branch: m9-t455-m9-integration
budget_lines: 240
assignee: ""
---

# T-455: M9 integration: verify journey J8 end to end and report evidence

## Goal

Cloud billing works as a whole with the fake gateway, self-host instances are provably unlimited, and nothing depends on an undecided owner value.

## Context

> **Journey J8** (`standards/user-journeys.md`): checkout (fake gateway) → signed webhook → `active` → entitlements applied (creation limits, AI allowance) → cancel at period end → period end → `canceled` → free limits; a forged and a replayed webhook change nothing.
> **Gates**: TST-06 isolation for `subscriptions` and `invoices`; TST-07 module matrix with `billing` off (the module is off by default, so J0–J7b pass and `EntitlementPort` answers `Unlimited` — asserted); TST-12 jobs; TST-19 fault injection for webhook processing; SEC-AC-41…44; log scan: no gateway key or webhook secret in logs.
> **Owner-blocked items (non-blocking for this run)**: OWNER-01/02/04/05/06 are not decided. The run uses test fixture plans in tests only. The Report lists, under *Maintainer actions*, that enabling billing on a real instance requires OWNER-01 values and a real adapter (OWNER-04), which are separate future tasks.
> **Rule**: integration tasks add no features. Defects are fixed in the owning task's files only when trivial (and noted), else raised as a blocking question for the architect.

## Scope

**In:**
- `crates/kinevo-app/tests/m9_integration.rs` (in-process J8 with fake gateway, entitlements, grace, reconcile, deletion hook, secret scan), matrix run with `billing` off, SEC-AC table 41–44, recorded `just check`, `just e2e`, `just spec`.

**Out:**
- New features or spec edits.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-app/tests/m9_integration.rs` | create |  |
| `tasks/T-455-m9-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e
```

## Business rules

1. Nothing is reported as passing unless it was run; skipped items name the exact command not run and why.
2. The Report includes counts per command, environment-limited items and the SEC-AC table for 41–44.
3. A failing gate blocks the milestone and is listed under Questions with the failing test name.

## Security

- Re-run the privacy grep from AGENTS.md before declaring done and record the result.
- Run `cargo deny check` and `pnpm --dir web audit --prod`; record findings.

## Implementation notes

- No real payment provider is contacted anywhere in this milestone.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 | `just check` |
| AC-2 | **Given** the browser suite **When** `just e2e` runs **Then** J8 passes | `e2e::j8_cloud_billing` |
| AC-3 | **Given** the in-process scenario **When** run **Then** states, invoices, entitlements and drift handling match the journey | `m9_integration::j8_in_process_scenario` |
| AC-4 | **Given** `billing` off **When** the matrix runs **Then** J0–J7b pass and entitlements are unlimited | `m9_integration::tst_07_billing_off_unlimited` |
| AC-5 | **Given** logs and audit **When** scanned **Then** no gateway key or webhook secret appears | `m9_integration::bil_fr_07_secret_scan` |
| AC-6 | **Given** the abuse cases **When** run **Then** SEC-AC-41…44 pass and are listed in the Report | `m9_integration::sec_ac_41_to_44_table` |

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
