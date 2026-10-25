---
id: T-206
title: Build the Progress screen: daily facts, goal progress and the weekly review
milestone: M4
kind: feature
status: todo
depends_on: [T-205, T-203]
requirements: [PRG-FR-03, PRG-FR-05, PRG-FR-08, FE-04, FE-14, UXS-02, DS-A11Y-13]
branch: m4-t206-web-progress
budget_lines: 380
assignee: ""
---

# T-206: Build the Progress screen: daily facts, goal progress and the weekly review

## Goal

Users see what moved, per day, goal and week, as honest facts with accessible charts and text alternatives.

## Context

> **Route** (`spec/08-frontend.md` §3): `/reflect/progress` (nav from the `progress` descriptor, permission `progress.summary.read`). Sections: daily bars (completed, focus minutes) for 7/30/90 days, goal list with milestone and task counts and the overdue facts, weekly review (week picker, completed / missed / partial, focus minutes, by workspace, missed task list).
> **Principles**: facts, not scores — no streaks, points or badges, no colour-only status. Charts follow the `dataviz` rules of `09-design-system.md`: every chart has a visible table alternative, colours from OKLCH tokens that pass the contrast gate, and labels not truncated at 320 px. `freshness.pending_events > 0` shows an "updating" hint (PRG-FR-08). Workspace filter uses the workspace picker; a 404 from a foreign id shows `not_found`.
> **States**: loading, `progress.empty` (no activity yet, link to Today), error, stale. All strings i18n (en, id); numbers and dates formatted with `Intl` in the account locale/timezone. No business logic in the browser: net counts come from the server.

## Scope

**In:**
- Feature folder `web/src/features/progress/**`, i18n, MSW handlers, Vitest tests, Playwright test for J1 steps 4–5 progress assertions, a11y gate route.

**Out:**
- New server logic.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/progress/**` | create |  |
| `web/src/i18n/{en,id}/progress.json` | create |  |
| `web/tests/{progress.test.tsx,e2e/progress.spec.ts}` | create | AC-1…AC-6 |
| `web/tests/e2e/gates/a11y.spec.ts` | modify | add route |

## Interfaces

```ts
useProgressSummary(range, workspaceId?); useProgressGoals(filters); useWeeklyReview(weekOf);
```

## Business rules

1. Every chart has an adjacent data table.
2. No gamification elements.
3. Dates and numbers use `Intl` with the account locale and timezone.

## Security

- Text nodes only; no raw HTML.

## Implementation notes

- Keep the chart a small SVG component with `role="img"` and a `<title>`/`<desc>`; do not add a charting dependency unless the stack map already lists one.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a summary **When** rendered **Then** the chart and its table show the same numbers | `progress::chart_and_table_match` |
| AC-2 | **Given** no activity **When** loaded **Then** `progress.empty` with a link to Today | `progress::ux_empty_state` |
| AC-3 | **Given** pending events **When** loaded **Then** the updating hint shows | `progress::prg_fr_08_updating_hint` |
| AC-4 | **Given** a weekly review **When** the week changes **Then** the server's bounds are shown | `progress::weekly_review_follows_server_bounds` |
| AC-5 | **Given** the route **When** axe runs **Then** no serious violations | `e2e::a11y_progress_route` |
| AC-6 | **Given** J1 steps 4–5 **When** run **Then** completion appears and undo nets zero | `e2e::j1_progress_assertions` |

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
