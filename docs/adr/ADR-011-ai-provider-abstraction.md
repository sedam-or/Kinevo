# ADR-011 — AI Provider Abstraction (Ollama Optional)

> PROVENANCE: reconstructed 2026-08-31 (R0 documentation rebaseline). This decision was made and
> implemented before this file existed — original decision date unrecorded. Evidence: SRS §ADR
> register ("ADR-011 AI Provider abstraction; Ollama optional — Accepted"), SRS FR-60/§13.4–13.6/
§17.8, `docs/ai-architecture.md` (provider tree, roles, failure behavior), `docs/deployment.md`
§Ollama development adapter, TASK-105 era implementation, docker-compose ai profile.

### Decision
All LLM access passes through a Kinevo-owned provider abstraction. Ollama is an OPTIONAL local
provider (development/self-host profile); hosted providers are pluggable drivers. The application
MUST remain operational when the provider is unavailable (FR-60). AI output is untrusted input:
schema validation → domain validation → human approval where material.

### Context
Single-tenant self-hosting (MIT Core) requires a no-cloud AI path; the SaaS requires hosted
providers with metering. Provider-specific semantics must never leak into domain logic; the AI
ledger (not the provider) is billing truth.

### Alternatives rejected
- hard-wiring one hosted vendor (self-host impossible);
- Ollama as the only path (no SaaS scale);
- direct client→provider calls (key leakage, no metering, untrusted output unbudgeted).

### Consequences
Positive: provider swap without domain change; offline/local development AI; explicit failure
behavior (AI_PROVIDER_UNAVAILABLE); metering point for credits/cost.
Negative: abstraction maintenance; per-provider capability drift must be managed (capability
protocol matrix).

### Status
Accepted (per SRS ADR register; implemented — provider selection explicit, never implicit).
