---
id: T-054
title: Build the admin screens for modules, policies and secrets
milestone: M1
kind: feature
status: todo
depends_on: [T-050, T-033, T-034, T-032, T-045]
requirements: [MOD-02, MOD-03, POL-02, POL-03, POL-04, ADM-FR-06, SEC-AC-09, SEC-AC-11, UXS-02, UXS-04]
branch: m1-t054-web-admin-modules-policies-secrets
budget_lines: 400
assignee: ""
---

# T-054: Build the admin screens for modules, policies and secrets

## Goal

An instance admin can switch modules with dependency feedback, edit policies from generated forms with effective-dating, and set or remove instance secrets without ever seeing a value.

## Context

> **Routes** (`spec/08-frontend.md` §3): `/admin/modules`, `/admin/policies`, `/admin/secrets` for `instance_admin` only (items come from `GET /modules`, T-050).
> **Modules** (`03-module-system.md` §4 decision table): switch with `{enabled, version}`; `module_core_locked` (core switches are disabled and labelled), `module_dependency_unmet` (`details.missing`), `module_required_by` (`details.dependents`), `version_conflict` (reload and show the current state). Module toggles use Switch with a visible "On"/"Off" label. UX keys: `admin.module.switched` (toast with Undo), `admin.module.dependency`, `admin.module.required_by`.
> **Policies** (`04-policies.md` POL-02..04): the form is generated from `GET /policies?scope=` metadata (type, bounds, label key, source, effective-from); `PUT /policies/{key}` `{value, effective_from?, scope}`; `policy_value_invalid` (`details.bounds`), `policy_scope_invalid`, `policy_effective_in_past`, `policy_effective_order`, `policy_unknown`. UX keys: `admin.policy.saved` (applies `{when}`), `admin.policy.invalid` (range). Types: integer, boolean, enum, seconds (shown as a duration field), local time, basis points (shown as a percentage with the bp value visible). No domain constants in the client: the bounds come from the server.
> **Secrets** (`modules/admin.md` §2): `GET /admin/secrets` → `[{name, is_set, last4}]`; `PUT /admin/secrets/{name} {value}`; `DELETE`. Value field is write-only; after saving show `admin.secret.set` (Saved. Ends in {last4}; actions Replace, Remove). The secret name list is from the response (the server registry, D-40).
> **SEC-AC-09**: an owner never sees these routes; direct URL access renders `state.forbidden`. **SEC-AC-11**: secret values never appear in DOM after submit, logs or query cache.
> **UXS-04**: remove-secret uses an alertdialog naming the secret.

## Scope

**In:**
- `/admin/modules` list with dependency hints, switch mutations with optimistic UI rolled back on error, Undo toast that issues the inverse switch.
- `/admin/policies` with scope tabs (Instance, My account), grouped by module, generated field components per policy type, effective-from picker (typed entry always possible) shown only for keys that allow it, version/`source` badges.
- `/admin/secrets` list and write-only dialog.

**Out:**
- Audit, jobs and accounts screens: T-055.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/admin/{ModulesPage,PoliciesPage,PolicyField,SecretsPage,SecretDialog}.tsx` | create | screens |
| `web/src/features/admin/hooks.ts` | create | queries, mutations |
| `web/src/features/admin/policyTypes.ts` | create | type → field mapping |
| `web/src/features/admin/*.test.tsx` | create | tests |
| `web/tests/e2e/admin.a11y.spec.ts` | create | axe pass |

## Interfaces

```ts
useModules(); useSwitchModule(): mutate({ key, enabled, version });
usePolicies(scope); useSetPolicy(): mutate({ key, scope, value, effective_from? });
useSecrets(); useSetSecret(): mutate({ name, value }); useDeleteSecret()
```

## Business rules

1. Switch requests carry the module row `version`; on `version_conflict` the list reloads and a message explains.
2. The policy form only offers `effective_from` for keys whose metadata allows it, and validates bounds client-side as a convenience (server authoritative).
3. Saving a policy shows when it applies (now or the chosen instant) using `useFormat`.
4. Secret values are cleared from state as soon as the request resolves.

## Security

- The secret input is `type=password` with `autocomplete=off`, cleared on dialog close, and excluded from query-cache persistence and error reports.
- No secret name outside the server response is offered.
- Routes check `instance_admin` via the module nav; the server remains the authority.

## Implementation notes

- Use the generated policy metadata only; do not hard-code any policy key in the web app (the `check-no-literals` script covers TS).
- Basis points display: show `25.50 %` with the stored bp value in the helper text, computed with integer arithmetic.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a core module **When** listed **Then** its switch is disabled with an explanation | `admin_ui::mod_02_core_switch_locked` |
| AC-2 | **Given** `module_dependency_unmet` **When** enabling **Then** the inline message names the missing module and the switch reverts | `admin_ui::mod_03_dependency_message_and_revert` |
| AC-3 | **Given** a switch **When** it succeeds **Then** the toast offers Undo that restores the previous state | `admin_ui::admin_module_switched_undo` |
| AC-4 | **Given** policy metadata of each type **When** rendered **Then** the matching field with bounds helper appears | `admin_ui::pol_02_form_generated_from_metadata` |
| AC-5 | **Given** an out-of-range value **When** saved **Then** the inline range message shows from `details.bounds` | `admin_ui::pol_03_bounds_message` |
| AC-6 | **Given** a saved secret **When** the list reloads **Then** only `is_set` and `last4` show and the DOM holds no value | `admin_ui::sec_ac_11_secret_value_never_in_dom` |
| AC-7 | **Given** removing a secret **When** the alertdialog opens **Then** Cancel is focused and the name is shown | `admin_ui::uxs_04_remove_secret_alertdialog` |
| AC-8 | **Given** an owner **When** opening `/admin/modules` directly **Then** `state.forbidden` shows (SEC-AC-09) | `admin_ui::sec_ac_09_owner_gets_forbidden_state` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
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
