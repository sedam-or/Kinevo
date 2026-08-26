# KINEVO OS — MASTER EXECUTION PROMPT
# PHASE 18 + PHASE 19 + PHASE 20
# AI PROVIDER CONTROL PLANE + WORKSPACE/CONTEXT + BRAND/PRODUCT EXPERIENCE

## PURPOSE

This is the master implementation specification for the next major Kinevo product evolution.

It combines:

- PHASE 18 — AI Provider Control Plane
- PHASE 19 — Workspace & Context System
- PHASE 20 — Brand, Design System & Product Experience

The objective is not merely to add features. The objective is to make Kinevo technically coherent, runtime-AI configurable, context-aware, cross-domain coherent, understandable to users, visually consistent, browser-verifiable, and production-oriented.

This is an EXISTING repository. This is NOT GREENFIELD.

---

# MASTER RULE 0 — NO HALLUCINATION

Before modifying code:

1. Read the authoritative documentation.
2. Inspect current implementation.
3. Inspect current tests.
4. Inspect current migrations.
5. Inspect current routes.
6. Inspect current frontend.
7. Determine what already exists.
8. Extend or correct existing implementation rather than duplicating it.
9. Record assumptions when necessary.
10. Never invent missing product behavior.

Source-of-truth documents:

- `docs/SRS.md`
- `docs/architecture.md`
- `docs/domain-model.md`
- `docs/design.md`
- `docs/knowledge-layer.md`
- `docs/scheduling-engine.md`
- `docs/offline-sync.md`
- `docs/ai-architecture.md`
- `docs/design-tokens.md`
- `docs/ui-audit.md`
- `docs/browser-e2e.md`
- `docs/test-strategy.md`
- `docs/deployment.md`
- `docs/environment.md`
- `docs/api/openapi.yaml`
- `docs/implementation-status.md`
- `TASK.md`

Inspect:

- `database/migrations/`
- `server/app/Domain/`
- `server/app/Application/`
- `server/app/Infrastructure/`
- `server/app/Models/`
- `server/app/Http/`
- `server/routes/`
- `server/resources/js/`
- `server/tests/`
- `tests/e2e/`
- `infrastructure/`
- `Makefile`
- `composer.json`
- `package.json`

---

# MASTER RULE 1 — PRESERVE EXISTING ARCHITECTURE

Preserve and extend:

- Laravel modular monolith
- PostgreSQL
- Vue 3
- TypeScript
- Inertia
- deterministic scheduler
- Goal domain
- Milestone domain
- Program domain
- Task/Subtask domain
- Knowledge Layer
- Notes
- Tiptap adapter
- Knowledge Links
- Canvas
- CanvasAdapter
- React Island
- Excalidraw
- autosave/versioning
- offline synchronization
- Today cache
- adaptive context
- progress events
- AI provider abstraction
- AI proposal architecture
- observability

Do NOT create:

- a second scheduler
- a second knowledge-link system
- a second canvas persistence system
- a second AI architecture
- a second auth system
- a second offline queue

---

# MASTER RULE 2 — PHASE ORDER

Execute in this order:

P18 — AI Provider Control Plane
↓
P19 — Workspace & Context System
↓
P20 — Brand, Design System & Product Experience

P18 makes AI runtime configurable.
P19 makes Kinevo contextually coherent.
P20 makes the product understandable and visually unified.

Do not use P20 visual work to hide P18/P19 architecture gaps.

---

# MASTER RULE 3 — DEVELOPMENT CODING AI VS KINEVO RUNTIME AI

These are two different systems.

## Development coding path

```text
Developer
→ OpenCode / coding agent
→ remote coding provider/gateway
→ coding model
→ repository
```

## Kinevo runtime path

```text
Browser
→ Kinevo
→ Laravel
→ AiProviderResolver
→ configured runtime endpoint
→ model
```

The coding agent API key MUST NOT automatically become Kinevo runtime credentials.

Kinevo MUST NOT inspect or reuse OpenCode configuration unless explicitly configured by a future requirement.

---

# MASTER RULE 4 — OLLAMA IS OPTIONAL

Local Ollama is optional.

Normal Kinevo development MUST work without it.

The following must not require Ollama:

```text
make up
make test
make ci
make e2e
```

Ollama may remain behind an explicit profile, such as:

```text
make ollama-up
```

but must not auto-start, auto-pull models, or become a hidden background dependency.

CI uses deterministic mocks/fakes, not large local-model inference.

---

# MASTER RULE 5 — RUNTIME AI

Normal Kinevo development/production should support a configurable remote OpenAI-compatible endpoint.

Conceptual configuration:

```text
provider_id
protocol
base_url
model
credential
enabled
```

Do not hardcode one vendor.

A gateway credential belongs to the gateway endpoint.
A direct-provider credential belongs to that provider endpoint.
Never assume credentials are interchangeable.

---

# MASTER RULE 6 — AI SECRET SECURITY

Never persist raw API keys in:

- localStorage
- sessionStorage
- IndexedDB
- Pinia persistence
- cookies
- URL/query/hash
- HTML
- frontend environment variables
- logs
- analytics
- telemetry
- OpenAPI responses
- repository

Correct lifecycle:

