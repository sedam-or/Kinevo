---
id: T-041
title: Generate the OpenAPI document and add the drift check
milestone: M1
kind: feature
status: review
depends_on: [T-040, T-016, T-038]
requirements: [TST-04, API-01, API-03, ARCH-20, CA-14]
branch: m1-t041-app-openapi-contract
budget_lines: 330
assignee: "OpenAI Codex"
---

# T-041: Generate the OpenAPI document and add the drift check

## Goal

`kinevo openapi` prints the OpenAPI 3.1 document derived from the route table and DTOs; the committed `contracts/openapi.json` must equal it.

## Context

> **TST-04** (`spec/12-testing.md`): the generated OpenAPI document is committed (`contracts/openapi.json`); CI regenerates and fails on drift; TS client types are regenerated and must match.
> **CA-14**: DTOs are separate from domain types; OpenAPI is derived from the DTOs (`utoipa`).
> **Route table** (T-016): every route is declared through `RouteTable::route(RouteSpec, …)`; `specs()` lists method, path, module, access, rate class and idempotency mode, which feed the document's `security`, `x-kinevo-permission` and parameter entries (`Idempotency-Key`, `If-Match`).
> **Errors**: every operation documents `application/problem+json` with the registry codes it can return (from the route spec and the module doc); no example contains a real secret (SEC-AC-11).
> **API conventions** (`spec/07-api-conventions.md`): prefix `/api/v1`, cursor pagination parameters, headers, status codes.

## Scope

**In:**
- `kinevo openapi [--check <path>]` printing canonical JSON (sorted keys, 2-space indent, trailing newline).
- `utoipa` schema derives on the DTOs of T-016…T-038 that lack them, plus the document assembly in `kinevo-app/src/openapi.rs` merging each module's fragment.
- `contracts/openapi.json` committed, and the `just spec` recipe extended with `cargo run -q -p kinevo-app -- openapi --check contracts/openapi.json`.
- A test that every route in the table appears in the document and every document path is a route.

