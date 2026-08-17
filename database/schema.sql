# Database Schema Snapshot

### Authority
This file is a generated/current schema snapshot. Laravel migrations are the historical migration authority.

### Core domains
The schema MUST represent at minimum:
- profiles;
- goals;
- milestones;
- programs;
- contributions;
- tasks;
- subtasks;# LIFESYNC OS — Software Requirements Specification

### Source
This file MUST be populated with the complete contents of **LIFESYNC OS SRS v2.0.0** already finalized as the project’s requirements baseline.

### Rule
Do not manually rewrite a shortened version here. Copy the authoritative `LIFESYNC_OS_SRS_v2.0.0.md` content verbatim into `docs/SRS.md`, then update only through controlled SRS version changes.

### Required metadata
- SRS Version: 2.0.0 at initial repository bootstrap.
- Status: Implementable Baseline / Single Source of Truth.
- Product: LIFESYNC OS.
- Requirement owner: Product/System Owner.
- Primary user: single-user owner.

### Required major sections present in v2.0.0
- Document Control.
- Introduction and product vision.
- Functional requirements FR-01 through FR-64.
- Non-functional requirements NFR-01 through NFR-15.
- System architecture.
- Domain model.
- Data model.
- API contract.
- Offline synchronization specification.
- Knowledge layer specification.
- Canvas/Excalidraw integration specification.
- Adaptive productivity requirements.
- AI architecture.
- UI/UX requirements.
- Security/privacy.
- Operations/deployment.
- Test strategy.
- Traceability.
- Resolved ADR baseline.
- Implementation roadmap.
- Change management.

### SRS mutation rule
Any approved requirement change MUST update:
- SRS version;
- impacted FR/NFR;
- domain/data/API contracts;
- acceptance criteria;
- traceability;
- UAT/test coverage;
- migration/backward compatibility plan where applicable.

---


- task_assignments;
- hard_landscape_events;
- schedule_overrides;
- task_templates;
- activity_logs;
- pause_events;
- notifications;
- attachments;
- capacity_snapshots;
- scheduler_runs;
- offline_operation_ledger;
- notes;
- knowledge_links;
- canvases;
- canvas_documents;
- canvas_files;
- adaptive_context_observations;
- AI audit/proposal records where required.

### Schema rules
- UUID primary identifiers where the architecture requires them.
- All user-owned business data includes `user_id` or has an ownership path enforceable by invariant.
- timestamps use timezone-aware representation (`timestamptz` in PostgreSQL).
- version columns exist for optimistic concurrency where required.
- indexes follow query patterns defined in SRS.
- foreign-key cascades are explicit and reviewed; do not use broad cascade deletes blindly.

### Index principles
At minimum index user/date, user/status, foreign keys, activity time, notification due time, scheduler run time, and knowledge link lookups.

### Generated rule
Do not hand-edit schema snapshot independently of migrations.

---

