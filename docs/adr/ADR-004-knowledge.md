# ADR-004 — Native Knowledge Layer with Headless Editor

### Decision
Build Kinevo-native Knowledge entities using a headless rich-text engine such as Tiptap. Do not embed a complete third-party notes application as the domain owner.

### Reasoning
Kinevo needs notes as contextual domain artifacts, not a separate general-purpose note application.

### Consequences
- simpler entity linking;
- one permission model;
- one search model;
- one offline model;
- editor engine can be replaced behind an adapter.

### Status
Accepted.

---

