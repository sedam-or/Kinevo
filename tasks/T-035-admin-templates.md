---
id: T-035
title: Add the embedded setup templates and the template schema validator
milestone: M1
kind: feature
status: in_progress
depends_on: [T-033, T-034]
requirements: [TPL-01, TPL-02, TPL-03, TPL-04, MOD-03, POL-03, D-10]
branch: m1-t035-admin-templates
budget_lines: 330
assignee: "OpenAI Codex"
---

# T-035: Add the embedded setup templates and the template schema validator

## Goal

Four shipped templates are embedded in the binary, validated against a strict schema, and applying one is idempotent.

## Context

> **Template** (`spec/05-templates-and-setup.md` §1): a data file selecting modules, policy presets, terminology overrides and optional sample data. Fields: `id` (`[a-z][a-z0-9-]{1,31}`), `name_key`, `description_key` (message keys, en + id required), `modules` (map key → bool, dependency graph valid, core absent or `true`), `policies` (key → value, each validated by POL-03; instance-scope keys apply at setup, account-scope keys become the first account's defaults), `terms` (only keys of the terminology registry), `sample` (`student`, `professional`, `empty`).
> **Terminology registry**: `term.goal`, `term.milestone`, `term.task`, `term.workspace`, `term.hard_landscape`, `term.focus_session`.
> **Shipped templates** (1.0): `personal` (default; core + `sync`, `knowledge`; registration `closed`), `student` (`personal` + `canvas`; `term.hard_landscape` = "Class & fixed hours"; `scheduler.day_start` 07:00, `scheduler.day_end` 22:00), `professional` (`personal` + `ai` off until a provider is set; `scheduler.anchor.earliest_time` 08:00), `cloud` (core + `sync`, `knowledge`, `canvas`, `ai`, `billing` off until OWNER-01; registration `open`). Content is provisional (`[ASSUMED]`).
> **TPL-01** invalid file → `template_invalid` with `details.path`. **TPL-02** no code, fetch URLs or secrets. **TPL-03** terms change labels only. **TPL-04** re-applying yields no new policy versions when values are unchanged.
> **D-10**: every distribution channel ends in the 3-step wizard.

## Scope

**In:**
- `templates/{personal,student,professional,cloud}.toml` at the repository root, embedded with `rust-embed` into `kinevo-mod-admin`.
- `Template` parsing with `toml` and `deny_unknown_fields`, schema validation returning the field path.
- `TemplateRegistry::{list, get}` and `apply_plan(template, current) -> ApplyPlan` (module switches and policy writes that differ from current values) used by T-037 and the CLI `apply-template`.
- `GET /setup/status`-ready listing (`id`, `name_key`, `description_key`); the route itself is T-036.

**Out:**
- Sample data creation: M2 (needs workspaces/goals). Templates only record the sample id.
- Importing external template files over HTTP: not in 1.0.

## Files

| Path | Action | Notes |
|---|---|---|
| `templates/personal.toml, student.toml, professional.toml, cloud.toml` | create | the four shipped templates |
| `crates/kinevo-mod-admin/src/domain/template.rs` | create | schema types, validation |
| `crates/kinevo-mod-admin/src/app/templates.rs` | create | registry, `apply_plan` |
| `crates/kinevo-mod-admin/tests/templates.rs` | create | AC-1…AC-6 |
| `crates/kinevo-mod-admin/tests/fixtures/templates/bad_*.toml` | create | one fixture per rejection |

## Interfaces

```rust
pub struct Template { pub id: String, pub name_key: String, pub description_key: String, pub modules: BTreeMap<String, bool>,
                      pub policies: BTreeMap<String, serde_json::Value>, pub terms: BTreeMap<String, String>, pub sample: Option<SampleId> }
pub fn parse_template(src: &str, ctx: &ValidationCtx) -> Result<Template, AppError>;   // `template_invalid` with details.path
pub struct ApplyPlan { pub module_changes: Vec<(String, bool)>, pub policy_writes: Vec<(String, Scope, serde_json::Value)> }
pub fn apply_plan(t: &Template, enabled: &BTreeSet<String>, current: &dyn Fn(&str, Scope) -> Option<serde_json::Value>) -> ApplyPlan;
```

## Business rules

1. Core modules in `modules` must be absent or `true`; a toggleable module needs its `requires` on (MOD-03).
2. Policy keys and values are validated with the kernel registry; an instance template may set account-scope keys only as first-account defaults (stored as account rows in T-037).
3. Message keys must exist in both catalogues once they exist (T-047 adds that check); until then the validator checks the key syntax only.
4. `apply_plan` returns an empty plan when everything already matches (TPL-04).

## Security

- The schema has no field that accepts a URL to fetch, a script or a secret; a fixture with each is rejected (TPL-02).
- Template text is never interpolated into SQL or logs; only ids and paths appear in errors.

## Implementation notes

- Error `path` uses dotted/bracket notation: `modules.canvas`, `policies."scheduler.day_start"`.
- `toml` and `rust-embed` must be in the stack map; add rows if missing (CQ-16).
- Values written in templates use the types of T-006 (`LocalTime` as `"07:00"`).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the four shipped templates **When** parsed and validated **Then** all pass | `templates::tpl_shipped_templates_valid` |
| AC-2 | **Given** a template with an unknown key **When** parsed **Then** `template_invalid` with the key path | `templates::tpl_01_unknown_key_has_path` |
| AC-3 | **Given** a module map enabling `ai` with `goals` off **When** validated **Then** `template_invalid` at `modules.ai` | `templates::tpl_01_dependency_unmet_has_path` |
| AC-4 | **Given** fixtures containing a URL, a script field and a secret **When** parsed **Then** each is rejected | `templates::tpl_02_no_code_urls_secrets` |
| AC-5 | **Given** `terms` with an unregistered key **When** validated **Then** rejected; a registered key passes and changes labels only | `templates::tpl_03_terms_registry_only` |
| AC-6 | **Given** a template already applied **When** `apply_plan` runs again **Then** the plan is empty | `templates::tpl_04_apply_is_idempotent` |

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
