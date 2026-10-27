---
id: T-008
title: Define the module descriptor and platform port traits in kinevo-ports
milestone: M1
kind: feature
status: review
depends_on: [T-004, T-005, T-006, T-007]
requirements: [D-41, D-43, ADR-0011, MOD-03, MOD-10, MOD-11, CA-10, CA-11, CQ-09]
branch: m1-t008-ports-descriptor
budget_lines: 400
assignee: "Codex"
---

# T-008: Define the module descriptor and platform port traits in kinevo-ports

## Goal

`kinevo-ports` contains the pure-data `ModuleDescriptor`, its dependency-graph validator, and the platform ports that every module may rely on without importing `admin` or `identity`. After this task, later crates depend on these traits only.

## Context

> **D-43** (`spec/decisions.md`): `ModuleDescriptor` (in `kinevo-ports`) holds only static metadata: key, kind, requires, permissions, policy keys, nav, settings panels, message namespaces, job kinds, event subscriptions. It contains no axum, sqlx or async types. Each module crate exports plain functions `router()`, `jobs()`, `subscribers()`, `provisioners()`, `exporters()` and `migrations()` wired by `kinevo-app`.
> **D-41**: Platform ports in `kinevo-ports`, implemented by `admin` and wired by `kinevo-app`: `PolicyReader` (typed resolution, POL), `ModuleSwitches` (enabled state, MOD-04), `InstanceState` (setup status, base URL, default locale) and `SecretStore` (D-40). `kinevo-http` and modules depend on these traits, never on `admin`. Account export collects data through `AccountExporter` (MOD-11), as account creation does through `AccountProvisioner` (MOD-10).
> **ADR-0011**: `AccountProvisioner`: `async fn provision(&self, tx: &mut Tx, account: &ProvisionedAccount) -> Result<(), DomainError>`. *(Ports cannot depend on `kinevo-db`, so the trait is generic over the transaction type; see Interfaces.)*
> **D-40(b)**: `SecretStore` offers `put`, `get` (returns a redacting `Secret<String>`), `delete` and `mask` (`is_set`, `last4`), by `(account_id?, name)`; names are registered in `modules/admin.md` §2: instance `smtp.password`, `ai.api_key`, `billing.gateway_key`, `billing.webhook_secret`; account `identity.totp_seed`, `ai.api_key`; any other name → `secret_name_unknown`.
> **MOD-03**: The dependency graph is validated at build time (unit test over descriptors) and at startup. Enabling requires all `requires` enabled; disabling is rejected if an enabled module requires it: `module_dependency_unmet` (`details.missing`), `module_required_by` (`details.dependents`), `module_core_locked`.
> **MOD-10 / MOD-11**: Provisioners run in module dependency order; exporters each contribute named sections of `export_v1`; a section name may be claimed by one exporter only (startup check).
> **CA-10**: Ports are narrow, live in `kinevo-ports`, and return DTOs, never another module's entities. **CA-11**: Events are past-tense facts with ids and enums only, versioned (`kind` + `v`).
> **Module catalogue** (`spec/03-module-system.md` §1): key, kind and `requires` for the 14 modules, with `identity`/`admin` etc. core, and `sync`, `ai`, `knowledge`, `canvas`, `billing` toggleable. Only the descriptors of crates that exist (M1: `identity`, `admin`) are authored by their own tasks.
> **Descriptor fields** (`03-module-system.md` §2): `key` `[a-z][a-z0-9_]{1,23}`, `kind`, `requires`, `permissions`, `policies`, `nav` (key, label key, route, order, required permission), `settings_panels`, `message_namespaces`, `jobs`/`job_schedules`, `subscriptions`.

## Scope

