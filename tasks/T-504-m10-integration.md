---
id: T-504
title: M10 integration: verify journey J9 and AND-01..11 and report evidence
milestone: M10
kind: integration
status: todo
depends_on: [T-503, T-502]
requirements: [J9, AND-01, AND-02, AND-03, AND-04, AND-05, AND-06, AND-07, AND-08, AND-09, AND-10, AND-11, TST-07]
branch: m10-t504-m10-integration
budget_lines: 240
assignee: ""
---

# T-504: M10 integration: verify journey J9 and AND-01..11 and report evidence

## Goal

The Android deliverable is verified as far as the environment allows, with exact commands and honest labels for everything that needs an emulator, a device or store accounts.

## Context

> **Journey J9** (`standards/user-journeys.md`): install the APK → enter the server URL (or deep link) → sign in → Today → go offline → complete a task → reconnect → synced; sign-out clears secure storage; unsupported server version → update screen. The roadmap requires J9 on an emulator and one physical device; the physical device and Play listing are **maintainer actions** (M-STORE), not blockers for this run.
> **What this task runs**: the simulated-shell Playwright journey (T-502), JVM unit tests, the manifest/dependency checks, the CORS test, the release dry run, and — when `scripts/android-sdk.sh` succeeds and an emulator is available (`emulator -list-avds` non-empty or `ANDROID_SERIAL` set) — the instrumented tests and an emulator J9 run with Maestro-free `adb` steps documented in `android/README.md`. Anything else is recorded as `environment-limited` with the command and the exact error.
> **Rule**: integration tasks add no features. Defects are fixed in the owning task's files only when trivial (and noted), else raised as a blocking question for the architect.

## Scope

**In:**
- `android/README.md` (build, run, test, emulator steps, troubleshooting), `crates/kinevo-app/tests/m10_integration.rs` (server side: instance compatibility values, token transport round trip with `X-Kinevo-Client: android`, refresh reuse detection, CORS), AND-01…11 evidence table in the Report with a status per item (`verified`, `verified-simulated`, `environment-limited`, `manual`), recorded `just check` and `just e2e`.

**Out:**
- New features or spec edits.

## Files

| Path | Action | Notes |
|---|---|---|
| `android/README.md` | create |  |
| `crates/kinevo-app/tests/m10_integration.rs` | create |  |
| `tasks/T-504-m10-integration.md` | modify | Report only |

## Interfaces

```text
just check && just e2e && just android-release-dry-run
```

## Business rules

1. Nothing is reported as passing unless it was run; skipped items name the exact command not run and why.
2. The Report includes counts per command, environment-limited items and the SEC-AC table for AND-01…11 (with the four status labels).
3. A failing gate blocks the milestone and is listed under Questions with the failing test name.

## Security

- Re-run the privacy grep from AGENTS.md before declaring done and record the result.
- Run `cargo deny check` and `pnpm --dir web audit --prod`; record findings.

## Implementation notes

- A claim of 'works on a device' is forbidden unless a device ran it; say `verified-simulated` for the Playwright shell simulation.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `just check` runs **Then** exit 0 | `just check` |
| AC-2 | **Given** the server **When** the Android transport is exercised **Then** login/refresh in body, reuse detection and CORS behave as specified | `m10_integration::and_02_03_server_side_transport` |
| AC-3 | **Given** the simulated shell **When** J9 web steps run **Then** they pass | `e2e::j9_web_part_in_simulated_shell` |
| AC-4 | **Given** the manifest and dependency checks **When** run **Then** AND-08, AND-10, AND-11 pass | `test_android_manifest::and_08_permissions_exact` |
| AC-5 | **Given** an SDK and emulator **When** instrumented tests and the emulator J9 run **Then** they pass, or the Report labels them environment-limited with the error | `just android-instrumented` |
| AC-6 | **Given** the Report **When** read **Then** every AND item has one of the four labels | `m10_integration::report_has_status_for_every_and_item` |

## Tests

```
just check
just e2e
just android-release-dry-run   # environment-limited without SDK
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
