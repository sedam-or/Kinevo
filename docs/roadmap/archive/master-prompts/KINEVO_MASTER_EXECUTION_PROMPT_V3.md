> ARCHIVED 2026-08-31 (R0): superseded by the canonical master execution program —
> `docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md`. Preserved verbatim as owner-issued
> historical evidence. NOT an execution authority.

# KINEVO MASTER EXECUTION PROMPT V3
# P28 CLOSURE → PRODUCT CONVERGENCE → PRODUCTION → RELEASE CANDIDATE

> Owner-issued umbrella execution authority (2026-08-31). Supersedes
> `KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md` for P28 closure → P39 RC execution
> (the superseded file is retained as historical planning evidence). Stored verbatim.

MODE:
MASTER PROGRAM ORCHESTRATION

PROJECT:
Kinevo

REPOSITORY:
https://github.com/sedam-or/Kinevo

EXECUTION PHILOSOPHY:
QUALITY OVER SPEED

PRIMARY PRINCIPLE:

Kinevo optimizes for correctness, coherence, recoverability,
security, accessibility, observability, maintainability,
and user trust before release speed.

========================================================
0. MASTER EXECUTION CONTRACT
========================================================

This document is the umbrella execution authority for the next
development era of Kinevo.

It covers:

P28 closure
P29 Product & Architecture Convergence
P30 Runtime, Identity & Communication
P31 Assets & Content Infrastructure
P32 Analytics, AI Observability & Retention Instrumentation
P33 Commercial Runtime & FinOps
P34 Repository Boundary & Distribution
P35 Production Operations & Reliability
P36 Android Production
P37 Production Security & Privacy Gate
P38 Production Performance & Capacity Gate
P39 Release Candidate

IMPORTANT:

This master prompt contains the complete roadmap.

However:

DO NOT execute every phase blindly in one uncontrolled pass.

DEFAULT:

AUTO_CONTINUE_PHASES = FALSE

Execute the CURRENT authorized phase.

At the end of each phase:

1. run its complete verification gate;
2. produce its final report;
3. update roadmap truth;
4. create a coherent checkpoint;
5. STOP.

Only continue to the next phase when explicitly authorized by the owner.

Within one phase, bounded tasks MAY be executed sequentially by the
same agent if every internal gate remains green.

========================================================
1. CURRENT VERIFIED STARTING POINT
========================================================

Expected current repository state from the latest completed epic:

HEAD:
945e333 or descendant

Working tree:
CLEAN

VERIFY THIS.

Do not assume it remains true.

The following stabilization epics have already completed:

- Effective Schedule ADR-015
- canonical recurrence resolution
- UNTIL recurrence correction
- Today / Week / Month effective landscape integration
- scheduler/rescheduler effective landscape integration
- Permanent Shift
- One-Time Exception
- schedule assignment history
- locked-task producer
- Effective Schedule E2E

- Scheduler Trigger ADR-016
- weekly draft lifecycle
- Schedule Sync Now
- reality-change schedule impact
- persistent schedule drafts
- run locking
- Sacred Anchor producer
- scheduler trigger E2E

- Offline Reconciliation ADR-017
- offline capability matrix
- server operation ledger
- POST /sync/reconcile
- operation idempotency
- replay protection
- optimistic conflict handling
- web IndexedDB MutationQueue integration
- reconnect/drain behavior
- conflict UX
- offline E2E

The following previously established blockers are expected RESOLVED:

BLOCKER-ES-01
BLOCKER-ES-02
BLOCKER-ES-03
BLOCKER-ES-04
BLOCKER-ES-05
BLOCKER-SCHED-01
BLOCKER-OFFLINE-01

VERIFY BEFORE TRUSTING.

========================================================
2. SOURCE-OF-TRUTH HIERARCHY
========================================================

During execution, authority must be explicit.

LEGAL / SECURITY HARD CONSTRAINTS
        ↓
PRODUCT CONSTITUTION
        ↓
SRS / REQUIREMENTS
        ↓
DOMAIN MODEL / ARCHITECTURE
        ↓
UX / INFORMATION ARCHITECTURE / DESIGN
        ↓
COMMERCIAL POLICY / FINOPS POLICY
        ↓
ADR / INTEGRATION SPECIFICATIONS
        ↓
ROADMAP / TASK TRACKING
        ↓
IMPLEMENTATION + TEST EVIDENCE

IMPORTANT:

During P29 convergence, CURRENT repository reality must also be
consulted whenever existing documentation conflicts with implementation.

Code is implementation evidence.

Code is NOT automatically product intent.

Old documentation is historical evidence.

Old documentation is NOT automatically current authority.

========================================================
3. DOCUMENTATION RECONSTRUCTION AUTHORIZATION
========================================================

THIS IS EXPLICIT AUTHORIZATION TO RESTRUCTURE KINEVO DOCUMENTATION.

The agent is NOT required to preserve the current documentation layout.

The agent MAY and, where required for coherence, MUST:

- REWRITE existing documents
- SPLIT oversized documents
- MERGE duplicate documents
- MOVE documents
- RENAME documents
- ARCHIVE obsolete documents
- DELETE obsolete redundant documents
- CREATE missing canonical documents
- UPDATE every internal reference
- UPDATE AGENTS.md references
- UPDATE README references
- UPDATE TASK references
- UPDATE CI/documentation checks
- REMOVE stale wording
- REMOVE superseded requirements from canonical documents
- PRESERVE historical decisions where historically important

The goal is NOT:

"add another document."

The goal is:

"leave exactly one clear canonical location for each type of truth."

========================================================
4. DOCUMENT DELETE / ARCHIVE RULE
========================================================

Do NOT keep obsolete documents merely because they already exist.

Use these dispositions:

KEEP
REWRITE
REPLACE
MERGE
MOVE
ARCHIVE
DELETE

DELETE is explicitly allowed when ALL are true:

- content is obsolete or duplicated;
- useful canonical information has been migrated;
- there is no legal/license requirement to preserve it;
- it is not an ADR/history artifact that should remain;
- no live reference depends on it;
- document-link validation remains green.

ARCHIVE rather than DELETE when:

- historical reasoning remains useful;
- document represents a former product phase;
- release/research history should remain traceable.

NEVER rewrite historical audit snapshots to make them look current.

NEVER delete accepted ADRs simply because a newer ADR supersedes them.

Mark ADR supersession explicitly.

