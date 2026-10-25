# Standard — Validation and sanitisation (VAL)

Server validation is authoritative; client validation (zod schemas in `web/`) mirrors it for UX and is tested against the same fixtures.
Applies to every request body, query parameter, path parameter, header, uploaded file, imported file (ICS), sync payload, AI output and stored JSON document.

## 1. Pipeline (server) — order is normative

```
bytes ──▶ (1) size limit (stream) ──▶ (2) content-type check ──▶ (3) decode (UTF-8 JSON / multipart / raw)
      ──▶ (4) shape: serde with deny_unknown_fields ──▶ (5) normalise (trim, NFC, case) ──▶ (6) field-kind validation (bounds, charset)
      ──▶ (7) cross-field rules ──▶ (8) authorisation (ownership via RLS + permission) ──▶ (9) domain validation (state, invariants)
      ──▶ (10) persistence constraints (CHECK/FK/unique) ──▶ map any constraint failure to a registry error, never a raw DB error
```

| ID | Rule |
|---|---|
| VAL-01 | All failures of steps 4–7 are collected and returned together as `errors[]` (422); steps 1–3 fail fast (413/415/400); step 8 yields 401/403/404 (API-23); steps 9–10 yield 409/422 with a registry code. |
| VAL-02 | Normalisation is applied **before** validation and before uniqueness checks: strings are trimmed (except `secret_text`), Unicode NFC-normalised, and for `email`/`key` lower-cased. The stored value is the normalised value. |
| VAL-03 | Strings reject NUL and control characters (U+0000–U+001F, U+007F–U+009F) except `\n` (and `\t` in `long_text`); lone surrogates and non-characters (U+FFFE/FFFF) are rejected; zero-width and bidi override characters (U+200B–U+200F, U+202A–U+202E, U+2066–U+2069) are removed from `short_text` and rejected in `key`/`email`/`url` kinds. |
| VAL-04 | Length limits count **Unicode scalar values** (not bytes, not UTF-16 units); byte limits are additionally enforced where storage matters (`note`, `scene`). |
| VAL-05 | Every numeric field has explicit min/max; integers are 53-bit-safe on the wire (≤ 2^53−1) because clients are JavaScript. |
| VAL-06 | Enumerations are closed; unknown value ⇒ 422 `enum_invalid` with `details.allowed`. Response enums may grow (clients tolerate). |
| VAL-07 | Output encoding: the API returns data as data. No HTML is generated server-side from user content; ICS/CSV/Markdown exports escape per their format (ICS RFC 5545 text escaping; CSV not offered in 1.0). The web renders user text only through React text nodes. |
| VAL-08 | Uploaded files: size cap before reading, magic-number sniffing, MIME allow-list, no archive extraction, no image decoding on the server in 1.0 (no codec attack surface), stored as opaque bytes. |
| VAL-09 | Imported documents (ICS, AI JSON, notes JSON, canvas JSON) are parsed with bounded memory and depth, validated against their schema, and **re-serialised from the validated model** when stored or exported (never echo raw input). |
| VAL-10 | Regular expressions are linear-time (Rust `regex` crate); no user-supplied patterns. |
| VAL-11 | Constraint violations from the database (unique, FK, CHECK) are translated to registry codes by name (`email_taken`, `workspace_name_taken`, …); an unmapped violation is a bug (500 `internal_error`, alert in logs by code `unmapped_constraint` + constraint name). |

## 2. Field-kind catalogue

