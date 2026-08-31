> Planned phase document (R0 rebaseline 2026-08-31). Authority: docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.
> Detailed microtasks are authored at phase activation — never fabricated in advance.

# P33 — Commercial Runtime & FinOps
Status: PLANNED · Depends On: P32 gate

## Objective
Commercial truth stays in Kinevo backend: payment ≠ entitlement; subscription ≠ gateway state.

## Scope / major workstreams
- Midtrans production readiness: signed webhook, idempotency, out-of-order events, reconciliation, pending/active/past_due/cancel/resume/upgrade/downgrade/refund/chargeback, cross-device, provider outage. Resolve the paid→paid upgrade contradiction explicitly.
- Lago (if adopted): ADAPTER + SERVICE; Lago never owns entitlements; Midtrans remains gateway unless explicitly changed.
- AI FinOps simulator BEFORE quota lock: per capability → provider/model/tokens (input/output/cached)/cost/customer allowance/Kinevo cost/margin; percentiles P50/P75/P90/P95/P99 + abuse scenario; FX + buffer.
- BYOK boundaries proven: eligible per final entitlement policy; BYOK usage never consumes hosted allowance.

## Non-goals
No annual plan or default trial (locked open); no team-tier Power features.

## Gate
Production Midtrans behavior proven; upgrade path resolved; entitlements authoritative; AI ledger complete; FinOps simulation complete; quotas decided OR explicit launch-safe bounds approved by owner; billing reconciliation + failure drills; commercial docs consistent. STOP.

## Known open decisions
Final hosted AI allowances (evidence-gated); exact Power entitlements; Lago adoption yes/no.

## P29 convergence refinements (2026-08-31)
- Open decisions inventory moved to `docs/product/commercial-model.md` §5 (AI quotas, Power parameters, top-up, annual, proration/grace).
- Config 20/300/1000 remains DEPRECATED BASELINE — never publishable (claims registry #10).
