---
id: T-022
title: Implement refresh-token families, rotation, reuse detection and the grace cache
milestone: M1
kind: feature
status: in_progress
depends_on: [T-020, T-004, T-012]
requirements: [ID-FR-05, ID-FR-06, ID-FR-07, AUTH-01, SEC-AC-04, SM-22]
branch: m1-t022-identity-refresh-tokens
budget_lines: 420
assignee: ""
---

# T-022: Implement refresh-token families, rotation, reuse detection and the grace cache

## Goal

The identity module has a tested session core: it creates a refresh-token family with its first token, rotates tokens with reuse detection, serves a duplicate refresh inside the grace window with the same token pair, and revokes families. HTTP endpoints come later (T-024).

## Context

> **AUTH §3 (normative)**:
> ```
> refresh(presented_token):
>   h = sha256(presented_token)
>   BEGIN (system context) ; row = SELECT ... FROM refresh_tokens WHERE token_hash = h FOR UPDATE
>   if row is none                         → 401 unauthenticated
>   family = SELECT ... FOR UPDATE row.family_id
>   if family.revoked_at is set            → 401 unauthenticated
>   if now ≥ family.absolute_expires_at or now ≥ row.idle_expires_at → revoke(family,'expired') ; 401
>   if row.used_at is none:
>         issue new token T2 (idle_expires_at = now + idle_ttl, capped by absolute) ; row.used_at = now ; row.replaced_by = T2
>         family.last_used_at = now ; COMMIT → 200 (T2 + new access token)
>   else (token already used):
>         cached = grace_cache.get(h)
>         if cached exists                 → return the SAME token pair ; COMMIT
>         else  → revoke(family,'reuse_detected') ; audit 'auth.refresh_reuse' ; COMMIT ; 401 refresh_reuse_detected
> ```
> **Grace cache**: after every successful rotation the server keeps `sha256(old token) → (new refresh token, new access token)` in a process-local cache for `auth.refresh.grace` seconds, LRU of 10 000 entries. Plaintext tokens live only in RAM for ≤ 10 s. On restart or multi-node a replay inside the window is treated as reuse (accepted and documented).
> **ID-FR-05**: Refresh tokens rotate on every use. Presenting an already-used token revokes the whole family and returns `refresh_reuse_detected`. Concurrent refresh within `auth.refresh.grace` returns the *same* new pair.
> **ID-FR-07**: Changing password or disabling an account revokes all refresh families immediately; access tokens die within their TTL.
> **AUTH-01**: tests for normal rotation; reuse after grace ⇒ family revoked; concurrent double-submit within grace ⇒ no revocation and both callers hold valid tokens; expired idle; expired absolute. With injected clock.
> **Schema** (`06` §4): `refresh_token_families(id, account_id, device_label, mfa, created_at, last_used_at, absolute_expires_at, revoked_at, revoked_reason in logout|reuse_detected|password_changed|admin|expired)`; `refresh_tokens(id, family_id, account_id, token_hash bytea unique, issued_at, idle_expires_at, used_at, replaced_by)`.
> **SM-22 / tokens**: refresh tokens are 256-bit random values, base64url 43 chars, stored as SHA-256 only (T-004 `encode_token`, `hash_token`, `TokenGen`).
> **Policies** (supplied as values by the caller via `SessionPolicy`): `auth.access_token.ttl` (600 s), `auth.refresh_token.idle_ttl` (14 d), `auth.refresh_token.absolute_ttl` (30 d), `auth.refresh.grace` (10 s).
> **Contexts**: this runs in `SystemTx` (ADR-0011, `system_auth` policies); family creation during registration runs inside the provisioning `Tx` (T-025) through the same functions via the `TxConn` trait (T-012).
> **Concurrency (TST-20)**: two concurrent refreshes with the same token must not both rotate: the `FOR UPDATE` on the token row serialises them; the second sees `used_at` set and takes the grace path.

## Scope

**In:**
- `domain::session`: `SessionPolicy { access_ttl, idle_ttl, absolute_ttl, grace }` (seconds), pure functions `idle_expiry(now, policy, family_absolute)` (min of idle and absolute), `decide(row, family, now, cached_present) -> RefreshDecision::{Unknown, Revoked, Expired, Rotate, Grace, Reuse}` (the §3 algorithm as a pure state machine, SM-style table test over all inputs).
- `infra::sessions` repository on `refresh_token_families`/`refresh_tokens`: `create_family_with_token(conn: &mut impl TxConn, ids, tokens, clock_now, account_id, device_label, mfa, policy) -> NewSession { family_id, refresh_token: Secret<String> }`, `lock_by_hash(tx: &mut SystemTx, hash) -> Option<(TokenRow, FamilyRow)>`, `mark_rotated`, `revoke_family(conn, family_id, reason, now)`, `revoke_all_for_account(conn, account_id, reason, now, except: Option<FamilyId>)`, `list_families(conn, account_id) -> Vec<FamilySummary>`, `prune_expired(tx: &mut SystemTx, cutoff) -> u64` (used by the job in T-026).
- `app::refresh::RefreshService { db, clock, ids, tokens, codec: Arc<TokenCodec> (kinevo-http), cache: GraceCache, audit: AuditWriter }` with `refresh(&self, presented: Secret<String>, policy: SessionPolicy, client: Client) -> Result<Refreshed, AppError>` implementing §3, and `Refreshed { access_token: String, expires_in: u32, refresh_token: Secret<String>, account_id, family_id }`.
- `GraceCache` (moka, 10 000 entries, per-entry TTL = grace): `put(old_hash, pair)`, `get(old_hash)`; values hold `Secret<String>` tokens and are zeroised on drop.
- Audit on reuse: action `auth.refresh_reuse` with `details {family_id}` (id only), account id as subject.
- Emits no events (login emits `AccountLoggedIn`).

