# Kinevo — UI/UX Audit

> **Document role:** Living gap/P0–P3 audit for the UI/UX stabilization effort.
>
> **Status:** Baseline declared (lifecycle: ACTIVE). Assessments filled in
> during rescue Phase R2 (Bug taxonomy) and refreshed on every UI PR.
>
> **Contract:** `docs/design.md` §70, §77, §85, §86, §92; SRS §14.

---

## 1. Purpose

Track exactly where the current UI diverges from the design system baseline, and
classify every defect so rescue work is prioritized. This document is the output
of `docs/design.md` §77 (R2 — build a bug taxonomy) and feeds the R7 design
consistency audit (§85).

## 2. Claim baseline

The current implementation is verified at the **contract level** (feature tests,
typecheck, build, adapter mocks). Real-browser UX assertions do NOT yet exist
(`tests/e2e/` is not set up). Per `docs/design.md` §74:

> **DONE means "implementation contract verified", not necessarily "real browser
> UX is production-ready".**

Until the R1 browser smoke run, every surface below is **NOT ASSESSED** on the
user-visible axis even if its feature tests pass.

## 3. Bug taxonomy (design.md §77)

| Class | Definition                                                        | Effect            |
| ----- | ----------------------------------------------------------------- | ----------------- |
| P0    | data loss; cannot authenticate; cannot save; cannot open core feature; canvas crashes; offline mutation lost | Blocks all feature work |
| P1    | feature unusable; wrong schedule; wrong state; navigation broken; major UX confusion | Blocks release     |
| P2    | visual inconsistency; minor workflow friction; poor copy          | Scheduled cleanup  |
| P3    | cosmetic; enhancement                                             | Backlog            |
P0 findings block all feature work. No findings may be silently closed; each has
a record (format §6).
### Product-experience classes (Phase 17, design.md §104 / TASK-P17-031)
Software bug and product-experience bug are distinct. Classify experience
problems separately so they are fixed as UX, not patched as code failures.
| Class | Definition                                             | Example                                    |
| ----- | ------------------------------------------------------ | ------------------------------------------ |
| UX-C1 | workflow broken (a flow cannot complete)               | create goal then AI breakdown unreachable  |
| UX-C2 | workflow unclear (user does not know the next step)    | no "next action" surfaced                   |
| UX-C3 | feature undiscoverable (no entry point or explanation) | AI provider settings hidden                 |
| UX-C4 | visual inconsistency (hierarchy/affordance violated)   | five equal CTAs; everything boxed          |
| UX-C5 | micro-interaction missing (no state feedback)          | save has no Saving→Saved confirmation       |
| UX-C6 | information hierarchy problem (wrong emphasis)         | analytics shows 15 charts before signal     |
UX-C findings follow the same record/close rules as P0–P3.

## 4. Surface audit matrix

Scoring per surface uses the `docs/design.md` §70 design-QA dimensions. Status
legend: `⛔ P0 found · 🔴 P1 found · 🟡 P2 · ⚪ not assessed · ✅ clear`.

> 2026-08-22 (TASK-R3): Today capacity became a load bar with click-reveal (§22);
> a lightweight adaptive-context check-in landed (§23); a notification center with
> Unread/Today/Earlier groups was wired into the topbar (§28–§29); TaskDetail gained
> a primary action per state (§19); Goal cards/detail gained a dominant progress
> bar + milestone roadmap glyphs (§17/§39); Knowledge desk added the §31 toolbar and
> the linked-entities desktop sidebar (§30/§33).
>
> 2026-08-22 (TASK-R5): axe-core WCAG 2.2 A/AA scans + keyboard-only flow +
> emulated reduced-motion proven in real browsers (chromium/firefox/webkit,
> `tests/e2e/tests/accessibility.spec.ts`); keyboard/reduced-motion rows
> flipped to ✅ on Shell/Today/Task/Goal/Knowledge. Canvas keeps 🟡 until its
> own keyboard-only flow is walked (R7). See UI-010 for the fixed defects.
>
> 2026-08-22 (TASK-R7): Canvas keyboard-only flow walked in a real browser
> (`tests/e2e/tests/release-gate.spec.ts`) — Canvas keyboard 🟡 → ✅. Dark-mode
> axe scans clean on Today/Knowledge/Canvas shell after UI-011 fix. Mobile
> 375px smoke clean on all primary surfaces after UI-012 fixes. Screen-reader
> live-region smoke green (save/sync states announced, bell named).
>
> 2026-08-24 (TASK-P17-034 width sweep): full-surface overflow audit at
> 375/390/412/768/1024/1440 (`tests/e2e/tests/mobile-sweep.spec.ts`,
> navigation via the real mobile bottom bar + More drawer). One new UI-012-class
> defect found and fixed: the note editor header bled up to 68px at 375w — it
> now wraps and the title input flexes. Sweep green 18/18 across browsers.

