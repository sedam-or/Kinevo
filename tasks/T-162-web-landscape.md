---
id: T-162
title: Build the Landscape screen: events, overrides and ICS import and export
milestone: M3
kind: feature
status: todo
depends_on: [T-154, T-111]
requirements: [LAND-FR-02, LAND-FR-10, LAND-FR-13, LAND-FR-14, LAND-FR-16, FE-04, FE-14, UXS-02, DS-A11Y-13, J3]
branch: m3-t162-web-landscape
budget_lines: 420
assignee: ""
---

# T-162: Build the Landscape screen: events, overrides and ICS import and export

## Goal

Users can maintain their fixed commitments, shift or cancel single occurrences, and import and export calendars with a clear preview and undo.

## Context

> **Route** (`spec/08-frontend.md` §3): `/plan/landscape` (nav item contributed by the `landscape` descriptor, permission `landscape.event.read`). Views: list/week of source events with recurrence summary; event editor (title, start/end, timezone default from profile, recurrence: none/daily/weekly with interval, weekday chips, end: never/count/until-date); occurrence actions **Skip this one**, **Move this one**, **Shift from here** (permanent, +N days) creating overrides; override list per event with Remove.
> **Import** (`modules/landscape.md` §5–§6): choose `.ics` → preview table with checkbox per item, `{new}`/`{exists}` badges and warnings rendered from codes (`rrule_unsupported`, `all_day_skipped_by_default`, `floating_time_assumed_account_timezone`, `rejected[]` with line numbers); Commit with `Idempotency-Key`; after commit an **Undo import** action using the batch id. Size limit text from the registry (`ics_too_large`). Export button: downloads `export.ics` for the visible window.
> **States** (`standards/ux-states.md`): `landscape.empty`, loading, error, stale (`version_conflict` keeps the user's text), `landscape.import.preview`, `landscape.import.committed`. All text through i18n keys (en, id); errors through the T-048 mapper; no business logic: recurrence summaries and the effective list come from the server (`GET /landscape/effective`), the web does not expand recurrences.
> **A11y**: dialogs and sheets from T-046; weekday chips are a labelled toggle group; the preview table is a real table with a caption; WCAG 2.2 AA via the axe gate (DS-A11Y-13).

## Scope

**In:**
- Feature folder `web/src/features/landscape/**` (routes, hooks over the generated API client, components, i18n keys en/id), descriptor nav entry, MSW handlers, Vitest tests, Playwright test for the import and override flow (journey steps 1–2 and 8 of J3).
- Extend the a11y gate with the landscape route.

**Out:**
- Schedule screen: T-163.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/landscape/**` | create |  |
| `web/src/i18n/{en,id}/landscape.json` | create | keys; the check-i18n gate must pass |
| `web/tests/{landscape.test.tsx,e2e/landscape.spec.ts}` | create | AC-1…AC-8 |
| `web/tests/e2e/gates/a11y.spec.ts` | modify | add route |

## Interfaces

```ts
// hooks over the generated client
useEffectiveLandscape(from, to); useCreateEvent(); useCreateOverride(eventId); usePreviewIcs(); useCommitIcs(); useUndoImport();
```

## Business rules

1. Weekday labels and recurrence phrases come from i18n with plural rules, not string concatenation.
2. Warnings and violations are rendered from codes, never from server prose.
3. Skipping an occurrence creates a one-time cancel override for that occurrence's original local date.
4. Nothing is expanded or resolved in the browser.

## Security

- The uploaded file is read by the server; the browser only posts it. File name and titles are rendered as text.
- No `dangerouslySetInnerHTML`.

## Implementation notes

- Keep each component under about 150 lines; reuse T-045/T-046 primitives.
- Use the fixture `.ics` files from T-154 for e2e.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** no events **When** the route loads **Then** `landscape.empty` with a create action | `landscape::ux_empty_state_and_create` |
| AC-2 | **Given** a weekly event **When** the user picks **Skip this one** **Then** a one-time cancel override is created and the effective list marks it cancelled | `landscape::land_fr_02_skip_creates_cancel_override` |
| AC-3 | **Given** an ICS preview with a MONTHLY rule and an all-day event **When** shown **Then** the warnings render from codes and the all-day item is unchecked | `landscape::land_fr_13_preview_warnings_and_defaults` |
| AC-4 | **Given** an `exists` item **When** selected and committed **Then** the replace behaviour is requested | `landscape::land_fr_14_exists_item_replace_selected` |
| AC-5 | **Given** a committed import **When** Undo is used **Then** the batch is removed and the list refreshes | `landscape::land_fr_16_undo_import` |
| AC-6 | **Given** a stale version **When** saving **Then** the user's text is kept and the conflict is explained | `landscape::version_conflict_keeps_user_text` |
| AC-7 | **Given** the route **When** axe runs **Then** no serious violations | `e2e::a11y_landscape_route` |
| AC-8 | **Given** J3 steps 1, 2 and 8 **When** run in the browser **Then** the preview counts, cancelled occurrence and round trip match | `e2e::j3_landscape_steps` |

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
