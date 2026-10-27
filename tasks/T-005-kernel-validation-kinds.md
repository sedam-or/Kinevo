---
id: T-005
title: Add validated newtypes and the field-kind validators to the kernel
milestone: M1
kind: feature
status: review
depends_on: [T-003, T-004]
requirements: [VAL-01, VAL-02, VAL-03, VAL-04, VAL-05, VAL-06, VAL-10, CQ-06, DOM-07, ID-FR-14]
branch: m1-t005-kernel-validation-kinds
budget_lines: 400
assignee: "Codex"
---

# T-005: Add validated newtypes and the field-kind validators to the kernel

## Goal

One kernel module implements the normalisation and validation of every field kind in `spec/standards/validation.md` §2 that M1 needs. Constrained values (`Email`, `ShortText`, `Key`, `HttpsUrl`, `Digits`, `OpaqueToken`, `IanaTz`, `Locale`) are newtypes that cannot be built from invalid input. A shared fixtures file drives the Rust tests now and the TypeScript tests later.

## Context

> **VAL-02** (`spec/standards/validation.md` §1): Normalisation is applied before validation and before uniqueness checks: strings are trimmed (except `secret_text`), Unicode NFC-normalised, and for `email`/`key` lower-cased. The stored value is the normalised value.
> **VAL-03**: Strings reject NUL and control characters (U+0000–U+001F, U+007F–U+009F) except `\n` (and `\t` in `long_text`); lone surrogates and non-characters (U+FFFE/FFFF) are rejected; zero-width and bidi override characters (U+200B–U+200F, U+202A–U+202E, U+2066–U+2069) are removed from `short_text` and rejected in `key`/`email`/`url` kinds.
> **VAL-04**: Length limits count Unicode scalar values (not bytes, not UTF-16 units).
> **VAL-05**: Every numeric field has explicit min/max; integers are 53-bit-safe on the wire (≤ 2^53−1).
> **VAL-06**: Enumerations are closed; unknown value ⇒ 422 `enum_invalid` with `details.allowed`.
> **VAL-10**: Regular expressions are linear-time (Rust `regex` crate); no user-supplied patterns.
> **VAL-01**: Failures of steps 4–7 are collected and returned together as `errors[]`.
> **CQ-06**: Ids, emails, titles, … are distinct types with private fields and validating constructors.
> **DOM-07**: Emails are stored lower-cased and Unicode NFC-normalised.

Field-kind table (`validation.md` §2), the kinds in scope here:

| Kind | Normalisation | Rules | Field code |
|---|---|---|---|
| `short_text` | trim, NFC, VAL-03 | 1–N scalars (N per field), no line breaks | `required`, `too_long`, `too_short`, `invalid_characters` |
| `secret_text` | none | 1–4096 | `required`, `too_long` |
| `email` | trim, NFC, lower-case | ≤ 254; local part ≤ 64; exactly one `@`; domain labels 1–63, total ≤ 253, at least one dot (or punycode); no spaces/quotes/comments | `email_invalid` |
| `key` | trim, lower-case | regex given per field | `key_invalid` |
| `https_url` | trim | absolute `https://`; host required; no userinfo; ≤ 2048; `http` only for loopback | `url_invalid` |
| `iana_tz` | trim | member of embedded tz database | `timezone_invalid` |
| `digits` | trim | `^[0-9]{N}$` | `digits_invalid` |
| `opaque_token` | — | `^[A-Za-z0-9_-]{43}$` | `token_invalid` |
| `enum` | — | closed set | `enum_invalid` |
| `int` | — | integer within bounds | `int_invalid`, `out_of_range` |

> **Fixtures** (`validation.md` §5): `spec/fixtures/validation/*.json` lists, per field kind, valid and invalid examples including: empty, whitespace-only, max and max+1, emoji (multi-scalar graphemes), combining marks, RTL override, NUL, Turkish dotless i for `email`, full-width digits. Rust and TypeScript tests load the same fixtures (TST-03).
> **Identity validation table** (`modules/identity.md` §6): `email` as kind; `display_name` `short_text` 1–80; `timezone` `iana_tz`; `locale` enum `en`,`id`; `totp code` `digits` exactly 6; tokens `opaque_token` 43 chars.

## Scope

