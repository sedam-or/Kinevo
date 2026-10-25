# 03 — Module system ("drag and drop", D-01)

Depends on: `02-architecture.md`. Requirement prefix: `MOD`.

**What it is.** A fixed set of first-party modules compiled into the binary. An admin switches
toggleable modules on or off per instance (setup wizard step 2, later Admin → Modules). It is **not**
a plugin API (PRD-X2).

## 1. Module catalogue

| Key | Name (message key) | Kind | Depends on | Default (template `personal`) | Spec |
|---|---|---|---|---|---|
| `identity` | `module.identity.name` | core | — | on | `modules/identity.md` |
| `admin` | `module.admin.name` | core | `identity` | on | `modules/admin.md` |
| `workspaces` | `module.workspaces.name` | core | `identity` | on | `modules/workspaces.md` |
| `goals` | `module.goals.name` | core | `workspaces` | on | `modules/goals.md` |
| `tasks` | `module.tasks.name` | core | `goals` | on | `modules/tasks.md` |
| `landscape` | `module.landscape.name` | core | `identity` | on | `modules/landscape.md` |
| `scheduler` | `module.scheduler.name` | core | `tasks`, `landscape` | on | `modules/scheduler.md` |
| `execution` | `module.execution.name` | core | `tasks`, `scheduler` | on | `modules/execution.md` |
| `progress` | `module.progress.name` | core | `tasks`, `goals` | on | `modules/progress.md` |
| `sync` | `module.sync.name` | toggleable | `tasks` | on | `modules/sync.md` |
| `ai` | `module.ai.name` | toggleable | `goals`, `tasks` | **off** | `modules/ai.md` |
| `knowledge` | `module.knowledge.name` | toggleable | `workspaces` | on | `modules/knowledge.md` |
| `canvas` | `module.canvas.name` | toggleable | `workspaces` | off | `modules/canvas.md` |
| `billing` | `module.billing.name` | toggleable | `identity` | **off** | `modules/billing.md` |

Soft links (e.g. notes linking to goals) are **optional dependencies**: the link feature appears only when both modules are enabled; each module works alone.
`knowledge` and `canvas` link to goals/tasks through `kinevo-ports::LinkTargetPort`; when `goals`/`tasks` are core they are always present, so no optional-dependency logic is needed for them. `canvas` → `knowledge` links are optional.

## 2. Descriptor (conceptual — shape, not code)

Every module provides exactly one descriptor with these fields:

| Field | Meaning | Rule |
|---|---|---|
| `key` | Stable lowercase id, `[a-z][a-z0-9_]{1,23}` | Never renamed after release |
| `kind` | `core` or `toggleable` | Core modules cannot be disabled (`ERR module_core_locked`) |
| `requires` | Module keys that must be enabled | Acyclic (validated at startup, MOD-03) |
| `permissions` | Permission keys it defines (`<module>.<resource>.<action>`) | Listed in `standards/rbac.md` |
| `policies` | Policy keys it owns with type, default, bounds, scope | Listed in `04-policies.md` |
| `migrations` | Ordered SQL files | Always applied (ARCH-11) |
| `routes` | Axum router + OpenAPI fragment | Mounted only when enabled |
| `jobs` / `job_schedules` | Handlers and recurring schedules | Registered only when enabled |
| `subscriptions` | Event kinds consumed | Registered only when enabled |
| `nav` | Navigation items (key, label key, route, order, required permission) | Served by `GET /api/v1/modules` |
| `settings_panels` | Admin/user settings sections | Served likewise |
| `message_namespaces` | i18n namespaces | Must exist in `en` and `id` catalogues |
| `provisioners` | `AccountProvisioner` implementations that create the module's per-account rows | Run inside the account-creation transaction (MOD-10) |
| `exporters` | `AccountExporter` implementations that contribute the module's sections to `export_v1` | Called by the export job (MOD-11) |