> 2026-08-23 (TASK-P17-010, UX hierarchy audit): every major page audited for
> the ONE-primary-CTA rule (design.md §2.3, §51). Violations fixed:
>
> - GoalListView showed up to THREE simultaneous primaries (Create goal,
>   Generate-with-AI panel, Add program). Now stage-based: Create is primary
>   until a goal is created; while the breakdown suggestion is open it becomes
>   the single primary and Create demotes to secondary; program `Add` is
>   secondary.
> - TaskDetailView had two primaries (edit Save + status transition). The
>   state transition stays the page's one primary (§19); edit Save demoted to
>   secondary.
>
> Findings logged without code change (P17-012 polish territory): scheduler
> draft/reschedule pages drive their main flow (generate/propose → apply)
> through unstyled raw buttons — no visual primary at all on those pages;
> Canvas delegates all CTAs to Excalidraw's own toolbar (accepted boundary).
>
> Per-page CTA checklist (primary / secondary / verdict):
>
> | Page | ONE primary | Secondary | Verdict |
> | ---- | ----------- | --------- | ------- |
> | Login/Register | Log in · Register | First-time link | ✅ |
> | Today | Start (state-driven) | Complete/Pause/Break | ✅ |
> | Week | — (read-only planner) | day navigation | ✅ no competing CTAs |
> | Calendar | — (read-only) | view controls | ✅ |
> | Goals list | Create ⇄ Generate with AI (staged) | manual-breakdown option | ✅ after fix |
> | Goal detail | Accept proposal / state action | edit milestones inline | ✅ |
> | Tasks list | Add task | filter/status controls | ✅ |
> | Task detail | Status transition (per state) | edit form Save, secondary transitions | ✅ after fix |
> | Schedule draft | Generate → Apply (staged, tactile) | Cancel, Dynamic Reschedule | ✅ after UI-013 fix |
> | Reschedule | Propose → Apply (staged, tactile) | Back, Cancel | ✅ after UI-013 fix |
> | Knowledge/Notes desk | New note | toolbar actions | ✅ |
> | Canvas | Excalidraw-owned tools | Kinevo shell chrome | ✅ external boundary |
> | Analytics | — (read-only) | range/pillar filters | ✅ |
> | Settings / AI & Providers | Save provider | Test connection | ✅ |
> | Imports / Exports | Run import / export | file pickers | ✅ |
>
| Dimension | Shell | Today | Task | Goal | Knowledge/Notes | Canvas | Analytics | Settings |
| --------- | ----- | ----- | ---- | ---- | --------------- | ------ | --------- | -------- | -------- |
| Visual consistency (§85) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Keyboard navigation (§45) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | 🟡 |
| Responsive layout (§8, §58) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Loading / skeleton (§11.1) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Empty state (§11.2) | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ |
| Error state (§11.3) | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ |
| Offline (§11, §90) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Conflict / save state (§2.5, §34.4) | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ | ⚪ | ✅ |
| Dark mode (§5.4) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Reduced motion (§47) | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | ⚪ | 🟡 |
| Primary action obvious (§2.3, §51) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅¹ | ✅ | ✅ |
| No color-only state (§5.2) | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ |
| Token usage (§65, design-tokens.md) | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ |
| Component consolidation (§50, §80) | ✅ | ✅ | 🟡 | 🟡 | ✅ | ⚪ | ⚪ | ⚪ |
>
> 2026-08-23 (TASK-P17-011, micro-interaction system, design.md §104):
> the four feedback cascades now exist and are tested:
>
> - **Task complete** — Complete button snaps while the request runs
>   (`ExecutionTimer`), Today reloads (progress advance), an activity toast
>   confirms ("Task completed · progress updated", polite live region), and
>   the NEXT task gets a 2s ring spotlight. Proven in real browsers by
>   `core-loop.spec.ts` (chromium/firefox/webkit).
> - **Save** — shared `useSaveState` (Saving… → Saved ✓ → idle); wired into
>   the adaptive check-in panel and Boost dialog (+toast). AI provider
>   settings already had its saved confirmation.
> - **Offline** — `sync-status.ts` bridge already maps offline/queued/
>   syncing/saved with aria-live announcements; Journey E covers
>   queued→synced end-to-end. No code change needed.
> - **AI generation** — shared `useGenerationStages` cycles honest stage
>   labels (Preparing context… → Generating… → Validating…) on GoalList and
>   GoalDetail breakdown buttons; completion stays server-truth (proposal
>   ready / error), never faked by the timer.
>
> Matrix flips: Shell + Settings "Conflict / save state" → ✅; Today → ✅
> (complete cascade + Saved ✓ + toasts). Task/Goal/Knowledge per-form save
> flashes remain ⚪/🟡 pending the P17-012 token pass.
>
> 2026-08-23 (TASK-P17-012, neo-brutalist interaction polish): audited the
> offset-shadow language against design-tokens.md — tokens existed
> (--shadow-rest/hover/active = 4/6/2px) and KButton already carried them;
> the gap was the scheduler pages still using raw buttons (UI-013). All
> transition durations verified within 100–250ms (Tailwind default 150ms;
> snap 180ms); reduced-motion collapse rules intact and re-proven by the
> accessibility suite. No new visual noise: quiet variants stay flat.
> Matrix flips: schedule draft/reschedule CTA hierarchy 🟡 → ✅ (UI-013
> closed).
>
> 2026-08-23 (TASK-P17-021, UX-C4 shared hierarchy): the five-level surface
> system (Hero/Primary/Secondary/Supporting/Metadata) landed as tokenized
> utilities (`app.css` `@layer components`, design-tokens.md §4a) — weight
> concentrates on Hero/Primary; Supporting groups are OPEN (hairline +
> whitespace, never boxed). Analytics adopted as reference surface: summary/
> goals/capacity/execution = L2 Primary; pillars/heatmap/per-day de-boxed to
> L4; section rhythm space-4 → space-6. Off-token gray card borders replaced
> by theme-var borders. Real-browser audit + light/dark screenshots:
> `tests/e2e/tests/analytics-hierarchy.spec.ts` (6 passed, artifacts under
> `tests/e2e/test-results/screenshots/<browser>/p17-021-analytics-*.png`).
> UX-C4 "everything boxed" is closed for the analytics surface; remaining
> surfaces adopt the L-system incrementally (no re-boxing of open groups).


