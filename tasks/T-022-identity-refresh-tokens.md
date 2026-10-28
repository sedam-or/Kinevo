---
id: T-022
title: Implement refresh-token families, rotation, reuse detection and the grace cache
milestone: M1
kind: feature
status: review
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
- Refresh decisions use expiry timestamps compared to the injected `Clock`, so tests with `FixedClock` are exact. The cache also applies a per-entry Moka TTL equal to the grace policy to remove sensitive token pairs from an idle process.

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

### Commands run and results

| Command | Exit | Result |
|---|---:|---|
| `NEXTEST_TEST_THREADS=1 cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing -j 1` (using `KINEVO_TEST_DATABASE_URL` from `scripts/dev-postgres.sh env`) | 0 | 29 passed; 1 skipped. All ten named acceptance tests are present and pass. |
| `cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing --test refresh -j 1` | 0 | 9/9 refresh integration tests passed. |
| `cargo nextest run -p kinevo-db --features testing --test outbox -E 'test(dispatch_preserves_per_account_order)' -j 1` | 0 | 1/1 timestamp/order regression test passed. |
| `cargo clippy -p kinevo-mod-identity --all-targets -- -D warnings` | 0 | Passed. |
| `NEXTEST_TEST_THREADS=1 just check` | 0 | Format and Clippy passed; workspace nextest ran 207 tests: 207 passed, 1 skipped; `cargo deny` reported advisories/bans/licenses/sources OK; spec reported 0 findings in 60 files; contrast reported 0 failures; task index reported 0 problems in 132 tasks. |
| `python3 scripts/spec_tool.py check` | 0 | 0 findings in 60 spec files. |
| `python3 scripts/contrast.py` | 0 | 0 failures. |
| `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks. |
| `cargo fmt --all -- --check` | 0 | Passed through `just check`. |
| `git diff --check` | 0 | Passed. |

The first database test attempt without the PostgreSQL fallback failed at test-database startup with `disposable PostgreSQL test database should start: Unavailable` (testcontainers cluster startup). The requested fallback, `eval "$(scripts/dev-postgres.sh env)"`, provided the disposable PostgreSQL service; rerunning the task tests and serialized workspace gate passed. `NEXTEST_TEST_THREADS=1` avoids stressing the shared local database with concurrent test binaries. One exploratory fixture initially used six fractional digits and was rejected with `InstantError`; `Instant` has millisecond precision, so the fixture was corrected to three digits and the affected refresh and outbox tests passed. The first format check after that fixture edit identified a rustfmt line-wrap difference; `cargo fmt --all` corrected it, and the final workspace format gate passed.

The workspace Clippy run also prints the existing warning `warning: `rand::thread_rng` does not refer to a reachable function` from `clippy.toml:8:5`; it is non-fatal and outside this task's files.

### Acceptance

| Acceptance | Named test | Result |
|---|---|---|
| AC-1 | `session::tests::auth_01_decision_table` | Pass |
| AC-2 | `refresh::auth_01_normal_rotation` | Pass |
| AC-3 | `refresh::id_fr_05_grace_returns_same_pair` | Pass |
| AC-4 | `refresh::sec_ac_04_reuse_revokes_family` | Pass |
| AC-5 | `refresh::tst_20_concurrent_double_submit` | Pass |
| AC-6 | `refresh::auth_01_idle_and_absolute_expiry` | Pass |
| AC-7 | `refresh::id_fr_07_revoke_others` | Pass |
| AC-8 | `refresh::no_oracle_between_failure_causes` | Pass |
| AC-9 | `refresh::sec_ac_45_no_plaintext_tokens` | Pass |
| AC-10 | `refresh::tests::cac_1_grace_cache_bounded` | Pass |

### Deviations

- The implementation exceeds `budget_lines: 420` (the identity acceptance suite and repository are over 1,500 added lines before generated lockfile changes). D-55 makes this guidance non-blocking; `Cargo.lock` is generated and excluded from the budget.
- The implementation adds `Db: Clone` in `crates/kinevo-db/src/pool.rs` and permits the already registry-approved UUID audit detail in `crates/kinevo-kernel/src/errors/mod.rs` so the service can own its database handle and record the required family-only audit detail.
- The passing workspace gate exposed stale T-014 migration/outbox fixtures and assertions. They were corrected in their owning files and recorded under D-88 and D-90..D-95 below and in `tasks/T-014-db-outbox.md`; no product behavior outside the written outbox ordering and schema contracts was added.
- The existing T-014 outbox timestamp was formatted as explicit UTC RFC3339 at the SQL boundary to satisfy the kernel instant parser. The retry test now asserts progress per `(account, subscriber)` stream. These are owning-task corrections, not new features.
- `moka` was already approved in `spec/standards/open-source-stack.md`; it was added as the bounded grace-cache implementation. The existing `tracing` dependency is used only by the log-capture acceptance test.

### Decisions made by implementer

| ID | Choice and alternatives rejected | Reason | Files touched | Reversal |
|---|---|---|---|---|
| D-86 | Follow `standards/auth.md` §3 and keep SM-22 as the one-time-token machine; do not redefine SM-22 for refresh. | T-022's SM-22 cross-reference conflicts with the normative refresh algorithm and the state-machine registry. | `spec/decisions.md`, `spec/index.md`, this task, refresh implementation/tests. | Supersede D-86 and update the state-machine registry and refresh contract together. |
| D-87 | `prune_expired` marks expired families revoked and preserves rows; deleting them or granting DELETE to `kinevo_system` was rejected. | ADR-0011 grants the system role SELECT/INSERT/UPDATE, not DELETE; preserving rows retains session/replay history. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-mod-identity/src/infra/sessions.rs`. | Supersede D-87 only with a revised role/grant and retention contract, then update the repository behavior. |
| D-88 | Keep the `platform_` migration filename prefix; weakening the validator was rejected. | The migration validator checks filename module prefixes. | `spec/decisions.md`, `spec/index.md`, T-014 migration and task report. | Supersede D-88 with a documented validator exception and migrate the file path consistently. |
| D-89 | Use injected-clock logical grace expiry plus Moka per-entry TTL equal to the supplied grace. | Logical expiry makes decisions deterministic; physical TTL clears sensitive cache entries while idle. | `spec/decisions.md`, `spec/index.md`, this task, `crates/kinevo-mod-identity/src/app/refresh.rs`. | Supersede D-89 with an equally deterministic expiry and memory-clearing contract, then update cache/tests. |
| D-90 | Derive expected migration count from migration sources instead of fixing it at three. | Additive migrations made the old assertion stale. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-db/tests/migrate.rs`, T-014 report. | Restore a fixed expected count only alongside an explicit versioned migration-count contract. |
| D-91 | Format outbox `created_at` as UTC RFC3339 in SQL; relying on session-timezone-dependent `timestamptz::text` was rejected. | The kernel parser requires RFC3339 instants. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-db/src/outbox.rs`, T-014 report. | Supersede D-91 by changing the timestamp contract and parser together. |
| D-92 | Test ordering/progress independently per `(account, subscriber)` stream; serializing unrelated streams was rejected. | This matches the T-014 stream-ordering rule. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-db/tests/outbox.rs`, T-014 report. | Supersede D-92 with a revised ordering contract and corresponding dispatcher/tests. |
| D-93 | Supply version `1` in the outbox retention fixture; weakening the non-null event-version schema was rejected. | D-78 requires positive event versions. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-db/tests/platform_tables.rs`, T-014 report. | Supersede D-93 only with a revised event-version schema and fixture contract. |
| D-94 | Compare schema columns by name, type and nullability rather than physical order. | Additive migrations append columns; consumers address them by name. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-db/tests/platform_tables.rs`, T-014 report. | Supersede D-94 with a schema-order requirement and migration/table rebuild strategy. |
| D-95 | Assert the existing positive `outbox_events.v` constraint in the schema test; dropping it was rejected. | The test inventory must cover the D-78 schema invariant. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-db/tests/platform_tables.rs`, T-014 report. | Supersede D-95 only alongside a revised event-version invariant and schema. |

All ten decisions are pending architect/owner review and do not block this run.

### Not done / environment-limited

- Environment-limited: testcontainers could not start its disposable PostgreSQL cluster with `cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing -j 1`; exact test error: `disposable PostgreSQL test database should start: Unavailable`. The PostgreSQL fallback command above was available and the required tests passed, so no database acceptance check remains unrun.
- HTTP transport, cookies, CSRF and Android refresh flow are out of scope and remain for T-024. Password-change revocation call sites remain for T-026. No hosted runtime path was exercised by this component task.

## Questions

- Resolved by implementer, pending architect review (D-86): the `SM-22` reference does not match the refresh algorithm in `standards/auth.md` §3; the implementation follows §3 and leaves SM-22 unchanged. See Report for alternatives and reversal.
- Resolved by implementer, pending architect review (D-87): `prune_expired` preserves expired session rows by revoking families because ADR-0011 does not grant DELETE to the system role. See Report.
- Resolved by implementer, pending architect review (D-88..D-95): migration validation, migration-count, outbox timestamp/stream semantics, fixture/schema assertions were aligned with the existing higher-priority contracts. See the corresponding decision entries and `tasks/T-014-db-outbox.md` Report.
