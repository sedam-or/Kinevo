# Kinevo — Knowledge Layer

### Purpose
Provide a first-class knowledge system connected to goals, milestones, programs, tasks, and canvases without turning Kinevo into an unbounded general-purpose workspace clone.

### Components
- Notes.
- Knowledge links.
- Canvas.
- Attachments.
- Search.
- Optional AI extraction/summarization.

### Note architecture
```text
Vue
 ↓
Tiptap adapter
 ↓
Editor document JSON
 ↓
Laravel Note application service
 ↓
PostgreSQL
```

### Canonical document representation
Tiptap/ProseMirror JSON is canonical. Markdown and plain text are derived representations for import/export/search use cases.

### Note fields
Recommended:
- id;
- user_id;
- title;
- document_json;
- markdown_cache;
- plain_text_cache;
- created_at;
- updated_at;
- version.

### Semantic nodes
Optional custom nodes:
- Task reference;
- Goal reference;
- Milestone reference;
- Program reference;
- Canvas reference;
- Callout;
- Evidence/reference block.

### Link model
Use a generic `knowledge_links` relation:
```text
source_type
source_id
target_type
target_id
link_type
```
All relations are ownership-scoped. Sources may be Notes or Canvases; targets may
be Goals, Milestones, Programs, Tasks, Canvases, or Notes.

### Search
MVP search SHOULD support:
- title;
- plain text;
- linked entity metadata;
- tags where introduced.

PostgreSQL full-text search is preferred before introducing an external search engine.

### Attachment model
Large binary files MUST use object storage. Database stores metadata and ownership.

### Knowledge deletion
Deletion semantics MUST distinguish:
- soft deletion/archival;
- permanent deletion;
- link cleanup;
- attachment cleanup.

### Knowledge-to-Task flow
```text
Note
 ↓ AI/Manual extraction
Task proposal
 ↓ User approval
Task
 ↓ Scheduler
Assignment
```

### Knowledge-to-Canvas flow
```text
Note
 ↓ user action or AI proposal
Canvas
 ↓
linked to same Goal/Milestone/Program context
```

---