========================================================
5. FROZEN HISTORICAL ARTIFACTS
========================================================

The dated implementation audit artifacts are historical evidence.

Do not rewrite them.

Future reality audits receive new dated files.

Likewise, accepted ADRs remain historical decision records.

If superseded:

add explicit supersession metadata.

Do not erase history.

========================================================
6. TASK.MD RECONSTRUCTION — MANDATORY
========================================================

TASK.md is currently too large and has accumulated phase history,
mapping drift, historical evidence, and future planning.

P29 MUST RECONSTRUCT IT.

DO NOT merely append P29-P39 to the bottom of the existing giant file.

Target role of ROOT TASK.md:

TASK.md becomes a SMALL CONTROL PLANE.

It should contain approximately:

# Kinevo Execution Control Plane

- current product baseline
- current active phase
- current phase completion state
- next phase
- global blockers
- global release gates
- task-ID conventions
- dependency conventions
- links to detailed roadmap files
- links to archive
- latest checkpoint/evidence references

Detailed task bodies MUST move out.

Target structure:

docs/
  roadmap/
    README.md
    roadmap.md
    rebaseline-2026-08.md

    active/
      P28-product-experience-closure.md
      P29-product-architecture-convergence.md

    planned/
      P30-runtime-identity-communication.md
      P31-assets-content-infrastructure.md
      P32-analytics-ai-observability.md
      P33-commercial-runtime-finops.md
      P34-repository-boundary-distribution.md
      P35-production-operations-reliability.md
      P36-android-production.md
      P37-security-privacy-gate.md
      P38-performance-capacity-gate.md
      P39-release-candidate.md

    archive/
      P01-...
      P02-...
      ...
      completed P28 predecessor records

Exact filenames may adapt to repository conventions.

========================================================
7. TASK ID IMMUTABILITY
========================================================

Published task IDs MUST NOT be recycled.

If an old task was:

P28-013

it remains P28-013.

If a task is moved to another document:

its ID remains unchanged.

If a task is superseded:

mark:

SUPERSEDED BY <ID>

Do not silently rename historical task IDs.

For old future phases that were planned but never started:

renumbering/rebaselining is allowed ONLY through an explicit mapping:

OLD PHASE/TASK
→
NEW PHASE/TASK

Record this in:

docs/roadmap/rebaseline-2026-08.md

========================================================
8. TARGET DOCUMENTATION ARCHITECTURE
========================================================

P29 must converge toward a structure similar to:

docs/

  product/
    product-constitution.md
    vision-mission.md
    positioning.md
    product-model.md
    workspace-model.md
    product-metrics.md

  requirements/
    SRS.md
    requirements-traceability.md

  architecture/
    architecture.md
    domain-model.md
    runtime-architecture.md
    scheduling.md
    offline-sync.md
    security-architecture.md
    data-ownership.md

  ux/
    information-architecture.md
    design-system.md
    interaction-states.md
    content-design.md
    motion.md
    accessibility.md
    stitch-reference.md
    stitch-convergence-matrix.md

  commercial/
    commercial-policy.md
    pricing-policy.md
    subscription-entitlements.md
    ai-finops.md
    unit-economics.md

  engineering/
    testing-strategy.md
    development.md
    release-process.md
    observability.md
    backup-recovery.md

  integrations/
    email.md
    billing.md
    analytics.md
    ai-observability.md
    notifications.md
    assets-storage.md

  third-party/
    adoption-matrix.md
    licenses.md
    attributions.md
    provenance.md

  adr/
    ...

  roadmap/
    ...

  audit/
    ...

  archive/
    ...

This is a TARGET hierarchy, not permission to blindly create empty files.

Inspect current documentation first.

Reuse/move/rewrite existing useful documents.

Avoid file proliferation.

========================================================
9. ROOT DOCUMENT HYGIENE
========================================================

Inspect root-level planning and scratch documents.

Known historical examples may include:

KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md
revisi-finance.md
third-party planning specifications
temporary implementation prompts

For every such file decide:

MOVE TO CANONICAL DOC
ARCHIVE
DELETE

Do not leave temporary planning documents at repository root after
convergence unless they are intentionally permanent governance files.

README.md remains the public repository entry point.

AGENTS.md remains agent/development governance.

LICENSE remains repository licensing authority.

========================================================
10. PRODUCT IDENTITY — LOCKED DIRECTION
========================================================

Kinevo is:

a workspace-scoped personal operating system that reconciles
intention, reality, and context into executable action.

Core conceptual model:

                         KINEVO
                       WORKSPACE
                           │
           ┌───────────────┼───────────────┐
           │               │               │
      INTENTION         REALITY         CONTEXT
           │               │               │
         Goals       Hard Landscape      Knowledge
                           │           Notes / Canvas
                     Import & Sync
           │               │               │
           └───────────────┼───────────────┘
                           ↓
                       STRUCTURE
                           ↓
                        SCHEDULE
                           ↓
                          TODAY
                           ↓
                       EXECUTION
                           ↓
                        PROGRESS
                           ↓
                         REVIEW
                           ↓
                          ADAPT

Tagline:

Kinevo — Turn intentions into execution.

Do not replace this casually.

========================================================
11. WORKSPACE SEMANTICS — LOCKED
========================================================

Workspace is the contextual organization boundary.

Goals:
workspace-scoped

Programs:
workspace-scoped

Tasks:
workspace-scoped

Notes:
workspace-scoped

Canvas:
workspace-scoped

Hard Landscape:
GLOBAL personal reality

Today:
cross-workspace by default

Week:
cross-workspace by default

Month:
cross-workspace by default

Workspace:
filter/context for Today/Week/Month

Progress:
global + workspace-filtered perspectives

Review:
global + workspace-filtered perspectives

Notifications:
global, carrying Workspace context where relevant

AI contextual retrieval:
must respect Workspace isolation where Workspace-scoped context is used.

Do NOT add workspace_id to global Hard Landscape merely for consistency.

========================================================
12. AI AUTHORITY — LOCKED
========================================================

AI:

PROPOSES.

USER:

DECIDES.

AI must never become authoritative scheduler logic.

AI must never silently mutate authoritative domain state.

Canonical pattern:

context
→ AI proposal
→ user review
→ accept/edit/reject
→ authoritative mutation

========================================================
13. COMMERCIAL MODEL — LOCKED / OPEN BOUNDARIES
========================================================

Plans:

Free
Pro
Power

Current launch-price hypothesis:

