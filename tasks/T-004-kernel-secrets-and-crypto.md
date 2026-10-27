---
id: T-004
title: Add the redacting Secret type and crypto helpers to the kernel
milestone: M1
kind: feature
status: review
depends_on: [T-002]
requirements: [SEC-03, SEC-23, CQ-10, D-19, ADM-FR-06]
branch: m1-t004-kernel-secrets-crypto
budget_lines: 380
assignee: "codex"
---

# T-004: Add the redacting Secret type and crypto helpers to the kernel

## Goal

Secret values cannot be printed, serialised or cloned by accident. One kernel module provides the only key derivation, AEAD, token-hashing and constant-time comparison code in the system.

## Context

> **SEC-03** (`spec/standards/security-by-design.md`): Secrets are read once into types without `Debug`/`Display`/`Serialize`/`Clone` (CQ-10). At rest: passwords argon2id, tokens SHA-256 of 256-bit random values, secrets AEAD (XChaCha20-Poly1305) under HKDF-derived subkeys of `KINEVO_SECRET_KEY` with a `key_id` for rotation. Comparisons are constant-time; secrets are zeroised on drop.
> **CQ-10** (`spec/standards/code-quality.md`): Secret-bearing types implement neither `Debug` (custom redacting impl only), `Display`, `Serialize` (except through an explicit `expose()` call site), nor `Clone` unless unavoidable. Check: compile-fail tests.
> **D-19**: Displayable secrets are masked `••••` + last 4 characters. Password hashes, TOTP seeds and refresh tokens are never displayed or logged.
> **Auth §2** (`spec/standards/auth.md`): Signing keys derive from `KINEVO_SECRET_KEY` via HKDF-SHA256 with distinct `info` labels (`kinevo/jwt/v1`, `kinevo/cursor/v1`, `kinevo/secrets/v1`, `kinevo/csrf/v1`); a `kid` identifies the derivation version.
> **ADM-FR-06** (`spec/modules/admin.md`): Secrets are stored as AEAD ciphertext under the master key `KINEVO_SECRET_KEY` (32 random bytes, base64) using the key id; ciphertext differs per row (random nonce).
> **Technical constants** (`spec/standards/open-source-stack.md` §1): AEAD XChaCha20-Poly1305 with a 24-byte random nonce; HKDF labels as above.

## Scope

**In:**
- `Secret<T: Zeroize>`: no `Clone`, `Display` or `Serialize`. `Debug` prints `Secret(<redacted>)`. `expose(&self) -> &T` is the only accessor. The value is zeroised on drop.
- `MasterKey`: 32 bytes, parsed from base64 (standard alphabet, padded) with a precise error when the length is wrong. It never exposes its bytes.
- `derive_subkey(master, label: KeyLabel) -> Secret<[u8; 32]>`: HKDF-SHA256, where `KeyLabel` is an enum `Jwt`, `Cursor`, `Secrets`, `Csrf`, each with a version number (`v1`).
- `seal(key, plaintext, aad, token_gen) -> Sealed { nonce: [u8; 24], ciphertext: Vec<u8> }` and `open(key, &Sealed, aad) -> Result<Secret<Vec<u8>>, CryptoError>`.
- `hash_token(bytes) -> [u8; 32]` (SHA-256), plus `encode_token`/`decode_token` (base64url without padding, 43 chars for 32 bytes).
- `ct_eq(a, b) -> bool` (constant-time).
- `mask_last4(s: &str) -> String` returning `••••` + last 4 scalar values (D-19). Fewer than 4 characters yields `••••` only.
- Compile-fail doctests proving `Secret` has no `Clone`, `Display` or `Serialize`.