**In:**
- `kinevo_kernel::validate` with: `normalize_short_text`, `normalize_long_text` (the `\r\n`→`\n` and `\t` rules), `Collector` (accumulates `FieldError`s with a path; `finish()` returns `Result<(), AppError>` with `validation_failed` and all errors in order, VAL-01).
- Newtypes with private fields and `parse(raw, ...)` constructors returning `Result<_, FieldCode>`: `Email`, `ShortText` (carries the bound it was built with), `Key` (takes a compiled `regex::Regex` supplied by the caller), `HttpsUrl` (parsed with the `url` crate; loopback hosts `localhost`, `127.0.0.0/8`, `::1` may use `http`), `Digits`, `OpaqueToken`, `IanaTz` (checked against `jiff`'s bundled tz database), `Locale` (`En`, `Id`), `SecretText` (wraps `Secret<String>` from T-004; 1–4096; never trimmed).
- `bounded_int(value: i64, min, max) -> Result<i64, FieldCode>` rejecting values above 2^53−1.
- `enum_check(value, allowed: &[&str]) -> Result<&str, (FieldCode, allowed)>`.
- `spec/fixtures/validation/` JSON files, one per kind above, plus one test in `crates/kinevo-kernel/tests/validation_fixtures.rs` that loads every file and asserts each example.

**Out:**
- `money`, `date`, `local_time`, `instant`, array and `jsonb` validators: `instant` already exists in T-002; the rest arrive with the module that needs them.
- Password rules (min length, common list, NFKC): T-021.
- HTTP extraction (`ValidatedJson` etc.): T-016.
- The TypeScript fixture loader (a later web task reuses the files).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-kernel/Cargo.toml` | modify | deps `unicode-normalization`, `regex`, `url`, `serde_json` (dev only) |
| `crates/kinevo-kernel/src/lib.rs` | modify | `pub mod validate;` |
| `crates/kinevo-kernel/src/validate/mod.rs` | create | `Collector`, `bounded_int`, `enum_check` |
| `crates/kinevo-kernel/src/validate/text.rs` | create | normalisation, `ShortText`, `SecretText`, `Key`, `Digits`, `OpaqueToken` |
| `crates/kinevo-kernel/src/validate/net.rs` | create | `Email`, `HttpsUrl` |
| `crates/kinevo-kernel/src/validate/tz.rs` | create | `IanaTz`, `Locale` |
| `crates/kinevo-kernel/tests/validation_fixtures.rs` | create | loads the fixtures |
| `spec/fixtures/validation/{short_text,secret_text,email,key,https_url,iana_tz,digits,opaque_token,enum,int}.json` | create | `{ "kind", "params", "valid":[{"input","normalised"}], "invalid":[{"input","code"}] }`; `secret_text.json` covers: leading/trailing spaces kept verbatim, 4096 chars valid, empty → `required`, 4097 chars → `too_long` |
| `spec/standards/open-source-stack.md` | modify | add rows for `unicode-normalization`, `regex`, `url` if missing (CQ-16) |

## Interfaces

```rust
pub struct Email(/* private */);       impl Email { pub fn parse(raw: &str) -> Result<Self, FieldCode>; pub fn as_str(&self) -> &str; }
pub struct ShortText(/* private */);   impl ShortText { pub fn parse(raw: &str, min: usize, max: usize) -> Result<Self, FieldCode>; pub fn as_str(&self) -> &str; }
pub struct Key(/* private */);         impl Key { pub fn parse(raw: &str, pattern: &Regex) -> Result<Self, FieldCode>; }
pub struct HttpsUrl(/* private */);    impl HttpsUrl { pub fn parse(raw: &str) -> Result<Self, FieldCode>; pub fn as_str(&self) -> &str; } // trailing "/" of a bare origin removed
pub struct Digits(/* private */);      impl Digits { pub fn parse(raw: &str, len: usize) -> Result<Self, FieldCode>; }
pub struct OpaqueToken(/* private */); impl OpaqueToken { pub fn parse(raw: &str) -> Result<Self, FieldCode>; }
pub struct IanaTz(/* private */);      impl IanaTz { pub fn parse(raw: &str) -> Result<Self, FieldCode>; }
pub enum Locale { En, Id }             impl Locale { pub fn parse(raw: &str) -> Result<Self, FieldCode>; pub fn as_str(self) -> &'static str; }
pub struct SecretText(Secret<String>); impl SecretText { pub fn parse(raw: String) -> Result<Self, FieldCode>; }
pub struct Collector { /* errors: Vec<FieldError> */ }
impl Collector {
    pub fn new() -> Self;
    pub fn check<T>(&mut self, path: &str, r: Result<T, FieldCode>) -> Option<T>;
    pub fn finish(self) -> Result<(), AppError>;
}
```

Field-code details (`too_long` → `details.max`, `too_short` → `details.min`, `enum_invalid` → `details.allowed`, `out_of_range` → `details.min`/`max`) use `Details` from T-003.

## Business rules

1. Length counts Unicode scalar values. A 80-scalar `display_name` made of 80 emoji is valid, an 81st is `too_long`.
2. Normalisation happens before validation, so `"  Ada  "` becomes `Ada` and is then measured.
3. For `short_text`, zero-width and bidi characters are *removed* and then measured. For `key`, `email` and `https_url` they make the value invalid (`key_invalid`, `email_invalid`, `url_invalid`).
4. `İ` handling for `email`: lower-casing uses Unicode `to_lowercase` and applies NFC after it, so the stored form is stable (fixture "Turkish dotless i").
5. `Collector::finish` returns all errors at once, in the order they were checked.

## Security

- Inputs are S1–S3 depending on the field; no function logs or formats the raw input in an error. Errors carry only a code and bounds.
- `SecretText` never trims, never appears in `Debug` output (it wraps `Secret`).
- All patterns are literal in this crate and use the linear-time `regex` crate (VAL-10). `Key::parse` receives patterns from callers, which are compile-time literals only.
- Bidi/zero-width stripping protects against spoofed display names and keys.

## Implementation notes

- Control-character check: reject `c.is_control()` except the allowed `\n`/`\t`; Rust `char` cannot hold lone surrogates, so that rule is satisfied by decoding. Reject U+FFFE and U+FFFF explicitly.
- `IanaTz`: use `jiff::tz::TimeZone::get(name)` and require the canonical name to round-trip; reject `""`, `"UTC+7"`-style and `"local"`.
- `HttpsUrl`: after `Url::parse`, require no username/password, a host, no fragment, and `scheme == "https"` unless the host is loopback. Remove only a trailing `/` of an empty path.
- Fixture JSON keys are stable so a later TypeScript test can read the same files. Add a `"$comment"` field to each file naming the requirement.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** every example in `spec/fixtures/validation/*.json` **When** parsed by the matching kind **Then** valid inputs yield the `normalised` value and invalid ones yield the listed code | `validation_fixtures::val_fixtures_all_kinds` |
| AC-2 | **Given** 80 emoji and 81 emoji as `display_name` (1–80) **When** parsed **Then** the first is valid and the second is `too_long` with `details.max = 80` | `validate::text::tests::val_04_counts_scalars` |
| AC-3 | **Given** `"  Ada\u{200B}  "` **When** parsed as `short_text` **Then** the result is `Ada`; **When** parsed as `key` **Then** `key_invalid` | `validate::text::tests::val_03_zero_width_removed_or_rejected` |
| AC-4 | **Given** `"A@B.COM"` and a full-width-`@` variant **When** parsed as `email` **Then** `a@b.com` is accepted and the variant is `email_invalid` | `validate::net::tests::dom_07_email_lowercase_nfc` |
| AC-5 | **Given** `"http://example.com"` and `"http://localhost:8080"` **When** parsed as `https_url` **Then** the first is `url_invalid`, the second is accepted | `validate::net::tests::https_only_except_loopback` |
| AC-6 | **Given** `"Asia/Jakarta"`, `"UTC+7"` **When** parsed as `iana_tz` **Then** the first is accepted, the second is `timezone_invalid` | `validate::tz::tests::id_fr_14_timezone_and_locale` |
| AC-7 | **Given** three failing fields **When** checked through one `Collector` **Then** `finish` returns `validation_failed` with three errors in check order | `validate::tests::val_01_collects_all_errors` |
| AC-8 | **Given** the value 2^53 **When** passed to `bounded_int` with a larger max **Then** `out_of_range` | `validate::tests::val_05_safe_integer_limit` |

## Tests

```
cargo nextest run -p kinevo-kernel
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget (fixture JSON counts toward the budget only up to 150 lines; keep examples minimal but cover every case listed under *Fixtures*).
- [ ] New dependencies are in the stack map.
- [ ] Report below is filled in.

## Report

### Summary

The kernel now normalizes and validates all ten in-scope field kinds, collects field errors in check order, and exposes constrained values through private-field newtypes. Shared fixtures cover valid and invalid boundary cases, including verbatim secret text.

### Acceptance results

| Criterion | Named test | Result |
|---|---|---|
| AC-1 | `validation_fixtures::val_fixtures_all_kinds` | PASS |
| AC-2 | `validate::text::tests::val_04_counts_scalars` | PASS; 80 emoji accepted, 81 rejected with `details.max = 80` |
| AC-3 | `validate::text::tests::val_03_zero_width_removed_or_rejected` | PASS |
| AC-4 | `validate::net::tests::dom_07_email_lowercase_nfc` | PASS |
| AC-5 | `validate::net::tests::https_only_except_loopback` | PASS |
| AC-6 | `validate::tz::tests::id_fr_14_timezone_and_locale` | PASS |
| AC-7 | `validate::tests::val_01_collects_all_errors` | PASS |
| AC-8 | `validate::tests::val_05_safe_integer_limit` | PASS |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `cargo nextest run -p kinevo-kernel` | 0 | 38 tests passed; 0 failed or skipped. |
| `just fmt` | 0 | `cargo fmt --all` completed. |
| `just check` | 0 | Clippy passed; 41 tests passed; cargo-deny passed; spec check found 0 issues in 60 files; contrast failures 0; task index 0 problems in 132 tasks. Five unused-license-allowance warnings and the existing `uuid::Uuid::new_v4` clippy configuration warning remain non-failing. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated `tasks/index.md` for this task's `review` status. |
| `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks. |
| `python3 scripts/spec_tool.py check` | 0 | 0 findings in 60 spec files. |

### Files changed

`crates/kinevo-kernel/Cargo.toml`, `Cargo.lock` (generated), `crates/kinevo-kernel/src/lib.rs`, `crates/kinevo-kernel/src/validate/{mod,text,net,tz}.rs`, `crates/kinevo-kernel/tests/validation_fixtures.rs`, ten `spec/fixtures/validation/*.json` fixtures, `spec/standards/open-source-stack.md`, `spec/index.md`, `spec/13-roadmap.md`, this task file, and generated `tasks/index.md`.

### Deviations

Added `ShortText::parse_with_details` alongside the required `parse` interface so AC-2 can expose the requested `details.max` value. Enabled Jiff's bundled timezone database because this crate had default features disabled. Updated `spec/index.md` and the M1 roadmap row as required by the mapping rule; `tasks/index.md` is generated. The diff has 745 insertions and 22 deletions (767 changed lines), excluding the generated 297-line `Cargo.lock`; the ten fixture JSON lines are within the 150-line fixture cap. This exceeds the approximate 400-line guidance and ships under D-55.

### Spec issues noticed

None outstanding. D-56 resolved Q-1: `secret_text.json` is part of the shared fixture set.

### Environment-limited

None.

### Not done / not verified

No acceptance criteria remain unverified. Browser/TypeScript fixture consumption is a later task and was not run here.

## Questions

- Q-1 (implementer, 2026-10-26): The task's scope includes `SecretText` and its Context says the fixtures cover the in-scope field kinds. However, `spec/standards/validation.md` §5 requires a fixture for each field kind, while the task's Files list omits `spec/fixtures/validation/secret_text.json`. Adding that fixture would touch a file not listed under Files; omitting it would leave the shared fixtures incomplete for an in-scope kind.
  Options: (a) add `secret_text.json` to the task's Files and require the shared fixture test to cover it; (b) explicitly exclude `SecretText` from the shared fixtures and clarify how §5's per-kind requirement applies.
  Recommendation: (a), because it keeps the task's stated scope and the spec's shared-fixture contract aligned.
  Resolved (architect): D-56, add `secret_text.json` to the fixture set and cover its verbatim normalization, 1–4096 length bounds, and empty input.
