---
id: T-035
title: Add the embedded setup templates and the template schema validator
milestone: M1
kind: feature
status: review
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

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally (database-dependent checks are environment-limited; details below).
- [ ] Only the files listed under *Files* changed; diff within budget (extra wiring/spec documentation and line-budget deviation recorded below).
- [x] Report below is filled in.

## Report

### Summary

Added four embedded, data-only TOML setup templates with strict schema, module graph and policy-registry validation, safe `template_invalid` field paths, and deterministic idempotent application plans. The caller supplies the live registries so template validation does not maintain a duplicate module or policy catalogue.

### Acceptance results

| Criterion | Test | Result |
|---|---|---|
| AC-1 | `templates::tpl_shipped_templates_valid` | pass |
| AC-2 | `templates::tpl_01_unknown_key_has_path` | pass |
| AC-3 | `templates::tpl_01_dependency_unmet_has_path` | pass |
| AC-4 | `templates::tpl_02_no_code_urls_secrets` | pass |
| AC-5 | `templates::tpl_03_terms_registry_only` | pass |
| AC-6 | `templates::tpl_04_apply_is_idempotent` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `cargo nextest run -p kinevo-mod-admin --features kinevo-db/testing -E 'test(tpl_shipped_templates_valid) | test(tpl_01_unknown_key_has_path) | test(tpl_01_dependency_unmet_has_path) | test(tpl_02_no_code_urls_secrets) | test(tpl_03_terms_registry_only) | test(tpl_04_apply_is_idempotent)'` before implementation | 101 | Expected red step: compile failed because `app::templates` and `domain::template` had not been implemented yet. |
| `cargo nextest run -p kinevo-mod-admin --features kinevo-db/testing -E 'test(tpl_shipped_templates_valid) | test(tpl_01_unknown_key_has_path) | test(tpl_01_dependency_unmet_has_path) | test(tpl_02_no_code_urls_secrets) | test(tpl_03_terms_registry_only) | test(tpl_04_apply_is_idempotent)'` | 0 | 6 passed, 34 filtered out. |
| `NEXTEST_TEST_THREADS=4 cargo nextest run -p kinevo-mod-admin --features kinevo-db/testing` | 100 | 10 passed; 3 database tests failed to start with `disposable PostgreSQL test database should start: Unavailable`; nextest cancelled the remaining 27. Environment-limited; see database setup result below. |
| `NEXTEST_TEST_THREADS=4 just check` | 100 | Formatting and Clippy passed. Nextest reported 330 tests; 180 passed, 2 failed while creating a disposable PostgreSQL database, and 148 were not run after cancellation; it also reported 1 skipped test. `cargo deny check` and `just spec` were run separately and passed. |
| `cargo deny check` | 0 | `advisories ok, bans ok, licenses ok, sources ok`; existing duplicate-crate and unmatched-license warnings remain. |
| `just spec` | 0 | `spec_tool.py check`: 0 findings in 60 files; contrast: 0 failures; `tasks_index.py --check`: 0 problems in 132 tasks. |
| `cargo fmt --all -- --check` and `git diff --check` | 0 | Both clean. |
| `eval "$(scripts/dev-postgres.sh env)"` | 3 | `environment-limited: container kinevo-dev-pg already exists without a script state file; refusing to replace it`. No PostgreSQL server was started or replaced. |
| `pg_isready` | 2 | `/run/postgresql:5432 - no response`; at the script's recorded port, `127.0.0.1:45949 - no response`. No local `initdb` or `pg_ctl` was found. |
| `df -h /` before builds/tests | 0 | `Filesystem /dev/mapper/nvme0n1p3crypted 468G 405G 61G 88% /`; no cleanup performed. |
| `df -h /` at end of T-035 verification | 0 | `Filesystem /dev/mapper/nvme0n1p3crypted 468G 408G 58G 88% /`; no cleanup performed. |

The first acceptance-test run failed at compile time because the template modules did not exist yet; this was the expected red step. Later intermediate Clippy findings were fixed. The final `just check` did not complete because the database fixture could not start; it is reported as environment-limited, not as a pass.

### Files changed

