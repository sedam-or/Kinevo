# ADR-0001 — Rebuild the backend in Rust from a new specification

- Status: **Accepted** (2026-10-25) — decision D-02; owner chose Rust in the decision round.
- Supersedes: legacy ADR-001 (modular monolith in Laravel) and ADR-007 (container deployment) for the runtime; keeps their intent.

## Context
The legacy implementation (`legacy-v0`) is a working Laravel 13 + Vue 3 product of ≈ 73k lines with strong tests, but the vision (one artifact for cloud, self-host and templates; per-install module switches; the same deterministic scheduler on server, web and Android; maintainable by one person plus AI implementers) is blocked by structural gaps: no module system, per-user ownership with hand-written filters, billing inside the core, a PHP runtime to ship, and no way to share the scheduler with clients (`spec/00-verdict.md` §4–§7).

## Decision
Rebuild the backend as a Rust modular monolith (axum, tokio, sqlx, PostgreSQL only) from `spec/`, not by line-by-line port. The legacy behaviour that matters (scheduler semantics, landscape resolution, offline ledger, AI pipeline) is re-specified and verified through golden fixtures derived from legacy tests. The scheduler is a separate pure crate (D-22).

## Alternatives considered
| Option | For | Against |
|---|---|---|
| A. Keep Laravel, refactor (module seam, split billing) | ships sooner; keeps 42k LOC | no shared scheduler for WASM/Kotlin (second implementation ⇒ second source of truth); PHP runtime packaging heavier; owner preference is Rust |
| B. Introduce Rust only for the scheduler | smallest risk | leaves the structural gaps; two runtimes forever |
| **C. Rebuild in Rust** | single static binary with embedded SPA; compiler-enforced module boundaries and secret/validated types; one scheduler for server/WASM/Kotlin; fits AI-assisted implementation | no new releases for months; slower CRUD; smaller contributor pool; second-system risk |

## Consequences
- Roadmap M1–M4 deliver the core loop first; the legacy tag stays runnable and documented.
- Parity is by behaviour fixtures, not code; differences are listed (`modules/scheduler.md` §12).
- Risks RM-R1, RM-R3, RM-R4 in `13-roadmap.md`.
- **Revisit if:** by the end of M3 the core loop is not at parity or velocity makes M4 unreachable in a reasonable time — then re-evaluate option A for the CRUD modules while keeping the Rust scheduler crate.
