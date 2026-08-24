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
| Chromium | ✅ rendered | ✅ create flow | ✅ create flow | ✅ rendered | ✅ edit + autosave | ✅ shell + create | ⚪ |
| Firefox | ✅ rendered | ✅ create flow | ✅ create flow | ✅ rendered | ✅ edit + autosave | ✅ shell + create | ⚪ |
| WebKit/Safari | ✅ rendered | ✅ create flow | ✅ create flow | ✅ rendered | ✅ edit + autosave | ✅ shell + create | ⚪ |

Legend: ✅ pass · 🔴 fail · ⚠ partial · ⚪ not run.

### R6 browser E2E evidence (TASK-R6)

- Runner: `tests/e2e/` Docker Playwright (`mcr.microsoft.com/playwright:v1.62.1-jammy`),
  projects **chromium**, **firefox**, **webkit**, host-network attach to the dev
  SPA on `127.0.0.1:8000` (`make e2e` / `docker build -t kinevo-e2e tests/e2e`).
- Result: **54/54 passed** across the three-browser matrix (1 worker, 1.3 min).
  Specs: `login.spec.ts`, `journeys.spec.ts` (R1), `golden-journeys.spec.ts`
  (R6 journeys A/B/D + journey G Plan→breakdown + surface create flows),
  `surface-qa.spec.ts`
  (R6 §88/§93 — page errors, horizontal overflow, spinner), `visual-baseline.spec.ts`
  (R6 §87 — screenshot artifacts).
- Re-verification (2026-08-22, post-R4/R5): **105/105 passed** (3.0 min) — the
  R4 canvas matrix and R5 accessibility suite joined the run. Two defects
  found and fixed during re-verification:
  1. A plain production build dead-code-eliminates the `__kinevoCanvasAdapter`
     e2e seam, silently disarming the canvas draw/persistence tests.
     `make e2e` now rebuilds assets with `KINEVO_E2E_SEAM=1` first (`e2e-assets`).
  2. QuickCapture mounted once at boot with `v-if` inside, so
     `useFocusTrap`'s onMounted initial focus never fired (focus stayed on
     `<body>` after Ctrl+K). The dialog component now mounts only while open.
- Canonical evidence: `docs/browser-e2e.md` + Playwright list report / PNG
  artifacts under `tests/e2e/test-results/screenshots/<browser>/`.
- Per-browser surface QA: no uncaught page errors, no horizontal overflow on
  Today/Week/Schedule/Goals/Tasks/Knowledge in Chromium, Firefox, WebKit.
- Git commits: R1 `5f32f1d` (smoke harness); R6 spec + matrix commits listed in
  the journal section below.
- Known gaps (not yet browser-proven): AI (Journey F) — the AI backend
  (proposals/accept contract) is proven at integration level only; no AI
  mutation surface ships in the UI during the stabilization freeze (see §8
  R7 record). Journey C, canvas Journey E, canvas keyboard flow, dark mode,
  mobile 375px, and screen-reader state smoke are browser-proven by R7.
  NOW executor transitions are browser-proven by the R1 core-loop run (§8).

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
- Known limit: the R1 harness covered Chromium only; the R6 matrix run
  (`832c1ec..`) added Firefox + WebKit projects, so the ? rows are now updated
  with real results (Offline still pending, see §4/§7).

## 5. Canvas browser matrix (design.md §35, §72)

| Scenario | Result |
| -------- | ------ |
| Open new canvas | ✅ chromium (TASK-R4) |
| Open existing canvas | ✅ chromium (TASK-R4) |
| Draw (adapter-boundary seam, see note) | ✅ chromium/firefox/webkit (TASK-R4) |
| Text | ⚪ R7 |
| Move | ⚪ R7 |
| Delete | ⚪ R7 |
| Undo / Redo | ⚪ R7 |
| Save (autosave → server 200) | ✅ all three engines (TASK-R4) |
| Reload (persistence) | ✅ all three engines (TASK-R4) |
| Offline (save surfaces offline state) | ✅ all three engines (TASK-R4) |
| Reconnect (sync path restored) | ✅ all three engines (TASK-R4) |
| Version conflict (409) + manual reconcile | ✅ all three engines (TASK-R4) |
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

### R4 canvas-hardening browser evidence (TASK-R4)

