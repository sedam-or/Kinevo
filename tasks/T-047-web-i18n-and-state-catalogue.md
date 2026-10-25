---
id: T-047
title: Add the i18n runtime, the UX state catalogue (en, id) and the check-i18n gate
milestone: M1
kind: feature
status: todo
depends_on: [T-046]
requirements: [FE-05, FE-06, UXS-01, UXS-05, DS-A11Y-10]
branch: m1-t047-web-i18n-and-state-catalogue
budget_lines: 400
assignee: ""
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

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
