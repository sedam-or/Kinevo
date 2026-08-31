# ADR-014 — Third-Party Adoption Strategy (Integration Modes & Boundaries)

### Status
Accepted — 2026-08-29. Authoritative for P28+ third-party work unless superseded
by a documented owner decision.

### Decision
Adopt the five-mode integration model from
`docs/roadmap/archive/planning-specs/KINEVO_THIRD_PARTY_ADOPTION_INTEGRATION_AND_RETENTION_UX_SPEC.md`:

1. **EMBED** — package/component inside Kinevo, wrapped behind a Kinevo boundary
   (Excalidraw, Tiptap, Uppy, Pic Smaller engine, Filament).
2. **HARVEST** — study and adapt patterns; no runtime introduced (Open SaaS —
   the Wasp/React/Node/Prisma stack is never introduced).
3. **REIMPLEMENT** — native implementation after studying an external concept
   (selected Lago billing semantics, selected OpenPanel aggregation semantics).
4. **ADAPTER + SERVICE** — external service stays separate; Kinevo owns the
   application port and adapter (Gotify, Lago, OpenPanel, Langfuse, GlitchTip).
5. **REFERENCE ONLY** — studied without becoming a dependency.

Locked invariants (No-Frankenstein rule, spec §60):
- Kinevo remains the single authority for identity, workspaces, goals,
  milestones, programs, tasks, notes, knowledge, canvases, schedules, progress,
  activity, AI proposal/entitlement/credit semantics, BYOK policy, subscription
  and entitlement meaning, notification semantics, privacy, export, deletion,
  product event definitions, and customer-facing UX.
- No adoption may create a second backend, source of truth, identity system,
  billing meaning, analytics event definition, notification domain, or storage
  authority without an explicit ADR.
- No new dependency without a `docs/third-party/adoption-matrix.md` row; no
  adoption without license re-check at the exact version
  (`docs/third-party/licenses.md`).
- Every external system needs an exit strategy (adapter contract, stored-data
  format, exportability, replacement difficulty, migration procedure).
- Development services start explicitly (Docker profiles), never silently —
  local Ollama MUST NOT auto-start merely because it is installed.

### Context
Post-P27 the product needs mature capabilities (uploads, image compression,
admin console, billing/metering, analytics, AI observability, notification
transport, error tracking) without becoming a dependency-driven Frankenstein.
The owner supplied a researched adoption specification (baseline: 11 projects
with license/mode decisions — see `docs/third-party/adoption-matrix.md`).
AGPL-licensed services (Lago, OpenPanel) are consumed as separate services via
adapters only; no source copying into the MIT Kinevo Core.

### Implementation mapping
- `docs/third-party/adoption-matrix.md` — required per-dependency record.
- `docs/third-party/licenses.md` / `attributions.md` — license/provenance ledger.
- `docs/architecture.md` — Third-Party Integration Architecture section
  (port/adapter pattern, failure policy, dev resource profiles).
- `docs/SRS.md` — FR-65 (upload/asset pipeline), FR-66 (notification provider
  abstraction), FR-67 (AI usage firewall), FR-68 (provider price catalog),
  FR-69 (product event taxonomy + analytics boundary).
- `docs/design.md` — third-party UI theming rule + retention UX system.
- `TASK.md` — PHASE 28 TPI foundation slice (P28-TPI-001..010).
- Implementation order (spec §56): Stage 1 embedded wins (Uppy, Pic Smaller,
  Filament) → Stage 2 adapter contracts → Stage 3 service integrations →
  Stage 4 pattern harvesting → Stage 5 UX integration.

### Alternatives rejected
- Building every capability in-house (violates No-Greenfield-If-Mature-
  Capability-Exists rule; slower, more fragile).
- Adopting external runtimes wholesale (Wasp for Open SaaS patterns; separate
  admin service instead of Filament) — creates a second backend.
- Copying AGPL source into Kinevo Core — license contamination.

### Consequences
Positive: mature capability without ownership erosion; every dependency has a
license record, failure policy, and exit strategy; development stays
explicit/profiled.
Negative: adapter surface must be maintained; adoption matrix + ledger entries
are mandatory overhead per dependency; AGPL boundaries need ongoing review.

---