¹ Canvas: audited 2026-08-23 (P17-010) — page-level chrome has no competing primaries; drawing tools are Excalidraw-owned (§104 external engine boundary).

## 5. Component inventory checklist (design.md §50, §80)

Design-system components that MUST exist centrally (no per-page re-implementations):

| Component | Exists centrally | Where detected |
| --------- | ---------------- | -------------- |
| Button / IconButton | ✅ | `components/KButton.vue` (primary/secondary/danger/ghost) |
| Input / Textarea / Select / Combobox | 🟡 | `components/KInput.vue` exists; Select/Textarea/Combobox not yet centralized |
| Checkbox / Switch | ⚪ | — |
| Badge / VisualStateBadge | ⚪ | — |
| Card / Panel | ⚪ | — |
| Modal / Drawer | ⚪ | — |
| Tabs / Tooltip / Dropdown | ⚪ | — |
| Toast | ✅ | `components/toast.ts` + `components/ToastHost.vue` (P17-011) |
| ProgressBar | ⚪ | — |
| Timeline / CalendarGrid | ⚪ | — |
| EmptyState / ErrorState | ⚪ | — |
| Skeleton | ⚪ | — |
| CommandPalette / ConfirmDialog | ⚪ | — |

Known partial baseline (from repository inventory, to be re-verified in R2):

- `visualstate/VisualStateBadge.vue` — state badge vehicle.
- `today/EmergencyPauseDialog.vue`, `today/BreakModeDialog.vue` — confirm-style dialogs.
- `shell/AppShell.vue`, `shell/SyncStatusPanel.vue` — shell + persistence state.
- `canvas/CanvasHost.vue` — Vue→React→Excalidraw boundary.
- `editor/EditorHost.vue` — Tiptap boundary.

## 6. Finding record format

```text
UI-nnn | @date | <surface> | <class>
Title
Observed behavior
Expected (design.md §...)
Repro (steps / browser / E2E run id)
Severity justification
Status (open / fixed / triaged / accepted)
Link (test, PR, screenshot, issue)
```

`<class>` is the §3 taxonomy value: a software-bug class (`P0`–`P3`) or,
since TASK-P17-031, a product-experience class (`UX-C1`–`UX-C6`). A finding
may carry both when a code defect causes an experience problem — record the
experience class too so the fix is verified as UX, not just as code.

No finding is closed silently; closing requires evidence (test, browser run,
visual baseline). UX-C findings flow through this same record and close with
the same evidence rules.

### 6.1 Active findings (rescue R1 baseline)

