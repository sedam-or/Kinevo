---
id: T-057
title: Add the Playwright suite for journeys J0 and J4 with axe, storage and network checks
milestone: M1
kind: feature
status: todo
depends_on: [T-051, T-052, T-053, T-054, T-055, T-056]
requirements: [SET-01, FE-02, FE-13, FE-16, TST-11, TST-13, SEC-AC-01, DS-A11Y-15, DST-07, J0, J4]
branch: m1-t057-e2e-journeys-j0-j4
budget_lines: 380
assignee: ""
---

# T-057: Add the Playwright suite for journeys J0 and J4 with axe, storage and network checks

## Goal

One command boots the real stack on a clean database and runs the journeys through the browser on three engines, with accessibility, storage, network allow-list and log-hygiene gates.

## Context

> **Journey J0 — first run** (`spec/standards/user-journeys.md`): (1) start the stack, open the URL → the token gate shows (`setup.token.invalid` on a wrong token); (2) enter the token from the log → step 1 with the template list and the browser language preselected; (3) Next (default `personal`) → modules step (core locked, `ai`/`canvas`/`billing` off) → Next → rules step with defaults and the first-account form; (4) email, password at the policy minimum, name, timezone defaulted → Finish → account created, lands on Today with `today.empty.no_tasks`, `GET /instance` reports `setup_completed=true`; (5) reload → still signed in and no token in storage; (6) open `/setup` → `setup_already_completed` page. Variants: wrong token ×3 → locked; Finish with the database down → `setup.finish.error`, retry succeeds, one account only.
> **Journey J4 (M1 subset)** — administration: switch `ai` on without a provider (the module switches and the audit entry appear), switch `canvas` off then on, try to switch off a core module (`module_core_locked`; `admin` is the core example available in M1), change an instance policy such as `audit.retention_days` (`admin.policy.saved`), set an out-of-range value (`admin.policy.invalid`), view the audit entries for these actions, create an invite and a reset link. Steps that need later modules (`tasks`, `scheduler.day_start`) join J4 in their milestones.
> **FE-16**: Playwright on Chromium, Firefox and WebKit; axe with tags `wcag2a, wcag2aa, wcag21aa, wcag22aa` on every route and every catalogued state (TST-11, DS-A11Y-15): zero serious/critical violations. **FE-13**: network allow-list test: requests go only to the instance origin. **SEC-AC-01b / FE-02**: no token in `localStorage`, `sessionStorage` or IndexedDB after login and reload. **TST-13 / SEC-AC-45**: after the run, logs and audit `details` are scanned for fixture markers (secrets, emails, titles); any hit fails. **DST-07**: the wizard is available in English and Indonesian from the first screen.
> **D-42**: the `e2e-fake-clock` feature fixes time for lockout tests; the E2E binary is built with it, release images never.
> **Fixtures**: addresses use `example.test`; passwords and names are synthetic (PRV-08).

## Scope

**In:**
- `web/tests/e2e/` suite: `fixtures/stack.ts` (starts Postgres through compose, bootstraps roles, runs `kinevo serve` built with `--features e2e-fake-clock`, captures the setup URL from its log), `journeys/j0.spec.ts`, `journeys/j4.spec.ts`, `gates/{a11y,storage,network,logs}.spec.ts`, Playwright config with three projects.
- `justfile` recipe `e2e` (builds web and binary, runs the suite) and a documented `e2e-fast` (Chromium only).
- The log-hygiene scanner reading the captured server log and the audit rows through the admin API.

**Out:**
- CI job wiring (`e2e`, `a11y`, `quickstart`): Phase 5 workflows.
- Journeys J1–J3, J5–J10: later milestones.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/tests/e2e/fixtures/stack.ts` | create | stack control |
| `web/tests/e2e/journeys/j0.spec.ts, j4.spec.ts` | create | journeys |
| `web/tests/e2e/gates/a11y.spec.ts, storage.spec.ts, network.spec.ts, logs.spec.ts` | create | gates |
| `web/playwright.config.ts` | create | 3 projects, traces on failure |
| `justfile` | modify | `e2e`, `e2e-fast` |

## Interfaces

```text
just e2e         # build + run all projects
just e2e-fast    # chromium only
```

## Business rules

1. Every journey step has an assertion on visible text via message keys, not hard-coded strings.
2. The a11y gate visits every M1 route and the catalogued states reachable in M1 (token invalid, finish error, lockout, session expired, forbidden, module disabled, offline banner stub) and fails on serious/critical violations.
3. The suite runs against a fresh database per run.
4. Traces, screenshots and videos are kept only for failures and contain no real data.

## Security

- The storage gate fails the build if any storage area contains a string matching the JWT shape or the refresh token.
- The log gate fails on any fixture marker found in logs or audit `details`.
- The network gate asserts zero third-party hosts and no `eval`-style CSP violations (listens to `securitypolicyviolation`).

## Implementation notes

- Use a unique marker string inside the fixture password and display name so the log gate can search for them.
- Take the Finish-with-database-down variant by pausing the Postgres container for one request (compose `pause`/`unpause`).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a fresh stack **When** J0 runs end to end **Then** every step passes on all three engines | `e2e::j0_first_run` |
| AC-2 | **Given** three wrong tokens **When** submitted **Then** the lockout state shows and no account exists | `e2e::j0_wrong_token_lockout` |
| AC-3 | **Given** Postgres paused during Finish **When** the user retries **Then** exactly one account exists | `e2e::j0_finish_retry_one_account` |
| AC-4 | **Given** J4 steps **When** run as an admin **Then** switch, conflict, policy and audit steps pass and an owner gets `forbidden` on the same URLs | `e2e::j4_admin_subset` |
| AC-5 | **Given** every M1 route and state **When** scanned by axe **Then** zero serious/critical violations | `e2e::tst_11_axe_all_m1_routes_and_states` |
| AC-6 | **Given** login and reload **When** all storage is dumped **Then** no token appears | `e2e::sec_ac_01b_storage_has_no_token` |
| AC-7 | **Given** the whole run **When** requests are recorded **Then** only the instance origin is contacted | `e2e::fe_13_network_allow_list` |
| AC-8 | **Given** the captured logs and audit details **When** scanned for markers **Then** none are found | `e2e::sec_ac_45_log_hygiene` |

## Tests

```
just e2e   # environment-limited without Docker and browsers; record the exact command not run
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
