# ADR-002 — Vue 3 + TypeScript + Inertia

### Decision
Use Vue 3 + TypeScript + Inertia + Vite + Pinia as primary frontend stack.

### Reasoning
- rapid development;
- excellent fit for interactive personal productivity UI;
- one deployable application;
- avoids duplicated API/frontend deployment complexity;
- compatible with a Laravel modular monolith.

### Exception
React is allowed inside a bounded Canvas integration island for Excalidraw.

### Status
Accepted.

---


---

### Amendment (2026-08-31, R0 truth patch)
Inertia.js was planned here but was never installed (`@inertiajs/*` absent from package.json).
The shipped frontend is a Vue 3 + TypeScript + Vite + Pinia SPA talking to the Laravel API with a
custom shell navigation (no Inertia, no vue-router). The decision's substance — Vue 3 + TS +
Vite + Pinia, one deployable application — stands; the Inertia-specific claim is superseded by
implementation. Full frontend/architecture convergence: P29.
