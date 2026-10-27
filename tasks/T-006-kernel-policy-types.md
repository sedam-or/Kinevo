---
id: T-006
title: Add policy types, typed keys and resolution to the kernel
milestone: M1
kind: feature
status: blocked
depends_on: [T-002, T-003]
requirements: [POL-01, POL-03, POL-04, POL-06, POL-07, POL-08, CQ-05]
branch: m1-t006-kernel-policy-types
budget_lines: 420
assignee: ""
---

# T-006: Add policy types, typed keys and resolution to the kernel

## Goal

The kernel defines how a policy value is typed, bounded, validated and resolved at an instant, and which M1 policy keys exist. Code reads policies only through `PolicyKey<T>` constants; nothing in the kernel touches a database.

## Context

> **Model** (`spec/04-policies.md` §1): Policy key `<module>.<area>.<name>`, lowercase, dot-separated, stable. Types: `int` (min,max), `bool`, `enum` (closed set), `duration_seconds` (int, min,max), `local_time` (`HH:MM`), `bp` (0–10000), `text_key` (message key), `json` (typed by a registered schema). Scope: `instance` or `account`; each key declares `scopes`.
> **Resolution**: For account `A` at instant `t`: the account value with the greatest `effective_from ≤ t` if the key allows account scope and one exists; else the instance value likewise; else the compiled default from the registry. Version is append-only.
> **POL-01**: Every policy key is declared in exactly one module descriptor with type, bounds, default, scopes, `effective_dating` (`immediate` or `future_only`), `snapshot` (bool), `secret` (always false). Registry unit test: no duplicate keys; every key has bounds.
> **POL-03**: `PUT` validates type and bounds server-side → `policy_value_invalid` (`details.bounds`), unknown key `policy_unknown`, scope not allowed for key `policy_scope_invalid`.
> **POL-04**: `effective_from` defaults to now. For `future_only` keys, a value earlier than now is rejected with `policy_effective_in_past`. For `immediate` keys, `effective_from` must still be ≥ the latest existing version's `effective_from` for that scope → else `policy_effective_order`.
> **POL-06**: Decision records store a snapshot (resolved values and `policy_version_id`); `resolve_with_snapshot` is used for replay; live resolution is never used when a snapshot exists.
> **POL-07**: `kinevo-kernel::policy` exposes typed accessors (`PolicyKey<T>`); handlers/use cases cannot read an untyped string key (CQ-05).
> **POL-08**: Bounds are enforced again when loading from the database; a value outside current bounds resolves to the compiled default and logs `policy_out_of_bounds` once per key per process (no value in the log).

The M1 keys (`spec/04-policies.md` §3.3 and §3.5) are: `auth.registration.mode`, `auth.password.min_length`, `auth.access_token.ttl`, `auth.refresh_token.absolute_ttl`, `auth.refresh_token.idle_ttl`, `auth.lockout.max_attempts`, `auth.lockout.window`, `auth.lockout.duration`, `auth.totp.required`, `auth.refresh.grace`, `identity.purge.delay`, `api.idempotency.ttl`, `auth.invite.ttl`, `auth.password_reset.ttl`, `ratelimit.auth_per_minute`, `ratelimit.api_per_minute`, `ratelimit.ai_per_minute`, `ratelimit.upload_per_minute`, `ratelimit.sync_per_minute`, `ratelimit.export_per_minute`, `audit.retention_days`, `modules.cache_ttl_seconds`, `jobs.lease_seconds`, `jobs.retry.max_attempts`, `jobs.retry.base_seconds`, `jobs.retry.cap_seconds`, `export.max_age_days`. Types, bounds, defaults and scopes are exactly those in the tables of §3.3 and §3.5 (all `instance` scope).

## Scope

