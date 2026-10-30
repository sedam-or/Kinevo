---
id: T-041
title: Generate the OpenAPI document and add the drift check
milestone: M1
kind: feature
status: in_progress
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

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
