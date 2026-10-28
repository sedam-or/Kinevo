# 07 — API conventions

Depends on: `02-architecture.md`, `06-domain-model.md`. Requirement prefix: `API`. Canonical value
forms are in `standards/contracts.md`; error codes in `standards/error-handling.md`; permission keys in
`standards/rbac.md`. The OpenAPI 3.1 document is **generated from the Rust handlers** (utoipa) and is
the contract of record for field-level shapes; this file fixes the rules that every endpoint obeys.

## 1. Basics

| ID | Rule |
|---|---|
| API-01 | Base path `/api/v1`. Breaking changes ship under `/api/v2`; additive changes (new optional request fields, new response fields, new endpoints, new enum values documented as extensible) stay in v1. |
| API-02 | JSON only: `Content-Type: application/json` (UTF-8) for requests with a body → else 415 `unsupported_media_type`. File uploads (ICS import preview, canvas files) use `multipart/form-data` or the raw media type named by the endpoint. |
| API-03 | Field names are `snake_case`; enum values are lower `snake_case`; ids are UUID strings; instants RFC 3339 UTC with `Z`; dates `YYYY-MM-DD`; durations are integers with a unit suffix (`_minutes`, `_seconds`); money `{ "amount_minor": int, "currency": "XXX" }`; percentages `…_bp` (basis points). |
| API-04 | Requests reject unknown fields (422 `unknown_field`, `fields[].path`). Clients must ignore unknown response fields and unknown enum values they do not understand. |
| API-05 | Methods: `GET` safe/idempotent; `POST` create or command (`POST /tasks/{id}/status`); `PATCH` partial update (omitted = unchanged, `null` = clear a nullable field; non-nullable `null` → 422 `field_not_nullable`); `PUT` replaces a singleton setting (`/policies/{key}`, `/admin/modules/{key}`); `DELETE` removes. |
| API-06 | `GET /api/v1/openapi.json` serves the generated document (no auth). CI fails if the committed `web/src/api/schema.d.ts` differs from the generated client types (`12-testing.md` TST-04). |
| API-07 | Health endpoints are outside the versioned API: `GET /livez`, `GET /readyz` (ARCH §10). |

## 2. Authentication

| ID | Rule |
|---|---|
| API-10 | Authenticated endpoints require `Authorization: Bearer <access_token>`. Missing/invalid/expired → 401 `unauthenticated` with `WWW-Authenticate: Bearer`. |
| API-11 | The refresh token lives only in cookie `__Secure-kinevo_rt` (`HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth`, see `standards/auth.md` §4). `POST /auth/refresh` and `/auth/logout` additionally require header `X-Kinevo-Client: web|android` and a matching `Origin` (CSRF defence in depth, SEC-09). The Android shell (`X-Kinevo-Client: android`) instead receives and sends the refresh token in the JSON body and stores it in Keystore-backed encrypted storage (D-37, `standards/auth.md` §Android). |
| API-12 | Access tokens are opaque-to-the-client, signed tokens whose claims are `sub` (account id), `exp`, `iat`, `jti`, `sid` (family id), `mfa` (bool). Claims never include email, role names or permissions; permissions are loaded server-side (cached, `standards/containers-and-caching.md`). |
| API-14 | HTTP 401 is reserved for authentication of the **session** (missing/invalid/expired access token, refresh failure) and for the login endpoint's own credential failures. Business re-verification on an authenticated endpoint (current password, TOTP code to change settings) answers 422 with a dedicated code (`current_password_incorrect`, `totp_code_invalid`) so clients never mistake it for an expired session (checked by a contract test over the error registry). |
| API-13 | Every response carries `X-Request-Id` (echoed from the request if it is a valid UUID, else generated). |

## 3. Errors (RFC 9457 problem details)

All non-2xx responses are `application/problem+json`:

```json
{
  "type": "urn:kinevo:error:version_conflict",
  "title": "The item changed",                     // localized from the catalog by Accept-Language
  "status": 409,
  "detail": "This task was changed elsewhere. Reload to see the latest version.",
  "code": "version_conflict",                      // stable machine code (registry)
  "request_id": "0192b8e1-…",
  "errors": [ { "path": "title", "code": "too_long", "details": { "max": 200 } } ],   // validation only
  "details": { "current_version": 7 }              // code-specific, documented in the registry
}
```

| ID | Rule |
|---|---|
| API-20 | `code` is from the registry (`standards/error-handling.md`), owned by `kinevo-kernel`, stable forever; `title`/`detail` are translated (en, id) and may change. Clients branch on `code` and `status`, never on text. |
| API-21 | Validation failures return 422 with one `errors[]` entry per field, all failures at once (not first-only). |
| API-22 | Internal errors return 500 `internal_error` with `request_id` only. No stack traces, SQL, file paths, secrets or user content (SEC-04). |
| API-23 | Authorization failures that would reveal existence use 404: a resource that exists but belongs to another account is `404 not_found`, identical to a non-existent id (SEC-07). 403 `forbidden` is used only when the caller is known to be allowed to know the resource exists but lacks the permission (e.g. non-admin calling an admin endpoint). |

