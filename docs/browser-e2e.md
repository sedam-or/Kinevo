# Kinevo — Browser E2E Verification

> **Document role:** Browser QA matrix + golden-journey records for the UI/UX
> stabilization effort.
>
> **Status:** Baseline declared (lifecycle: ACTIVE). Live results entered after
> rescue Phase R1 (real browser smoke test) and maintained through R6 (visual
> regression) and R7 (release readiness).
>
> **Contract:** `docs/design.md` §35, §71, §72, §73, §86, §87, §99; SRS §17.4,
> docs/test-strategy.md.

---

## 1. Purpose

Record what must be proven in a real browser and whether it passes. The Canvas
and offline surfaces are deliberately covered here because unit/adapter tests
mock the Excalidraw island and the network edges.

## 2. Principle

`DONE` at contract level ≠ production-ready UX. Per `docs/design.md` §74, real
browser verification is a separate gate. Automated unit counts do not close the
rescue phase (design.md §103).

## 3. Tooling (declared for R1)

`tests/e2e/` is created in R1 with a real browser runner. Target matrix:

```text
Chromium
Firefox
WebKit/Safari-equivalent
```

Bare `docker run`-style headless runs for CI; wider matrix documented here.

## 4. Global browser QA matrix (design.md §71)

Focus surfaces: Today, Quick Capture, Task, Schedule, Notes, Canvas, Offline.

Result legend: ✅ pass · 🔴 fail · ⚠ partial · ⚪ not run. "Rendered" = the
surface mounts and is visible in a real browser. Chromium row reflects the
TASK-R1 Docker Playwright run (commit evidence below).

| Browser | Today | Quick Capture | Task | Schedule | Notes | Canvas | Offline |
| ------- | ----- | ------------- | ---- | -------- | ----- | ------ | ------- |
| Chromium | ✅ rendered | ⚪ | ✅ rendered | ✅ rendered | ✅ rendered | ✅ shell rendered | ⚪ |
| Firefox | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| WebKit/Safari | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |

Legend: ✅ pass · 🔴 fail · ⚠ partial · ⚪ not run.

### R1 browser smoke evidence (TASK-R1)

- Runner: `tests/e2e/` Docker Playwright (`mcr.microsoft.com/playwright:v1.62.1-jammy`),
  Chromium desktop, host-network attach to the dev SPA on `127.0.0.1:8000`
  (`make e2e` / `docker build -t kinevo-e2e tests/e2e`).
- Result: **3/3 passed** (`npx playwright test`, JSON reporter) — login → Today,
  invalid-password rejection, and all primary nav destinations render
  (Today/Week/Schedule/Goals/Tasks/Knowledge/Canvas/Analytics/Settings).
- Canonical evidence: CI/browser-e2e.md + Playwright JSON report; first-love
  shell mount proven in a real browser. Deeper surface behavior (task/goal/note
  create-edit, canvas draw/persist, offline) is tracked as P2 gaps in
  `docs/ui-audit.md` (see §4 matrix rows still ⚪) and scheduled for R3/R4.
- Known limit: `make e2e` currently covers Chromium only; Firefox + WebKit rows
  are pending the same runner added to the matrix.

## 5. Canvas browser matrix (design.md §35, §72)

| Scenario | Result |
| -------- | ------ |
| Open new canvas | ⚪ |
| Open existing canvas | ⚪ |
| Draw | ⚪ |
| Text | ⚪ |
| Move | ⚪ |
| Delete | ⚪ |
| Undo / Redo | ⚪ |
| Save | ⚪ |
| Reload (persistence) | ⚪ |
| Offline | ⚪ |
| Reconnect | ⚪ |
| Version conflict (409) | ⚪ |
| Archive | ⚪ |
| Rename | ⚪ |
| Context links | ⚪ |
| Light mode / Dark mode | ⚪ |
| Window resize | ⚪ |
| Mobile-compatible fallback where supported | ⚪ |
| Fresh session vs authenticated session | ⚪ |
| Empty canvas / large canvas / image or file | ⚪ |
| Route navigation, back/forward, refresh | ⚪ |

Canvas entry state must never leave the page blank (design.md §34.2) and the
save state must always be visible (design.md §34.4). The dev-only diagnostic
route `/dev/canvas-diagnostics` (§36) is exercised in dev and verified disabled
in production (TASK-R4).

Contract-level entry-state coverage landed in TASK-R4:
- Async boundary lazy-loads the Excalidraw chunk by route (§89) with a visible
  "Loading Canvas…" state.
- `CanvasHost` surfaces loading → ready → error entry states (§34.2) with
  Retry / Open read-only, and never renders a blank page.
- The `canvas-editor-loading` / `canvas-editor-error` / `canvas-host` states are
  unit-tested (vitest); the browser rows above still await R7 real-browser runs.

## 6. Canvas boundary sequence (design.md §82)

Measured per boundary:

```text
Canvas route
 ↓ React island mount
 ↓ Excalidraw render
 ↓ load scene
 ↓ change event
 ↓ autosave
 ↓ server persistence
 ↓ offline
 ↓ reconnect
```

Each boundary has a row in the R4 canvas-hardening record, not just one overall
pass/fail.

## 7. Golden user journeys (design.md §73, §99)

### Journey A — Plan

```text
PLAN group → Goals
Create Goal
Create Milestone
Create Program
PLAN group → Tasks
Create Task
```

Result: ⚪ (browser run pending)

### Journey B — Execute

```text
EXECUTE group → Today
NOW task visible
Start task
Pause
Resume
Complete
NEXT task advances
```

Result: ⚪ (browser run pending)

### Journey C — Recover

```text
EXECUTE group → Today
Miss task
EOD
Morning Recovery
Reschedule
```

Result: ⚪ (browser run pending)

### Journey D — Knowledge

```text
KNOWLEDGE group → Knowledge
Create Note
Edit (autosave indicator top-right)
Link Goal
KNOWLEDGE group → Canvas
Create Canvas
Link Canvas
```

Result: ⚪ (browser run pending)

### Journey E — Offline

```text
Go offline
Quick Capture
Edit
Reconnect
Sync
```

Result: ⚪

### Journey F — AI

```text
Goal
AI proposal
Review
Accept
Milestones
```

Result: ⚪

### Core loop (design.md §99 — highest priority)

```text
LOGIN → TODAY → NOW TASK → START → COMPLETE → PROGRESS → NEXT TASK
```

First browser journey that must be beautiful and reliable. Result: ⚪

## 8. Journey execution record

For each journey run, record:

```text
Journey id
Date
Runner (Playwright/CI job id)
Browser(s)
Seed data used
Steps executed
Assertions/user-visible checks
Failures (finding id + repro)
Result
```

## 9. Visual regression (design.md §87)

Screens with baseline snapshots:

```text
Today
Task detail
Goals
Notes
Canvas shell
Analytics
```

Snapshots are reviewed intentionally — never accepted automatically.

## 10. Readiness gate

R1 produces an empty-to-full matrix; R7 closes rescue only when all ⚪ rows are
resolved and §102 design.md acceptance gate holds.

---

## Maintenance

- Updated per browser run; each golden journey has an evidence trail.
- The matrix reflects the live state of the repo, not intent.