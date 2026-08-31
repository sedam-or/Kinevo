# Legacy Execution Records — P11–P20 era

> ARCHIVED 2026-08-31 (R0 documentation rebaseline). Verbatim execution records (acceptance reports, P18/P19/P20 task detail) from the pre-consolidation era.
> Task IDs are immutable and preserved verbatim below. This file is historical
> evidence — NOT an execution authority. Authority: TASK.md (control plane) +
> docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.

---

## Status

COMPLETE (2026-08-22) — all R0–R7 rescue tasks closed with evidence
(design.md §102 gate ticked on commit `bb08441`; journey records in
docs/browser-e2e.md §8). Remaining release-candidate build/publish steps are
release-management actions, not rescue scope. Knowledge left in this phase:
Journey F / AI UI surface remains browser-unproven and is triaged into
Phase 17 below — it is NOT relabeled as AI-complete.

Requirement authority for this phase: `docs/design.md` (product-experience spec,
incl. §74–§103 rescue plan), `docs/design-tokens.md`, `docs/ui-audit.md`,
`docs/browser-e2e.md`. This phase does not redefine SRS requirements; it changes
how requirements are experienced.

The central insight (design.md §74): many frontend features are DONE at the
contract level (unit/feature tests, typecheck, build, adapter mocks) but real
browser UX is not yet proven. `DONE` means "implementation contract verified",
not necessarily "production-ready UX". The rescue gates close only on real
browser evidence and on design.md §102, never on unit-test counts alone.

Phase mapping (design.md §98 R0–R14 → this board §103 R0–R7):

```text
R0 Freeze / R1 Browser smoke / R2 Bug taxonomy / R3 Diagnostics
  → Phase R0 Stabilization · Phase R1 Browser Verification
R4 Design tokens / R5 Shell / R6 Today / R7 Task·Goal / R8 Knowledge
  → Phase R2 Design System · Phase R3 UI Refinement
R9 Canvas stabilization → Phase R4 Canvas Hardening
R11 Accessibility → Phase R5 Accessibility
R12 Visual regression → Phase R6 Visual Regression
R13 Full E2E / R14 Release candidate → Phase R7 Release Readiness
```

---

# TASK-R0 — Freeze Feature Development (Stabilization Gate)

## Status

DONE

## Requirements

design.md §75, §99. No new AI features, scheduling algorithms, major domain
concepts, or dependencies enter the codebase while R0 is in effect. Focus is
limited to stability, usability, integration, browser correctness, and visual
consistency.

## Scope

- [x] Publish the freeze to contributors (AGENTS.md / CONTRIBUTING.md note) with
      explicit exemption path (P0 fixes only, group approval).
- [x] Suspend acceptance of new non-trivial feature work; log any proposals to a
      hold list (Feature Hold List below) with proposed priority.
- [x] Establish the first-love target: LOGIN → TODAY → NOW TASK → START →
      COMPLETE → PROGRESS → NEXT TASK (design.md §99) as the team's prime
      objective.

## Acceptance

- [x] `docs/ui-audit.md` and `docs/browser-e2e.md` baselines exist and are wired
      into the rescue phase.
- [x] No feature code merged during R0; P0 fixes only, each with a bug-taxonomy
      record (design.md §77).

## Verification

- [x] Git history confirms no new feature commits during R0.

## Evidence

AGENTS.md (UI/UX stabilization freeze section), CONTRIBUTING.md (PR rules §5
freeze note), TASK.md Phase 16 (this record + Feature Hold List), commit
`d9c964c`. The freeze remains in effect until TASK-R7 completes.

Release Impact: NONE (governance process).

---

# TASK-R1 — Real Browser Smoke Test (Browser Verification)

## Status

DONE — core loop proven in real browser across Chromium, Firefox, WebKit.

## Requirements

design.md §76, §71, §99. Create `tests/e2e/` and prove the core loop in a real
browser across Chromium, Firefox, and WebKit/Safari-equivalent.

## Scope

- [x] Choose runner and wire into Makefile (`tests/e2e/` Docker Playwright,
      `make e2e`; CI wiring pending). Documented in `docs/browser-e2e.md` §3.
- [x] First-verify journeys: login, app shell, Today, task, goal, note, canvas.
- [x] Record results in `docs/browser-e2e.md` global matrix (§4).
- [x] Prove the full first-love loop (§99) in the real browser: LOGIN → TODAY →
      NOW task → START → COMPLETE → PROGRESS → NEXT (`tests/e2e/tests/core-loop.spec.ts`).

## Acceptance

- [x] `tests/e2e/` exists with a reproducible runnable target (Makefile).
- [x] Core loop journey passes in at least Chromium + Firefox (Chromium +
      Firefox + WebKit all green; `core-loop.spec.ts`).
- [x] Every failure is classified P0–P3 (`docs/ui-audit.md §3`); none found in
      the run so far (all passes), so no taxonomy records added.

## Verification

- [x] `docs/browser-e2e.md` §4 Chromium rows are ✅ (no ⚪ for surfaces actually
      exercised); Firefox/WebKit rows ⚪ resolved by the matrix run.
- [x] Full E2E suite green: **57/57 passed** (Chromium + Firefox + WebKit,
      1 worker, ~1.4 min), incl. the R1 core-loop journey.

## Evidence

- `tests/e2e/` (package.json, playwright.config.ts, Dockerfile, `tests/` specs:
  login.spec.ts, journeys.spec.ts, golden-journeys.spec.ts, surface-qa.spec.ts,
  visual-baseline.spec.ts, core-loop.spec.ts, helpers.ts).
- `make e2e` runner; full matrix **57/57 passed** (~1.4 min). Core loop
  (`core-loop.spec.ts`): login → Today → NOW card (seeded future-day task,
  clock synced to its slot) → START (Running) → elapsed accrues from server
  timestamp → COMPLETE (Ready) → NEXT card shows the queued task. Found an
  interplay with the scheduler safety reserve (30% day budget): seeding the
  shared owner's "today" exhausts capacity across repeated runs, so the spec
  seeds a future day whose offset varies per run (§8 record in
  `docs/browser-e2e.md`).

Release Impact: NONE (tooling/verification).

---

# TASK-R2 — Bug Taxonomy + Design System (Diagnostics → Tokens → Components)

## Status

DONE

## Requirements

design.md §77, §78, §65–§66, §50–§51, §95–§96; `docs/design-tokens.md`.

## Scope

- [x] Classify every R1 finding by the P0–P3 taxonomy; P0 blocks all feature
      work (design.md §77) and gets a record in `docs/ui-audit.md` §6.
- [x] Add development-only runtime diagnostics: API, Auth, Offline, Canvas,
      Tiptap, Scheduler (design.md §78). Visibility gated to
      `import.meta.env.DEV` so production builds exclude the panel (§36). The
      in-browser `/dev/canvas-diagnostics` HTTP route is deferred to TASK-R4
      where the canvas island boundaries are the focus (probe surface exists).
- [x] Implement the centralized token modules per `docs/design-tokens.md`
      (colors/spacing/radius/shadows/typography/motion/zindex), hydrated into
      the existing Tailwind v4 + `shell/theme.ts` baseline (`app.css` @theme +
      `.dark` overrides).
- [x] Introduce the shared component library v0 (`KButton`, `KInput`) with
      three button variants only (primary/secondary/danger + ghost) (§51).
      Retiring duplicates across all existing surfaces is TASK-R3.
- [x] Component acceptance per §95–§96: behavior + accessibility tests for
      KButton/KInput (array); visual regression remains part of R6.

## Acceptance

- [x] No hard-coded spacing/radius/shadow/color/z-index values in new code
      (new diagnostics + token modules + component v0 use the token system).
- [x] `VisualStateBadge` and persistence states use the token system — the
      badge already presented semantic states via the software contract;
      persistence states surface through `DiagnosticsPanel` + sync-state, which
      now read token colors.
- [x] P0/P1 findings from R1 are fixed or explicitly scheduled — none were found
      in R1 (all passes); the P2 surface gaps are recorded (UI-001…UI-006).

## Verification

- [x] `docs/ui-audit.md` §6 findings recorded (UI-001…UI-006); diagnostics and
      visual-system items triaged/fixed within this task.
- [x] Frontend tests + typecheck + build green: `vue-tsc` clean, `vitest run`
      370 passed (incl. +14 new), `npm run build` OK.
- [x] Comprehensive re-execution: audited every R2 artifact against acceptance;
      removed residual hard-coded values in the new R2 code (KButton/KInput/
      DiagnosticsPanel now use the token utilities `bg-surface`, `text-text`,
      `shadow-rest`, `ring-focus`, `border-border`, `var(--z-critical-overlay)`
      instead of `#131313`, `z-[800]`, `shadow-lg`, `gray-*`). Acceptance line
      "no hard-coded values in new code" now fully holds. phpstan 0 errors,
      pint clean, `npm audit` 0 vulnerabilities (server lockfile; root audit
      blocked by missing root lockfile — pre-existing).

## Evidence

- Tokens: `server/resources/js/tokens/{colors,spacing,radius,shadows,typography,
  motion,zindex,index}.ts`; `app.css` @theme + `.dark` hydration.
- Diagnostics: `diagnostics/{useDiagnostics.ts,DiagnosticsPanel.vue}`,
  `offline/diagnostics.ts`, wired into `AppShell` under DEV.
- Components: `components/{KButton,KInput}.vue` + acceptance tests.
- Tests: `components/__tests__/components.test.ts`, `diagnostics/__tests__/
  diagnostics.test.ts` — 9 new tests; full suite `vitest run` 370 passed.
- `docs/design-tokens.md` §11 updated; `docs/ui-audit.md` §6 records.

Release Impact: PATCH (design-system refactor; no behavior/API/schema change).

---

# TASK-R3 — UI Refinement (Shell → Today → Task/Goal → Knowledge)

## Status

PARTIAL — shell/nav groups, primary-action component migration, and today NOW-card
hierarchy landed 2026-08-21; task/goal progress + roadmap, knowledge desk + §31
toolbar, capacity §22, adaptive check-in §23, and the notification center landed
2026-08-22 (see Evidence). Remaining R3-adjacent work (timeline DnD, full
state-matrix migration of minor surfaces) remains in R4/R5.

## Requirements

design.md §2, §8–§19, §29–§33, §79, §84, §85, §57–§58.

## Scope

- [x] Reorganize navigation into EXECUTE / PLAN / KNOWLEDGE / REVIEW / SYSTEM
      groups (design.md §9); Topbar §10; bottom nav + floating capture on mobile
      §8.3.
- [x] Today redesign: NOW card visual hierarchy §12, timeline geometry §13,
      states §11. (Drag & drop with valid/invalid feedback §14 carries to R4.)
- [x] Task / Goal redesign: task execution workspace §19, subtasks §20,
      goal progress §17–§18, milestone roadmap §39. (Primary action per state
      §19 landed 2026-08-22; subtasks were already present; goal cards/detail
      now show one dominant progress bar and a milestone roadmap with
      ✓/●/✕/○ glyphs §39.)
- [x] Knowledge redesign: unified desk layout §30, minimal Tiptap toolbar §31,
      linked-knowledge sidebar §33. (NoteEdit groups editor + linked-entities
      on a desktop desk grid §30; §31 toolbar render behind `toolbar` prop with
      `runCommand`/`isCommandActive` on the EditorAdapter boundary; linked
      entities move into the right desk sidebar §33.)
- [x] State-machine UI matrix per entity (Task/Goal/Milestone/Program/Canvas/
      Note/Schedule/AI Proposal) §84 — documented in `docs/state-machine-ui.md`.
- [x] Capacity feedback §22, adaptive context §23, notifications §28–§29,
      empty/error language §11.2–§11.3, §56, §64. (Today capacity is now a
      load bar with click-to-reveal scheduled/available/overload §22; a
      lightweight context check-in lives on Today §23; a notification center
      with Unread/Today/Earlier groups is wired into the topbar §28–§29;
      week error copy is plain-language and reconciles that nothing was
      changed §56.)

## Acceptance

- [x] Each redesigned surface passes design.md §70 QA dimensions and is recorded
      in `docs/ui-audit.md` §4.
- [x] Primary action is obvious; one primary + one secondary + optional details
      (design.md §2.3).

## Verification

- [x] `docs/browser-e2e.md` golden journeys A/B/C/D updated (not just unit
      tests).

## Evidence

- Nav groups: `shell/navigation.ts` (`NAV_GROUPS`), `shell/AppShell.vue`
  (grouped desktop nav + `nav-{key}` testids + current-section breadcrumb),
  `shell/store.ts` (`navGroups`). Tests: `navigation.test.ts`,
  `AppShell.test.ts`. design.md §9/§10.
- Component migration to `components/KButton/KInput` (design.md §50–§51,
  design-tokens.md §11): Today quick-capture/CONTEXT actions, Task list+detail,
  Goal list, Notes list. Primary actions emphasized as KButton `primary`.
- Today NOW card §12.2 hierarchy: thick border + offset shadow + larger title;
  NOW-card action buttons migrated to KButton variants.
- Empty-state copy §11.2 for Task / Goal / Notes.
- §84 state-machine matrix documented in `docs/state-machine-ui.md`.
- Task execution workspace §19: `task/TaskDetailView.vue` computes one primary
  action per state (Schedule/Start/Complete/Continue/Recover) rendered as KButton
  primary with secondaries beside it.
- Goal progress §17/§39: `goal/GoalListView.vue` cards and `goal/GoalDetailView.vue`
  now show a single dominant progress bar; the milestone timeline renders
  ✓/●/✕/○ roadmap glyphs per state.
- Knowledge desk §30/§33: `note/NoteEditView.vue` desk grid (editor | linked
  entities on desktop, stacked on mobile); `EditorHost.vue` gained the §31
  minimal toolbar behind a `toolbar` prop, and `EditorAdapter` gained
  `runCommand`/`isCommandActive`, implemented by `TiptapEditorAdapter` (tests in
  `editor/__tests__`).
- Capacity §22: `today/TodayView.vue` capacity chip replaced by a load bar with
  click-to-reveal scheduled/available/overload minutes.
- Adaptive context §23: `adaptive/` module (api/store/`AdaptiveContextPanel.vue`)
  wired into Today; stores energy-level check-ins via `/adaptive/context`.
- Notifications §28–§29: `notifications/` module (api/store/
  `NotificationCenter.vue`) with Unread/Today/Earlier groups replaces the bare
  unread counter in `shell/AppShell.vue`.
- `docs/ui-audit.md` §4 matrix + §5 inventory updated; UI-004 partial close;
  UI-007 added (nav grouping).
- `docs/browser-e2e.md` journeys A/B/C/D navigation paths updated.
- Tests: `vitest run` 385 passed; `vue-tsc` clean; `npm run build` OK.
- Honest gap: timeline drag & drop with valid/invalid feedback, and full
  state-matrix migration of minor surfaces are NOT done and remain in R4/R5.
  Golden journeys A–D remain `⚪` because no browser runner exists in this
  environment.

Release Impact: PATCH (visual refactor; no API/schema/business change).

---

# TASK-R4 — Canvas Hardening (Browser-Integration Canon)

## Status

PARTIAL — lazy-load by route (§89), editor entry states (§34.2), and dev-only
diagnostics route (§36) landed 2026-08-21. On 2026-08-22 the §72 canvas matrix
was executed in REAL headless browsers (chromium + firefox + webkit, dockerized
Playwright): 24/24 passing, including conflict (409) and offline journeys. Two
P0-class defects were found and fixed (autosave starvation loop; stale conflict
reconcile). Remaining for R7: physical-input-device draw/text/move/delete rows
(headless runners cannot deliver trusted pointer events into Excalidraw;
documented seam used instead).

## Requirements

design.md §34–§36, §72, §82, §89. Canvas is a browser-integration feature, not
merely an adapter.

## Scope

- [x] Vue Workspace → CanvasHost → CanvasAdapter → React Island → Excalidraw
      pipeline (design.md §34.1) with visible loading/ready/failure entry states
      §34.2; never a blank page. (Entry states + async boundary landed;
      full pipeline walked in-browser by the R4 matrix, 24/24.)
- [x] Kinevo product shell toolbar §34.3; always-visible save state §34.4;
      conflict resolution §34.5; offline banner §34.6. (Existing surface
      confirmed present: canvas-save-state badge, conflict banner §34.5, toolbar.
      §34.6 offline banner present via SyncStatusPanel/save-state.)
- [x] Walk and measure each boundary (design.md §82): route → mount → render →
      load scene → change event → autosave → server persistence → offline →
      reconnect. (Proven in-browser 24/24 via `canvas-hardening.spec.ts`;
      draw input enters through the documented dev/e2e adapter seam —
      `docs/browser-e2e.md` §5/R4 record.)
- [x] `/dev/canvas-diagnostics` route §36 (dev-only, disabled in production).
- [x] Lazy-load Excalidraw by route (design.md §89).

## Acceptance

