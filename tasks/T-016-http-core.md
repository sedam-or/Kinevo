---
id: T-016
title: Build the kinevo-http core (route table, request id, problem+json, headers, health, extractors)
milestone: M1
kind: feature
status: review
depends_on: [T-003, T-005, T-007, T-008]
requirements: [API-01, API-02, API-04, API-07, API-13, API-20, API-21, API-22, API-31, API-32, API-42, FE-10, ERR-01, ERR-04, SEC-04, SEC-05, SEC-08, RBAC-01, OPS-02]
branch: m1-t016-http-core
budget_lines: 450
assignee: "OpenAI Codex"
---

# T-016: Build the kinevo-http core (route table, request id, problem+json, headers, health, extractors)

## Goal

`kinevo-http` provides everything a module's HTTP layer needs that is not authentication: a route table that records each route's access rule, request ids, RFC 9457 error responses with localised titles, security headers, strict extractors, health endpoints and the time endpoint. Handlers return `Result<T, AppError>` and never build error bodies themselves.

## Context

> **API-01/02/04/07/13**: Base path `/api/v1`; JSON only (`Content-Type: application/json` → else 415 `unsupported_media_type`, `details.accepted`); requests reject unknown fields (422 `unknown_field` with `errors[].path`); health endpoints `GET /livez`, `GET /readyz` live outside the versioned API; every response carries `X-Request-Id` (echoed from the request if a valid UUID, else generated).
> **Problem shape** (`07` §3): `type: "urn:kinevo:error:<code>"`, `title` (localised), `status`, `detail` (localised), `code`, `request_id`, optional `errors[]` and `details`. **API-20**: `code` stable; text translated and may change. **API-21**: 422 with all field errors at once. **API-22**: 500 `internal_error` with `request_id` only (no stack, SQL, path, secrets, content).
> **Localisation** (`07` §9): `Accept-Language` selects `en` or `id`; default: account locale if authenticated, else instance default. Only `title`/`detail` are localised.
> **ERR-01**: A code is added only in the registry + Rust enum + both catalogs; tests fail if any of the three diverge. Message keys `error.<code>.title` and `error.<code>.detail` in both catalogs (`web/src/i18n/en.json`, `web/src/i18n/id.json`). English titles are normative (`error-handling.md` §2); Indonesian text is written at implementation time and reviewed by the maintainer (M-L10N).
> **ERR-04**: Logging: 4xx at `info` with `error_code` and `request_id` only; 5xx at `error` with the same plus the internal error chain **without** user data.
> **SEC-04**: Log fields only: `request_id`, `account_id`, `module`, `route` (template), `method`, `status`, `latency_ms`, `error_code`, `event`, `job_kind`, `attempt`, `policy_key`, `constraint_name`, `count`. Never bodies, headers (except truncated `user-agent` family), query strings, emails, titles, tokens.
> **FE-10 security headers** (server-set): CSP `default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'`; `Referrer-Policy: no-referrer`; `X-Content-Type-Options: nosniff`; `Permissions-Policy` empty; HSTS (`max-age=31536000; includeSubDomains`) only when the public URL is https (SEC-08).
> **HTTP caching** (`containers-and-caching.md` §3): `GET /api/v1/*` authenticated → `no-store`; `GET /api/v1/openapi.json` and `/api/v1/instance` → `public, max-age=60`.
> **API-31/32**: Sort: `?sort=field`/`-field` from an allow-list, `id` as final tiebreaker, unknown → 422 `sort_invalid` (`details.allowed`). Unknown query parameters → 422 `query_param_unknown` (`details.name`).
> **API-42**: `GET /api/v1/time` returns `{ "now": instant }` from the injected clock.
> **OPS-02**: `/livez` 200 when the process runs; `/readyz` 200 only when the database answers, migrations are current and isolation checks passed; both return no data beyond `{status}`.
> **RBAC-01 / SEC-05**: Every route descriptor declares exactly one permission key (or `public`); a route without it fails the architecture test.

## Scope

