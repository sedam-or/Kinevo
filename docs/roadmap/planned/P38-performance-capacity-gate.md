> Planned phase document (R0 rebaseline 2026-08-31). Authority: docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.
> Detailed microtasks are authored at phase activation — never fabricated in advance.

# P38 — Production Performance & Capacity Gate
Status: PLANNED · GATE PHASE · Depends On: P37 gate

## Objective
Benchmark the REAL target architecture (no VPS folklore); document the capacity envelope.

## Scope / major workstreams
- Measure: CPU, RAM, DB, FrankenPHP workers, queue, scheduler, storage, AI gateway, asset pipeline, analytics/observability sidecars, billing load.
- Load profiles: launch, 100 active users, 1k, 10k where meaningful. Separate Core server capacity from managed external services/object storage/analytics/observability.
- User-perceived performance P50/P95/P99: Today, Week, Month, Goals, Tasks, Knowledge search, scheduler draft, Sync Now, offline reconcile, AI proposal, billing views (+ frontend performance, mobile where relevant).

## Gate
Documented capacity envelope; known bottlenecks; resource budget; benchmark evidence; no unacceptable memory leak; FrankenPHP soak green; database query review; acceptable backup window; AI cost model updated; scaling/runbook strategy. STOP.

## Known open decisions
Launch infra sizing; horizontal-scaling trigger thresholds.

## P29 convergence refinements (2026-08-31)
- Benchmark posture inherited from `docs/architecture.md` §3 (BENCHMARK_REQUIRED until measured).
