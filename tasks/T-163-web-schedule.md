---
id: T-163
title: Build the Schedule screen: week view, draft review, Sync Now and the review banner
milestone: M3
kind: feature
status: todo
depends_on: [T-161, T-162]
requirements: [SCH-FR-04, SCH-FR-08, SCH-FR-09, SCH-FR-10, SCH-FR-11, FE-04, FE-14, UXS-02, DS-A11Y-13, J3]
branch: m3-t163-web-schedule
budget_lines: 420
assignee: ""
---

# T-163: Build the Schedule screen: week view, draft review, Sync Now and the review banner

## Goal

Users can generate, understand, apply or dismiss schedule proposals, see why each task is placed, and are told when the schedule needs a second look.

## Context

> **Route** (`spec/08-frontend.md` §3): `/plan/schedule` (nav item from the `scheduler` descriptor, permission `scheduler.schedule.read`). Week view merges `GET /schedule/assignments` with `GET /landscape/effective` for the visible window (landscape blocks read-only); draft review panel lists placements, moves, unassigned tasks (with violation codes) and warnings; actions **Generate week**, **Sync Now**, **Apply**, **Dismiss**, per-assignment **Lock**, **Move to…** (manual placement) and **Unschedule**.
> **Why here?** (SCH-FR-04): each placement opens the reasons list rendered from `reasons[]` codes with params via keys `placement.reason.<code>`; each unassigned task shows `placement.violation.<code>` entries.
> **States** (`standards/ux-states.md`): `schedule.draft.pending`, `schedule.draft.applied`, `schedule.draft.stale` (after `schedule_version_conflict` or `draft_infeasible`: "schedule changed — review again" with Generate again), `schedule.review.needed` banner (from `GET /schedule/state`, shows `review_reason` code text, link to Sync Now), `schedule.sync.no_changes`, `schedule.sync.busy` (disable buttons, show retry time), and a **weekly draft ready** banner derived from a pending weekly draft (D-51, no notice table).
> **Concurrency**: Apply sends `base_version` from the draft; every mutating call sends `Idempotency-Key`; errors map through T-048. The web never validates placements itself.
> **A11y**: the week view has a list alternative (a table per day) and is keyboard operable; drag-and-drop is not required (Move to… uses a dialog with time inputs).
> **Journey J3** steps 3–7 are the e2e target.

## Scope

**In:**
- Feature folder `web/src/features/schedule/**`, i18n keys (en, id) including every reason/violation code from `scheduler.md` §2.6–§2.7, descriptor nav entry, MSW handlers, Vitest tests, Playwright for J3 steps 3–7 (drafts apply, shift a recurring meeting → review banner, Sync Now, stale draft from a second tab).
- Extend the a11y gate with the schedule route.

**Out:**
- Today screen content: M4.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/schedule/**` | create |  |
| `web/src/i18n/{en,id}/schedule.json` | create | every reason and violation code |
| `web/tests/{schedule.test.tsx,e2e/schedule.spec.ts}` | create | AC-1…AC-8 |
| `web/tests/e2e/gates/a11y.spec.ts` | modify | add route |

## Interfaces

```ts
useScheduleState(); useAssignments(from, to); useDrafts(status); useGenerateDraft(); useSyncNow(); useApplyDraft(id); useDismissDraft(id);
usePlaceAssignment(taskId); useSetLock(id); useRemoveAssignment(id);
```

## Business rules

1. A reason or violation code without a translation is a failing test (the check-i18n gate lists all codes).
2. Apply uses the draft's `base_version`; a 409 turns the draft into the stale state without data loss.
3. Sync Now with `no_changes` shows the info state, never an error.

## Security

- Everything rendered from codes and ids; task titles are text nodes.
- No schedule logic in the browser; the WASM preview is M5.

## Implementation notes

- Time rendering uses the account timezone from the profile; use `Intl.DateTimeFormat` with the zone, no hand-made offsets.
- Do not poll aggressively: refetch on focus and after mutations.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a pending draft **When** opened **Then** placements, moves and unassigned entries show with their reasons and violations | `schedule::sch_fr_04_why_here_reasons_render` |
| AC-2 | **Given** a draft **When** Apply is pressed **Then** `base_version` and an `Idempotency-Key` are sent and the view refreshes | `schedule::sch_fr_09_apply_sends_base_version` |
| AC-3 | **Given** a 409 `schedule_version_conflict` or `draft_infeasible` **When** received **Then** the stale state appears with Generate again | `schedule::ux_stale_draft_state` |
| AC-4 | **Given** `review_state=needs_review` **When** the route loads **Then** the banner shows the reason and a Sync Now action | `schedule::sch_fr_12_review_banner` |
| AC-5 | **Given** a `no_changes` response **When** Sync Now finishes **Then** the info state shows | `schedule::sch_fr_10_no_changes_state` |
| AC-6 | **Given** a pending weekly draft **When** the route loads **Then** the weekly-draft banner shows (derived, D-51) | `schedule::sch_fr_11_weekly_banner_derived` |
| AC-7 | **Given** the route **When** axe runs **Then** no serious violations | `e2e::a11y_schedule_route` |
| AC-8 | **Given** J3 steps 3–7 **When** run in the browser **Then** each expected result is asserted | `e2e::j3_schedule_steps` |

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