```text
Browser
→ HTTPS
→ Laravel
→ validate/authorize
→ encrypt
→ PostgreSQL
```

Runtime:

```text
Laravel
→ decrypt in memory
→ provider request
```

GET settings must never return raw secrets.

---

# MASTER RULE 7 — AI PROPOSALS NEVER SILENTLY MUTATE

AI workflow:

```text
Generate
→ Validate
→ Preview
→ Edit / Accept / Reject
→ Commit
```

Existing proposal architecture remains authoritative.

No silent milestone/task mutation.

---

# MASTER RULE 8 — WORKSPACE IS CONTEXT, NOT GOD-OBJECT

Workspace means:

> “What context am I working in?”

Workspace is NOT:

- folder
- notebook
- tenant
- organization
- team
- permissions boundary
- universal parent
- replacement for Goal
- replacement for Program
- replacement for Knowledge

Kinevo remains single-owner/single-user for this phase.

---

# MASTER RULE 9 — NOTE AND CANVAS MUST REMAIN

Workspace MUST NOT remove or replace Knowledge or Canvas.

Mandatory:

```text
Task
├── Notes
└── Canvas
```

Workspace adds:

```text
Task → Workspace context
```

It does not replace:

```text
Task → Note
Task → Canvas
```

Existing Knowledge Links remain the relationship mechanism.

---

# MASTER RULE 10 — CONTEXT INHERITANCE

Context precedence:

```text
explicit entity context
>
parent entity context
>
active workspace
>
global
```

Examples:

- Goal created in Research → Research
- Milestone from Goal → Goal context
- Task from Goal → Goal context
- Note from Task → Task context
- Canvas from Task → Task context
- Quick Capture without parent → active workspace

Active workspace MUST NOT override a stronger explicit parent context.

---

# MASTER RULE 11 — GLOBAL COMMITMENTS

Workspace does not isolate the scheduler from real-world commitments.

Default global:

- Hard Landscape
- global calendar commitments

Scheduler input:

```text
workspace task candidates
+
global hard constraints
+
locked assignments
+
capacity
+
deadlines
```

Existing scheduler remains authoritative.

Do not create a WorkspaceScheduler.

---

# MASTER RULE 12 — PRODUCT UX

For every major feature answer:

- Why does this exist?
- When should I use it?
- What will happen?
- What is the next action?
- What changed afterward?

A technically powerful feature that users cannot discover or understand is incomplete at product level.

---

# MASTER RULE 13 — BRAND ARCHITECTURE

Existing Kinevo logo, colors, and public brand are authoritative.

Do NOT invent a new logo or replace the current brand without explicit approval.

Use:

```text
Existing Kinevo Brand
→ Brand Tokens
→ Semantic Tokens
→ Component Tokens
→ Product Patterns
```

Treat public landing page as brand/narrative reference, not as product UI source.

Landing page = marketing narrative.
Application = operational interface.

---

# MASTER RULE 14 — VISUAL DIRECTION

Do not apply pure Neo-Brutalism everywhere.

Preferred:

- structured/editorial hierarchy
- calm/minimal surfaces
- restrained tactile interaction
- spatial Canvas treatment
- subtle Neo-Brutalist interaction DNA

Use tokens, not raw values.

---

# PHASE 18 — AI PROVIDER CONTROL PLANE

## P18-001 — AI Provider Settings Domain
Priority: P0

Create/extend:

- `AiProviderSettings`
- `AiProviderType`
- `AiProviderProtocol`
- `AiProviderCapabilities`
- `AiProviderConnectionStatus`
- `AiCredential`

Required concepts:

```text
provider_id
protocol
base_url
model
credential
enabled
```

Capabilities:

```text
requires_api_key
requires_base_url
requires_model
supports_local
supports_remote
supports_connection_test
```

Initial families:

- disabled
- mock
- ollama
- openai-compatible

Do not assume all OpenAI-compatible endpoints use one protocol.

---

## P18-002 — Secure Credential Storage
Priority: P0

Conceptual schema:

```text
ai_provider_settings
--------------------
id
user_id
provider_id
protocol
base_url
model
credential_ciphertext
credential_hint
enabled
last_verified_at
last_status
last_error_code
created_at
updated_at
```

Use project conventions.

Rules:

- encrypted credential
- owner scoped
- no plaintext column
- no raw authorization header
- no raw provider error payload

Tests must prove ciphertext storage and no secret exposure.

---

## P18-003 — AI Provider Application Services
Priority: P0

Implement/extend:

- `GetAiSettingsUseCase`
- `UpdateAiProviderSettingsUseCase`
- `SetAiProviderCredentialUseCase`
- `RemoveAiProviderCredentialUseCase`
- `EnableAiProviderUseCase`
- `DisableAiProviderUseCase`
- `ListAvailableAiProvidersUseCase`
- `TestAiProviderConnectionUseCase`

No domain logic in controllers.

---

## P18-004 — Runtime Provider Resolution
Priority: P0

Use the existing resolver.

Flow:

```text
AI Application Use Case
→ AiOrchestrator
→ AiProviderResolver
→ runtime settings
→ provider implementation
```

Provider implementations must not query the database directly.

---

## P18-005 — Configuration Precedence
Priority: P0

Document and test:

```text
user-managed runtime settings
>
deployment defaults
>
application fallback
```

