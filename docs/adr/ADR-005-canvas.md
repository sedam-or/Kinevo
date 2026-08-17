# ADR-005 — Excalidraw Behind a Canvas Adapter

### Decision
Use Excalidraw as the initial canvas engine behind a LIFESYNC adapter.

### Boundary
```text
Vue → Canvas Adapter → React Island → Excalidraw
```

### LIFESYNC owns
- canvas identity;
- domain links;
- persistence;
- versioning;
- offline sync;
- permissions;
- files;
- AI proposal integration.

### Excalidraw owns
- visual editing;
- canvas interaction;
- scene element mechanics.

### Status
Accepted, subject to architecture spike verification.

---