**Out:**
- Endpoints, cookies, CSRF, Android body flow: T-024.
- Login and lockout: T-023. Password change revocation call sites: T-026.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/src/domain/session.rs` | create | pure decision function and policy |
| `crates/kinevo-mod-identity/src/infra/sessions.rs` | create | repository |
| `crates/kinevo-mod-identity/src/app/refresh.rs` | create | `RefreshService`, `GraceCache` |
| `crates/kinevo-mod-identity/src/app/mod.rs`, `infra/mod.rs`, `domain/mod.rs` | modify | exports |
| `crates/kinevo-mod-identity/tests/refresh.rs` | create | component and concurrency tests |
| `crates/kinevo-mod-identity/Cargo.toml` | modify | `moka` |

## Interfaces

```rust
pub enum RefreshDecision { Unknown, Revoked, Expired, Rotate, Grace, Reuse }
pub fn decide(row: &TokenRow, family: &FamilyRow, now: Instant, grace_cached: bool) -> RefreshDecision;
pub struct Refreshed { pub access_token: String, pub expires_in: u32, pub refresh_token: Secret<String>, pub account_id: AccountId, pub family_id: FamilyId }
impl RefreshService { pub async fn refresh(&self, presented: Secret<String>, policy: SessionPolicy, client: Client) -> Result<Refreshed, AppError>; }
pub enum Client { Web, Android }
```

Failure mapping: unknown token, revoked family and expired → `unauthenticated` (401, no hint); reuse → `refresh_reuse_detected` (401).

## Business rules

1. The new token's `idle_expires_at` is `min(now + idle_ttl, family.absolute_expires_at)`; `absolute_expires_at` is fixed at family creation.
2. A rotation sets `used_at` and `replaced_by` on the old token, `last_used_at` on the family, and inserts the new token — in one transaction.
3. Expiry (idle or absolute) revokes the family with reason `expired` and returns 401; the revocation is committed even though the request fails (the §3 algorithm commits).
4. A used token whose hash is in the grace cache returns the *same* new refresh and access tokens (access token re-issued with the original `jti`, `iat`, `exp` stored in the cache).
5. A used token not in the cache → family revoked `reuse_detected`, audit entry, 401 `refresh_reuse_detected`; **all** tokens of the family die with it.
6. The access token's `mfa` claim is the family's `mfa` flag; `sid` is the family id.
7. Revoking by reason `password_changed`/`admin` revokes every non-revoked family of the account (optionally except the current).

## Security

- Plaintext refresh tokens exist only in memory (`Secret`), in the response, and in the grace cache for ≤ grace seconds; the database holds only SHA-256 (DOM-10).
- No token, hash or family secret is logged. Audit `details` contain the family id only.
- Unknown / revoked / expired failures are indistinguishable to the caller (same 401, same body).
- Constant-time handling: lookups are by hash equality in SQL; no application comparison of secrets.

## Implementation notes

- Use `SELECT … FOR UPDATE` on the token row, then on the family row, in that order always, to avoid deadlocks.
- The Android refresh body flow is handled in T-024; this service takes the presented token as a `Secret<String>` regardless of transport.
- `GraceCache` must count hits/misses for the later metric (`cache_hits_total{cache="refresh_grace"}`); expose counters through a plain struct, no metrics crate yet.
- All time comes from the injected `Clock`; the cache uses explicit expiry timestamps compared to that clock (not wall-clock TTL), so tests with `FixedClock` are exact.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** every combination of row/family/time/cache flags **When** `decide` runs **Then** the result equals the §3 table (table-driven, exhaustive over the enumerated cases) | `session::tests::auth_01_decision_table` |
| AC-2 | **Given** a fresh family **When** refreshed once **Then** a new token and access token are returned, the old row has `used_at` and `replaced_by`, `last_used_at` moved to the injected now | `refresh::auth_01_normal_rotation` |
| AC-3 | **Given** a rotated token presented again within grace **When** refreshed **Then** the identical pair is returned and the family stays active | `refresh::id_fr_05_grace_returns_same_pair` |
| AC-4 | **Given** a rotated token presented after grace **When** refreshed **Then** `refresh_reuse_detected`, the family is revoked with `reuse_detected`, a `auth.refresh_reuse` audit row exists, and the newest token no longer works | `refresh::sec_ac_04_reuse_revokes_family` |
| AC-5 | **Given** two concurrent refreshes of the same token **When** both finish **Then** both succeed with the same pair and the family is not revoked | `refresh::tst_20_concurrent_double_submit` |
| AC-6 | **Given** the clock beyond idle expiry (and separately beyond absolute expiry) **When** refreshed **Then** 401 and the family is `revoked` with reason `expired` | `refresh::auth_01_idle_and_absolute_expiry` |
| AC-7 | **Given** three families for an account **When** `revoke_all_for_account(PasswordChanged, except current)` runs **Then** only the other two are revoked | `refresh::id_fr_07_revoke_others` |
| AC-8 | **Given** an unknown token, a revoked family's token and an expired token **When** refreshed **Then** all three responses are identical 401 `unauthenticated` | `refresh::no_oracle_between_failure_causes` |
| AC-9 | **Given** the DB after a refresh **When** inspected **Then** no column contains the plaintext tokens, and a log capture contains no token substring | `refresh::sec_ac_45_no_plaintext_tokens` |
| AC-10 | **Given** more than 10 000 cache entries **When** inserted **Then** the cache stays bounded | `refresh::tests::cac_1_grace_cache_bounded` |

## Tests

```
cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