**In:**
- `RouteSpec { method, path: &'static str, module: &'static str, access: Access, rate: RateClass, idempotency: IdemMode, summary: &'static str }` with `Access::Public | Access::Permission(&'static str)`, `RateClass::{None, Auth, Api, Ai, Upload, Sync, Export}`, `IdemMode::{Off, Optional}`; and `RouteTable` that collects `(RouteSpec, axum MethodRouter)` pairs: `RouteTable::new().route(spec, method_router)`, `.specs() -> &[RouteSpec]` (used by architecture tests and the RBAC matrix test), `.into_router(state) -> axum::Router`. Paths are relative to `/api/v1` unless `RouteSpec.path` starts with `/` and the module flags `outside_api` (`/livez`, `/readyz`).
- `HttpState`: `Arc` bundle of `clock`, `ids`, `tokens`, `i18n: Arc<Catalog>`, `instance: Arc<dyn InstanceState>`, `ready: Arc<dyn ReadyCheck>`, `public_url: Option<HttpsUrl>`, `cursor: Arc<CursorCodec>`. (Further fields are added by T-017/T-018/T-019 as extensions via `Extension<…>` layers, not by editing this struct.)
- Layers (outermost first): request id → panic catcher → trace (SEC-04 fields) → security headers/Cache-Control → body limit (config `max_body_bytes`, `payload_too_large` with `details.max_bytes`) → content-type check for bodies.
- `ApiError` (`IntoResponse`): wraps `AppError`, renders problem+json with the catalogue, `Retry-After` for `rate_limited` and `idempotency_in_progress`, `WWW-Authenticate: Bearer` for 401, `Allow`... not needed. Unhandled `AppError` code → still rendered; the code→status table comes from T-003.
- `Catalog::load()` embeds `web/src/i18n/en.json` and `id.json` with `include_str!`, flat `"key": "text"` maps; `Catalog::title(code, locale)` / `detail(...)`; locale negotiation `negotiate(accept_language: Option<&str>, fallback: Locale) -> Locale` (q-values, `en-US` → `en`, `id-ID` → `id`, unknown → fallback).
- Create `web/src/i18n/en.json` and `web/src/i18n/id.json` with `error.<code>.title` and `error.<code>.detail` for **every** `ErrorCode` (from T-003): English from the registry titles, Indonesian written by the implementer, `detail` sentences short and free of placeholders. Mark Indonesian wording for review in the Report.
- Extractors: `ValidatedJson<T>` (content-type check, `serde_path_to_error`, maps `unknown field` to field error `unknown_field` and type errors to `schema_invalid`/`required`-style field errors with request-JSON `path`; collects one error — serde stops at the first — and documents this limit; then calls `T::validate(&self) -> Collector` to add further errors, VAL-01), `StrictQuery<T>` (unknown parameter → `query_param_unknown`), `PageParams` (limit/cursor via T-007), `SortParam::parse(raw, allowed)` (API-31).
- Routes: `GET /livez`, `GET /readyz` (via `ReadyCheck::check() -> Result<(), ReadyFailure>`; failure → 503 `{status:"unavailable"}` without detail), `GET /api/v1/time`.
- `ReadyCheck` trait (`async-trait`) lives here; the real one is wired in T-039.
- A test helper `kinevo_http::testing::TestServer` (feature `testing`) built on `tower::ServiceExt::oneshot` returning status, headers and parsed JSON.