If no valid AI provider exists:

```text
AI = unavailable/disabled
```

Core Kinevo still works.

---

## P18-006 — AI Settings API
Priority: P0

Inspect existing routes first.

Required capabilities where missing:

```text
GET    /api/v1/ai/settings
PATCH  /api/v1/ai/settings
POST   /api/v1/ai/settings/credential
DELETE /api/v1/ai/settings/credential
POST   /api/v1/ai/settings/test
POST   /api/v1/ai/settings/enable
POST   /api/v1/ai/settings/disable
GET    /api/v1/ai/providers
```

Owner scoped.

OpenAPI updated.

---

## P18-007 — Safe Settings Response
Priority: P0

Allowed:

```text
provider
protocol
base_url
model
enabled
configured
masked hint
last verified
safe status
```

Forbidden:

```text
raw key
ciphertext
authorization header
```

---

## P18-008 — Connection Test
Priority: P0

A connection test must verify:

- authentication
- protocol compatibility
- model usability
- minimal non-mutating inference

It must not use user content.

A mere TCP/ping is not sufficient.

Map upstream failures to stable Kinevo errors:

```text
AI_PROVIDER_UNAVAILABLE
AI_PROVIDER_AUTH_FAILED
AI_PROVIDER_BAD_CONFIGURATION
AI_PROVIDER_MODEL_NOT_FOUND
AI_PROVIDER_TIMEOUT
AI_PROVIDER_RATE_LIMITED
AI_PROVIDER_UNSUPPORTED
```

Use existing project conventions when names already exist.

---

## P18-009 — Provider Status
Priority: P0

Existing `GET /api/v1/ai/status` must use the same settings source.

States:

```text
not_configured
disabled
configured
testing
connected
degraded
unavailable
```

Configured != connected.

---

## P18-010 — AI Provider Settings UI
Priority: P0

Route:

```text
Settings → AI & Providers
```

Sections:

- Runtime Status
- Provider
- Base URL
- Model
- Credentials
- Connection Test
- Privacy
- Enable/Disable

Use existing Kinevo design system.

---

## P18-011 — SecretField
Priority: P0

States:

- empty
- saving
- configured
- replacing
- removing
- error

After save show:

```text
••••••••••••abcd
```

Never provide raw secret recovery.

---

## P18-012 — Provider UI
Priority: P0

Ollama:

- Base URL
- Model
- API Key: not required

OpenAI-compatible:

- Base URL
- Model
- API Key
- protocol when required

Disabled:

- no runtime configuration needed

---

## P18-013 — Privacy UX
Priority: P0

Local:

> Data stays inside the configured Kinevo/local AI infrastructure, subject to the actual deployment.

Remote:

> Kinevo may send content selected for AI assistance to the configured external endpoint.

Do not invent retention guarantees.

---

## P18-014 — Goal Breakdown Runtime Flow
Priority: P0

Required:

```text
Goal
→ Break down with AI
→ Validate
→ Proposal
→ Review
→ Accept/Edit/Reject
→ Commit
```

No silent mutations.

---

## P18-015 — Goal Breakdown Entry Points
Priority: P0

Expose:

- post-goal creation
- Goal detail
- empty milestone state
- Goal action menu

If not configured:

```text
AI isn't configured.
[Configure AI]
```

---

## P18-016 — AI Proposal UX
Priority: P1

Show:

- proposed milestones
- estimated workload
- deadline considerations
- assumptions
- constraints

Clearly mark:

```text
AI GENERATED
NOT YET COMMITTED
```

---

## P18-017 — Remote Runtime Smoke Test
Priority: P0

Prove:

```text
Browser
→ Laravel
→ remote endpoint
→ successful model call
```

while Ollama is NOT running.

Use secure injected test credentials.

---

## P18-018 — Ollama Isolation
Priority: P0

Verify:

```text
make up     -> no Ollama
make test   -> no Ollama
make ci     -> no Ollama
make e2e    -> no Ollama
```

Optional explicit profile:

```text
make ollama-up
```

---

## P18-019 — Agent/Runtime Documentation
Priority: P1

Document:

- coding-agent AI
- Kinevo runtime AI
- remote runtime
- optional Ollama
- credential flow
- environment defaults
- user overrides

---

## P18-020 — AI Browser E2E
Priority: P0

Journey:

```text
Settings
→ AI & Providers
→ configure
→ save
→ masked secret
→ reload
→ still masked
→ test connection
→ Goal
→ Break down with AI
→ proposal
→ accept
→ milestones
```

Also test:

- invalid key
- unavailable endpoint
- disabled mode

---

## P18-021 — Provider Protocol Capability
Priority: P1

Make protocol an explicit provider capability.

Do not assume all remote models expose identical APIs.

---

## P18-022 — Credential Rotation
Priority: P1

Replace credentials atomically.

Old credential ceases to be active.
New credential becomes sole active credential.

---

## P18-023 — Deployment Defaults vs User Override
Priority: P1

Test:

- deployment default only
- user override
- no configuration

---

## P18-024 — Provider Runtime Test Matrix
Priority: P1

At minimum:

