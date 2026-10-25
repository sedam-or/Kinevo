---
id: T-203
title: Build the Today screen, the focus timer and keyboard flow
milestone: M4
kind: feature
status: todo
depends_on: [T-202, T-163]
requirements: [EXE-FR-01, EXE-FR-07, EXE-FR-09, EXE-FR-10, FE-04, FE-11, FE-14, UXS-02, DS-A11Y-13, J1]
branch: m4-t203-web-today-and-focus
budget_lines: 420
assignee: ""
---

# T-203: Build the Today screen, the focus timer and keyboard flow

## Goal

Opening the app shows what to do now; starting, finishing, undoing and abandoning a task works with mouse and keyboard, survives reloads, and never lies about elapsed time.

## Context

> **Route** (`spec/08-frontend.md` §3): `/today` replaces the M1 placeholder (T-050). Cards: **Now** (title, goal, progress, Start / Complete / Skip actions), **Next**, day timeline (tasks and landscape blocks), counts "`completed` of `total`", running-session card with timer, banners for `schedule.review_needed` (link to Schedule) and the pending weekly draft (D-51), nudge when `unscheduled_open_tasks > 0`.
> **Behaviour**: re-fetch `GET /today` on focus, on reconnect and every 60 s while visible; local one-second tick only re-renders (EXE-FR-01). **EXE-FR-10**: the timer shows `now_corrected − started_at` where the skew offset is `server_now` of the last response minus the local clock at receipt; it survives reload and device sleep. Complete dialog: outcome Done / Partial, optional note (≤ 2 000), calls `complete` with `base_version`; the toast `session.completed` has **Undo**, which reopens the task through `POST /tasks/{id}/status` using the `allowed_transitions` returned by the server. A stale session (`stale=true`) shows `session.stale` with Complete using the server's rule and Abandon. `session.conflict` appears on 409 `session_already_running` with an Open it action. Skip uses the task status API (`missed` is not user-requestable; use only server-provided actions).
> **Keyboard (FE-11)**: `g t` goes to Today; Tab reaches Start; Enter starts; focus is visible; state changes are announced in a polite live region. In-app only notifications (EXE-FR-09): a banner when `now` changes while the app is open.
> **States** (`standards/ux-states.md`): `today.loading`, `today.empty.no_tasks` (create first task), `today.empty.nothing_scheduled` (go to Schedule), `today.now`, `today.next`, `today.review_needed`, `session.running`, `session.stale`, `session.conflict`. All strings via i18n (en, id); errors via the T-048 mapper.

## Scope

**In:**
- Feature folder `web/src/features/today/**`, i18n, MSW handlers, Vitest tests incl. a skewed-clock test with fake timers, Playwright journey steps 1–7 of J1 (`web/tests/e2e/journeys/j1.spec.ts`), a11y gate route.

**Out:**
- Progress screen: T-206.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/today/**` | create |  |
| `web/src/i18n/{en,id}/today.json` | create |  |
| `web/src/routes/today.tsx` | modify | replace placeholder |
| `web/tests/{today.test.tsx,e2e/journeys/j1.spec.ts}` | create | AC-1…AC-8 |
| `web/tests/e2e/gates/a11y.spec.ts` | modify | add route |

## Interfaces

```ts
useToday(date?); useStartSession(); useCompleteSession(id); useAbandonSession(id); useCurrentSession();
export function elapsedSeconds(startedAt: string, serverNow: string, receivedAtLocalMs: number, nowLocalMs: number): number;
```

## Business rules

1. The browser never computes now/next, counts or allowed actions.
2. The timer is derived from timestamps, not from an incrementing counter.
3. Undo uses a server-provided transition, never a hard-coded status.

## Security

- Titles and notes rendered as text nodes only.

## Implementation notes

- The timer component is a single small hook so skew logic is unit-tested without the DOM.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a server clock 5 minutes ahead of the browser **When** the timer renders **Then** elapsed time equals server-based elapsed time | `today::exe_fr_10_timer_skew_corrected` |
| AC-2 | **Given** a reload mid-session **When** the page loads **Then** the same elapsed time continues | `today::timer_survives_reload` |
| AC-3 | **Given** Start on the Now task **When** pressed **Then** the running card appears and the task shows `in_progress` | `today::start_shows_running_card` |
| AC-4 | **Given** Complete → Done **When** confirmed **Then** counts update and the toast offers Undo that reopens via the server transition | `today::complete_and_undo` |
| AC-5 | **Given** a 409 `session_already_running` **When** received **Then** `session.conflict` with an Open it action | `today::session_conflict_state` |
| AC-6 | **Given** a stale session **When** shown **Then** `session.stale` with Complete and Abandon | `today::exe_fr_07_stale_state` |
| AC-7 | **Given** keyboard only **When** `g t`, Tab, Enter are used **Then** a session starts and the change is announced | `e2e::j1_keyboard_flow` |
| AC-8 | **Given** J1 steps 1–6 **When** run in the browser **Then** each expected result holds | `e2e::j1_first_love_loop` |

## Tests

```
pnpm --dir web test
pnpm --dir web check-i18n
just e2e   # environment-limited without browsers
just check
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
