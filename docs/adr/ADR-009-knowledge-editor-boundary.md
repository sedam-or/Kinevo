# ADR-009 — Knowledge Editor Boundary (Tiptap)

> PROVENANCE: reconstructed 2026-08-31 (R0 documentation rebaseline). This decision was made and
> implemented before this file existed — original decision date unrecorded. Evidence: SRS §ADR
> register ("ADR-009 Tiptap-based Knowledge Editor boundary — Accepted"), SRS §10.1–10.3 Knowledge
> Layer, `docs/knowledge-layer.md`, TASK-066 era implementation. Reconstructed, not re-decided.

### Decision
Tiptap is the Knowledge editor boundary. Kinevo owns note identity, links, persistence, access,
attachments, and all domain semantics; Tiptap owns document editing mechanics only.

### Context
Knowledge needs rich structured editing (structured JSON canon) but the domain must not depend on
an editor framework. Notes are first-class domain entities with links, versions, ownership, and
offline semantics.

### Alternatives rejected
- building a custom editor (unbounded scope);
- letting the editor own persistence semantics (second source of truth);
- storing HTML as canonical (unstructured, unqueryable).

### Consequences
Positive: structured canonical JSON; replaceable editor behind the Kinevo boundary; domain-aware
references resolve through Kinevo APIs.
Negative: adapter layer must be maintained; editor upgrades are gated by the boundary contract.

### Status
Accepted (per SRS ADR register; implemented in `Domain\Notes` / editor adapter boundary).