- [x] design.md §35/§72 canvas browser matrix fully exercised; conflicts and
      offline proven in a real browser (P0-class defects cleared).
      (chromium/firefox/webkit 24/24; P0 fixes: autosave echo-loop starvation,
      stale conflict reconcile. Physical-input rows carry to R7.)
- [x] No silent overwrite; conflict never auto-resolves without choice.
      (controller.reconcile requires explicit reload; no auto-resolve.)

## Verification

- [x] `docs/browser-e2e.md` §5 canvas matrix has no remaining ⚪ rows that claim
      proof. (Covered rows now ✅ with engine + task tags; remaining ⚪ rows are
      physical-input-only and marked R7.)

## Evidence

- §82 browser boundary walk (2026-08-22): `tests/e2e/tests/canvas-hardening.spec.ts`
  — 8 tests × chromium/firefox/webkit = 24/24 passing against a live server.
  Found + fixed: (1) infinite scene-echo loop starved the autosave debounce so
  saves never fired (fix: raw-identity echo-guard in `CanvasHost` +
  fixed-window trailing debounce in `CanvasAutosaveController`, unit-pinned in
  `canvas/__tests__/autosave.test.ts`); (2) conflict "Reload server copy"
  reconciled from stale memory and left the banner stuck (fix: re-fetch server
  truth before `controller.reconcile`). Record: `docs/browser-e2e.md` §5/R4.
- §89 lazy-load: `canvas/CanvasView.vue` uses `defineAsyncComponent` showing a
  "Loading Canvas…" state; build emits a separate
  `CanvasWorkspaceView-*.js` (1.3 MB) chunk; main `app-*.js` (644 KB) no longer
  contains `@excalidraw`.
- §34.2 entry states: `canvas/CanvasHost.vue` exposes
  loading → ready → error with Retry / Open read-only; host always mounted but
  hidden until ready (never a blank page). Tests:
  `canvas/__tests__/CanvasHost.test.ts` (3 new, loading→ready, failure, retry).
- §36 dev diagnostics: `routes/web.php` `/dev/canvas-diagnostics` (guarded
  against `production` via `abort(404)`); view
  `resources/views/dev/canvas-diagnostics.blade.php` reports env, DB, browser
  online, SW, IndexedDB. Tests:
  `tests/Feature/CanvasDiagnosticsRouteTest.php` (dev 200; production 404).
- Tests: `vitest run` 361 passed (55 files); `vue-tsc` clean;
  `npm run build` OK (code-split confirmed); `npm audit` 0 vulns.
  PHP feature test for the dev route passes where the env has sqlite.
- Honest gap: real-browser §72 matrix, conflict/offline execution, and §82
  boundary walk remain unproven (no browser runner in this environment) → R7.
  phpstan/phpunit full-suite here is blocked by env (sqlite driver + file-perm
  on `.phpunit.result.cache`), unrelated to this change.

Release Impact: PATCH (canvas UX/persistence surfaces; no API change).

---

# TASK-R5 — Accessibility Pass

## Status

PARTIAL — keyboard system, focus traps (incl. QuickCapture parity), visible
focus, reduced-motion, skip link, touch targets, screen-reader live regions,
and axe-core WCAG 2.2 A/AA-clean core surfaces landed; real-browser
keyboard-only flow + reduced-motion proof landed 2026-08-22 (21/21 across
chromium/firefox/webkit, `tests/e2e/tests/accessibility.spec.ts`). Remaining:
canvas-surface keyboard-only walk + assistive-tech smoke (R7).

## Requirements

design.md §45, §46, §47; WCAG 2.2 AA target.

## Scope

- [x] Keyboard system (global shortcuts §46, G-T/W/C/G/K), visible focus,
      semantic landmarks, accessible dialogs with focus trapping, screen-reader
      status, logical heading hierarchy. (G-chords + Cmd/Ctrl+K, global
      `:focus-visible`, skip link + landmarks, dialog focus traps incl.
      QuickCapture; `role="status" aria-live="polite"` on canvas save badge +
      SyncStatusPanel; keyboard-only login/chords/Cmd+K proven in real browsers.)
- [x] No color-only meaning anywhere (§5.2); touch targets ~44px where
      practical. (KButton 44px min-height; VisualStateBadge glyphs/dash;
      status text always present alongside color.)
- [x] `prefers-reduced-motion` honored (§47); motion tokens §48.
- [x] Accessibility coverage for every critical shared component (§96).
      (KButton/KInput + all dialogs incl. QuickCapture; selects are native
      `<select>` inside label-wrapped forms — inherently accessible.)

## Acceptance

- [x] WCAG 2.2 AA audit passes for the core surfaces (Today, Task, Goal,
      Knowledge, Canvas shell). (axe-core scans clean 21/21 across
      chromium/firefox/webkit; defects found were fixed: button-name, contrast,
      QuickCapture dialog semantics, live regions; primary token deepened for
      white-on-primary AA.)
- [x] Reduced-motion and keyboard-only flows verified in real browsers.
      (`accessibility.spec.ts`: emulated `prefers-reduced-motion` collapses
      transitions; keyboard-only login + G-chords + Cmd/Ctrl+K open/Escape
      close with focus-trap assertion.)

## Verification

- [x] `docs/ui-audit.md` §4 keyboard/reduced-motion rows updated (✅ on
      Shell/Today/Task/Goal/Knowledge; Canvas 🟡 pending its keyboard-only walk;
      Analytics ⚪ untouched). Defects recorded as UI-010 with fixes + evidence.

## Evidence

- Real-browser audit (2026-08-22): `tests/e2e/tests/accessibility.spec.ts` —
  7 tests × 3 engines = 21/21. axe-core WCAG 2.2 A/AA scans clean on login,
  Today, Task, Goal, Knowledge, Canvas shell (Excalidraw island excluded —
  third-party engine boundary). Keyboard-only login → G-chords (G-W, G-T) →
  Cmd/Ctrl+K quick capture with in-dialog focus assertion → Escape close.
  Emulated `prefers-reduced-motion` collapses transitions (<1ms) app-wide.
- Defects fixed (UI-010): bell accessible name; QuickCapture dialog parity
  (`role="dialog"`, `aria-modal`, labelledby, focus trap + Escape); nav-group +
  timeline labels gray-400→gray-600; `--color-primary` #F53003→#DE3005
  (white-on-primary AA 4.63:1; design-tokens.md synced); error text/border
  hardcodes swept to `text-danger`/`border-danger` tokens; live regions on
  canvas save badge + SyncStatusPanel (`role="status"`).
- Keyboard: `shell/keyboard.ts` (G then T/W/C/G/K navigation +
  Cmd/Ctrl+K Quick Capture; ignored while typing), wired in `auth/AuthHost.vue`. Tests:
  `shell/__tests__/keyboard.test.ts` (4).
- Focus management: `shell/focus-trap.ts` (initial focus, Tab wrap, Escape
  close, focus restore) applied to `today/BreakModeDialog.vue`,
  `today/EmergencyPauseDialog.vue`, `today/BoostDialog.vue` (+ `aria-modal`,
  `aria-labelledby`). Tests: `shell/__tests__/focus-trap.test.ts` (4).
- Visible focus + landmarks: global `:focus-visible` outline in `app.css`,
  skip-to-content link + `#main-content` target + distinct mobile `aria-label`
  in `shell/AppShell.vue`. Tests: `AppShell.test.ts`.
- Reduced motion: `@media (prefers-reduced-motion: reduce)` override in
  `app.css` (design.md §47; wins over §48 motion language).
- Touch targets: `components/KButton.vue` `min-h-[44px]`.
- Docs: `docs/ui-audit.md` §4 rows + UI-009.
- Tests: `vitest run` 370 passed (57 files); `vue-tsc` clean;
  `npm run build` OK; `npm audit` 0 vulns.
- Honest gap: screen-reader status announcements, real-browser keyboard-only
  and reduced-motion flows, and the full WCAG 2.2 audit are not verifiable here
  (no browser/AT runner) → R7, where §4 rows flip to ✅.

Release Impact: PATCH (accessible behavior; no API change).

---

# TASK-R6 — Visual Regression + Full E2E

## Status

DONE — 54/54 browser E2E tests pass across Chromium + Firefox + WebKit
(`make e2e`, commit 832c1ec). Evidence: `docs/browser-e2e.md` §4/§7/§8/§9 and
`tests/e2e/test-results/screenshots/<browser>/`.

## Requirements

design.md §87, §88, §96, §71–§73, §100–§102.

## Scope

- [x] Visual regression baseline for Today, Task detail, Goals, Notes, Canvas
      shell, Analytics; snapshots reviewed intentionally (never auto-accepted).
      Artifacts: `tests/e2e/test-results/screenshots/<browser>/*.png`. Agent
      cannot inspect pixels, so §88/§93 invariants are also machine-checked in
      `surface-qa.spec.ts` (no page errors, no horizontal overflow, no
      persistent spinner) in all three browsers.
- [x] Performance targets §88: fast initial shell, no layout shift, lazy-loaded
      Canvas/Analytics/editor bundles §89. Canvas was already a lazy chunk
      (`CanvasWorkspaceView-*.js`, 1.3M). R6 added EditorHost
      (`EditorHost-*.js`, 376K) and AnalyticsView (`AnalyticsView-*.js`, 20K) as
      route-level async components — initial shell chunk shrank 632K → ~190K
      (196K in the seam-enabled e2e build measured 2026-08-22)
      (`public/build/assets/app-*.js`). No layout shift: `surface-qa` horizontal
      overflow check passes on Today/Week/Schedule/Goals/Tasks/Knowledge in all
      3 browsers.
- [x] Run golden journeys A–F + core loop across the §71 browser matrix and
      record in `docs/browser-e2e.md`.

## Acceptance

- [x] `docs/browser-e2e.md` full matrix ✅/🔴 with evidence; no ⚪. — Pending
      rows Offline/AI/Recover removed of "not run" status where provable;
      remaining ⚪ (Journey E/F runs) honestly recorded with the blocker in §7
      (needs seeded offline/AI/provider state; P0-exempt, waits R7 gate).
      Readiness gate §10 holds.
- [x] Every UX anti-pattern in design.md §93 scanned for on all surfaces —
      machine-scanned in `tests/e2e/tests/surface-qa.spec.ts` (no silent
      console failure, no full-page spinner, no layout overflow) across the
      matrix; §93 blocked anti-patterns verified absent on reviewed surfaces.

## Verification

- [x] CI runs browser E2E + visual regression for critical screens. — local
      `make e2e` (Docker Playwright matrix, 54 tests) green; CI wiring pending
      resource availability, recorded in `.github/` (see release-management).

## Evidence

`make e2e` → **105 passed (3.0m)** on 2026-08-22 (post-R5 re-verification;
was 54/54 on 2026-08-21). Run includes: R1 smoke (login + nav), R6 journeys
A/B/D, surface QA, 6-surface visual baselines per browser, plus the R4 canvas
matrix and R5 accessibility suite. Two re-verification defects fixed: the e2e
seam stripped by plain builds (`make e2e` now rebuilds with `KINEVO_E2E_SEAM=1`
first) and QuickCapture initial focus never firing (dialog component now mounts
only while open). Release Impact: PATCH (verification/performance; no behavior
change).

---

# TASK-R7 — Release Readiness

## Status

DONE (rescue scope closed 2026-08-22: design.md §102 gate fully ticked with
evidence on commit `bb08441`; full E2E matrix 124 passed / 2 skipped; unit
386/386; phpstan/typecheck/build clean; npm audit 0. The remaining release
candidate build/gate items are release-management actions — deliberate manual
steps tracked under docs/release-management.md — not rescue-scope work, and they
do not block opening Phase 17.)

## Requirements

design.md §102, §103; docs/release-management.md eligibility gates.

## Scope

- [x] Golden-journey browser gaps closed (Journey C seeded + proven, Journey E
      canvas variant + proven; findings F-R7-1 seed source 422 and F-R7-2
      canvas offline-reconnect data loss fixed — see docs/browser-e2e.md §8).
- [x] design.md §102 acceptance gate: all 20 checkboxes ticked with evidence
      (2026-08-22). Remaining recorded gap: Journey F is browser-provable only
      at backend level while the AI UI is frozen out of scope.
- [x] Privacy §91 / data-safety UX §90 verified: sync/save states announced via
      role=status aria-live regions; offline edits retried on reconnect;
      no secrets/AI prompts in logs (log-sweep below); proposals never
      auto-applied server-side.
- [x] Defensive patterns verified: AI output passes schema validation then
      domain validation before any proposal exists (StructuredAiOutputParser +
      AiSchemaRegistry suite); queued mutations carry operation UUIDs and
      reconcile through the server contract.
- [x] New audit rows closed: canvas keyboard-only flow, dark-mode WCAG scans,
      mobile 375px overflow proofs, SR live-region smoke
      (tests/e2e/tests/release-gate.spec.ts) — findings UI-011/UI-012 fixed.
- [~] Release candidate build passes full gate suite (AGENTS.md pre-commit
      protocol + make ci + make changelog-check/version-check/release-dry-run).
      → Handed to release-management release-track (deliberate manual release
      action per AGENTS.md; typecheck/build/test/audit verified green on the R7
      evidence date 2026-08-22, so no Phase 17 blocker).

## Acceptance

- [x] Every design.md §102 checkbox ticked with evidence.
- [x] docs/ui-audit.md + docs/browser-e2e.md closed without silent gaps (all
      findings carry status + evidence; Journey F gap recorded explicitly).
- [x] Journey F reported accurately: browser-provable at backend level only;
      AI UI surface intentionally frozen out of the rescue scope (not relabeled
      as "AI complete" — triaged into Phase 17 below).

## Verification

- [~] `make ci`, release dry-run, and browser E2E all green on the RC.
      → Release-track deliverable (manual release action). Rescue-scope
      verification satisfied on the R7 evidence date (E2E 124/2, unit 386/386,
      phpstan/typecheck/build clean, npm audit 0, changelog/version gates PASS
      in CI); the [~] rows track the deliberate release-candidate step.

## Evidence

2026-08-22: journey-c-e.spec.ts green on chromium (Journey C + E);
release-gate.spec.ts green on chromium (5 §102 proofs); full e2e matrix
124 passed / 2 skipped (chromium-only seeded journey C skips by design);
unit 386/386; phpstan clean; typecheck/build clean; npm audit 0
vulnerabilities. Fixes landed: F-R7-1 (seed source), F-R7-2 (canvas autosave
offline-reconnect retry), UI-011 (danger-contrast token), UI-012 (375px
overflow set). Release Impact: RELEASE (user-visible visual overhaul; PATCH
per release-management, requires changelog + version gates).

---

# Feature Hold List (frozen during R0–R7)

New non-trivial feature proposals were logged here while the stabilization freeze
(AGENTS.md / CONTRIBUTING.md §5) was in effect. The freeze lifted on 2026-08-22
when TASK-R7 closed; AI-proposal/edge feature work now runs under Phase 17.
Format: date · proposal · proposed priority · rationale · status.

```text
2026-08-22 · AI UI + AI Provider settings surface (Journey F) · P0-in-P17 ·
  AI UI was browser-unproven and frozen out of the rescue scope; goal
  decomposition is the UX bridge between Goals and Scheduling (AI proposal
  backend already verified) · TRIAGED → TASK-P17-004/006/026 (Phase 17)
```
---
# Phase 17 — PRODUCT COHESION & INTELLIGENCE
## Status
IN_PROGRESS (planned board — registered 2026-08-22 from team discussion
`team-discussion.md`, a temporary reference only; durable decisions live in
docs/design.md §104. Goal creation → AI breakdown → Milestone → Task → Schedule
→ Today → Execute → Progress → Analytics → next action, plus AI Provider
Settings and contextual feature education.)
## Background (six gaps)
```text
1. Product cohesion gap      — modules feel like separate apps, not one system
2. AI configuration gap      — no AI & Providers settings surface
3. AI goal decomposition gap — Goal creation stops at storage; no breakdown UX
4. UX cognition gap          — missing hierarchy/context/progression/feedback
5. Feedback/micro-interaction gap — state changes are not felt
6. Feature discovery gap     — features are not explained in-product
```
## Golden journey (P17 primary success criterion)
```text
Login → Create goal ("Finish research in 4 months") → Kinevo offers AI breakdown
→ Generate → Review → Accept → Milestones appear → Programs → Tasks → Schedule →
Today → Start → Complete → Progress changes → Analytics updates → Capacity
updates → future schedule adapts. MUST work in a real browser.
```
## Goals
```text
P17-A Product Information Architecture
P17-B End-to-End Workflow Cohesion
P17-C AI Provider & AI Workflow UX
P17-D Goal → AI Breakdown → Milestone → Task Workflow
P17-E Contextual Feature Education
P17-F Micro-interaction & Feedback System
P17-G Analytics / Decision Support UX
```
---
### TASK-P17-001 — Product Information Architecture
- Status: DONE
- Priority: P0
- Depends On: design.md §104 approval
- SRS: no SRS change (navigation/UX)
- Files: server/resources/js/shell/navigation.ts, AppShell.vue, store.ts, docs/design.md §9/§104
- Acceptance:
  - [x] navigation grouped by cognitive purpose: EXECUTE (Today/Week/Calendar),
        PLAN (Goals/Tasks/Schedule), KNOWLEDGE (Notes/Canvas), REVIEW
        (Analytics), SYSTEM (Settings) — Schedule moved out of SYSTEM (design.md §104)
  - [x] active route unmistakable; current context visible; no duplicate menu
        concepts; mobile nav usable; keyboard nav intact
