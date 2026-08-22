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

| Dimension | Shell | Today | Task | Goal | Knowledge/Notes | Canvas | Analytics | Settings |
| --------- | ----- | ----- | ---- | ---- | --------------- | ------ | --------- | -------- | -------- |
| Visual consistency (§85) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Keyboard navigation (§45) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | 🟡 |
| Responsive layout (§8, §58) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Loading / skeleton (§11.1) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Empty state (§11.2) | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ |
| Error state (§11.3) | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ |
| Offline (§11, §90) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Conflict / save state (§2.5, §34.4) | ⚪ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ |
| Dark mode (§5.4) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Reduced motion (§47) | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | ⚪ | 🟡 |
| Primary action obvious (§2.3, §51) | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ |
| No color-only state (§5.2) | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ |
| Token usage (§65, design-tokens.md) | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ |
| Component consolidation (§50, §80) | ✅ | ✅ | 🟡 | 🟡 | ✅ | ⚪ | ⚪ | ⚪ |

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
| Toast | ⚪ | — |
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
UI-nnn | @date | <surface> | P#
Title
Observed behavior
Expected (design.md §...)
Repro (steps / browser / E2E run id)
Severity justification
Status (open / fixed / triaged / accepted)
Link (test, PR, screenshot, issue)
```

No finding is closed silently; closing requires evidence (test, browser run,
visual baseline).

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
Status: open (extends into TASK-R2 component work and R3/R4).
Link: docs/browser-e2e.md §7 (Journey E) ⚪.

UI-003 | 2026-08-21 | Task / Goal / Note (workspaces) | P2
Deep create/edit flows unverified in a real browser
Gap: R1 only asserts each surface renders its list. Task/goal/note create,
edit, link, and schedule-side effects are unproven in-browser.
Expected: design.md §19–§21, §30–§33; golden journeys A/C/D.
Repro: R1 E2E navigation assertions only.
Severity: P2.
Status: open (extends into TASK-R3 UI refinement).
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
Status: open (R1 completion item).
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

No finding above is closed silently; each closes with concrete browser/test/visual
evidence in a later task.

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