> Planned phase document (R0 rebaseline 2026-08-31). Authority: docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.
> Detailed microtasks are authored at phase activation — never fabricated in advance.

# P32 — Analytics, AI Observability & Retention Instrumentation
Status: PLANNED · Depends On: P31 gate (taxonomy semantics come from P28 RET-007 + P29)

## Objective
Canonical product-event taxonomy (Kinevo-owned semantics) + provider instrumentation via adapters. Provider is transport/analytics infrastructure only.

## Scope / major workstreams
- Event taxonomy versioned (activation, Goal→Execution, schedule aha, KRS import aha, Today usage, completion/progress, review, first-week retention semantics per RET-007).
- OpenPanel via adapter/service boundary (ingestion/aggregation sink only).
- Langfuse-compatible AI observability adapter; AI billing truth remains the Kinevo AI Ledger (never Langfuse). Redact sensitive context; never leak full private notes/tasks.
- Instrumentation guarantees: event deduplication, privacy, Workspace context, no sensitive-content payloads, provider-failure degradation.

## Non-goals
No Wrapped feature build (later, evidence-driven); no quota decisions (P33).

## Gate
Versioned event semantics; provider abstractions; integrations (if adopted); privacy/redaction tests; provider-down degradation; AI ledger remains financial truth; dashboards/runbooks; licensing. STOP.

## Known open decisions
OpenPanel vs alternative adoption; Langfuse self-host vs cloud; Wrapped scope deferral.
