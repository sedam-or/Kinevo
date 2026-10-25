---
id: T-255
title: Build the WASM scheduler bindings and the offline preview worker
milestone: M5
kind: feature
status: todo
depends_on: [T-254, T-156]
requirements: [SCH-FR-01, SCH-FR-18, TST-05, FE-09, FE-15, D-22, CA-06]
branch: m5-t255-wasm-scheduler-preview
budget_lines: 380
assignee: ""
---

# T-255: Build the WASM scheduler bindings and the offline preview worker

## Goal

The same scheduler code runs in the browser for a clearly labelled preview, produces byte-identical output to the server, and never persists anything.

## Context

> **Bindings** (`spec/modules/scheduler.md` SCH-FR-18, `08-frontend.md` FE-15): a thin crate `kinevo-scheduler-wasm` exposes `generate(input_json: &str) -> String` (canonical output JSON) and `version() -> String` (`algorithm_version`) using `wasm-bindgen`; built with `wasm-pack --target web` into `web/src/wasm/` (generated, not committed unless the build cannot run in CI; decision in the Report). It depends only on `kinevo-scheduler`.
> **Worker** (FE-15): the WASM module loads lazily in a Web Worker on the Schedule route; the PWA caches the last `GET /schedule/input` (allow-listed query key) and may show a result labelled **"Preview — not saved"**; Apply is disabled with a reason while offline; if WASM fails to load: "Preview unavailable offline". CSP already allows `'wasm-unsafe-eval'` (FE-10). Code splitting: the WASM and worker load only on the Schedule route (FE-09).
> **TST-05 parity**: a test runs the golden fixtures of T-156 through the compiled WASM (Node `--target nodejs` in CI helper, or `wasm-bindgen-test`) and compares canonical bytes with the native output; the same fixtures run natively in `kinevo-scheduler` tests. Where `wasm-pack` or the wasm target is missing the parity test is `environment-limited`, and `scripts/dev-setup.sh` (T-256 preflight) installs `wasm-pack` and the target.
> **Never persist a preview** (D-22): no API call sends preview output; the server is the only authority.

## Scope

**In:**
- Crate `kinevo-scheduler-wasm`, build script/recipe `just wasm`, worker wrapper `web/src/wasm/worker.ts`, Schedule screen preview toggle and labels, parity test, bundle-size note.

**Out:**
- Server changes.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-scheduler-wasm/**` | create |  |
| `Cargo.toml, justfile` | modify | member and `wasm` recipe |
| `web/src/wasm/{worker.ts,client.ts}` | create |  |
| `web/src/features/schedule/preview.tsx` | create |  |
| `web/tests/{wasm-preview.test.ts,e2e/preview.spec.ts}` | create | AC-1…AC-6 |
| `crates/kinevo-scheduler-wasm/tests/parity.rs` | create | AC-1 |

## Interfaces

```rust
#[wasm_bindgen] pub fn generate(input_json: &str) -> Result<String, JsError>;
#[wasm_bindgen] pub fn version() -> String;
```

## Business rules

1. Preview output is always labelled and never sent to the server.
2. Apply is disabled offline with a reason.
3. The worker is terminated on route leave.

## Security

- Input JSON is bounded by the crate's input bounds; the worker has no network access beyond same-origin.

## Implementation notes

- If `wasm-pack` cannot run in the environment, record the exact command that failed and mark parity `environment-limited`; do not fake the result.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the golden fixtures **When** run natively and through WASM **Then** canonical bytes are equal | `parity::tst_05_native_equals_wasm` |
| AC-2 | **Given** invalid input JSON **When** `generate` is called **Then** a typed error, no panic | `parity::bad_input_returns_error` |
| AC-3 | **Given** offline with a cached input **When** the preview runs **Then** the result shows labelled "Preview — not saved" | `wasm_preview::fe_15_preview_label` |
| AC-4 | **Given** WASM failing to load **When** the preview is requested **Then** "Preview unavailable offline" | `wasm_preview::fe_15_graceful_failure` |
| AC-5 | **Given** offline **When** the schedule route renders **Then** Apply is disabled with a reason | `wasm_preview::apply_disabled_offline` |
| AC-6 | **Given** J5 step 7 **When** run **Then** the preview appears labelled and Apply is disabled | `e2e::j5_offline_preview` |

## Tests

```
cargo nextest run -p kinevo-scheduler-wasm   # parity environment-limited without wasm-pack
pnpm --dir web test
just e2e   # environment-limited without browsers
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
