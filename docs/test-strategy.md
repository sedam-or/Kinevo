# LIFESYNC OS — Test Strategy

### Quality philosophy
Testing proves behavior, not code volume.

### Test pyramid
```text
         E2E
       /     \
 Integration  
     /       \
   Unit / Domain
```

The largest suite should be pure domain/unit tests.

### Unit priorities
- slot calculation;
- 15-minute boundary;
- Sacred Anchor placement;
- priority/lock ranking;
- deadline urgency;
- goal/milestone progress;
- contribution math;
- program lifecycle;
- partial completion;
- end-of-day boundaries;
- recovery;
- capacity feedback;
- hard/soft scheduler separation;
- explainability reason codes;
- AI schema validation;
- knowledge link invariants;
- canvas version arithmetic.

### Integration priorities
- task + assignment transaction;
- goal + milestone persistence;
- scheduler run idempotency;
- recurring template generation;
- notification deduplication;
- offline queue synchronization;
- canvas save/version conflict;
- attachment atomicity;
- PDF import staging;
- AI provider timeout/failure.

### E2E golden flows
1. First setup.
2. Create goal with deadline.
3. Generate milestone breakdown.
4. Create program.
5. Quick Capture task.
6. Auto-schedule draft.
7. Execute task.
8. Partial completion.
9. Offline Quick Capture.
10. Reconnect and sync.
11. Create/edit Note.
12. Create/edit Canvas.
13. Canvas offline mutation.
14. Dynamic rescheduler.
15. EOD reconciliation.
16. Morning Recovery.

### Architecture spike gate
Canvas integration is accepted only when:
- load persists;
- change persists;
- offline queue works;
- reconnect sync works;
- stale version returns 409;
- binary attachment path works;
- Vue/React boundary remains isolated.

### Accessibility tests
- keyboard navigation;
- focus management;
- screen reader labels;
- non-color-only states;
- responsive Today view;
- drag keyboard fallback.

### Performance tests
Measure:
- Today first contentful response;
- schedule calculation duration;
- API P95;
- canvas load/save latency;
- offline queue throughput;
- AI latency separately from core application latency.

### Security tests
- unauthorized ownership access;
- invalid state transitions;
- IDOR attempts;
- file type spoofing;
- oversized upload;
- XSS in notes;
- CSRF/API auth behavior;
- AI prompt/context leakage;
- secret exposure in logs.

### Release gates
- all P0 acceptance criteria pass;
- no unresolved migration integrity issue;
- scheduler simulation passes;
- offline UAT passes;
- backup/restore smoke test passes;
- OpenAPI and implementation consistent;
- license ledger complete for new dependencies.

---

