# ADR-010 — Canvas Adapter Boundary (Excalidraw)

> PROVENANCE: reconstructed 2026-08-31 (R0 documentation rebaseline). This decision was made and
> implemented before this file existed — original decision date unrecorded. Evidence: SRS §ADR
> register ("ADR-010 Excalidraw Canvas Adapter boundary — Accepted pending spike gate"),
> `docs/architecture.md` §Canvas boundary, `Domain\Canvas` + `CanvasAdapter` implementation
> (canvas file scoping, task/goal linkage, persistence). The spike gate is SATISFIED by the
> shipped adapter (canvas entity/versioning/persistence live in Kinevo; Excalidraw renders only).

### Decision
Excalidraw is the Canvas engine behind a Kinevo adapter (CanvasHost / CanvasAdapter → React
island). Kinevo owns the canvas entity, ownership, workspace scoping, links, persistence,
versioning, and offline semantics; Excalidraw owns visual editing only.

### Context
Freeform visual thinking requires a mature canvas engine; the domain requires structured
persistence (scene JSON as data, not truth) and access control.

### Alternatives rejected
- alternative engines (tldraw etc.) at decision time: maturity/licensing;
- canvas-as-image storage (no editability);
- Excalidraw as business-logic owner (violates external-engine boundary).

### Consequences
Positive: production-grade drawing UX; replaceable engine; domain rules never delegate to the
engine; scene persistence stays queryable/versioned.
Negative: React island inside a Vue SPA; adapter maintenance; version pinning discipline.

### Status
Accepted (spike gate satisfied by implemented adapter; per SRS ADR register).
