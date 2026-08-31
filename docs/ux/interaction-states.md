# Kinevo — Interaction & State System

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Canonical state/interaction authority.
> Migrates `docs/state-machine-ui.md` (entity transition matrices + P28-011 global
> user-facing state matrix + RET-013 failure matrix — preserved verbatim below
> after consolidation; that file is archived). Component contract:
> `components/VisualStateBadge.vue`, `components/InlineError.vue`,
> `shell/SyncStatusPanel.vue`, `ai/AiNotConfiguredNotice.vue`.

## 1. Principles

- Every state answers: what does the user see, what can they do, can they recover,
  is data preserved, what is the canonical action, what are the a11y semantics.
- Transient states get dashed borders; every state carries glyph + label
  (never color-only); `role="status"`/`role="alert"` + aria-live for async
  transitions.
- No surface hides a meaningful state; N/A is declared explicitly.

## 2. Entity transition matrices (canonical, from state-machine-ui.md)

Task (TaskStatus; authority `task/types.ts TASK_TRANSITIONS`): backlog→Schedule ·
scheduled→Start (locked badge when locked) · in_progress→Complete · partial→
Continue · continued→Schedule/Start · missed→Reschedule · conflict→Reschedule
(material confirm) · completed/skipped terminal (`saved` badge).
Goal (draft/active/paused/completed/archived/dropped) and Milestone
(planned/active/blocked/completed/dropped) follow the same pattern — full tables
in the archived source; the implementation constants remain the state authority.

## 3. Global user-facing state matrix (P28-011 — canonical)

The surface × state matrix (LOADING/EMPTY/SUCCESS/PARTIAL/OFFLINE/QUEUED/SYNCING/
STALE/NEEDS_REVIEW/CONFLICT/FAILED/UNAVAILABLE/ENTITLEMENT_BLOCKED per Today, Week,
Goals, Goal detail, Tasks, Knowledge/Notes, Canvas, Schedule draft, Import,
Analytics, Workspace, AI proposal) is recorded in
`docs/convergence/P29_CONVERGENCE_MATRIX_2026-08-31.md` §2 source inventory and
was last verified in full in `docs/state-machine-ui.md` (archived copy retained in
`docs/archive/design-legacy-2026-08-31/state-machine-ui.md`). Canonical rules that
outlive any table:

- **LOADING:** muted `Loading…` text with `{surface}-loading` testid.
- **EMPTY:** answers where/why/next/after (content rules:
  `content-design.md`); education via FeatureHelp block, never modal tours.
- **FAILED (data load):** `InlineError` — message + `Try again` re-running the
  surface's own load action (uniform on Today/Goals/Tasks/Notes/Canvas/Analytics).
- **OFFLINE:** app-wide (`api.setOnline` → SyncStatusPanel, aria-live polite);
  per-surface reads surface offline-aware errors; server stays canonical.
- **QUEUED/SYNCING:** SyncStatusPanel queued count + canvas `view-sync`
  NextActionBanner.
- **CONFLICT:** optimistic base_version 409 → explicit reconcile (note reload;
  canvas "Reload server copy"; sync panel Discard local change). Data preserved.
- **NEEDS_REVIEW:** Today banner + Schedule surface weekly-draft review; stale
  drafts refresh in place (ADR-016).
- **UNAVAILABLE (AI):** `AiNotConfiguredNotice` routes to Settings; runtime chip
  states (disabled/not configured/configured/testing/connected/degraded/
  unavailable) explained by the provider-modes FeatureHelp.
- **ENTITLEMENT_BLOCKED:** UpgradeNotice with the limit explained (workspace cap,
  AI allowance) — never a dead end.

## 4. Retention-critical failure matrix (RET-013 — canonical)

AI unavailable · AI misconfigured · network offline · offline queued · offline
conflict · stale schedule proposal · schedule needs review · no scheduling slot ·
failed import · partial import · empty first session · server failure (5xx) —
each with user-visible explanation, data preservation, recovery action, retry
semantics, escape route. Full table: archived `state-machine-ui.md` §RET-013
(migrated 1:1; implementation: InlineError + AiNotConfiguredNotice +
SyncStatusPanel + weekly-draft review + import preview + first-session guide).

## 5. Motion contract

See `motion.md` (durations, reduced-motion, what may animate).