**In:**
- `kinevo_kernel::policy`:
  - `PolicyType` (`Int{min,max}`, `Bool`, `Enum{allowed}`, `DurationSeconds{min,max}`, `LocalTime`, `Bp`, `TextKey`, `Json{schema}`), `PolicyValue` (typed counterpart), `Scope`, `EffectiveDating`.
  - `PolicySpec { key, ty, default, scopes, effective_dating, snapshot }` and `PolicyKey<T>` (zero-sized typed handle with `const KEY: &'static str`).
  - `validate_value(spec, json) -> Result<PolicyValue, PolicyError>` where `PolicyError` maps to `policy_value_invalid` with `details.bounds`.
  - `check_effective(spec, now, requested, latest_for_scope) -> Result<Instant, PolicyError>` implementing POL-04.
  - `PolicyVersion { id, scope, account_id: Option<AccountId>, key, value, effective_from }` plain data, and `resolve(spec, versions: &[PolicyVersion], account, at) -> Resolved { value, source: Default|Instance|Account, version_id: Option<_> }` (pure, POL-01 Resolution, POL-08 out-of-bounds fallback returning a flag the caller logs).
  - `Snapshot { entries: Vec<(key, value, version_id)> }` and `resolve_with_snapshot(&Snapshot, key)` (POL-06).
- `kinevo_kernel::policy::registry`: `pub const` `PolicySpec`s and typed `PolicyKey` constants for the M1 keys above, grouped per owning module (`identity`, `admin`, `http`, `platform`), and `pub fn all_specs() -> &'static [&'static PolicySpec]` used by tests and later by the descriptor check.