Free:
Rp0

Pro:
Rp49.900 / month

Power:
Rp89.900 / month

Treat price values as launch hypotheses, not eternal product truth.

No annual plan is locked yet.

No default trial is locked.

Power must represent:

capacity
depth
history
intelligence
convenience/personalization

NOT:

team features disguised as a higher individual tier.

Exact Power entitlements remain evidence-driven.

========================================================
14. AI ECONOMICS
========================================================

Hosted AI allowances are NOT production locked.

Current config placeholder values must not become production policy by
accident.

Required FinOps model:

capability
→ model/provider
→ input tokens
→ output tokens
→ cached tokens
→ provider cost
→ customer allowance usage
→ Kinevo cost
→ margin impact

P33 must finalize quotas only after simulation.

Required percentiles:

P50
P75
P90
P95
P99
abuse scenario

BYOK:

Pro / Power eligible according to final entitlement policy.

BYOK provider usage must not consume Kinevo hosted allowance.

========================================================
15. OPEN SOURCE / CLOUD MODEL
========================================================

Kinevo Core:

public
MIT
genuinely self-hostable

Kinevo Cloud:

private SaaS operational/commercial layer

Kinevo Site:

public website/marketing surface

Target eventual repositories:

sedam-or/Kinevo
kinevo-cloud
kinevo-site

DO NOT split repositories before P34.

========================================================
16. LICENSING POLICY
========================================================

Kinevo Core remains MIT.

Do NOT replace the primary LICENSE simply because Kinevo also has SaaS.

P29/P35 must maintain:

THIRD_PARTY_NOTICES.md where required
TRADEMARKS.md if owner adopts trademark policy
third-party provenance
attribution
exact dependency/license evidence

Third-party code/service integration must be classified:

EMBED
HARVEST
REIMPLEMENT
ADAPTER + SERVICE
REFERENCE ONLY
REJECT

Do not casually copy AGPL implementation internals into MIT Core.

License uncertainty becomes a production gate.

========================================================
17. THIRD-PARTY ADOPTION PRINCIPLE
========================================================

Reuse as much as possible.

Couple as little as possible.

Harvest capability, not repositories.

Every production third-party integration must pass:

ownership
contract
license
failure/degradation
data/privacy
observability
exit/replacement

Known target directions include:

Excalidraw
Tiptap
Pic Smaller
Uppy
Filament
OpenPanel
Lago
Gotify
Langfuse
GlitchTip
Open SaaS pattern harvesting where useful

Verify exact current versions/licenses before adoption.

========================================================
18. DESIGN AUTHORITY
========================================================

Existing visual direction:

PRODUCT APPLICATION:
Kinevo Tactile Editorial

MARKETING:
Kinevo Editorial Constructivism

DNA:

Swiss grid
+
refined neo-brutalist tactility
+
constructivist composition
+
technical information design
+
controlled Gen-Z energy

Product UI should remain much calmer than marketing.

Avoid:

gradient-heavy generic SaaS
glassmorphism
blur soup
card soup
excessive pills
decorative bounce
confetti
3D gimmicks
one giant magazine grid for an entire long page

========================================================
19. STITCH MCP POLICY
========================================================

STITCH_PROJECT_ID:

<INSERT_STITCH_PROJECT_ID_HERE>

The Stitch project may be shared through MCP.

STITCH IS NOT PRODUCT AUTHORITY.

STITCH is:

VISUAL REFERENCE
DESIGN EXPLORATION
DESIGN EVIDENCE

Stitch MUST NOT define:

business rules
pricing truth
domain semantics
scheduler behavior
Workspace ownership
API behavior
entitlements
security guarantees

========================================================
20. WHEN STITCH MAY BE CONNECTED
========================================================

P28:
DO NOT use Stitch as implementation authority.

P29 early product/SRS/domain convergence:
DO NOT use Stitch to make product decisions.

P29 Information Architecture:
Stitch MCP may be connected READ-ONLY for comparison.

P29 Design Convergence:
Stitch MCP SHOULD be actively read if available.

Required workflow:

canonical product truth
+
canonical SRS
+
canonical architecture
+
canonical information architecture
        ↓
READ STITCH PROJECT THROUGH MCP
        ↓
inventory screens/frames
        ↓
classify each:
APPROVED
APPROVED_WITH_REFINEMENT
OUTDATED
CONFLICTS_WITH_PRODUCT
REJECT
        ↓
extract useful visual grammar
        ↓
write canonical repository design docs

Do NOT directly convert random Stitch output into production code.

After canonical design docs are locked:

implementation agents may use:

canonical design docs
+
approved Stitch frames

If they conflict:

canonical repository design documentation wins.

========================================================
21. STITCH DESIGN PROVENANCE
========================================================

Create:

docs/ux/stitch-reference.md

Record:

project ID
review date
status
approved screens/frames
rejected screens/frames
reason
canonical docs generated from review

Never commit MCP/API credentials.

A project identifier may be stored if it is not secret.

========================================================
22. MARKETING DESIGN INTENSITY
========================================================

Product application:

calm
focused
clear
fast

Marketing:

high-expression editorial constructivism

Wrapped:

highest expressive intensity

Landing:

vertical scrolling manifesto.

Each section may use editorial composition.

The WHOLE PAGE must not become one giant magazine layout.

========================================================
23. P28 — PRODUCT EXPERIENCE CLOSURE
========================================================

STATUS:

ACTIVE FIRST PHASE.

Before work:

re-audit TASK.md and current repo.

Do not trust old count if implementation changed since last report.

Expected remaining items include:

P28-010
P28-011
P28-013
RET-002
RET-005
RET-006
RET-007
RET-008
RET-013
P28-014 gate

Verify exact reality.

========================================================
24. P28-010 — FEATURE EXPLANATION
========================================================

Complete missing explanation subjects identified by prior audit.

Expected previously missing coverage:

Workspace
Knowledge
Canvas
AI provider modes

Do not create intrusive onboarding tours.

Use contextual education.

Each explanation must answer where appropriate:

what is this?
why should I care?
what should I do next?

Verify existing Goal/Analytics behavior is not duplicated.

========================================================
25. P28-011 — GLOBAL STATE MATRIX
========================================================

Create the canonical user-facing state matrix.

Cover meaningful entities/surfaces against relevant states:

loading
empty
success
partial
offline
stale
conflict
failed
permission/entitlement blocked where applicable

