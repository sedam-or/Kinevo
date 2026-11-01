---
id: T-053
title: Build the data settings (export and account deletion)
milestone: M1
kind: feature
status: review
depends_on: [T-052, T-029]
requirements: [ID-FR-10, ID-FR-11, ID-FR-12, UXS-04, UXS-02]
branch: m1-t053-web-settings-data-export-delete
budget_lines: 300
assignee: "OpenAI Codex"
---

# T-053: Build the data settings (export and account deletion)

## Goal

A user can request a data export, see its progress and download the ZIP, and can delete their account after confirming with their password and the phrase.

## Context

> **Endpoints** (`modules/identity.md` §2): `POST /account/export` (202 `{export_id}`; `export_in_progress` 409), `GET /account/export/{id}` (`{state, size_bytes?, expires_at}`), `GET /account/export/{id}/download` (`application/zip`; `export_not_ready` 409, `export_expired` 410), `DELETE /account {password, confirm: "delete my account"}` (202; `current_password_incorrect`, `last_admin` 409).
> **ID-FR-10**: the last active `instance_admin` cannot delete or disable themself (`last_admin`). **ID-FR-11**: deletion confirms with the password; status becomes `deleting`, all sessions are revoked, purge is asynchronous. **ID-FR-12**: export is a ZIP with `data.json`; states `queued → running → ready → expired` (SM-21); a ready export is available until `expires_at`.
> **UX keys** (`ux-states.md` §10): `data.export.queued` (toast), `data.export.ready` (banner with Download), `data.delete.confirm` (alertdialog: type the phrase and enter the password), `data.delete.last_admin` (inline). **UXS-04**: destructive modal names the object; Cancel initially focused.
> **Phrase**: the phrase comes from the message catalogue key used in `data.delete.confirm` (`{phrase}` argument = the server-required string `delete my account`; the string is a protocol constant, not translated).

## Scope

**In:**
- `/settings/data` panel: request export, poll `GET /account/export/{id}` with TanStack Query (interval backoff while `queued`/`running`, stop at `ready`/`failed`/`expired`), banner and download link using an authenticated fetch to a blob (the download needs the `Authorization` header).
- Delete flow: alertdialog with phrase and password, `last_admin` inline error, success path signs the user out and shows a neutral goodbye page.

