---
id: T-046
title: Build Dialog, Sheet, Toast, Banner, Card, EmptyState, Skeleton and Tabs
milestone: M1
kind: feature
status: review
depends_on: [T-045]
requirements: [CA-15, DS-A11Y-02, DS-A11Y-09, DS-A11Y-12, FE-12, FE-16]
branch: m1-t046-web-overlays-and-feedback
budget_lines: 390
assignee: "OpenAI Codex"
---

# T-046: Build Dialog, Sheet, Toast, Banner, Card, EmptyState, Skeleton and Tabs

## Goal

The feedback and layout primitives of the catalogue exist with the live-region, focus and motion behaviour the spec requires.

## Context

> **Catalogue (§7)**: Dialog/Sheet (modal, non-dismissible while saving; focus trap, restore focus, `Esc` closes unless destructive-in-progress; labelled and described); Popover/Tooltip/Menu (tooltip content also reachable without hover); Tabs (arrow keys, roving tabindex); Toast (`role="status"`, errors `role="alert"`; success/info auto-dismiss ≥ 6 s paused on hover/focus; errors persist until dismissed; queue max 3); Banner (info, warning, danger, success; persistent; dismissible only if non-critical); Card/Section (no card-in-card beyond one level); Badge/StatusPill (icon + text); ProgressBar (labelled, `aria-valuenow`); EmptyState (display-serif headline, one sentence, one primary action); Skeleton (static under reduced motion, `aria-hidden`).
> **UX conventions (`standards/ux-states.md` §1)**: loading skeleton appears after ≥ 400 ms; error card shows the message, a copyable `request_id` and Retry; destructive actions use an `alertdialog` naming the object and consequence.
> **FE-12**: a render error in one feature shows a local error card with "Reload this section" and copies the id, and does not blank the shell.
> **DS-A11Y-09**: toasts, banners and save indicators use live regions. **DS-A11Y-12**: landmarks, one `h1` per route, skip link first focusable element.
> **Motion (§6)**: allowed fade and ≤ 8 px translate; reduced motion removes transforms and caps fades at 120 ms.

## Scope

**In:**
- The listed components with tests, plus `ToastProvider` + `useToast`, `ErrorCard`, `ConflictCard` (shell only: props for "Your change" / "Current version" and two actions), `ErrorBoundary` section wrapper.
- `useDelayedLoading(ms)` hook used by `Skeleton` wrappers.
- The dev-only demo route extended with these components for axe tests.