Do not mechanically build every Cartesian combination if impossible.

Document required behavior and implement missing high-value states.

========================================================
26. RETENTION UX CLOSURE
========================================================

Resolve:

RET-002
contextual empty-state refinement

RET-005
AI Breakdown Aha Moment

RET-006
First Session Journey

RET-007
First Week Retention Event semantics

RET-008
Progress Feedback

Important:

RET-007 semantic taxonomy belongs in P28.

Provider transport/instrumentation belongs to P32.

Do not create a new phase dependency cycle.

========================================================
27. KNOWN BROWSER REGRESSION TRIAGE
========================================================

Before final P28-013 evidence, investigate current browser failures.

Previously observed examples included:

p28-ux-audit goals empty-state
theme mobile
scheduler S1 harness/seed prerequisite

VERIFY whether they still exist.

Classify:

REAL PRODUCT REGRESSION
TEST HARNESS DEFECT
STALE TEST
ENVIRONMENT ISSUE

Fix legitimate defects.

Do not delete meaningful assertions merely to get green.

========================================================
28. P28-013 — FINAL GOLDEN JOURNEYS
========================================================

Run final evidence ONLY after remaining P28 experience work is complete.

Required product journeys:

A — GOAL FIRST

signup
→ workspace
→ goal
→ AI breakdown
→ approve
→ schedule
→ Today
→ complete
→ progress

B — REALITY FIRST

signup
→ workspace
→ KRS import
→ parse
→ review
→ confirm
→ recurring Hard Landscape
→ future Week
→ future Today
→ work scheduled around reality

C — PERMANENT SHIFT

recurring Hard Landscape
→ Permanent Shift
→ preview
→ Apply
→ effective future schedule changes correctly

D — ONE-TIME EXCEPTION

one occurrence
→ exception
→ preview
→ Apply
→ exactly one occurrence changes

E — KNOWLEDGE CONTINUITY

task
→ note
→ knowledge context
→ Canvas/Goal linkage

F — REFLECTION

complete work
→ Progress
→ Review
→ next action

Also include regression coverage for:

scheduler Sync Now
weekly draft
locked task
offline reconnect
offline replay
offline conflict

========================================================
29. P28 BROWSER MATRIX
========================================================

Final P28 evidence requires:

Chromium
Firefox
WebKit

Do not claim full P28 browser completion from Chromium alone.

Record exact:

command
engine
pass
fail
skip
duration
environment

========================================================
30. RET-013 — FAILURE E2E
========================================================

Create and execute retention-critical failure journeys.

Cover at least:

AI unavailable
network failure
offline queued mutation
offline conflict
stale schedule proposal
no scheduling slot
billing/entitlement degradation if relevant to P28 experience
empty first-session conditions

Failures must remain understandable and recoverable.

========================================================
31. P28-014 — PRODUCT EXPERIENCE BASELINE GATE
========================================================

P28-014 is NOT a production launch gate.

It is:

PRODUCT EXPERIENCE BASELINE GATE

Gate requires:

all P28 required tasks complete
all P0/P1 experience blockers resolved
golden journeys green
3 browser engines green or explicitly justified gate exception approved
accessibility evidence green
offline/scheduler core journeys green
state matrix coherent
no known correctness defect in core loop

When green:

checkpoint
tag
archive P28 detailed phase document
enter P29.

========================================================
32. P29 — PRODUCT & ARCHITECTURE CONVERGENCE
========================================================

P29 is NOT another feature phase.

P29 rebuilds Kinevo's source of truth around the now-verified product.

This phase is explicitly authorized to perform major documentation
reconstruction.

NO broad feature development unless required for a tiny factual
convergence correction.

========================================================
33. P29 — DOCUMENTATION REALITY INVENTORY
========================================================

Inventory EVERY documentation/planning/governance file.

For each classify:

CANONICAL
USEFUL_BUT_MISPLACED
STALE
CONFLICTING
HISTORICAL
DUPLICATE
TEMPORARY
UNKNOWN

Create a migration matrix:

CURRENT FILE
CURRENT ROLE
TARGET FILE
ACTION
WHY
REFERENCES TO UPDATE

No document gets a permanent role merely because it exists today.

========================================================
34. P29 — PRODUCT CONSTITUTION
========================================================

Create canonical Product Constitution.

It must define:

what Kinevo is
what Kinevo is not
user
problem
core product loop
Workspace
Intention
Reality
Context
Execution
Progress
Review
Adapt
AI authority
ownership principle
open-source/cloud relationship
non-goals

This becomes the highest product authority below legal/security
constraints.

========================================================
35. P29 — VISION / MISSION / POSITIONING
========================================================

Converge:

vision
mission
ICP
category
positioning
differentiators
competitive posture
brand promise
North Star
retention definitions

Avoid generic:

"AI-powered productivity platform."

Preserve Kinevo's intention-to-execution and reality-aware planning
differentiation.

========================================================
36. P29 — BUSINESS / COMMERCIAL CONVERGENCE
========================================================

Create canonical policy separating:

PRODUCT VALUE
PLAN ENTITLEMENTS
PAYMENT
SUBSCRIPTION
AI USAGE
COST
CUSTOMER BALANCE

Converge:

Core vs Cloud
Free / Pro / Power
launch pricing hypothesis
BYOK
hosted AI principles
upgrade/downgrade
cancel/resume
refund/grace principles
web-first Android billing
commercial uncertainties

Do NOT finalize AI quota numbers without FinOps evidence.

========================================================
37. P29 — SRS V3
========================================================

REWRITE / RECONSTRUCT the SRS.

Do NOT append patches to the old SRS indefinitely.

Preserve historical requirement traceability.

For each previous requirement classify:

UNCHANGED
REFINED
SUPERSEDED
DEPRECATED
NEW

SRS v3 must reflect CURRENT accepted domain behavior including:

Workspace
Today/Week/Month
Hard Landscape
Effective Schedule
recurrence
Permanent Shift
One-Time Exception
schedule history
locked work
Sacred Anchor
weekly drafts
Sync Now
offline reconciliation
AI proposals
billing/entitlements
mobile contractual boundaries

Remove obsolete architecture-frozen/MVP language where it no longer
represents the product.

========================================================
38. P29 — DOMAIN MODEL CONVERGENCE
========================================================

REWRITE the canonical domain model where needed.

Workspace MUST be represented explicitly.

Converge aggregate/entity/value-object boundaries.

Document:

