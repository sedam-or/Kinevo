---
id: T-021
title: Add password hashing, policy checks and the common-password list
milestone: M1
kind: feature
status: review
depends_on: [T-020, T-005, T-006]
requirements: [ID-FR-01, ID-FR-02, ID-FR-03, AUTH-03, AUTH-05, SEC-03, SEC-AC-03]
branch: m1-t021-identity-passwords
budget_lines: 330
assignee: ""
---

# T-021: Add password hashing, policy checks and the common-password list

## Goal

The identity module can hash and verify passwords with argon2id under a bounded concurrency, enforce the password rules, and burn equivalent time for unknown emails. The embedded common-password list has recorded provenance and licence.

## Context

> **ID-FR-01**: Passwords are hashed with argon2id (parameters from `auth.md`) and never logged or returned; `password_hash` is a PHC string starting `$argon2id$`.
> **ID-FR-02**: Length between `auth.password.min_length` and 128, Unicode **NFKC**-normalised before hashing, not in the embedded common-password list; **no** composition rules. Table test incl. 128/129 chars, emoji, common password `password1234`.
> **ID-FR-03**: Login failure is indistinguishable for unknown email vs wrong password (same status, body and comparable latency: a dummy argon2id verification runs for unknown emails). Timing test: median difference < 20 % over 200 samples (SEC-AC-03).
> **AUTH §1**: argon2id via the `argon2` crate: memory 64 MiB, iterations 3, parallelism 1, 16-byte random salt, 32-byte output, PHC string. Parameters are technical constants recorded in `open-source-stack.md`; rehash-on-login upgrades old parameters. Hashing runs on a blocking thread pool with a concurrency cap of `min(4, cores)` to bound memory. Common-password check: embedded list of the 10 000 most common passwords (public-domain lists), case-insensitive compare after NFKC → `password_too_common`.
> **AUTH-03**: Argon2id parameters are checked at startup against minimums (m ≥ 19 MiB, t ≥ 2) and a self-benchmark logs a warning if one hash takes > 1 s on the host.
> **Field codes** (`error-handling.md` §2.2): `password_too_short`, `password_too_common`, `too_long`, `required`. Registered in `validation.md` §3: `password` is `secret_text` 1–4096 pre-check, policy min – 128.
> **Provenance**: the common-password list comes from a public list (SecLists "10k-most-common", MIT). Record source URL, commit/date, licence and SHA-256 in `crates/kinevo-mod-identity/data/PROVENANCE.md` and in `standards/open-source-stack.md` (CQ-16). If the licence cannot be confirmed from the upstream repository, stop and ask in *Questions*.
> **Policy** `auth.password.min_length` (int 10–128, default 12).

## Scope

**In:**
- `domain::password`: `PasswordPolicy { min_length }`, `PlainPassword` (wraps `Secret<String>`, built by `PlainPassword::parse(raw: Secret<String>, policy, common: &CommonPasswords) -> Result<Self, FieldCode>` applying NFKC, length in scalar values, common-list check), `CommonPasswords::embedded()` (`OnceLock`, lowercase set built from `include_str!("../data/common-passwords.txt")`).
- `infra::hasher`: `Hasher` with `hash(&PlainPassword) -> Result<PasswordHash, AppError>` (PHC string), `verify(&Secret<String>, &PasswordHash) -> VerifyOutcome::{Ok, OkNeedsRehash, Wrong}`, `verify_dummy(&Secret<String>)` (verifies against a fixed valid dummy hash with the current parameters), `needs_rehash(&PasswordHash) -> bool`. Work runs on `tokio::task::spawn_blocking` behind a `tokio::sync::Semaphore` of `min(4, cores)` permits (injected `HasherConfig { concurrency }` so tests can set 1).
- `Hasher::self_check(&self) -> StartupCheck` validating parameters against minimums and timing one hash (returns `elapsed_ms` so `kinevo-app` can warn; no logging inside).
- The data file `crates/kinevo-mod-identity/data/common-passwords.txt` (10 000 lines, lowercase, one per line) and `PROVENANCE.md`.

