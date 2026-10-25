# Standard — User journeys (JRN)

One script per persona journey. Each journey is (a) the acceptance narrative for its milestone, (b) the Playwright E2E test (`web/tests/e2e/jNN-*.spec.ts`), (c) the manual walkthrough for usability trials. Steps are numbered `S`; each step lists the **expected observable result** and the states touched (`standards/ux-states.md`). A journey passes only if every step's result is observed. Personas: **Sari** (self-hoster/admin), **Maya** (student), **Dion** (professional) — all fictional.

Conventions: fixture emails `…@example.test`; clock injected (E2E uses a fake-clock endpoint enabled only in the test profile); viewport desktop 1280×800 and mobile 390×844 for J1 and J2; browsers Chromium, Firefox, WebKit.

## J0 — First run (Sari) — M1

| S | Action | Expected |
|---|---|---|
| 1 | Start the stack from the quick-start; open the URL | wizard token gate shown (`setup.token.invalid` on a wrong token) |
| 2 | Enter the token from the log | Step 1: template list, language preselected from the browser |
| 3 | Next (default `personal`) → Step 2 modules (core locked, `ai`/`canvas`/`billing` off) → Next | Step 3 shows defaults and the first-account form |
| 4 | Enter email, password (policy min), name, timezone defaulted; Finish | account created; lands on Today empty state `today.empty.no_tasks`; `GET /instance` → `setup_completed=true` |
| 5 | Reload the page | still signed in (refresh restores) ; no token in storage (SEC-AC-01b) |
| 6 | Open `/setup` | `setup_already_completed` page |

Failure variants: wrong token ×3 → locked; Finish with DB down → `setup.finish.error`, retry succeeds, one account only.

## J1 — The first-love loop (Maya, Dion) — M4

| S | Action | Expected |
|---|---|---|
| 1 | Sign in | Today loads < 2 s on the test profile `[ASSUMED]`; `now` and `next` cards, timeline with a landscape item |
| 2 | Press **Start** on the Now task | timer running; task `in_progress`; `session.running` card |
| 3 | Reload mid-session | the same timer continues with the correct elapsed time |
| 4 | **Complete** → "Done" | `session.completed` toast with Undo; counts `1 of N`; Progress shows the completion; the Next task becomes Now |
| 5 | Undo | task reopened (`backlog` or `scheduled`); counts revert; progress net 0 that day |
| 6 | Start a second task while one is running (from another tab) | `session.already_running` |
| 7 | Keyboard only: `g t`, Tab to Start, Enter | works; focus visible; screen reader announces state changes |

## J2 — Plan a project (Maya) — M2

Create workspace "Thesis" → create goal with a target date → add 3 milestones (reorder with Move up/down) → add tasks with estimates and one due date → set one anchor task → archive and unarchive a task's workspace → delete a goal with tasks (choose keep tasks). Expected: each step's toast/banner; task lists filter by workspace; `goal.has_tasks` modal behaviour; version-conflict card when the same task is edited in two tabs.

## J3 — Reality and schedule (Dion) — M3

| S | Action | Expected |
|---|---|---|
| 1 | Import a sample `.ics` (weekly meetings, one all-day, one monthly) | preview with `{new}` counts, `rrule_unsupported` warning on the monthly, all-day unchecked |
| 2 | Commit; add a one-time exception cancelling next Tuesday's meeting | effective view shows `cancelled` |
| 3 | Generate a weekly draft | draft panel lists placements with "Why here?" reasons; nothing applied yet |
| 4 | Apply | schedule version bumps; tasks `scheduled`; Today shows them; `review_state=clean` |
| 5 | Shift a recurring meeting permanently (+1 day) | `schedule.review.needed` banner appears; assignments unchanged |
| 6 | Sync Now | proposal with moves (only conflicting tasks); apply; banner clears |
| 7 | Apply a stale draft from another tab | `schedule.draft.stale` |
| 8 | Export landscape `.ics` and re-import | identical landscape (round trip) |

## J4 — Administration (Sari) — M1/M5

Switch `ai` on without a provider (`ai.not_configured.admin` appears for users), switch `canvas` off then on (data retained), try to switch off `tasks` (`module_core_locked`), change `scheduler.day_start` (applies from "now", `admin.policy.saved`), set an out-of-range value (`admin.policy.invalid`), view audit entries for each action, view job summary.

## J5 — Offline (Dion, on a train) — M5

| S | Action | Expected |
|---|---|---|
| 1 | Load Today online; go offline (DevTools / airplane) | `state.offline.banner`; Today still renders from cache with "Last updated" |
| 2 | Create a task; complete another; edit a title | optimistic UI; `state.sync.pending` shows 3 |
| 3 | Reload the page while offline | app shell loads; queue persists (3) |
| 4 | Reconnect | queue drains; `state.synced`; server state matches; each op ledgered once |
| 5 | Edit task X offline while the same task was edited online elsewhere | on reconnect: `sync.conflict` card with Keep current / Reapply mine; no silent overwrite |
| 6 | Simulate a lost response (server applied, client didn't receive) | resend with the same `operation_id` → `replay=true`; no duplicate |
| 7 | Offline schedule preview | labelled "Preview — not saved"; Apply disabled with reason |

## J6 — AI breakdown (Maya) — M6

Set BYOK provider (fake provider in CI) → open a goal → see `ai.sent.preview` → generate → review → edit one task → accept → milestones and tasks exist; reject path; invalid output path (`ai.output_invalid`); limit path (`ai.limit`); `private IP` BYOK URL refused.

## J7a — Notes (Maya) — M7 / J7b — Canvas (Maya) — M8

Notes: write with headings/lists/links; link to a goal and a task; search "…" (Indonesian and English terms); edit in two tabs → `note.conflict` → keep mine as new note. Canvas: draw, add a PNG, reload, link to a goal, rename; offline read-only; SVG upload refused.

## J7 — Data rights (Maya) — M2+

Export → download ZIP → verify contents (no secrets) → delete account (password + phrase) → sign-in fails; database has no rows for the account; last-admin guard for Sari.

## J8 — Cloud billing (Dion) — M9

Select plan → checkout (fake gateway) → webhook → entitlements change → cancel at period end → webhook replay ignored → past-due banner → downgrade keeps data (`billing.over_limit`).

## J9 — Android (Dion) — M10

Install APK → enter server URL (or deep link) → sign in → Today → go offline → complete a task → reconnect → synced; sign-out clears secure storage; unsupported server version → update screen.

## Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| JRN-01 | Each journey exists as an automated test (except J9 steps marked manual) and every step's expected result is asserted. | CI e2e |
| JRN-02 | Journeys are the only E2E tests allowed to depend on multiple modules; per-module behaviour is tested below E2E. | Review |
| JRN-03 | Each journey runs with the module matrix (TST-07) where its modules are on. | CI |
| JRN-04 | Usability trials (V2, V3) use these scripts without the expected-results column. | Trial protocol |