global vs Workspace-scoped domains
Effective Landscape
schedule drafts
offline operation ledger
AI proposals
subscriptions
entitlements
assets
notifications

Do not invent implementation that does not exist or is not target-locked.

========================================================
39. P29 — ARCHITECTURE CONVERGENCE
========================================================

REWRITE architecture.md into current architecture authority.

Remove stale Inertia-era claims if still present.

Document:

modular monolith
application/domain/infrastructure boundaries
Vue SPA
NativePHP
PostgreSQL
offline reconciliation
scheduling authority
AI provider boundary
email provider boundary
billing provider boundary
asset-storage boundary
analytics provider boundary
observability boundary
Core/Cloud future separation
runtime target

Explicitly distinguish:

CURRENT
TARGET
MIGRATION_REQUIRED

========================================================
40. P29 — INFORMATION ARCHITECTURE
========================================================

Canonical desktop IA target:

KINEVO

[Workspace]

NOW
  Today
  Week
  Month

BUILD
  Goals
  Tasks

THINK
  Knowledge
  Canvas

REFLECT
  Progress
  Review

separator

Import & Sync
Notifications
Settings

+ Capture

Refine against actual implementation.

Do not reintroduce Calendar naming casually if Month is canonical.

========================================================
41. P29 — STITCH MCP DESIGN REALITY AUDIT
========================================================

ONLY NOW connect the Stitch MCP project if:

STITCH_PROJECT_ID is available
and MCP access exists.

Read the project.

Inventory all relevant screens/frames.

Create:

docs/ux/stitch-convergence-matrix.md

For each screen:

frame/screen ID
intended feature
current product match
SRS match
IA match
design quality
status
decision

Status:

APPROVED
APPROVED_WITH_REFINEMENT
OUTDATED
CONFLICTS_WITH_PRODUCT
REJECT

Do not edit product requirements just to preserve a visually attractive
Stitch concept.

========================================================
42. P29 — DESIGN SYSTEM RECONSTRUCTION
========================================================

The existing design.md MAY be:

REWRITTEN
SPLIT
MOVED
ARCHIVED

Do not preserve it as canonical if it no longer captures the accepted
design language.

Create canonical design docs from:

accepted product requirements
accepted IA
current implementation evidence
existing useful design.md content
approved Stitch evidence

Cover:

tokens
typography
grid
spacing
border
shadow
radius
icons
navigation
forms
tables
scheduling UI
empty states
error states
loading
offline
stale/conflict
responsive behavior
accessibility
motion
dark/light behavior

========================================================
43. P29 — CONTENT DESIGN
========================================================

Create canonical UI copy principles.

Converge terms:

Workspace
Hard Landscape
Sync Now
Schedule needs review
Permanent Shift
One-Time Exception
Sacred Anchor
Goal
Task
Progress
Review

Avoid exposing internal engineering vocabulary unnecessarily.

Bilingual Indonesia + English readiness should be considered.

========================================================
44. P29 — MARKETING / WEBSITE SPEC
========================================================

Converge website strategy.

Preserve tagline:

Kinevo — Turn intentions into execution.

Marketing direction includes concepts such as:

YOUR LIFE IS NOT A TODO LIST.

FROM INTENTION TO EXECUTION.

UPLOAD THE SCHEDULE. KEEP YOUR LIFE.

AI PROPOSES. YOU DECIDE.

OWN THE SOFTWARE. OR LET KINEVO HOST IT FOR YOU.

Do not claim unsupported features.

Create claims registry:

CLAIM
PRODUCT EVIDENCE
ALLOWED?
NOTES

Website implementation belongs to later repository/distribution work,
but website specification belongs here.

========================================================
45. P29 — DOCUMENT MIGRATION EXECUTION
========================================================

After all canonical replacements are prepared:

MOVE useful documents.

MERGE duplicates.

ARCHIVE historical material.

DELETE obsolete duplicates.

UPDATE links.

UPDATE README.

UPDATE AGENTS.md.

UPDATE CI/docs checks.

UPDATE references in code comments only where necessary.

No broken links.

No dangling authority references.

========================================================
46. MISSING ADR REFERENCES
========================================================

Resolve historical dangling ADR references such as ADR-009/010/011.

For each:

RECONSTRUCT only if credible original decision evidence exists.

Otherwise:

remove/de-reference the dangling authority claim and explain in the
migration record.

Do NOT fabricate historical ADR content.

========================================================
47. P29 — TASK / ROADMAP REBASELINE
========================================================

Rebuild root TASK.md into the slim control plane.

Move detailed tasks into docs/roadmap.

Create explicit old→new phase mapping.

Target roadmap:

P29 Product & Architecture Convergence

P30 Runtime, Identity & Communication Foundation

P31 Assets & Content Infrastructure

P32 Analytics, AI Observability & Retention Instrumentation

P33 Commercial Runtime & FinOps

P34 Repository Boundary & Distribution

P35 Production Operations & Reliability

P36 Android Production

P37 Production Security & Privacy Gate

P38 Production Performance & Capacity Gate

P39 Release Candidate

========================================================
48. P29 — REQUIREMENTS TRACEABILITY
========================================================

Create cross-document traceability.

Required conceptual chain:

PRODUCT PRINCIPLE
↔ SRS REQUIREMENT
↔ DOMAIN
↔ API / ROUTE
↔ UX SURFACE
↔ TASK
↔ TEST

High-value core loops must be fully traceable.

========================================================
49. P29 GATE
========================================================

P29 is DONE only when:

one Product Constitution exists
one SRS authority exists
one architecture authority exists
Workspace semantics are explicit
one IA authority exists
one design authority exists
Stitch has been reconciled if available
commercial policy is coherent
document hierarchy is coherent
TASK.md is slim
roadmap P30-P39 is rebuilt
old docs have been migrated/archived/deleted
all references work
no unresolved HIGH documentation contradiction remains without an
explicit decision owner

Checkpoint and STOP.

========================================================
50. P30 — RUNTIME, IDENTITY & COMMUNICATION FOUNDATION
========================================================

P30 combines three foundations:

A. Laravel runtime migration
B. production identity
C. transactional communication

========================================================
51. P30 — FRANKENPHP + OCTANE
========================================================

Migration target is LOCKED:

Laravel Octane
+
FrankenPHP

Do not treat performance benefit as proven.

Benchmark before/after.

Audit all long-lived-worker hazards:

