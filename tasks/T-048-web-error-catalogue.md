---
id: T-048
title: Add the error-code message catalogue (en, id) and the error mapper
milestone: M1
kind: feature
status: in_progress
depends_on: [T-047, T-041]
requirements: [ERR-01, ERR-02, FE-03, UXS-01, UXS-05]
branch: m1-t048-web-error-catalogue
budget_lines: 390
assignee: "OpenAI Codex"
---

# T-048: Add the error-code message catalogue (en, id) and the error mapper

## Goal

Every registry error code has `error.<code>.title` and `.detail` in both languages, and one mapper turns `problem+json` into the UI error model so screens branch on `code`, never on text.

## Context

> **Registry** (`spec/standards/error-handling.md`): codes are owned by `kinevo-kernel::errors`; each has an HTTP status, a client retry class, and message keys `error.<code>.title` / `.detail` in both catalogues (UXS-01). English titles in the registry are normative; Indonesian text is written at implementation time and reviewed later (M-L10N). Field-level codes (§2.2) have `error.field.<code>` messages with `{min}`/`{max}`-style arguments from `details`.
> **ERR-02 retry classes**: `never`, `after_change`, `backoff`, `reauth`. The UI uses them to choose retry affordances: `backoff` shows Retry with delay, `reauth` routes to sign-in with `next`, `after_change` shows the form with the fix, `never` shows the message only.
> **FE-03**: `problem+json` maps to `AppError { code, status, fields, details, requestId }`; the UI branches on `code`, never on text. `details` hold ids, enums and numbers only.
> **Copy rules** (`09-design-system.md` §9): what happened, why if known, what to do; no blame; sentence case; no exclamation marks.
> **Process-level codes** (§2.9) never reach the client and get no messages.

## Scope

**In:**
- `web/src/api/errors.ts`: `AppError` class, `mapProblem(response)` (also for network failures → `network_error` local code), retry-class lookup from a generated table.
- `web/scripts/gen-error-codes.ts` generating `web/src/api/error-codes.ts` (code → status, retry) by parsing the registry tables in `spec/standards/error-handling.md`, the same source as the Rust sync test (T-003), so the two tables cannot diverge; the generated file is committed and drift-checked in `pnpm lint`.
- `error.<code>.title|detail` for all API codes and `error.field.<code>` for all field codes in `en.json` and `id.json`, merged into the T-047 files; the T-047 gate extended to require them.
- `useErrorMessage(appError)` returning `{ title, detail }` with ICU arguments from `details`.

**Out:**
- Rendering components (ErrorCard exists: T-046).

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/api/errors.ts, error-codes.ts` | create | mapper and generated table |
| `web/scripts/gen-error-codes.ts` | create | generator |
| `web/src/i18n/en.json, id.json` | modify | error messages |
| `web/scripts/check-i18n.ts` | modify | require error keys |
| `web/src/api/errors.test.ts` | create | AC-1…AC-6 |

## Interfaces

```ts
export class AppError { code: string; status: number; requestId?: string; fields: FieldError[]; details: Record<string, string | number | boolean | string[]>; retry: 'never'|'after_change'|'backoff'|'reauth' }
export async function mapProblem(res: Response): Promise<AppError>;
export function useErrorMessage(e: AppError): { title: string; detail: string };
```

## Business rules

1. Unknown codes from a newer server map to the generic `internal_error` messages and keep the code for display of the reference id.
2. `detail` text never includes server-provided free text; only catalogue text with ICU arguments from `details`.
3. A network failure yields code `network_error` (local), retry `backoff`.
4. Validation problems keep all `errors[]` entries in order.

## Security

- The mapper drops any response field not in the problem shape.
- Request ids are shown, never request or response bodies.

## Implementation notes

- The detail copy is authored per code in one pass; keep each under 140 characters.
- Indonesian: natural-formal, `kamu` form as in the existing catalogue rows.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the registry table **When** compared with `error-codes.ts` **Then** codes, statuses and retry classes match | `errors::err_01_ts_table_matches_registry` |
| AC-2 | **Given** every API code **When** the gate runs **Then** `error.<code>.title` and `.detail` exist in both languages | `errors::uxs_01_error_messages_complete` |
| AC-3 | **Given** a 422 problem with two field errors **When** mapped **Then** `fields` keeps both in order | `errors::fe_03_validation_maps_all_fields` |
| AC-4 | **Given** a response with `code: unknown_future_code` **When** mapped **Then** generic messages and the request id are shown | `errors::unknown_code_falls_back` |
| AC-5 | **Given** a fetch that throws **When** mapped **Then** `network_error` with retry `backoff` | `errors::network_error_local_code` |
| AC-6 | **Given** `policy_value_invalid` with `details.bounds` **When** formatted **Then** the min and max appear in the message | `errors::details_feed_icu_arguments` |

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
