> Planned phase document (R0 rebaseline 2026-08-31). Authority: docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.
> Detailed microtasks are authored at phase activation — never fabricated in advance.

# P31 — Assets & Content Infrastructure
Status: PLANNED · Depends On: P30 gate

## Objective
Production asset pipeline: User → Uppy → validation → Pic Smaller (images) → Upload Adapter → AssetStorage port → object storage → Asset record → Notes/Canvas/Tasks. Never embed large binary data into Note/Canvas JSON.

## Scope / major workstreams
- Audit existing Attachment capability before inventing a new Asset model (extend, don't duplicate).
- Uppy embedded/adapter upload pipeline; Pic Smaller embedded/adapter compression; object storage provider abstraction (S3/R2-compatible path).
- Content reliability: upload, validation, image optimization, retry, partial failure, orphan cleanup, reference deletion, access authorization, offline compatibility, storage quotas, backup/recovery.

## Non-goals
No new user-facing asset features beyond pipeline integration.

## Gate
Production pipeline + Notes/Canvas/Task-attachment integration + object-storage recovery + license provenance (Uppy, Pic Smaller verified at exact versions) + failure tests + observability + accessibility + docs. STOP.

## Known open decisions
Storage provider for launch (local vs S3/R2); quota numbers (evidence-driven).

## P29 convergence refinements (2026-08-31)
- Asset provenance ledger: `docs/marketing/asset-provenance.md` — NO Stitch PNG may be committed; regenerate canonical diagrams (D01–D08, dia_01–07) as original SVGs here.
- Attachment contract: `docs/knowledge-layer.md` attachment model (no large binaries in Note/Canvas JSON).