| Scenario | Expected |
|---|---|
| valid endpoint | connected |
| invalid key | auth_failed |
| invalid model | model_not_found |
| timeout | timeout |
| rate limit | rate_limited |
| endpoint down | unavailable |
| disabled | disabled |
| Ollama unavailable | unavailable |
| remote works with no Ollama | connected |

---

## P18 ACCEPTANCE GATE

P18 is complete only when:

- [ ] Runtime AI configurable through web app
- [ ] Credential encrypted
- [ ] Raw credential never returned
- [ ] Credential rotation works
- [ ] Provider protocol explicit
- [ ] Connection test proves model usability
- [ ] AI status uses same settings source
- [ ] Goal Breakdown accessible
- [ ] Proposal approval works
- [ ] Core app works when AI unavailable
- [ ] Normal development does not require Ollama
- [ ] Browser E2E passes
- [ ] Secret scan passes
- [ ] OpenAPI passes
- [ ] Documentation updated
- [ ] TASK evidence updated

# PHASE 19 — WORKSPACE & CONTEXT SYSTEM

## P19-001 — Workspace Domain
Priority: P0

Create:

- `Workspace`
- `WorkspaceType`
- `WorkspaceStatus`
- `WorkspaceRepository`

Conceptual fields:

```text
id
user_id
name
slug
description
icon
accent
type
is_default
status
timestamps
```

Invariants:

- owner scoped
- name required
- slug unique per user
- exactly one default
- archived workspace cannot be active
- archive preserves data
- restore supported

---

## P19-002 — Workspace Persistence
Priority: P0

Workspace-scoped candidates:

- Goals
- Programs
- Tasks
- Notes
- Canvas

Parent-inherited:

- Milestone ← Goal
- Subtask ← Task
- ScheduleAssignment ← Task
- CanvasFile ← Canvas

Global:

- User
- Profile
- Auth
- AI provider settings
- Theme
- System settings

Hard Landscape/Notifications must be evaluated explicitly and not blindly scoped.

---

## P19-003 — Existing Data Migration
Priority: P0

Create default:

```text
Personal
```

Assign existing workspace-aware data to Personal unless explicit deterministic historical context exists.

Migration must be:

- idempotent
- tested
- data-preserving
- non-destructive

---

## P19-004 — Workspace API
Priority: P0

Add only missing endpoints:

```text
GET    /api/v1/workspaces
POST   /api/v1/workspaces
GET    /api/v1/workspaces/{workspaceId}
PATCH  /api/v1/workspaces/{workspaceId}
POST   /api/v1/workspaces/{workspaceId}/default
POST   /api/v1/workspaces/{workspaceId}/archive
POST   /api/v1/workspaces/{workspaceId}/restore
GET    /api/v1/workspaces/{workspaceId}/home
```

Owner scoped.
OpenAPI updated.

---

## P19-005 — Workspace Switcher
Priority: P0

Create one reusable `WorkspaceSwitcher`.

Must support:

- current workspace clarity
- keyboard
- mobile
- persistence
- reload/deep-link
- archived workspaces excluded

---

## P19-006 — Active Workspace State
Priority: P0

One authoritative active workspace state.

URL/server context is authoritative.
Client persistence is convenience.

Must survive:

- navigation
- reload
- session restoration

---

## P19-007 — Workspace Route Context
Priority: P1

Deep-link and refresh safe.

Preferred:

```text
/workspaces/{workspace}/...
```

but do not rewrite routing unnecessarily.

---

## P19-008 — Workspace Home
Priority: P1

Not a generic analytics dashboard.

Order:

```text
Identity
→ Current Goal
→ Next Action
→ Today
→ Knowledge
→ Canvas
→ Upcoming
→ Progress
```

---

## P19-009 — Workspace Identity
Priority: P1

Properties:

- name
- icon
- accent
- description

Workspace accent must not override semantic color meanings.

---

## P19-010 — Workspace Management UI
Priority: P1

Support:

- Create
- Edit
- Set Default
- Archive
- Restore

No teams/RBAC/organizations.

---

## P19-011 — Goal Workspace Scoping
Priority: P0

Goal context:

```text
explicit context > active workspace
```

Goal lists scope to active workspace unless Global explicitly selected.

---

## P19-012 — Program Workspace Scoping
Priority: P0

Program context follows explicit parent or active workspace.

Validate compatibility with Goal context.

Never silently move entities.

---

## P19-013 — Task Workspace Scoping
Priority: P0

Task context:

```text
explicit Goal/Milestone/Program
>
active workspace
```

Server validates consistency.

---

## P19-014 — Note Workspace Scoping
Priority: P0

Notes remain first-class.

Default Note context = active workspace.

---

## P19-015 — Note Creation from Task/Goal
Priority: P0

Task → Add Note:

```text
inherit Task workspace
create Note
create knowledge link
```

Goal → Add Note:

```text
inherit Goal workspace
create Note
create knowledge link
```

No repeated workspace selection.

---

## P19-016 — Knowledge Link Preservation
Priority: P0

Existing `knowledge_links` remains authoritative.

Workspace = context.
Knowledge Link = relationship.

Do not replace links with arbitrary direct FKs.

---

## P19-017 — Canvas Workspace Scoping
Priority: P0

Canvas remains:

- Excalidraw
- adapter
- autosave
- versioning
- offline

Workspace only adds context.

---

