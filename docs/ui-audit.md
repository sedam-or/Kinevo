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
| --------- | ----- | ----- | ---- | ---- | --------------- | ------ | --------- | -------- |
| Visual consistency (§85) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Keyboard navigation (§45) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Responsive layout (§8, §58) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Loading / skeleton (§11.1) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Empty state (§11.2) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Error state (§11.3) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Offline (§11, §90) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Conflict / save state (§2.5, §34.4) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Dark mode (§5.4) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Reduced motion (§47) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Primary action obvious (§2.3, §51) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| No color-only state (§5.2) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Token usage (§65, design-tokens.md) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| Component consolidation (§50, §80) | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |

## 5. Component inventory checklist (design.md §50, §80)

Design-system components that MUST exist centrally (no per-page re-implementations):

| Component | Exists centrally | Where detected |
| --------- | ---------------- | -------------- |
| Button / IconButton | ⚪ | — |
| Input / Textarea / Select / Combobox | ⚪ | — |
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