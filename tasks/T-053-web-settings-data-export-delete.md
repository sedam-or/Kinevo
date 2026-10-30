---
id: T-053
title: Build the data settings (export and account deletion)
milestone: M1
kind: feature
status: in_progress
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

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
