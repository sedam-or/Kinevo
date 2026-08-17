# ADR-006 — Provider-Abstraction AI

### Decision
Use an internal `AIProvider` interface. Ollama is an optional local provider.

### Development
Development may use Qwythos 9B Q6_K or other coding/reasoning models.

### Production
Choose a small local model appropriate to VPS resources or disable local AI. Exact runtime model is operational, not domain-critical.

### Safety
All AI outputs are proposals and untrusted inputs until validated.

### Status
Accepted.

---