**Out:**
- Password hashing (argon2): T-021.
- The `secrets` table and the `SecretStore` implementation: T-032.
- Reading `KINEVO_SECRET_KEY` from the environment: T-039.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-kernel/Cargo.toml` | modify | deps `zeroize`, `subtle`, `sha2`, `hkdf`, `chacha20poly1305`, `base64` |
| `crates/kinevo-kernel/src/lib.rs` | modify | `pub mod secret; pub mod crypto;` |
| `crates/kinevo-kernel/src/secret.rs` | create | `Secret<T>`, compile-fail doctests |
| `crates/kinevo-kernel/src/crypto.rs` | create | `MasterKey`, `KeyLabel`, `derive_subkey`, `seal`, `open`, `hash_token`, token encoding, `ct_eq`, `mask_last4` |
| `spec/standards/open-source-stack.md` | modify | add the `base64` row (purpose: master key and token encoding; MIT/Apache-2.0) |

## Interfaces

```rust
pub struct Secret<T: zeroize::Zeroize>(/* private */);
impl<T: Zeroize> Secret<T> { pub fn new(v: T) -> Self; pub fn expose(&self) -> &T; }
pub struct MasterKey(/* private */);
impl MasterKey { pub fn from_base64(s: &Secret<String>) -> Result<Self, CryptoError>; }
pub enum KeyLabel { Jwt, Cursor, Secrets, Csrf }
impl KeyLabel { pub fn info(self, version: u8) -> String; } // "kinevo/jwt/v1"
pub fn derive_subkey(master: &MasterKey, label: KeyLabel, version: u8) -> Secret<[u8; 32]>;
pub struct Sealed { pub nonce: [u8; 24], pub ciphertext: Vec<u8> }
pub fn seal(key: &Secret<[u8; 32]>, plaintext: &[u8], aad: &[u8], rng: &dyn TokenGen) -> Sealed;
pub fn open(key: &Secret<[u8; 32]>, sealed: &Sealed, aad: &[u8]) -> Result<Secret<Vec<u8>>, CryptoError>;
pub fn hash_token(raw: &[u8]) -> [u8; 32];
pub fn encode_token(raw: &[u8; 32]) -> String;          // 43 chars base64url, no padding
pub fn decode_token(s: &str) -> Result<[u8; 32], CryptoError>;
pub fn ct_eq(a: &[u8], b: &[u8]) -> bool;
pub fn mask_last4(s: &str) -> String;
```

## Business rules

1. Every derivation uses a distinct HKDF `info` label (domain separation).
2. A new nonce is drawn from `TokenGen` for every `seal` call, so two seals of the same plaintext differ (ADM-FR-06).
3. The masked form shows at most the last 4 characters (D-19).

## Security

- S3 throughout. No function in this module logs anything.
- AAD binds ciphertext to its context. Callers pass e.g. `"secret:" + name + ":" + account_id`, so a ciphertext copied to another row fails to open (documented on `seal`).
- `ct_eq` must not short-circuit on length differences in a way that leaks content. Compare lengths first, then use `subtle::ConstantTimeEq` for equal lengths.

## Implementation notes

- `chacha20poly1305::XChaCha20Poly1305` with `aead::Aead` and a `Payload { msg, aad }`.
- Use `zeroize::Zeroizing` internally where buffers hold key material.
- A compile-fail doctest looks like: ```` ```compile_fail\nlet s = kinevo_kernel::secret::Secret::new(String::from("x")); let _ = s.clone();\n``` ````.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a `Secret<String>` **When** formatted with `{:?}` **Then** the output is `Secret(<redacted>)` and does not contain the value | `secret::tests::cq_10_debug_is_redacted` |
| AC-2 | **Given** the doctests **When** run **Then** `clone()`, `{}` and `serde_json::to_string` on a `Secret` fail to compile | `secret (doctests: compile_fail)` |
| AC-3 | **Given** one key and one plaintext **When** sealed twice **Then** the nonces and ciphertexts differ and both open to the plaintext | `crypto::tests::adm_fr_06_random_nonce_per_seal` |
| AC-4 | **Given** a sealed value **When** opened with a different AAD or a flipped ciphertext bit **Then** `open` fails | `crypto::tests::open_rejects_wrong_aad_and_tamper` |
| AC-5 | **Given** one master key **When** subkeys for `Jwt` and `Secrets` are derived **Then** they differ, and deriving `Jwt` twice gives the same key | `crypto::tests::hkdf_labels_separate_domains` |
| AC-6 | **Given** a master key string of 31 decoded bytes **When** parsed **Then** an error naming the expected length is returned, without the value | `crypto::tests::master_key_length_checked` |
| AC-7 | **Given** `sk-abcdef1234` and `ab` **When** masked **Then** the results are `••••1234` and `••••` | `crypto::tests::d_19_mask_last4` |
| AC-8 | **Given** 32 random bytes **When** encoded and decoded **Then** the string has 43 base64url characters and round-trips | `crypto::tests::token_encoding_round_trip` |

## Tests

```
cargo nextest run -p kinevo-kernel
cargo test -p kinevo-kernel --doc
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Stack map updated for `base64` (CQ-16).
- [ ] Report below is filled in.

