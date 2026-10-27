---
id: T-017
title: Add access-token verification, AuthCtx, and the setup, module and permission gates
milestone: M1
kind: feature
status: review
depends_on: [T-016]
requirements: [API-10, API-12, API-23, AUTH-02, RBAC-01, RBAC-02, RBAC-03, SEC-05, SEC-07, ADM-FR-01, MOD-04, MOD-08, ADR-0007]
branch: m1-t017-http-auth-gates
budget_lines: 450
assignee: "OpenAI Codex"
---

# T-017: Add access-token verification, AuthCtx, and the setup, module and permission gates

## Goal

Every route passes through one gate that applies, in order, the setup gate, authentication, the module gate and the permission check. Handlers receive an `AuthCtx` with the account id and role loaded from the database (never from token claims) and cannot take the tenant from the request.

## Context

> **ADR-0007 / AUTH §2**: Access token = compact JWS, algorithm **HS256**, key derived by HKDF from `KINEVO_SECRET_KEY` (label `kinevo/jwt/v1`, T-004), header `kid`, claims `sub, sid, jti, iat, exp, mfa`; verification allow-lists exactly this algorithm. A `kid` identifies the derivation version; rotation overlaps by one access-token TTL.
> **AUTH-02**: Verification rejects `alg: none`, wrong `kid`, expired, not-yet-valid (skew ≤ 30 s). Access tokens are stateless; the residual revocation window is `auth.access_token.ttl`.
> **API-10**: Authenticated endpoints require `Authorization: Bearer <access_token>`; missing/invalid/expired → 401 `unauthenticated` with `WWW-Authenticate: Bearer`. **API-12**: claims never include email, role names or permissions; permissions are loaded server-side (cached).
> **Decision order** (`rbac.md` §1): 1. Route is public? → allow. 2. Valid access token → account loaded (cached ≤ 60 s) → `status = active`, else 403 `account_disabled`. 3. Module enabled? else 404 `module_disabled`. 4. Permission key ∈ role's set? else 403 `forbidden`. 5. Entitlement (not in M1). 6. Ownership by RLS (404 for foreign ids).
> **API §10**: A disabled module's endpoint returns 404 `module_disabled` (`details.module`); an unauthenticated caller gets 401 first.
> **Roles** (`rbac.md`): `account_owner` (every active account) and `instance_admin` (`is_instance_admin = true`): owner plus instance administration. Derived server-side from the account row. A `disabled` or `deleting` account has no role (403 `account_disabled`, except `GET /instance`).
> **ADM-FR-01**: Before `setup_completed`, every route except `/setup/*`, `/livez`, `/readyz` returns 409 `setup_required`. After completion, `/setup/*` returns 409 `setup_already_completed`.
> **SEC-05**: The tenant is taken **only** from the authenticated context and never from path, query or body. Unauthenticated requests reach only the public route list. **SEC-07**: a foreign resource is indistinguishable from a missing one.
> **RBAC-02**: A generated test iterates every (route × role) pair and asserts the matrix: allowed pairs reach the handler, denied pairs get 403. **RBAC-03**: permission keys are validated by a `PermissionKey` newtype and exist in the registry. **MOD-08**: a descriptor referencing an unknown permission key fails a test.
> **MOD-04**: Handlers check a cached switch (TTL `modules.cache_ttl_seconds`, invalidated on switch) and return 404 `module_disabled` when off.
> **Caches** (`containers-and-caching.md` §2, CAC-1): permission set cache key `account_id`, 60 s, max 10 000 entries, invalidated on `AccountDisabled`, role change, logout-all; correctness must not depend on caches (tests run with caches disabled and enabled).

## Scope

