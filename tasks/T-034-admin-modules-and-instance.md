---
id: T-034
title: Implement ModuleSwitches, GET /modules, GET /instance and PUT /admin/modules/{key}
milestone: M1
kind: feature
status: in_progress
depends_on: [T-031, T-008, T-012, T-014, T-017, T-019]
requirements: [MOD-01, MOD-02, MOD-03, MOD-04, MOD-06, MOD-09, ADM-FR-01, ADM-FR-03, ADM-FR-05, D-41]
branch: m1-t034-admin-modules-and-instance
budget_lines: 380
assignee: "OpenAI Codex"
---

# T-034: Implement ModuleSwitches, GET /modules, GET /instance and PUT /admin/modules/{key}

## Goal

Modules can be switched at runtime through a cached `ModuleSwitches` port, the client learns navigation from `GET /modules`, and the instance summary is readable.

## Context

> **MOD-01** (`spec/03-module-system.md`): `GET /modules` returns, for the caller, each module's key, kind, enabled flag, nav items and settings panels the caller may use. The web app builds navigation only from it.
> **MOD-02**: `PUT /admin/modules/{key}` `{ enabled, version }` is allowed only for `instance_admin`, and only for toggleable modules.
> **MOD-03 / decision table (§4)**: enable with missing dependency → 409 `module_dependency_unmet` (`details.missing`); disable with enabled dependents → 409 `module_required_by` (`details.dependents`); core → 409 `module_core_locked`; stale `version` → 409 `version_conflict`; enabling an enabled module is idempotent (200, version unchanged).
> **MOD-04**: A switch writes the row, an `audit_log` entry and a `module_switched` outbox event together. Handlers check a cached switch (TTL `modules.cache_ttl_seconds`, invalidated on switch) and return 404 `module_disabled` when off.
> **MOD-06**: Data stays when a module is disabled.
> **MOD-09**: Clients ignore unknown module keys.
> **GET /instance** (`admin.md` §2): `{ version, setup_completed, base_url, default_locale, template_id, registration_mode }` for `admin.instance.read`.
> **Absent row rule** (T-031): a module without an `instance_modules` row is on if it is core, otherwise off, except while the setup template sets rows (T-036/T-037).

## Scope

**In:**
- `ModuleService` using `plan_switch` (T-008) with a row-level `version` check, audit and outbox in one admin `Tx`.
- `ModuleSwitches` port implementation with a `moka` cache (TTL from the policy, invalidated on switch and on the event).
- The module gate middleware input: `is_enabled(key)` used by T-017's module gate (wire the real port in T-039).
- `GET /modules` assembling nav and settings panels from descriptors filtered by enabled state and the caller's permissions.
- `GET /instance`.

**Out:**
- Job claiming rules for disabled modules (MOD-05) are tested in T-013's claim filter; this task provides the enabled-set accessor only.
- Templates: T-035.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-admin/src/app/modules.rs` | create | `ModuleService` |
| `crates/kinevo-mod-admin/src/infra/module_repo.rs` | create | queries |
| `crates/kinevo-mod-admin/src/infra/module_switches.rs` | create | `ModuleSwitches` impl + cache |
| `crates/kinevo-mod-admin/src/http/modules.rs` | create | `GET /modules`, `PUT /admin/modules/{key}` |
| `crates/kinevo-mod-admin/src/http/instance.rs` | create | `GET /instance` |
| `crates/kinevo-mod-admin/tests/modules.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
#[async_trait] impl ModuleSwitches for CachedModuleSwitches { /* enabled_set(), is_enabled(key) per T-008 */ }
pub struct ModuleService { /* db, descriptors: Vec<&'static ModuleDescriptor>, cache, audit, outbox */ }
impl ModuleService { pub async fn set(&self, ctx: &AdminProof, key: &str, enabled: bool, version: i64) -> Result<ModuleView, AppError>; }
// GET /modules → 200 { items:[{ key, kind, enabled, nav:[{key,label_key,route,order}], settings_panels:[…] }] }
```

## Business rules

1. Core modules are always `enabled: true` and cannot be switched.
2. Nav and settings panels appear only when the module is enabled and the caller holds the item's permission.
3. A disable keeps all data (MOD-06); only routes, jobs and subscriptions stop.
4. Switch requests are idempotent: the same state returns 200 without a new version, audit row or event.

## Security

- `PUT /admin/modules/{key}` needs `admin.modules.manage` and returns 403 for an owner (SEC-AC-09).
- `GET /modules` never lists nav items the caller cannot use.
- The module key is validated against the compiled catalogue; unknown keys give `not_found`.

## Implementation notes

- Row `version` is the optimistic-lock counter; the client sends the version from its last `GET /modules` (add `version` to the item).
- The cache stores the whole enabled set; one query fills it.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** `canvas` disabled **When** `GET /modules` **Then** it carries `enabled=false` and no nav for it | `modules::mod_01_nav_only_for_enabled` |
| AC-2 | **Given** an owner **When** `PUT /admin/modules/ai` **Then** 403 `forbidden` | `modules::sec_ac_09_owner_cannot_switch` |
| AC-3 | **Given** `ai` with `goals` and `tasks` on **When** enabled **Then** 200, audit row `module.switched`, outbox row | `modules::mod_04_switch_audits_and_emits` |
| AC-4 | **Given** a missing dependency **When** enabling **Then** 409 `module_dependency_unmet` with `details.missing` | `modules::mod_03_dependency_unmet` |
| AC-5 | **Given** an enabled dependent **When** disabling **Then** 409 `module_required_by`; core gives `module_core_locked` | `modules::mod_03_required_by_and_core_locked` |
| AC-6 | **Given** a stale version **When** switching **Then** 409 `version_conflict` | `modules::switch_version_conflict` |
| AC-7 | **Given** the cache **When** a module is switched **Then** `is_enabled` reflects it immediately on the same node | `modules::mod_04_cache_invalidated_on_switch` |
| AC-8 | **Given** an authenticated admin **When** `GET /instance` **Then** the documented fields are present and an unauthenticated call gives 401 | `modules::instance_summary_fields` |

## Tests

```
cargo nextest run -p kinevo-mod-admin --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