## Report

### Summary

Added redacting, zeroizing `Secret<T>`, a validated 32-byte master key, HKDF domain labels, XChaCha20-Poly1305 sealing/opening, SHA-256 token hashing, base64url token encoding, constant-time equality, and D-19 masking. The eight acceptance tests, SEC-03/SEC-23 supporting tests, and compile-fail doctests pass.

### Acceptance results

| Criterion | Named test | Result |
|---|---|---|
| AC-1 | `secret::tests::cq_10_debug_is_redacted` | pass |
| AC-2 | `secret (doctests: compile_fail)` | pass (3 compile-fail doctests) |
| AC-3 | `crypto::tests::adm_fr_06_random_nonce_per_seal` | pass |
| AC-4 | `crypto::tests::open_rejects_wrong_aad_and_tamper` | pass |
| AC-5 | `crypto::tests::hkdf_labels_separate_domains` | pass |
| AC-6 | `crypto::tests::master_key_length_checked` | pass |
| AC-7 | `crypto::tests::d_19_mask_last4` | pass |
| AC-8 | `crypto::tests::token_encoding_round_trip` | pass |
| SEC-03 zeroization | `secret::tests::sec_03_secret_zeroizes_on_drop` | pass |
| SEC-23 injected nonce source | `crypto::tests::sec_23_seal_nonce_uses_injected_token_generator` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| Pre-implementation `cargo nextest run -p kinevo-kernel` | 101 (expected red phase) | Test compilation failed on the not-yet-implemented crypto and secret APIs. |
| Initial `cargo fmt --all` | 1 (fixed before final run) | Rust rejected inner doctest comments after `use` items; moved module docs before items. |
| Initial implementation `cargo nextest run -p kinevo-kernel` | 101 (fixed before final run) | Compilation found the same doctest-comment placement plus a test requiring `MasterKey: Debug`; both were corrected. |
| `cargo fmt --all` | 0 | Formatting completed. |
| `cargo nextest run -p kinevo-kernel` | 0 | 22 passed, 0 skipped, including all eight T-004 acceptance tests and two requirement-coverage tests. |
| `cargo test -p kinevo-kernel --doc` | 0 | 3 compile-fail doctests passed. |
| `just check` | 0 | 25 passed, 0 skipped; format, Clippy, cargo-deny, spec, contrast, and task-index gates passed. Existing Clippy config warning for unreachable `uuid::Uuid::new_v4`; cargo-deny emitted 6 unmatched license-allowance warnings. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated `tasks/index.md` with T-004 at `review`; 0 problems in 132 tasks. |
| `python3 scripts/spec_tool.py check` | 0 | 0 findings in 60 spec files (also run by `just check`). |
| `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks (also run by `just check`). |

### Files changed

`crates/kinevo-kernel/Cargo.toml`, `crates/kinevo-kernel/src/lib.rs`, `crates/kinevo-kernel/src/secret.rs`, `crates/kinevo-kernel/src/crypto.rs`, `spec/standards/open-source-stack.md`, `Cargo.lock`; this task file and generated `tasks/index.md`.

### Deviations

No technical deviations. The implementation changes 414 non-lockfile lines across the task's implementation files, exceeding the 380-line guidance by 34; D-55 makes the budget advisory, so the complete scoped implementation is retained. `Cargo.lock` adds 210 lines and is excluded per D-55.

### Spec issues noticed

None.

### Environment-limited

None.

### Not done / not verified

No T-004 acceptance criteria remain unverified.

## Questions

_None yet._