**Out:**
- Admin-side account management: T-055.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/identity/settings/{DataPanel,ExportCard,DeleteDialog}.tsx` | create | panel |
| `web/src/features/identity/settings/data.test.tsx` | create | tests |

## Interfaces

```ts
useExport(): { request(): Promise<void>; status: 'idle'|'queued'|'running'|'ready'|'failed'|'expired'; expiresAt?: string; download(): Promise<void> }
```

## Business rules

1. Only one export request at a time; `export_in_progress` shows the current one.
2. The Delete button stays disabled until the phrase matches exactly and the password is non-empty; the server remains authoritative.
3. After a successful delete request the client clears all cached data and the in-memory token.

## Security

- The blob URL is revoked right after the download starts.
- The export is never cached by React Query persistence.
- The phrase and password are cleared on close.

## Implementation notes

- Keep the polling interval policy-free (UI-only constant in `lib/polling.ts` with a comment on why; it is not a business value).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a request **When** `queued` then `ready` **Then** the toast then the banner with date and Download appear | `data::data_export_flow_states` |
| AC-2 | **Given** an active export **When** requesting again **Then** the in-progress state is shown | `data::export_in_progress_handled` |
| AC-3 | **Given** `export_expired` **When** downloading **Then** the expired message with a Request again action | `data::export_expired_state` |
| AC-4 | **Given** the delete dialog **When** open **Then** Cancel is initially focused and the object name is in the text | `data::uxs_04_delete_alertdialog` |
| AC-5 | **Given** `last_admin` **When** deleting **Then** `data.delete.last_admin` shows inline | `data::id_fr_10_last_admin_inline` |
| AC-6 | **Given** a successful delete **When** finished **Then** the token and cache are cleared and the goodbye page shows | `data::delete_clears_session_and_cache` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
cargo nextest run -p kinevo-mod-identity -p kinevo-mod-admin --test descriptor
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [x] Report below is filled in.

## Report

### Summary

The data settings page lets an authenticated user request and track an export, download its ZIP, and delete the account after password and phrase confirmation. Successful deletion clears the query cache and in-memory token before showing the public goodbye route.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `data::data_export_flow_states` | pass |
| AC-2 | `data::export_in_progress_handled` | pass |
| AC-3 | `data::export_expired_state` | pass |
| AC-4 | `data::uxs_04_delete_alertdialog` | pass |
| AC-5 | `data::id_fr_10_last_admin_inline` | pass |
| AC-6 | `data::delete_clears_session_and_cache` | pass |

### Commands run

| Command | Result |
|---|---|
| `df -h / && PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH pnpm --dir web test -- src/features/identity/settings/data.test.tsx` (test-first run) | exit 1 as expected: the named tests were red before `DataPanel.tsx` existed (`failed to resolve import ./DataPanel`). |
| `df -h / && eval "$(scripts/dev-postgres.sh env)" && PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH just check` (first resumed attempt) | Interrupted with SIGINT before Rust tests because the helper reported `environment-limited: container kinevo-dev-pg already exists without a script state file; refusing to replace it`. The command's exit code was not captured. The existing container metadata was then restored without displaying credentials; the same container was reused. |
| `df -h / && eval "$(scripts/dev-postgres.sh env)" && PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH just check` (retry with the missing shim) | exit 1 at web lint: Corepack could not find `/home/kepolu/.cache/node/corepack/v1/pnpm/12.16.1/bin/pnpm.cjs`. |
| `df -h / && eval "$(scripts/dev-postgres.sh env)" && PATH=/home/kepolu/.cache/node/corepack/v1/pnpm/12.16.1:/usr/bin:$PATH just check` | exit 1 after 388 Rust tests passed and one was skipped; Vitest's `generated_css_matches_committed` failed with `spawnSync pnpm ENOEXEC`. |
| `PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH node -e 'const {execFileSync}=require("node:child_process"); process.stdout.write(execFileSync("pnpm",["--version"],{encoding:"utf8"}))'` | exit 0; child-process resolution printed `12.16.1`. The temporary shim invokes the already cached pnpm native binary and was not added to the repository. |
| `df -h / && PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH pnpm --dir web exec vitest run src/features/identity/settings/data.test.tsx` | exit 0; 1 file, 7 tests passed. |
| `df -h / && eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-mod-identity -p kinevo-mod-admin --test descriptor` | exit 0; 5 tests passed. |
| `df -h / && eval "$(scripts/dev-postgres.sh env)" && PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH just check` | exit 0 (`JUST_CHECK_EXIT=0`); 388 Rust tests passed / 1 skipped, 87 web tests passed across 34 files, lint/typecheck/build/API generation/cargo-deny/spec/contrast/task-index/OpenAPI gates completed. |
| `python3 scripts/spec_tool.py check` | exit 0; `0 finding(s) in 60 spec files`. |
| `python3 scripts/tasks_index.py --check` | exit 0; `0 problem(s) in 132 task(s)`. |

`just check` emitted existing non-failing warnings: the unreachable `rand::thread_rng` clippy entry, six Biome warnings/nine infos, the Vite large-chunk and `ModuleGate` import warnings, and cargo-deny duplicate/unmatched-allowance warnings.

### Files changed

| Path | Added / removed |
|---|---:|
| `web/src/features/identity/settings/DataPanel.tsx` | +42 / -0 |
| `web/src/features/identity/settings/DeleteDialog.tsx` | +124 / -0 |
| `web/src/features/identity/settings/ExportCard.tsx` | +160 / -0 |
| `web/src/features/identity/settings/data.test.tsx` | +247 / -0 |
| `web/src/lib/polling.ts` | +8 / -0 |
| `web/src/app/router.tsx` | +31 / -3 |
| `web/src/auth/AuthManager.ts` | +5 / -0 |
| `web/src/auth/guards.ts` | +1 / -0 |
| `web/src/i18n/en.json` | +18 / -0 |
| `web/src/i18n/id.json` | +18 / -0 |
| `spec/decisions.md`, `spec/index.md`, `tasks/T-053-web-settings-data-export-delete.md`, `tasks/RUN_REPORT.md`, `tasks/index.md` | D-132, task Report/status, run report, and generated-index updates; included in the commit. |

The application and acceptance-test files add 654 lines and remove 3. The task/spec/report/index updates are also included in the staged diff.

### Deviations

- Minimal routing/session/i18n wiring and the UI-only polling helper were needed but are outside the task's four listed paths; no new dependency was added.
- The implementation diff exceeds the 300-line guidance; D-55 makes this non-blocking. Generated lockfiles were not changed.
- No generated OpenAPI snapshot was edited; its stale response metadata is recorded in D-132 for architect review.

### Decisions made by implementer

- **D-132, pending architect review:** follow the identity module/task and Rust handlers for 202 export creation/deletion responses and the export-status body, using a narrow local client cast while leaving the generated OpenAPI snapshot untouched in this UI task. Matching the stale generated response metadata or hand-editing generated API output was rejected. Reversal: correct the source OpenAPI contract in an architect-owned contract change and regenerate the client, then remove the narrow cast.

### Spec issues noticed

- `contracts/openapi.json` records 200 for export creation and account deletion, and no response body for export status, while `spec/modules/identity.md` and the Rust handlers specify 202 and a status payload. D-132 records the client-side choice pending architect review.

### Not done / not verified

- No browser-based E2E or manual accessibility audit was run; neither is in this task's Tests table. The task's named alertdialog/focus acceptance test passed.
- No environment-limited acceptance check remains after the existing PostgreSQL helper metadata was restored. No image was built or pulled and no new container was created.

### Follow-up suggestions

Correct the canonical OpenAPI response metadata and regenerate the client in an architect-owned contract task.

## Questions

- Q-1 (implementer, 2026-11-01): The task/module specify 202 for `POST /account/export` and `DELETE /account`, and a JSON body for `GET /account/export/{id}`; `contracts/openapi.json` currently documents 200 for create/delete and omits the status body. The Rust handlers return 202.
  Options: (a) follow the task/module and handlers, keep the narrow client typing, and report the stale generated contract; (b) adapt the UI to the generated 200/no-content entries.
  Recommendation: (a), because the task and identity module agree with the server behavior.
  Resolved by implementer, pending architect review: followed option (a) and recorded D-132; generated OpenAPI output remains untouched in this UI task.
