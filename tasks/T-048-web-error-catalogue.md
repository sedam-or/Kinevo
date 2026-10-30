---
id: T-048
title: Add the error-code message catalogue (en, id) and the error mapper
milestone: M1
kind: feature
status: review
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

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [x] Scope is complete; D-55 budget overage and minimal wiring deviations are recorded below.
- [x] Report below is filled in.

## Report

### Summary

Added a registry-derived TypeScript error metadata table and drift generator, a safe `problem+json` mapper with ordered field errors and network fallback, localized message lookup, and gates for registry parity and required API/field/network messages. The existing API error title/detail entries were retained; this task adds the 25 field-code messages and network copy in both locales and updates the validation and policy-bound detail copy. Each locale now contains 469 messages.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `errors::err_01_ts_table_matches_registry` | pass |
| AC-2 | `errors::uxs_01_error_messages_complete` | pass |
| AC-3 | `errors::fe_03_validation_maps_all_fields` | pass |
| AC-4 | `errors::unknown_code_falls_back` | pass |
| AC-5 | `errors::network_error_local_code` | pass |
| AC-6 | `errors::details_feed_icu_arguments` | pass |

### Commands run

| Command | Result |
|---|---|
| `pnpm --dir web exec vitest run src/api/errors.test.ts` | final exit 0; all 6 named acceptance tests passed. Earlier TDD runs failed first because the mapper did not exist, then on missing field/network copy and missing ICU bounds; those failures were fixed. |
| `pnpm --dir web check:error-codes` | exit 0; generated metadata matches the registry. |
| `pnpm --dir web check:i18n` | exit 0; 469 messages, no findings. |
| `pnpm --dir web typecheck` | exit 0. |
| `pnpm --dir web lint` | final exit 0; four reduced-motion `!important` warnings remain. An earlier lint attempt found six formatting/import-order errors, corrected before the final run. |
| `pnpm --dir web test` | exit 0; 47 tests passed across 24 files. |
| `cargo nextest run --workspace --test-threads 1 -E 'test(err_01_catalogs_match_registry)'` | exit 0; 1 passed, 385 filtered. |
| `just check` | final exit 0; Rust nextest 385 passed / 1 skipped, web tests 47 passed, plus lint, build, cargo-deny, contrast, spec, task-index and OpenAPI drift gates. The first full attempt exposed a stale Rust catalogue-count assertion (it counted every `error.*` key as a registry API error); the assertion now counts only registered API-code title/detail keys. |
| `python3 scripts/tasks_index.py` then `python3 scripts/tasks_index.py --check` | generator and final check completed after setting this task to `review`; final result: 0 problems across 132 tasks. |
| `python3 scripts/spec_tool.py check` | exit 0; 0 findings across 60 spec files. |

### Files changed

Counts exclude `tasks/T-048-web-error-catalogue.md`, `tasks/RUN_REPORT.md` and generated `tasks/index.md`.

| Path | Added / removed |
|---|---:|
| `web/scripts/gen-error-codes.ts` | +188 / -0 |
| `web/src/api/error-codes.ts` | +153 / -0 |
| `web/src/api/errors.ts` | +119 / -0 |
| `web/src/api/errors.test.ts` | +103 / -0 |
| `web/scripts/check-i18n.ts` | +47 / -0 |
| `web/src/i18n/en.json` | +29 / -2 |
| `web/src/i18n/id.json` | +29 / -2 |
| `crates/kinevo-http/tests/http_core.rs` | +11 / -1 |
| `web/package.json` | +3 / -1 |

The implementation diff is 682 additions and 6 removals (688 changed lines), which exceeds the 390-line guidance by 298 lines. No lockfile or new dependency changed. D-55 makes this budget guidance rather than a blocker.

### Deviations

- The interface says `mapProblem(res: Response)`, while AC-5 requires converting a rejected fetch into a local `network_error`; the mapper accepts either a `Response` or `Promise<Response>` and maps promise rejection.
- The task interface describes scalar-only `details`, while AC-6 requires `details.bounds.{min,max}`; the type accepts the safe nested bounds object and flattens those values into ICU arguments.
- `web/package.json` is minimally wired for generation and lint drift checking, and the existing Rust catalogue test was narrowed to actual registry API keys so adding field/network message keys does not invalidate it. These are small supporting changes beyond the Files table and are recorded for architect review.
- Four existing reduced-motion `!important` lint warnings remain; they are warnings and did not fail lint or `just check`.

### Not done / not verified

- No browser E2E journey was run for this task; the task's listed tests and all `just check` gates passed.
- No environment-limited checks for this task.
## Questions

Resolved by implementer, pending architect review: the interface signature `mapProblem(Response)` does not express AC-5's rejected-fetch case; the mapper also accepts a promise and normalizes rejection to `network_error`. The scalar-only `details` interface does not express AC-6's nested `bounds` input; safe numeric bounds are accepted and passed as ICU arguments. Supporting lint/generator wiring and the stale Rust key-count assertion adjustment are recorded under Deviations. No blocker remains.
