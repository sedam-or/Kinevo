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
- subtasks;
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

