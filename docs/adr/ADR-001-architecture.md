# ADR-001 — Modular Monolith

### Decision
Adopt Laravel modular monolith.

### Context
LIFESYNC is single-user but domain-rich. Scheduling, goals, tasks, capacity, recovery, knowledge, and audit state have strong transactional coupling.

### Alternatives rejected
- microservices;
- serverless-only decomposition;
- event-bus-first architecture.

### Consequences
Positive:
- simpler deployment;
- easy local development;
- strong transaction boundaries;
- low operational complexity.

Negative:
- requires discipline around module boundaries;
- eventual extraction needs clean interfaces.

### Status
Accepted.

---