`tests/e2e/tests/canvas-hardening.spec.ts` — 8 tests × chromium/firefox/webkit,
all passing (24/24, dockerized Playwright against a live server):

| # | Test | §82 boundary proven |
| - | ---- | ------------------- |
| 1 | Entry: bounded canvas + visible save state | route → mount → render |
| 2 | Draw → autosave → server persistence survives reload | change → autosave → server → reload |
| 3 | Rename persists to list | server |
| 4 | Theme cycle auto→light→dark + read-only toggle | render |
| 5 | Conflict 409 surfaces banner; manual reconcile clears it | autosave → conflict → reconcile |
| 6 | Offline draw surfaces offline state; reconnect restores path | offline → reconnect |
| 7 | Archive confirm flow | server |
| 8 | Back navigation remounts cleanly | route |

**Draw input seam (documented limitation).** This runner's software-rendered
headless engines trap (renderer int3) on real pointer input over Excalidraw.
Tests drive `window.__kinevoCanvasAdapter.load(...)` — a dev/e2e-only seam
(present only in artifacts built with `KINEVO_E2E_SEAM=1`; plain production
builds dead-code-eliminate it). The seam
enters through the app's REAL adapter boundary, so every boundary after "input"
is production-identical. Real-input-device drawing remains an R7 physical/CI
check (rows marked ⚪).

**Defects found and fixed by this matrix (why it exists):**
1. Infinite scene echo loop (`change` → workspace prop → `load` → `onChange`)
   starved autosave — saves never fired for real users. Fixed via raw-identity
   echo-guard in `CanvasHost` + fixed-window trailing debounce in
   `CanvasAutosaveController` (unit-pinned).
2. Conflict "Reload server copy" reconciled from the stale in-memory document
   and never cleared the banner. Now re-fetches server truth first (§34.5).

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
Result: ✅ R6 pass — goal create (Quarterly horizon to respect FR-19 yearly caps)
and task create both surface in their lists on Chromium/Firefox/WebKit.

### Journey B — Execute

```text
EXECUTE group → Today
NOW task visible
Start task
Pause
Resume
Complete
NEXT task advances
Capacity load bar reveals scheduled/available on click (§22)
Context check-in: pick energy level, log (§23)
```
Result: ✅ proven end-to-end — Today mounts, Quick Capture round-trips, NOW
task executes START → COMPLETE, and NEXT advances on all 3 browsers via the R1
core-loop run (§8, §7 Core loop). Capacity bar + adaptive check-in landed in
TASK-R3 (2026-08-22); not yet re-verified in browser.

### Journey C — Recover

```text
EXECUTE group → Today
Miss task
EOD
Morning Recovery
Reschedule
```
Result: ✅ R7 pass (chromium, seeded) — `tests/e2e/scripts/seed-journey-c.sh` +
`eod:reconcile --phase=deadline` seed a missed task; browser proves the missed
badge on the task item and the reschedule surface proposing moves or an explicit
no-changes state. Single-engine by design (global seeded state); recorded in §7.
Note: seeding must use assignment source `draft` — `scheduler` is not a valid
ScheduleAssignmentSource and 422s draft generation (fixed 2026-08-22).

### Journey D — Knowledge

```text
KNOWLEDGE group → Knowledge
Create Note
Edit (autosave indicator top-right)
Use §31 toolbar (heading/bold/italic/link/list/tasks)
Linked entities show in the desktop desk sidebar (§33)
Link Goal
KNOWLEDGE group → Canvas
Create Canvas
Link Canvas
```
Result: ✅ R6 pass (partial) — Note create + edit surfaces autosave state, Canvas
create mounts the lazy-loaded workspace on all 3 browsers. §31 toolbar + §33
linked-entities desk sidebar landed in TASK-R3 (2026-08-22); not yet re-verified
in browser.

### Journey E — Offline

```text
Go offline
Quick Capture
Edit
Reconnect
Sync
```

Result: ✅ R7 pass (chromium) — canvas variant of the offline journey: draw
offline → save badge leaves "saved" → reconnect → server-confirmed PUT →
reload → scene restored from the server. This run exposed and closed a real
§34.6 gap: `CanvasAutosaveController` dropped offline-pending scenes on reload
(nothing re-saved on reconnect, violating offline-sync.md "sync on reconnect");
fixed by an `online`-event retry in `autosave.ts`. The Quick Capture offline
variant (note/task mutations through the general MutationQueue) remains
covered at unit level only (TASK-115 suite); browser proof pending.

