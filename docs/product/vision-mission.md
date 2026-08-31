# Kinevo — Vision, Mission & Positioning

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Product-strategy authority under
> `product-constitution.md`. One file by design (epic allows a single coherent
> file over fragmentation). Claims used here are registered in
> `docs/marketing/claims-registry.md` before any external publication.

## 1. Vision

A world where people can turn meaningful intentions into consistent progress
without surrendering control of their time, context, or data.

## 2. Mission

Build a personal operating system that connects what people want to achieve,
what reality allows, what they know, and what they should do next.

## 3. Category

Personal productivity operating system — the reconciliation layer between
intention (goals), reality (fixed commitments), and context (knowledge), executed
through deterministic scheduling. Not a todo app, not a calendar, not a team PM
platform.

## 4. Ideal customer profile (ICP)

**Primary: serious individual users** who hold long-horizon intentions while
living around fixed commitments:

- knowledge workers with deep-work intentions and meeting-dense calendars
- builders shipping personal projects around day jobs
- students and researchers reconciling class schedules with research goals
- creators and professionals running personal systems of record

Anti-profiles (through v1): teams needing RBAC/shared workspaces; enterprises
needing administration; casual list-keepers. Gen-Z influence informs brand
expression (editorial constructivism), never the product definition. Indonesia is
the first commercial market; the architecture does not prevent broader adoption.

## 5. Jobs to be done

1. When I set a meaningful goal, help me break it into executable structure
   without losing ownership of the plan.
2. When reality changes (schedule, commitments), respect it and re-plan honestly
   around what my time actually allows.
3. When I think, keep that thinking connected to the work it serves.
4. When I open the product, show me the one next move that matters now.
5. When I look back, show me what actually moved — and what to adjust.

## 6. Differentiation (evidence-backed)

1. **Intention → Execution** — goals decompose into approved, scheduled,
   executable work (browser-proven golden journey A; AI proposes, user decides).
2. **Reality-aware planning** — Hard Landscape as global personal reality, KRS/ICS
   import, effective schedule with recurrence + UNTIL, Permanent Shift,
   One-Time Exception, locked placements, Sacred Anchors (ADR-015).
3. **Deterministic + explainable scheduling** — same inputs, same plan; capacity
   based on real behavior; every placement explainable (ADR-015/016, FR-63).
4. **Context continuity** — Goal ↔ Task ↔ Note ↔ Canvas ↔ Progress knowledge
   links keep thinking attached to execution.
5. **Ownership** — MIT Core, self-hostable, BYOK; Cloud is optional convenience
   ("Own the software. Or let Kinevo host it for you.").

## 7. Anti-positioning / alternatives

- vs todo apps (Todoist et al.): they list intentions; Kinevo executes them inside
  real capacity and reality.
- vs calendars: they record events; Kinevo reconciles events with intentions and
  proposes honest plans.
- vs AI schedulers that act autonomously: Kinevo's AI proposes; the user decides;
  the deterministic engine owns placement.
- vs team PM platforms (Asana/Notion): single-user depth over team breadth.

## 8. Brand promise

Your plans should survive contact with your life. Kinevo keeps your intentions,
your reality, and your thinking in one system — and shows you the next move.

## 9. Activation & retention model

- **Activation (aha moments):** first goal → AI breakdown accepted (intention aha);
  KRS import confirmed (reality aha); first Today completion loop (execution aha).
- **Retention loops:** daily Today loop (execution), weekly draft review
  (planning), reflection (review), recovery (missed tasks are recovered, not
  punished).
- **North Star:** WGPU — Weekly Goal Progressing Users (`product-constitution.md`
  §11). Event semantics: `docs/retention-events.md`. Instrumentation: P32.
- Success metrics are defined, not yet instrumented; no market statistics are
  claimed without evidence.