mutable static state
singleton request leakage
user leakage
Workspace leakage
locale/timezone leakage
database connections
HTTP clients
AI provider instances
listeners
buffers
memory growth

CRITICAL SECURITY TEST:

User/Workspace A state must NEVER leak into User/Workspace B request.

========================================================
52. P30 — RUNTIME BENCHMARK
========================================================

Measure current vs target:

cold behavior
warm behavior
P50
P95
P99
memory baseline
memory after sustained requests
CPU
throughput
key endpoints

Worker count and max requests must be evidence-driven.

Provide rollback path.

========================================================
53. P30 — IDENTITY
========================================================

Implement production identity baseline including:

email verification
password reset
security notifications
session controls as required
recovery
rate limits
abuse controls

Do not overbuild enterprise identity.

========================================================
54. P30 — EMAIL
========================================================

Initial provider:

Resend

BUT:

provider access MUST go through a Kinevo-owned EmailProvider abstraction.

Provider-specific semantics must not leak into domain logic.

Transactional scope:

verification
password reset
security notice
subscription/payment notice
critical account change
important schedule notice where policy permits

Track:

sent
delivered
bounced
complained
failed

Provider dashboard is not Kinevo's source of truth.

========================================================
55. P30 GATE
========================================================

Require:

FrankenPHP functional parity
worker isolation
memory soak
benchmark evidence
rollback drill
identity E2E
email provider abstraction
verification/reset working
failure/degradation tested
docs/runbooks synced

STOP.

========================================================
56. P31 — ASSETS & CONTENT INFRASTRUCTURE
========================================================

Audit current Attachment capability before inventing a new Asset model.

Target pipeline:

User
→ Uppy
→ validation
→ Pic Smaller when image
→ Upload Adapter
→ AssetStorage
→ object storage
→ Asset record/reference
→ Notes / Canvas / Tasks

Never embed large binary data into Note/Canvas JSON.

========================================================
57. P31 — THIRD-PARTY ASSET ADOPTION
========================================================

Uppy:
embedded/adapter UI pipeline where appropriate

Pic Smaller:
embedded/adapter processing capability where appropriate

Object storage:
provider abstraction

Verify licenses/versions/provenance.

========================================================
58. P31 — CONTENT RELIABILITY
========================================================

Cover:

upload
validation
image optimization
retry
partial failure
orphan cleanup
reference deletion
access authorization
offline compatibility
storage quotas
backup/recovery
S3/R2-compatible path

========================================================
59. P31 GATE
========================================================

Require:

production asset pipeline
Notes integration
Canvas integration
Task attachment compatibility
object-storage recovery
license provenance
failure tests
observability
accessibility
docs

STOP.

========================================================
60. P32 — ANALYTICS, AI OBSERVABILITY & RETENTION
========================================================

Implement canonical product-event taxonomy defined during P28/P29.

Provider target:

OpenPanel through adapter/service boundary.

Kinevo's product semantics remain provider-owned by Kinevo.

Provider is transport/analytics infrastructure.

========================================================
61. P32 — AI OBSERVABILITY
========================================================

Target:

Langfuse-compatible provider abstraction / service boundary.

AI billing truth remains:

Kinevo AI Ledger

NOT:

Langfuse.

Redact sensitive context.

Do not leak full private notes/tasks unnecessarily.

========================================================
62. P32 — RETENTION INSTRUMENTATION
========================================================

Implement:

activation
Goal→Execution
schedule aha
KRS import aha
Today usage
completion/progress
review
first-week retention semantics
WGPU supporting metrics

Ensure:

event deduplication
privacy
Workspace context
no sensitive-content payloads
provider failure degradation

========================================================
63. P32 GATE
========================================================

Require:

event semantics versioned
provider abstraction
OpenPanel integration if adopted
Langfuse integration if adopted
privacy/redaction tests
provider-down degradation
AI ledger remains financial truth
dashboards/runbooks
licensing

STOP.

========================================================
64. P33 — COMMERCIAL RUNTIME & FINOPS
========================================================

Converge:

Midtrans
Subscription
Payment
Entitlement
Lago if adopted
AI FinOps
BYOK
quota
billing reconciliation

Payment is not entitlement.

Subscription is not payment gateway state.

Kinevo backend remains authoritative.

========================================================
65. P33 — MIDTRANS PRODUCTION
========================================================

Move from sandbox evidence to production-readiness.

Cover:

signed webhook
idempotency
out-of-order
reconciliation
pending
active
past_due
cancel
resume
upgrade
downgrade
refund
chargeback
cross-device
provider outage

Resolve paid→paid upgrade contradiction explicitly.

========================================================
66. P33 — LAGO
========================================================

If adopted:

ADAPTER + SERVICE.

Lago does not own Kinevo entitlements.

Midtrans remains payment gateway unless explicitly changed.

Kinevo owns commercial truth.

========================================================
67. P33 — AI FINOPS SIMULATOR
========================================================

Before production quotas:

simulate AI cost per capability.

Produce:

P50
P75
P90
P95
P99
abuse

Model:

provider
model
cached input
uncached input
output
currency
FX
buffer
Kinevo cost
customer allowance impact
margin

Only then lock hosted AI allowances.

========================================================
68. P33 GATE
========================================================

Require:

production Midtrans behavior proven
upgrade path resolved
entitlements authoritative
AI ledger complete
FinOps simulation complete
quotas decided or explicit launch-safe bounds approved
BYOK boundaries proven
billing reconciliation
failure drills
commercial docs consistent

STOP.

========================================================
69. P34 — REPOSITORY BOUNDARY & DISTRIBUTION
========================================================

ONLY NOW perform repository split.

Target:

PUBLIC
sedam-or/Kinevo
= Core

PRIVATE
kinevo-cloud

PUBLIC
kinevo-site

========================================================
70. P34 — SPLIT SAFETY
========================================================

NEVER DELETE FIRST.

Required process:

freeze
→ inventory ownership
→ classify files
→ dependency analysis
→ destination mapping
→ copy/migrate
→ build/test destinations
→ verify boundaries
→ compare functionality
→ update docs
→ migration notes
→ only then remove from original

Create a pre-split tag.

Temporary split branches may be used.

No long-lived Core/Cloud/Site branch model.

========================================================
71. P34 — CORE REQUIREMENT
========================================================

Kinevo Core must remain genuinely usable/self-hostable.

Do not hollow the open-source Core merely to force Cloud adoption.