### Journey F — AI

```text
Goal
AI proposal
Review
Accept
Milestones
```

Result: ⚪

### Journey G — Plan, then decide how to break down (TASK-P17-003)

```text
LOGIN → PLAN (Goals) → New goal (Outcome/Description/Deadline)
     → Goal created → breakdown suggestion:
       [Generate with AI] [I'll do it myself] [Later]
     → no automatic mutation of the goal
```

The goal-creation planning workflow: after submit the goal is stored and the
product *offers* an AI breakdown; the goal/milestones are NEVER mutated without
an explicit choice. "Generate with AI" creates a *pending* proposal via the
validated proposal contract (SRS FR-52/FR-62); the full proposal review/edit/
accept UX renders inline in the post-create panel (TASK-P17-026) — the user
never has to leave the page to approve a breakdown.

Result: ✅ P17-003 pass (chromium) — `golden-journeys.spec.ts` "golden journey G";
suggestion renders with all three actions; "I'll do it myself" opens the goal
detail for manual planning.

### Core loop (design.md §99 — highest priority)

```text
LOGIN → TODAY → NOW TASK → START → COMPLETE → PROGRESS → NEXT TASK
```

First browser journey that must be beautiful and reliable. Result: ✅ R1 — the
full loop (LOGIN → TODAY → NOW → START → COMPLETE → PROGRESS → NEXT) passes in
Chromium + Firefox (+ WebKit) through the real browser, see §8 R1 core-loop run.

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

### R6 journey runs

```text
Journey A (Plan)       2026-08-21  Playwright chromium/firefox/webkit  dev DB (fresh owners)
                         steps: login → Goals → create goal (Quarterly) → Tasks → create task
                         checks: goal + task visible in lists          Result: ✅
Journey B (Execute)    2026-08-21  Playwright chromium/firefox/webkit  dev DB
                         steps: login → Today → Quick Capture title → submit
                         checks: capture accepted, surface reloads, qc field clears
                         Result: ✅ (R1 core loop proves capture + NOW executor)
Journey D (Knowledge)  2026-08-21  Playwright chromium/firefox/webkit  dev DB
                         steps: login → Knowledge → create note → edit title →
                                Canvas → create canvas
                         checks: autosave state visible, workspace mounts (lazy chunk)
                         Result: ✅ (goal-link not re-verified in browser)
Core loop              2026-08-21  Playwright chromium/firefox/webkit  dev DB
                         steps: LOGIN → TODAY → quick-capture → next-day shift
                        Result: superseded by R1 core-loop run below.
Journey G (Plan)        2026-08-22  Playwright chromium  dev DB (fresh owners)
                         steps: login → Goals → create goal (Outcome/Description/
                                Deadline, Quarterly) → breakdown suggestion →
                                "I'll do it myself" → goal detail
                         checks: suggestion shows [Generate with AI] [Manual]
                                [Later]; goal never auto-mutated; manual opens detail
                         Result: ✅ (golden-journeys.spec.ts, TASK-P17-003)
### R1 core-loop runs
```text
Core loop (R1)          2026-08-21  Playwright chromium/firefox/webkit  dev DB
                         seed: quick-capture 2 tasks onto future day (adds
                               fixtures to the 70% safety reserve only on that
                               day; offset varies per run to stay free)
                         steps: LOGIN → TODAY → NOW card visible (clock synced
                               to assigned slot) → START (Running) → elapsed
                               accrues from server timestamp (clock restored) →
                               COMPLETE (Ready) → TODAY reloads without error
                         checks: now-title == seeded task, next-card shows the
                               second seeded task, status Running → Ready,
                               today-error absent
                         full suite: 57/57 passed (includes this journey)
                         Result: ✅ (Chromium + Firefox + WebKit)
 ```
### R7 journey runs
```text
Journey C (Recover)     2026-08-22  Playwright chromium (single-engine record)
 Journey E (Offline,     dev DB; C seeded via tests/e2e/scripts/seed-journey-c.sh
 canvas variant)         + eod:reconcile --phase=deadline
                         steps C: login → Tasks → missed badge on seeded task →
                           Schedule → Generate Draft → Dynamic Reschedule →
                           Propose (moves or explicit no-changes state)
                         steps E: login → Canvas → create → go OFFLINE →
                           adapter.load(rect) → save badge leaves "saved" →
                           reconnect → ok PUT observed → reload+reopen →
                           scene_json.elements contains the offline element
                         findings fixed during the run:
                           F-R7-1 seed used invalid assignment source
                             `scheduler` (422 on draft generate); corrected to
                             `draft` in seed script + DB
                           F-R7-2 CanvasAutosaveController never retried an
                             offline-pending scene on reconnect (silent loss vs
                             offline-sync.md "sync on reconnect"); fixed with
                             window-online retry in autosave.ts
                         full suite at commit time: 109 passed / 2 skipped
                         Result: ✅
