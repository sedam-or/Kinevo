---
id: T-016
title: Build the kinevo-http core (route table, request id, problem+json, headers, health, extractors)
milestone: M1
kind: feature
status: in_progress
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

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget (the two catalogue files are excluded from the count).
- [ ] The Indonesian catalogue wording is listed in the Report for maintainer review.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