**In:**
- `kinevo_http::token`: `KeyRing { current_kid, keys: Vec<(kid, Secret<[u8;32]>)> }` built from the HKDF subkey (T-004); `TokenCodec::issue(&Claims) -> String` and `verify(&str, now) -> Result<Claims, TokenError>` using `jsonwebtoken` with an explicit `Validation` (algorithms = [HS256], `validate_exp`, leeway 30 s, required claims, `kid` must be in the ring, `iat` not more than 30 s in the future). `Claims { sub: AccountId, sid: FamilyId, jti: Uuid, iat, exp, mfa: bool }`. `FamilyId` is defined here via `define_id!`. Time comes from the injected `Clock` (the library's own clock is disabled).
- `kinevo_http::auth`: `Role::{AccountOwner, InstanceAdmin}`, `AuthCtx { account_id, session_id, role, mfa }` with `is_admin()` and `require_admin() -> Result<AdminProof, AppError>`; `AccountCache` (moka, 10 000 entries, TTL 60 s) over the `AccountAccess` port (T-008) with `invalidate(account_id)` and `invalidate_all()`.
- `kinevo_http::rbac`: `PermissionTable::from_descriptors(&[&ModuleDescriptor]) -> Result<Self, RbacError>` (rejects duplicate keys and keys whose first segment is not the descriptor's module), `allows(role, key)`; `check_routes(&PermissionTable, &[RouteSpec]) -> Result<(), RbacError>` fails if a `RouteSpec` names an unknown permission.
- `kinevo_http::gate`: a `tower` layer `Gate` installed by `RouteTable::into_router` (T-016) that reads the `RouteSpec` of the matched route and applies the decision order above, inserting `AuthCtx` into request extensions. Setup gate uses `InstanceState::get()`; the module gate uses `ModuleSwitches::is_enabled(spec.module)`; core modules are never gated off (descriptor kind from a `ModuleKinds` map supplied at build time).
- Extractor `Auth` (reads `AuthCtx` from extensions; its absence in a non-public route is a programming error → `internal_error`).
- Gate behaviours for route classes: `Access::Public` routes skip auth but still pass the setup gate and module gate; `GET /instance` and `/modules` are handled by admin (T-034/T-036) with their own `RouteSpec`s.
- Test doubles use the fakes from T-008.

**Out:**
- Issuing tokens on login/refresh and refresh-token storage: T-022…T-024.
- Rate limiting (T-018) and idempotency (T-019) layers; they are separate layers in the same chain, ordered by `RouteTable::into_router`.
- `Tx::begin_admin`: the call site must follow `ctx.require_admin()?` (enforced by T-042).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-http/Cargo.toml` | modify | deps `jsonwebtoken`, `moka` (future), `kinevo-ports` (already) |
| `crates/kinevo-http/src/token.rs` | create | `KeyRing`, `TokenCodec`, `Claims` |
| `crates/kinevo-http/src/auth.rs` | create | `Role`, `AuthCtx`, `AccountCache`, `AdminProof` |
| `crates/kinevo-http/src/rbac.rs` | create | `PermissionTable`, `check_routes` |
| `crates/kinevo-http/src/gate.rs` | create | the layer |
| `crates/kinevo-http/src/route.rs` | modify | `into_router` installs `Gate` |
| `crates/kinevo-http/tests/gates.rs` | create | AC tests with fake ports |
| `spec/standards/open-source-stack.md` | modify | `jsonwebtoken`, `moka` rows if missing |

## Interfaces

```rust
pub struct Claims { pub sub: AccountId, pub sid: FamilyId, pub jti: uuid::Uuid, pub iat: Instant, pub exp: Instant, pub mfa: bool }
impl TokenCodec { pub fn issue(&self, c: &Claims) -> String; pub fn verify(&self, token: &str, now: Instant) -> Result<Claims, TokenError>; }
pub enum Role { AccountOwner, InstanceAdmin }
pub struct AuthCtx { /* private fields */ }  impl AuthCtx { pub fn account_id(&self) -> AccountId; pub fn role(&self) -> Role; pub fn require_admin(&self) -> Result<AdminProof, AppError>; }
pub struct PermissionTable; impl PermissionTable { pub fn allows(&self, role: Role, key: &str) -> bool; }
```

`require_admin` returns `forbidden` for an owner.

## Business rules

1. Algorithm, key and claims are allow-listed: `alg: none`, `HS384`, a token signed with another key, an unknown `kid`, a missing claim, `exp` in the past, `iat` more than 30 s ahead: all `unauthenticated` (401, `WWW-Authenticate: Bearer`), and the response never says which check failed.
2. The role is `InstanceAdmin` iff the loaded account row says so. A token claim cannot elevate (there is no role claim).
3. Disabled or deleting → 403 `account_disabled` (before the module and permission checks), except routes flagged `allow_disabled` in `RouteSpec` (only `GET /instance`; add the boolean `allow_disabled` to `RouteSpec` in this task, default false).
4. Gate order for a non-public route: setup gate → bearer → account → module → permission. A missing bearer on a route of a disabled module yields 401.
5. A route naming a permission that no descriptor defines makes `check_routes` fail at startup and in tests.
6. With caches disabled (`AccountCache` TTL 0) behaviour is identical.

## Security

- Signing key is a `Secret<[u8;32]>` from `derive_subkey(Jwt)`; never logged. `TokenError` carries no token text.
- Constant-time signature verification is provided by `jsonwebtoken`/`hmac`; the codec adds no comparisons of its own.
- The account cache stores only `{id, status, is_instance_admin, version}`.
- `AuthCtx` has private fields and no `Default`; only the gate constructs it.

## Implementation notes

- Disable `jsonwebtoken`'s default validation of `nbf` unless the claim exists; explicitly pass required claims `["sub","sid","jti","iat","exp","mfa"]`.
- Build the RBAC matrix test (RBAC-02) generically: it takes `RouteTable::specs()` plus `PermissionTable`, spawns a test app with a fake handler per route returning 200, and checks owner/admin/anonymous for each route. In M1 the module tasks add their routes to a shared list consumed by T-042's matrix; this task provides the generator and tests it with synthetic routes.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** tokens: valid; `alg: none`; HS384; wrong key; unknown `kid`; expired; `iat` +31 s; missing `sid` **When** verified **Then** only the valid one passes | `token::tests::auth_02_verification_matrix` |
| AC-2 | **Given** a token at `exp − 1 s` and the clock at `exp + 1 s` plus leeway 30 s **When** verified **Then** valid inside leeway, rejected after | `token::tests::auth_02_skew_boundaries` |
| AC-3 | **Given** a non-public route without a bearer **When** requested **Then** 401 with `WWW-Authenticate: Bearer` and no hint of the cause | `gates::api_10_missing_or_bad_bearer` |
| AC-4 | **Given** an account with status `disabled` and a valid token **When** requested **Then** 403 `account_disabled`; the same account on `GET /instance` (flagged `allow_disabled`) gets 200 | `gates::rbac_disabled_account_blocked_except_instance` |
| AC-5 | **Given** a route of module `x` switched off **When** requested with a valid token **Then** 404 `module_disabled` with `details.module = "x"`; without a token 401 | `gates::mod_04_module_gate_after_auth` |
| AC-6 | **Given** owner and admin accounts and routes needing an admin permission **When** called **Then** owner 403 `forbidden`, admin 200 | `gates::rbac_02_role_matrix_generated` |
| AC-7 | **Given** setup not completed **When** any non-setup route is requested **Then** 409 `setup_required`; after completion `/setup/*` returns 409 `setup_already_completed`; `/livez` always 200 | `gates::adm_fr_01_setup_gate_table` |
| AC-8 | **Given** a `RouteSpec` naming `foo.bar.baz` unknown to descriptors **When** `check_routes` runs **Then** an error naming the route | `rbac::tests::mod_08_unknown_permission_fails` |
| AC-9 | **Given** a token whose account row says `is_instance_admin = false` but whose payload carries an extra `role: "instance_admin"` claim **When** verified and used **Then** the role is `AccountOwner` | `gates::api_12_claims_cannot_elevate` |
| AC-10 | **Given** a disabled account cached for 60 s **When** `invalidate(account_id)` is called **Then** the next request reloads and sees the new status; with TTL 0 the result is the same | `auth::tests::cac_1_cache_invalidation_and_equivalence` |
| AC-11 | **Given** a handler that reads `Auth` **When** a foreign account id is placed in path, query and body **Then** the handler's `AuthCtx.account_id()` is still the token's account | `gates::sec_05_tenant_only_from_context` |

## Tests

```
cargo nextest run -p kinevo-http
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

- **Summary.** Implemented HS256 access-token verification, server-derived `AuthCtx`, a bounded account authorization cache, descriptor-backed permission validation, and setup/auth/module/permission gates installed by `RouteTable`. Account roles come from `AccountAccess`; token claims cannot elevate privileges, and expiry uses the injected clock.
- **Acceptance results.**

  | Criterion | Test | Result |
  |---|---|---|
  | AC-1 | `token::tests::auth_02_verification_matrix` | pass |
  | AC-2 | `token::tests::auth_02_skew_boundaries` | pass |
  | AC-3 | `gates::api_10_missing_or_bad_bearer` | pass |
  | AC-4 | `gates::rbac_disabled_account_blocked_except_instance` | pass |
  | AC-5 | `gates::mod_04_module_gate_after_auth` | pass |
  | AC-6 | `gates::rbac_02_role_matrix_generated` | pass |
  | AC-7 | `gates::adm_fr_01_setup_gate_table` | pass |
  | AC-8 | `rbac::tests::mod_08_unknown_permission_fails` | pass |
  | AC-9 | `gates::api_12_claims_cannot_elevate` | pass |
  | AC-10 | `auth::tests::cac_1_cache_invalidation_and_equivalence` | pass |
  | AC-11 | `gates::sec_05_tenant_only_from_context` | pass |

- **Commands run.**

  | Command | Exit | Result |
  |---|---:|---|
  | `cargo fmt --all` | 0 | formatted |
  | `cargo check -p kinevo-http` | 0 | passed after fixing middleware `Send` and cache API issues |
  | `cargo nextest run -p kinevo-http` | 0 | 27 passed, 0 skipped |
  | `just check` | 0 | lint passed; workspace 94 passed, 0 skipped; cargo-deny passed; spec check 0 findings/60 files; contrast 0 failures; task index 0 problems/132 tasks |
  | `python3 scripts/tasks_index.py` | 0 | regenerated `tasks/index.md` |
  | `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks |

  Earlier iterations exited nonzero while implementing: cargo check/nextest exposed missing wiring and test fixture issues; `just check` first caught clippy findings, then cargo-deny advisory findings from jsonwebtoken's `rust_crypto` provider. Those were corrected; the final commands above pass. Final cargo-deny output includes duplicate-version and unused-license-allowance warnings; clippy emits the existing `clippy.toml` warning for `rand::thread_rng`.
- **Files changed.**

  | Path | Change |
  |---|---|
  | `crates/kinevo-http/Cargo.toml` | +3 / -0 |
  | `crates/kinevo-http/src/token.rs` | +190 / -0 |
  | `crates/kinevo-http/src/auth.rs` | +147 / -0 |
  | `crates/kinevo-http/src/rbac.rs` | +102 / -0 |
  | `crates/kinevo-http/src/gate.rs` | +337 / -0 |
  | `crates/kinevo-http/src/lib.rs` | +4 / -0 |
  | `crates/kinevo-http/src/route.rs` | +24 / -6 |
  | `crates/kinevo-http/src/state.rs` | +5 / -1 |
  | `crates/kinevo-http/src/system_routes.rs` | +3 / -0 |
  | `crates/kinevo-http/tests/gates.rs` | +834 / -0 |
  | `crates/kinevo-http/tests/http_core.rs` | +99 / -5 |
  | `Cargo.lock` | +208 / -6 (generated; excluded from budget) |
  | `tasks/T-017-http-auth-and-gates.md` | +54 / -3 |
  | `tasks/index.md` | +2 / -2 (generated) |
- **Deviations.** The AC-5 fixture uses valid module key `demo`; `x` does not satisfy `ModuleKey` grammar. Expiry is checked against the supplied `now` with 30-second leeway because jsonwebtoken's built-in `validate_exp` reads process time, which bypasses the injected clock. `jsonwebtoken` uses its explicit `aws_lc_rs` provider; `rust_crypto` caused cargo-deny advisory failures. Minimal wiring also changed `lib.rs`, `state.rs`, `system_routes.rs`, and `tests/http_core.rs`; `Cargo.lock` was generated for the approved dependencies. Excluding the generated lockfile, this task changes 1,821 lines against the 450-line guide; the acceptance coverage and required gate wiring account for the overage.
- **Spec issues noticed.** AC-5 uses module key `x`, but `ModuleKey` requires a longer valid key. The task also asks for library `validate_exp` while requiring the injected clock.
- **Not done / not verified.** No environment-limited items; no acceptance checks were skipped. Live database-backed account lookup is outside this task; acceptance uses the T-008 fakes as specified.
- **Follow-up suggestions.** Architect review of Q-1 and Q-2 below.

## Questions

- Q-1: AC-5 gives a one-character example module key `x`, which does not satisfy the `ModuleKey` grammar. Resolved by implementer, pending architect review: use valid synthetic key `demo` while testing the same disabled-module behavior.
- Q-2: The task asks for jsonwebtoken `validate_exp` while also requiring expiry to use the injected clock. Resolved by implementer, pending architect review: disable the library's process-clock expiry check and apply its 30-second leeway against `verify`'s `now` argument.
