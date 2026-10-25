---
id: T-554
title: Add the optional /metrics endpoint with the S1-only metric set
milestone: M11
kind: feature
status: todo
depends_on: [T-550]
requirements: [OPS-07, SEC-04, SEC-AC-45, CA-11]
branch: m11-t554-metrics-endpoint
budget_lines: 340
assignee: ""
---

# T-554: Add the optional /metrics endpoint with the S1-only metric set

## Goal

Operators can scrape counters and histograms that describe health and load without any personal or content data.

## Context

> **OPS-07** (`spec/11-operations.md`): JSON logs per SEC-04; `/metrics` is optional and exposes **S1 only** counters/histograms: `http_requests_total{route,status}`, `http_request_duration_seconds`, `jobs_total{kind,state}`, `jobs_queue_age_seconds`, `outbox_pending`, `outbox_dead`, `db_pool_in_use`, `scheduler_runs_total{trigger,status}`, `ai_runs_total{status}`, `sync_ops_total{status}`. **No account ids, emails, titles or user-supplied strings as labels**; `route` is the matched route template, never the raw path. Acceptance: a metrics contract test.
> **Exposure**: disabled by default; enabled with `KINEVO_METRICS_ENABLED=true` (added to the config schema, T-039/T-454); when enabled it is served on the main listener at `/metrics` only to requests presenting the bearer token from the secret file `KINEVO_METRICS_TOKEN_FILE` (constant-time compare), or on a separate listener `KINEVO_METRICS_ADDR` (default off) without auth for private networks. Unauthenticated requests get 404 when metrics are off and 401 when on without the token.
> **Library**: `metrics` + `metrics-exporter-prometheus` (add to the stack map in the same PR, MIT/Apache-2.0) or a small hand-written text exposition if the crates conflict with the dependency policy; the contract test fixes the output format. Counters for modules that are disabled are absent. Instrumentation points: the HTTP layer (T-016), job runner (T-013), outbox (T-014), pool (T-010), and the scheduler, AI and sync modules (increment through a tiny `Metrics` port in `kinevo-ports` with a no-op default so modules never depend on the exporter).

## Scope

**In:**
- `Metrics` port with a no-op fake, exporter wiring in `kinevo-app`, instrumentation in the listed layers (minimal edits), config variables, contract and label-hygiene tests, OpenAPI exclusion (the route is not part of the public API contract).

**Out:**
- Dashboards, alert rules (operator concern, Phase 5 docs).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/metrics.rs` | create | port and fake |
| `crates/kinevo-app/src/metrics.rs` | create | exporter and auth |
| `crates/kinevo-http/src/**, crates/kinevo-db/src/**, crates/kinevo-mod-{scheduler,ai,sync}/src/**` | modify | minimal instrumentation calls |
| `crates/kinevo-app/tests/metrics_contract.rs` | create | AC-1…AC-6 |

## Interfaces

```text
GET /metrics   (Authorization: Bearer <token from KINEVO_METRICS_TOKEN_FILE>) → text exposition, S1 only
```

## Business rules

1. Metrics are off by default.
2. Labels come from closed sets (route templates, enum states).
3. Disabled modules emit no series.

## Security

- Constant-time token compare; the token is read from a file and never logged.
- A label-hygiene test seeds a canary email/title and asserts it never appears in the exposition (SEC-AC-45 family).

## Implementation notes

- High-cardinality protection: unknown routes collapse to the label `unmatched`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** metrics enabled **When** scraped with the token **Then** exactly the documented metric names appear | `metrics_contract::ops_07_metric_names_match_spec` |
| AC-2 | **Given** a request with a canary in path, query and body **When** scraped **Then** the canary appears nowhere | `metrics_contract::ops_07_no_user_data_in_labels` |
| AC-3 | **Given** metrics off **When** `/metrics` is requested **Then** 404; on without a token 401 | `metrics_contract::exposure_rules` |
| AC-4 | **Given** an unmatched path **When** requested **Then** the route label is `unmatched` | `metrics_contract::route_label_is_template` |
| AC-5 | **Given** a module switched off **When** scraped **Then** its series are absent | `metrics_contract::disabled_module_has_no_series` |
| AC-6 | **Given** the modules **When** they record metrics **Then** they use the `Metrics` port only (architecture test) | `metrics_contract::modules_do_not_depend_on_exporter` |

## Tests

```
cargo nextest run -p kinevo-app --test metrics_contract --features kinevo-db/testing
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
