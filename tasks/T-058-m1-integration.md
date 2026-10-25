---
id: T-058
title: M1 integration: verify the foundation end to end and report evidence
milestone: M1
kind: integration
status: todo
depends_on: [T-042, T-057]
requirements: [TST-06, TST-12, TST-19, SEC-AC-01, SEC-AC-02, SEC-AC-45, SEC-AC-47, SEC-AC-48, OPS-01, J0, J4]
branch: m1-t058-m1-integration
budget_lines: 200
assignee: ""
---

# T-058: M1 integration: verify the foundation end to end and report evidence

## Goal

Every M1 task is integrated, the milestone's journeys and gates pass together on a clean checkout, and the evidence is recorded so M2 can start from a verified base.

## Context

> **Milestone M1 scope** (`spec/13-roadmap.md`): workspace and tooling, kernel types (time, ids, errors, secrets, validation, policy, cursors), ports and descriptors, database contexts and isolation, platform tables (audit, jobs, outbox, idempotency), HTTP core (problem+json, auth gates, rate limits, idempotency, CSRF), the identity module, the admin module (modules, policies, secrets, setup, audit, jobs), the CLI, OpenAPI contract, the web foundation (tokens, primitives, i18n, auth, shell, setup, settings, admin) and the container image.
> **TST-06**: for every tenant table a generated isolation test inserts rows for two accounts and asserts account A can neither see nor write B's rows, with RLS enabled and forced. **TST-12**: each abuse case in the M1 range has a test named `sec_ac_<nn>_…`. **TST-19**: fault injection for the multi-step transaction in M1 (setup Finish). **TST-04**: `contracts/openapi.json` has no drift.
> **M1 abuse cases** (`standards/security-by-design.md` §4): SEC-AC-01, 01b, 02, 03–07 (identity), 08–11 (admin), 45 (log scan), 47 (container hygiene), 48 (advisory gate; wired in Phase 5 CI, here proven locally with `cargo deny check advisories`).
> **Journeys**: J0 in full; J4 for the M1 subset (`standards/user-journeys.md`).
> **Rule**: integration tasks add no features. Anything found broken is fixed in the owning task's files only if trivial (and noted), otherwise recorded as a blocking question for the architect.

## Scope

**In:**
- Run, from a clean clone, `just setup`, `just check`, `just e2e`, `docker build .`, `scripts/smoke-compose.sh` and record each result.
- Add `crates/kinevo-app/tests/m1_integration.rs`: boots the app in-process against `TestApp`, drives register/login/refresh/logout, setup Finish (with fault injection at every statement), module switch and policy change, and asserts the resulting audit rows and outbox events together.
- Add the abuse-case coverage table to the Report: each M1 SEC-AC id → test name → pass/not run.
- Update `tasks/index.md` statuses via the normal workflow (not by hand) and list open questions.

**Out:**
- New features, refactors and spec edits (those go through the architect).
- CI workflow files: Phase 5.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-app/tests/m1_integration.rs` | create | cross-module scenario tests |
| `tasks/T-058-m1-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e && docker build . && scripts/smoke-compose.sh
```

## Business rules

1. Nothing is reported as passing unless it was run; every skipped item states the exact command not run and why (WORKFLOW.md §4).
2. The Report includes the output summary (counts) of each command, the list of environment-limited items, and the SEC-AC table.
3. A failing gate blocks the milestone; the Report then lists it under Questions with the failing test name.

## Security

- Run `cargo deny check` and `pnpm --dir web audit --prod` and record findings; a critical advisory blocks the milestone (SEC-AC-48).
- Grep the repository, logs and fixtures for the owner-privacy strings listed in AGENTS.md before declaring done; record the result.

## Implementation notes

- Run the in-process scenario with a `FixedClock` and `SeqIdGen` so failures are reproducible.
- Keep the scenario test under ~150 lines by reusing the test kit helpers.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 with no skipped suites other than documented environment limits | `m1_integration::just_check_green` |
| AC-2 | **Given** two accounts and every tenant table **When** the generated isolation test runs **Then** zero leaks and RLS enabled and forced everywhere | `m1_integration::tst_06_isolation_all_tables` |
| AC-3 | **Given** setup Finish **When** a failure is injected at each statement **Then** no partial state remains | `m1_integration::tst_19_setup_finish_fault_injection` |
| AC-4 | **Given** register, login, refresh, logout, module switch and policy change **When** run in one scenario **Then** audit rows and outbox events match expectations | `m1_integration::cross_module_scenario` |
| AC-5 | **Given** the journeys **When** `just e2e` runs **Then** J0 and the J4 subset pass on all three engines | `m1_integration::journeys_j0_j4` |
| AC-6 | **Given** the abuse-case registry **When** M1 ids are matched to test names **Then** every id has a test or a documented exception | `m1_integration::tst_12_abuse_case_coverage` |
| AC-7 | **Given** the image **When** run **Then** it is non-root with a read-only root file system (SEC-AC-47) | `m1_integration::sec_ac_47_container_hygiene` |
| AC-8 | **Given** the dependency policy **When** `cargo deny check` runs **Then** no critical advisory (SEC-AC-48) | `m1_integration::sec_ac_48_no_critical_advisory` |

## Tests

```
just setup
just check
just e2e
docker build -t kinevo:dev .
scripts/smoke-compose.sh   # environment-limited without Docker
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