## P19-018 — Canvas in Task Detail
Priority: P0

Task detail retains:

- Linked Canvas
- Create Canvas
- Open Canvas

New Canvas inherits Task context and links back to Task.

---

## P19-019 — Note in Task Detail
Priority: P0

Task detail retains:

- Linked Notes
- Create Note
- Open Note

---

## P19-020 — Canvas in Task Detail
Priority: P0

Canvas remains visible in Task Knowledge.

---

## P19-021 — Subtask Knowledge
Priority: P1

Default:

```text
Subtask
→ Parent Task
→ Workspace
→ Knowledge
```

Do not make Subtasks independent Knowledge roots without explicit requirement.

---

## P19-022 — Workspace-Aware Today
Priority: P0

Today reflects active Workspace while still showing relevant global commitments.

---

## P19-023 — Workspace-Aware Scheduler
Priority: P0

Existing scheduler remains authoritative.

Input:

```text
workspace task candidates
+
global hard landscape
+
locks
+
capacity
+
deadlines
```

Do not build a WorkspaceScheduler.

---

## P19-024 — Workspace Quick Capture
Priority: P0

Quick Capture:

```text
explicit parent context > active workspace
```

Default = active workspace.

---

## P19-025 — Workspace-Aware AI Context
Priority: P1

AI receives only minimal relevant context:

- workspace metadata
- selected Goal
- relevant Milestones
- relevant Programs
- relevant Tasks
- explicitly selected Notes

Never automatically all workspace data.

Never credentials.

Never unrelated workspaces.

---

## P19-026 — AI Goal Breakdown + Workspace
Priority: P1

Flow:

```text
Research
→ Goal
→ AI Breakdown
→ workspace-bounded context
→ proposal
→ accept
→ milestones in Research
```

---

## P19-027 — Workspace Analytics
Priority: P1

Default = active workspace.

Explicit Global / All Workspaces is allowed.

No silent aggregation.

---

## P19-028 — Global / All Workspaces View
Priority: P1

Explicit global context:

- overall calendar
- global commitments
- overall analytics
- overall activity

Global means all data for CURRENT authenticated user, not all users.

---

## P19-029 — Cross-Workspace Relationships
Priority: P1

If supported:

- no duplication
- show target workspace
- owner authorization remains mandatory

---

## P19-030 — Workspace Archive
Priority: P0

Archive:

- preserves data
- removes from active switcher
- prevents new scoped work
- allows restore

Never cascade-delete Goals, Tasks, Notes, Canvas.

---

## P19-031 — Workspace Accessibility
Priority: P1

Keyboard, screen reader, focus, semantics, touch, reduced motion.

---

## P19-032 — Workspace Browser E2E
Priority: P0

Test:

- creation
- switching
- reload
- isolation
- Goal
- Task
- Note
- Canvas
- scheduling
- AI
- archive
- restore

---

## P19-033 — Workspace Data Safety
Priority: P0

Prove:

- no IDOR
- no cross-workspace leakage
- no orphan migration
- valid default
- archive is non-destructive

---

## P19-034 — Workspace UX Contract
Priority: P1

Show workspace context via:

- switcher
- breadcrumb
- title
- subtle accent

Do not repeat excessively.

---

## P19-035 — Task/Note/Canvas Relationship Preservation
Priority: P0

Mandatory:

```text
Task
├── Workspace
├── Goal
├── Milestone
├── Program
├── Schedule
├── Subtasks
├── Notes
└── Canvas
```

---

## P19-036 — Task Detail IA
Priority: P1

Task:

- title/status/action
- planning
- schedule
- knowledge
- subtasks
- activity
- AI

---

## P19-037 — Goal Detail IA
Priority: P1

Goal:

- outcome
- deadline
- progress
- workspace
- AI Breakdown
- milestones
- programs
- tasks
- knowledge
- schedule
- analytics

---

## P19-038 — Workspace Home IA
Priority: P1

Identity → Goal → Next Action → Today → Knowledge → Canvas → Upcoming → Progress

---

## P19-039 — Documentation
Priority: P1

Update architecture/domain/design/knowledge/scheduling/AI/offline/API/E2E/UI audit/test strategy/implementation status/TASK.

---

## P19-040 — Final E2E
Priority: P0

Full:

```text
Login
→ Personal
→ Create Research
→ Switch Research
→ Goal
→ AI Breakdown
→ Proposal
→ Accept
→ Milestones
→ Programs/Tasks
→ Note from Task
→ Canvas from Task
→ Schedule
→ Today
→ Execute
→ Complete
→ Progress
→ Analytics
→ Personal
→ Research hidden
→ Research restored intact
```

# PHASE 20 — BRAND, DESIGN SYSTEM & PRODUCT EXPERIENCE

## P20-001 — Brand Audit
Priority: P0

Inspect:

- public landing page
- logo
- colors
- typography
- favicon/app icon
- existing tokens
- existing UI

Produce brand usage inventory.

Do not replace brand identity automatically.

---

## P20-002 — Brand Architecture
Priority: P1

Formalize:

- logo
- wordmark
- icon
- app icon
- favicon
- monochrome variants
- light/dark usage
- minimum size
- clear space
- forbidden usage

Existing logo remains authoritative.

---

## P20-003 — Color Architecture
Priority: P0

