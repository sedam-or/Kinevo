# ADR-0009 — PostgreSQL as job queue and event outbox; no Redis

- Status: **Accepted** (2026-10-25) — D-13.

## Context
Background work is modest: weekly drafts, retention pruning, exports, AI proposal expiry, outbox dispatch. A one-binary, one-database deployment is a core goal (self-hosters, cheap cloud).

## Decision
Use PostgreSQL tables `jobs` and `outbox_events/outbox_deliveries` claimed with `FOR UPDATE SKIP LOCKED`; recurring jobs are materialised by a tick with unique keys; caches and rate limits are in-process (`standards/containers-and-caching.md`). The outbox gives at-least-once, idempotent cross-module reactions written in the same transaction as the state change.

## Alternatives
| Option | For | Against |
|---|---|---|
| Redis (queues, cache, rate limit) | proven, fast | another service to run, secure, back up |
| In-memory queue only | trivial | loses work on restart |
| External broker (NATS/RabbitMQ) | decoupling | overkill; operational burden |

## Consequences
- Single-node assumptions (limiter, grace cache) are documented; multi-node needs an RFC.
- Throughput ceiling is bounded by PostgreSQL; acceptable for personal-scale workloads (assumption; measure in benchmarks).
- **Revisit if** job latency or database load from polling becomes material at cloud scale, or horizontal scaling is required.