**Out:**
- TypeScript client generation: T-043 (web scaffold) consumes the file.
- Contract compatibility checks against a previous release (CON-C2): from the first release.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-app/src/openapi.rs` | create | assembly |
| `crates/kinevo-app/src/cli.rs` | modify | `openapi` command |
| `contracts/openapi.json` | create | committed output |
| `justfile` | modify | add the drift check to `spec` |
| `crates/kinevo-app/tests/openapi.rs` | create | AC-1…AC-6 |
| `crates/kinevo-mod-identity/src/http/*.rs, crates/kinevo-mod-admin/src/http/*.rs` | modify | derives and examples only |

## Interfaces

```text
$ kinevo openapi            # prints the document
$ kinevo openapi --check contracts/openapi.json    # exit 1 and a diff summary if different
```

## Business rules

1. Output is deterministic: same binary, same bytes.
2. Every operation has `operationId` (`<module>_<verb>_<noun>`), a summary from the route spec and security requirements derived from `Access`.
3. Components are shared (`Problem`, `ValidationProblem`, pagination envelope).
4. Examples use `example.test` addresses and synthetic ids only (PRV-08).

## Security

- A scan test fails when any example or description contains a string matching a secret pattern (`sk-`, `Bearer ey`, a 43-char base64url token).
- The document describes no internal-only route (health probes are included as public GETs, `/metrics` is not).

## Implementation notes

- Keep derive-only edits in the module crates; do not change behaviour.
- Canonicalise through `serde_json` with `preserve_order` off so keys sort.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the route table **When** the document is generated **Then** every route is present and no extra path exists | `openapi::every_route_documented` |
| AC-2 | **Given** the committed file **When** compared with a fresh generation **Then** identical | `openapi::tst_04_no_drift` |
| AC-3 | **Given** the document **When** inspected **Then** every operation lists problem+json responses with registry codes only | `openapi::errors_use_registry_codes` |
| AC-4 | **Given** the document **When** scanned **Then** no example matches a secret pattern | `openapi::sec_ac_11_no_secret_examples` |
| AC-5 | **Given** a route with `IdemMode::Required` **When** documented **Then** the `Idempotency-Key` header is a required parameter | `openapi::idempotency_header_documented` |
| AC-6 | **Given** two generations **When** compared **Then** byte-identical | `openapi::deterministic_output` |

## Tests

```
cargo nextest run -p kinevo-app --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [x] The task files were changed, with only minimal adjacent wiring; the over-budget diff is recorded below per D-55/D-56.
- [x] Report below is filled in.

## Report

### Summary

`kinevo openapi` now emits a canonical OpenAPI 3.1 JSON document assembled from the identity, admin, and system route inventories and module DTO schemas. The `--check` mode compares the generated bytes with the committed contract, and `just spec` runs that drift check. All six named acceptance tests pass.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `openapi::every_route_documented` | pass |
| AC-2 | `openapi::tst_04_no_drift` | pass |
| AC-3 | `openapi::errors_use_registry_codes` | pass |
| AC-4 | `openapi::sec_ac_11_no_secret_examples` | pass |
| AC-5 | `openapi::idempotency_header_documented` | pass |
| AC-6 | `openapi::deterministic_output` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `cargo run -q -p kinevo-app -- openapi` | 0 | Generated `contracts/openapi.json`. |
| `cargo nextest run -p kinevo-app --features kinevo-db/testing` | 0 | 27 passed, including the six named acceptance tests. |
| `set -e; export PATH="$HOME/.cargo/bin:$PATH"; cargo fmt --all; df -h /; eval "$(scripts/dev-postgres.sh env)"; df -h /; just check` | 100 | First full run reached 218/382: 215 passed, 3 failed, 1 skipped; the failures were DB setup `Unavailable` in `audit_jobs::sec_ac_10_summary_has_no_content`, `finish_setup::mod_10_provisioner_failure_rolls_back`, and `finish_setup::password_policy_enforced`; 164 tests were cancelled. |
| `eval "$(scripts/dev-postgres.sh env)"; pg_isready -h 127.0.0.1 -p "$(cat /tmp/kinevo-dev-pg/port)" -U postgres -d postgres` | 0 | Shared test PostgreSQL accepted connections; a read-only status query showed 6 of 100 connections in use. |
| `export PATH="$HOME/.cargo/bin:$PATH"; eval "$(scripts/dev-postgres.sh env)"; df -h /; just check` | 0 | Full gate completed: 382 tests discovered, 1 skipped, no failures; lint, cargo-deny, spec checks, task index check, and OpenAPI drift check completed. The failed DB bootstrap did not recur. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated `tasks/index.md`. |
| `python3 scripts/tasks_index.py --check` | 0 | `0 problem(s) in 132 task(s)`. |
| `python3 scripts/spec_tool.py check` | 0 | Spec consistency check passed. |
| `git diff --check` | 0 | No whitespace errors. |

The successful full gate used the already-running shared PostgreSQL 17 test server. No environment-limited checks were needed. The only output warning was the existing clippy configuration warning that `rand::thread_rng` is not a reachable function; it did not fail clippy or `just check`.

### Files changed

| Path | Lines added / removed |
|---|---:|
| `crates/kinevo-app/src/openapi.rs` | +420 / -0 |
| `crates/kinevo-app/tests/openapi.rs` | +303 / -0 |
| `crates/kinevo-app/src/cli.rs` | +15 / -2 |
| `crates/kinevo-app/src/lib.rs` | +1 / -0 |
| `crates/kinevo-app/src/main.rs` | +14 / -0 |
| `crates/kinevo-app/src/serve.rs` | +16 / -6 |
| `crates/kinevo-app/Cargo.toml` | +1 / -0 |
| `crates/kinevo-mod-identity/src/http/mod.rs` | +249 / -0 |
| `crates/kinevo-mod-identity/src/http/openapi.rs` | +38 / -0 |
| `crates/kinevo-mod-identity/src/http/account.rs` | +7 / -6 |
| `crates/kinevo-mod-identity/src/http/admin.rs` | +10 / -10 |
| `crates/kinevo-mod-identity/src/http/deletion.rs` | +2 / -2 |
| `crates/kinevo-mod-identity/src/http/export.rs` | +3 / -2 |
| `crates/kinevo-mod-identity/src/http/refresh.rs` | +2 / -1 |
| `crates/kinevo-mod-identity/src/http/register.rs` | +2 / -2 |
| `crates/kinevo-mod-identity/src/http/reset.rs` | +4 / -4 |
| `crates/kinevo-mod-identity/src/http/session_response.rs` | +2 / -2 |
| `crates/kinevo-mod-identity/src/http/totp.rs` | +6 / -6 |
| `crates/kinevo-mod-admin/src/http/mod.rs` | +201 / -0 |
| `crates/kinevo-mod-admin/src/http/openapi.rs` | +28 / -0 |
| `crates/kinevo-mod-admin/src/http/audit.rs` | +4 / -2 |
| `crates/kinevo-mod-admin/src/http/modules.rs` | +5 / -4 |
| `crates/kinevo-mod-admin/src/http/policies.rs` | +8 / -6 |
| `crates/kinevo-mod-admin/src/http/secrets.rs` | +4 / -3 |
| `crates/kinevo-mod-admin/src/http/setup.rs` | +8 / -8 |
| `crates/kinevo-mod-admin/Cargo.toml` | +1 / -0 |
| `justfile` | +1 / -0 |
| `contracts/openapi.json` (generated) | +14,723 / -0 |
| `Cargo.lock` (generated) | +2 / -0 |
| `tasks/T-041-app-openapi-contract.md` (Report/status) | +96 / -7 |
| `tasks/index.md` (generated) | +2 / -2 |

### Deviations

- The implementation diff (excluding task/index documentation, generated contract, and lockfile) is 1,421 changed lines against the 330-line guidance. This is not treated as a gate per D-55; most of the added implementation is the route inventory, schema fragments, and acceptance tests needed for the contract. The generated contract adds 14,723 lines and `Cargo.lock` adds 2, both excluded from the budget by D-55.
- Minimal adjacent wiring changed `crates/kinevo-app/src/lib.rs`, `main.rs`, `serve.rs`, and package manifests so the CLI and composed route inventory can be exposed and `utoipa` derives compile. These are recorded under D-56; no unrelated behavior was changed.
- `RouteSpec` has no route-specific error-code list, and `IdemMode` currently has only `Off` and `Optional`. Error responses therefore enumerate registry codes by status; `Idempotency-Key` is documented as required for non-`Off` routes to satisfy AC-5 conservatively, without changing runtime idempotency behavior.
- The app's composed health router mounts `/livez` and `/readyz`; the system route table also describes `/api/v1/time`. The contract includes the declared system route inventory, and the coverage test verifies active routes are represented without changing runtime routing.

### Spec issues noticed

- API-70's optional idempotency mode and AC-5's required-header case do not have a matching `Required` enum variant or runtime contract. The document uses a required header for routes with idempotency enabled; runtime behavior is unchanged.
- The route descriptor type has no field for per-route error codes or DTO associations. The contract currently associates known DTO schemas by route and groups registered problem codes by HTTP status.
- The system route inventory includes `/api/v1/time`, which is not mounted by the current application composition root.

### Not done / not verified

- No browser E2E or TypeScript client generation was run; TypeScript generation is explicitly out of scope for T-041 and is assigned to T-043. No check remained environment-limited.
- One initial full-gate attempt failed transiently during shared database bootstrap; the full gate was rerun successfully as recorded above.

### Follow-up suggestions

- Architect review should decide whether to add explicit required idempotency metadata and route-specific error/DTO metadata in a later task or decision.

## Questions

- Q-1 (resolved by implementer, pending architect review, 2026-10-30): AC-5 names `IdemMode::Required`, but `kinevo-http::route::IdemMode` contains only `Off` and `Optional`. The generated contract marks the header required for non-`Off` descriptors, following the acceptance requirement and API-70's conservative header description; no runtime behavior changed. Recommendation: decide in a later task whether a `Required` mode is needed and align runtime enforcement.
- Q-2 (resolved by implementer, pending architect review, 2026-10-30): the system route table describes `/api/v1/time`, while the app composition root currently mounts only `/livez` and `/readyz` from the health router. The OpenAPI document includes the declared route inventory and does not change runtime routing. Recommendation: confirm in a later routing task whether `/api/v1/time` should be mounted or removed from the declared inventory.
- Q-3 (resolved by implementer, pending architect review, 2026-10-30): `RouteSpec` has neither per-route error-code metadata nor request/response DTO associations. The contract maps the implemented DTO schemas to known operations and emits registered error codes by HTTP status. Recommendation: decide whether richer route metadata belongs in a later API-contract task.