Three levels:

```text
brand/primitive
semantic
component
```

Example:

```text
brand.primary
brand.secondary
neutral.*

surface.*
content.*
border.*
action.*
status.*
focus.*

button.*
card.*
input.*
workspace.*
ai.*
schedule.*
```

Components should consume semantic/component tokens, not raw hex.

---

## P20-004 — Existing Palette Preservation
Priority: P0

Map existing Kinevo palette into formal tokens.

Do not replace existing colors simply because another style seems fashionable.

Fill only missing semantic states.

Run contrast validation.

---

## P20-005 — Theme Architecture
Priority: P0

Support:

- light
- dark
- system

Must survive reload and work across all major screens.

One theme system only.

No per-page theme implementations.

---

## P20-006 — Typography Architecture
Priority: P1

Define:

- display
- page title
- section
- body
- metadata
- label
- mono/code

Typography hierarchy is part of information architecture.

---

## P20-007 — Spacing/Radius/Shadow/Z-Index/Motion
Priority: P1

Formalize all into tokens.

No arbitrary visual values in new UI.

---

## P20-008 — Visual Hierarchy
Priority: P0

Every page:

```text
context
→ outcome/current state
→ primary action
→ supporting info
→ details/history
```

Avoid card walls.

---

## P20-009 — CTA Architecture
Priority: P0

Define:

- primary
- secondary
- tertiary
- destructive
- contextual

Normally one primary CTA.

CTA must reflect current lifecycle state.

---

## P20-010 — Feature Communication
Priority: P0

Reusable:

- FeatureIntro
- FeatureHelp
- InfoPopover
- LearnMore

Major features must explain:

- purpose
- benefit
- when to use
- primary action

At minimum:

- Hard Landscape
- Dynamic Rescheduler
- Effective Capacity
- Adaptive Context
- Recovery
- Progress
- AI Proposal
- Workspace
- Knowledge Links
- Canvas

---

## P20-011 — Feature Definition Registry
Priority: P1

Where appropriate, create centralized feature metadata:

```text
id
name
purpose
benefit
when_to_use
primary_action
related_features
```

Avoid duplicated help text.

---

## P20-012 — Progressive Disclosure
Priority: P0

Advanced details hidden until requested.

Example:

```text
Task
[Start]

Why this now?
```

reveals:

- priority
- deadline
- context fit
- capacity fit

---

## P20-013 — Micro-Interaction Language
Priority: P0

Animations serve:

- confirmation
- transition
- orientation
- feedback
- discovery

Not decoration alone.

---

## P20-014 — Interaction Feedback
Priority: P0

Consistent patterns:

```text
Saving → Saved
Offline → Queued → Syncing → Synced
AI → Generating → Validating → Proposal Ready
Task → Completed
Workspace → Context switch
Archive → Archived
```

---

## P20-015 — Tactile Interaction
Priority: P1

Use restrained Neo-Brutalist principles:

- subtle offset
- pressed state
- visible focus
- immediate feedback

Do not apply thick borders to every surface.

---

## P20-016 — Login
Priority: P0

Login is first brand impression.

It must communicate what Kinevo is without marketing overload.

---

## P20-017 — Onboarding
Priority: P0

Teach the mental model, not a feature list.

Preferred:

```text
What are you trying to accomplish?
→ Break it into work
→ Organize knowledge
→ Schedule execution
→ Execute today
```

---

## P20-018 — Today UX
Priority: P0

Today hierarchy:

```text
NOW
NEXT
Later/Timeline
```

Supporting:

- capacity
- recovery
- quick capture
- progress

---

## P20-019 — Goal UX
Priority: P0

Goal must answer:

- What am I trying to achieve?
- How far along am I?
- What is next?
- Can AI help?
- What knowledge supports it?
- What tasks move it forward?

---

## P20-020 — Task UX
Priority: P0

Task detail includes:

- state
- primary action
- planning context
- schedule
- subtasks
- Notes
- Canvas
- activity
- AI/context actions

---

## P20-021 — Knowledge UX
Priority: P0

Notes/Canvas should feel like one Knowledge surface.

Note:

- workspace
- links
- editor
- save state

Canvas:

- workspace
- links
- editor
- save/sync state

---

## P20-022 — Canvas Shell UX
Priority: P1

Canvas can retain a spatial visual grammar.

Kinevo shell provides:

- breadcrumb
- workspace
- save state
- sync state
- conflict state

Do not force Kinevo styling into Excalidraw internals.

---

## P20-023 — Analytics UX
Priority: P0

Every major chart must answer:

- What changed?
- Why?
- Why does it matter?
- What should I do?

---

## P20-024 — Analytics Actionability
Priority: P0

Every important insight should provide an actionable next step.

Example:

```text
Effective Capacity
23h
↓ 8%

Completion rate declined.

Recommendation:
Reduce next week's load by ~3h.

[Review Schedule]
```

---

## P20-025 — AI UX
Priority: P0

AI should feel:

- capable
- transparent
- optional
- controlled

No unexplained "magic".

---

## P20-026 — Workspace UX
Priority: P0

Workspace must communicate:

- current context
- current Goal
- next action

Not become a metric wall.

---

## P20-027 — State-Machine UX
Priority: P0