**Out:**
- Storage (`policy_versions`) and the `PUT /policies` endpoint: T-033.
- The `PolicyReader` port: T-008.
- Policy keys of later modules (scheduler, ai, …): added by their milestones.
- The `check-no-literals` script: T-042.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-kernel/Cargo.toml` | modify | no new dependency expected (`serde_json` already via T-005 dev; promote to normal dependency, record in stack map if missing) |
| `crates/kinevo-kernel/src/lib.rs` | modify | `pub mod policy;` |
| `crates/kinevo-kernel/src/policy/mod.rs` | create | types, validation, effective rules |
| `crates/kinevo-kernel/src/policy/resolve.rs` | create | `resolve`, `Snapshot` |
| `crates/kinevo-kernel/src/policy/registry.rs` | create | the M1 specs and keys |

## Interfaces

```rust
pub struct PolicyKey<T> { pub name: &'static str, _t: PhantomData<T> }   // const-constructible
pub struct PolicySpec { pub key: &'static str, pub ty: PolicyType, pub default: PolicyValue,
                        pub scopes: &'static [Scope], pub effective_dating: EffectiveDating, pub snapshot: bool }
pub enum PolicyValue { Int(i64), Bool(bool), Str(&'static str /* enum literal */ ), Text(String), Seconds(i64), LocalTime(u16 /* minutes since 00:00 */), Bp(u16) }
pub fn validate_value(spec: &PolicySpec, v: &serde_json::Value) -> Result<PolicyValue, PolicyError>;
pub fn check_effective(spec: &PolicySpec, now: Instant, requested: Option<Instant>, latest: Option<Instant>) -> Result<Instant, PolicyError>;
pub fn resolve(spec: &PolicySpec, versions: &[PolicyVersion], account: Option<AccountId>, at: Instant) -> Resolved;
pub fn resolve_with_snapshot<'a>(s: &'a Snapshot, key: &str) -> Option<&'a PolicyValue>;
// typed accessors, one per type, e.g.:
impl PolicyKey<i64> { pub fn get(&self, r: &dyn Fn(&str) -> Resolved) -> i64; } // exact shape is the implementer's, but never `&str` keys in callers
```

`PolicyError` → `ErrorCode`: `policy_value_invalid`, `policy_scope_invalid`, `policy_effective_in_past`, `policy_effective_order` (codes exist in T-003).

## Business rules

1. Resolution picks, per scope, the version with the greatest `effective_from ≤ at`; ties break by the larger `id` (UUIDv7 order).
2. Account scope overrides instance scope only when the key allows account scope.
3. A stored value that fails `validate_value` during resolution is ignored and the next candidate (then the default) is used; the result carries `out_of_bounds = true` (POL-08).
4. For `future_only` keys, `requested < now` is rejected; for `immediate` keys, `requested < latest` is rejected (POL-04). `requested = None` means `now`.
5. `bp` is an integer 0–10000, `local_time` is minutes since midnight 0–1439; no floats anywhere.

## Security

- `PolicySpec.secret` does not exist: a policy can never be a secret (POL-01). A test lists the key names and fails if any contains `secret`, `password` (other than `auth.password.min_length`), `token` (other than TTL keys) or `key` as a trailing segment.
- Errors carry bounds and enum literals only, never the submitted value (ERR-03).

## Implementation notes

- Keep the registry data-only; the spec tables are the source. Copy defaults and bounds exactly. A mistake here is a spec violation: the registry test in AC-6 parses `spec/04-policies.md` §3.3/§3.5 and compares key, default and bounds for every M1 key.
- `auth.registration.mode` is an enum `open|closed|invite` with default `closed`.
- Durations are given in seconds in the spec; `identity.purge.delay` allows 0–30 d (0–2592000).
- Do not add a `Date`/`Zoned` dependency: `LocalTime` is minutes since midnight.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** instance value 10 (from t0) and account value 20 (from t1) **When** resolved at t0 and at t1 for a key with both scopes **Then** the results are 10 (instance) and 20 (account) | `policy::resolve::tests::pol_resolution_account_over_instance` |
| AC-2 | **Given** a key with instance scope only **When** an account-scope version exists **Then** it is ignored | `policy::resolve::tests::scope_not_allowed_is_ignored` |
| AC-3 | **Given** a stored value above the current max **When** resolved **Then** the compiled default is returned with `out_of_bounds = true` | `policy::resolve::tests::pol_08_out_of_bounds_falls_back` |
| AC-4 | **Given** a `future_only` key and `requested` in the past **When** checked **Then** `policy_effective_in_past`; for an `immediate` key with `requested` earlier than the latest version, `policy_effective_order` | `policy::tests::pol_04_effective_rules` |
| AC-5 | **Given** each type **When** `validate_value` gets an out-of-bounds, wrong-type or unknown-enum value **Then** `policy_value_invalid` with `details.bounds` | `policy::tests::pol_03_validate_each_type` |
| AC-6 | **Given** the tables in `spec/04-policies.md` **When** compared with the registry **Then** every M1 key matches in type, bounds, default and scopes, and there are no duplicate keys | `policy_registry::pol_01_registry_matches_spec` |
| AC-7 | **Given** a snapshot with key K = 7 **When** a newer version with 9 exists **Then** `resolve_with_snapshot` still returns 7 | `policy::resolve::tests::pol_06_snapshot_replay_is_stable` |

## Tests

```
cargo nextest run -p kinevo-kernel
just check
```

(`policy_registry` is an integration test in `crates/kinevo-kernel/tests/policy_registry.rs`; add that file to *Files* when creating it.)

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed (plus the integration test file above); diff within budget.
- [ ] Report below is filled in.

## Report

### Summary

T-006 is blocked because its Security rule forbids `password` in every key except `auth.password.min_length`, while its required M1 key list includes `auth.password_reset.ttl`. The local implementation candidate is preserved in `stash@{0}`; no implementation code is committed or pushed.

### Acceptance results

| Criterion | Named test | Result |
|---|---|---|
| AC-1 | `policy::resolve::tests::pol_resolution_account_over_instance` | pass in local candidate; not committed |
| AC-2 | `policy::resolve::tests::scope_not_allowed_is_ignored` | pass in local candidate; not committed |
| AC-3 | `policy::resolve::tests::pol_08_out_of_bounds_falls_back` | pass in local candidate; not committed |
| AC-4 | `policy::tests::pol_04_effective_rules` | pass in local candidate; not committed |
| AC-5 | `policy::tests::pol_03_validate_each_type` | pass in local candidate; not committed |
| AC-6 | `policy_registry::pol_01_registry_matches_spec` | pass in local candidate; not committed |
| AC-7 | `policy::resolve::tests::pol_06_snapshot_replay_is_stable` | pass in local candidate; not committed |
| Security | `policy_registry::policy_key_names_exclude_sensitive_segments` | fail: required key `auth.password_reset.ttl` violates the task's key-name rule |

The seven acceptance tests passed before the required Security check was added. The candidate and failing test are held together in `stash@{0}`.

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `export PATH="$HOME/.cargo/bin:$PATH"; scripts/preflight.sh` | 0 | No hard blockers; Docker and PostgreSQL image available. |
| `cargo fmt --all` | 0 | Formatted the workspace. |
| `cargo nextest run -p kinevo-kernel` | 0 | 36 passed, 0 skipped on the local candidate before the Security check. |
| `just check` | 0 | 39 passed, 0 skipped on the local candidate before the Security check; all gates completed. `cargo-deny` reported six existing unmatched license-allowance warnings; clippy reported the existing `uuid::Uuid::new_v4` configuration warning. |
| `cargo nextest run -p kinevo-kernel --test policy_registry policy_key_names_exclude_sensitive_segments` | 100 | Fails on `auth.password_reset.ttl`, which the task both requires and forbids. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated `tasks/index.md` after setting this task to `blocked`. |
| `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks. |
| `python3 scripts/spec_tool.py check` | 0 | 0 findings in 60 spec files. |

### Files changed

| Path | Lines added | Lines removed |
|---|---:|---:|
| `tasks/T-006-kernel-policy-types.md` | 31 | 15 |
| `tasks/index.md` | 2 | 2 |

Implementation candidate: 1,348 additions and 1 deletion in the local stash; no code changes are on the branch.

### Deviations

The local implementation candidate is 1,349 changed lines, 929 above `budget_lines: 420`; D-55 makes the budget guidance, not a blocker. Its registry uses `Immediate` and `snapshot: false` because the M1 policy table omits those fields. Its `billing_plans_v1` validator checks the required structure and scalar constraints, including the three-uppercase-letter currency shape, but does not verify ISO-4217 registry membership. None of this is the blocking reason.

### Spec issues noticed

The task's Security rule conflicts with its required M1 key list: `auth.password_reset.ttl` contains `password` but is not the sole permitted exception. The Security test cannot pass while both instructions remain unchanged.

### Environment-limited

None.

### Not done / not verified

No implementation code was committed or pushed. The blocked implementation candidate remains in local `stash@{0}` until Q-2 is resolved; the issue comment and milestone PR updates are pending this block commit.

## Questions

- Q-1 (resolved by implementer, 2026-10-27): `spec/04-policies.md` §1 includes schema-backed `json` policies. This task also declares `PolicyType::Json { schema }` and AC-5 requires validation for every type, but the specified `PolicyValue` enum has no JSON variant and no schema registry/validator interface is defined. A `Text` value would not be a typed JSON counterpart.
  Options: (a) add a JSON value variant and define the schema registry/validation path for T-006; (b) defer JSON policy support and remove it from this task's type/acceptance scope, then add it with the first JSON policy key.
  Recommendation: (a), because the task explicitly includes JSON in its policy type model and the spec requires schema validation.
  Resolved (implementer, 2026-10-27): add `PolicyValue::Json(serde_json::Value)` and validate the registered `billing_plans_v1` payload using the field contract in `spec/standards/contracts.md` §1 and §7. The M1 key registry remains limited to M1 keys; no owner plan, price, or currency values are added.
- Q-2 (implementer, 2026-10-27): The Security rule requires a test to reject every key containing `password` except `auth.password.min_length`, but the required M1 key list also includes `auth.password_reset.ttl`. The test `policy_registry::policy_key_names_exclude_sensitive_segments` fails on that required key.
  Options: (a) add `auth.password_reset.ttl` as an explicit exception in the Security rule; (b) remove or rename the M1 policy key and update the spec. Recommendation: (a), because the required key is a TTL policy and does not contain a password value.
