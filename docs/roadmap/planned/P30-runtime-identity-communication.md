> Planned phase document (R0 rebaseline 2026-08-31). Authority: docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.
> Detailed microtasks are authored at phase activation — never fabricated in advance.

# P30 — Runtime, Identity & Communication Foundation
Status: PLANNED · Depends On: P29 gate

## Objective
Three foundations: (A) Laravel Octane + FrankenPHP runtime (target LOCKED; benefit must be proven), (B) production identity, (C) transactional email via provider abstraction.

## Scope / major workstreams
- FrankenPHP+Octane migration: benchmark before/after (cold/warm, P50/P95/P99, memory baseline + sustained, CPU, throughput, key endpoints); audit long-lived-worker hazards (static state, singleton/user/Workspace/locale leakage, DB connections, HTTP clients, AI provider instances, buffers, memory growth). CRITICAL SECURITY TEST: User/Workspace A state NEVER leaks into B's request. Evidence-driven worker count/max requests; rollback path.
- Identity baseline: email verification, password reset, security notifications, session controls, recovery, rate limits, abuse controls (no enterprise overbuild).
- Email: Kinevo-owned EmailProvider abstraction (Resend first driver; provider semantics never leak into domain). Transactional scope: verification, reset, security notice, subscription/payment notice, critical account change, important schedule notice where policy permits. Track sent/delivered/bounced/complained/failed — provider dashboard is NOT truth.

## Non-goals
No new product features; no billing changes.

## Gate
FrankenPHP functional parity + worker isolation + memory soak + benchmark evidence + rollback drill; identity E2E; email abstraction + verification/reset working + failure/degradation tested; docs/runbooks synced. STOP.

## Known open decisions
Worker topology; email provider fallback ordering; session control depth.
