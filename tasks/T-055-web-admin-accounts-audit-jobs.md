---
id: T-055
title: Build the admin screens for accounts, invites, audit and jobs
milestone: M1
kind: feature
status: todo
depends_on: [T-054, T-028, T-038]
requirements: [ID-FR-09, ID-FR-10, ID-FR-13, ADM-FR-05, SEC-AC-10, UXS-02, UXS-04]
branch: m1-t055-web-admin-accounts-audit-jobs
budget_lines: 390
assignee: ""
---

# T-055: Build the admin screens for accounts, invites, audit and jobs

## Goal

An instance admin can manage accounts (status, invites, password-reset links), read the audit log and see job and outbox health, all without any account content.

## Context

> **Accounts** (`modules/identity.md` §2): `GET /admin/accounts` (page of `{id, email, display_name, status, created_at, last_login_at}`, no content data, SEC-AC-10), `PATCH /admin/accounts/{id} {status}` (`last_admin` 409, `version_conflict`), `POST /admin/invites {email?}` (201 `{invite_url}`, token shown once), `POST /admin/accounts/{id}/password-reset` (201 `{reset_url}`, shown once; emailed if SMTP is configured, ID-FR-13). **ID-FR-10**: the last active `instance_admin` cannot be disabled or deleted.
> **Audit** (`modules/admin.md` §2): `GET /admin/audit` with filters `action`, `from`, `to`, `account_id` and cursor paging; entries `{id, at, actor_id, account_id, action, details}`; `details` hold ids, enums and counts only.
> **Jobs**: `GET /admin/jobs/summary` returns counts by kind and state, the oldest queued age and the number of dead deliveries; `POST /admin/outbox/{event_id}/{subscriber}/retry` (202; `not_dead` 409) exists but no endpoint lists dead deliveries yet, so the M1 screen shows the counts only. A dead-delivery list with retry buttons is a follow-up suggestion in the closing list.
> **UX**: tables use real table semantics with sortable headers as buttons; cursor "Load more" is a button; one-time links are shown in a modal with a copy button and the warning that they are shown once. Destructive/impactful status changes (disable account) use an alertdialog naming the account (UXS-04).
> **SEC-AC-10**: admin views contain no content fields (contract test over the schema; the UI renders only the documented columns).

## Scope

**In:**
- `/admin/accounts` (search by email client-side on the loaded page only; server list is paged), status change with confirmation, invite dialog, reset-link dialog.
- `/admin/audit` table with filters in URL search params, relative and absolute times, JSON-free rendering of `details` as key/value chips.
- `/admin/jobs` summary cards and table; refresh button and auto refresh at a UI-constant interval.

**Out:**
- Per-delivery outbox listing and retry buttons: needs an admin endpoint that lists dead deliveries; recorded in the closing suggestions list (the retry endpoint exists, T-038).

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/admin/{AccountsPage,InviteDialog,ResetLinkDialog,AuditPage,JobsPage}.tsx` | create | screens |
| `web/src/features/admin/hooks.ts` | modify | queries |
| `web/src/features/admin/*.test.tsx` | create | tests |
| `web/tests/e2e/admin.a11y.spec.ts` | modify | add the three routes |

## Interfaces

```ts
useAdminAccounts(cursor); usePatchAccount(); useCreateInvite(); useIssueReset(id)
useAudit(filters, cursor); useJobsSummary()
```

## Business rules

1. One-time links are shown once; closing the dialog discards them from state.
2. Filters live in the URL so a view can be shared among admins; `account_id` and times are validated before being sent.
3. The last-admin error shows `data.delete.last_admin`-style inline text mapped from `last_admin`.
4. Times render in the admin's timezone via `useFormat`.

## Security

- Reset and invite URLs never enter logs, query keys or persisted caches.
- No column outside the documented set is rendered, even if the response carries extras (explicit pick).
- All lists require the admin permission server-side; direct URL access by an owner renders `state.forbidden`.

## Implementation notes

- Use `<table>` with `scope` attributes; keep tables readable at 320 px with a stacked layout alternative (DS-A11Y-08).
- Audit `details` rendering is a generic key/value list; no per-action templates in M1.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the accounts response with an extra content-like field **When** rendered **Then** only documented columns show | `admin_ui2::sec_ac_10_only_documented_columns` |
| AC-2 | **Given** disabling the last admin **When** the server answers `last_admin` **Then** the inline message shows and the row is unchanged | `admin_ui2::id_fr_10_last_admin_message` |
| AC-3 | **Given** an invite **When** created **Then** the link is shown once with a copy button and gone after closing | `admin_ui2::invite_link_shown_once` |
| AC-4 | **Given** audit filters in the URL **When** the page loads **Then** requests carry them and the table matches | `admin_ui2::audit_filters_from_url` |
| AC-5 | **Given** the jobs summary **When** rendered **Then** counts per kind/state and the oldest age show with a refresh button | `admin_ui2::jobs_summary_rendered` |
| AC-6 | **Given** disabling an account **When** confirming **Then** an alertdialog names the account with Cancel focused | `admin_ui2::uxs_04_disable_account_alertdialog` |
| AC-7 | **Given** 320 px width **When** the tables render **Then** there is no horizontal page scroll | `admin_ui2::ds_a11y_08_tables_reflow` |
| AC-8 | **Given** the three routes **When** scanned by axe **Then** zero serious/critical violations | `admin.a11y::tst_11_admin_axe_clean` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
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
