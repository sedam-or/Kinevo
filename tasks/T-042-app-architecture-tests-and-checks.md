---
id: T-042
title: Add the architecture tests, the check scripts and the RBAC matrix test
milestone: M1
kind: feature
status: todo
depends_on: [T-041, T-038, T-030]
requirements: [CA-02, CA-03, CA-04, CA-05, CA-09, CQ-20, TST-09, TST-12, MOD-08, D-38]
branch: m1-t042-app-architecture-tests-and-checks
budget_lines: 400
assignee: ""
---

# T-042: Add the architecture tests, the check scripts and the RBAC matrix test

## Goal

The dependency graph, layering, SQL placement, tenant-context rules, business-literal rule and lint-exception count are enforced mechanically, and the RBAC matrix is proven against the compiled descriptors.

## Context

> **CA-05** (`spec/standards/clean-architecture.md`): architecture tests run in CI: (a) the crate dependency graph equals the allowed graph; (b) no forbidden imports in `domain`; (c) every table is referenced by exactly one module's `infra`; (d) every route is registered by a module descriptor; (e) every descriptor permission/policy/message key exists in its registry.
> **CA-03**: modules never import each other. **CA-04**: SQL only in `infra/` and only on the module's own tables (platform tables only through `kinevo-db`, D-41). **CA-09**: there is no API to obtain a pooled connection for tenant tables outside `kinevo-db`.
> **TST-09**: `scripts/check-no-literals` scans Rust and TS for literals in a deny-list of contexts (durations, percentages, currency codes, time-of-day strings, URLs) outside allow-listed files; a seeded violation fixture proves it fails.
> **CQ-20**: lint exceptions require a reason; `check-exceptions` reports the count and fails if it grows past the committed baseline without a reviewer note. **TST-12**: abuse-case tests are named `sec_ac_<nn>_<slug>`; CI fails if a registry id has no test (here: for the ids whose milestone is M1).
> **RBAC** (`spec/standards/rbac.md`): the matrix of permission keys against `account_owner` and `instance_admin`; `instance_admin ⊇ account_owner`.
> **MOD-08**: a test fails when a descriptor references a permission key, policy key or message key missing from its registry.

## Scope

**In:**
- `crates/kinevo-app/tests/architecture.rs` implementing CA-05 (a)–(e) with `cargo metadata` and source scans (no extra crates).
- `scripts/check-no-literals` (Python, stdlib) with an allow-list file `scripts/no-literals.allow` and fixtures under `scripts/fixtures/no-literals/`.
- `scripts/check-exceptions` counting `#[allow(`/`expect(` lint exceptions and the baseline file `scripts/exceptions.baseline`.
- `crates/kinevo-app/tests/rbac_matrix.rs`: parses `spec/standards/rbac.md` and compares with the `PermissionTable` built from descriptors.
- `justfile`: `lint` runs the two scripts.

**Out:**
- Web-side checks (`check-i18n`, Biome restrictions): T-047.
- `check-spec-refs`: part of `scripts/spec_tool.py` already.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-app/tests/architecture.rs` | create | 5 rules |
| `crates/kinevo-app/tests/rbac_matrix.rs` | create | matrix vs descriptors |
| `scripts/check-no-literals` | create | executable Python |
| `scripts/no-literals.allow` | create | allow-list with reasons |
| `scripts/check-exceptions` | create | executable Python |
| `scripts/exceptions.baseline` | create | count |
| `scripts/fixtures/no-literals/*` | create | violation fixtures |
| `justfile` | modify | wire into `lint` |

## Interfaces

```text
$ scripts/check-no-literals [paths…]      # exit 1 listing file:line and the matched context
$ scripts/check-exceptions                # exit 1 if count > baseline
```

## Business rules

1. A violation message names the rule id (CA-03, CA-04, …) and the offending file or crate edge.
2. `check-no-literals` flags: duration literals next to time units, percentage literals in domain/app code, ISO currency codes, `HH:MM` strings, `http(s)://` strings, outside the allow-list; each allow entry needs a reason.
3. A table referenced from two modules' `infra` fails (c); platform tables are exempt only in `kinevo-db`.
4. The RBAC test fails on any key present in one side only, and on any admin-only key held by an owner.

## Security

- The SQL-placement test also fails on a `set_config`/`SET LOCAL` outside `kinevo-db` (T-010 owns the narrow rule; this task generalises it to the workspace).
- The scripts read files only; they do not execute project code.

## Implementation notes

- Seed one violating fixture per rule in the tests so the checks are proven to fail when they should.
- Keep the allow-list short; every entry is a reviewable decision.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the workspace **When** the dependency graph is read **Then** it equals the allowed edges of D-29/ARCH | `architecture::ca_05a_dependency_graph_matches` |
| AC-2 | **Given** `domain/` sources **When** scanned **Then** no forbidden import appears | `architecture::ca_02_domain_has_no_infra_imports` |
| AC-3 | **Given** all migrations and `infra/` sources **When** compared **Then** each table is referenced by exactly one module's `infra` | `architecture::ca_05c_one_owner_per_table` |
| AC-4 | **Given** descriptors and the route table **When** compared **Then** every route belongs to a descriptor module and every descriptor key exists in its registry | `architecture::ca_05de_routes_and_keys_registered` |
| AC-5 | **Given** the seeded fixtures **When** `check-no-literals` runs on them **Then** exit 1 for each; on the real tree exit 0 | `architecture::tst_09_no_literals_script_fails_on_fixtures` |
| AC-6 | **Given** the baseline **When** an exception is added without updating it **Then** `check-exceptions` fails | `architecture::cq_20_exception_count_gate` |
| AC-7 | **Given** `rbac.md` and the descriptors **When** compared **Then** identical, and `instance_admin` holds every key | `rbac_matrix::rbac_matrix_matches_descriptors` |
| AC-8 | **Given** the M1 abuse cases in the registry **When** test names are scanned **Then** each has a `sec_ac_<nn>_` test or an entry in the exceptions file | `architecture::tst_12_m1_abuse_cases_have_tests` |

## Tests

```
cargo nextest run -p kinevo-app --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