## 3. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| MOD-01 | `GET /api/v1/modules` returns, for the caller, each module's key, kind, enabled flag, nav items, and settings panels the caller may use. The web app builds navigation **only** from this response. | Given `canvas` disabled, the response omits Canvas nav; the SPA shows no Canvas entry (no hard-coded nav list). |
| MOD-02 | `PUT /api/v1/admin/modules/{key}` with `{ "enabled": bool, "version": n }` is allowed only for `instance_admin`, and only for toggleable modules. | Core module → 409 `module_core_locked`; non-admin → 403 `forbidden`. |
| MOD-03 | The dependency graph is validated at build time (unit test over descriptors) and at startup. Enabling a module requires all its `requires` to be enabled; disabling is rejected if an enabled module requires it. | Enabling `ai` while `goals` and `tasks` are on succeeds; disabling `tasks` is refused because it is core; an enable with a missing dependency fails with `module_dependency_unmet`; a disable that would orphan a dependent fails with `module_required_by`. |
| MOD-04 | Switching a module is transactional: the switch row, an `audit_log` entry and a `module_switched` outbox event are written together. Route availability changes **without restart**: handlers check a cached switch (TTL `modules.cache_ttl_seconds`, invalidated on switch) and return 404 `module_disabled` when off. | After disabling, the next request (≤ cache TTL, or immediately on the same node via invalidation) returns 404 `module_disabled`. |
| MOD-05 | Jobs of a disabled module are not claimed (their rows stay `queued`); on re-enable they run. Recurring schedules of a disabled module are not materialised. | Test: disable → tick → no new job rows for that kind. |
| MOD-06 | Data written while a module was enabled stays after disabling and is readable again after re-enabling. | Test: create note → disable `knowledge` → enable → note returned. |
| MOD-07 | Core flows (journey J1) must pass with every toggleable module disabled; each toggleable module's own journey must pass with all others disabled except its `requires`. | CI matrix job `module-matrix` (`12-testing.md` TST-07). |
| MOD-08 | A test fails if a descriptor references a permission key, policy key or message key that does not exist in the registries (`standards/rbac.md`, `04-policies.md`, `standards/ux-states.md`). | Unit test over registries. |
| MOD-09 | Clients (web, Android) must tolerate an unknown module key in `GET /api/v1/modules` by ignoring it (forward compatibility). | Contract test. |
| MOD-10 | Account creation (register, setup Finish, `KINEVO_SETUP_*` pre-creation) runs every compiled module's `AccountProvisioner` in module dependency order inside the same transaction as the `accounts` insert (ADR-0011), whether or not a toggleable module is enabled (so re-enabling it later finds its rows). In 1.0: `workspaces` creates the default workspace (WS-FR-01) and `scheduler` creates the `schedule_states` row. A provisioner failure rolls back the whole account creation. | Test: a failing fake provisioner leaves no `accounts` row; a new account has exactly one default workspace and one schedule state. |
| MOD-11 | The account export job (ID-FR-12) builds `data.json` from every compiled module's `AccountExporter` (each returns its named sections of `export_v1`, e.g. `workspaces[]`), whether or not a toggleable module is enabled. A section name may be claimed by one exporter only (startup check). | Test: two fake exporters → both sections present; duplicate section name → startup error. |

## 4. Enable/disable decision table

| Action | Module state | Dependency state | Result |
|---|---|---|---|
| enable | off | all `requires` on | 200, enabled, audit + event |
| enable | off | some `requires` off | 409 `module_dependency_unmet` with `details.missing=[keys]` |
| enable | on | — | 200 (idempotent; version unchanged) |
| disable | on | no enabled module requires it | 200 |
| disable | on | an enabled module requires it | 409 `module_required_by` with `details.dependents=[keys]` |
| disable | core | — | 409 `module_core_locked` |
| any | any | stale `version` | 409 `version_conflict` |

## 5. Why not a plugin API now (decision record)

A plugin API (WASM or dynamic loading) needs a stable ABI, sandboxing, a permission model for plugin-defined data, versioned migrations for third-party schemas and a review process. None of that has user demand evidence. The module descriptor above is deliberately the shape such an API would later expose, so promoting a first-party module to an external one is a mechanical step. Revisit trigger: ≥ 3 independent requests for the same extension point, filed as RFCs (`CONTRIBUTING.md`, Phase 5).
