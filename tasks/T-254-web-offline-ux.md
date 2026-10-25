---
id: T-254
title: Build the offline UX: optimistic updates, conflict cards, banners, service worker and PWA manifest
milestone: M5
kind: feature
status: todo
depends_on: [T-253, T-203, T-111]
requirements: [SYN-FR-09, FE-07, FE-08, UXS-02, DS-A11Y-13, J5]
branch: m5-t254-web-offline-ux
budget_lines: 420
assignee: ""
---

# T-254: Build the offline UX: optimistic updates, conflict cards, banners, service worker and PWA manifest

## Goal

Working offline feels honest: changes appear instantly and are marked, conflicts are resolved by the user, and the app shell loads without a network.

## Context

> **Optimistic UI** (SYN-C5): an intent applies to the local cache immediately and is marked "syncing"; on `applied` the entity is replaced by the server's; on failure the conflict/rejection card appears and the local value stays visibly flagged until resolved. Offline-capable intents: create/update/status of tasks and subtasks (and notes after M7); everything else (focus sessions, schedule apply, landscape, goals, canvas, AI, policies, account) is disabled offline with an explanation.
> **Conflict card** (SYN-C4): shows "Your change" and "Current version"; actions **Keep current** (drop the op) and **Reapply mine** (new `operation_id`, `base_version` = current); `rejected` offers **Discard** and, for validation errors, **Edit**. Errors are shown from codes through the T-048 mapper.
> **States and strings**: `state.offline.banner` with "Last updated" on cached screens, `state.sync.pending` ("3 changes waiting to sync", SYN-C7), `state.synced`, `sync.conflict`; `sync` module off (SYN-FR-09): mutations are sent directly with `Idempotency-Key` and an "Offline editing is off" state appears when disconnected.
> **Service worker and PWA** (FE-07/FE-08): the SW precaches the hashed app shell and serves it offline; API responses are not cached; a new server version shows "Update available — reload"; web manifest (name, short_name, icons incl. maskable, `display: standalone`, theme colours from tokens, `start_url: /today`, shortcuts to Today and New task). The CSP of FE-10 stays valid (no inline scripts). Use `workbox` only if it is in the stack map; otherwise a small hand-written SW whose behaviour is covered by tests.
> **Journey J5** steps 1–4 and 6 are the e2e target (steps 5 and 7 are asserted with MSW/server as noted in the integration task).

## Scope

**In:**
- Feature code `web/src/offline/ui/**`, integration into task mutations (M2 hooks switch from direct calls to `enqueue` when `sync` is enabled), `web/src/sw/**`, `web/public/manifest.webmanifest`, icons from the brand set (placeholder generated from tokens if the brand assets do not exist yet; note in the Report), i18n (en, id), tests, Playwright with `context.setOffline`.

**Out:**
- WASM preview: T-255.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/offline/ui/**` | create |  |
| `web/src/features/tasks/hooks.ts` | modify | use enqueue when sync is on |
| `web/src/sw/**, web/public/manifest.webmanifest` | create |  |
| `web/src/i18n/{en,id}/offline.json` | create |  |
| `web/tests/{offline-ui.test.tsx,e2e/offline.spec.ts}` | create | AC-1…AC-9 |

## Interfaces

```ts
export function useOfflineMutation<I>(spec: IntentSpec<I>): { mutate(i: I): void };   // enqueue when sync on, direct call otherwise
```

## Business rules

1. Business rules stay on the server: the card only shows both versions and sends the user's choice.
2. Reapply creates a new operation id with the current `base_version`.
3. The SW never caches `/api/*`.

## Security

- The SW scope is `/` and it caches only same-origin hashed assets and the shell.
- No secrets in IndexedDB.

## Implementation notes

- Keep `needs_attention` items visible across reloads and in the shell indicator.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** offline **When** a task is created, another completed and a title edited **Then** the UI updates at once and shows 3 changes waiting | `offline_ui::j5_optimistic_and_pending_count` |
| AC-2 | **Given** a conflict result **When** shown **Then** the card offers Keep current and Reapply mine; Reapply sends a new id with the current version | `offline_ui::syn_c4_conflict_card_actions` |
| AC-3 | **Given** a rejected validation result **When** shown **Then** Edit and Discard are offered | `offline_ui::syn_c4_rejected_actions` |
| AC-4 | **Given** `sync` off **When** disconnected **Then** mutations go direct with `Idempotency-Key` and the off-state shows | `offline_ui::syn_fr_09_sync_off_behaviour` |
| AC-5 | **Given** online-only actions **When** offline **Then** they are disabled with a reason | `offline_ui::online_only_actions_disabled` |
| AC-6 | **Given** a reload while offline **When** the page loads **Then** the app shell loads from the SW and the queue persists | `e2e::j5_offline_reload_keeps_queue` |
| AC-7 | **Given** reconnect **When** the queue drains **Then** `state.synced` and server state matches | `e2e::j5_reconnect_drains` |
| AC-8 | **Given** a lost response **When** the op is resent **Then** `replay=true` and no duplicate | `e2e::j5_lost_response_replay` |
| AC-9 | **Given** the manifest **When** checked **Then** it has the required fields, maskable icon and `start_url` `/today` | `offline_ui::fe_08_manifest_fields` |

## Tests

```
pnpm --dir web test
pnpm --dir web check-i18n
just e2e   # environment-limited without browsers
just check
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