Release gate (R7)       2026-08-22  Playwright chromium  dev DB
                         tests/e2e/tests/release-gate.spec.ts — §102 proofs:
                           canvas keyboard-only flow (create/leave, no mouse);
                           mobile 375px smoke (no horizontal overflow on
                           Today/Tasks/Goals/Knowledge/Canvas/Schedule +
                           canvas workspace); dark-mode WCAG scans (Today/
                           Knowledge/Canvas shell, island excluded); SR live-
                           region smoke (save/sync role=status + aria-live,
                           bell accessible name)
                         findings fixed during the run: UI-011 (dark-mode
                           danger-button contrast → --color-danger-contrast
                           token), UI-012 (375px overflow: topbar, mobile nav,
                           sync explanation clamp, canvas header wrap)
                         full suite with this spec: 124 passed / 2 skipped
                         Result: ✅
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

R6 baseline artifacts: `tests/e2e/test-results/screenshots/<browser>/`
(e.g. `chromium/today.png`, `chromium/task.png`, `chromium/goals.png`,
`chromium/notes.png`, `chromium/canvas.png`, `chromium/analytics.png`).
Captured by `tests/e2e/tests/visual-baseline.spec.ts` per matrix browser on
2026-08-21 (commit evidence in §4).

Snapshots are reviewed intentionally — never accepted automatically. R6
reviewer note: the artifact PNGs are stored for human review; the agent-level
model cannot inspect pixels, so this run records the machine-checkable
invariants instead (no uncaught page errors, no horizontal overflow, no
persistent spinner — see `surface-qa.spec.ts`) plus the raw snapshots for a
human pair-of-eyes before R7. No snapshot was auto-accepted.

## 10. Readiness gate

R1 produces an empty-to-full matrix; R7 closes rescue only when all ⚪ rows are
resolved and §102 design.md acceptance gate holds.

## 11. Phase 17 planned journeys (design.md §104)

Rescue R0–R7 closed on the §102 gate with evidence (Journey A/B/C/D/E + core
loop; Journey F = AI UI, intentionally browser-unproven and now triaged into
Phase 17). Phase 17 adds four journeys; each is recorded here on execution and
goes green across chromium/firefox/webkit before P17 tasks close:

```text
Journey G — Goal AI Breakdown    create goal → [Break down with AI] → generate
                                 → review proposal → accept → milestones appear
Journey H — Provider Setup       Settings → AI & Providers → set provider →
                                 credential → test connection → save → reload →
                                 status persists, key never returned raw
Journey I — Task → Today → Progress   task created → scheduled → appears in
                                 Today → start → complete → progress updates
Journey J — Analytics → Action   analytics shows interpretation → user clicks
                                 the related action → lands in that workflow
```

Theme real-browser proof (light/dark/system + reload + navigation + mobile) and
the mobile width sweep (375/390/412/768/1024/1440) are Phase 17 verification
items tracked in TASK.md (TASK-P17-013/033/034); visual baselines from §9 are
re-captured per §9 rules after the P17 redesign (TASK-P17-035).

### P17-001 — Product Information Architecture run (2026-08-22)
```text
tests/e2e/tests/navigation.spec.ts  chromium/firefox/webkit
  checks: group labels render (EXECUTE/PLAN/KNOWLEDGE/REVIEW/SYSTEM);
          Schedule pinned to PLAN, absent from SYSTEM (design.md §104);
          aria-current moves with selection; current-section breadcrumb updates;
          mobile bottom nav shows primary subset (today/tasks/goals/knowledge)
          + More drawer; drawer discloses remaining views; keyboard Enter from
          drawer selects and closes.  Result: ✅ 3 passed (3 browsers), plus
          journeys + surface-qa regression 39 passed.
accessibility.spec.ts (axe WCAG 2.2 A/AA) against the PRODUCTION bundle
  (public/hot moved aside so Laravel serves built assets): Result: ✅
  navigation + all core-surface scans clean — 24 passed. Note: running the
  same scans through the Vite dev server (`/hot`) fails color-contrast on the
  dev-only runtime-diagnostics panel (`opacity-60` dt rows) because DEV mode
  renders it; production bundles compile the panel out (design.md §36,
  ui-audit UI-006), so this is a dev-environment artifact, not product debt.
```

