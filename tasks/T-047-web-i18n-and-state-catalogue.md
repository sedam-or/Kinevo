---
id: T-047
title: Add the i18n runtime, the UX state catalogue (en, id) and the check-i18n gate
milestone: M1
kind: feature
status: review
depends_on: [T-046]
requirements: [FE-05, FE-06, UXS-01, UXS-05, DS-A11Y-10]
branch: m1-t047-web-i18n-and-state-catalogue
budget_lines: 400
assignee: "OpenAI Codex"
---

# T-047: Add the i18n runtime, the UX state catalogue (en, id) and the check-i18n gate

## Goal

Every user-visible string comes from the `en` and `id` catalogues; the state catalogue of `ux-states.md` exists in both; a CI script proves key parity and ICU validity.

## Context

> **FE-05** (`spec/08-frontend.md`): all user-visible strings come from catalogs `en` and `id` via keys; a missing `id` key falls back to `en` in production and fails CI (`check-i18n`); pluralisation uses `Intl.PluralRules` categories; dates and numbers use `Intl` with the account locale and timezone; no concatenation of translated fragments.
> **UXS-01** (`standards/ux-states.md` §11): every key in the catalogue tables exists in `en.json` and `id.json`; ICU message syntax validates; Indonesian has only the `other` plural category. Rows may list several keys in one cell (for example `setup.step.template` / `.modules` / `.rules`); the EN and ID cells then list the matching texts separated by ` / `.
> **Copy rules** (`09-design-system.md` §9): sentence case, verbs for actions, errors say what happened, why if known and what to do, no blame, no exclamation marks. Indonesian is natural-formal; it is reviewed by a native speaker before 1.0 (UXS-05, maintainer action M-L10N).
> **DS-A11Y-10**: `<html lang>` follows the account locale; mixed-language fragments carry `lang`.
> **Terminology**: user-facing nouns (`term.goal`, `term.task`, …) are message keys so templates can rename them (`05-templates-and-setup.md` §1).
> **FE-06**: the client never derives business "today" from its own clock; formatting only.

## Scope

**In:**
- `web/src/i18n/{en,id}.json` (flat dotted keys) containing every key of `ux-states.md` §2–§10 (EN and ID text copied from the tables) and the six `term.*` keys with their default labels.
- `i18n/index.ts`: i18next + react-i18next + `i18next-icu`, language detection order (account locale after login, else `Accept-Language`/`navigator.language`, else `en`), `<html lang>` sync, `useFormat()` hook (date, time, number via `Intl` with locale and the account timezone passed in).
- `web/scripts/check-i18n.ts` (run by `pnpm lint`): key parity, ICU syntax validation with the `intl-messageformat` parser, plural categories (`id` only `other`), every key used in `src` via `t('…')` exists, every catalogue key of `ux-states.md` is present (the script parses the markdown tables), no unused keys outside an allow-list file with reasons.
- A Biome rule or script check forbidding literal JSX text outside tests and the catalogue (visible strings must go through `t`).