Cloud value:

managed hosting
managed AI
sync convenience
storage
backup
operations
commercial services

========================================================
72. P34 — SITE
========================================================

Implement `kinevo-site` from P29 canonical marketing/site/design spec.

Approved Stitch references may be used.

Do not introduce unsupported marketing claims.

========================================================
73. P34 GATE
========================================================

Require:

three boundaries coherent
Core builds
Cloud builds
Site builds
license boundaries valid
secret separation
CI works
docs links updated
installation works
functional comparison complete

STOP.

========================================================
74. P35 — PRODUCTION OPERATIONS & RELIABILITY
========================================================

Adopt production operational capabilities.

Potential targets:

Filament
GlitchTip
Gotify
external monitoring
backup/restore
alerts
health checks
runbooks

Each must pass third-party adoption gates.

========================================================
75. P35 — FILAMENT
========================================================

Filament may serve Kinevo Cloud/operator control-plane needs.

Do NOT turn it into normal customer UX.

No enterprise admin surface creep.

========================================================
76. P35 — GLITCHTIP
========================================================

If adopted:

Adapter + Service where appropriate.

Cover:

backend errors
frontend errors where supported
release correlation
PII redaction
provider outage

========================================================
77. P35 — GOTIFY / NOTIFICATION TRANSPORT
========================================================

If adopted:

NotificationProvider boundary.

Kinevo Notification domain remains authoritative.

Transport failure must degrade safely.

========================================================
78. P35 — DISASTER RECOVERY
========================================================

Prove:

backup
restore
database loss recovery
object storage recovery
credential rotation
provider outage
deployment rollback

A backup that has never been restored is not sufficient evidence.

========================================================
79. P35 GATE
========================================================

Require:

health checks
metrics
alerts
error tracking
backup
restore drill
DR runbook
incident runbook
provider failure behavior
license review
operational ownership

STOP.

========================================================
80. P36 — ANDROID PRODUCTION
========================================================

Android remains the first native mobile platform.

Same account.
Same entitlement.
Same backend/domain authority.

Web-first billing remains acceptable for v1.

No duplicate mobile backend.

========================================================
81. P36 — MOBILE OFFLINE
========================================================

Implement the previously deferred durable mobile queue using ADR-017's
existing protocol.

Do NOT invent a second mobile sync protocol.

Cover:

durable persistence
replay
conflict
cross-account safety
offline capture
rehydration

========================================================
82. P36 — MOBILE PRODUCT PARITY
========================================================

Production mobile scope should cover appropriate companion flows:

Today
Capture
Tasks
Goals
Workspace
Hard Landscape visibility
Import where suitable
offline
notifications
assets
entitlement
deep links
review/progress essentials

Canvas full authoring may remain desktop/tablet-oriented.

========================================================
83. P36 — MOBILE SECURITY
========================================================

Remove plaintext token storage.

Use appropriate secure storage.

Verify:

logout cleanup
account switching
token revocation behavior
backup exclusion
deep-link authorization

========================================================
84. P36 — MOBILE DELIVERY
========================================================

Add repeatable:

CI build
signed release process
versioning
artifact verification
release notes
crash monitoring
device matrix

No manual PHP-version lock hacks in production release workflow.

========================================================
85. P36 GATE
========================================================

Require:

secure auth
durable offline
core companion flows
assets
notifications
entitlements
device accessibility
CI build
release pipeline
failure handling
docs

STOP.

========================================================
86. P37 — PRODUCTION SECURITY & PRIVACY GATE
========================================================

P37 is a GATE phase.

Not a random feature bucket.

Perform security review across:

authentication
authorization
Workspace isolation
offline replay
AI context
AI keys
billing webhooks
email
assets
uploads
mobile
operator surfaces
logging
observability
backups
secrets
third-party processors

========================================================
87. P37 — REQUIRED SECURITY TESTS
========================================================

Include:

cross-user access
cross-workspace access
long-running worker leakage
IDOR
mass assignment
rate limits
replay
webhook spoofing
file validation
path traversal
secret exposure
log redaction
XSS
CSRF where applicable
CSP
dependency audit
mobile secure storage

========================================================
88. P37 — PRIVACY
========================================================

Document:

what Kinevo stores
why
retention
AI data flow
analytics data flow
email provider data
billing provider data
object storage
deletion
export
account closure

Implement account deletion/full export if still outstanding.

No unsupported privacy claim.

========================================================
89. P37 GATE
========================================================

No P0/P1 known security blocker.

Privacy docs match implementation.

Account deletion/export works.

Third-party data processing documented.

Security tests green.

STOP.

========================================================
90. P38 — PRODUCTION PERFORMANCE & CAPACITY GATE
========================================================

Benchmark real target architecture.

Do not rely on VPS folklore.

Measure:

CPU
RAM
DB
FrankenPHP workers
queue
scheduler
storage
AI gateway
asset pipeline
analytics sidecars/services
billing load

========================================================
91. P38 — LOAD PROFILES
========================================================

Model realistic scales:

launch
100 active users
1k
10k where meaningful

Do not assume all services run on one small VPS.

Separate:

Core server capacity
managed external services
object storage
analytics
observability

========================================================
92. P38 — USER-PERCEIVED PERFORMANCE
========================================================

Measure:

Today
Week
Month
Goals
Tasks
Knowledge search
scheduler draft
Sync Now
offline reconcile
AI proposal
billing views

Use P50/P95/P99.

Include frontend performance and mobile where relevant.

========================================================
93. P38 GATE
========================================================

Require:

documented capacity envelope
known bottlenecks
resource budget
benchmark evidence
no unacceptable memory leak
FrankenPHP soak green
database query review
backup window acceptable
AI cost model updated
scaling/runbook strategy

STOP.

========================================================
94. P39 — RELEASE CANDIDATE
========================================================

P39 is evidence-driven.

No calendar-forced launch.

Stages:

RC1
internal dogfood

BUG BURN-DOWN

RC2
production-like rehearsal

GO / NO-GO

========================================================
95. P39 — RC1
========================================================

Deploy complete candidate.

Run all canonical journeys.

Use real monitoring.

Collect:

bugs
UX friction
billing issues
email issues
AI cost
scheduler quality
offline issues
mobile issues
performance

No feature expansion during RC unless required to fix a release blocker.

========================================================
96. P39 — BUG CLASSIFICATION
========================================================

P0:
release blocked

P1:
release blocked unless explicit owner waiver