**Out:**
- Login/registration flows: T-023, T-025. Password change endpoint: T-026.
- Rate limiting and lockout: T-018, T-023.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/Cargo.toml` | modify | deps `argon2`, `unicode-normalization`, `tokio` (sync, rt), `zeroize` |
| `crates/kinevo-mod-identity/src/domain/password.rs` | create | policy + parse |
| `crates/kinevo-mod-identity/src/infra/hasher.rs` | create | argon2 |
| `crates/kinevo-mod-identity/data/common-passwords.txt` | create | 10 000 lines |
| `crates/kinevo-mod-identity/data/PROVENANCE.md` | create | source, licence, hash, retrieval date |
| `spec/standards/open-source-stack.md` | modify | `argon2` row if missing; list provenance |
| `justfile` | modify | add recipe `test-slow` (`cargo nextest run --workspace --run-ignored only`) |

## Interfaces

```rust
pub struct PlainPassword(Secret<String>);   // NFKC-normalised, validated
impl PlainPassword { pub fn parse(raw: Secret<String>, p: &PasswordPolicy, c: &CommonPasswords) -> Result<Self, FieldCode>; pub fn expose(&self) -> &str; }
pub struct PasswordHash(String);            // PHC
impl Hasher { pub async fn hash(&self, p: &PlainPassword) -> Result<PasswordHash, AppError>;
              pub async fn verify(&self, candidate: &Secret<String>, h: &PasswordHash) -> VerifyOutcome;
              pub async fn verify_dummy(&self, candidate: &Secret<String>);
              pub async fn self_check(&self) -> StartupCheck; }
```

`verify` and `verify_dummy` normalise the candidate with NFKC exactly as `parse` does (otherwise a user who registered with a compatibility character could not sign in).

## Business rules

1. Length counts Unicode scalar values after NFKC; with `min_length = 12`: 11 chars → `password_too_short` (`details.min = 12`), 12 chars OK, 128 OK, 129 → `too_long` (`details.max = 128`).
2. Spaces are allowed and never trimmed; there are no composition rules (`aaaaaaaaaaaa` fails only if on the common list).
3. A list hit is case-insensitive after NFKC: `Password1234` → `password_too_common`.
4. Emoji passwords (e.g. 12 emoji) are valid.
5. `OkNeedsRehash` is returned when the stored PHC parameters differ from the current constants; callers rehash after a successful login.

## Security

- Passwords are `Secret<String>` end to end; `PlainPassword` has no `Debug` content, `Display`, `Clone` or `Serialize` (CQ-10, compile-fail doctest).
- Hashing concurrency is capped to bound memory (64 MiB × permits) against a DoS.
- Comparison of hashes is inside `argon2`; this crate adds no string comparison of secrets.
- Nothing in this module logs a password, hash or timing per account (AUTH-05).

## Implementation notes

- Constants: `ARGON2_M_KIB = 65536`, `ARGON2_T = 3`, `ARGON2_P = 1`, `OUTPUT_LEN = 32`, `SALT_LEN = 16`. They are technical constants (CQ-05 allows them; recorded in the stack map).
- Generate the dummy hash lazily once per `Hasher` with the real parameters so timing matches.
- Salt from the injected `TokenGen` (16 bytes), so tests are deterministic.
- Test timing without wall-clock assertions in CI: assert that `verify_dummy` performs a full argon2 verification by counting calls through a `HashBackend` trait with a counting fake; the real-time median comparison (SEC-AC-03) runs as an `#[ignore]` benchmark-style test `sec_ac_03_timing_oracle` executed by `just test-slow` (define the recipe in this task; record that it was or was not run).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** policy min 12 **When** passwords of 11, 12, 128, 129 scalars are parsed **Then** `password_too_short`, ok, ok, `too_long` with the bounds | `password::tests::id_fr_02_length_bounds` |
| AC-2 | **Given** `password1234`, `PASSWORD1234`, `ｐａｓｓｗｏｒｄ１２３４` (full-width) **When** parsed **Then** all `password_too_common` | `password::tests::id_fr_02_common_list_after_nfkc` |
| AC-3 | **Given** 12 emoji and a password with leading/trailing spaces **When** parsed **Then** accepted and spaces preserved | `password::tests::id_fr_02_unicode_and_spaces` |
| AC-4 | **Given** a hashed password **When** inspected **Then** it starts with `$argon2id$v=19$m=65536,t=3,p=1$` and verifies; a different password is `Wrong` | `hasher::tests::id_fr_01_phc_and_verify` |
| AC-5 | **Given** a hash with weaker parameters **When** verified **Then** `OkNeedsRehash` | `hasher::tests::rehash_on_old_parameters` |
| AC-6 | **Given** the counting backend **When** `verify_dummy` runs **Then** exactly one full verification happens | `hasher::tests::id_fr_03_dummy_verification_does_work` |
| AC-7 | **Given** semaphore size 1 and three concurrent hashes **When** run **Then** at most one runs at a time | `hasher::tests::concurrency_is_capped` |
| AC-8 | **Given** parameters m = 8 MiB **When** `self_check` runs **Then** it reports below-minimum (AUTH-03) | `hasher::tests::auth_03_minimums_enforced` |
| AC-9 | **Given** `PlainPassword` **When** formatted with `{:?}` **Then** the value is absent (compile-fail doctests for `Clone`/`Display`/`Serialize`) | `password::tests::cq_10_plain_password_is_redacted` |
| AC-10 | **Given** `data/common-passwords.txt` **When** counted **Then** 10 000 lowercase unique lines and its SHA-256 equals the value in `PROVENANCE.md` | `password::tests::common_list_matches_provenance` |