**Out:**
- Command palette, Timer, NowCard, DraftDiffRow and other composites: the milestones that own their screens.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/components/{Dialog,Sheet,Toast,Banner,Card,EmptyState,Skeleton,Tabs,Badge,ProgressBar,ErrorCard,ConflictCard,ErrorBoundary}.tsx` | create | one file each + test |
| `web/src/hooks/useDelayedLoading.ts` | create |  |
| `web/src/app/components-demo.tsx` | modify | add the new components |
| `web/tests/e2e/overlays.a11y.spec.ts` | create | focus and live-region tests |

## Interfaces

```tsx
const toast = useToast(); toast.success(messageKey); toast.error({ messageKey, requestId });
<Dialog title="…" description="…" isDismissable={!saving}>…</Dialog>
<ErrorCard error={appError} onRetry={…} />          // shows request_id with a copy button
<EmptyState headline="…" body="…" action={<Button …/>} />
```

## Business rules

1. At most 3 toasts queue; error toasts never auto-dismiss.
2. A Dialog traps focus, restores it on close, and ignores `Esc` while `isDismissable` is false.
3. Skeletons render only after 400 ms of loading and are `aria-hidden`.
4. The ErrorBoundary resets when the user presses Reload without remounting the shell.

## Security

- `ErrorCard` shows only the `request_id`, never the problem `detail` text from an unknown code beyond its catalogue message; stack traces never render.
- Copy-to-clipboard of the request id uses the async clipboard API behind a user gesture.

## Implementation notes

- Use `aria-live` regions mounted once at the app root so announcements are reliable.
- Keep animations in CSS using motion tokens.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a Dialog **When** opened and closed **Then** focus is trapped inside and returns to the trigger | `overlays::dialog_focus_trap_and_restore` |
| AC-2 | **Given** a saving Dialog **When** `Esc` is pressed **Then** it stays open | `overlays::dialog_not_dismissible_while_saving` |
| AC-3 | **Given** four toasts **When** queued **Then** at most three show; an error toast persists past 6 s | `overlays::toast_queue_and_persistence` |
| AC-4 | **Given** hover on a toast **When** the timer runs **Then** dismissal pauses | `overlays::toast_pauses_on_hover_focus` |
| AC-5 | **Given** a throwing child **When** inside ErrorBoundary **Then** a local card renders and the sibling shell stays | `overlays::fe_12_error_boundary_is_local` |
| AC-6 | **Given** reduced motion **When** components animate **Then** no transforms and fades ≤ 120 ms | `overlays::reduced_motion_respected` |
| AC-7 | **Given** the demo route **When** scanned by axe **Then** zero serious/critical violations and live regions announce | `overlays.a11y::tst_11_overlays_axe_and_live_regions` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
just check
pnpm --dir web exec playwright test tests/e2e/overlays.a11y.spec.ts
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

### Summary

Added the overlay, feedback, loading, status and navigation primitives with React Aria behavior and accessible names. Toasts enforce a three-item cap, persist errors and pause timed dismissal while hovered or focused; section errors stay local, omit untrusted server detail and call the section's reload handler.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `overlays::dialog_focus_trap_and_restore` | pass |
| AC-2 | `overlays::dialog_not_dismissible_while_saving` | pass |
| AC-3 | `overlays::toast_queue_and_persistence` | pass |
| AC-4 | `overlays::toast_pauses_on_hover_focus` | pass |
| AC-5 | `overlays::fe_12_error_boundary_is_local` | pass |
| AC-6 | `overlays::reduced_motion_respected` | pass in Chromium; Firefox/WebKit environment-limited |
| AC-7 | `overlays.a11y::tst_11_overlays_axe_and_live_regions` | pass in Chromium (0 serious/critical axe violations); Firefox/WebKit environment-limited |

### Commands run

| Command | Result |
|---|---|
| `pnpm --dir web test -- src/components/Dialog.test.tsx src/components/Toast.test.tsx src/components/ErrorCard.test.tsx src/components/ErrorBoundary.test.tsx src/components/Skeleton.test.tsx src/components/Tabs.test.tsx src/components/Feedback.test.tsx` (initial TDD run) | exit 1 as expected: the seven new suites could not resolve their not-yet-created component modules; the existing 22 tests passed. |
| `pnpm --dir web test` (intermediate ErrorBoundary recovery test) | exit 1: React concurrent recovery retried a one-shot throwing child before the user action; the test now uses the explicit `onReload` section callback and verifies the child recovers. |
| `pnpm --dir web typecheck` | final exit 0. Earlier attempts exited 2 for component return/ref and React Aria key typing; those type errors were corrected. |
| `pnpm --dir web lint` | final exit 0; four existing reduced-motion `!important` warnings. Initial run exited 1 on formatting and accessibility lint in new files; fixes are included. |
| `pnpm --dir web test` | exit 0; 34 passed across 22 files. |
| `just check` without the shared DB environment | exit 100; four DB-dependent CLI tests reported `Unavailable` because `KINEVO_TEST_DATABASE_URL` had not been loaded. |
| `eval "$(scripts/dev-postgres.sh env)"` followed by `just check` | final exit 0 after reusing the shared PostgreSQL 17 service: nextest 385 passed / 1 skipped; web tests 34 passed; web build/typecheck/lint, task-index, contrast and OpenAPI drift gates completed. |
| `pnpm --dir web exec playwright test tests/e2e/overlays.a11y.spec.ts` | Chromium 2/2 passed. Firefox and WebKit could not launch; exact errors are recorded below and in `tasks/RUN_REPORT.md`. The first E2E attempt also exposed a strict locator matching the persistent Banner and toast; the locator was scoped to the Notifications region and the final Chromium run passed. |
| `python3 scripts/tasks_index.py` followed by `python3 scripts/tasks_index.py --check` | exit 0; 0 problems across 132 tasks. |
| `python3 scripts/spec_tool.py check` | exit 0; 0 findings across 60 spec files. |

### Files changed

| Path | Added / removed |
|---|---:|
| `web/src/app/components-demo.tsx` | +118 / -35 |
| `web/src/components/Badge.tsx` | +30 / -0 |
| `web/src/components/Banner.tsx` | +47 / -0 |
| `web/src/components/Button.tsx` | +19 / -14 |
| `web/src/components/Card.tsx` | +35 / -0 |
| `web/src/components/ConflictCard.tsx` | +41 / -0 |
| `web/src/components/Dialog.test.tsx` | +61 / -0 |
| `web/src/components/Dialog.tsx` | +61 / -0 |
| `web/src/components/EmptyState.tsx` | +19 / -0 |
| `web/src/components/ErrorBoundary.test.tsx` | +41 / -0 |
| `web/src/components/ErrorBoundary.tsx` | +45 / -0 |
| `web/src/components/ErrorCard.test.tsx` | +30 / -0 |
| `web/src/components/ErrorCard.tsx` | +44 / -0 |
| `web/src/components/Feedback.test.tsx` | +69 / -0 |
| `web/src/components/ProgressBar.tsx` | +27 / -0 |
| `web/src/components/Sheet.tsx` | +12 / -0 |
| `web/src/components/Skeleton.test.tsx` | +31 / -0 |
| `web/src/components/Skeleton.tsx` | +14 / -0 |
| `web/src/components/Tabs.test.tsx` | +24 / -0 |
| `web/src/components/Tabs.tsx` | +43 / -0 |
| `web/src/components/Toast.test.tsx` | +85 / -0 |
| `web/src/components/Toast.tsx` | +173 / -0 |
| `web/src/hooks/useDelayedLoading.ts` | +17 / -0 |
| `web/src/main.tsx` | +8 / -5 |
| `web/tests/e2e/overlays.a11y.spec.ts` | +33 / -0 |

Implementation and test diff: 1,181 changed lines excluding task/run reports and the generated index; `budget_lines: 390` was exceeded by 791 lines. Lockfiles and generated files were unchanged.

### Deviations

- `web/src/main.tsx` wraps both the app and the development component route in one `ToastProvider`, as required for one root live-region host; this minimal wiring was not listed in Files.
- `web/src/components/Button.tsx` now forwards its DOM ref so React Aria can restore focus to Dialog/Sheet triggers; this minimal integration fix was not listed in Files.
- The 390-line budget was exceeded because this task adds thirteen primitives, named behavior tests and an accessibility E2E suite. D-55 makes the budget guidance, not a blocker.
- Two pre-existing open issues reference T-046 (#69 and #89). The ledger-linked #69 remains canonical; no third issue was created.
- No dependencies or lockfile entries were added.

### Spec issues noticed

None identified.

### Not done / not verified

- Firefox E2E: `pnpm --dir web exec playwright test tests/e2e/overlays.a11y.spec.ts` could not launch because `/home/kepolu/.cache/ms-playwright/firefox-1555/firefox/firefox` does not exist. The suggested browser download was not run.
- WebKit E2E: the same command could not launch because host libraries `libicu74` and `libflite1` are missing. No sudo or system dependency installation was run.
- Manual NVDA/Firefox, VoiceOver/Safari and TalkBack review was not available in this environment.

### Follow-up suggestions

None.

## Questions

_None._