P2:
may ship with documented plan

P3:
post-launch backlog

No hidden P0/P1 debt.

========================================================
97. P39 — RC2 REHEARSAL
========================================================

Perform production-like rehearsal:

fresh deployment
migration
seed/bootstrap
domain configuration
email
payment
AI provider
storage
analytics
observability
backup
restore
rollback
mobile connection
site
status/health

Verify runbooks from zero.

========================================================
98. P39 — FINAL GOLDEN JOURNEYS
========================================================

Run production candidate journeys including:

new user
Workspace
Goal
AI breakdown
schedule
Today
complete
Progress
Review

KRS import
recurrence
Permanent Shift
One-Time Exception

offline/reconnect/conflict

weekly draft
Sync Now
locked/Sacred Anchor

Note/Canvas/assets

Free→Pro
Pro→Power where supported
cancel/resume
payment reconciliation

email verification/reset

Android core journey

data export/delete

========================================================
99. GO / NO-GO
========================================================

GO only if:

correctness green
security green
privacy green
accessibility green
browser matrix green
Android gate green
performance green
backup/restore green
observability green
billing green
email green
AI FinOps acceptable
license compliance green
runbooks tested
no unresolved P0
no unresolved unapproved P1

Otherwise:

NO-GO.

There is no requirement to ship because a target date was previously
written.

========================================================
100. GLOBAL PRODUCTION DEFINITION
========================================================

A capability is production-ready only if it is:

FUNCTIONAL
TESTED
ACCESSIBLE
OBSERVABLE
SECURE
RECOVERABLE
DOCUMENTED
FAILURE-TESTED
LICENSE-CORRECT
OPERATIONALLY UNDERSTOOD

"Feature works on my machine" is not production-ready.

========================================================
101. GLOBAL TEST POLICY
========================================================

Never weaken tests merely to obtain green status.

When a test fails classify:

PRODUCT DEFECT
TEST DEFECT
STALE CONTRACT
ENVIRONMENT DEFECT
FLAKY TEST

Fix the correct layer.

Do not silently delete assertions.

========================================================
102. GLOBAL EVIDENCE POLICY
========================================================

Every DONE task requires evidence appropriate to its type.

Possible evidence:

unit test
feature test
integration test
browser test
device test
benchmark
security test
failure drill
backup/restore drill
license evidence
documentation validation

TASK.md label alone is never implementation evidence.

========================================================
103. GLOBAL MIGRATION POLICY
========================================================

Prefer:

additive
backward-compatible
rollback-capable

For destructive migrations:

backup
migration plan
rollback/recovery plan
evidence

No destructive schema operation hidden inside unrelated work.

========================================================
104. GLOBAL THIRD-PARTY FAILURE POLICY
========================================================

Kinevo must remain understandable when an external provider fails.

Each adapter must define:

available
degraded
unavailable
retry
fallback where valid
user messaging
operational alert

Do not let provider-specific exceptions leak randomly into UX.

========================================================
105. GLOBAL DOCUMENTATION COMPLETION RULE
========================================================

At the end of EVERY phase:

inspect documentation touched by implementation.

Update factual truth.

Do not defer every doc update to P39.

But avoid broad unrelated rewrites outside P29.

========================================================
106. GLOBAL GIT POLICY
========================================================

Before a major phase:

clean working tree preferred
checkpoint current accepted baseline

During phase:

coherent bounded commits

Before final phase report:

full verification
clean status unless explicitly justified

Never:

discard unknown user WIP
force-reset owner changes
mix unrelated experimental work into a release checkpoint

========================================================
107. MASTER ROADMAP STATUS FORMAT
========================================================

Each phase must expose:

NOT_STARTED
ACTIVE
BLOCKED
GATED
DONE
SUPERSEDED

Each phase file must contain:

Objective
Dependencies
Decisions
Tasks
Evidence
Known gaps
Definition of Done
Final report
Next phase

========================================================
108. MASTER FINAL REPORT FORMAT — EACH PHASE
========================================================

Return:

A. repository before/after
B. phase objective
C. decisions made
D. implementation completed
E. docs rewritten/moved/deleted/created
F. task/roadmap changes
G. migrations
H. API changes
I. UI changes
J. third-party changes
K. tests
L. browser/device evidence
M. security/privacy evidence
N. performance/FinOps evidence where relevant
O. licensing evidence
P. unresolved issues
Q. gate status
R. commits/checkpoint
S. exact next phase
T. explicit STOP statement

========================================================
109. DOCUMENT RECONSTRUCTION FINAL CHECK
========================================================

P29 MUST NOT finish with:

multiple competing SRS files
multiple competing architecture authorities
old and new design.md both claiming authority
giant legacy TASK.md plus separate roadmap that duplicates it
stale root planning prompts
dangling ADR references
old navigation model presented as current
old pricing presented as current
old AI quota placeholders presented as policy
Inertia references presented as current when not installed
old mobile offline claims presented as implemented
Stitch concepts presented as product truth

These are convergence failures.

========================================================
110. P29 DOCUMENT CLEANUP DEFINITION OF DONE
========================================================

For every legacy document:

a disposition exists.

For every moved document:

references updated.

For every deleted document:

canonical replacement identified.

For every archived document:

clearly marked historical.

For every canonical concept:

exactly one authority exists.

========================================================
111. MASTER START COMMAND
========================================================

CURRENT AUTHORIZED PHASE:

P28 — PRODUCT EXPERIENCE CLOSURE

Start by:

1. verify repository snapshot;
2. re-audit current P28 tracking against current implementation;
3. identify remaining P28 tasks;
4. resolve remaining UX/RET work;
5. triage browser failures;
6. execute final golden journeys;
7. execute retention failure E2E;
8. evaluate P28-014;
9. reconstruct P28 roadmap records where necessary;
10. STOP after P28-014 final report.

DO NOT enter P29 automatically.

========================================================
112. MASTER FINAL PRINCIPLE
========================================================

Do not optimize for finishing tasks.

Optimize for leaving Kinevo more coherent than before.

When old documentation is wrong:

correct it.

When old documentation is redundant:

merge or remove it.

When TASK.md is structurally unhealthy:

reconstruct it.

When a historical artifact is valuable:

archive it.

When product intent and implementation disagree:

resolve the conflict explicitly.

When evidence is missing:

do not guess.

When a phase gate is not green:

do not advance.

When a release is not ready:

do not ship.
