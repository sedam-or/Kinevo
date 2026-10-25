---
id: T-046
title: Build Dialog, Sheet, Toast, Banner, Card, EmptyState, Skeleton and Tabs
milestone: M1
kind: feature
status: todo
depends_on: [T-045]
requirements: [CA-15, DS-A11Y-02, DS-A11Y-09, DS-A11Y-12, FE-12, FE-16]
branch: m1-t046-web-overlays-and-feedback
budget_lines: 390
assignee: ""
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

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