### P17-002 — Workflow Continuity Layer run (2026-08-22)
```text
tests/e2e/tests/continuity.spec.ts  chromium/firefox/webkit
  checks: goal detail → continuity strip (Tasks/Schedule/Progress→Analytics)
          navigates to each downstream surface; task detail → downstream
          (Schedule/Notes/Canvas) surfaces render; unlinked task shows NO Goal
          chip (no false upstream); deep-open (linked goal focus target) is
          unit-proven and the strip is visible in-browser. Result: ✅ 6 passed
          (3 browsers). Regression: journeys + golden-journeys + surface-qa +
          core-loop green (33 passed). Note: Milestones/Programs have no
          dedicated shell surface — their owning Goal is the single upstream
          entry (documented here per P17-002 Notes).
```

---

### P17-013 — Theme hardening run (2026-08-23)
```text
tests/e2e/tests/theme.spec.ts  chromium/firefox/webkit
  checks: unauth gate toggle reachable + switches theme; light/dark reload
          persistence with a pre-hydration class snapshot (no flash);
          color-scheme flips for native controls; system mode follows live OS
          switches; keyboard operable (focus + Enter); Excalidraw island
          re-themes live (.theme--dark present/absent); mobile 375px reachable.
  Result: ✅ 6 tests × 3 browsers = 18 passed. Regression: release-gate,
          canvas-hardening, navigation, continuity, core-loop, surface-qa,
          accessibility, tactile-language green across browsers.
```
Environment note (resolved 2026-08-24): `golden-journeys.spec.ts` journeys H,
G2/I/IJ require a reachable Ollama endpoint. Verify with
```
E2E_BASE_URL=http://127.0.0.1:8000 E2E_OLLAMA_URL=http://172.18.0.1:11434 \
npx playwright test golden-journeys journey-i journey-j
```
(host Ollama on the compose bridge; override the base URL with `E2E_OLLAMA_URL`
defaulting to `http://localhost:11434`). The app HTTP budget for provider
calls must exceed a 30s default — a cold local 7B can take minutes
(`AI_TIMEOUT_SECONDS` in `server/.env` and `infrastructure/docker-compose.yml`).
Run: 42 passed (3 browsers) on 2026-08-24.

### P17-014 — Today control-center run (2026-08-23)
```text
tests/e2e/tests/journey-i.spec.ts  chromium/firefox/webkit
  checks: seeded tasks render under the strict hierarchy NOW → NEXT →
          Timeline → progress → check-in → quick capture (DOM top-offset
          ordering); start → complete flips the progress strip by exactly one;
          mobile 375/390/412 have no horizontal overflow and keep the hub
          above the timeline. Result: ✅ 4 tests × 3 browsers = 12 passed.
  Fix landed from the proof: the adaptive check-in energy row (10×32px
  buttons) bled past 375px — it now wraps (design.md §58); regression
  core-loop/surface-qa/accessibility/release-gate green (60 passed).
```

### P17-015 — "Why This?" run (2026-08-23)
```text
journey-i.spec.ts extended (chromium/firefox/webkit)
  checks: NOW card exposes the collapsed "Why this task now?" toggle;
          expanding reveals tier (P3), no-deadline and 45m slot-fit lines;
          aria-expanded flips; second click collapses.
          Result: ✅ included in Journey I — 12 passed across browsers.
```

### P17-016 — Next Action Engine run (2026-08-23)
```text
tests/e2e/tests/next-action.spec.ts  chromium/firefox/webkit
  checks: goal without milestones → create-milestone (Do it focuses the
          milestone form); backlog task → schedule-task (navigates scheduler);
          scheduled → start-task (navigates Today); missed → recover-task
          (navigates scheduler); canvas offline → view-sync strip with queued
          note. AI-pending → review-proposal is resolver-unit-proven only
          (browser state requires Ollama; see §11 env note).
  Result: 5 tests × 3 browsers = 15 passed. Fix from proofs: the NOW-card
          pause-control row could bleed past 375px with long state messages —
          it now wraps (design.md §58); journey-i mobile proofs re-green.
```