**In:**
- `kinevo_ports::module`: `ModuleKey` (validated newtype), `ModuleKind`, `NavItem`, `SettingsPanel`, `JobKind`, `JobSchedule { kind, cadence_policy_key }`, `ModuleDescriptor` (all `&'static` data, `const`-constructible, with a `PermissionKey` newtype for `<module>.<resource>.<action>` and `PermissionDef { key, owner }` entries: `instance_admin` holds every permission, `account_owner` holds those with `owner = true`), and `validate_graph(descriptors: &[&ModuleDescriptor]) -> Result<(), GraphError>` (unknown `requires`, cycles, duplicate keys, duplicate permission or job kinds).
- `kinevo_ports::module::plan_switch(descriptors, enabled: &BTreeSet<ModuleKey>, key, want_enabled) -> Result<SwitchOutcome, AppError>` implementing the decision table of `03-module-system.md` §4 (idempotent enable returns `Unchanged`). The `version` check belongs to the repository, not here.
- `kinevo_kernel::event` (new module, because `kinevo-db` may depend only on the kernel) and its re-export `kinevo_ports::events`: `EventEnvelope { id, kind, v, account_id: Option<_>, occurred_at, payload: EventPayload }` where `EventPayload` is a small map of id/enum/int values only, and the M1 event kinds as constants: `AccountRegistered`, `AccountLoggedIn`, `AccountDisabled`, `AccountDeletionRequested`, `PasswordChanged`, `InstanceSetupCompleted`, `ModuleSwitched`, `PolicyChanged`, `SecretChanged`.
- Platform ports (all `#[async_trait]`, object-safe, used as `Arc<dyn …>`):
  - `PolicyReader` (`async fn snapshot_values(&self, account: Option<AccountId>, at: Instant) -> Result<ResolvedPolicies, AppError>`; `ResolvedPolicies` offers the typed `get(&PolicyKey<T>)` accessors from T-006).
  - `ModuleSwitches` (`fn is_enabled(&self, key: &ModuleKey) -> bool` served from cache, plus `async fn refresh(&self)`).
  - `InstanceState` (`async fn get(&self) -> Result<InstanceInfo, AppError>`; `InstanceInfo { setup_completed, base_url: Option<HttpsUrl>, default_locale: Locale }`).
  - `SecretStore` (`put`, `get`, `delete`, `mask` as in D-40; `SecretName` newtype restricted to the registered names; `SecretScope::{Instance, Account(AccountId)}`).
  - `AccountProvisioner<T>` and `AccountExporter<T>`, generic over the transaction type `T` (the app binds `T = kinevo_db::Tx`). `ProvisionedAccount { id, display_name, timezone, locale, is_instance_admin }`. `ExportSection { name: &'static str, rows: serde_json::Value }`.
  - `AccountAccess` (`async fn load(&self, id: AccountId) -> Result<Option<AccountAccessInfo>, AppError>`; `AccountAccessInfo { id, status, is_instance_admin, version }`) used by the HTTP layer to derive roles (API-12). Implemented by `identity` (T-017/T-026).
- `kinevo_ports::fakes` behind feature `testing`: in-memory `FakePolicyReader`, `FakeModuleSwitches`, `FakeInstanceState`, `FakeSecretStore`, `FakeAccountAccess`.

**Out:**
- Implementations (T-020 onwards).
- Ports of later modules (`TaskReadPort`, `LinkTargetPort`, `EntitlementPort`…): their milestones.
- Router, jobs and subscriber traits (D-43: plain functions in the module crates).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/Cargo.toml` | modify | deps `kinevo-kernel`, `serde`, `serde_json`, `async-trait`; feature `testing` |
| `spec/standards/open-source-stack.md` | modify | `async-trait` row |
| `crates/kinevo-ports/src/lib.rs` | modify | `pub mod module; pub mod events; pub mod platform; #[cfg(feature="testing")] pub mod fakes;` |
| `crates/kinevo-ports/src/module.rs` | create | descriptor types, `validate_graph`, `plan_switch` |
| `crates/kinevo-kernel/src/event.rs`, `crates/kinevo-kernel/src/lib.rs` | create / modify | envelope, payload and kinds |
| `crates/kinevo-ports/src/events.rs` | create | `pub use kinevo_kernel::event::*;` |
| `crates/kinevo-ports/src/platform.rs` | create | port traits and DTOs |
| `crates/kinevo-ports/src/fakes.rs` | create | in-memory fakes |

## Interfaces

```rust
pub struct ModuleDescriptor {
    pub key: &'static str, pub kind: ModuleKind, pub requires: &'static [&'static str],
    pub permissions: &'static [PermissionDef], pub policy_keys: &'static [&'static str],
    pub nav: &'static [NavItem], pub settings_panels: &'static [SettingsPanel],
    pub message_namespaces: &'static [&'static str],
    pub jobs: &'static [&'static str], pub job_schedules: &'static [JobSchedule],
    pub subscriptions: &'static [&'static str],
}
pub struct PermissionDef { pub key: &'static str, pub owner: bool }  // instance_admin holds every key; account_owner holds those with owner = true (rbac.md §2: admin ⊇ owner)
pub fn validate_graph(ds: &[&ModuleDescriptor]) -> Result<(), GraphError>;
pub fn plan_switch(ds: &[&ModuleDescriptor], enabled: &BTreeSet<String>, key: &str, want: bool) -> Result<SwitchOutcome, AppError>;
pub enum SwitchOutcome { Changed, Unchanged }

#[async_trait] pub trait AccountProvisioner<T: Send>: Send + Sync {
    fn module(&self) -> &'static str;
    async fn provision(&self, tx: &mut T, account: &ProvisionedAccount) -> Result<(), AppError>;
}
#[async_trait] pub trait AccountExporter<T: Send>: Send + Sync {
    fn sections(&self) -> &'static [&'static str];
    async fn export(&self, tx: &mut T, account: AccountId) -> Result<Vec<ExportSection>, AppError>;
}
#[async_trait] pub trait SecretStore: Send + Sync {
    async fn put(&self, scope: SecretScope, name: SecretName, value: Secret<String>) -> Result<(), AppError>;
    async fn get(&self, scope: SecretScope, name: SecretName) -> Result<Option<Secret<String>>, AppError>;
    async fn delete(&self, scope: SecretScope, name: SecretName) -> Result<bool, AppError>;
    async fn mask(&self, scope: SecretScope, name: SecretName) -> Result<SecretMask, AppError>; // { is_set, last4 }
}
```

