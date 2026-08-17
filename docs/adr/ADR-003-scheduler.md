# ADR-003 — Deterministic Two-Layer Scheduler

### Decision
Implement scheduler as:
1. hard constraint engine;
2. soft optimization/ranking engine.

### Reasoning
Deterministic behavior is required for correctness, testability, explanation, and user trust.

### AI position
AI may propose semantic task/milestone decomposition but cannot become the scheduling authority in the baseline release.

### Status
Accepted.

---