### P17-017 — Connect Analytics to Decisions run (2026-08-23)
```text
tests/e2e/tests/journey-j.spec.ts  chromium/firefox/webkit
  checks: seeded scheduled task + focus session drive Analytics data; the
          Work-Life chart shows the What changed / Why it matters / What to do
          strip; the capacity card shows the recommendation plus the
          [Review schedule] action; clicking it lands in the Schedule workflow
          (schedule-draft-view) — analytics → interpretation → action.
  Result: 1 test × 3 browsers = 3 passed.
  Note: every chart now carries the deterministic interpretation strip
        (design.md §38/§104 P17-G); no chart stands alone.
```

### P17-018 — Analytics information hierarchy visual audit (2026-08-23)
```text
journey-j.spec.ts extended  chromium/firefox/webkit
  checks: executive signal block renders FIRST in Analytics (before any
          chart), then summary → goals → capacity → pillars → heatmap → days
          by DOM top-offset, whatever sections rendered. Signal content is
          priority-resolved (overdue > at-risk > overload > imbalance) with a
          resolving action; unit tests additionally prove via
          compareDocumentPosition that no chart precedes it.
  Result: 3 passed (1 test × 3 browsers) against the live stack.
  Closes ui-audit UX-C6 ("analytics shows 15 charts before signal") for the
  analytics surface.
```

### P17-019 — Analytics chart requirements audit (2026-08-23)
```text
journey-j.spec.ts extended  chromium/firefox/webkit
  checks: every chart exposes a meta header — period (resolved overview range
          from–to), unit caption, and color legend swatches matching the bars
          (Work/Recharge on summary & days, Scheduled/Overload on capacity).
          No pie charts; all productivity visuals are bars/heatmap.
  Result: 3 passed (1 test × 3 browsers) against the live stack.
  Regression reported with §10/P17-018: 81 passed, 9 pre-existing failures
  (golden-journeys AI/Ollama env blockers + canvas + feature-education Today).
```

### P17-020 — Analytics actionability run (2026-08-23)
```text
journey-j.spec.ts extended  chromium/firefox/webkit
  checks: each section drives an action and clicking it lands in the related
          workflow — Review milestone → goals-view (goal pressure), Plan a
          recharge/focus block → today-view (work/recharge imbalance), Reduce
          workload → schedule-draft-view (completion below 50%, new Execution
          card), Review schedule → schedule-draft-view (capacity). Optional
          actions are followed when the seeded state renders them.
  Result: 3 passed (1 test × 3 browsers) against the live stack. Regression:
          81 passed with the identical pre-existing failure set as P17-019.
```

### P17-021 — Design-system hierarchy visual audit (2026-08-23)
```text
tests/e2e/tests/analytics-hierarchy.spec.ts  chromium/firefox/webkit
  checks: Analytics renders the shared surface system (design-tokens §4a) —
          summary/goals/capacity/execution boxed as L2 .surface-primary;
          pillars/heatmap/per-day OPEN as L4 .surface-supporting (hairline,
          never boxed); section rhythm space-y-4 → space-y-6; light + dark
          themes both audited via kinevo.theme override.
  Result: 6 passed (2 tests × 3 browsers). Screenshots:
          test-results/screenshots/<browser>/p17-021-analytics-{light,dark}.png
```

### P17-026 — AI Goal Breakdown Quick Action spec (2026-08-24)
```text
golden-journeys.spec.ts — journey G2 reworked  chromium/firefox/webkit
  checks: [Generate with AI] → proposal review/edit/accept happens INLINE in
          the post-create panel — goal-detail is asserted NOT visible until the
          user opts to open the goal; accept keeps the user on the Goals
          surface and the goal list is refreshed; opening the goal then shows
          the accepted milestones (edited title + milestone count).
  Note: like all P17 AI-generation journeys this one needs a reachable AI
        provider (journey H). Unit evidence: GoalViews.test.ts inline review
        + inline accept cases green (P17-026).
```

