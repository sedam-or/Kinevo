---
id: T-301
title: Implement the provider adapters, the SSRF-safe HTTP client and the BYOK settings endpoints
milestone: M6
kind: feature
status: todo
depends_on: [T-300]
requirements: [AI-FR-11, AI-FR-09, SEC-15, SEC-AC-30, SEC-AC-34, CA-10]
branch: m6-t301-ai-providers-and-ssrf-guard
budget_lines: 420
assignee: ""
---

# T-301: Implement the provider adapters, the SSRF-safe HTTP client and the BYOK settings endpoints

## Goal

The server can talk to an OpenAI-compatible or Ollama endpoint safely, users can bring their own key without it ever leaking, and private addresses are refused even under DNS rebinding.

## Context

> **Providers** (`spec/modules/ai.md` §2): `openai_compatible` → `POST {base_url}/chat/completions`, `Authorization: Bearer <key>`, `response_format: {type: "json_object"}`; `ollama` → `POST {base_url}/api/chat`, no auth, `format: "json"`, `stream: false`; `disabled` → every call `ai_not_configured`. Temperature is the fixed technical constant `0.2` (stack map §AI). Resolution order: account BYOK (if policy `ai.allow_user_keys`) → instance provider (`ai.provider.kind/base_url/model` + secret `ai.api_key`) → none.
> **HTTP client rules**: `reqwest` with rustls, `ai.request.timeout`, **redirects disabled**, response body capped at 1 MiB (streamed), TLS verification on; for **account** URLs the resolved IP must not be loopback/private/link-local/metadata (169.254.0.0/16, fc00::/7, ::1, 127/8, 10/8, 172.16/12, 192.168/16, 100.64/10, 0.0.0.0, IPv4-mapped IPv6) unless `ai.byok.allow_private_hosts`; implement a custom `reqwest` DNS resolver that resolves, filters and **pins** the validated address so a second resolution cannot rebind (SEC-AC-30). Instance URLs may be loopback http for Ollama only (the policy validation of T-033).
> **Endpoints** (§3): `GET /ai/provider` (200 `{kind, base_url, model, key_last4}` or 204); `PUT /ai/provider {kind, base_url, model, api_key?}` (200 masked; `ai_byok_disabled` 403, `ai_host_not_allowed` 422, validation); `DELETE /ai/provider` (204, also deletes the secret); `POST /ai/provider/test` (200 `{ok, latency_ms}` or `ai_provider_unavailable`/`ai_provider_timeout`; rate-limited with `ratelimit.ai_per_minute`); `GET /ai/status` (200 `{module_enabled, configured, source, kind?, model?, limits:{runs_today, runs_per_day?, allowance_remaining?}}`).
> **AI-FR-11** the key is stored as the account secret `ai.api_key` through `SecretStore` (AEAD, D-40), shown masked as `••••` + last 4, never returned, never logged, never in audit, and sent only to the configured host over TLS (or loopback http for Ollama). **SEC-AC-34** a scan test asserts the key never appears in responses, logs or audit rows. The provider call is never made inside a database transaction (§7).
> **Port**: `AiProvider` trait in the module (`complete(system, user) -> ProviderReply {text, latency_ms}`), with adapters and a deterministic fake for tests; calls are made with the shared `reqwest` client built in `kinevo-app` (injected).

## Scope

**In:**
- `infra/provider/{openai,ollama,fake}.rs`, `infra/http_guard.rs` (resolver, IP classification, size cap), BYOK repository and use cases, handlers for the five endpoints, OpenAPI regeneration, a local test server (axum on 127.0.0.1) used as the fake provider in integration tests.

**Out:**
- Breakdown use cases: T-302.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-ai/src/infra/{provider,http_guard,byok}/**` | create |  |
| `crates/kinevo-mod-ai/src/{app,http}/provider_settings.rs, status.rs` | create |  |
| `crates/kinevo-mod-ai/tests/{providers,ssrf,byok_api,status_api}.rs` | create | AC-1…AC-10 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
#[async_trait] pub trait AiProvider: Send + Sync { async fn complete(&self, system: &str, user: &str) -> Result<ProviderReply, ProviderError>; }
pub fn classify_ip(ip: IpAddr) -> IpClass;   // Public | Private | Loopback | LinkLocal | Metadata | Reserved
```

## Business rules

1. Account URLs are re-validated on every request (connect to the validated address).
2. A redirect response is an error, never followed.
3. A failed provider call does not consume the run count (T-302).

## Security

- SEC-AC-30: `http://169.254.169.254/` and hostnames resolving to private IPs → 422 `ai_host_not_allowed`; a second resolution to a private IP is refused at connect.
- The API key is `Secret<String>` end to end and appears in no log line.
- `key_last4` is the only key-derived value ever returned.

## Implementation notes

- Test the resolver with an injected fake DNS (trait) so no external DNS is used.
- The fake provider server can be told to return redirects, huge bodies, slow responses and invalid JSON.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the fake OpenAI-compatible server **When** called **Then** the request has bearer auth, `json_object` format, temperature 0.2 and the reply is returned | `providers::openai_request_shape` |
| AC-2 | **Given** the fake Ollama server **When** called **Then** `format: json`, `stream: false`, no auth header | `providers::ollama_request_shape` |
| AC-3 | **Given** a redirect, a 5 MB body and a slow response **When** called **Then** error, capped at 1 MiB, and timeout error respectively | `providers::redirect_size_and_timeout_rules` |
| AC-4 | **Given** private, loopback, link-local, metadata and mapped addresses **When** classified **Then** none is `Public` | `ssrf::sec_ac_30_ip_classification_table` |
| AC-5 | **Given** a hostname that first resolves public then private **When** connecting **Then** the pinned address is used or the connect is refused | `ssrf::sec_ac_30_dns_rebinding_refused` |
| AC-6 | **Given** BYOK disabled by policy **When** `PUT /ai/provider` **Then** 403 `ai_byok_disabled` | `byok_api::ai_byok_disabled` |
| AC-7 | **Given** a saved key **When** reading the provider, logs and audit **Then** only `key_last4` appears | `byok_api::sec_ac_34_key_never_leaks` |
| AC-8 | **Given** account, instance and no provider **When** status is read **Then** `source` is account, instance and none respectively | `status_api::resolution_order` |
| AC-9 | **Given** `DELETE /ai/provider` **When** done **Then** the secret is deleted too | `byok_api::delete_removes_secret` |
| AC-10 | **Given** many test calls **When** over the limit **Then** 429 `rate_limited` | `byok_api::provider_test_rate_limited` |

## Tests

```
cargo nextest run -p kinevo-mod-ai --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