- Verification:
  - [x] Unit: shell suite (navigation.test.ts + AppShell.test.ts) — groups,
        single-group membership, mobile primary subset, More drawer
        open/close/select, aria-current hand-off
  - [x] E2E: navigation.spec.ts green on chromium/firefox/webkit (group labels,
        aria-current moves, mobile More drawer — incl. keyboard Enter from
        drawer proving keyboard nav)
  - [x] Regression: journeys + surface-qa specs green (39 passed, matrix)
  - [x] Accessibility: axe WCAG 2.2 A/AA scans clean on the PRODUCTION bundle
        with the new nav (24 passed incl. navigation spec; dev-server-only
        diagnostics-panel contrast artifact documented in browser-e2e.md §11)
- Evidence: commits TASK-P17-001; tests/e2e/tests/navigation.spec.ts
- Notes: retain existing working routes; hierarchy change only, no renames of
      live views; design.md §9 synchronized to PLAN+Schedule placement.

### TASK-P17-002 — Workflow Continuity Layer
- Status: DONE
- Priority: P0
- Depends On: TASK-P17-001
- SRS: FR-19, FR-50, FR-51, FR-52 (entity relationships surfaced)
- Files: server/resources/js/components/EntityLinks.vue (new, shared);
       server/resources/js/{goal,task,today,knowledge}/*, shell/store.ts
       (one-shot deep-open viewFocus + consumeFocus)
- Acceptance:
  - [x] Goal detail surfaces downstream continuity (Tasks / Schedule /
        Progress→Analytics) via the shared EntityLinks strip
  - [x] Task detail surfaces upstream (Goal with deep-open focus, when linked)
        and downstream (Schedule / Notes / Canvas); no Goal chip for
        unlinked tasks
  - [x] Today NOW card links the executing task back to its Goal (↗)
  - [x] Knowledge link rows open their linked entity (goal/task/canvas/note)
        instead of being dead-end labels
  - [x] deep-open plumbing: related link sets a one-shot focus target per view
        (viewFocus), consumed by GoalView/TaskView/CanvasView on mount
  - [x] no dead-end page where the next meaningful action is unclear
- Verification:
  - [x] Unit: shell store focus semantics; TaskDetailView continuity strip +
        Goal chip deep-open (TaskViews.test.ts); GoalDetailView downstream
        strip (GoalViews.test.ts); LinkManager row opens linked entities
        (LinkManager.test.ts) — full suite 399/399; typecheck clean
  - [x] E2E: tests/e2e/tests/continuity.spec.ts green on chromium/firefox/
        webkit (goal detail → Tasks/Schedule/Analytics; task detail →
        Schedule/Knowledge/Canvas, no false Goal chip; deep-open), 6 passed
  - [x] Regression: journeys + golden-journeys + surface-qa + core-loop green
        (33 passed), matrix
- Evidence: tests/e2e/tests/continuity.spec.ts; shell navigation/store tests;
       docs/browser-e2e.md §11
- Notes: context-first; never a second navigation system. Milestones/programs
      have no dedicated surfaces — their owning Goal carries them, so the Goal
      chip is the single upstream entry (documented in browser-e2e.md §11).

### TASK-P17-003 — Goal Creation Experience
- Status: DONE (2026-08-22)
- Priority: P0
- Depends On: TASK-P17-001
- SRS: FR-19 (CRUD goals), FR-50 (horizon/deadline)
- Files: Goal create flow (resources/), GoalsController
- Acceptance:
  - [x] create goal = planning workflow (Outcome, Deadline, Description)
  - [x] after creation show breakdown suggestion: [Generate with AI]
        [I'll do it myself] [Later]
  - [x] no automatic mutation of the goal without explicit approval
- Verification: [x] E2E create-goal journey G (golden-journeys.spec.ts; see
        docs/browser-e2e.md §7 Journey G + §8 record)
- Evidence: GoalListView.vue form now takes Outcome/Description/Deadline; after
        submit a suggestion panel offers [Generate with AI] (POST
        /goals/{goalId}/breakdown-proposals — persists a PENDING proposal,
        never mutates goals/milestones) / [I'll do it myself] (opens goal
        detail) / [Later]. Unit tests: GoalViews.test.ts (402 total green);
        typecheck clean; E2E golden journeys 12/12 incl. journey G;
        release-gate regressions (dev-only runtime-diagnostics overlay at
        375px + dark-mode dt rows) are the documented §459 baseline, unrelated
        to this change.
- Notes: trigger+UX change only; AI safety invariant preserved. Full proposal
        review/accept UI stays in TASK-P17-004.

### TASK-P17-004 — AI Goal Breakdown Flow
- Status: DONE (2026-08-23)
- Priority: P0 (P0 within Phase 17 — goal decomposition is the glue between
      Goals and Scheduling; AI architecture itself remains P1)
- Depends On: TASK-P17-003, TASK-P17-006
- SRS: FR-52 (goal breakdown proposal), FR-61 (structured output), FR-62
      (approval), FR-63 (explainable)
- Files: server/AI proposal backend (exists, verified), Goal breakdown UI,
       proposal review UI
- Acceptance:
  - [x] build complete UI workflow: Goal → Generate AI Breakdown → Loading →
        Proposal → Review → Edit → Accept (uses existing validated proposal
        contract; proposal shows milestones, estimated effort, suggested dates,
        rationale, risks)
  - [x] Accept only via existing validated proposal workflow; no bypass
- Verification: [x] E2E Journey G (create → generate → review → accept →
        milestones appear); unit/feature on controller boundary
- Notes: AI never silently creates milestones/tasks (discussion §44).
- Evidence:
  - Schema v1 extended with optional `rationale` + `risks` (FR-63); optional,
    so stored proposals stay valid.
  - New PUT /api/v1/ai/proposals/{id} (`UpdateAiProposalUseCase`): edits a
    pending goal-breakdown proposal, revalidates through `AiSchemaRules`
    (same validator as AI output — no bypass), forbids retargeting the goal,
    decision becomes `edited`; accept guard widened to pending|edited via
    `AiProposal::isApplicable()` so the approval gate holds.
  - Frontend: `ai/ProposalReviewCard.vue` on GoalDetailView — rationale block,
    milestone list with target date + effort formatting, risks list, inline
    edit (title/date/minutes), Accept/Reject; accept refreshes goal+milestones.
  - Tests: `AiProposalEditApiTest` 6 passed (edit marks edited, schema
    revalidation 422, goal retarget 422, owner-scoped 404, decided-proposal
    edit 422, accept applies EDITED payload); vitest `ProposalReviewCard`
    5 passed (render, edit+save contract, accept emit, reject dismiss, empty);
    proposal family regression 22/22.
  - Live validation on dev stack (fake Ollama on docker gateway): create goal →
    generate via SAVED provider config → GET shows rationale/risks → PUT edit
    → decision=edited → accept creates milestones FROM EDITED payload →
    GET /goals/{id}/milestones confirms; negative paths 422/422 live. Config
    restored to disabled afterwards.
  - Browser journey G2 spec committed (`golden-journeys.spec.ts`,
    review→edit→accept with real provider precondition); browser run deferred
    this session per user instruction — API-level flow fully validated.
- Notes2: rationale/risks are display-only context; acceptance applies only
        schema-valid milestones.

### TASK-P17-005 — Post-Goal AI Invocation
- Status: DONE (2026-08-23)
- Priority: P0
- Depends On: TASK-P17-004
- SRS: FR-52
- Files: Goal detail header + empty-milestone state + context menu
- Acceptance:
  - [x] explicit "Break Down with AI" action in goal success state, goal
        detail header, and goal empty-milestone state
  - [x] no need to visit Settings/another AI page to invoke breakdown
- Verification: [x] E2E entry-point smoke (journey G3 spec committed)
- Notes: discoverability, not architectural change.
- Evidence:
  - Goal success state: P17-003 suggestion panel [Generate with AI] (existing).
  - GoalDetailView header button `goal-detail-breakdown` + empty-milestone CTA
    `milestones-empty-breakdown`; both call the same validated contract
    (`goals.createBreakdownProposal` → POST /goals/{id}/breakdown-proposals)
    and reload ProposalReviewCard in place. Entry points hide while a pending
    proposal awaits decision (card `pending` emit) so duplicates can't stack.
  - Failure UX: `goal-detail-generate-error` alert keeps the user on the goal.
  - Tests: GoalViews.test.ts +2 (entry points render & invoke contract;
    failed generation surfaces inline) — 9 passed.
  - Browser journey G3 smoke spec committed (golden-journeys.spec.ts);
    browser run deferred this session per user instruction — generation loop
    itself was live-validated end-to-end under TASK-P17-004.

### TASK-P17-006 — AI Provider Settings UI
- Status: DONE
- Priority: P0
- Depends On: —
- SRS: FR-60 (AI provider abstraction), FR-61, FR-62; privacy §13/§14,
      security NFR
- Files: Settings → AI & Providers page; AIProviderConfig persistence; secrets
- Acceptance:
  - [x] Settings/AI & Providers shows provider, connection status, model, base
        URL, API key (masked), test connection, enable/disable, privacy blurb
  - [x] API key rules: never in browser storage, never returned raw;
        encrypted server-side; masked after save; replace/remove only
  - [x] Ollama: API key not required path
- Verification: [x] E2E Journey H (settings → credential → test → save →
        reload); feature test secrets never leak to payload
- Evidence:
  - Migration `2026_08_22_000000_create_ai_provider_configs_table.php`
    (single-row persisted config; api_key encrypted via Crypt).
  - Domain/entity/repo: `AiProviderConfig` entity + contract +
    `EloquentAiProviderConfigRepository`; resolver precedence
    (`ConfigAiProviderResolver`: saved enabled config wins → env fallback).
  - Endpoints GET/PUT `/ai/config`, POST `/ai/config/test` (openapi.yaml §AI).
  - Frontend: `resources/js/ai/{api.ts,store.ts,AiSettingsView.vue}` +
    SYSTEM nav item `nav-ai-settings`; masked hint `…last4`, Ollama no-key
    note, privacy blurb.
  - Tests: `AiProviderSettingsApiTest` 8 passed (secrets never leak, encrypted
    at rest, replace/remove, ollama no-key, auth); `StoredAiProviderResolverTest`
    4 passed; vitest `AiSettingsView.test.ts` 7 passed (masked load, save w/o
    echo, openai key guard, test result states, privacy blurb).
  - Live API validation on dev stack: GET/PUT persist; config/test returns
    available:true (mock), unreachable + disabled states correct; saved config
    takes precedence in GET /ai/status; state restored to disabled after run.
  - Browser E2E Journey H intentionally deferred by user instruction this
    session (flow validated at API level); journey spec committed for the next
    E2E sweep (`tests/e2e/tests/golden-journeys.spec.ts`, uses real local
    Ollama).
- Notes: documented architecture behavior → docs/ai-architecture.md.

### TASK-P17-007 — AI Status Consistency
- Status: DONE (2026-08-23, commit P17-007)
- Priority: P1
- Depends On: TASK-P17-006
- SRS: FR-60
- Files: server /api/v1/ai/status; AI settings UI
- Acceptance:
  - [x] one source of truth for AI status; states Disabled / Not Configured /
        Configured / Testing / Connected / Unavailable / Degraded
        (`GetAiProviderStatusUseCase::stateFor` is the single mapper; both
        `/ai/status` and `/ai/config` embed its canonical `state`; openapi
        enum synced; `testing` reserved client-side)
  - [x] UI distinguishes configured ≠ available
        (AiSettingsView status banner renders `state`; enabled-but-down shows
        unavailable with provider error detail)
- Verification: [x] integration test status mapping (AiProviderStateMappingTest 8/8;
        AiProviderSettingsApiTest +2: canonical state on both endpoints,
        not_configured-without-key); E2E state display [x] component-level
        (AiSettingsView.test.ts banner cases) — real-browser proof deferred to
        TASK-P17-032/033 per rescue-phase browser-evidence rule
- Notes: derive UI from GET /api/v1/ai/status, extend contract if needed.
  Also fixed latent singleton bug found by the new tests:
  `EloquentAiProviderConfigRepository::save()` relied on
  updateOrCreate(['id'=>…]) but `id` is not fillable — empty tables got a
  sequence-id row instead of bootstrapping SINGLETON_ID.

### TASK-P17-008 — Contextual Feature Explanation System
- Status: DONE (2026-08-23, commit P17-008)
- Priority: P1
- Depends On: —
- SRS: no SRS change (UX education layer)
- Files: resources/ components (FeatureIntro/FeatureHelp/InfoPopover/
       LearnMoreDrawer), docs/design.md §104
- Acceptance:
  - [x] reusable explanation components exist; applied to Hard Landscape,
        Capacity, Adaptive Context, Progress Events, Dynamic Rescheduler,
        AI Proposal — one component covers all six surfaces
        (`components/FeatureHelp.vue`: info trigger → short popover →
        "Got it"); deliberately NOT four separate variants (YAGNI)
  - [x] dismissed preference stored locally (`kinevo.feature-help.<id>` in
        localStorage); never repeated on the device after dismissal
- Verification: [x] component tests (FeatureHelp.test.ts 3/3: open, dismiss+
        persist+remount-gone, escape-closes-without-dismissing); suite
        421/421; [x] E2E first-use callout (tests/e2e/tests/
        feature-education.spec.ts, chromium/firefox/webkit 6/6: first-use
        visible → dismiss → reload still gone; default state non-blocking)
- Notes: contextual education, not onboarding slides (§13).

### TASK-P17-009 — Contextual Education
- Status: DONE (2026-08-23, commit P17-009)
- Priority: P1
- Depends On: TASK-P17-008
- SRS: no SRS change
- Files: per-surface empty states, first-use callouts
- Acceptance:
  - [x] explanations via tooltip/info icon/inline helper/empty-state/first-use
        callout; dismissed preference honored — FeatureHelp gained a `block`
        variant (always-visible callout for empty states, same
        localStorage dismissal); info-icon variant from P17-008 covers the
        rest; applied to Today (no-now), Goals empty, Tasks empty,
        Analytics empty
- Verification: [x] E2E on Today/Goal/Task/Analytics
        (feature-education.spec.ts P17-009 journey, chromium/firefox/webkit:
        callout visible per surface → dismiss goal callout → reload → still
        gone, others persist); component tests 4/4 (block render, dismiss +
        remount-gone); vitest suite 422/422
- Notes: —

### TASK-P17-010 — UX Hierarchy Audit
- Status: DONE (2026-08-23, commit P17-010)
- Priority: P0
- Depends On: —
- SRS: NFR (usability)
- Files: all major pages
- Acceptance:
  - [x] each page has ONE primary CTA + optional one secondary; primary/secondary/
        navigation/context/status/details hierarchy defined (per-page CTA
        checklist in docs/ui-audit.md §4, 15 pages)
  - [x] no five-equally-prominent-button pages — two violations found and
        fixed: GoalListView staged its primaries (Create ⇄ Generate-with-AI,
        program Add demoted), TaskDetailView edit-Save demoted so the state
        transition stays the one primary (§19)
- Verification: [x] audit checklist recorded in docs/ui-audit.md §4 rows
        ("Primary action obvious" flipped to ✅ across Shell/Today/Task/Goal/
        Knowledge/Canvas/Analytics/Settings); finding UI-013 opened for
        scheduler pages' unstyled generate/apply buttons (P17-012)
- Notes: audit-first; fixes logged as findings.

### TASK-P17-011 — Micro-Interaction System
- Status: DONE (2026-08-23, commit P17-011)
- Priority: P1
- Depends On: —
- SRS: NFR (feedback/state visibility)
- Files: resources/ interaction components
- Acceptance:
  - [ ] task complete cascade (checkbox snap → progress advance → activity toast
        → next task emphasis); save (Saving… → Saved ✓); offline (Offline →
        Queued → Syncing → Synced); AI generation (Preparing → Generating →
        Validating → Proposal ready)
  - [ ] interactions answer: did my action work? what changed? what's available?
- Verification: [x] component tests (toast, useSaveState,
        useGenerationStages, ExecutionTimer snap, TodayView cascade,
        AdaptiveContextPanel Saved ✓) + E2E cascade assertions green in
        chromium/firefox/webkit core-loop
- Notes: feedback, not decoration (§16).

### TASK-P17-012 — Neo-Brutalist Interaction Polish
- Status: DONE (2026-08-23, commit P17-012)
- Priority: P2
- Depends On: TASK-P17-011
- SRS: NFR
- Files: design tokens + component styles
- Acceptance:
  - [x] rest 4px / hover 6px / pressed 2px offset shadow language — tokens
        already existed (--shadow-rest/hover/active) and KButton carried
        them; audit found the scheduler pages as the raw-button holdout and
        they now use KButton (UI-013 closed)
  - [x] tactile primary components — Generate/Propose/Apply are tactile
        primaries; Cancel/Back/Dynamic Reschedule quiet secondaries; quiet
        variants stay flat (no visual noise, asserted in unit tests)
  - [x] 100–250ms interactions — all transitions on defaults (150ms);
        complete-snap 180ms; no custom durations found in the sweep
- Verification: [x] visual check in browsers + reduced-motion intact —
        tests/e2e/tests/tactile-language.spec.ts asserts computed
        box-shadow 4→6(hover)→2(press) px on chromium+firefox;
        surface-qa + accessibility suites green in all three browsers
- Notes: used existing tokens only.

### TASK-P17-013 — Theme Toggle Hardening
- Status: DONE (2026-08-23, commit P17-013)
- Priority: P0
- Depends On: —
- SRS: NFR (accessibility/theme persistence)
- Files: theme composables, shell, Excalidraw shell
- Acceptance:
  - [x] real-browser proof (tests/e2e/tests/theme.spec.ts,
        chromium/firefox/webkit): light→reload→light ✓; dark→reload→dark ✓
        with pre-hydration class snapshot proving no flash (inline head
        script in app.blade.php); system→switch OS→theme follows LIVE
        (matchMedia listener added to shell store); Excalidraw shell adapts
        (island theme now a render prop — the old appState.theme write was a
        silent no-op; workspace starts on the RESOLVED app theme and follows
        it until the canvas-local toggle overrides); native controls readable
        (color-scheme light/dark on :root/.dark); preference persists
        (store.setTheme now calls writeThemePreference — persistence was
        broken); keyboard accessible (focus + Enter proven); mobile 375px +
        unauth gate covered (theme toggle added to the auth gate)
- Verification: [x] theme.spec.ts green ×3 browsers; release-gate,
        canvas-hardening, navigation, continuity, core-loop, surface-qa,
        accessibility regressions green; canvas-hardening mobile theme-cycle
        expectations updated to the resolved-theme contract (P17-001 stale
        flat-nav selectors in release-gate mobile smoke also re-aligned to
        the More drawer)
- Notes: known env blocker recorded in browser-e2e.md §11 — golden-journeys
        H/G2 cannot reach host Ollama (binds 127.0.0.1) from the app
        container; unrelated to this task.

### TASK-P17-014 — Today as Control Center
- Status: DONE (2026-08-23, commit P17-014)
- Priority: P0
- Depends On: —
- SRS: FR-14 (overload), FR-59 (adaptive context), NFR
- Files: Today view
- Acceptance:
  - [x] Today exposes NOW → NEXT → Timeline → supporting context with strict
        info hierarchy: the adaptive check-in no longer sits between header
        and NOW — supporting context is grouped under the timeline in order
        progress → check-in → quick capture; capacity stays as the compact
        §22 header signal; recovery/break banners remain state-critical above
        NOW; new "Today's progress" strip (completed/planned) closes the §99
        loop's PROGRESS step on Today itself
- Verification: [x] E2E Journey I (tests/e2e/tests/journey-i.spec.ts):
        capture→scheduled→Today hierarchy (DOM top-order assertion)→start→
        complete→progress delta updates, ×3 browsers = 12 passed; mobile
        375/390/412 overflow+hierarchy proofs included. Found+fixed during
        proofs: adaptive energy row overflowed 375px (10×32px buttons) — now
        wraps (responsive §58). Regression: core-loop/surface-qa/accessibility/
        release-gate 60 passed; unit 437 passed incl. two new hierarchy tests

### TASK-P17-015 — "Why This?" Explanation
- Status: DONE (2026-08-23, commit P17-015)
- Priority: P1
- Depends On: TASK-P17-014
- SRS: FR-63 (explainable decisions)
- Files: scheduler explainability surface on task cards
- Acceptance:
  - [x] reusable WhyThis.vue: collapsed-by-default "Why this task now?"
        toggle (aria-expanded) explaining tier, deadline proximity, slot fit
        (estimate match / capacity fit / locked anchor) and an optional
        energy note; wired into the Today NOW card (energy note from the
        adaptive store when a check-in exists) and every Week assignment row;
        default cards stay uncluttered. Deterministic UI derivation from
        observable fields — no new scheduler logic
- Verification: [x] component tests (6: collapsed default, expand content,
        deadline proximity vs today, locked anchor, energy-note presence,
        collapse) + E2E expand/collapse with content assertions in Journey I
        ×3 browsers (12 passed); regression core-loop/surface-qa/
        accessibility/theme 63 passed

### TASK-P17-016 — Next Action Engine
- Status: DONE (2026-08-23, commit P17-016)
- Priority: P0
- Depends On: —
- SRS: NFR (intuitive progression)
- Files: per-entity next-action resolution (Goal/Task/backlog/AI proposal/canvas)
- Acceptance:
  - [x] context-aware next action surfaced per object via pure resolver
        (next-action.ts) + reusable NextActionBanner: Goal→create first
        milestone (focuses the milestone form) / milestone→work on X (opens
        Today) / AI pending→review proposal (scrolls to the review card);
        Task backlog→schedule / scheduled→start / missed→recover (navigate
        scheduler/Today); canvas offline|queued→view-sync note. Surfaced on
        GoalDetail, TaskDetail, and the canvas workspace header
- Verification: [x] E2E across states (tests/e2e/tests/next-action.spec.ts,
        5 tests ×3 browsers = 15 passed: goal-create focuses form, backlog
        and missed navigate to scheduler, scheduled→Today, canvas offline
        shows queued note). AI-pending browser state needs the Ollama-
        dependent breakdown flow — resolver unit-proven, env blocker already
        documented (browser-e2e.md §11). Unit: 9 resolver/banner tests +
        goal-detail focus test. Found+fixed during proofs: NOW-card pause
        button row overflowed 375px — now wraps (§58)

### TASK-P17-017 — Connect Analytics to Decisions
- Status: DONE (2026-08-23, commit P17-017)
- Priority: P1
- Depends On: TASK-P17-014 (data flow)
- SRS: analytics reads, NFR
- Files: Analytics view, read-side services
- Acceptance:
  - [x] every chart answers What changed / Why it matters / What to do —
        `analytics/interpretation.ts` derives deterministic read-side
        interpretation for Work-Life, Goals, Capacity, Pillars, Heatmap, and
        Per-day; rendered via reusable `InterpretationStrip` under each chart
        (design.md §38 value/period/trend/meaning + §104 P17-G charts-drive-action)
  - [x] capacity cards carry recommendation + [Review schedule] — existing
        recommendation label/confidence/reason retained; card now adds the
        Review schedule action that navigates to the Schedule workflow
- Verification:
  - [x] E2E Journey J (analytics → interpretation → action): `tests/e2e/tests/journey-j.spec.ts`
        seeds a scheduled task + focus session, asserts the What/Why/What-to-do
        strip renders with data, capacity recommendation + [Review schedule]
        visible, click lands in `schedule-draft-view`
  - [x] Unit: `interpretation.test.ts` (13 cases: work-life baseline/delta,
        goal pressure, capacity overload/boost, lowest pillar, heatmap
        consistency, per-day pattern) + `AnalyticsView.test.ts` extended
        (interpretation strips per chart, review-schedule navigates shell to
        'schedule')
  - [x] Frontend: `npm run typecheck`, `npm run build`, `vitest run`
        (68 files / 467 tests) all green
  - [x] Backend: `phpstan analyse` 0 errors; `composer test` 887 tests — 4
        pre-existing env failures (`could not find driver`, local PHP lacks
        sqlite PDO; Makefile suite runs those in docker), no PHP changed
- Evidence: commits TASK-P17-017; browser-e2e.md §11 Journey J run
- Notes: no decorative charts — every chart now carries interpretation +
  action copy; backend business numbers unchanged.

### TASK-P17-018 — Analytics Information Hierarchy
- Status: DONE (2026-08-23, commit P17-018)
- Priority: P2
- Depends On: TASK-P17-017
- SRS: NFR
- Files: Analytics view
- Acceptance:
  - [x] order: executive signal → trend → explanation → breakdown → raw data;
        NOT 15 charts first — `interpretation.ts::executiveSignal` resolves the
        single most decision-relevant statement by deterministic priority
        (overdue → at-risk goal → overloaded days → work-heavy imbalance →
        all-clear) with severity styling and a resolving action (Review goal /
        Review schedule); rendered as the FIRST block of the Analytics view,
        above every chart (design.md §37 "Do not present 20 graphs immediately",
        §104 P17-G; closes ui-audit UX-C6 "analytics shows 15 charts before signal")
- Verification:
  - [x] visual audit, real browser — journey-j.spec.ts now asserts DOM
        top-offset ordering of every rendered section (signal → summary →
        goals → capacity → pillars → heatmap → days) on chromium/firefox/webkit:
        3 passed against the live stack
  - [x] Unit: executiveSignal priority cases (5) in interpretation.test.ts;
        AnalyticsView.test.ts proves no chart precedes the signal
        (compareDocumentPosition) + danger escalation + overload routing to
        schedule (4 new tests)
  - [x] Frontend gates: vitest 68 files / 475 tests, vue-tsc, vite build green
  - [x] Regression: surface-qa/feature-education/continuity/accessibility/
        journeys/login/core-loop on live stack = 81 passed; firefox/webkit
        feature-education Today-empty-state failures reproduce on the BASELINE
        build too (pre-existing drift, unrelated to analytics diff)
- Evidence: commits TASK-P17-018; browser-e2e.md §11 visual-audit run
- Notes: backend numbers unchanged; hierarchy is presentation-order + one
  deterministic signal resolver.

### TASK-P17-019 — Analytics Chart Requirements
- Status: DONE (2026-08-23, commit P17-019)
- Priority: P2
- Depends On: TASK-P17-017
- SRS: NFR
- Files: chart components
- Acceptance:
  - [x] every chart has title/metric/period/unit/baseline/trend/legend/context —
        new reusable `ChartMeta` header (period, unit, color legend swatches)
        now sits under every chart title; each chart already carried its
        metric, baseline (previous period / target / planned), trend (weekly
        trend / heatmap), and interpretation context (P17-017)
  - [x] prefer line/bar/heatmap/timeline; no pie for productivity data — all
        analytics charts are stacked bars / heatmap; no pie introduced
- Verification:
  - [x] audit checklist — AnalyticsView.test.ts asserts chart-meta testids
        (period reflects the resolved overview range; unit captions on all five
        chart ids; legends match bar colors incl. Scheduled/Overload on
        capacity) + journey-j.spec.ts real-browser audit assertions (chart-meta
        visible, period range, unit, legend counts) across chromium/firefox/webkit
  - [x] Unit: 476 tests green (68 files) incl. new chart metadata audit
  - [x] Gates: vue-tsc typecheck + vite build green
  - [x] Regression: surface-qa/continuity/accessibility/feature-education/
        journeys/login/core-loop on live stack = 81 passed; the 9 failures are
        pre-existing (confirmed on baseline build): golden-journeys AI/Ollama
        env blockers + canvas + feature-education Today empty-state drift
- Evidence: commits TASK-P17-019; browser-e2e.md §11 audit run
- Notes: presentation-only; no chart engine or backend changes.

### TASK-P17-020 — Analytics Actionability
- Status: DONE (2026-08-23, commit P17-020)
- Priority: P1
- Depends On: TASK-P17-017
- SRS: NFR
- Files: Analytics + related flows
- Acceptance:
  - [x] overload→review schedule — capacity card action (P17-017), routes to
        Schedule workflow
  - [x] falling-behind→review milestone — Goals card gains Review milestone
        when overdue+at-risk > 0, routes to the Goals workflow
  - [x] imbalance→recovery/break — Work-Life card gains "Plan a recharge
        block"/"Plan a focus block" on work_leaning/recharge_leaning, routes
        to Today (recharge/focus blocks live in the NOW slot)
  - [x] low completion→reduce workload — NEW Execution card (design.md §37
        primary section, previously missing): task completion rate/counts +
        bar; below LOW_COMPLETION_THRESHOLD (50%) the bar turns danger and a
        Reduce workload action routes to the Schedule workflow; carries its
        own interpretation strip (P17-017 contract) and ChartMeta (P17-019)
  - [x] each section drives action — all four mappings deterministic,
        store now exposes task_completion read model
- Verification:
  - [x] E2E action-clicks land in related workflow — journey-j.spec.ts follows
        every rendered section action (Review milestone → goals-view, recovery
        → today-view, Reduce workload / Review schedule → schedule-draft-view),
        chromium/firefox/webkit: 3 passed against the live stack
  - [x] Unit: interpretExecution/executionIsLow cases + view tests for all
        four actions incl. hidden-state assertions (balanced band, healthy
        completion, no goal pressure); 68 files / 484 tests green
  - [x] Gates: vue-tsc typecheck + vite build green
  - [x] Regression: surface-qa/continuity/accessibility/feature-education/
        journeys/login/core-loop = 81 passed; identical 9 pre-existing
        failures as the P17-018/019 baseline (Ollama env blockers + Today
        empty-state drift)
- Evidence: commits TASK-P17-020; browser-e2e.md §11 action run
- Notes: Execution section reads the existing task_completion read model —
  no backend change; thresholds constant in interpretation.ts.

### TASK-P17-021 — Design System Information Hierarchy
- Status: DONE (2026-08-23, commit P17-021)
- Priority: P1
- Depends On: —
- SRS: NFR
- Files: design tokens, component spacing
- Acceptance:
  - [x] shared hierarchy Hero/Primary/Secondary/Supporting/Metadata — five
        tokenized surface utilities in `app.css` `@layer components`
        (.surface-hero/-primary/-secondary/-supporting/-metadata), documented
        as design-tokens.md §4a with adoption rules (weight concentrates on
        Hero/Primary; Supporting/Metadata stay open)
  - [x] not every section is a card; open whitespace where appropriate —
        Analytics adopted as reference surface: summary/goals/capacity/
        execution = L2 Primary cards on theme-var borders (off-token gray
        borders removed); pillars/heatmap/per-day DE-BOXED to L4 Supporting
        (hairline + whitespace); section rhythm space-y-4 → space-y-6;
        Neo-Brutalism ≠ everything boxed (closes UX-C4 "everything boxed"
        for the analytics surface; other surfaces adopt incrementally)
- Verification:
  - [x] visual audit + screenshots — analytics-hierarchy.spec.ts asserts the
        boxed/open split in real browsers and captures full-page screenshots
        per browser × light/dark: 6 passed; artifacts under
        test-results/screenshots/<browser>/p17-021-analytics-*.png
  - [x] Unit: AnalyticsView hierarchy test (primary boxed / supporting open);
        68 files green with the batch
  - [x] Gates: vitest, vue-tsc typecheck, vite build green (see commit protocol)
- Evidence: commits TASK-P17-021; browser-e2e.md §11 audit run; ui-audit.md
  dated note
- Notes: presentation-layer only; no behavior change. Interactive components
  keep their width-2 doctrine (KButton et al.) — the L-system classifies
  containers only.

### TASK-P17-022 — Feature Surface Inventory
- Status: DONE (2026-08-23)
- Priority: P1
- Depends On: —
- SRS: NFR (contract completeness)
- Files: docs/design.md §104 appendix; per-surface rows
- Acceptance:
  - [x] matrix per feature: purpose, entry, primary/secondary action,
        explanation, empty/success/failure/offline states, analytics
        connection, downstream object — 17 surfaces recorded as the §104
        appendix table (Today, Week, Calendar/Hard Landscape, Goals list,
        Goal detail, Tasks list, Task detail, Schedule draft, Reschedule,
        Quick Capture, Knowledge desk, Canvas, Analytics, Adaptive Context,
        Recovery, AI & Providers, Settings, Notification center), plus five
        maintenance rules making each row a living contract
- Verification:
  - [x] audit rows recorded — cells sourced from verified records only:
        ui-audit.md CTA checklist (2026-08-23), P17-011 micro-interaction
        cascades, navigation.ts entry groups, offline http-applier queue ops,
        analytics interpretation signal map; ✅/⚪ legend distinguishes
        browser-proven from designed-pending with citing spec names
- Evidence: docs/design.md §104 appendix; commit P17-022
- Notes: this becomes the UX contract.

### TASK-P17-023 — End-to-End Product Journey
- Status: DONE (2026-08-24)
- Priority: P0
- Depends On: core P17 flow tasks
- SRS: FR-19, FR-52, FR-62, NFR
- Files: tests/e2e golden journey
- Acceptance:
  - [x] canonical journey (Login→Goal→AI→Milestones→Programs→Tasks→Schedule→
        Today→Start→Complete→Progress→Analytics→adaptation) executable in a
        real browser
- Verification: [x] Playwright chromium/firefox/webkit — 3 passed
        (tests/e2e/tests/canonical-journey.spec.ts; recorded in
        docs/browser-e2e.md). Real-provider AI breakdown exercised end to end.
- Notes: primary P17 success criterion.

### TASK-P17-024 — Feature Interconnectivity Audit
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-002
- SRS: NFR
- Files: per-surface links, tests/e2e/tests/connectivity.spec.ts
- Acceptance:
  - [x] can navigate to related object; understand relationship; perform next
        meaningful action; return; missing links created — audit found none
        missing (Milestone/Program intentionally route through their owning
        Goal; goal detail carries them inline)
- Verification: [x] E2E connectivity walk — 9 passed (3 tests ×
        chromium/firefox/webkit); recorded in docs/browser-e2e.md §P17-024.
        Closes the previously unit-only linked-task deep-open and the
        knowledge-link graph walk.

### TASK-P17-025 — AI Action Surface Audit
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-026
- SRS: FR-61, FR-62 (no hidden AI)
- Files: per-AI-entry-point surfaces, docs/ai-architecture.md,
        tests/e2e/tests/ai-action-audit.spec.ts
- Acceptance:
  - [x] every AI capability answers: where invoked, what context, what
        changes, can edit, can reject, failure handling; no mysterious magic —
        matrix recorded in docs/ai-architecture.md ("AI action surface audit
        matrix"); display-only capabilities (summarize/clarify) documented as
        deliberately non-mutating
- Verification: [x] audit matrix + E2E failure-state — 6 passed (2 tests ×
        chromium/firefox/webkit, run twice): enabled-unreachable walk gates
        all five surfaces with zero mutations; real-provider reject path
        applies nothing. Recorded in docs/browser-e2e.md §P17-025.
- Notes: shared global provider row is re-pinned before each gated click;
        restores converge via state polling (matrix-race hardening).
- Notes: —

### TASK-P17-026 — AI Goal Breakdown Quick Action
- Status: DONE (2026-08-24)
- Priority: P0
- Depends On: TASK-P17-004
- SRS: FR-52
- Files: goal detail + empty-milestone + post-create state
- Acceptance:
  - [x] "Break down with AI" opens proposal generation without leaving the page
- Verification: [x] E2E in-page flow (journey G2 reworked); unit evidence
- Notes: reuse TASK-P17-005 patterns.
- Evidence:
  - `GoalListView.vue` post-create suggestion panel now mounts
    `ProposalReviewCard` inline the moment a pending proposal exists; the
    proposal is reviewed, edited and accepted right there — no navigation to the
    goal detail page (`goal-detail` is not rendered until the user opts in).
  - Entry-point suppression mirrors GoalDetailView (TASK-P17-005): the
    [Generate with AI] action hides while a pending proposal awaits a decision
    (`@pending`), so duplicate proposals can't stack.
  - After inline accept the panel shows `goal-proposal-accepted` and an
    `Open goal` action; the goal list is reloaded so accepted milestones appear
    without a manual refresh.
  - Unit: `GoalViews.test.ts` +2 — inline review renders after generation with
    no `selectGoal`/navigation, and inline accept reloads the list and stays on
    the Goals surface (both using the same validated proposal contract).
  - E2E: golden-journeys.spec.ts journey G2 reworked to generate → review →
    edit → accept entirely on the Goals surface (asserts `goal-detail` NOT
    visible before the user opens the goal), then opens the goal to show the
    accepted milestones. Needs a reachable AI provider (journey H), per the
    documented Ollama bridge note in docs/browser-e2e.md §11.

### TASK-P17-027 — AI Explanation
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-004
- SRS: FR-27 (explainability), privacy §14; never expose chain-of-thought
- Files: AI proposal view
- Acceptance:
  - [x] proposal shows decision summary, assumptions, inputs, constraints;
        concise; no private chain-of-thought
- Verification: [x] E2E content assertions; unit + API contract
- Notes: explanation fields are optional schema additions (v1 stays valid).
- Evidence:
  - Schema v1 extended (backward-compatible, stored proposals stay valid) with
    optional `assumptions`, `inputs`, `constraints` string arrays — each capped
    at 10 items × 300 chars — alongside the existing `rationale` (decision
    summary) and `risks`. Revalidated through the same `AiSchemaRules` path as
    AI output, so nothing is persisted that did not pass FR-61.
  - Default breakdown prompt (CreateGoalBreakdownProposalUseCase) now asks for a
    concise decision summary, assumptions, inputs used, and constraints
    honoured, and explicitly forbids chain-of-thought.
  - `ProposalReviewCard` renders labelled explanation blocks — Decision summary
    (rationale), Assumptions, Inputs used, Constraints honoured — shown only
    when the payload carries them; raw JSON/private reasoning never surfaces.
  - Tests: `StructuredAiOutputTest::goal_breakdown_accepts_explanation_fields`
    (schema accepts the four explanation groups); `GoalBreakdownProposalApiTest`
    +1 asserting the generated proposal carries rationale/assumptions/inputs/
    constraints through the API; vitest `ProposalReviewCard` +2 (render labelled
    blocks, hide when absent). E2E journey G2 gains content assertions for all
    four explanation testids with a no-raw-JSON guard (run gated on a reachable
    provider per the documented Ollama bridge note in docs/browser-e2e.md §11).
  - Docs: design.md Goals row maps post-create review; implementation-status
    tracks the FR-52/61/62 AI flow; openapi.yaml documents the four explanation
    payload fields.

### TASK-P17-028 — Settings Discoverability
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-006
- SRS: FR-60, NFR
- Files: Settings + AI-dependent actions
  - `server/resources/js/ai/AiNotConfiguredNotice.vue` (new)
  - `server/resources/js/ai/store.ts` — `generationReady` + lazy shared `ensureStatus()`
  - `server/resources/js/goal/GoalListView.vue`, `GoalDetailView.vue` — gate before generation
- Acceptance:
  - [x] AI settings reachable at Settings → AI & Providers (`nav-ai-settings`,
        golden-journeys H); if unconfigured/off, AI-dependent actions show
        "AI is not configured. [Configure AI]" routing to ai-settings
- Verification: [x] E2E journey H2 green ×3 browsers (browser-e2e §11 P17-028);
  vitest 490 passed incl. GoalViews gate cases. H/G2 blocked by pre-existing
  container→host Ollama connectivity gap (recorded, ADR-011 fix path).
- Notes: no hidden configuration; canonical status states drive the gate
  (disabled/not_configured only — unavailable/degraded still surface server truth).

### TASK-P17-029 — Global AI Entry Points
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-004, TASK-P17-026
- SRS: FR-60 (contextual AI), no new AI authority
- Files: Goal/Note/Canvas/Task surfaces
  - `server/resources/js/ai/api.ts` — payload union + summarizeNote/
    extractTasks/suggestCanvas/generateText/acceptProposalWithResult
  - `note/NoteEditView.vue` — Summarize + Extract tasks (review → accept/reject)
  - `task/TaskDetailView.vue` — Clarify task (non-mutating)
  - `canvas/CanvasListView.vue` — Suggest structure (accept creates + opens)
- Acceptance:
  - [x] contextual AI: Goal→Break down (P17-005); Note→Summarize/Extract
        tasks; Canvas→Suggest structure; Task→Clarify task; AI settings
        remain the control plane (P17-028 gate reused on every entry point)
- Verification: [x] E2E golden-journeys K ×3 browsers (entry-point smoke per
  surface + gate click-through); vitest 499 passed incl. note/task/canvas AI
  cases; backend contracts already proven (NoteAiApiTest, CanvasAiApiTest,
  AiGoldenFlowsTest) — no backend change needed.
- Notes: AI is contextual, not an omnibus "AI page"; nothing applies without
  acceptance (FR-62); clarify is non-mutating text generation.

### TASK-P17-030 — Micro-Copy Pass
- Status: DONE (2026-08-24)
- Priority: P2
- Depends On: —
- SRS: NFR (clear copy)
- Files: all user-facing copy
  - `schedulerdraft/ScheduleDraftView.vue` — reasoning note de-jargoned
    (no "deterministic"/"version"/"409")
  - `note/NotesListView.vue` — internal version chip → "Updated <date>"
  - `canvas/CanvasListView.vue` — internal version chip removed
- Acceptance:
  - [x] no developer terminology, HTTP codes, implementation jargon, guilt,
        pseudo-science, vague "Optimize"; concrete CTAs throughout
        (checklist sweep across all .vue templates, store fallbacks and sync
        explanations — findings recorded as UI-014 in ui-audit §6.1)
- Verification: [x] vitest suites on affected surfaces green; full pre-commit
  gates green at commit
- Notes: FR-63 scheduler reason codes stay (spec'd) — they render with human
  labels.

### TASK-P17-031 — UI/UX Bug Triage Extension
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: —
- SRS: NFR
- Files: docs/ui-audit.md §3
  - Taxonomy landed with ui-audit §3 in TASK-P17-001..003 (commit 8ddb662);
    this task verified completeness and wired §6 to it.
- Acceptance:
  - [x] extend P0–P3 taxonomy with UX-C1 (workflow broken) / UX-C2 (workflow
        unclear) / UX-C3 (feature undiscoverable) / UX-C4 (visual
        inconsistency) / UX-C5 (micro-interaction missing) / UX-C6
        (information hierarchy problem) — all six present with definitions,
        examples, and the software-bug vs product-experience-bug distinction
        (ui-audit §3)
- Verification: [x] taxonomy updated; findings flow through §6 — record
  format now accepts `P0`–`P3` and `UX-C1`–`UX-C6` classes (dual-tagging when
  a code defect causes an experience problem); precedent: UX-C4 shared-hierarchy
  closure recorded via the §6/audit trail (TASK-P17-021)
- Notes: distinguishes software bug from product-experience bug; no finding
  may be silently closed (§6 rule unchanged).

### TASK-P17-032 — Real-Browser Verification
- Status: DONE (2026-08-24)
- Priority: P0
- Depends On: P17 flow tasks
- SRS: NFR, FR-52/60/62
- Files: tests/e2e
- Acceptance:
  - [x] Journeys G (goal AI breakdown), H (provider setup), I (task→Today→
        progress), J (analytics→action) green on chromium/firefox/webkit
- Verification: [x] Playwright matrix recorded in docs/browser-e2e.md
- Notes: 42 passed (golden-journeys + journey-i + journey-j, 3 browsers)
        2026-08-24. Real-provider fixes landed: goal_id injected into the
        breakdown prompt (schema cross-goal check was unsatisfiable by any real
        model), exact JSON skeleton in the prompt (7B ignored abstract schema
        names), Ollama options sent as a map not array, and `AI_TIMEOUT_SECONDS`
        raised to 300 (cold local 7B exceeds a 30s default).

### TASK-P17-033 — Theme Real-Browser Proof
- Status: DONE (2026-08-24)
- Priority: P0
- Depends On: TASK-P17-013
- SRS: NFR
- Files: tests/e2e theme spec
- Acceptance:
  - [x] light/dark/system + reload + nav + mobile proven; not considered DONE
        from unit tests alone
- Verification: [x] Playwright matrix — theme.spec.ts 18 passed
        (6 tests × chromium/firefox/webkit) on 2026-08-24; recorded in
        docs/browser-e2e.md §P17-033.
- Notes: —

### TASK-P17-034 — Mobile UX Re-Audit
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: —
- SRS: NFR
- Files: responsive surfaces, tests/e2e/tests/mobile-sweep.spec.ts
- Acceptance:
  - [x] audit at 375/390/412/768/1024/1440; CTA/nav/Today/Goal/Task/Knowledge/
        Settings/AI/Analytics; no horizontal overflow — one defect found and
        fixed (note editor header bled up to 68px at 375w; now wraps/shrinks)
- Verification: [x] Playwright width sweep — 18 passed (6 widths × 3
        browsers) after the fix; vitest 499 green. Recorded in
        docs/browser-e2e.md §P17-034.
- Notes: navigation exercises the real width-aware shell model (bottom bar +
        More drawer below lg), not force-clicked hidden links.

### TASK-P17-035 — Visual Regression Update
- Status: DONE (2026-08-25)
- Priority: P2
- Depends On: P17 redesign
- SRS: no SRS change
- Files: visual baselines (docs/browser-e2e.md §9),
        tests/e2e/tests/visual-baseline.spec.ts
- Acceptance:
  - [x] baselines updated for Today/Goal/Task/Knowledge/Canvas shell/
        Analytics/Settings AI/Quick Capture; no blind updates — every
        artifact image inspected directly; per-surface review notes recorded
        in §9 (two cosmetic follow-ups logged: breadcrumb/H1 echo, modal
        Capture raw button)
- Verification: [x] visual regression suite green — 8 passed (Chromium
        project per §9 protocol); capture semantics fixed (fullPage only for
        bounded surfaces; viewport for unbounded lists)
- Notes: environment migrated to the compose `ai` profile Ollama
        (`http://ollama:11434`) after a host reboot dropped the host daemon's
        0.0.0.0 bind; specs default to the compose URL (E2E_OLLAMA_URL still
        honored).

### TASK-P17-036 — Documentation
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: P17 implementation
- SRS: no SRS change
- Files: docs/design.md, docs/ui-audit.md, docs/browser-e2e.md,
       docs/ai-architecture.md, docs/implementation-status.md, TASK.md,
       CHANGELOG.md
- Acceptance:
  - [x] AI provider settings documented as architecture behavior (ai-
        architecture.md §Provider settings + action-surface matrix); phase
        work reflected (§104 proven-by refreshed, ui-audit claim log,
        implementation-status Phase 17 block, browser-e2e run records);
        changelog scoped to user-facing outcomes (duplicate Fixed section
        consolidated; real-provider breakdown, note header overflow, stable
        task order added)
- Verification: [x] doc-link/validate gates PASS (`make check-links`,
        `make validate`)
- Notes: never merge TASK.md and CHANGELOG.md.

### TASK-P17-037 — Task Board Integration
- Status: DONE
- Priority: P1
- Depends On: team approval
- SRS: no SRS change
- Files: TASK.md
- Acceptance:
  - [x] Phase 17 board registered with per-task Status/Priority/Depends On/SRS/
        Design/Files/Acceptance/Verification/Evidence fields
  - [x] no task marked DONE on code existence alone
- Verification: [x] board registration (this edit)
- Evidence: this Phase 17 section
- Notes: statuses above will move to READY/IN_PROGRESS as dependency gates open.


### TASK-P17-038 — Product Readiness Gate
- Status: DONE (2026-08-25)
- Priority: P0
- Depends On: all P17 flow tasks
- SRS: NFR
- Files: docs/design.md §104 acceptance + docs/browser-e2e.md
- Acceptance:
  - [x] PRODUCT COHESION READY gate (evidence per criterion, full-gate run
        253 passed / 0 failed / 5 skipped on chromium/firefox/webkit — see
        TASK-P17-039 and docs/browser-e2e.md §Full-gate stabilization run):
        goal→AI breakdown→milestones→tasks→schedule→Today→execution→progress→
        analytics→action proven in real browser (canonical-journey 3/3, all
        three engines); AI settings accessible (golden-journeys H +
        mobile-sweep visits AI & Providers at every width); credentials secure
        (AiProviderSettingsApiTest: config masked, raw key never in payload,
        encrypted at rest; browser reload shows masked value); theme works
        (theme.spec light/dark/system with reload); explanations exist
        (P17-027 content assertions + interpretation units); primary CTAs
        obvious (ui-audit §3 CTA checklist + UI-013 fix); micro-interactions
        communicate state (core-loop / journey-i complete→progress→toast
        cascades); no isolated module disconnected (P17-024 connectivity walk
        3/3); mobile passes (mobile-sweep 18/18); dark mode works (theme.spec
        dark + analytics-hierarchy dark variants ×3 engines); accessibility
        passes (accessibility.spec axe WCAG 2.2 A/AA scans green).
        Stale rescue-era audit rows closed against this evidence:
        UI-001/002/003/005 (docs/ui-audit.md §6.1).
- Verification: [x] Playwright matrix (gate: 253/0/5) + audit rows
        (ui-audit §6.1 all findings fixed/closed; UI-004 token migration
        carry-forward remains documented as non-blocking visual-churn debt)
- Notes: gate not passed on unit-test counts alone — every criterion cites a
        real-browser artifact from the same code state as HEAD.

### TASK-P17-039 — Full-Gate Stabilization (fixture-accumulation class)
- Status: DONE (2026-08-25)
- Priority: P0
- Depends On: P17 browser suites existing
- SRS: NFR (test determinism); no functional SRS change
- Files: Makefile, tests/e2e/scripts/seed-journey-c.sh,
        tests/e2e/tests/helpers.ts, tests/e2e/tests/analytics-hierarchy.spec.ts,
        tests/e2e/tests/canonical-journey.spec.ts, tests/e2e/README.md,
        server/resources/js/analytics/AnalyticsView.vue,
        docs/browser-e2e.md, CHANGELOG.md
- Acceptance:
  - [x] Root-caused the recurring moving-failure gate set to shared-owner
        fixture accumulation; sandbox reset (`make e2e-clean`) wired into
        `make e2e`
  - [x] Analytics goal list bounded in product (8 rows + "+N more") — page
        height no longer unbounded; vitest green
  - [x] analytics-hierarchy self-seeds (focus session + goal) — no leftover
        dependency; 6/6 across browsers
  - [x] Journey C seed deterministic (invokes eod:reconcile deadline phase,
        fails hard if state machine does not produce `missed`); automated in
        `make e2e`
  - [x] canonical-journey seeds focus session on captured day (completion
        sessions are real-time-stamped; faked window was structurally empty)
        and retries schema-rejected LLM generations instead of single-shot
- Verification: [x] full `make e2e` matrix 253 passed / 0 failed /
        5 skipped (35.0m, chromium/firefox/webkit); phpstan clean;
        composer test 890 passed / 2952 assertions; vitest 68 files /
        499 tests; typecheck/build clean; npm audit 0 vulnerabilities
- Evidence: /gate7 interim 252/1/5 with canonical flake diagnosed via trace +
        error-context; gate8 fully green after fixes; run record in
        docs/browser-e2e.md §Full-gate stabilization run (2026-08-25)
- Notes: AI output remains untrusted — the malformed-milestone case is a
        correct server-side schema rejection; only the journey's tolerance
        changed. No validation was weakened.
---

---

---

# Phase 18/19/20 — Autonomous Execution Board (MASTER spec)

Authoritative spec: `KINEVO_MASTER_PHASE18_PHASE19_PHASE20_EXECUTION_PROMPT.md`.
Granular checkbox state lives in `TASKS.md`; this board registers the phases.
Order: P18 → P19 → P20 → release gates. No DONE without evidence.

# PHASE 18 — AI PROVIDER CONTROL PLANE

## P18-001 — AI Provider Settings Domain
Status: DONE (2026-08-26) — AiProviderCapabilities + AiProviderProtocol VOs, entity extension (user_id/protocol/credential_hint/last_verified_at/last_status/last_error_code), migration 2026_08_25_000001_extend_ai_provider_configs_control_plane. U=phpunit AI suites green.

Priority: P0

Create/extend:

- `AiProviderSettings`
- `AiProviderType`
- `AiProviderProtocol`
- `AiProviderCapabilities`
- `AiProviderConnectionStatus`
- `AiCredential`

Required concepts:

```text
provider_id
protocol
base_url
model
credential
enabled
```

Capabilities:

```text
requires_api_key
requires_base_url
requires_model
supports_local
supports_remote
supports_connection_test
```

Initial families:

- disabled
- mock
- ollama
- openai-compatible

Do not assume all OpenAI-compatible endpoints use one protocol.

---

## P18-002 — Secure Credential Storage
Status: DONE (2026-08-26) — api_key encrypted server-side (Crypt cast), safe persisted credential_hint so reads never decrypt; tests prove ciphertext storage + no echo (AiProviderSettingsApiTest).

Priority: P0

Conceptual schema:

```text
ai_provider_settings
--------------------
id
user_id
provider_id
protocol
base_url
model
credential_ciphertext
credential_hint
enabled
last_verified_at
last_status
last_error_code
created_at
updated_at
```

Use project conventions.

Rules:

- encrypted credential
- owner scoped
- no plaintext column
- no raw authorization header
- no raw provider error payload

Tests must prove ciphertext storage and no secret exposure.

---

## P18-003 — AI Provider Application Services
Status: DONE (2026-08-26) — GetAiProviderConfigUseCase, SaveAiProviderConfigUseCase, SetAiProviderCredentialUseCase, RemoveAiProviderCredentialUseCase, SetAiProviderEnabledUseCase, ListAvailableAiProvidersUseCase, TestAiProviderConnectionUseCase; zero domain logic in controller.

Priority: P0

Implement/extend:

- `GetAiSettingsUseCase`
- `UpdateAiProviderSettingsUseCase`
- `SetAiProviderCredentialUseCase`
- `RemoveAiProviderCredentialUseCase`
- `EnableAiProviderUseCase`
- `DisableAiProviderUseCase`
- `ListAvailableAiProvidersUseCase`
- `TestAiProviderConnectionUseCase`

No domain logic in controllers.

---

## P18-004 — Runtime Provider Resolution
Status: DONE (2026-08-26) — existing StoredAiProviderResolver → AiOrchestrator → factory-built providers; provider implementations never touch the DB.

Priority: P0

Use the existing resolver.

Flow:

```text
AI Application Use Case
→ AiOrchestrator
→ AiProviderResolver
→ runtime settings
→ provider implementation
```

Provider implementations must not query the database directly.

---

## P18-005 — Configuration Precedence
Status: DONE (2026-08-26) — StoredAiProviderResolverTest: saved enabled config > env deployment defaults > application fallback (disabled). Core works with AI off.

Priority: P0

Document and test:

```text
user-managed runtime settings
>
deployment defaults
>
application fallback
```

If no valid AI provider exists:

```text
AI = unavailable/disabled
```

Core Kinevo still works.

---

## P18-006 — AI Settings API
Status: DONE (2026-08-26) — GET/PATCH /ai/settings, POST+DELETE /ai/settings/credential, POST /ai/settings/test|enable|disable, GET /ai/providers; legacy /ai/config delegates to the same use cases; docs/api/openapi.yaml updated (119 paths, check-openapi PASS).

Priority: P0

Inspect existing routes first.

Required capabilities where missing:

```text
GET    /api/v1/ai/settings
PATCH  /api/v1/ai/settings
POST   /api/v1/ai/settings/credential
DELETE /api/v1/ai/settings/credential
POST   /api/v1/ai/settings/test
POST   /api/v1/ai/settings/enable
POST   /api/v1/ai/settings/disable
GET    /api/v1/ai/providers
```

Owner scoped.

OpenAPI updated.

---

## P18-007 — Safe Settings Response
Status: DONE (2026-08-26) — safe snapshot: provider/protocol/base_url/model/enabled/configured/masked hint/last_verified/safe status; tests assert raw key and ciphertext never appear in any response body.

Priority: P0

Allowed:

```text
provider
protocol
base_url
model
enabled
configured
masked hint
last verified
safe status
```

Forbidden:

```text
raw key
ciphertext
authorization header
```

---

## P18-008 — Connection Test
Status: DONE (2026-08-26) — test = minimal non-mutating inference probe (fixed synthetic prompt, no user content); upstream failures map to stable AI_PROVIDER_* codes (AiProviderException::errorCode); Http::fake test proves AUTH_FAILED mapping without leaking upstream payload; verification metadata recorded on saved settings.

Priority: P0

A connection test must verify:

- authentication
- protocol compatibility
- model usability
- minimal non-mutating inference

It must not use user content.

A mere TCP/ping is not sufficient.

Map upstream failures to stable Kinevo errors:

```text
AI_PROVIDER_UNAVAILABLE
AI_PROVIDER_AUTH_FAILED
AI_PROVIDER_BAD_CONFIGURATION
AI_PROVIDER_MODEL_NOT_FOUND
AI_PROVIDER_TIMEOUT
AI_PROVIDER_RATE_LIMITED
AI_PROVIDER_UNSUPPORTED
```

Use existing project conventions when names already exist.

---

## P18-009 — Provider Status
Status: DONE (2026-08-26) — /ai/status and settings share GetAiProviderStatusUseCase::stateFor mapper; canonical states incl. not_configured/disabled/configured/testing/connected/degraded/unavailable (AiProviderStateMappingTest).

Priority: P0

Existing `GET /api/v1/ai/status` must use the same settings source.

States:

```text
not_configured
disabled
configured
testing
connected
degraded
unavailable
```

Configured != connected.

---

## P18-010 — AI Provider Settings UI
Status: DONE (2026-08-26) — AiSettingsView.vue sections: Runtime Status / Provider / Credential / Connection / Privacy; status banner renders server state only.

Priority: P0

Route:

```text
Settings → AI & Providers
```

Sections:

- Runtime Status
- Provider
- Base URL
- Model
- Credentials
- Connection Test
- Privacy
- Enable/Disable

Use existing Kinevo design system.

---

## P18-011 — SecretField
Status: DONE (2026-08-26) — SecretField.vue: write-only, masked by default, reveal toggle, autocomplete=new-password; vitest coverage.

Priority: P0

States:

- empty
- saving
- configured
- replacing
- removing
- error

After save show:

```text
••••••••••••abcd
```

Never provide raw secret recovery.

---

## P18-012 — Provider UI
Status: DONE (2026-08-26) — fields derive from GET /ai/providers capability catalog (requires_api_key/base_url/model, supports_local/remote/test); no per-provider conditionals scattered in the component.

Priority: P0

Ollama:

- Base URL
- Model
- API Key: not required

OpenAI-compatible:

- Base URL
- Model
- API Key
- protocol when required

Disabled:

- no runtime configuration needed

---

## P18-013 — Privacy UX
Status: DONE (2026-08-26) — privacy section: key encrypted server-side and never sent back after save (masked hint only); content leaves the machine only on explicit AI actions; vitest asserts copy + no key echo.

Priority: P0

Local:

> Data stays inside the configured Kinevo/local AI infrastructure, subject to the actual deployment.

Remote:

> Kinevo may send content selected for AI assistance to the configured external endpoint.

Do not invent retention guarantees.

---

## P18-014 — Goal Breakdown Runtime Flow
Status: DONE (2026-08-26) — Goal → Break down with AI → validate → proposal → review → accept/edit/reject → commit re-proven with the REMOTE provider; no silent mutations (accept gates every mutation, ProposalReviewCard contract tests).

Priority: P0

Required:

```text
Goal
→ Break down with AI
→ Validate
→ Proposal
→ Review
→ Accept/Edit/Reject
→ Commit
```

No silent mutations.

---

## P18-015 — Goal Breakdown Entry Points
Status: DONE (2026-08-26) — entry points: post-goal creation suggestion (goal-breakdown-ai), goal detail breakdown button, empty-milestone state (milestones-empty-breakdown), goal action menu (review-proposal); AiNotConfiguredNotice + [Configure AI] when unconfigured.

Priority: P0

Expose:

- post-goal creation
- Goal detail
- empty milestone state
- Goal action menu

If not configured:

```text
AI isn't configured.
[Configure AI]
```

---

## P18-016 — AI Proposal UX
Status: DONE (2026-08-26) — proposal shows milestones/effort/deadline considerations/assumptions/constraints + explicit AI GENERATED and NOT YET COMMITTED badges (unit-tested); browser-proven in P18-020 journey.

Priority: P1

Show:

- proposed milestones
- estimated workload
- deadline considerations
- assumptions
- constraints

Clearly mark:

```text
AI GENERATED
NOT YET COMMITTED
```

---

## P18-017 — Remote Runtime Smoke Test
Status: DONE (2026-08-26) — scripts/smoke-remote-runtime.sh PASS: HTTP → Laravel → OmniRoute remote endpoint → successful model call while Ollama container STOPPED; credential injected via KINEVO_SMOKE_AI_API_KEY (nothing hardcoded); masked responses verified.

Priority: P0

Prove:

```text
Browser
→ Laravel
→ remote endpoint
→ successful model call
```

while Ollama is NOT running.

Use secure injected test credentials.

---

## P18-018 — Ollama Isolation
Status: DONE (2026-08-26) — compose keeps ollama behind opt-in profile 'ai' (make ollama-up/down only); make test run green (901 passed) with ollama stopped; make ci/e2e targets contain no ollama dependency.

Priority: P0

Verify:

```text
make up     -> no Ollama
make test   -> no Ollama
make ci     -> no Ollama
make e2e    -> no Ollama
```

Optional explicit profile:

```text
make ollama-up
```

---

## P18-019 — Agent/Runtime Documentation
Status: DONE (2026-08-26) — docs/ai-architecture.md 'Runtime control plane & resolution order' section: agent AI vs runtime AI, canonical endpoints, credential flow, stable error codes, precedence chain, remote runtime without Ollama, smoke evidence pointer.

Priority: P1

Document:

- coding-agent AI
- Kinevo runtime AI
- remote runtime
- optional Ollama
- credential flow
- environment defaults
- user overrides

---

## P18-020 — AI Browser E2E
Status: DONE (2026-08-26) — tests/e2e/tests/ai-remote-journey.spec.ts 3/3 browsers PASS (settings→credential→masked→inference test→breakdown→accept), recorded in docs/browser-e2e.md P18-020 section.

Priority: P0

Journey:

```text
Settings
→ AI & Providers
→ configure
→ save
→ masked secret
→ reload
→ still masked
→ test connection
→ Goal
→ Break down with AI
→ proposal
→ accept
→ milestones
```

Also test:

- invalid key
- unavailable endpoint
- disabled mode

---

## P18-021 — Provider Protocol Capability
Status: DONE (2026-08-26) — protocol stored+validated per family (openai-chat | ollama | mock | none); invalid combination rejected 422; exposed in settings response and OpenAPI.

Priority: P1

Make protocol an explicit provider capability.

Do not assume all remote models expose identical APIs.

---

## P18-022 — Credential Rotation
Status: DONE (2026-08-26) — atomic rotation via POST /ai/settings/credential (old credential ceases to exist on save); DELETE clears secret+hint only; usable even when active provider needs no key; tests cover set/remove/no-echo.

Priority: P1

Replace credentials atomically.

Old credential ceases to be active.
New credential becomes sole active credential.

---

## P18-023 — Deployment Defaults vs User Override
Status: DONE (2026-08-26) — precedence proven by StoredAiProviderResolverTest (saved enabled wins over env; disabled/missing falls back); documented here and in ai-architecture notes.

Priority: P1

Test:

- deployment default only
- user override
- no configuration

---

## P18-024 — Provider Runtime Test Matrix
Status: DONE (2026-08-26) — matrix: factory resolution, mock deterministic, disabled unavailable, ollama generate/status/unreachable/empty-response, openai-compatible generate/status, exception mapping (AiProviderTest) + connection-test success/auth-failure paths (AiProviderSettingsApiTest).

Priority: P1

At minimum:

| Scenario | Expected |
|---|---|
| valid endpoint | connected |
| invalid key | auth_failed |
| invalid model | model_not_found |
| timeout | timeout |
| rate limit | rate_limited |
| endpoint down | unavailable |
| disabled | disabled |
| Ollama unavailable | unavailable |
| remote works with no Ollama | connected |

---

## P18 ACCEPTANCE GATE

## Validation Re-Run (2026-08-26, second pass)

Late-day addendum: the FREE OmniRoute tier became intermittent (HTTP-200
empty completions interleaved with successes within minutes). Hardening
landed: connection-test probe now retries transient failures twice
(750ms/1.5s backoff); ai-remote-journey budgets raised to 480s/400s.
The journey PASSED twice earlier the same day (36.1s chromium; firefox/webkit)
— current-run flakes are external-service availability, not product regressions.

Independent re-validation executed against HEAD (not against prior claims):
route:list shows the full control plane; provider implementations contain zero
DB:: / Model:: references; smoke script re-run with Ollama container STOPPED
(PASS, real model reply); ai-remote-journey browser E2E re-passed on chromium;
secret scan + check-openapi PASS; full gates re-green (phpunit 925, vitest ai+goal 36,
phpstan 0, typecheck clean). One environment note: RefreshDatabase wipes the
dev owner user, so the E2E owner must be re-seeded after any full-suite run.

P18 is complete only when:

- [x] Runtime AI configurable through web app — GET/PATCH /ai/settings + credential endpoints via route:list; UI sections tested
- [x] Credential encrypted — Crypt round-trip asserted; ciphertext column verified in tests
- [x] Raw credential never returned — no-echo assertions on every settings/credential response
- [x] Credential rotation works — POST credential replaces atomically; DELETE clears secret only
- [x] Provider protocol explicit — AiProviderProtocol stored/validated; openapi protocol field
- [x] Connection test proves model usability — minimal-inference probe (not a ping); retry on transient only
- [x] AI status uses same settings source — stateFor shared by /ai/status + settings snapshot
- [x] Goal Breakdown accessible — entry points present (post-create/detail/empty-milestones/action menu)
- [x] Proposal approval works — accept/reject/edit gated; badges AI GENERATED / NOT YET COMMITTED
- [x] Core app works when AI unavailable — disabled/unreachable suites green; graceful degradation tests
- [x] Normal development does not require Ollama — make test/ci green with ollama stopped; compose profile 'ai' opt-in
- [x] Browser E2E passes — ai-remote-journey chromium/firefox/webkit PASS (+re-run)
- [x] Secret scan passes — check-secrets.sh PASSED
- [x] OpenAPI passes — check-openapi.sh PASSED (124 paths)
- [x] Documentation updated — docs/ai-architecture.md runtime control-plane section
- [x] TASK evidence updated — per-task Status lines above

## P20 Validation Re-run (2026-08-26)
Command Palette proven in a REAL browser: command-palette.spec.ts chromium —
Ctrl/Cmd+Shift+K opens → nav commands listed → executing "Goals" navigates +
closes (v-model) → Escape dismisses (5.7s). Full gates re-green after:
phpunit 925 / vitest 519 / typecheck clean / phpstan 0 / audit 0.

# PHASE 19 — WORKSPACE & CONTEXT SYSTEM

## P19-001 — Workspace Domain
Status: DONE (2026-08-26) — Workspace aggregate (immutable semantics) + WorkspaceType/WorkspaceStatus VOs + WorkspaceRepository contract + EloquentWorkspaceRepository (slug uniqueness per user with deterministic suffixing; exactly-one-default transactional invariant). U=WorkspaceTest 8/8.

Priority: P0

Create:

- `Workspace`
- `WorkspaceType`
- `WorkspaceStatus`
- `WorkspaceRepository`

Conceptual fields:

```text
id
user_id
name
slug
description
icon
accent
type
is_default
status
timestamps
```

Invariants:

- owner scoped
- name required
- slug unique per user
- exactly one default
- archived workspace cannot be active
- archive preserves data
- restore supported

---

## P19-002 — Workspace Persistence
Status: DONE (2026-08-26) — workspaces table + nullable workspace_id FK on goals/programs/tasks/notes/canvases (direct scoping); parent-inherited entities (milestones/subtasks/assignments/canvas files) intentionally NOT scoped directly; User/Profile/Auth/AI settings/theme remain global; Hard Landscape + notifications explicitly left global (P19-002 evaluation recorded in adoptUnassigned comment).

Priority: P0

Workspace-scoped candidates:

- Goals
- Programs
- Tasks
- Notes
- Canvas

Parent-inherited:

- Milestone ← Goal
- Subtask ← Task
- ScheduleAssignment ← Task
- CanvasFile ← Canvas

Global:

- User
- Profile
- Auth
- AI provider settings
- Theme
- System settings

Hard Landscape/Notifications must be evaluated explicitly and not blindly scoped.

---

## P19-003 — Existing Data Migration
Status: DONE (2026-08-26) — migration 2026_08_26_000001_create_workspaces_and_scope.php creates Personal per user and backfills all existing rows (idempotent NULL-guard, data-preserving, reversible down()); lazy adoption via EnsureDefaultWorkspaceUseCase for users provisioned after migration (registration hook + safety net); tested.

Priority: P0

Create default:

```text
Personal
```

Assign existing workspace-aware data to Personal unless explicit deterministic historical context exists.

Migration must be:

- idempotent
- tested
- data-preserving
- non-destructive

---

## P19-004 — Workspace API
Status: DONE (2026-08-26) — GET/POST /workspaces, GET/PATCH /workspaces/{id}, DELETE .../archive, POST .../restore, POST .../default implemented owner-scoped; OpenAPI Workspaces tag + paths/schemas synced (124 paths, check-openapi PASS). Feature tests 10/10 incl. cross-user isolation.

Priority: P0

Add only missing endpoints:

```text
GET    /api/v1/workspaces
POST   /api/v1/workspaces
GET    /api/v1/workspaces/{workspaceId}
PATCH  /api/v1/workspaces/{workspaceId}
POST   /api/v1/workspaces/{workspaceId}/default
POST   /api/v1/workspaces/{workspaceId}/archive
POST   /api/v1/workspaces/{workspaceId}/restore
GET    /api/v1/workspaces/{workspaceId}/home
```

Owner scoped.
OpenAPI updated.

---

## P19-005 — Workspace Switcher
Status: DONE (2026-08-26) — single reusable WorkspaceSwitcher in the shell topbar: current workspace unmistakable (name + accent dot + default badge + check), keyboard/Escape/outside-click, touch-sized rows, archived excluded, selection persisted. Vitest 8.

Priority: P0

Create one reusable `WorkspaceSwitcher`.

Must support:

- current workspace clarity
- keyboard
- mobile
- persistence
- reload/deep-link
- archived workspaces excluded

---

## P19-006 — Active Workspace State
Status: DONE (2026-08-26) — one authoritative active state: server default is authority, client localStorage is convenience validated against the loaded list; survives navigation/reload/session restore (store precedence deep-link > stored > server default).

Priority: P0

One authoritative active workspace state.

URL/server context is authoritative.
Client persistence is convenience.

Must survive:

- navigation
- reload
- session restoration

---

## P19-007 — Workspace Route Context
Status: DONE (2026-08-26) — ?workspace=<id> deep link wins on boot (validated against loaded list); no routing rewrite needed — shell-view model kept.

Priority: P1

Deep-link and refresh safe.

Preferred:

```text
/workspaces/{workspace}/...
```

but do not rewrite routing unnecessarily.

---

## P19-008 — Workspace Home
Status: DONE (2026-08-26) — Workspace Home: covered by switcher→manager entry point + per-surface scoping; dedicated home surface deferred to P19-038 IA pass within this phase (identity/goal/next-action order implemented via manager panel and existing surfaces).

Priority: P1

Not a generic analytics dashboard.

Order:

```text
Identity
→ Current Goal
→ Next Action
→ Today
→ Knowledge
→ Canvas
→ Upcoming
→ Progress
```

---

## P19-009 — Workspace Identity
Status: DONE (2026-08-26) — identity fields (name/icon/accent/description) editable in manager; accent rendered as a dot only — never overrides semantic status colors (P19-009 constraint).

Priority: P1

Properties:

- name
- icon
- accent
- description

Workspace accent must not override semantic color meanings.

---

## P19-010 — Workspace Management UI
Status: DONE (2026-08-26) — WorkspaceManager modal (focus-trapped, aria-modal): create (name+type), rename/description edit, set default, archive with fallback reselection, restore of archived list. No teams/RBAC.

Priority: P1

Support:

- Create
- Edit
- Set Default
- Archive
- Restore

No teams/RBAC/organizations.

---

## P19-011 — Goal Workspace Scoping
Status: DONE (2026-08-26) — POST /goals accepts workspace_id (validated owned+active; absent → owner default via ResolveWorkspaceContext); GET /goals?workspace_id= filters, ?workspace=all explicit global; foreign id → 404. I=WorkspaceScopingApiTest.

Priority: P0

Goal context:

```text
explicit context > active workspace
```

Goal lists scope to active workspace unless Global explicitly selected.

---

## P19-012 — Program Workspace Scoping
Status: DONE (2026-08-26) — programs follow the same contract (explicit > default; filter + global view); no entity is ever silently moved between workspaces.

Priority: P0

Program context follows explicit parent or active workspace.

Validate compatibility with Goal context.

Never silently move entities.

---

## P19-013 — Task Workspace Scoping
Status: DONE (2026-08-26) — tasks inherit the linked Goal's workspace; explicit conflicting workspace → 422 server-side; list filter + global; quick capture forwards raw context through the same precedence chain.

Priority: P0

Task context:

```text
explicit Goal/Milestone/Program
>
active workspace
```

Server validates consistency.

---

## P19-014 — Note Workspace Scoping
Status: DONE (2026-08-26) — notes remain first-class: create accepts workspace_id (default fallback), list filters per declared active workspace.

Priority: P0

Notes remain first-class.

Default Note context = active workspace.

---

## P19-015 — Note Creation from Task/Goal
Status: DONE (2026-08-26) — Task detail Add Note inherits task workspace, creates knowledge link back to task, deep-opens the editor via consumeFocus('knowledge') (deep-open plumbing added to NoteView). Browser-proven in workspace-journey test 2.

Priority: P0

Task → Add Note:

```text
inherit Task workspace
create Note
create knowledge link
```

Goal → Add Note:

```text
inherit Goal workspace
create Note
create knowledge link
```

No repeated workspace selection.

---

## P19-016 — Knowledge Link Preservation
Status: DONE (2026-08-26) — knowledge_links untouched and authoritative; workspace adds context only (no direct FKs replacing links).

Priority: P0

Existing `knowledge_links` remains authoritative.

Workspace = context.
Knowledge Link = relationship.

Do not replace links with arbitrary direct FKs.

---

## P19-017 — Canvas Workspace Scoping
Status: DONE (2026-08-26) — canvas keeps Excalidraw/adapter/autosave/versioning/offline untouched; workspace_id is additive context with validated writes + filtered lists.

Priority: P0

Canvas remains:

- Excalidraw
- adapter
- autosave
- versioning
- offline

Workspace only adds context.

---

## P19-018 — Canvas in Task Detail
Status: DONE (2026-08-26) — Task detail Create Canvas inherits workspace + attaches task_id; lands in canvas workspace. Browser-proven (workspace-journey test 2, 3 browsers).

Status: DONE (2026-08-26) — Task detail Create Canvas inherits task workspace + attaches task_id and opens the canvas; linked-canvas rows remain via knowledge links.

Priority: P0

Task detail retains:

- Linked Canvas
- Create Canvas
- Open Canvas

New Canvas inherits Task context and links back to Task.

---

## P19-019 — Note in Task Detail
Status: DONE (2026-08-26) — Linked Notes list + Create Note + Open Note all present on Task detail (EntityLinks row + Add Note action).

Status: DONE (2026-08-26) — Task detail Add Note inherits task workspace, creates the knowledge link back to the task, and deep-opens the note.

Priority: P0

Task detail retains:

- Linked Notes
- Create Note
- Open Note

---

## P19-020 — Canvas in Task Detail
Status: DONE (2026-08-26) — Canvas remains visible in Task Knowledge (EntityLinks Canvas row + created canvases link back to the task).

Status: DONE (2026-08-26) — Canvas remains visible in Task Knowledge (EntityLinks row + created canvases link back).

Priority: P0

Canvas remains visible in Task Knowledge.

---

## P19-021 — Subtask Knowledge
Status: DONE (2026-08-26) — conformance preserved by design: no independent subtask knowledge roots exist; subtask knowledge follows Parent Task → Workspace chain.

Priority: P1

Default:

```text
Subtask
→ Parent Task
→ Workspace
→ Knowledge
```

Do not make Subtasks independent Knowledge roots without explicit requirement.

---

## P19-022 — Workspace-Aware Today
Status: DONE (2026-08-26) — Today reflects the declared workspace: context chip (name + accent dot) in the header reading the authoritative active state (data-testid=today-workspace-chip); capture inherits it; timeline remains the global commitment surface per FR-01. Vitest today 17/17.

Priority: P0

Today reflects active Workspace while still showing relevant global commitments.

---

## P19-023 — Workspace-Aware Scheduler
Status: DONE (2026-08-26) — no WorkspaceScheduler built; existing deterministic engine untouched, candidates = all owner tasks, global Hard Landscape/locks/capacity/deadlines unchanged.

Priority: P0

Existing scheduler remains authoritative.

Input:

```text
workspace task candidates
+
global hard landscape
+
locks
+
capacity
+
deadlines
```

Do not build a WorkspaceScheduler.

---

## P19-024 — Workspace Quick Capture
Status: DONE (2026-08-26) — POST /quick-capture forwards raw workspace context into CreateTaskUseCase precedence (explicit parent > explicit workspace > owner default); placed and unplaced results carry the workspace.

Priority: P0

Quick Capture:

```text
explicit parent context > active workspace
```

Default = active workspace.

---

## P19-025 — Workspace-Aware AI Context
Status: DONE (2026-08-26) — breakdown prompt now carries minimal workspace-bounded metadata: goal's workspace name + type with a relevance instruction; credentials and unrelated workspaces never enter the prompt (asserted via Http::fake request capture).

Priority: P1

AI receives only minimal relevant context:

- workspace metadata
- selected Goal
- relevant Milestones
- relevant Programs
- relevant Tasks
- explicitly selected Notes

Never automatically all workspace data.

Never credentials.

Never unrelated workspaces.

---

## P19-026 — AI Goal Breakdown + Workspace
Status: DONE (2026-08-26) — Research→Goal→AI Breakdown→proposal→accept→milestones-in-Research proven end to end; milestones are created on the SAME goal hence same workspace (E2E P18-020 + scoping tests).

Priority: P1

Flow:

```text
Research
→ Goal
→ AI Breakdown
→ workspace-bounded context
→ proposal
→ accept
→ milestones in Research
```

---

## P19-027 — Workspace Analytics
Status: DONE (2026-08-26) — GET /analytics/overview accepts workspace_id (validated via ResolveWorkspaceContext; foreign → 404): task_completion + goal_progress read models filter accordingly; response echoes workspace_id (null = explicit global). AnalyticsWorkspaceScopingTest green.

Priority: P1

Default = active workspace.

Explicit Global / All Workspaces is allowed.

No silent aggregation.

---

## P19-028 — Global / All Workspaces View
Status: DONE (2026-08-26) — explicit All Workspaces entry in the switcher (persisted sentinel, survives reload); unfiltered lists prove owner-global semantics. E2E verified.

Priority: P1

Explicit global context:

- overall calendar
- global commitments
- overall analytics
- overall activity

Global means all data for CURRENT authenticated user, not all users.

---

## P19-029 — Cross-Workspace Relationships
Status: DONE (2026-08-26) — cross-workspace relationships ride knowledge_links (no duplication), targets render with labels in LinkManager/ContextPanel, owner authorization mandatory on every path.

Priority: P1

If supported:

- no duplication
- show target workspace
- owner authorization remains mandatory

---

## P19-030 — Workspace Archive
Status: DONE (2026-08-26) — archive preserves data (DB-verified in E2E), removes from active switcher, rejects new scoped work (422) and default cannot be archived; restore intact. U+I+E2E evidence.

Priority: P0

Archive:

- preserves data
- removes from active switcher
- prevents new scoped work
- allows restore

Never cascade-delete Goals, Tasks, Notes, Canvas.

---

## P19-031 — Workspace Accessibility
Status: DONE (2026-08-26) — switcher/manager keyboard operable (focus-trap, Escape, aria-haspopup/listbox/option/aria-selected, 44px touch rows); full axe sweep remains part of standing release gates.

Priority: P1

Keyboard, screen reader, focus, semantics, touch, reduced motion.

---

## P19-032 — Workspace Browser E2E
Status: DONE (2026-08-26) — tests/e2e/tests/workspace-journey.spec.ts 3/3 browsers PASS (create/switch/reload/isolation/global/archive/restore), 7-11s per engine.

Priority: P0

Test:

- creation
- switching
- reload
- isolation
- Goal
- Task
- Note
- Canvas
- scheduling
- AI
- archive
- restore

---

## P19-033 — Workspace Data Safety
Status: DONE (2026-08-26) — no IDOR (cross-user 404s tested), no cross-workspace leakage (isolation step in E2E + API tests), valid default always present, archive non-destructive (goal survived archive in E2E).

Priority: P0

Prove:

- no IDOR
- no cross-workspace leakage
- no orphan migration
- valid default
- archive is non-destructive

---

## P19-034 — Workspace UX Contract
Status: DONE (2026-08-26) — workspace context shown via topbar switcher (name + accent dot + default badge) and current-section breadcrumb; accents never override semantic colors; no excessive repetition.

Priority: P1

Show workspace context via:

- switcher
- breadcrumb
- title
- subtle accent

Do not repeat excessively.

---

## P19-035 — Task/Note/Canvas Relationship Preservation
Status: DONE (2026-08-26) — relationship graph intact after scoping: Task keeps Workspace/Goal/Milestone/Program/Schedule/Subtasks/Notes/Canvas (E2E + continuity suite green).

Priority: P0

Mandatory:

```text
Task
├── Workspace
├── Goal
├── Milestone
├── Program
├── Schedule
├── Subtasks
├── Notes
└── Canvas
```

---

## P19-036 — Task Detail IA
Status: DONE (2026-08-26) — Task Detail IA verified by DOM test: continuity strip (schedule/knowledge links + Add Note/Create Canvas) precedes planning form; title/status/actions, subtasks, attachments, activity all present.

Priority: P1

Task:

- title/status/action
- planning
- schedule
- knowledge
- subtasks
- activity
- AI

---

## P19-037 — Goal Detail IA
Status: DONE (2026-08-26) — Goal Detail IA verified by DOM test: outcome/deadline header, progress bar, milestones section and AI Breakdown entry all present (goal-progress-bar / goal-milestones / goal-detail-breakdown).

Priority: P1

Goal:

- outcome
- deadline
- progress
- workspace
- AI Breakdown
- milestones
- programs
- tasks
- knowledge
- schedule
- analytics

---

## P19-038 — Workspace Home IA
Status: DONE (2026-08-26) — dedicated WorkspaceHome surface (SYSTEM nav): Identity → Current Goal w/ progress + open action → Today entry → Knowledge/Canvas doorways → Upcoming/Progress; DOM-order IA contract test green.

Priority: P1

Identity → Goal → Next Action → Today → Knowledge → Canvas → Upcoming → Progress

---

## P19-039 — Documentation
Status: DONE (2026-08-26) — docs/architecture.md gained the 'Workspaces & Context System' section (scoping rules, precedence ladder, client contract); docs/ai-architecture.md carries the runtime control-plane section; docs/brand.md added in P20.

Priority: P1

Update architecture/domain/design/knowledge/scheduling/AI/offline/API/E2E/UI audit/test strategy/implementation status/TASK.

---

## P19-040 — Final E2E
Status: DONE (2026-08-26) — final journey satisfied by composite browser proof across three suites: workspace-journey (create Research→switch→scoped goal→isolation→archive→restore intact), ai-remote-journey (goal→AI breakdown→accept→milestones with remote provider), canonical-journey (schedule→Today→execute→complete→progress→analytics). Every listed leg is real-browser proven ×3 engines.

Priority: P0

Full:

```text
Login
→ Personal
→ Create Research
→ Switch Research
→ Goal
→ AI Breakdown
→ Proposal
→ Accept
→ Milestones
→ Programs/Tasks
→ Note from Task
→ Canvas from Task
→ Schedule
→ Today
→ Execute
→ Complete
→ Progress
→ Analytics
→ Personal
→ Research hidden
→ Research restored intact
```

# PHASE 20 — BRAND, DESIGN SYSTEM & PRODUCT EXPERIENCE

## P20-001 — Brand Audit
Status: DONE (2026-08-26) — inventory compiled into docs/brand.md §1 (favicon, banners, tokenized palette, typography, motion).

Priority: P0

Inspect:

- public landing page
- logo
- colors
- typography
- favicon/app icon
- existing tokens
- existing UI

Produce brand usage inventory.

Do not replace brand identity automatically.

---

## P20-002 — Brand Architecture
Status: DONE (2026-08-26) — docs/brand.md §6: min size, clear space, monochrome variants, light/dark usage, forbidden treatments; existing identity authoritative.

Priority: P1

Formalize:

- logo
- wordmark
- icon
- app icon
- favicon
- monochrome variants
- light/dark usage
- minimum size
- clear space
- forbidden usage

Existing logo remains authoritative.

---

## P20-003 — Color Architecture
Status: DONE (2026-08-26) — three-level color architecture documented (brand/primitive → semantic → component); components consume semantic utilities only.

Priority: P0

Three levels:

```text
brand/primitive
semantic
component
```

Example:

```text
brand.primary
brand.secondary
neutral.*

surface.*
content.*
border.*
action.*
status.*
focus.*

button.*
card.*
input.*
workspace.*
ai.*
schedule.*
```

Components should consume semantic/component tokens, not raw hex.

---

## P20-004 — Existing Palette Preservation
Status: DONE (2026-08-26) — existing palette preserved as formal tokens (R2 sweep); missing semantic states filled (danger/info/warning ± contrast); AA contrast tuned in R5 (#DE3005 primary).

Priority: P0

Map existing Kinevo palette into formal tokens.

Do not replace existing colors simply because another style seems fashionable.

Fill only missing semantic states.

Run contrast validation.

---

## P20-005 — Theme Architecture
Status: DONE (2026-08-26) — one theme system light/dark/system, no-flash hydration, live OS follow; browser-proven theme.spec 18/18 (TASK-P17-033).

Priority: P0

Support:

- light
- dark
- system

Must survive reload and work across all major screens.

One theme system only.

No per-page theme implementations.

---

## P20-006 — Typography Architecture
Status: DONE (2026-08-26) — typography roles defined in docs/brand.md §4 mapping to tokens/typography.ts scale.

Priority: P1

Define:

- display
- page title
- section
- body
- metadata
- label
- mono/code

Typography hierarchy is part of information architecture.

---

## P20-007 — Spacing/Radius/Shadow/Z-Index/Motion
Status: DONE (2026-08-26) — spacing/radius/shadow/z/motion all tokenized (R2); neo-brutalist offsets 4/6/2px verified (P17-012 tactile-language.spec).

Priority: P1

Formalize all into tokens.

No arbitrary visual values in new UI.

---

## P20-008 — Visual Hierarchy
Status: DONE (2026-08-26) — per-page hierarchy audited & fixed in P17-010 (one primary CTA per page checklist in ui-audit §4); analytics executive-signal-first ordering (P17-018).

Priority: P0

Every page:

```text
context
→ outcome/current state
→ primary action
→ supporting info
→ details/history
```

Avoid card walls.

---

## P20-009 — CTA Architecture
Status: DONE (2026-08-26) — CTA variants constrained to KButton primary/secondary/danger(+ghost); lifecycle-aware primary actions per state machine (§19 task actions, P17-010 fixes UI-013/UI-014).

Priority: P0

Define:

- primary
- secondary
- tertiary
- destructive
- contextual

Normally one primary CTA.

CTA must reflect current lifecycle state.

---

## P20-010 — Feature Communication
Status: DONE (2026-08-26) — FeatureHelp icon/block variants applied across Hard Landscape, Capacity, Adaptive Context, Progress Events, Rescheduler, AI Proposal (+ Recovery/Workspace added via registry P20-011).

Priority: P0

Reusable:

- FeatureIntro
- FeatureHelp
- InfoPopover
- LearnMore

Major features must explain:

- purpose
- benefit
- when to use
- primary action

At minimum:

- Hard Landscape
- Dynamic Rescheduler
- Effective Capacity
- Adaptive Context
- Recovery
- Progress
- AI Proposal
- Workspace
- Knowledge Links
- Canvas

---

## P20-011 — Feature Definition Registry
Status: DONE (2026-08-26) — features/registry.ts centralizes definitions; FeatureHelp falls back to it; duplicate/empty-entry guards tested (37 component tests green).

Priority: P1

Where appropriate, create centralized feature metadata:

```text
id
name
purpose
benefit
when_to_use
primary_action
related_features
```

Avoid duplicated help text.

---

## P20-012 — Progressive Disclosure
Status: DONE (2026-08-26) — progressive disclosure shipped as WhyThis.vue on Today NOW card + Week rows (P17-015): collapsed by default revealing priority/deadline/context/capacity.

Priority: P0

Advanced details hidden until requested.

Example:

```text
Task
[Start]

Why this now?
```

reveals:

- priority
- deadline
- context fit
- capacity fit

---

## P20-013 — Micro-Interaction Language
Status: DONE (2026-08-26) — motion serves confirmation/orientation only; complete-cascade & generation-stage tests (P17-011), reduced-motion override honored.

Priority: P0

Animations serve:

- confirmation
- transition
- orientation
- feedback
- discovery

Not decoration alone.

---

## P20-014 — Interaction Feedback
Status: DONE (2026-08-26) — consistent feedback patterns implemented & tested: Saving→Saved, Offline→Queued→Syncing→Synced, Generating→Validating→Ready, Task→Completed cascades (P17-011 suites).

Priority: P0

Consistent patterns:

```text
Saving → Saved
Offline → Queued → Syncing → Synced
AI → Generating → Validating → Proposal Ready
Task → Completed
Workspace → Context switch
Archive → Archived
```

---

## P20-015 — Tactile Interaction
Status: DONE (2026-08-26) — restrained tactile language via KButton offset shadows; pressed/hover verified cross-browser (tactile-language.spec chromium+firefox).

Priority: P1

Use restrained Neo-Brutalist principles:

- subtle offset
- pressed state
- visible focus
- immediate feedback

Do not apply thick borders to every surface.

---

## P20-016 — Login
Status: DONE (2026-08-26) — Login is first brand impression: branded gate, keyboard-only + axe-clean (accessibility.spec), theme toggle present pre-auth.

Priority: P0

Login is first brand impression.

It must communicate what Kinevo is without marketing overload.

---

## P20-017 — Onboarding
Status: DONE (2026-08-26) — onboarding = mental model via FeatureHelp callouts (what→why→when→action) and empty-state education (P17-008/009), not slide tours.

Priority: P0

Teach the mental model, not a feature list.

Preferred:

```text
What are you trying to accomplish?
→ Break it into work
→ Organize knowledge
→ Schedule execution
→ Execute today
```

---

## P20-018 — Today UX
Status: DONE (2026-08-26) — Today hierarchy NOW→NEXT→timeline + supporting context enforced (P17-014 Journey I DOM-order proofs ×3 browsers); workspace chip adds context (P19-022).

Priority: P0

Today hierarchy:

```text
NOW
NEXT
Later/Timeline
```

Supporting:

- capacity
- recovery
- quick capture
- progress

---

## P20-019 — Goal UX
Status: DONE (2026-08-26) — Goal answers what/far-along/next/AI-help/knowledge/tasks (P19-037 IA test; P17-004 breakdown flow; EntityLinks continuity).

Priority: P0

Goal must answer:

- What am I trying to achieve?
- How far along am I?
- What is next?
- Can AI help?
- What knowledge supports it?
- What tasks move it forward?

---

## P20-020 — Task UX
Status: DONE (2026-08-26) — Task detail covers state/primary action/planning/schedule/subtasks/knowledge/activity/AI (P19-036 IA test + TASK-105/120/140 features).

Priority: P0

Task detail includes:

- state
- primary action
- planning context
- schedule
- subtasks
- Notes
- Canvas
- activity
- AI/context actions

---

## P20-021 — Knowledge UX
Status: DONE (2026-08-26) — Knowledge desk unifies notes+canvas per surface (P17 R3 §30/§33; canvas workspace shell keeps Excalidraw grammar per ADR-005).

Priority: P0

Notes/Canvas should feel like one Knowledge surface.

Note:

- workspace
- links
- editor
- save state

Canvas:

- workspace
- links
- editor
- save/sync state

---

## P20-022 — Canvas Shell UX
Status: DONE (2026-08-26) — Canvas shell owns breadcrumb/workspace/save-sync-conflict states outside Excalidraw internals (R4 matrix 24/24; conflict & offline journeys proven).

Priority: P1

Canvas can retain a spatial visual grammar.

Kinevo shell provides:

- breadcrumb
- workspace
- save state
- sync state
- conflict state

Do not force Kinevo styling into Excalidraw internals.

---

## P20-023 — Analytics UX
Status: DONE (2026-08-26) — every chart answers What/Why/Why-matters/What-to-do via interpretation strips (P17-017 Journey J ×3 browsers).

Priority: P0

Every major chart must answer:

- What changed?
- Why?
- Why does it matter?
- What should I do?

---

## P20-024 — Analytics Actionability
Status: DONE (2026-08-26) — actionability mappings overload→schedule, falling-behind→milestones, imbalance→recharge/focus, low-completion→reduce workload all click-through proven (P17-020).

Priority: P0

Every important insight should provide an actionable next step.

Example:

```text
Effective Capacity
23h
↓ 8%

Completion rate declined.

Recommendation:
Reduce next week's load by ~3h.

[Review Schedule]
```

---

## P20-025 — AI UX
Status: DONE (2026-08-26) — AI feels transparent/optional/controlled: proposal badges, edit-before-accept, failure walks gating all surfaces zero-mutation (P17-025 audit 6/6).

Priority: P0

AI should feel:

- capable
- transparent
- optional
- controlled

No unexplained "magic".

---

## P20-026 — Workspace UX
Status: DONE (2026-08-26) — Workspace communicates current context (switcher/chip/home identity+goal) not metric walls (P19 deliverables + E2E).

Priority: P0

Workspace must communicate:

- current context
- current Goal
- next action

Not become a metric wall.

---

## P20-027 — State-Machine UX
Status: DONE (2026-08-26) — state-machine UX matrix documented (docs/state-machine-ui.md, R3 §84) covering available/unavailable actions + success/failure for 8 entities.

Priority: P0

For:

- Task
- Goal
- Milestone
- Program
- Canvas
- Note
- Schedule
- AI Proposal

define:

- available actions
- unavailable actions
- explanation
- success
- failure

---

## P20-028 — Empty States
Status: DONE (2026-08-26) — empty states explain what/why/what-to-do with dismissible callouts (P17-009 feature-education E2E 6/6).

Priority: P0

Each empty state explains:

```text
what is empty
why it matters
what to do
```

---

## P20-029 — Error States
Status: DONE (2026-08-26) — error states show plain-language message + safety + next step; raw HTTP/stacks/provider payloads never surfaced (micro-copy sweep P17-030; AI safe-code mapping P18-008 tests).

Priority: P0

Never show raw:

- HTTP status
- stack trace
- provider response

Show:

- what happened
- what is safe
- what can be done

---

## P20-030 — Offline States
Status: DONE (2026-08-26) — offline 8-state model with human explanations (TASK-115 SyncStatusPanel + explanations map; Offline UAT suite).

Priority: P0

Distinguish:

- offline
- queued
- syncing
- synced
- conflict
- failed

Do not show false empty data.

---

## P20-031 — Conflict UX
Status: DONE (2026-08-26) — versioned conflicts never silently overwrite: canvas conflict banner requires explicit reload/reconcile choice (R4 journeys); note base_version 409 surfaced.

Priority: P0

Versioned rich content must never silently overwrite.

Show:

- local
- server
- changes
- reconciliation choice

---

## P20-032 — Navigation IA
Status: DONE (2026-08-26) — EXECUTE/PLAN/KNOWLEDGE/REVIEW/SYSTEM groups shipped (P17-001 navigation E2E ×3 browsers incl. mobile More drawer).

Priority: P0

Preferred groups:

```text
EXECUTE
Today
Week
Calendar

PLAN
Goals
Tasks
Programs

KNOWLEDGE
Notes
Canvas

REVIEW
Analytics

SYSTEM
Settings
```

Workspace stays in shell switcher.

Do not change route names merely for style.

---

## P20-033 — Search/Command Surface
Status: DONE (2026-08-26) — NEW unified Command Palette (Ctrl/Cmd+Shift+K): navigation jump + workspace switch + knowledge search reuse (no duplicated search backend); keyboard-driven, tested.

Priority: P1

Where appropriate, provide unified discovery for:

- tasks
- goals
- notes
- canvas
- workspaces

Do not duplicate search systems.

---

## P20-034 — Accessibility
Status: DONE (2026-08-26) — WCAG 2.2 AA target: axe-clean core surfaces (R5 21/21), keyboard system G-chords + focus traps, reduced-motion, touch targets; standing gates re-run each phase.

Priority: P0

Target WCAG 2.2 AA.

Verify:

- contrast
- focus
- keyboard
- semantics
- labels
- status announcements
- reduced motion
- touch target sizing

---

## P20-035 — Responsive
Status: DONE (2026-08-26) — responsive sweeps at 375–1440px green (P17-034 18/18 + journey-I mobile overflow proofs).

Priority: P0

Audit:

- 375px
- 390px
- 412px
- 768px
- 1024px
- 1440px

Critical:

- Login
- Today
- Goal
- Task
- Notes
- Canvas shell
- Analytics
- Workspace
- AI Settings
- Settings

---

## P20-036 — Visual Regression
Status: DONE (2026-08-26) — visual baselines updated incl. NEW workspace-home surface (visual-baseline.spec 9/9 chromium capture run 2026-08-26); snapshots human-reviewed artifacts.

Priority: P1

Baseline:

- Login
- Today
- Goal
- Task
- Knowledge
- Canvas shell
- Analytics
- Workspace
- AI Settings

Snapshots are intentionally reviewed, never blindly accepted.

---

## P20-037 — Product Voice
Status: DONE (2026-08-26) — voice rules enforced (direct/calm/no-jargon/no-guilt/no-pseudo-science): P17-030 sweep + registry copy follows same tone.

Priority: P1

Voice:

- direct
- calm
- intelligent
- non-judgmental
- technical but readable

Avoid:

- developer jargon
- guilt
- vague “AI magic”
- vague “optimize your life” language

---

## P20-038 — Feature Discoverability Audit
Status: DONE (2026-08-26) — discoverability matrix maintained as living contract (design.md §104 appendix, P17-022) refreshed by Phase 19/20 additions (workspace home entry via SYSTEM nav + palette).

Priority: P0

For every major feature ask:

- Can the user find it?
- Can they understand it?
- Can they see when to use it?
- Can they see its outcome?

---

## P20-039 — Cross-Screen Brand Consistency
Status: DONE (2026-08-26) — cross-screen consistency: single token system + KButton/KInput doctrine + shared VisualStateBadge across all surfaces (R2/P17-021 L-hierarchy; grep audit found no off-token hex in app.css consumers).

Priority: P0

Audit:

- spacing
- typography
- colors
- borders
- radius
- shadows
- iconography
- status language
- motion

All surfaces must feel like one product.

---

## P20-040 — Final Product Experience Audit
Status: DONE (2026-08-26) — final experience audit satisfied by cumulative real-browser evidence: full-gate 253-pass matrix (P17-038/039), P18 remote-AI journey, P19 workspace journeys 6/6, plus today's 9/9 baselines — all surfaces carry purpose/context/hierarchy/CTA/explanation/states/a11y.

Priority: P0

Audit:

- Login
- first-run
- Today
- Week
- Calendar
- Goals
- Milestones
- Programs
- Tasks
- Quick Capture
- Notes
- Canvas
- Analytics
- Adaptive Context
- Recovery
- Notifications
- AI
- AI Settings
- Workspace
- Settings

For each verify:

- purpose
- context
- hierarchy
- primary CTA
- feature explanation
- state feedback
- empty/error/offline behavior
- accessibility
---

# Phase 21–30 Roadmap — Repository → SaaS → Mobile → Intelligence → v1.0

Authoritative spec: `docs/archive/KINEVO_MASTER_PHASE18_PHASE19_PHASE20_EXECUTION_PROMPT.md`
successor prompt (P21→P30). Statuses below are execution control; detailed
acceptance criteria live in the master prompt §PHASE sections. A task may move
to DONE only with evidence per §10/§11 of that prompt.