```text
UI-001 | 2026-08-21 | Canvas (workspace) | P2
Canvas behavior unverified in a real browser
Gap: only the Vue canvas shell / list mount was exercised in the R1 Chromium run.
The React/Excalidraw island (draw/text/move/delete/undo/save) is only covered by
adapter tests that mock the island.
Expected: design.md §34–§36, §72 canvas matrix proven in a browser.
Repro: R1 E2E navigates to Canvas and asserts the shell renders; drawing and
persistence are not yet driven in-browser.
Severity: P2 (feature usable at contract level; browser behavior unproven).
Status: open (extends into TASK-R4 Canvas Hardening).
Link: tests/e2e/tests/journeys.spec.ts; docs/browser-e2e.md §5.

UI-002 | 2026-08-21 | Offline (global) | P2
Offline/reconnect journey unverified in a real browser
Gap: no browser run covers go-offline → capture/edit → reconnect → sync.
Expected: design.md §11, §32, §34.6, §90; docs/browser-e2e.md §7 Journey E.
Repro: R1 E2E has no offline scenario.
Severity: P2 (offline is in scope; browser proof pending).
Status: closed (2026-08-25) — journey-c-e.spec.ts Journey E drives
go-offline → draw → queued → reconnect → synced through the real adapter
boundary; offline http-applier queue/LWW units green; green across all three
browser projects in the full gate.
Link: docs/browser-e2e.md §7 (Journey E).

UI-003 | 2026-08-21 | Task / Goal / Note (workspaces) | P2
Deep create/edit flows unverified in a real browser
Gap: R1 only asserts each surface renders its list. Task/goal/note create,
edit, link, and schedule-side effects are unproven in-browser.
Expected: design.md §19–§21, §30–§33; golden journeys A/C/D.
Repro: R1 E2E navigation assertions only.
Severity: P2.
Status: closed (2026-08-25) — deep create/edit/transition flows are
browser-proven by core-loop (LOGIN→NOW→START→COMPLETE), canonical-journey
(goal→AI breakdown→accept→program→task→schedule→today→analytics),
connectivity walk, and golden journeys; green across chromium/firefox/webkit
in the full gate.
Link: tests/e2e/tests/journeys.spec.ts.

UI-004 | 2026-08-21 | Visual system (global) | P2
Hard-coded visual values not yet centralized
Gap: 96+ color literals (primary #F53003, #FDFDFC, #0a0a0a, etc.) and inline
spacing/radius/shadows are scattered across components (found in the R2 color
scan). No shared button/input components yet.
Expected: design.md §65–§66, §50–§51; design-tokens.md.
Repro: repo-wide search for hex literals and repeated button markup.
Severity: P2 (visual inconsistency, high churn).
Status: triaged — R2 token modules (`server/resources/js/tokens/`), CSS
hydration (app.css), and component library v0 (KButton/KInput) land as a single
commit 2026-08-21; migrating the remaining surfaces to tokens/components is
TASK-R3. Partial close in R3: primary-action buttons and create-form inputs on
Today, Task, Goal, and Knowledge/Notes now use KButton/KInput (commit 2026-08-21).
Remaining hard-coded small/context buttons, `select`/`textarea`/native inputs on
minor surfaces, and the KInput `select` gap carry forward.
Link: this task (TASK-R2), docs/design-tokens.md.

UI-005 | 2026-08-21 | Browser matrix (global) | P2
Firefox and WebKit coverage missing
Gap: R1 runner projects Chromium only.
Expected: design.md §71; docs/browser-e2e.md §4 (Firefox/WebKit ⚪).
Repro: R1 E2E Chromium-only.
Severity: P2.
Status: closed (2026-08-25) — playwright.config.ts defines chromium/firefox/
webkit projects; the full gate matrix runs all three (253 passed / 0 failed /
5 skipped, TASK-P17-039 record in docs/browser-e2e.md).
Link: TASK-R1, tests/e2e/playwright.config.ts.

UI-006 | 2026-08-21 | Diagnostics | P2
No runtime diagnostics surface
Gap: no dev-only panel to inspect API/Auth/Offline/Canvas/Tiptap/Scheduler state,
and no /dev/canvas-diagnostics route.
Expected: design.md §78, §36.
Severity: P2.
Status: fixed — dev-only DiagnosticsPanel + useDiagnostics + offline/diagnostics
helpers landed in TASK-R2 (commit 2026-08-21); visibility gated to
import.meta.env.DEV so production bundles exclude it. Closing evidence: vitest
diagnostics suite; /dev/canvas-diagnostics in-browser route deferred to R4
where the canvas island boundaries are the focus.
Link: this task (TASK-R2).

UI-007 | 2026-08-21 | Shell (navigation) | P3
Flat navigation was a single ungrouped menu
Observed: nav rendered one flat list; no mental-purpose grouping.
Expected: design.md §9 — EXECUTE / PLAN / KNOWLEDGE / REVIEW / SYSTEM groups.
Severity: P2 (visual/information architecture; not a functional blocker).
Status: fixed — `shell/navigation.ts` exposes `NAV_GROUPS`; desktop nav renders
grouped sections with group labels; topbar adds current-section breadcrumb
(design.md §10.1); tests extended. Evidence: `navigation.test.ts`, `AppShell.test.ts`, `vue-tsc`.
Link: TASK-R3.

UI-008 | 2026-08-21 | Canvas (entry state, lazy-load, diagnostics) | P2
Canvas loaded in the main bundle and could boot to a blank page
Gap: built with Excalidraw statically bundled (main chunk > ~500 kB warning) and
no explicit loading/ready/failure entry states; `/dev/canvas-diagnostics` route
absent.
Expected: design.md §34.2, §36, §89.
Severity: P2 (bundle weight + diagnostic debuggability).
Status: fixed — `CanvasView` lazy-loads the workspace chunk via
`defineAsyncComponent` (build emits `CanvasWorkspaceView-*.js`; main `app-*.js`
no longer contains `@excalidraw`); `CanvasHost` surfaces loading/ready/error
entry states with Retry / Open read-only and never renders blank;
`/dev/canvas-diagnostics` web route guarded against `production` (abort 404).
Evidence: `CanvasHost.test.ts` (3), `CanvasDiagnosticsRouteTest.php` (2), build
code-split output, `vue-tsc`.
Link: TASK-R4.

UI-009 | 2026-08-21 | Global (accessibility) | P2
No global keyboard system, reduced-motion handling, or focus management
Gap: no global shortcuts (§46); dialogs had focus + neither focus trap nor
Escape-close; no skip link; duplicated nav landmark labels.
Expected: design.md §45–§47; WCAG 2.2 2.4.1 / 2.4.7 / 2.1.2.
Severity: P2 (WCAG AA; not a functional blocker).
Status: fixed — `shell/keyboard.ts` G-chord navigation + Cmd/Ctrl+K quick capture
(ignores editable targets); `shell/focus-trap.ts` focus trap + Escape-close +
focus restore applied to Break/Boost/Emergency dialogs; `app.css` global
`:focus-visible` outline + `prefers-reduced-motion` override; skip-to-content
link + `#main-content`; distinct mobile-nav aria-label; KButton 44px min-height.
Evidence: `keyboard.test.ts` (4), `focus-trap.test.ts` (4), `AppShell.test.ts`
(a11y landmark tests), `vue-tsc`, `vitest`.
Link: TASK-R5. Real-browser keyboard/reduced-motion proof landed 2026-08-22
(see UI-010); §4 keyboard/reduced-motion rows flipped to ✅ on the audited
surfaces.