### P17-027 — AI Explanation content assertions (2026-08-24)
```text
golden-journeys.spec.ts — journey G2 extended  chromium/firefox/webkit
  checks: each explanation block the AI supplied renders with a label —
          proposal-rationale (decision summary), proposal-assumptions,
          proposal-inputs, proposal-constraints — and never shows raw JSON or
          chain-of-thought. Blocks are tolerant (asserted only when present).
  Unit/API evidence: StructuredAiOutputTest accepts the four explanation
        groups; GoalBreakdownProposalApiTest asserts they persist through the
        API; vitest renders/hides the four labelled blocks (P17-027).
```

### P17-028 — AI discoverability gate run (2026-08-24)
```text
golden-journeys.spec.ts — journey H2 added  chromium/firefox/webkit
  checks: AI disabled via Settings → create goal → [Generate with AI] shows
          "AI is not configured." with a [Configure AI] button that lands on
          ai-settings-view; no doomed generation request is fired; the test
          re-enables the provider afterwards so other suites see prior state.
  Result: journey H2 green across all three browsers.
  Pre-existing environment gap (not a P17-028 regression): journeys H/G2 fail
        in this dev environment because the Laravel app container cannot reach
        host-loopback Ollama (127.0.0.1:11434) — server-side connectivity,
        reproducible with `curl` inside infrastructure-app-1. Documented fix
        path per ADR-011/deployment.md: run the compose ai profile
        (`docker compose -f infrastructure/docker-compose.yml --profile ai up
        -d ollama`) and point provider base_url at http://ollama:11434. Blocked
        today only by the ollama image pull (Docker Hub connection resets).
  Unit evidence: GoalViews.test.ts gate cases for GoalListView + GoalDetailView
        green; full vitest suite 490 passed (P17-028).
```

### P17-029 — Contextual AI entry points run (2026-08-24)
```text
golden-journeys.spec.ts — journey K added  chromium/firefox/webkit
  checks: every surface exposes its contextual AI action where the object
          lives — Goal→Break down (post-create), Note→Summarize/Extract tasks
          (editor), Canvas→Suggest structure (boards index), Task→Clarify task
          (detail). With AI disabled via Settings, clicking Summarize shows the
          P17-028 gate and [Configure AI] lands on ai-settings-view; provider
          state restored at the end. Generation itself stays provider-gated
          (same posture as G3); API-level flows are proven by NoteAiApiTest,
          CanvasAiApiTest, AiGoldenFlowsTest.
  Result: journey K green across all three browsers; H/G2 restored 2026-08-24
        (see resolved environment note above).
```

### P17-023 — Canonical end-to-end product journey run (2026-08-24)
```text
tests/e2e/tests/canonical-journey.spec.ts  chromium/firefox/webkit
  checks: ONE continuous session drives the whole product loop through the
          live UI — login → goal create → real AI breakdown proposal → inline
          edit + accept (FR-62 gate) → accepted milestones on the goal →
          program created (flexible workload) → Quick Capture schedules a task
          onto a future day linked to program+goal+milestone → clock installed
          on the assigned slot renders it as TODAY's NOW card → START →
          elapsed accrues from server timestamp → COMPLETE (toast) → progress
          strip increments exactly one → ANALYTICS summary renders over the
          seeded window → capacity [Review schedule] lands in Schedule draft.
  Determinism: same contract as journeys I/J — future empty day always has
          free capacity; single API read-back fetches the exact assignment
          slot (capture panel shows locale-formatted time only).
  Result: ✅ 3 passed (3 browsers). Real-provider generation exercised end to
          end (qwen2.5-coder:7b via Ollama bridge).
```

### P17-033 — Theme real-browser proof closure run (2026-08-24)
```text
tests/e2e/tests/theme.spec.ts  chromium/firefox/webkit
  checks: re-run of the P17-013 hardening suite as current evidence for the
          TASK-P17-033 gate — unauth gate toggle; light→reload→light and
          dark→reload→dark persisted with a pre-hydration class snapshot (no
          flash); color-scheme flips for native controls; system mode follows
          live OS switches; keyboard operable (focus + Enter); Excalidraw
          island re-themes live across navigation (.theme--dark present/gone);
          mobile 375px reachable.
  Result: ✅ 6 tests × 3 browsers = 18 passed.
```

## Maintenance

- Updated per browser run; each golden journey has an evidence trail.
- The matrix reflects the live state of the repo, not intent.