| Kind | Type on wire | Normalisation | Rules | Error codes |
|---|---|---|---|---|
| `uuid` | string | lower-case | RFC 4122 textual form | `uuid_invalid` |
| `short_text` | string | trim, NFC, VAL-03 | 1–N scalars (N per field), no line breaks | `required`, `too_long`, `too_short`, `invalid_characters` |
| `long_text` | string | NFC, normalise `\r\n`→`\n`, VAL-03 | ≤ N scalars | `too_long`, `invalid_characters` |
| `secret_text` | string | none (never trimmed) | 1–4096 | `required`, `too_long` |
| `email` | string | trim, NFC, lower-case | ≤ 254; local part ≤ 64; exactly one `@`; domain labels 1–63, total ≤ 253, at least one dot (or punycode); no spaces/quotes/comments | `email_invalid` |
| `key` | string | trim, lower-case | regex given per field | `key_invalid` |
| `https_url` | string | trim | absolute `https://`; host required; no userinfo; ≤ 2048; `http` only for loopback | `url_invalid` |
| `iana_tz` | string | trim | member of embedded tz database | `timezone_invalid` |
| `instant` | string | — | RFC 3339 with `Z` or offset (converted to UTC); year 1970–2100; fractional seconds ≤ 3 digits | `instant_invalid` |
| `date` | string | — | `YYYY-MM-DD` valid calendar date within 1970–2100 | `date_invalid` |
| `local_time` | string | — | `HH:MM` 00:00–23:59 (policy `local_time`; `24:00` only where documented as exclusive end) | `time_invalid` |
| `int` | number | — | integer within bounds; no float syntax | `int_invalid`, `out_of_range` |
| `bp` | number | — | integer 0–10000 | `out_of_range` |
| `money` | object | — | `{amount_minor: int ≥ 0 ≤ 10^12, currency: ISO-4217 uppercase from allow-list}` | `money_invalid` |
| `enum` | string | — | closed set | `enum_invalid` |
| `bool` | boolean | — | JSON `true/false` only (no `"true"`, `1`) | `bool_invalid` |
| `digits` | string | trim | `^[0-9]{N}$` | `digits_invalid` |
| `opaque_token` | string | — | `^[A-Za-z0-9_-]{43}$` | `token_invalid` |
| `array<T>` | array | — | length bounds per field; elements validated; duplicates rejected where set-like | `too_many_items`, `duplicate_item` |
| `jsonb:<schema>` | object | per schema | validated against the versioned schema in `contracts.md` | `schema_invalid` + `errors[].path` |

## 3. Per-field rule table (authoritative bounds; modules cite these)

| Field | Kind | Bounds |
|---|---|---|
| `account.email` | `email` | as kind |
| `account.display_name` | `short_text` | 1–80 |
| `password` | `secret_text` | policy min (default 12) – 128; NFKC before hashing; not in common list |
| `workspace.name` | `short_text` | 1–80 |
| `goal.title` / `task.title` | `short_text` | 1–`goals.title_max_chars` / `tasks.title_max_chars` (≤ 500 hard cap by DDL) |
| `milestone.title`, `subtask.title` | `short_text` | 1–500 |
| `goal.description`, `task.notes` | `long_text` | ≤ 20 000 |
| `landscape.title` | `short_text` | 1–200 |
| `note.title`, `canvas.title` | `short_text` | 0–500 |
| `focus.note` | `long_text` | ≤ 2 000 |
| `ai.hint` | `long_text` | ≤ 1 000 |
| `policy.value` | per policy type | registry bounds |
| `idempotency_key` | `key`-like | 8–128 chars `[A-Za-z0-9_-]` |
| `search q` | `short_text` | 2–200 |

## 4. Client side

| ID | Rule |
|---|---|
| VAL-C1 | zod schemas live next to forms in `features/<module>/forms`; they import shared limits from a generated `limits.ts` (built from the OpenAPI document's `minLength`/`maxLength`/`minimum`/`maximum`) so numbers are never duplicated by hand. |
| VAL-C2 | Validation runs on blur and on submit (not on every keystroke for the first interaction); errors are announced via `aria-live="polite"` and linked with `aria-describedby`. |
| VAL-C3 | The client trims/NFC-normalises before sending, but must not assume the server accepted that exact value; it displays the server's stored value from the response. |
| VAL-C4 | Paste of oversized text is truncated **with an announced notice**, never silently. |

## 5. Tests

A fixtures file `spec/fixtures/validation/*.json` (created in M1) lists, per field kind, valid and invalid examples (including: empty, whitespace-only, max and max+1, emoji (multi-scalar graphemes), combining marks, RTL override, NUL, Turkish dotless i for `email`, full-width digits). Rust and TypeScript tests load the same fixtures (TST-03).