For:

- Task
- Goal
- Milestone
- Program
- Canvas
- Note
- Schedule
- AI Proposal

define:

- available actions
- unavailable actions
- explanation
- success
- failure

---

## P20-028 — Empty States
Priority: P0

Each empty state explains:

```text
what is empty
why it matters
what to do
```

---

## P20-029 — Error States
Priority: P0

Never show raw:

- HTTP status
- stack trace
- provider response

Show:

- what happened
- what is safe
- what can be done

---

## P20-030 — Offline States
Priority: P0

Distinguish:

- offline
- queued
- syncing
- synced
- conflict
- failed

Do not show false empty data.

---

## P20-031 — Conflict UX
Priority: P0

Versioned rich content must never silently overwrite.

Show:

- local
- server
- changes
- reconciliation choice

---

## P20-032 — Navigation IA
Priority: P0

Preferred groups:

```text
EXECUTE
Today
Week
Calendar

PLAN
Goals
Tasks
Programs

KNOWLEDGE
Notes
Canvas

REVIEW
Analytics

SYSTEM
Settings
```

Workspace stays in shell switcher.

Do not change route names merely for style.

---

## P20-033 — Search/Command Surface
Priority: P1

Where appropriate, provide unified discovery for:

- tasks
- goals
- notes
- canvas
- workspaces

Do not duplicate search systems.

---

## P20-034 — Accessibility
Priority: P0

Target WCAG 2.2 AA.

Verify:

- contrast
- focus
- keyboard
- semantics
- labels
- status announcements
- reduced motion
- touch target sizing

---

## P20-035 — Responsive
Priority: P0

Audit:

- 375px
- 390px
- 412px
- 768px
- 1024px
- 1440px

Critical:

- Login
- Today
- Goal
- Task
- Notes
- Canvas shell
- Analytics
- Workspace
- AI Settings
- Settings

---

## P20-036 — Visual Regression
Priority: P1

Baseline:

- Login
- Today
- Goal
- Task
- Knowledge
- Canvas shell
- Analytics
- Workspace
- AI Settings

Snapshots are intentionally reviewed, never blindly accepted.

---

## P20-037 — Product Voice
Priority: P1

Voice:

- direct
- calm
- intelligent
- non-judgmental
- technical but readable

Avoid:

- developer jargon
- guilt
- vague “AI magic”
- vague “optimize your life” language

---

## P20-038 — Feature Discoverability Audit
Priority: P0

For every major feature ask:

- Can the user find it?
- Can they understand it?
- Can they see when to use it?
- Can they see its outcome?

---

## P20-039 — Cross-Screen Brand Consistency
Priority: P0

Audit:

- spacing
- typography
- colors
- borders
- radius
- shadows
- iconography
- status language
- motion

All surfaces must feel like one product.

---

## P20-040 — Final Product Experience Audit
Priority: P0

Audit:

- Login
- first-run
- Today
- Week
- Calendar
- Goals
- Milestones
- Programs
- Tasks
- Quick Capture
- Notes
- Canvas
- Analytics
- Adaptive Context
- Recovery
- Notifications
- AI
- AI Settings
- Workspace
- Settings

For each verify:

- purpose
- context
- hierarchy
- primary CTA
- feature explanation
- state feedback
- empty/error/offline behavior
- accessibility

# MASTER CROSS-PHASE PRODUCT JOURNEY

The following journey is the ultimate product cohesion test:

```text
Login
↓
Personal Workspace
↓
Create Research Workspace
↓
Switch Research
↓
Create long-horizon Goal
↓
Kinevo explains AI Breakdown
↓
Break down with AI
↓
Remote AI provider
↓
Validated Proposal
↓
Review
↓
Accept
↓
Milestones
↓
Programs
↓
Tasks
↓
Create Note from Task
↓
Create Canvas from Task
↓
Schedule
↓
Today
↓
Start
↓
Complete
↓
Progress
↓
Analytics
↓
Recommendation
↓
Next Action
```

At every point:

- Workspace is understandable.
- Notes remain reachable.
- Canvas remains reachable.
- Global commitments remain respected.
- AI context remains bounded.
- User data remains isolated.
- UI tells the user what to do next.

# MASTER TEST MATRIX

## Technical

- backend tests
- PHPStan
- Pint
- frontend typecheck
- frontend tests
- build
- OpenAPI
- docs validation
- secret scan

## AI

- MockProvider
- invalid credential
- invalid model
- timeout
- rate limit
- unavailable endpoint
- remote smoke test
- no Ollama

## Workspace

- owner scope
- migration
- inheritance
- switch
- archive/restore
- isolation
- deep link
- offline cache scope

## Knowledge/Canvas

- Task → Note
- Task → Canvas
- Goal → Note
- Goal → Canvas
- autosave
- versioning
- offline
- relationship preservation

## Product

- CTA
- feature explanations
- empty states
- error states
- offline states
- conflict states
- accessibility
- responsive
- theme

## Browser

- Chromium
- Firefox
- WebKit

# MASTER SECURITY REQUIREMENTS

No secrets in:

- repository
- frontend bundle
- browser persistence
- logs
- telemetry

No IDOR.

No cross-user workspace leakage.

No cross-workspace leakage.

No unbounded AI context.