## 4. Pagination, sorting, filtering

| ID | Rule |
|---|---|
| API-30 | List endpoints use cursor pagination: `?limit=` (default 50, max 200) and `?cursor=`. Response `{ "items": [...], "next_cursor": string|null }`. Cursors are opaque, HMAC-signed, bound to the endpoint and sort; tampered or reused on another endpoint/sort → 400 `cursor_invalid`. No page numbers, no total counts (use dedicated `…/count` endpoints where a screen needs a number). |
| API-31 | Sort: `?sort=field` or `?sort=-field` from an endpoint-specific allow-list, always with `id` as a final tiebreaker so order is total. Unknown sort → 422 `sort_invalid`. |
| API-32 | Filters are explicit query parameters per endpoint (documented in OpenAPI); unknown query parameters → 422 `query_param_unknown`. Dates in filters are account-local `YYYY-MM-DD`; instants are RFC 3339. |

## 5. Time

| ID | Rule |
|---|---|
| API-40 | The server never accepts or returns naive local timestamps. "A day" in any endpoint means a calendar day in the **account's timezone** (D-17); such endpoints take `date=YYYY-MM-DD`. |
| API-41 | Ranges are half-open `[from, to)`. Parameters are named `from`/`to` (instants) or `from_date`/`to_date` (inclusive-start, exclusive-end dates). |
| API-42 | Server time comes from the injected clock; `GET /api/v1/time` returns `{ "now": instant }` so clients can measure skew; clients must not trust their own clock for ordering of operations (`modules/sync.md`). |

## 6. Rate limiting

| ID | Rule |
|---|---|
| API-50 | Limits are policies (`ratelimit.*`, `04-policies.md`). Exceeding → 429 `rate_limited` with `Retry-After` (seconds) and `RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset` headers on all responses of limited routes. |
| API-51 | Keys: unauthenticated → client address; authenticated → account id. Behind a trusted reverse proxy, the client address is read from `X-Forwarded-For` only if the connecting peer is in `KINEVO_TRUSTED_PROXIES`. |

## 7. Optimistic concurrency

| ID | Rule |
|---|---|
| API-60 | Versioned resources expose `version` (integer). `PATCH`/`PUT`/`DELETE` on them require `If-Match: "<version>"`. Missing → 428 `precondition_required`. Mismatch → **409** `version_conflict` with `details.current_version` and `details.current` (the current resource representation containing no S2/S3 fields; ERR-03). |
| API-61 | `POST /…/commands` that depend on state (e.g. task status change) take `base_version` in the body and behave the same. |
| API-62 | A conflict never silently overwrites (no last-write-wins). Clients resolve by reloading and re-applying the user's intent (web: conflict banner, `standards/ux-states.md`). |

## 8. Idempotency (D-34)

| ID | Rule |
|---|---|
| API-70 | Clients send `Idempotency-Key: <uuid>` on every `POST` that creates or commands, and on `DELETE`. The server stores `(account, key)` with a hash of method+path+body and the response for `api.idempotency.ttl`. |
| API-71 | Same key + same request → the stored response is replayed with header `Idempotency-Replayed: true` (also for error responses that are deterministic; **not** for 5xx or 429, which are never stored). Same key + different request → 422 `idempotency_key_reuse`. Same key while the first is in flight → 409 `idempotency_in_progress` (client retries after `Retry-After: 1`). |
| API-72 | Idempotency keys are bound to the account; a key valid for one account is irrelevant to another. |
| API-73 | Offline operations use `operation_id` in the sync envelope instead (`modules/sync.md`); both mechanisms may not be mixed on one request. |

## 9. Localisation

`Accept-Language` selects `en` or `id` (default: account locale if authenticated, else instance default). Only
`title`, `detail` and enum **labels returned by catalog endpoints** are localised. Data and codes never are.

## 10. Module gating

An endpoint belonging to a disabled module returns 404 `module_disabled` (`details.module`), before any
authentication detail is revealed beyond "unauthenticated" (an unauthenticated caller gets 401 first).

## 11. Endpoint index (detail in `modules/*.md`)

| Area | Prefix | Module |
|---|---|---|
| Setup | `/setup/*` (pre-setup only) | `admin` |
| Instance, modules, policies | `/instance`, `/modules`, `/admin/modules/*`, `/policies/*` | `admin` |
| Auth & account | `/auth/*`, `/account/*` | `identity` |
| Workspaces | `/workspaces/*` | `workspaces` |
| Goals, milestones | `/goals/*`, `/milestones/*` | `goals` |
| Tasks | `/tasks/*`, `/subtasks/*` | `tasks` |
| Landscape | `/landscape/*` | `landscape` |
| Scheduler | `/schedule/*` | `scheduler` |
| Today, execution | `/today`, `/focus-sessions/*` | `execution` |
| Progress | `/progress/*` | `progress` |
| Sync | `/sync/*` | `sync` |
| AI | `/ai/*` | `ai` |
| Knowledge | `/notes/*` | `knowledge` |
| Canvas | `/canvases/*` | `canvas` |
| Billing | `/billing/*` | `billing` |