## Tests

```
cargo nextest run -p kinevo-mod-identity
just check
just test-slow   # runs #[ignore] timing tests; not part of check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally; whether `just test-slow` was run is stated in the Report.
- [ ] Only the files listed under *Files* changed; diff within budget (the data file is excluded from the count).
- [ ] Licence and provenance of the list are recorded (CQ-16).
- [ ] Report below is filled in.

## Report

- **Test-first red:** the initial `cargo nextest run -p kinevo-mod-identity` failed to compile because the password types and provenance data did not exist yet.
- **Acceptance tests:** final `cargo nextest run -p kinevo-mod-identity` — 19 passed, 1 ignored. `cargo test --doc -p kinevo-mod-identity` — 3 compile-fail doctests passed. `just test-slow` — SEC-AC-03 timing comparison passed (1 test, 798.970 seconds; 197 other tests skipped by the recipe).
- **Workspace gate:** `just check` — exit 0; 197 tests passed, 1 ignored; formatting and Clippy passed; `cargo deny check` passed with dependency-duplicate and unused-license-allowance warnings; `scripts/spec_tool.py check` reported 0 findings in 60 files; `scripts/contrast.py` reported 0 failures; `scripts/tasks_index.py --check` reported 0 problems across 132 tasks.
- **Environment-limited:** none. The PostgreSQL testcontainers checks ran and passed.
- **Files touched:** `crates/kinevo-mod-identity/Cargo.toml`, generated `Cargo.lock`, `crates/kinevo-mod-identity/src/domain/mod.rs`, `src/domain/password.rs`, `src/infra/mod.rs`, `src/infra/hasher.rs`, `crates/kinevo-mod-identity/data/common-passwords.txt`, `data/PROVENANCE.md`, `justfile`, `spec/standards/open-source-stack.md`, `spec/index.md`, `spec/13-roadmap.md`, this task file and generated `tasks/index.md`.
- **Deviations:** the change is 854 added-plus-removed lines against the 330-line guidance; the data file is excluded by the task and the generated lockfile is excluded by D-55. Added only the two module-export declarations needed to wire the files. `Hasher` accepts the injected `Clock` required by repository rules and returns integer milliseconds from `self_check`; the ignored real-time timing test uses a monotonic timer as required by SEC-AC-03. The pinned SecLists file has 10,001 unique lowercase entries and lacks the task's required `password1234`; the embedded list keeps its first 9,999 entries and appends that required example to meet the exact 10,000-entry acceptance contract. The repository has no `.github/CODEOWNERS` file or `.github/` directory, so no owner mapping could be updated.

## Questions

- Resolved by implementer, pending architect review: `spec/standards/auth.md` describes the source as public-domain, while this task explicitly selects MIT-licensed SecLists. Followed the task's named source and included its full MIT notice in `PROVENANCE.md`; if the spec intended a strict public-domain-only restriction, a different source is needed.
- Resolved by implementer, pending architect review: the pinned source has 10,001 entries and omits `password1234`, which the acceptance criteria require rejecting. Kept the first 9,999 upstream entries and added the required example as entry 10,000; an alternative is to select a different upstream list.