UI-010 | 2026-08-22 | Global (WCAG 2.2 AA audit) | P2
axe-core found real AA failures: notification bell had no accessible name
(button-name, critical); nav-group labels, Today timeline hour labels and two
submit buttons failed color-contrast (gray-400/#F53003 text on light bg);
QuickCapture modal lacked dialog semantics (no role/aria-modal/focus trap/
Escape); save-state + sync-state transitions were visual-only (no live
regions).
Expected: design.md §45–§47; WCAG 2.2 1.1.1 / 1.4.3 / 2.1.2 / 4.1.2 / 4.1.3.
Severity: P2.
Status: fixed — bell `aria-label`; QuickCapture given full dialog parity via
`shell/focus-trap.ts`; `--color-primary` deepened #F53003 → #DE3005
(white-on-primary 4.63:1; design-tokens.md updated); all hardcoded error
text/borders moved to `text-danger`/`border-danger` tokens (#D20812 light
5.45:1, #FF5A4E dark 5.88:1); nav/timeline labels gray-400 → gray-600;
`role="status" aria-live="polite"` on canvas save badge + SyncStatusPanel.
Evidence: `tests/e2e/tests/accessibility.spec.ts` — axe-core WCAG 2.2 A/AA
scans of login + Today + Task + Goal + Knowledge + Canvas shell all clean,
keyboard-only login/G-chords/Cmd+K flow, emulated prefers-reduced-motion —
21/21 across chromium/firefox/webkit; vitest 386.
Link: TASK-R5. Canvas-surface keyboard-only flow and screen-reader AT smoke
remain R7 rows.
```

```text
UI-011 | 2026-08-22 | Global (dark mode, WCAG contrast) | P2
Gap: the danger KButton paired white text with bg-danger. In dark mode
--color-danger is #FF5A4E; white-on-#FF5A4E is ~2.6:1 — axe-core flagged
color-contrast (serious) on Today's danger action in emulated dark mode.
Expected: WCAG 2.2 1.4.3; design-tokens.md §2.4.
Severity: P2.
Status: fixed — new `--color-danger-contrast` token (#FFFFFF light,
#1D0002 dark) applied to the danger button variant; dark-mode axe scans of
Today/Knowledge/Canvas shell now clean.
Evidence: tests/e2e/tests/release-gate.spec.ts (R7 dark-mode scans);
design-tokens.md updated.
Link: TASK-R7.

UI-012 | 2026-08-22 | Shell / SyncStatusPanel / Canvas workspace (responsive §58) | P2
Gap: at 375px three surfaces scrolled horizontally: (a) topbar theme-toggle
pushed past the viewport; (b) mobile bottom nav items could not shrink below
label width ("Schedule"/"Settings" clipped); (c) the canvas workspace header
rendered back/title/save/theme/readonly/archive as one non-wrapping row;
(d) SyncStatusPanel's full explanation prose (~340px) forced overflow on
Tasks/Goals.
Expected: design.md §58 responsive layout — no horizontal scroll on primary
surfaces.
Severity: P2.
Status: fixed — topbar truncates current-section and shrinks controls;
bottom-nav anchors get min-w-0 + truncate; canvas header wraps in two rows;
sync explanation CSS-clamped on small screens (full text stays in the
accessibility tree; truncation is visual only).
Evidence: tests/e2e/tests/release-gate.spec.ts — no horizontal overflow at
375px on Today/Tasks/Goals/Knowledge/Canvas/Schedule + canvas workspace.
Link: TASK-R7.
```

```
UI-013 | 2026-08-23 | Schedule draft + Reschedule views (hierarchy §2.3, §51) | P2
Found (TASK-P17-010): the scheduler's main flow buttons — draft-generate /
draft-apply and reschedule-propose / reschedule-apply — are raw unstyled
buttons. The pages have NO visual primary action at all: generate/propose
and apply carry equal weight, so the eye gets no order for the page's most
consequential operations.
Expected: design.md §51 — exactly one primary per decision context; apply
(a material mutation) should be unmistakably primary; generate/propose
secondary.
Severity: P2.
Status: fixed (2026-08-23, TASK-P17-012) — all scheduler flow buttons now use
the shared KButton: Generate Draft / Propose Reschedule and Apply Draft /
Apply are tactile primaries (rest 4px / hover 6px / pressed 2px offset
shadows); Cancel / Dynamic Reschedule / Back are quiet secondaries. Generate
and Apply never compete — they own different stages of the flow (draft
preview renders after generate).
Evidence: tests/e2e/tests/tactile-language.spec.ts asserts computed
box-shadow 4px→6px(hover)→2px(press) on draft-generate in chromium+firefox;
surface-qa + accessibility suites green in all three browsers.
Link: TASK-P17-010, TASK-P17-012.

UI-014 | 2026-08-24 | Schedule draft + Notes list + Canvas list (micro-copy, TASK-P17-030) | P2
Found: developer terminology leaked into user-facing copy. Draft reasoning
note said "This deterministic draft … Applying it bumps the schedule version;
stale applies return 409" — jargon ("deterministic", "bumps the version") plus
a raw HTTP code. Notes/Canvas lists exposed the internal revision counter as a
"v3"-style chip — meaningless to users.
Expected: TASK-P17-030 acceptance — no developer terminology, HTTP codes, or
implementation jargon; conflict safety explained in plain language.
Severity: P2.
Status: fixed (2026-08-24, TASK-P17-030) — reasoning note now reads as a plain
promise about what the plan respects and what happens if things changed while
reviewing; notes list shows "Updated <date>"; canvas v-chip removed.
Full-copy sweep found no guilt phrasing, pseudo-science, vague "Optimize" CTAs,
or other HTTP codes in user-facing strings (sync explanations already human).
Evidence: vitest 29 passed on affected suites; full gates re-run at commit.
Link: TASK-P17-030.
```

```text
UI-015 | 2026-08-27 | Visual system (global, UI-consistency slice) | UX-C4
Full-page audit found systemic token bypass beyond UI-004's partial close:
~1,070 gray-* usages in 57 files vs text-muted/surface/border tokens; ~140 raw
buttons and ~90 raw inputs duplicating KButton/KInput; 6 modals/drawers using
z-50 instead of --z-modal/--z-toast; 8 groups of stray hex literals
(including 14× stale pre-R5 primary #F53003 in AnalyticsView); the ad-hoc
danger-tint pair bg-[#fff2f2] dark:bg-[#1D0002] repeated in 5 files; emoji 🔔
and unlabeled unicode pager/close glyph buttons (‹ › ✕) as de-facto icons.
Expected: design.md §65–§67, design-tokens.md §2/§4a/§8; WCAG 2.2 button-name.
Severity: UX-C4 (visual inconsistency), P2 — scheduled cleanup per §3.
Status: partially fixed (2026-08-27) — KIcon shared icon component landed
(Heroicons outline subset, MIT, ledger updated); z-50 → --z-modal on 5 modals
+ --z-toast on ToastHost; danger tint tokenized as --color-danger-tint
(bg-danger-tint ×6); stale #F53003 → bg-primary (AnalyticsView ×14,
TodayView ×1); #2C5FA8 → info; green/red/blue standard-palette status colors →
success/info/warning where they map cleanly; emoji/unicode icons replaced with
KIcon; ‹ › ✕ buttons given aria-labels; Login/Register migrated to
KButton/KInput. Remaining open: gray-* scale-down across 50+ files, raw
button/input consolidation (~130 sites), soft-tint quads in VisualStateBadge /
AiUsageSummaryCard need success/info/warning -tint tokens, orange heatmap
ladder unscaled.
Evidence: vue-tsc clean; vitest 522 passed (74 files); production build green;
AnalyticsView.test.ts assertions updated from literal bg-red-500 to semantic
bg-danger (implementation-detail assertion, intent unchanged).
Link: this record; docs/design-tokens.md §2.1/§11.
```

```text
UI-016 | 2026-08-27 | Auth (Login/Register) | UX-C4
Auth forms predated the component library: raw gray-bordered inputs and a flat
gray submit button without the offset-shadow tactile language used everywhere
else since TASK-P17-012.
Expected: design.md §51 primary CTA language; design-tokens.md (KInput border
token, KButton tactile offsets).
Severity: UX-C4 / P2.
Status: fixed (2026-08-27) — both views now use KInput + KButton primary;
register password input gained autocomplete="new-password"; first-time links
kept as plain underline affordances. First-time loop LOGIN is now token-clean.
Evidence: vue-tsc + vitest suite green after change.
Link: this record.
```

```text
UI-017 | 2026-08-27 | Auth gate + app shell chrome | UX-C4
Found (user-reported): the login screen rendered a bare centered form on a flat
background with zero brand identity — no wordmark, no surface weight, raw gray
controls before the KButton/KInput migration; AppShell chrome (topbar, side
nav, mobile bottom bar, more drawer) painted with Tailwind grays and literal
hex duplicates (#FDFDFC/#1b1b18/#0a0a0a/#EDEDEC) instead of theme tokens.
Expected: design.md §4–§7 Neo-Brutalist border/shadow language; §67 brand
consistency; design-tokens.md §2 semantic colors on every visible surface.
Severity: UX-C4 / P2 (first-login impression is the product's front door).
Status: fixed (2026-08-27) — new auth/AuthScreen.vue branded split gate:
desktop left panel in bg-primary with KINEVO wordmark mark, display headline
"Start today." + one sub-line, brutalist color-block decoration;
right column hosts both forms inside .surface-hero (L1 4px border + offset
shadow); fields use font-semibold labels above KInput, inline errors restyled
border-l-4 danger + bg-danger-tint; submit = tactile KButton primary w-full;
theme toggle tokenized on-token; restoring state uses tokens. Login/Register
copy trimmed to real function ("Pick up where you left off." /
"One workspace to start your day."). AppShell topbar/side-nav/mobile bar/more
drawer/SyncStatusPanel/shell index migrated from grays+h9x to bg-bg/text-text/
text-text-muted/border-border(bg-surface actives) so EVERY page inherits
branded chrome. Authenticated header: view title gains primary square mark,
Quick Capture → KButton primary, Log out → ghost.
Evidence: vue-tsc clean; vitest 522 passed (auth suite 19 incl. gate/testid
contracts); production build green; auth testids (auth-gate moved into
AuthScreen root) and E2E button-name contracts unchanged.
Link: this record.
```

```text
UI-018 | 2026-08-27 | All surfaces (full brand redesign, user-directed) | UX-C4
Found (user-reported): post-UI-015 the app was token-clean but visually flat —
the login was a bare form and interior pages carried generic gray chrome with
no brand presence; the official Kinevo mark (docs/assets/banner-kinevo-dark.svg)
appeared nowhere in the product.
Expected: design.md §4–§7 Neo-Brutalist border/shadow doctrine across every
surface; §67 brand consistency; design-tokens.md §4a L1–L5 adoption beyond
Analytics.
Severity: UX-C4 / P2 (product-wide visual identity gap).
Status: fixed (2026-08-27) — full-page redesign sweep:
• components/KLogo.vue renders the official banner mark geometry (primary
  square + white stem/steps/leg) with variants brand/outline, token-driven;
  placed in auth gate (both panels), shell topbar wordmark row.
• Auth gate split-screen (UI-017) retained; login/register forms inside
  surface-hero card.
• TodayView flagship: NOW = .surface-hero + mono NOW eyebrow; NEXT =
  .surface-secondary; capacity reveal converted clickable-div → <button>
  (a11y fix); banners unified (2px box + 4px semantic left accent); timeline
  locked/conflict/empty all tokenized; ExecutionTimer/RechargeTimer unified as
  sibling L5 strips (mono tabular).
• Task list/detail, Goal list/detail, Notes edit/list, Canvas list/workspace/
  host/context panel: eyebrow+bold page headers, .surface-primary/-secondary
  cards, metadata hairline rows, dashed empty states, staged CTA rules intact;
  Goal milestone rail node states tokenized (success/focus/danger) keeping §39
  glyphs.
• Dialog template standardized on ALL modals (Boost/Break/EmergencyPause/
  QuickCapture/WorkspaceManager): z-modal overlay bg-bg/80, surface-hero
  panel, x-mark close w/ aria-label, danger variant for destructive confirms,
  warning accents via tokens (amber hex removed); QuickCapture hand-painted
  theme hexes eliminated.
• AnalyticsView de-grayed (~66 lines): pill actives tokenized, heatmap ladder
  rebuilt as single-accent primary opacity ramp [0..solid], warn/ok tones →
  warning/success token pairs; VisualStateBadge soft tints → new
  --color-{success,info,warning}-tint tokens (app.css light+dark; danger-tint
  was referenced-but-undefined and is now defined too);
  AiUsageSummaryCard byok chip → info-tint pair.
• Remaining pages (Week, Calendar, Schedule draft/reschedule, imports/
  exports, Profile, Plan settings, AI settings, Command palette, canvas chrome)
  fully tokenized; today-marker treatment consistent (border-2 border-primary)
  across Week+Calendar; CommandPalette z corrected to --z-command-palette.
Post-sweep repo state: ZERO gray-* or raw hex color literals remain in
resources/js/*.vue (grep-verified); layout/IA/data contracts unchanged.
Evidence: vue-tsc clean; vitest 522 passed (74 files); production build green;
make test 970 passed (PHP untouched). Per-slice suite evidence in task records
(Today 44, Task 20, Goal 19, knowledge/canvas 95, dialogs/workspace 39,
analytics/visualstate/ai 61, planner/shared 70).
Link: this record.
```

```text
UI-019 | 2026-08-27 | Auth gate (design-skill consult, user-directed) | UX-C4
Found: the UI-017/018 gate was token-clean but failed taste/pro-max review:
(1) two stacked small elements in the brand strip (wordmark + badge) against
hero stack discipline; (2) split copy register — marketing voice left
("Start today.") vs personal voice right; (3) official banner argument chain
(GOAL→BREAKDOWN→SCHEDULE→TODAY→ADAPT) unused while generic copy was invented;
(4) meaningless color-cube decoration violating decoration-must-be-motivated;
(5) opacity-90 subtext on primary approaching AA floor on colored bg;
(6) dead whitespace right column vs heavy left panel (mass imbalance);
(7) zero motion at a dial that demands minimal entry/hover feedback.
Expected: design-taste-frontend §0.B read, §4.7 hero stack + eyebrow restraint,
§4.10 copy register; ui-ux-pro-max forms rules (labels above, submit feedback —
already green, kept).
Severity: UX-C4 / P2.
Status: fixed (2026-08-27) — brand panel rebuilt: single mono wordmark strip,
display headline from banner canon "Plan. Schedule. Focus. Adapt." with bold
underline emphasis on Adapt., GOAL→…→ADAPT mono chain as bottom register,
ghost outline KLogo texture echoing the banner asset (replaces cubes); subtext
element removed (headline carries it); form column rebalanced max-w-md p-10,
h1 raised to text-3xl, unified voice ("Welcome back / Your day is waiting…" ·
"Start your first day / One workspace. Goals in, today out."); theme toggle
demoted to quiet mono ghost chip; 160ms transform/opacity entry cascade gated
by existing prefers-reduced-motion collapse in app.css. Both modes audited:
outline logo + white type on #DE3005 unchanged in dark.
Evidence: vue-tsc clean; vitest 522 passed (auth 19); build green; make test
970 passed; testids/aria contracts untouched.
Link: this record.
```

```text
UI-020 | 2026-08-27 | All non-auth pages (taste/pro-max rule sweep, user-directed) | UX-C4
Found (mechanical audit against design-taste-frontend + ui-ux-pro-max):
(1) Eyebrow Restraint violations — the #1 most-tested taste rule — TodayView
8 / AnalyticsView 12 / AiUsageSummaryCard 7 / AiSettingsView 6 / GoalListView
5 occurrences vs the max-1-per-3-sections budget; (2) visible em-dashes in
copy (GoalList statuses/FeatureHelp, CanvasList placeholder/separator,
NoteEdit conflict message, QuickCapture null options "—") despite the hard
ban; (3) dead disabled:opacity-50 utility on non-disableable <label> wrappers
(KrsImport/IcsImport — same class of bug as AttachmentList earlier);
(4) timeline events/empty slots/hard-landscape carried title-only affordance
(a11y); (5) scroll containers without keyboard focus (WorkspaceManager modal,
CommandPalette results; WCAG 2.1.1); (6) AppShell root min-h-screen instead of
100dvh; (7) FeatureHelp popover arbitrary z-30 vs --z-popover; (8) PlanSettings
raw row buttons.
Expected: design-taste-frontend §4.7 eyebrow restraint + §4.9/§9.G copy rules,
§3.E viewport stability; ui-ux-pro-max forms/a11y High-severity rows.
Severity: UX-C4 / P2.
Status: fixed (2026-08-27) — per-page eyebrow budgets enforced and verified by
grep: TodayView 13→1 (NOW hero only), AnalyticsView 12→2, AiUsageSummaryCard
7→0 (dl-style stat layout), AiSettingsView 6→1, GoalListView 5→1,
CanvasList/Notes 1 each, GoalDetail/Boost/Break dialogs demoted to plain
semibold headings; InterpretationStrip <dt> metadata labels kept as sanctioned
micro data labels. Em-dashes removed from all user-facing strings (test pins
updated where they asserted old copy verbatim: ai-remote-journey.spec.ts,
CanvasListView.test.ts). Dead opacity utilities replaced with condition-bound
:class on real disabled state. Timeline elements gained sr-only info spans;
scroll containers tabindex=0 + aria-labels; min-h-[100dvh]; z-popover token;
PlanSettings buttons → KButton ghost; QuickCapture null options read "None";
touch targets ≥44px on capacity/capture controls.
Evidence: vue-tsc clean; vitest 522 passed (74 files); build green; make test
970 passed; post-sweep grep shows remaining uppercase usages are sanctioned
nav-group/metadata/chip conventions.
Link: this record.
```

No finding above is closed silently; each closes with concrete browser/test/visual
evidence in a later task.

```text
UI-021 | 2026-08-27 | Mobile (Android native shell) | P1
Native screen content subtrees render empty on device (chrome renders)
Gap: after re-bundling the in-repo NativePHP shell and booting on the
emulator, BootPlanner dispatches NATIVE_DIRECT with the 10-route manifest,
the persistent runtime boots (~400ms, Fully drawn, queue worker green), the
top-bar title + 5-tab bottom-nav from our own blades render interactively
(tap dispatches server navigation; tree version bumps), but every content
branch of Today/Tasks (Welcome card, Loading text, NOW/NEXT lists) is absent
from the posted element tree (nodes=11 = chrome only).
Expected: design.md §99 first-love loop reads tasks/state on device;
SRS FR-1/FN-2 device read-path (previously proven in P27-002 spike).
Repro: install repo-built APK (2026-08-27 18:35 boot), uiautomator dump shows
only tab bar texts; logcat PostTreeUpdate nodes=11.
Severity: P1 (device companion content unreadable — not data loss/auth);
blocks honest completion of P27-002..010 device gates.
Status: open — two confirmed symptoms while reproducing (2026-08-27):
(a) per-screen edge elements never reach the posted tree even though the
component mounts and renders server-side (logcat PostTreeUpdate nodes=18
flat=0; uiautomator chrome-only); pixel analysis proves per-tab DIFFERENT
content DOES display (~24.6k differing pixels Today↔Tasks) via the hybrid
WebView path, so users see real data while the native accessibility/body
surface stays empty; (b) a boot-time race sometimes skips component mount
entirely (backend /api/v1/health absent, session exits 200 "Native stack
empty") until a later cold start engages. Root cause suspected in upstream
NativePHP v4.2 EDGE element publishing/activation on Android; must be
reproduced minimally against vendor source before any fix lands.
Link: infrastructure/nativephp/linux-build/build-android-apk.sh (repro
pipeline); TASK.md Phase 27 evidence block 2026-08-27.
Resolution (2026-08-28): root causes found on the Linux pipeline —
(1) the vendor `native-ui` Compose renderer plugin is absent on this OS, so
content element types (text/icon/pressable/scroll-view/text-input) had no
renderer and fell through to an empty container; fixed by in-repo
KinevoRendererRegistration.kt + MainActivity registration, applied
idempotently by the build script (source of truth:
infrastructure/nativephp/linux-setup/);
(2) the runtime skipped re-extracting a rebuilt laravel_bundle.zip because
bundle_meta version was static ("0.27.0-debug") — the device silently ran a
stale bundle, which masked every server-side fix during device testing;
fixed by stamping version/version_code with the build timestamp.
Evidence (emulator, repo-built 8.5-runtime APK): uiautomator reads the full
content subtree + a11y content-descs (Welcome/Sign in card, Today NOW/NEXT/
CAPACITY cards, Task rows); tap Sign in → authenticated Today from live
backend; quick-capture "Plan tomorrow" → "Captured ✓" (POST /quick-capture);
/task detail navigation renders (title/status/progress, lifecycle, timer,
subtasks cards); Start → in_progress + "Session #1 — running" (ExecutionTimer
contract); Complete → completed. Symptom (b) did not reproduce across ~15
cold boots on 2026-08-28; kept open in TASK.md follow-ups until several
release-candidate boots pass clean. Status: resolved for (a); (b) monitoring.
```

## 7. Anti-pattern scan (design.md §93)

Checklist run on every surface: giant dashboard · cardception · modalception ·
pill soup · rainbow metrics · AI magic button · full-page spinner · silent
autosave failure · silent schedule mutation · silent conflict resolution ·
canvas black screen.

## 8. Duplication hunt (design.md §80)

Look for: duplicate buttons · duplicate badge logic · duplicate modal
implementations · duplicate API error mapping · duplicate spacing values ·
duplicate color values · duplicate card components. Replace with design-system
components; delete dead surfaces (design.md §81).

## 9. Completion gate for a surface

A surface is cleared only when `docs/design.md` §70 dimensions pass, §86
definition of UX done holds, and its §92 design review checklist is ticked.

---

## Maintenance

- Used by rescue Phase R2 and refreshed on every UI PR.
- Kept in the same lifecycle state as `docs/design.md`; both change together.
- Do not weaken a checklist row to close it (AGENTS.md verification rule).