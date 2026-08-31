# Kinevo — Content Design & Terminology

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Canonical user-facing language.
> Internal architecture vocabulary never leaks into product copy. Bilingual
> readiness (EN canonical; ID glossary below) — full localization is NOT P29
> scope. Claims that leave the product are governed by
> `docs/marketing/claims-registry.md`.

## 1. Canonical terminology

| Term | Definition (user-facing) | Never call it |
|---|---|---|
| Workspace | A named life area that scopes Goals/Programs/Tasks/Notes/Canvas while Today stays global | project, board, team |
| Today | Where your plan meets the clock — NOW/NEXT/Timeline | dashboard, home |
| Week | The 7-day planning surface | calendar |
| Month | The full-month grid | Calendar (deprecated nav label) |
| Goal | A meaningful outcome you're heading toward | project |
| Milestone | A verifiable step toward a goal | phase |
| Program | A repeating structure of work inside a goal | sprint |
| Task | A unit of executable work | item, todo |
| Hard Landscape | Fixed commitments Kinevo never schedules over | calendar sync, busy blocks |
| Permanent Shift | Moves a recurring series from a date forward — previewed, applied, history kept | edit series |
| One-Time Exception | Changes exactly one occurrence | skip, delete |
| Sync Now | On-demand re-plan: recalculates and proposes, never silently mutates | refresh, auto-schedule |
| Schedule needs review | Reality changed; your accepted plan should be re-checked | conflict warning |
| Sacred Anchor | Protected placement the scheduler works around | priority lock |
| Knowledge | Notes + links that keep thinking connected to execution | wiki, docs |
| Note | A captured piece of context | document |
| Canvas | A visual board for planning and synthesis | whiteboard, Excalidraw (never name the vendor) |
| Progress | Where things actually moved | analytics (deprecated surface label) |
| Review | The reflective look at what happened and what's next | report |
| AI proposal | A suggestion you review, edit, accept, or reject | AI output, auto-plan |

## 2. Voice & tone

Calm, direct, honest. Sentences carry one idea. No exclamation marks in product
UI. Failure copy explains + preserves + offers recovery ("Your saved canvas data
is still safe" + Retry). Never blame the user ("This task was missed" → recovery,
not guilt). No gamification pressure (no streaks, no confetti language).

## 3. Empty-state pattern (RET-002)

Every meaningful empty state answers, in order: **WHERE am I** (surface name) →
**WHY is it empty** (one honest sentence) → **WHAT should I do next** (the
canonical action, as a real CTA) → **WHAT happens after** (one sentence). Quiet
states stay quiet — do not overfill intentionally blank surfaces. Example
(Goals): "No goals yet." + "Goals are the start of the roadmap… you approve every
step before anything is scheduled."

## 4. Education pattern (P28-010)

FeatureHelp = one contextual explanation per ambiguous concept (what it is / why
it matters / what to do next), dismissible ("Got it" persists locally). Registry:
`features/registry.ts`. Never: onboarding tours, modal mazes, coach-mark spam,
tooltip on every icon.

## 5. Failure & recovery copy

- Data-load failure: "{What failed}. Try again" (InlineError) — no stack traces,
  no error codes in user copy.
- Offline: "You are offline. Changes are saved locally and will sync when the
  connection returns."
- Conflict: "This {entity} was changed elsewhere; reload to reconcile."
- AI gate: route to Settings with the provider-mode explanation; never a raw
  provider error.
- No sensitive content (notes/tasks/prompts) ever appears in failure text.

## 6. Bilingual readiness (EN → ID glossary)

| EN (canonical) | ID |
|---|---|
| Workspace | Ruang Kerja |
| Today | Hari Ini |
| Week | Minggu |
| Month | Bulan |
| Goal | Tujuan |
| Milestone | Tonggak Capaian |
| Program | Program |
| Task | Tugas |
| Hard Landscape | Lanskap Kaku |
| Permanent Shift | Pergeseran Permanen |
| One-Time Exception | Pengecualian Sekali |
| Sync Now | Sinkronkan Sekarang |
| Schedule needs review | Jadwal perlu ditinjau |
| Sacred Anchor | Jangkar Sakral |
| Knowledge | Pengetahuan |
| Note | Catatan |
| Canvas | Kanvas |
| Progress | Kemajuan |
| Review | Tinjauan |
| AI proposal | Usulan AI |
| Turn intentions into execution. | Ubah niat menjadi eksekusi. |

Rules: glossary terms are the only acceptable translations; product remains EN
until a localization decision; marketing may lead with ID (Indonesia-first).