No silent AI mutations.

No silent rich-content overwrite.

# MASTER OFFLINE REQUIREMENTS

IndexedDB is cache/queue, not canonical.

PostgreSQL remains authoritative.

Workspace-scoped cache keys must prevent:

```text
Research response
→ Personal screen
```

Workspace switch with unsaved Note/Canvas changes must respect:

- autosave
- dirty state
- queue
- conflicts

Offline unavailable workspace must not appear as an empty workspace.

# MASTER PERFORMANCE REQUIREMENTS

Do not:

- fetch all user data then filter in Vue;
- auto-start Ollama;
- load all analytics at once;
- eagerly load Excalidraw;
- send entire workspaces to AI.

Prefer:

- server-side scope filtering
- lazy loading
- bounded payloads
- existing read models
- existing adapters

# MASTER "DONE" DEFINITION

A task is not DONE because code exists.

DONE requires:

```text
implementation
+
acceptance
+
automated verification
+
browser evidence where applicable
+
documentation
+
TASK evidence
```

# TASK BOARD FORMAT

Every Phase 18/19/20 task must use:

```markdown
### P18/P19/P20-ID — Title
- Status:
- Priority:
- Depends On:
- SRS:
- Design:
- Files:
- Acceptance:
  - [ ]
- Verification:
  - [ ] Unit
  - [ ] Integration
  - [ ] E2E
  - [ ] Browser
- Evidence:
- Notes:
```

No DONE without evidence.

# FINAL RELEASE GATES

## P18 READY

- [ ] Runtime AI configurable
- [ ] Encrypted credentials
- [ ] Raw credential never returned
- [ ] Provider protocol explicit
- [ ] Connection test proves model usability
- [ ] Remote runtime works
- [ ] Ollama optional
- [ ] Goal Breakdown works
- [ ] Browser E2E
- [ ] Security gates

## P19 READY

- [ ] Workspace core
- [ ] Existing data migration
- [ ] Context inheritance
- [ ] Task/Note/Canvas preserved
- [ ] Scheduler integration
- [ ] AI context integration
- [ ] Analytics scope
- [ ] Offline cache scope
- [ ] Browser E2E

## P20 READY

- [ ] Brand formalized
- [ ] Design token architecture
- [ ] Login-to-settings UX audit
- [ ] Feature communication
- [ ] Clear CTA hierarchy
- [ ] Micro-interactions
- [ ] Accessibility
- [ ] Responsive
- [ ] Visual regression
- [ ] Theme
- [ ] Product voice
- [ ] Browser E2E

# FINAL SUCCESS CRITERION

Kinevo must no longer feel like:

```text
Goals
+
Tasks
+
Notes
+
Canvas
+
Analytics
+
AI
```

placed beside each other.

It must feel like:

```text
              KINEVO
                 │
             WORKSPACE
                 │
       ┌─────────┼─────────┐
       │         │         │
   PLANNING  KNOWLEDGE  EXECUTION
       │         │         │
    Goals      Notes      Tasks
    Milestones Canvas     Schedule
    Programs  Search      Today
       │         │         │
       └─────────┼─────────┘
                 │
              PROGRESS
                 │
              ANALYTICS
                 │
                 ▼
                 AI
```

The user should understand:

- what context they are in;
- what outcome they are pursuing;
- what should happen next;
- what knowledge belongs to that context;
- what is scheduled;
- why a task was scheduled;
- what AI can help with;
- whether AI is configured;
- what data will change;
- what changed after an action;
- what to do next.

# FINAL REPORT

For every completed task:

## Task
P18/P19/P20-ID

## Status
DONE / IN_REVIEW / BLOCKED

## Requirements Implemented
...

## Existing Systems Reused
...

## Architecture Impact
...

## Files Created
...

## Files Modified
...

## Database
...

## API
...

## UI/UX
...

## Security
...

## Offline
...

## AI
...

## Workspace
...

## Tests
...

## Browser Evidence
...

## Documentation
...

## Known Limitations
...

## Assumptions
...

## Next READY Task
...

Never claim completion without evidence.

# FINAL AGENT BEHAVIOR

When ambiguity exists:

1. inspect authoritative documents;
2. inspect current implementation;
3. choose the smallest compatible interpretation;
4. record assumption;
5. do not invent functionality.

When a task is already satisfied:

- verify it;
- do not recreate it;
- mark existing implementation/evidence;
- only patch missing behavior.

When a task would create architecture duplication:

- stop;
- identify the existing boundary;
- integrate through it.

When a requirement conflicts with current implementation:

- do not silently overwrite;
- identify the conflict;
- preserve existing behavior unless the authoritative requirement explicitly changes it;
- record the decision.

When a UI feature is technically implemented but users cannot understand it:

- the feature is NOT complete;
- add explanation, hierarchy, CTA, feedback, and discovery.

When AI is unavailable:

- core Kinevo MUST remain usable.

When Ollama is unavailable:

- remote Kinevo runtime AI MUST still be usable when configured.

When Workspace changes:

- Notes and Canvas MUST remain reachable from Task and Goal.

The final product must optimize for:

```text
CLARITY
+
CONTROL
+
CONTEXT
+
COHERENCE
+
TRUST
+
ACTIONABILITY
```

not feature count.
