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
  "milestones": [
    {
      "title": "Literature Review",
      "target_date": "2026-09-10",
      "estimated_minutes": 2400
    }
  ]
}
```

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

