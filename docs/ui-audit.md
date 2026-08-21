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

## 4. Surface audit matrix

Scoring per surface uses the `docs/design.md` §70 design-QA dimensions. Status
legend: `⛔ P0 found · 🔴 P1 found · 🟡 P2 · ⚪ not assessed · ✅ clear`.

| Dimension | Shell | Today | Task | Goal | Knowledge/Notes | Canvas | Analytics | Settings |
| --------- | ----- | ----- | ---- | ---- | --------------- | ------ | --------- | -------- | -------- |
| Visual consistency (§85) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Keyboard navigation (§45) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Responsive layout (§8, §58) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Loading / skeleton (§11.1) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Empty state (§11.2) | ⚪ | ⚪ | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ |
| Error state (§11.3) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Offline (§11, §90) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Conflict / save state (§2.5, §34.4) | ⚪ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ |
| Dark mode (§5.4) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Reduced motion (§47) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
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