**Out:**
- Error-code messages `error.<code>.title|detail`: T-048.
- Strings for features of later milestones are present in the catalogue because `ux-states.md` lists them; no feature code consumes them yet.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/i18n/en.json, id.json` | create | catalogue keys |
| `web/src/i18n/index.ts` | create | runtime |
| `web/src/lib/format.ts` | create | `useFormat` |
| `web/scripts/check-i18n.ts` | create | gate |
| `web/scripts/i18n.allow` | create | allow-list with reasons |
| `web/src/i18n/i18n.test.ts` | create | AC-1…AC-7 |

## Interfaces

```ts
export function useT(): (key: MessageKey, vars?: Record<string, unknown>) => string;   // MessageKey is a union generated from en.json
export function useFormat(): { date(i: string, opts?): string; time(i: string): string; number(n: number): string };
```

## Business rules

1. `MessageKey` is generated from `en.json`; an unknown key is a type error.
2. In production a missing `id` key renders the `en` text (and CI fails earlier); in development it renders the key with a visible marker.
3. Interpolated values go through ICU arguments; no `+` concatenation of translated pieces (lint).
4. Plural categories are validated per language: `en` one/other, `id` other.

## Security

- Catalogue values are plain text with ICU syntax; rendering never uses `dangerouslySetInnerHTML`.
- User-supplied values (names, titles) are passed as ICU arguments and escaped by React.

## Implementation notes

- Run the markdown-table parser from the script against `spec/standards/ux-states.md`; fail loudly if the table format is unrecognised.
- The Indonesian texts are the spec's; do not rewrite them.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the catalogue tables **When** `check-i18n` runs **Then** every key exists in both files | `i18n::uxs_01_all_catalogue_keys_present` |
| AC-2 | **Given** a key only in `en.json` **When** the gate runs **Then** it fails naming the key | `i18n::fe_05_parity_failure_is_reported` |
| AC-3 | **Given** a broken ICU message **When** validated **Then** the gate fails | `i18n::icu_syntax_validated` |
| AC-4 | **Given** `{count, plural, one {…} other {…}}` **When** formatted for `en` with 1 and 2 and for `id` with 1 **Then** results follow each language | `i18n::plural_rules_per_language` |
| AC-5 | **Given** a locale switch **When** the account locale is `id` **Then** `<html lang>` is `id` and a date uses the account timezone | `i18n::ds_a11y_10_html_lang_and_format` |
| AC-6 | **Given** a component with literal JSX text **When** linted **Then** the rule fails | `i18n::no_literal_text_in_jsx` |
| AC-7 | **Given** the six `term.*` keys **When** a template override is passed **Then** the label changes and the key set is unchanged | `i18n::tpl_03_terms_are_message_keys` |

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
- [x] Scope and budget are resolved under the run decisions: minimum wiring outside the Files list is recorded below; the 400-line guidance overage is not a blocker under D-55.
- [x] Report below is filled in.

## Report

### Summary

Added the typed English/Indonesian i18n runtime, account-locale and timezone formatting, ICU validation, locale plural checks, UX catalogue parser, and a CI gate for catalogue parity, key use, unused keys, JSX text and labels. All 120 UX catalogue entries match the `ux-states.md` text; each locale has 442 messages including the existing error/email catalogues and six terminology keys. Existing visible web copy touched by the new JSX gate now uses catalogue keys.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `i18n::uxs_01_all_catalogue_keys_present` | pass |
| AC-2 | `i18n::fe_05_parity_failure_is_reported` | pass |
| AC-3 | `i18n::icu_syntax_validated` | pass |
| AC-4 | `i18n::plural_rules_per_language` | pass |
| AC-5 | `i18n::ds_a11y_10_html_lang_and_format` | pass |
| AC-6 | `i18n::no_literal_text_in_jsx` | pass |
| AC-7 | `i18n::tpl_03_terms_are_message_keys` | pass |

### Commands run

| Command | Result |
|---|---|
| `pnpm --dir web exec vitest run src/i18n/i18n.test.ts` | final exit 0; 7 acceptance tests passed. Earlier parser iterations failed on table-header/fixture handling; fixed before the final run. |
| `pnpm --dir web typecheck` | exit 0. |
| `pnpm --dir web lint` | exit 0; `check:i18n` reported 442 messages and no findings; four pre-existing reduced-motion `!important` warnings. |
| `pnpm --dir web test` | exit 0; 41 passed. |
| `cargo nextest run --workspace --test-threads 1 -E 'test(id_fr_05_grace_returns_same_pair)'` | exit 0; the isolated database test passed (1 passed, 385 filtered). |
| `eval "$(scripts/dev-postgres.sh env)"` followed by `just check` with the pinned Node 26.13.0 / pnpm 12.16.1 toolchain | final exit 0; Rust nextest 385 passed / 1 skipped, web tests 41 passed, and all lint/build/spec/index/contrast/OpenAPI drift gates passed. `spec_tool.py check` reported 0 findings across 60 files; `tasks_index.py --check` reported 0 problems across 132 tasks. |
| `just check` first full attempt | exit 100 after 339 passed / 1 skipped / 1 failed; `id_fr_05_grace_returns_same_pair` failed in test-database cleanup. The isolated retry and a subsequent full run passed, so this was a transient test cleanup failure, not an environment limitation. |
| `just check` retry without the pinned Node path | exit 1 at web lint: Node 22 selected the missing Corepack file `/home/kepolu/.cache/node/corepack/v1/pnpm/12.16.1/bin/pnpm.cjs` (`MODULE_NOT_FOUND`). Re-running with Node 26.13.0 / pnpm 12.16.1 completed successfully. |
| `python3 scripts/tasks_index.py` and `python3 scripts/tasks_index.py --check` | generator run after status update; final check: 0 problems across 132 tasks. |

### Files changed

Counts exclude the task/run reports and generated task index; `pnpm-lock.yaml` is listed separately as generated lockfile output.

| Path | Added / removed |
|---|---:|
| `web/scripts/check-i18n.ts` | +451 / -0 |
| `web/scripts/i18n.allow` | +30 / -0 |
| `web/src/i18n/i18n.test.ts` | +84 / -0 |
| `web/src/i18n/index.ts` | +79 / -0 |
| `web/src/lib/format.ts` | +24 / -0 |
| `web/src/i18n/en.json` | +423 / -227 |
| `web/src/i18n/id.json` | +423 / -227 |
| `web/package.json` | +6 / -1 |
| `web/pnpm-lock.yaml` | +90 / -0 (generated lockfile) |
| `web/src/main.tsx` | +1 / -0 |
| `web/src/app/components-demo.tsx` | +70 / -50 |
| `web/src/app/router.tsx` | +9 / -5 |
| `web/src/components/Banner.tsx` | +9 / -2 |
| `web/src/components/ConflictCard.tsx` | +7 / -5 |
| `web/src/components/Dialog.tsx` | +9 / -2 |
| `web/src/components/ErrorCard.tsx` | +8 / -6 |
| `web/src/components/ErrorCard.test.tsx` | +1 / -1 |
| `web/src/components/Toast.tsx` | +18 / -11 |
| `web/src/components/Toast.test.tsx` | +5 / -5 |
| `crates/kinevo-http/tests/http_core.rs` | +0 / -4 |

The implementation/test changes total 2,203 added and removed lines excluding the generated pnpm lockfile; this exceeds the 400-line budget guidance by 1,803 lines.

### Deviations

- D-55: the 400-line budget is guidance, not a blocker; the generated pnpm lockfile is excluded from the implementation/test line count.
- The minimum wiring needed to make the lint gate enforce FE-05 is outside the listed Files: added the web package scripts/dependencies and lockfile update, loaded i18n from `main.tsx`, and migrated visible strings in the demo, router and existing feedback components. Their focused tests were updated to assert the translated text. This preserves the UX gate across already-existing screens rather than exempting them.
- The existing Rust HTTP test assumed the shared catalogue could contain only `error.*` and `email.*` keys. Removed that obsolete whole-catalogue restriction while retaining its exact `error.*` registry count assertion, because T-047 adds UXS and terminology entries to those same JSON catalogues.
- The spec defines the six terminology keys but no exact default labels for `term.hard_landscape` and `term.focus_session`; used concise descriptive labels (`Hard landscape` / `Komitmen tetap`, `Focus session` / `Sesi fokus`). Resolved by implementer, pending architect review.
- The demo includes `feedback.section_error.*` copies for its existing selectable failure states; these are UI copy rather than business-policy values.
- No native-speaker rewrite was made; the UX spec's Indonesian catalogue text was copied as written.

### Spec issues noticed

No task/spec contradiction found. The two unspecified terminology defaults are recorded as an implementer decision above and under Questions.

### Not done / not verified

- No browser E2E command is listed for T-047, so none was run for this task. T-047's named tests and all `just check` gates passed.
- Native-speaker Indonesian copy review remains the maintainer action M-L10N before 1.0; it is outside this task's acceptance gate.
- No environment-limited checks for this task.

### Follow-up suggestions

Architect to review the two default terminology labels before the M-L10N copy review.

## Questions

Resolved by implementer, pending architect review: the spec defines `term.hard_landscape` and `term.focus_session` keys but not default copy; concise labels were chosen as listed in Deviations. No blocker remains.