**Out:**
- Authentication, permission and module gates: T-017. Rate limiting: T-018. Idempotency and If-Match: T-019.
- OpenAPI annotations/assembly: T-041.
- Static asset / SPA serving (`rust-embed`): T-039.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-http/Cargo.toml` | modify | deps `axum`, `tokio`, `tower`, `tower-http` (trace, catch-panic, limit), `tracing`, `serde`, `serde_json`, `serde_path_to_error`, `async-trait`; feature `testing` |
| `crates/kinevo-http/src/lib.rs` | modify | modules below |
| `crates/kinevo-http/src/route.rs` | create | `RouteSpec`, `RouteTable` |
| `crates/kinevo-http/src/state.rs` | create | `HttpState`, `ReadyCheck` |
| `crates/kinevo-http/src/layers.rs` | create | request id, panic, trace, headers, limits |
| `crates/kinevo-http/src/problem.rs` | create | `ApiError`, rendering |
| `crates/kinevo-http/src/i18n.rs` | create | `Catalog`, `negotiate` |
| `crates/kinevo-http/src/extract.rs` | create | `ValidatedJson`, `StrictQuery`, `PageParams`, `SortParam` |
| `crates/kinevo-http/src/system_routes.rs` | create | livez, readyz, time |
| `crates/kinevo-http/src/testing.rs` | create | `TestServer` |
| `crates/kinevo-http/tests/http_core.rs` | create | AC tests |
| `web/src/i18n/en.json`, `web/src/i18n/id.json` | create | `error.*` keys |
| `spec/standards/open-source-stack.md` | modify | rows for `tower-http`, `serde_path_to_error` if missing |

## Interfaces

```rust
pub enum Access { Public, Permission(&'static str) }
pub struct RouteSpec { pub method: Method, pub path: &'static str, pub module: &'static str, pub access: Access, pub rate: RateClass, pub idempotency: IdemMode, pub summary: &'static str }
pub struct RouteTable; impl RouteTable { pub fn route(self, spec: RouteSpec, mr: MethodRouter<HttpState>) -> Self; pub fn specs(&self) -> &[RouteSpec]; pub fn into_router(self, s: HttpState) -> Router; }
pub struct ApiError(pub AppError);   impl IntoResponse for ApiError {}   impl From<AppError> for ApiError {}
pub type ApiResult<T> = Result<T, ApiError>;
pub fn negotiate(header: Option<&str>, fallback: Locale) -> Locale;
```

Response body for an error (exact keys): `type`, `title`, `status`, `detail`, `code`, `request_id`, plus `errors` (only for validation) and `details` (only when non-empty).

## Business rules

1. `X-Request-Id` is the request's valid UUID, else a fresh UUIDv7 from `IdGen`; it appears in every response, in the problem body and in every log line of the request.
2. Status and retry class of an error come only from `ErrorCode` (T-003); the renderer never overrides them.
3. A panic in a handler yields 500 `internal_error` with the request id and an `error`-level log `event="panic"`; nothing else.
4. Body above the limit → 413 before the handler runs; wrong/missing content type on a request with a body → 415 with `details.accepted = ["application/json"]`.
5. An unknown JSON field is reported as `{path, code: unknown_field}` (422 `validation_failed`).
6. `Accept-Language: id-ID,id;q=0.9,en;q=0.5` → `id`; `fr` → fallback.
7. HSTS is sent only when `public_url` is https.

## Security

- The panic message and error chain are logged at `error` but never returned and never include request data.
- The trace layer records only the SEC-04 allow-list; the route is the **template** (`/tasks/{id}`), never the concrete URL. A test greps captured log output for a canary in the query string and body.
- Readiness output has no version, host or detail.
- Rendering never copies the submitted value of a field into an error (`details` is typed, T-003).

## Implementation notes

- Keep layers as plain `tower` functions so they can be reused in the test server.
- `Catalog` returns the English text if an Indonesian key is missing (production fallback) but the `i18n_catalog_complete` test fails the build in that case (ERR-01).
- Do not add business endpoints.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a request with a valid UUID `X-Request-Id` **When** any route answers **Then** the same id is echoed; without one a UUID is generated and appears in the problem body | `http_core::api_13_request_id_echo_or_generate` |
| AC-2 | **Given** a handler returning `AppError::new(VersionConflict)` with `current_version` **When** requested with `Accept-Language: id` **Then** 409 `application/problem+json`, `code`, `type` `urn:kinevo:error:version_conflict`, Indonesian `title`, `details.current_version` | `http_core::api_20_problem_json_shape_and_locale` |
| AC-3 | **Given** a handler that panics **When** requested **Then** 500 `internal_error`, body has only the standard keys plus `request_id`, and the panic text is absent from the body | `http_core::api_22_panic_is_opaque` |
| AC-4 | **Given** `POST` with `Content-Type: text/plain` **When** posted **Then** 415 with `details.accepted` | `http_core::api_02_unsupported_media_type` |
| AC-5 | **Given** a body above the limit **When** posted **Then** 413 `payload_too_large` with `details.max_bytes` and the handler never ran | `http_core::payload_too_large_before_handler` |
| AC-6 | **Given** JSON `{"title":"x","extra":1}` for a type with `deny_unknown_fields` **When** posted through `ValidatedJson` **Then** 422 `validation_failed`, `errors[0] = {path:"extra", code:"unknown_field"}`; a type-mismatch yields a field error at the right path | `http_core::api_04_unknown_field_rejected` |
| AC-7 | **Given** a type whose `validate` adds two errors **When** posted **Then** both appear in `errors[]` in order (VAL-01/API-21) | `http_core::api_21_all_validation_errors_at_once` |
| AC-8 | **Given** `?bogus=1` on a route with `StrictQuery` **When** requested **Then** 422 `query_param_unknown` with `details.name = "bogus"`; `?sort=nope` → `sort_invalid` with `details.allowed` | `http_core::api_32_unknown_query_and_sort` |
| AC-9 | **Given** any route **When** requested **Then** the FE-10 headers are present exactly as specified; `Strict-Transport-Security` only when `public_url` is https | `http_core::fe_10_security_headers` |
| AC-10 | **Given** `/livez` and `/readyz` with a failing `ReadyCheck` **When** requested **Then** 200 `{status:"ok"}` and 503 `{status:"unavailable"}` with no other keys | `http_core::ops_02_health_endpoints` |
| AC-11 | **Given** captured logs for a request with a canary in query and body **When** inspected **Then** only allow-listed fields appear and the canary is absent | `http_core::sec_04_log_allow_list` |
| AC-12 | **Given** every `ErrorCode` **When** the catalogs are loaded **Then** `error.<code>.title` and `.detail` exist in both files, and no extra `error.*` keys exist | `http_core::err_01_catalogs_match_registry` |
| AC-13 | **Given** the `Accept-Language` examples in Business rules **When** negotiated **Then** results match | `i18n::tests::negotiate_cases` |
| AC-14 | **Given** a `RouteTable` with one route lacking an access rule **When** the table is built **Then** it does not compile (`RouteSpec.access` is required) and the unit test lists all specs | `route::tests::rbac_01_every_route_declares_access` |

## Tests

```
cargo nextest run -p kinevo-http
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes.
- [x] `just check` passes locally.
- [x] Changed files and diff size are listed in the Report; additions outside *Files* are documented as deviations.
- [x] The Indonesian catalogue wording is listed in the Report for maintainer review.
- [x] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

### Summary

Implemented the HTTP foundation: route descriptors/table, request-id and panic handling, structured tracing, security/cache/body/content-type layers, RFC 9457 responses with locale catalogs, strict extractors, health/time routes, and the feature-gated test server. Added the missing typed kernel validation/detail support required by the task and aligned the field-code registry with the API-04 contract.
Tests were written before implementation and observed failing until the modules and catalogs were added.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `http_core::api_13_request_id_echo_or_generate` | pass |
| AC-2 | `http_core::api_20_problem_json_shape_and_locale` | pass |
| AC-3 | `http_core::api_22_panic_is_opaque` | pass |
| AC-4 | `http_core::api_02_unsupported_media_type` | pass |
| AC-5 | `http_core::payload_too_large_before_handler` | pass |
| AC-6 | `http_core::api_04_unknown_field_rejected` | pass |
| AC-7 | `http_core::api_21_all_validation_errors_at_once` | pass |
| AC-8 | `http_core::api_32_unknown_query_and_sort` | pass |
| AC-9 | `http_core::fe_10_security_headers` | pass |
| AC-10 | `http_core::ops_02_health_endpoints` | pass |
| AC-11 | `http_core::sec_04_log_allow_list` | pass |
| AC-12 | `http_core::err_01_catalogs_match_registry` | pass |
| AC-13 | `i18n::tests::negotiate_cases` | pass |
| AC-14 | `route::tests::rbac_01_every_route_declares_access` | pass |

The additional API-42 clock test is `http_core::api_42_time_uses_injected_clock` and passes.

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo fmt --all` | 0 | Formatted the touched Rust files. |
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo clippy -p kinevo-http --all-targets --all-features -- -D warnings` | 0 | Passed. Clippy emitted the repository's existing unreachable-method warning for `uuid::Uuid::new_v4` from `clippy.toml`. |
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo nextest run -p kinevo-http` | 0 | 16 passed, 0 skipped. |
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo nextest run -p kinevo-http --features testing` | 0 | 16 passed, 0 skipped; verifies `TestServer`. |
| `export PATH="$HOME/.cargo/bin:$PATH"; just check` | 0 | 83 passed, 0 skipped; `cargo deny` advisories, bans, licenses, and sources all OK; contrast failures: 0; spec: 0 findings in 60 files; task index: 0 problems in 132 tasks. Existing `clippy.toml` unreachable-method warnings (`uuid::Uuid::new_v4`, `rand::thread_rng`) and cargo-deny duplicate-version/unmatched-license warnings remain non-failing. |
| `python3 scripts/spec_tool.py check` | 0 | `0 finding(s) in 60 spec files`. |
| `python3 scripts/tasks_index.py --check` | 0 | `0 problem(s) in 132 task(s)`. |
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo clippy -p kinevo-http --all-targets --all-features -- -D warnings` (first run) | 101 | Test helpers passed `serde_json::Value` by value without consuming it; changed comparisons to consume the expected value, then reran successfully. |
| `export PATH="$HOME/.cargo/bin:$PATH"; just check` (first run after implementation) | 1 | `err_01_field_codes_match_spec` found `unknown_field` missing from the field-level registry; added it under D-60 and reran successfully. |
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo fmt --all -- --check && cargo clippy -p kinevo-http --all-targets --all-features -- -D warnings` (before formatting) | 1 | `cargo fmt --check` reported a required line wrap in the test helper; `cargo fmt --all` corrected it. |

### Files changed

Path → added/removed lines. Counts are relative to the T-016 start commit `29f62fb`; lockfile and generated catalogs are shown separately and excluded from the implementation budget (D-55).

| Path | Added | Removed |
|---|---:|---:|
| `Cargo.lock` (generated) | 115 | 0 |
| `crates/kinevo-http/Cargo.toml` | 17 | 0 |
| `crates/kinevo-http/src/lib.rs` | 11 | 0 |
| `crates/kinevo-http/src/extract.rs` | 262 | 0 |
| `crates/kinevo-http/src/i18n.rs` | 122 | 0 |
| `crates/kinevo-http/src/layers.rs` | 226 | 0 |
| `crates/kinevo-http/src/problem.rs` | 128 | 0 |
| `crates/kinevo-http/src/route.rs` | 140 | 0 |
| `crates/kinevo-http/src/state.rs` | 83 | 0 |
| `crates/kinevo-http/src/system_routes.rs` | 89 | 0 |
| `crates/kinevo-http/src/testing.rs` | 53 | 0 |
| `crates/kinevo-http/tests/http_core.rs` | 750 | 0 |
| `crates/kinevo-kernel/src/errors/codes.rs` | 1 | 0 |
| `crates/kinevo-kernel/src/errors/mod.rs` | 9 | 0 |
| `spec/decisions.md` | 3 | 0 |
| `spec/index.md` | 1 | 1 |
| `spec/standards/error-handling.md` | 1 | 1 |
| `spec/standards/open-source-stack.md` | 1 | 0 |
| `tasks/T-016-http-core.md` | 248 | 7 |
| `tasks/index.md` (generated) | 2 | 2 |
| `web/src/i18n/en.json` (generated catalog) | 246 | 0 |
| `web/src/i18n/id.json` (generated catalog) | 246 | 0 |### Deviations

- Added `FieldCode::UnknownField`, `DetailValue::OwnedStr`, and the `unknown_field` field-code registry entry because T-016's AC-6 requires that field error and AC-8 requires a dynamic query-name detail through the typed `AppError` path (D-60).
- Added `HttpState.max_body_bytes` as an injected operator setting because the body-limit layer needs `KINEVO_MAX_BODY_BYTES`, while the task's listed `HttpState` fields omit the configured limit (D-61).
- Panic logging omits the panic payload and error chain. SEC-04 and the privacy rules do not let the HTTP layer prove a panic payload is free of request content, so it logs only allow-listed `request_id`, `error_code`, and `event="panic"` (D-62).
- These necessary files were not all listed under T-016's *Files*; see D-60..D-62 and the corresponding Questions entries. `Cargo.lock` and the two generated catalogs are excluded from the budget. The implementation has 2,158 non-lockfile, non-catalog changed lines, exceeding the 450-line guidance; D-55 makes this a reported deviation, not a blocker.
- Indonesian catalog text is an implementation draft and still needs maintainer language review (M-L10N). Full title/detail wording is listed below.

### Implementer decisions

| ID | Choice and alternatives | Reason | Files changed | Reversal |
|---|---|---|---|---|
| D-60 | Add a typed field code and owned registry-approved string detail; alternative: special-case HTTP response serialization. Pending owner review. | Keeps errors in the shared `AppError` path and satisfies unknown-field and query-name contracts. | Kernel error types, error-handling registry, T-016 Report, decision log. | Revise API-04/T-016's wire contract and remove the additional types. |
| D-61 | Inject `max_body_bytes` into `HttpState`; alternative: access environment configuration inside the HTTP layer. Pending owner review. | Keeps configuration operator-controlled and HTTP code deterministic. | `crates/kinevo-http/src/state.rs`, decision log. | Move the configured limit to a separate extension and update constructor wiring. |
| D-62 | Log only allow-listed panic fields; alternative: log panic payload/error chain. Pending owner review. | Panic text can contain request data barred by SEC-04. | `crates/kinevo-http/src/problem.rs`, decision log. | Add a sanitizer contract that proves logged payloads cannot contain S2/S3 data. |

### Indonesian catalog wording for maintainer review

The following table lists all 122 Indonesian titles and their detail sentences from `web/src/i18n/id.json`; each detail is `Periksa permintaan lalu coba lagi.`

| Error code | Indonesian title | Indonesian detail |
|---|---|---|
| `unauthenticated` | Anda harus masuk | Periksa permintaan lalu coba lagi. |
| `forbidden` | Akses tidak diizinkan | Periksa permintaan lalu coba lagi. |
| `not_found` | Tidak ditemukan | Periksa permintaan lalu coba lagi. |
| `validation_failed` | Periksa kolom yang ditandai | Periksa permintaan lalu coba lagi. |
| `unknown_field` | Kolom tidak dikenal | Periksa permintaan lalu coba lagi. |
| `field_not_nullable` | Kolom ini tidak boleh kosong | Periksa permintaan lalu coba lagi. |
| `unsupported_media_type` | Jenis konten tidak didukung | Periksa permintaan lalu coba lagi. |
| `payload_too_large` | Permintaan terlalu besar | Periksa permintaan lalu coba lagi. |
| `cursor_invalid` | Kursor halaman tidak valid | Periksa permintaan lalu coba lagi. |
| `sort_invalid` | Urutan tidak valid | Periksa permintaan lalu coba lagi. |
| `query_param_unknown` | Parameter kueri tidak dikenal | Periksa permintaan lalu coba lagi. |
| `window_too_large` | Rentang tanggal terlalu lebar | Periksa permintaan lalu coba lagi. |
| `date_out_of_range` | Tanggal di luar rentang | Periksa permintaan lalu coba lagi. |
| `version_conflict` | Data berubah | Periksa permintaan lalu coba lagi. |
| `precondition_required` | Versi diperlukan | Periksa permintaan lalu coba lagi. |
| `idempotency_key_reuse` | Kunci permintaan sudah dipakai untuk permintaan lain | Periksa permintaan lalu coba lagi. |
| `idempotency_in_progress` | Permintaan masih diproses | Periksa permintaan lalu coba lagi. |
| `rate_limited` | Terlalu banyak permintaan | Periksa permintaan lalu coba lagi. |
| `internal_error` | Terjadi kesalahan | Server belum dapat memproses permintaan. |
| `service_unavailable` | Layanan sedang tidak tersedia | Server belum dapat memproses permintaan. |
| `module_disabled` | Fitur dinonaktifkan | Periksa permintaan lalu coba lagi. |
| `invalid_transition` | Perubahan tidak diizinkan dari status saat ini | Periksa permintaan lalu coba lagi. |
| `setup_required` | Penyiapan diperlukan | Periksa permintaan lalu coba lagi. |
| `setup_already_completed` | Penyiapan sudah selesai | Periksa permintaan lalu coba lagi. |
| `setup_token_invalid` | Tautan penyiapan tidak valid | Periksa permintaan lalu coba lagi. |
| `template_invalid` | Templat tidak valid | Periksa permintaan lalu coba lagi. |
| `module_core_locked` | Fitur ini tidak dapat dinonaktifkan | Periksa permintaan lalu coba lagi. |
| `module_dependency_unmet` | Aktifkan fitur yang diperlukan terlebih dahulu | Periksa permintaan lalu coba lagi. |
| `module_required_by` | Fitur ini masih diperlukan oleh fitur lain | Periksa permintaan lalu coba lagi. |
| `policy_unknown` | Pengaturan tidak dikenal | Periksa permintaan lalu coba lagi. |
| `policy_value_invalid` | Nilai tidak diizinkan | Periksa permintaan lalu coba lagi. |
| `policy_scope_invalid` | Pengaturan tidak dapat diubah pada tingkat ini | Periksa permintaan lalu coba lagi. |
| `policy_effective_in_past` | Waktu mulai tidak boleh di masa lalu | Periksa permintaan lalu coba lagi. |
| `policy_effective_order` | Waktu mulai harus setelah awal pengaturan saat ini | Periksa permintaan lalu coba lagi. |
| `secret_name_unknown` | Nama rahasia tidak dikenal | Periksa permintaan lalu coba lagi. |
| `not_dead` | Pengiriman belum gagal | Periksa permintaan lalu coba lagi. |
| `registration_closed` | Pendaftaran ditutup | Periksa permintaan lalu coba lagi. |
| `invite_invalid` | Undangan tidak valid | Periksa permintaan lalu coba lagi. |
| `email_taken` | Email sudah digunakan | Periksa permintaan lalu coba lagi. |
| `invalid_credentials` | Email atau kata sandi salah | Periksa permintaan lalu coba lagi. |
| `mfa_required` | Kode verifikasi diperlukan | Periksa permintaan lalu coba lagi. |
| `invalid_mfa_code` | Kode tidak valid | Periksa permintaan lalu coba lagi. |
| `current_password_incorrect` | Kata sandi saat ini salah | Periksa permintaan lalu coba lagi. |
| `totp_code_invalid` | Kode tidak valid | Periksa permintaan lalu coba lagi. |
| `account_locked` | Terlalu banyak percobaan | Periksa permintaan lalu coba lagi. |
| `account_disabled` | Akun dinonaktifkan | Periksa permintaan lalu coba lagi. |
| `refresh_reuse_detected` | Anda keluar demi keamanan | Periksa permintaan lalu coba lagi. |
| `csrf_check_failed` | Asal permintaan tidak diizinkan | Periksa permintaan lalu coba lagi. |
| `reset_token_invalid` | Tautan reset tidak valid | Periksa permintaan lalu coba lagi. |
| `totp_already_enabled` | Verifikasi dua langkah sudah aktif | Periksa permintaan lalu coba lagi. |
| `totp_required_by_policy` | Kebijakan mewajibkan verifikasi dua langkah | Periksa permintaan lalu coba lagi. |
| `export_in_progress` | Ekspor sedang berjalan | Periksa permintaan lalu coba lagi. |
| `export_not_ready` | Ekspor belum siap | Periksa permintaan lalu coba lagi. |
| `export_expired` | Ekspor sudah kedaluwarsa | Periksa permintaan lalu coba lagi. |
| `last_admin` | Setidaknya satu administrator harus tetap ada | Periksa permintaan lalu coba lagi. |
| `workspace_name_taken` | Nama ruang kerja sudah digunakan | Periksa permintaan lalu coba lagi. |
| `workspace_limit_reached` | Batas ruang kerja tercapai | Periksa permintaan lalu coba lagi. |
| `workspace_archived` | Ruang kerja diarsipkan | Periksa permintaan lalu coba lagi. |
| `workspace_is_default` | Ruang kerja utama tidak dapat dihapus | Periksa permintaan lalu coba lagi. |
| `workspace_not_empty` | Ruang kerja belum kosong | Periksa permintaan lalu coba lagi. |
| `goal_has_tasks` | Tujuan masih memiliki tugas | Periksa permintaan lalu coba lagi. |
| `goal_not_active` | Tujuan tidak aktif | Periksa permintaan lalu coba lagi. |
| `reorder_set_mismatch` | Urutan harus mencantumkan setiap item tepat satu kali | Periksa permintaan lalu coba lagi. |
| `milestone_after_goal` | Tanggal tonggak melewati tanggal tujuan | Periksa permintaan lalu coba lagi. |
| `task_goal_workspace_mismatch` | Tugas dan tujuan berada di ruang kerja yang berbeda | Periksa permintaan lalu coba lagi. |
| `task_milestone_goal_mismatch` | Tonggak milik tujuan lain | Periksa permintaan lalu coba lagi. |
| `milestone_requires_goal` | Pilih tujuan untuk menggunakan tonggak | Periksa permintaan lalu coba lagi. |
| `anchor_exists` | Anda sudah memiliki tugas jangkar | Periksa permintaan lalu coba lagi. |
| `task_has_running_session` | Tugas memiliki timer yang sedang berjalan | Periksa permintaan lalu coba lagi. |
| `subtask_limit_reached` | Terlalu banyak subtugas | Periksa permintaan lalu coba lagi. |
| `rrule_invalid` | Aturan pengulangan tidak valid | Periksa permintaan lalu coba lagi. |
| `override_requires_recurring` | Hanya acara berulang yang dapat digeser | Periksa permintaan lalu coba lagi. |
| `override_range_invalid` | Rentang tanggal tidak valid | Periksa permintaan lalu coba lagi. |
| `ics_too_large` | Berkas kalender terlalu besar | Periksa permintaan lalu coba lagi. |
| `ics_invalid` | Berkas kalender tidak valid | Periksa permintaan lalu coba lagi. |
| `ics_too_many_events` | Terlalu banyak acara dalam berkas | Periksa permintaan lalu coba lagi. |
| `scheduler_busy` | Pembaruan jadwal sedang berjalan | Periksa permintaan lalu coba lagi. |
| `no_eligible_tasks` | Tidak ada tugas yang dapat dijadwalkan | Periksa permintaan lalu coba lagi. |
| `schedule_version_conflict` | Jadwal berubah | Periksa permintaan lalu coba lagi. |
| `draft_infeasible` | Usulan jadwal tidak lagi sesuai | Periksa permintaan lalu coba lagi. |
| `draft_not_pending` | Usulan jadwal sudah ditangani | Periksa permintaan lalu coba lagi. |
| `task_ids_not_in_draft` | Tugas yang dipilih tidak ada dalam usulan | Periksa permintaan lalu coba lagi. |
| `placement_invalid` | Waktu tersebut tidak sesuai | Periksa permintaan lalu coba lagi. |
| `task_not_schedulable` | Status tugas tidak memungkinkan penjadwalan | Periksa permintaan lalu coba lagi. |
| `task_in_progress` | Tugas sedang dikerjakan | Periksa permintaan lalu coba lagi. |
| `session_already_running` | Timer sudah berjalan | Periksa permintaan lalu coba lagi. |
| `task_not_startable` | Tugas tidak dapat dimulai dalam status ini | Periksa permintaan lalu coba lagi. |
| `session_not_running` | Timer tidak sedang berjalan | Periksa permintaan lalu coba lagi. |
| `ended_at_invalid` | Waktu selesai tidak valid | Periksa permintaan lalu coba lagi. |
| `sync_protocol_unsupported` | Perbarui aplikasi untuk menyinkronkan | Periksa permintaan lalu coba lagi. |
| `sync_batch_too_large` | Terlalu banyak perubahan dalam satu permintaan | Periksa permintaan lalu coba lagi. |
| `sync_payload_too_large` | Perubahan terlalu besar | Periksa permintaan lalu coba lagi. |
| `operation_id_reused` | ID operasi sudah digunakan | Periksa permintaan lalu coba lagi. |
| `op_unsupported` | Jenis perubahan ini tidak didukung saat offline | Periksa permintaan lalu coba lagi. |
| `entity_id_taken` | ID item sudah digunakan | Periksa permintaan lalu coba lagi. |
| `dependency_failed` | Perubahan bergantung pada perubahan yang gagal | Periksa permintaan lalu coba lagi. |
| `ai_not_configured` | AI belum disiapkan | Periksa permintaan lalu coba lagi. |
| `ai_limit_reached` | Batas penggunaan AI tercapai | Periksa permintaan lalu coba lagi. |
| `ai_provider_unavailable` | Layanan AI tidak tersedia | Server belum dapat memproses permintaan. |
| `ai_provider_timeout` | Waktu layanan AI habis | Server belum dapat memproses permintaan. |
| `ai_output_invalid` | Jawaban AI tidak dapat digunakan | Periksa permintaan lalu coba lagi. |
| `ai_prompt_too_large` | Deskripsi terlalu panjang untuk AI | Periksa permintaan lalu coba lagi. |
| `ai_byok_disabled` | Penggunaan kunci AI sendiri dinonaktifkan | Periksa permintaan lalu coba lagi. |
| `ai_host_not_allowed` | Alamat tersebut tidak diizinkan | Periksa permintaan lalu coba lagi. |
| `proposal_not_pending` | Usulan sudah ditangani | Periksa permintaan lalu coba lagi. |
| `note_doc_invalid` | Isi catatan tidak valid | Periksa permintaan lalu coba lagi. |
| `note_too_large` | Catatan terlalu besar | Periksa permintaan lalu coba lagi. |
| `query_too_short` | Pencarian terlalu pendek | Periksa permintaan lalu coba lagi. |
| `query_too_long` | Pencarian terlalu panjang | Periksa permintaan lalu coba lagi. |
| `link_target_not_found` | Tujuan tautan tidak ditemukan | Periksa permintaan lalu coba lagi. |
| `link_limit_reached` | Terlalu banyak tautan | Periksa permintaan lalu coba lagi. |
| `canvas_scene_invalid` | Isi papan tidak valid | Periksa permintaan lalu coba lagi. |
| `canvas_too_large` | Papan terlalu besar | Periksa permintaan lalu coba lagi. |
| `canvas_file_invalid` | Gambar tidak diizinkan | Periksa permintaan lalu coba lagi. |
| `canvas_file_too_large` | Gambar terlalu besar | Periksa permintaan lalu coba lagi. |
| `canvas_files_limit` | Terlalu banyak gambar pada papan ini | Periksa permintaan lalu coba lagi. |
| `plan_unknown` | Paket tidak ditemukan | Periksa permintaan lalu coba lagi. |
| `billing_not_configured` | Penagihan belum disiapkan | Periksa permintaan lalu coba lagi. |
| `gateway_unavailable` | Layanan pembayaran tidak tersedia | Server belum dapat memproses permintaan. |
| `already_subscribed` | Sudah berlangganan | Periksa permintaan lalu coba lagi. |
| `signature_invalid` | Tanda tangan tidak valid | Periksa permintaan lalu coba lagi. |
| `gateway_unknown` | Penyedia pembayaran tidak dikenal | Periksa permintaan lalu coba lagi. |

### Spec issues noticed

- The field-level validation registry omitted `unknown_field` even though API-04 and T-016 AC-6 require it in `errors[].code`; added the registry entry and recorded D-60.
- T-016's requested panic payload logging conflicts with SEC-04's log allow-list and privacy boundary; followed SEC-04 under D-62.
- T-016's `HttpState` field list omits the configured body limit used by its body-limit layer; injected the setting under D-61.

### Not done / not verified

- Indonesian wording has not had maintainer review (M-L10N).
- No environment-limited checks were encountered for this task.
- No module authentication, rate limiting, idempotency, OpenAPI assembly, or SPA serving was added; those are explicitly out of scope for T-016.

### Follow-up suggestions

- Architect/maintainer review of D-60..D-62 and Indonesian catalog wording.

## Questions

- Q-1 (implementer, 2026-10-28): T-016's `HttpState` field list omits the operator-configured body ceiling required by the body-limit layer and `KINEVO_MAX_BODY_BYTES`.
  Resolved by implementer, pending architect review: inject `max_body_bytes` into `HttpState` (D-61).
  Options: (a) inject the integer in `HttpState`; (b) read environment configuration inside the HTTP layer.
  Recommendation: (a), because it keeps HTTP behavior deterministic and configuration outside the layer.
- Q-2 (implementer, 2026-10-28): T-016 requires dynamic `details.name` and `errors[].code = unknown_field`, but the kernel only exposed static string details and the field-code registry omitted `unknown_field`.
  Resolved by implementer, pending architect review: add typed `FieldCode::UnknownField`, owned registry-approved string details, and the matching registry entry (D-60).
  Options: (a) special-case these HTTP responses; (b) extend the typed error model and registry.
  Recommendation: (b), because the standard renderer remains the single error path.
- Q-3 (implementer, 2026-10-28): T-016 requests logging panic text/error chain, while SEC-04 limits logs to approved fields and panic payloads may contain request data.
  Resolved by implementer, pending architect review: log only `request_id`, `error_code`, and `event="panic"`; return an opaque 500 (D-62).
  Options: (a) log the payload; (b) log only safe structured fields.
  Recommendation: (b), because the payload cannot be proven free of user content.
