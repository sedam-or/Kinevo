# Kinevo — AI Architecture

### Position
AI is optional intelligence assistance. Core product correctness MUST NOT depend on an LLM.

### Provider abstraction
```text
AIOrchestrator
   ↓
AIProvider interface
   ├── OllamaProvider
   ├── ExternalProvider (optional)
   └── MockProvider
```

### AI roles
Allowed first-class roles:
- goal decomposition;
- milestone proposal;
- note summarization;
- task extraction;
- concept extraction;
- knowledge relation suggestion;
- canvas proposal;
- natural language explanation of existing deterministic results.

### Explicitly prohibited AI authority
AI MUST NOT directly own:
- final schedule placement;
- access control;
- task deletion;
- state transition bypass;
- database mutation without domain validation.

### Structured output
All AI mutation proposals MUST be schema-constrained and validated.

Example:
```json
{
  "type": "goal_breakdown_proposal",
  "goal_id": "uuid",
  "rationale": "why this decomposition (optional, decision summary)",
  "assumptions": ["what the plan assumes (optional)"],
  "inputs": ["which inputs were used — deadline, capacity (optional)"],
  "constraints": ["which constraints were honoured (optional)"],
  "risks": ["what could go wrong (optional)"],
  "milestones": [
    {
      "title": "Literature Review",
      "target_date": "2026-09-10",
      "estimated_minutes": 2400
    }
  ]
}
```
### Explainability boundary (TASK-P17-027)
AI proposals MUST be explainable at a high level — decision summary,
assumptions, inputs used, constraints honoured — and MUST never expose
chain-of-thought or private reasoning (privacy §14/§15.4, design.md §44).

### Context selection
AI context MUST be minimal and relevant. Do not send the entire database by default.

Context builder SHOULD enforce:
- ownership;
- relevance;
- size budget;
- field allowlist;
- sensitive field exclusion.

### Privacy
Local Ollama is preferred where privacy benefits justify operational complexity. External AI providers are opt-in capabilities and MUST have explicit configuration.

### Provider settings & secret handling (design.md §104 / TASK-P17-006)
`Settings → AI & Providers` is the control plane and the ONLY surface for
provider configuration. Documented behavior:
- Exposes provider (provider abstraction above), connection status, model,
  base URL, API key (masked), test connection, enable/disable, privacy note.
- API key rules: never stored in browser storage; never returned raw to any
  client payload; encrypted server-side; masked after save; replace/remove only.
- Ollama path requires no API key.
- Proposal review/edit/acceptance (TASK-P17-004): user edits of a pending
  goal-breakdown proposal revalidate through the SAME schema rules as AI
  output (PUT /api/v1/ai/proposals/{id}, decision becomes `edited`);
  acceptance still gates every domain mutation — nothing reaches milestones
  until the user accepts.
- Status derives from ONE source of truth (GET /api/v1/ai/status): Disabled /
  Not Configured / Configured / Testing / Connected / Unavailable / Degraded.
  The UI distinguishes *configured* ≠ *available* (TASK-P17-007).

Implementation (TASK-P17-006): configuration persists in a single-row
`ai_provider_configs` table (MVP is single-user; global control plane, not
user-scoped). The API key column is encrypted with the application key
(`Crypt`) and excluded from every serialization; GET/PUT `/api/v1/ai/config`
return only `has_api_key` plus a `…last4` hint, and POST
`/api/v1/ai/config/test` pings candidate settings before saving. A saved,
enabled, non-disabled configuration takes precedence over environment
defaults (`ConfigAiProviderResolver`).

### Development models
Development tooling MAY use a high-context reasoning model such as Qwythos 9B Q6_K or a coding-specialized model. This is not part of runtime requirements.

### Production local model profile
A small quantized model is preferred for low-resource VPS. Exact model is a deployment tuning decision, not a domain requirement.

### AI audit
Persist safe metadata, not private prompts or note contents, unless explicit audit retention requires otherwise.

### Human approval
Material changes MUST follow:
```text
Propose → Preview → Accept/Edit/Reject → Validate → Commit
```

### AI failure behavior
If AI is unavailable:
- core app remains functional;
- deterministic scheduling remains functional;
- manual goal/task/knowledge workflows remain available.

---