| Path | Added | Removed |
|---|---:|---:|
| `Cargo.lock` | 136 | 0 |
| `crates/kinevo-mod-admin/Cargo.toml` | 3 | 0 |
| `crates/kinevo-mod-admin/src/app/mod.rs` | 3 | 0 |
| `crates/kinevo-mod-admin/src/app/templates.rs` | 127 | 0 |
| `crates/kinevo-mod-admin/src/domain/mod.rs` | 3 | 0 |
| `crates/kinevo-mod-admin/src/domain/template.rs` | 276 | 0 |
| `crates/kinevo-mod-admin/tests/fixtures/templates/bad_dependency.toml` | 7 | 0 |
| `crates/kinevo-mod-admin/tests/fixtures/templates/bad_script.toml` | 4 | 0 |
| `crates/kinevo-mod-admin/tests/fixtures/templates/bad_secret.toml` | 4 | 0 |
| `crates/kinevo-mod-admin/tests/fixtures/templates/bad_unknown_policy.toml` | 5 | 0 |
| `crates/kinevo-mod-admin/tests/fixtures/templates/bad_url.toml` | 4 | 0 |
| `crates/kinevo-mod-admin/tests/templates.rs` | 219 | 0 |
| `spec/05-templates-and-setup.md` | 2 | 2 |
| `spec/decisions.md` | 4 | 0 |
| `spec/standards/open-source-stack.md` | 2 | 0 |
| `tasks/T-035-admin-templates.md` | 97 | 7 |
| `tasks/index.md` | 2 | 2 |
| `templates/cloud.toml` | 13 | 0 |
| `templates/personal.toml` | 10 | 0 |
| `templates/professional.toml` | 16 | 0 |
| `templates/student.toml` | 17 | 0 |

The generated lockfile is excluded from the budget under D-55.

### Deviations

- Added the explicitly required `toml` stack-map entry and direct `toml`, `rust-embed`, and `serde_path_to_error` dependencies; `Cargo.lock` changed as generated output.
- Added module exports and clarified template omission/scope behavior in the cited spec. Added D-118 through D-121 in the same commit. These are adjacent wiring/spec files beyond the task's Files list and are recorded for traceability.
- The non-lockfile change is 818 additions and 11 removals (829 changed lines), above `budget_lines: 330`; D-55 says this is not a blocker. The generated `Cargo.lock` adds 136 lines and is excluded.
- PostgreSQL-dependent package/workspace checks are environment-limited by the existing container-without-state condition. The named T-035 acceptance tests pass.

### Decisions made by implementer

| Decision | Choice, alternatives and reason | Files | Rollback |
|---|---|---|---|
| D-118 (pending owner review) | Store term overrides as `templates.<template-id>.terms.<term-key>` message keys. This follows the spec's `term key → message key` rule; embedding the English examples as literal values was rejected. T-047 must add the corresponding `en` and `id` catalogue entries. | `spec/decisions.md`, `templates/student.toml`, `templates/professional.toml` | Replace the override keys with the approved catalogue keys and remove the matching D-118 entry. |
| D-119 (pending owner review) | Inject module, policy and terminology registries through `ValidationCtx`; copying current/future registries into admin was rejected to avoid drift, and postponing validation would violate TPL-01. | `spec/decisions.md`, `crates/kinevo-mod-admin/src/domain/template.rs`, `crates/kinevo-mod-admin/src/app/templates.rs` | Switch call sites to the approved registry interface and remove D-119. |
| D-120 (pending owner review) | Normalize omitted toggleable modules to `false`; core modules remain implicit. Preserving omitted module state was rejected because a template should converge to its selected module set. | `spec/decisions.md`, `spec/05-templates-and-setup.md`, admin template files | Restore the previous omission semantics and remove the D-120 clarification. |
| D-121 (pending owner review) | Write a policy preset at every scope allowed by its `PolicySpec`; selecting only one scope would omit a declared instance or first-account default, and the template schema has no scope selector. | `spec/decisions.md`, `spec/05-templates-and-setup.md`, admin template files | Adopt the architect's scope rule, update `apply_plan`, and remove the D-121 clarification. |

### Spec issues noticed

- T-047 must add both-language catalogue entries for the D-118 term message keys.
- T-037 must construct the validation context from the complete compiled module and policy registries, including modules and policies introduced after M1.

### Not done / not verified

- Database-backed tests could not run because `scripts/dev-postgres.sh env` refused to replace the pre-existing `kinevo-dev-pg` container without its state file; local `pg_isready` checks found no responding server. The exact command and errors are recorded above.
- The final `just check` therefore exited 101 and did not reach its embedded `cargo deny check` or `just spec` stages; both were run separately and returned 0.
- No template-import HTTP path, sample-data creation, locale catalogue existence check, or setup route was implemented; those are outside T-035 or assigned to later tasks.

### Follow-up suggestions

- Keep the context assembled in one application composition point so the embedded templates and runtime setup flow use the same registries.

## Questions

No open blocking questions. D-118 through D-121 are implementer decisions pending owner review.