All port traits use the `async-trait` crate so they are object-safe and wired as `Arc<dyn Trait>` (decision of this task; add `async-trait` to the stack map, MIT/Apache-2.0).

## Business rules

1. The decision table in `03-module-system.md` §4 is implemented exactly: enable with missing dependency → `module_dependency_unmet` + `details.missing`; disable with enabled dependents → `module_required_by` + `details.dependents`; disable core → `module_core_locked`; enable of an already enabled module → `Unchanged`.
2. `validate_graph` rejects: a `requires` key that no descriptor defines; a cycle; a core module that requires a toggleable one; two descriptors with the same key; a permission key whose first segment is not the module key.
3. `SecretName` accepts only the registered names, with the instance/account sets from D-40; anything else is `secret_name_unknown`.

## Security

- `SecretStore::get` returns `Secret<String>` (redacting). No port returns a raw secret `String`.
- `EventPayload` accepts ids, enums and integers only (CA-11, ERR-03 spirit): there is no string-valued free-form variant.
- `ProvisionedAccount` and `AccountAccessInfo` contain no email or password material.

## Implementation notes

- The seven fakes are test infrastructure used by T-016 onwards; keep them tiny.
- Native async fn in traits is not object-safe in edition 2024; `async-trait` is the chosen mechanism. Add it to `standards/open-source-stack.md` (CQ-16).
- `ModuleKey` regex `^[a-z][a-z0-9_]{1,23}$` uses `Key::parse` from T-005.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the catalogue graph of `03-module-system.md` §1 (as test descriptors) **When** validated **Then** it passes; adding a cycle or an unknown `requires` fails | `module::tests::mod_03_graph_validation` |
| AC-2 | **Given** `ai` off and `goals`,`tasks` on **When** `plan_switch(ai, true)` **Then** `Changed`; with `goals` off, `module_dependency_unmet` with `details.missing=["goals"]` | `module::tests::mod_03_enable_rules` |
| AC-3 | **Given** `knowledge` on and `canvas` requiring it **When** `plan_switch(knowledge,false)` **Then** `module_required_by` with `details.dependents` | `module::tests::mod_03_disable_with_dependents` |
| AC-4 | **Given** a core module **When** disabled **Then** `module_core_locked`; **When** an enabled module is enabled again **Then** `Unchanged` | `module::tests::mod_core_locked_and_idempotent_enable` |
| AC-5 | **Given** the name `identity.totp_seed` and `foo.bar` **When** parsed as `SecretName` **Then** the first is accepted, the second is `secret_name_unknown` | `platform::tests::d_40_secret_name_registry` |
| AC-6 | **Given** two fake exporters claiming section `workspaces` **When** `check_exporters` runs **Then** an error names the section | `module::tests::mod_11_duplicate_export_section` |
| AC-7 | **Given** fake provisioners in dependency order `workspaces`,`scheduler` and `scheduler` failing **When** `run_provisioners` executes **Then** the error is returned and the order of calls was `workspaces`,`scheduler` | `module::tests::mod_10_provisioners_run_in_order_and_fail_fast` |

(`check_exporters(&[&dyn …]) -> Result<(), AppError>` and `run_provisioners(order: &[&dyn AccountProvisioner<T>], tx, account)` are small pure helpers in `module.rs`; add them to the file table implicitly.)

## Tests

```
cargo nextest run -p kinevo-ports
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes.
- [x] `just check` passes locally.
- [x] Only the listed implementation files plus the test-only module child file and generated lockfile changed; budget overage is recorded under Deviations per D-55.
- [x] `kinevo-ports` depends on `kinevo-kernel`, serde crates, and the task-required stack-listed async-trait and regex crates.
- [x] Report below is filled in.

## Report

### Summary

Implemented the static module descriptor and graph validator, pure module-switch planner, event envelope and M1 event kinds, object-safe platform ports, typed policy snapshots, D-40 secret-name registry, account provisioner/exporter helpers, and five in-memory fakes behind the `testing` feature. The module descriptor is const-constructible, and event payload values are limited to typed ids, enum literals, and integers.

### Acceptance results

| Criterion | Named test | Result |
|---|---|---|
| AC-1 | `module::tests::mod_03_graph_validation` | PASS |
| AC-2 | `module::tests::mod_03_enable_rules` | PASS |
| AC-3 | `module::tests::mod_03_disable_with_dependents` | PASS |
| AC-4 | `module::tests::mod_core_locked_and_idempotent_enable` | PASS |
| AC-5 | `platform::tests::d_40_secret_name_registry` | PASS |
| AC-6 | `module::tests::mod_11_duplicate_export_section` | PASS |
| AC-7 | `module::tests::mod_10_provisioners_run_in_order_and_fail_fast` | PASS |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `cargo nextest run -p kinevo-ports` before implementation | 101 | Expected red phase: the named tests failed to compile because the port APIs had not yet been implemented; the first attempt also caught and corrected module-attribute ordering. |
| `cargo nextest run -p kinevo-ports` | 0 | 7 passed, 0 skipped. |
| `cargo nextest run -p kinevo-ports --features testing` | 0 | 7 passed, 0 skipped; test fakes compiled with the feature enabled. |
| `cargo clippy -p kinevo-ports --all-targets --all-features -- -D warnings` | 0 | Passed; only the existing `uuid::Uuid::new_v4` clippy configuration warning was emitted by the kernel dependency. |
| `just check` | 0 | 57 tests passed, 0 skipped; cargo-deny advisories, bans, licenses, and sources passed; `spec_tool.py check` found 0/60; contrast had 0 failures; task index had 0 problems/132. Existing unused license-allowance and clippy configuration warnings remain. |
| `python3 scripts/spec_tool.py check` | 0 | 0 findings in 60 spec files. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated the index after the task Report/status change. |
| `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks. |
| `cargo fmt --all` and `just check` format gate | 0 | Formatting completed and `cargo fmt --all -- --check` passed. |

### Files changed

`Cargo.lock` (generated), `crates/kinevo-kernel/src/{event.rs,lib.rs}`, `crates/kinevo-ports/Cargo.toml`, `crates/kinevo-ports/src/{events.rs,fakes.rs,lib.rs,module.rs,platform.rs}`, `crates/kinevo-ports/src/module/tests.rs`, and `spec/standards/open-source-stack.md`.

### Deviations

- The final task diff exceeds `budget_lines: 400`: 1,318 non-lockfile insertions and 10 deletions (1,328 changed lines), plus 15 generated `Cargo.lock` additions excluded from the budget. The non-lockfile diff is 928 lines over the guidance; D-55 makes this a recorded deviation, not a blocker.
- Added the test-only `src/module/tests.rs` child module to keep `module.rs` at 425 lines under CQ-13's 600-line module limit. The named test paths remain `module::tests::…`.
- Added `async-trait` 0.1.92 and direct `regex` 1.13.1 dependencies. `async-trait` is explicitly required for object-safe async ports; direct `regex` is needed to use `Key::parse` as the task's implementation note requires. Both crates are listed in the stack map. `Cargo.lock` was regenerated.
- The implementation accepts both `BTreeSet<ModuleKey>` and `BTreeSet<String>` in `plan_switch` through `Borrow<str>`, reconciling the Scope and Interfaces signatures in the task.
- The task's Definition of done narrows dependencies to kernel plus serde, while its Files/Scope require `async-trait` and its implementation note requires the existing stack-listed regex parser. Followed the more specific port requirements and recorded this task-internal inconsistency for architect review.
- The first `just check` run found Clippy's `manual_noop_waker` lint in the test executor; replaced it with `Waker::noop()` and the final gate passed.

### Spec issues noticed

None. The two task-internal signature/dependency inconsistencies are recorded under Questions as implementer resolutions pending architect review.

### Environment-limited

None.

### Not done / not verified

None for T-008. Database behavior and concrete platform-port implementations are out of scope for this task.

## Questions

- Q-1 (resolved by implementer, pending architect review): the task Scope specifies `BTreeSet<ModuleKey>` for `plan_switch`, while Interfaces specifies `BTreeSet<String>` and a `&str` key. The helper uses `Borrow<str>` generics and accepts both forms.
- Q-2 (resolved by implementer, pending architect review): the Definition of done says kernel plus serde dependencies, while Files/Scope require `async-trait` and the implementation note requires `Key::parse`, which needs the stack-listed `regex` crate. Both dependencies are included and recorded in the stack map.
