> ARCHIVED 2026-08-31 (R0): owner planning input (2026-08-29). Decisions were migrated into
> ADR-014, docs/third-party/*, architecture.md §Third-Party, design.md §105, and the P28-TPI
> task records (see docs/roadmap/active/P28-product-experience-closure.md). Historical
> evidence — NOT an execution authority.

# KINEVO — THIRD-PARTY ADOPTION, INTEGRATION & RETENTION UX SPECIFICATION

**Status:** Proposed authoritative implementation specification for third-party adoption after P27.  
**Primary goal:** reuse mature open-source capability without turning Kinevo into a dependency-driven Frankenstein, while making every adopted capability reinforce one coherent, personal, retention-oriented product experience.

---

## 1. EXECUTIVE DECISION

Kinevo should **reuse proven software aggressively, but couple to it selectively**.

The correct rule is:

> **Kinevo owns the product meaning. External projects provide implementation capability.**

The adopted projects are classified into five integration modes:

1. **EMBED** — the project is consumed as a package/component inside Kinevo.
2. **HARVEST** — Kinevo studies and selectively adapts useful patterns/code; no external runtime is introduced.
3. **REIMPLEMENT** — Kinevo implements a compatible concept natively after studying the external architecture.
4. **ADAPTER + SERVICE** — the external system remains a separate service and Kinevo owns the application port/adapter.
5. **REFERENCE ONLY** — architecture/UX/documentation are studied without becoming a runtime dependency.

Kinevo MUST NOT choose integration mode from popularity alone. Every adoption requires a capability, licensing, dependency, resource, failure, and exit-strategy review.

---

## 2. PRODUCT PRINCIPLE: ONE SYSTEM, MANY CAPABILITIES

The user must never perceive:

> Laravel + Vue + Excalidraw + Tiptap + Uppy + Pic Smaller + Lago + OpenPanel + Langfuse + Gotify + GlitchTip.

The user must perceive:

> **Kinevo — one personal system that turns intentions into execution.**

The product chain is:

```text
INTENTION
   ↓
WORKSPACE / CONTEXT
   ↓
GOAL
   ↓
AI BREAKDOWN
   ↓
MILESTONE / PROGRAM
   ↓
TASK
   ↓
SCHEDULE
   ↓
TODAY
   ↓
EXECUTE
   ↓
PROGRESS
   ↓
REVIEW
   ↓
INSIGHT
   ↓
NEXT ACTION / NEXT GOAL
```

External tools must fit into this chain rather than create parallel mental models.

---

## 3. THIRD-PARTY BASELINE

Exact versions/tags/commits must be recorded before adoption. The current research baseline is:

| Project | Capability | Current planning mode | License baseline | Primary decision |
|---|---|---|---|---|
| Excalidraw | Canvas editor | Embedded editor | MIT | **EMBED** |
| Tiptap | Rich text editor | Embedded editor | verify selected packages | **EMBED** |
| Pic Smaller | Image compression | Browser/WASM engine | MIT | **EMBED / ADAPTER** |
| Uppy | File upload UX/transport | Browser library | MIT | **EMBED / ADAPTER** |
| Filament | Laravel admin UI | Laravel package | MIT | **EMBED** |
| Open SaaS | SaaS starter/patterns | Wasp/React/Node/Prisma reference | MIT | **HARVEST / REIMPLEMENT** |
| Gotify | Real-time notification transport | Separate transport | MIT | **ADAPTER + SERVICE** |
| Lago | Billing and usage metering | Separate billing service | AGPLv3 | **ADAPTER + SERVICE / REIMPLEMENT CONCEPTS** |
| OpenPanel | Product analytics | Separate analytics service | AGPL-3.0 | **ADAPTER + SERVICE / REIMPLEMENT CONCEPTS** |
| Langfuse | AI observability | Separate AI-ops service | MIT core; `ee` separately licensed | **ADAPTER + SERVICE** |
| GlitchTip | Error tracking | Separate observability service | MIT baseline | **ADAPTER + SERVICE** |

The exact licensing state must be rechecked for the precise version that Kinevo distributes or deploys.

---

## 4. VERIFIED RESEARCH NOTES

### 4.1 Pic Smaller

The official repository describes Pic Smaller as browser-local batch image compression using Web Workers, WebAssembly, Canvas and browser codecs. It supports common image formats and keeps processing on the user's device. The repository is MIT licensed. 

Kinevo uses it for:

- Notes image optimization;
- Canvas image import optimization;
- future attachment optimization.

Do **not** import the whole Pic Smaller application UI.

### 4.2 Open SaaS

Open SaaS is MIT licensed and includes SaaS-oriented capabilities such as email/social authentication, email sending, background jobs, payments, S3 upload patterns, AI examples and Playwright. Its runtime is Wasp + React + NodeJS + Prisma. 

Kinevo should therefore harvest:

- OAuth flow patterns;
- account linking patterns;
- email verification/recovery UX;
- onboarding patterns;
- billing UX;
- S3 upload UX;
- testing conventions.

Kinevo must **not** introduce Wasp/Prisma/Node as a second application backend merely to obtain those capabilities.

### 4.3 OpenPanel

OpenPanel is a product/web analytics platform. It should be treated as an analytics platform rather than an admin CRUD system. It can receive sanitized product events from Kinevo. 

Because it is AGPL-3.0, do not copy its source into the MIT Kinevo Core without a deliberate licensing decision.

### 4.4 Gotify

Gotify is an MIT-licensed real-time messaging server with WebSocket-based delivery and REST interfaces. 

Kinevo should use it as a transport/provider rather than exposing Gotify's terminology to normal users.

### 4.5 Lago

Lago is an open-source metering/usage-based billing platform covering consumption tracking, subscription management, pricing, payment orchestration and revenue analytics. 

Kinevo should use Lago as infrastructure only after defining the Kinevo-owned billing/entitlement boundary. Lago is AGPLv3.

### 4.6 Filament

Filament is a Laravel-native open-source UI/admin framework and is the appropriate candidate for Kinevo's internal operator console rather than a separate generic admin service.

### 4.7 Uppy

Uppy is an MIT-licensed modular browser uploader supporting previews, restrictions, resumable uploads and integrations such as Tus/S3. 

Kinevo owns authorization, ownership, canonical asset metadata and storage policy.

### 4.8 Langfuse

Langfuse's core is MIT licensed, while the repository's `ee` area uses a separate Enterprise License. The self-hosting documentation distinguishes core OSS functionality from Enterprise features. 

Kinevo must not accidentally depend on Enterprise-only functionality when claiming an OSS-compatible deployment.

### 4.9 GlitchTip

GlitchTip's backend repository is MIT licensed and designed for self-hosting. 

It is appropriate as an external error telemetry service after a Kinevo-owned redaction layer.

---

## 5. INTEGRATION MODES — DETAILED RULES

### 5.1 MODE A — EMBED

Use when the library is explicitly designed for application embedding and its runtime/licensing model fits Kinevo.

Examples:

- Excalidraw;
- Tiptap;
- Uppy;
- Pic Smaller engine/capability;
- Filament.

Requirements:

- Kinevo wraps the external package where meaningful;
- external library types do not leak through the domain layer;
- Kinevo owns persistence and authorization;
- external CSS/theme must not replace Kinevo branding.

### 5.2 MODE B — HARVEST

Use when the external project has useful architecture/UX but an incompatible application stack.

Example: Open SaaS.

Process:

```text
external source
   ↓
study
   ↓
identify reusable capability
   ↓
classify TAKE / ADAPT / REIMPLEMENT / REFERENCE / IGNORE
   ↓
Laravel/Vue-native implementation
```

### 5.3 MODE C — REIMPLEMENT

Use when Kinevo needs only a small/domain-specific subset.

Examples:

- selected Lago billing semantics;
- selected OpenPanel event/aggregation semantics.

Reimplementation must be independent code, not copied source, unless license review explicitly permits the chosen source reuse.

### 5.4 MODE D — ADAPTER + SERVICE

Use for complete infrastructure platforms.

Pattern:

```text
Kinevo domain
    ↓
Kinevo application port
    ↓
Kinevo adapter
    ↓
external service
```

Examples:

- Lago;
- OpenPanel;
- Langfuse;
- Gotify;
- GlitchTip.

### 5.5 MODE E — REFERENCE ONLY

Use when no runtime dependency is justified.

---

## 6. KINEVO OWNERSHIP MATRIX

Kinevo MUST remain authoritative over:

```text
Identity
Workspace
Goal
Milestone
Program
Task
Note
Knowledge
Canvas
Schedule
Progress
Activity
AI proposal semantics
AI entitlement
AI credit semantics
BYOK policy
Subscription semantics
Entitlement semantics
Notification semantics
Privacy policy
Data export
Account deletion
Product event definitions
Customer-facing UX
```

External tools may provide infrastructure:

```text
Excalidraw  → canvas editing
Tiptap      → rich-text editing
Pic Smaller → image compression
Uppy        → upload UX/transport
Filament    → admin UI
Lago        → metering/billing infrastructure
OpenPanel   → product analytics
Langfuse    → AI engineering observability
Gotify      → notification transport
GlitchTip   → error tracking
```

---

## 7. TARGET ARCHITECTURE

```text
                         KINEVO PRODUCT
                              │
                ┌─────────────┴─────────────┐
                │                           │
              Web App                  Android App
                │                           │
                └─────────────┬─────────────┘
                              │
                         Kinevo API
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
     DOMAIN              APPLICATION         INFRASTRUCTURE
        │                     │                     │
        │                     │                     ├── PostgreSQL
        │                     │                     ├── Queue
        │                     │                     ├── Cache
        │                     │                     └── Object Storage
        │                     │
        └─────────────────────┼─────────────────────────────┐
                              │                             │
                              ▼                             ▼
                        Kinevo adapters               Embedded packages
                              │                             │
              ┌───────────────┼───────────────┐             ├── Excalidraw
              │               │               │             ├── Tiptap
              ▼               ▼               ▼             ├── Uppy
           Billing         Analytics       AI Obs            ├── Pic Smaller
              │               │               │             └── Filament
              ▼               ▼               ▼
            Lago          OpenPanel       Langfuse
                              │
                           Gotify
                              │
                          GlitchTip
```

---

## 8. THIRD-PARTY ADOPTION MATRIX FILE

Create and maintain:

`docs/third-party/adoption-matrix.md`

Required columns:

```text
Project
Repository URL
Exact version/tag/commit
Capability
License
Integration mode
Runtime dependency
Files imported
Files adapted
Files reimplemented
Kinevo-owned contract
External-owned behavior
Data exchanged
Failure behavior
Resource profile
Security considerations
Upgrade risk
Exit strategy
Attribution requirement
Commercial distribution implications
```

No new dependency may be introduced without an adoption-matrix entry.

---

## 9. LICENSE / PROVENANCE LEDGER

Update:

`docs/third-party/licenses.md`

`docs/third-party/attributions.md`

For every project record:

- exact version;
- exact license;
- copyright notice;
- source URL;
- whether source code was copied;
- whether it is a package dependency;
- whether it is a deployed service;
- whether it is reference-only.

Do not assume an entire repository has one licensing rule without checking its actual license boundaries.

Langfuse is a required example: core is MIT while the `ee` directory is separately licensed. 

---

## 10. OPEN SaaS HARVEST SPECIFICATION

Audit the current Open SaaS repository for:

```text
OAuth
Email verification
Password reset
Account linking
Email templates
Onboarding
Pricing page UX
Billing UX
S3 upload UX
E2E organization
AI UX
Landing-page composition
Documentation organization
```

Each item must be classified:

```text
TAKE DIRECTLY
ADAPT
REIMPLEMENT
REFERENCE ONLY
IGNORE
```

No Wasp runtime is introduced into Kinevo.

Target implementation:

```text
Open SaaS patterns
      ↓
Laravel Identity / Mail / Queue / Billing adapters
      ↓
Kinevo UX
```

---

## 11. UPLOAD + IMAGE PIPELINE

The canonical pipeline for Notes and Canvas assets is:

```text
User
 ↓
Uppy
 ↓
client validation
 ↓
if image → Pic Smaller
 ↓
Kinevo upload adapter
 ↓
authorization
 ↓
object storage
 ↓
Asset record
 ↓
Note/Canvas reference
```

Required states:

```text
selecting
compressing
uploading
paused
queued
completed
failed
retrying
cancelled
```

No binary payload should be embedded directly inside structured documents when an asset reference is appropriate.

---

## 12. ASSET DOMAIN

Where the existing model is insufficient, introduce a Kinevo-owned `Asset` abstraction.

Minimum conceptual fields:

```text
id
user_id
workspace_id
storage_key
content_type
size_bytes
sha256
width
height
compression_profile
created_at
updated_at
```

Ownership is always user-scoped.

Storage location is infrastructure-specific.

---

## 13. CANVAS + NOTES CONTINUITY

Canvas and Notes are not standalone islands.

Expected relationship examples:

```text
Goal
 ↓
Note
 ↓
Canvas
 ↓
Task
```

and:

```text
Task
 ↓
Note
 ↓
Canvas
```

A Goal page should surface relevant Notes/Canvas/Tasks.

A Task page should surface relevant Goal/Knowledge/Canvas.

A Note page should surface linked Goal/Task/Canvas.

A Canvas page should surface linked Goal/Task/Note.

The user should not have to remember which feature stored the context.

---

# 14. RETENTION UX — CENTRAL PRODUCT SYSTEM

Kinevo retention is NOT primarily:

- streak spam;
- badges;
- artificial urgency;
- manipulative notifications;
- dark patterns.

The primary retention engine is:

```text
Context
  ↓
Continuity
  ↓
Progress
  ↓
Reflection
  ↓
Reduced cognitive overhead
  ↓
Return
```

A retained user should repeatedly experience:

> “Kinevo remembers where I am, understands what I am trying to achieve, helps me decide what matters now, and shows me that I am making progress.”

---

## 15. FIRST SESSION RETENTION

The first session must communicate:

1. What Kinevo is.
2. What a Workspace is.
3. What a Goal is.
4. What AI Breakdown does.
5. Where Tasks come from.
6. What Today means.
7. Why progress matters.

Do not expose every capability at equal visual weight.

Recommended progressive disclosure:

```text
Goal
 ↓
AI Breakdown
 ↓
first Task
 ↓
Today
 ↓
first completion
 ↓
then discover Knowledge / Canvas / Analytics
```

---

## 16. EMPTY STATE AUDIT — RELEASE-CRITICAL

Every empty state MUST answer:

```text
WHAT IS THIS?
WHY DOES IT MATTER?
WHAT CAN I DO?
WHAT SHOULD I DO NEXT?
```

### Goal empty state

```text
You haven't defined a goal yet.

Goals give Kinevo something meaningful to plan toward.

Try:
“Finish my thesis in 4 months.”

[Create Goal]
```

### Task empty state when an active Goal exists

```text
Your goal is ready.
Now define the next action.

[Create Task]
[Break down with AI]
```

### Knowledge empty state

```text
Your knowledge desk connects ideas to the work you're doing.

[Create Note]
```

### Canvas empty state

```text
Use a canvas when the problem is easier to see than to list.

[Create Canvas]
```

### Analytics empty state

```text
Your insights will appear after enough activity is recorded.

Start by moving one goal forward.
[Open Today]
```

Generic “No data” is insufficient unless followed by a useful next step.

---

## 17. PERSONALIZATION RULES

Personalization must be evidence-based.

Allowed signals:

```text
display_name
active_workspace
active_goal
deadlines
today tasks
recent progress
recent activity
local date/time
```

Good:

> Good morning. You have 2 important actions left in Research today.

Bad:

> Your brain is naturally more productive in the morning.

Kinevo must not turn heuristic productivity signals into medical/psychological claims.

---

## 18. FEATURE DISCOVERABILITY

Every major feature must be understandable without external documentation.

The UI should answer:

```text
What is this?
Why should I care?
When should I use it?
What connects to it?
```

Use:

- first-use hints;
- concise helper copy;
- contextual tooltips;
- “Learn more” links.

Do not introduce tooltip spam.

---

## 19. CTA HIERARCHY

Each critical surface gets:

```text
1 primary action
1 secondary action
optional tertiary/details
```

Example Goal:

```text
PRIMARY
[Break down with AI]

SECONDARY
[Add Milestone]

OTHER
Edit / Archive
```

Destructive actions must not compete visually with the primary workflow.

---

## 20. CROSS-FEATURE WORKFLOW AUDIT

The following paths must be browser-validated:

```text
Goal → AI Breakdown
Goal → Milestone
Milestone → Task
Goal → Today
Task → Note
Task → Canvas
Note → Goal
Note → Task
Canvas → Goal
Canvas → Task
Review → Next Goal
```

Any feature that has no meaningful pathway into or out of the core loop is either:

- redesignable;
- intentionally secondary;
- or deferred.

---

## 21. PRODUCT SHELL

There should be one Kinevo user-facing shell.

```text
Kinevo Shell
├── Navigation
├── Topbar
├── Workspace context
├── Search / Command
├── Notifications
├── User menu
└── Theme
```

Third-party screens must never silently replace the shell.

Normal users must not suddenly see Lago/OpenPanel/Gotify/Langfuse terminology unless that view is explicitly intended for them.

---

## 22. THIRD-PARTY UI THEMING

All customer-facing embedded UI must use Kinevo's:

- color tokens;
- typography;
- spacing;
- radii;
- shadows;
- motion;
- interaction semantics.

External branding must not visually dominate Kinevo.

The interface should feel like one product.

---

## 23. MICRO-INTERACTION SYSTEM

Micro-interactions must communicate cause and effect.

### Task completion

```text
click complete
 ↓
clear state transition
 ↓
progress feedback
 ↓
next relevant action
```

### AI Breakdown

```text
generating
 ↓
structured proposal
 ↓
validation complete
 ↓
Review/Accept CTA
```

### Save

```text
Saving…
 ↓
Saved
```

### Offline

```text
Online
 ↓
Offline
 ↓
Queued
 ↓
Syncing
 ↓
Saved
```

Motion must honor `prefers-reduced-motion`.

---

## 24. ANALYTICS UX

Analytics are not a chart gallery.

Every meaningful visualization should answer:

```text
What happened?
What changed?
What matters?
What can I do next?
```

Example:

```text
Goal Progress

+18% this month

You moved 3 active goals.
Research contributed most of the progress.

[Open Research]
```

Charts without interpretation are considered incomplete for critical screens.

---

## 25. PRODUCT ANALYTICS VS AI OBSERVABILITY

These are separate systems.

### OpenPanel

Owns product behavior analytics.

Examples:

```text
signup
onboarding_completed
goal_created
goal_progressed
ai_breakdown_requested
proposal_accepted
subscription_started
wrapped_shared
```

### Langfuse

Owns AI engineering telemetry.

Examples:

```text
trace
generation
model
prompt version
tokens
latency
evaluation
```

### Kinevo AI Ledger

Owns customer-facing billing/usage truth.

Never make OpenPanel or Langfuse the source of entitlement/credit truth.

---

## 26. BILLING / LAGO / MIDTRANS BOUNDARY

Kinevo owns:

```text
plan meaning
subscription meaning
entitlement
BYOK policy
customer-facing billing state
```

Midtrans owns/payment-processes the actual Indonesian payment transaction.

Lago may own/assist with:

```text
usage metering
billing calculations
invoice infrastructure
subscription/charge infrastructure
```

but Kinevo must define the source-of-truth boundary explicitly.

Midtrans Sandbox success is development evidence, not production certification.

Before production verify:

- merchant activation;
- supported payment methods;
- recurring capability if used;
- webhook signatures;
- retries;
- idempotency;
- refunds;
- cancellations;
- production credentials.

---

## 27. AI ECONOMICS BOUNDARY

Hosted AI:

```text
Kinevo pays provider
→ Kinevo meters usage
→ Kinevo consumes included/paid AI allowance
```

BYOK:

```text
User provider credential
→ provider bills user
→ Kinevo hosted AI credits are NOT consumed
```

Both must have:

- token limits;
- context limits;
- request limits;
- rate limits;
- timeout;
- abuse protection.

The request firewall must run before provider invocation.

---

## 28. AI USAGE FIREWALL

Required flow:

```text
Authenticate
 ↓
Entitlement
 ↓
AI allowance
 ↓
Rate limit
 ↓
Request budget
 ↓
Context/token guard
 ↓
Provider
 ↓
Actual usage settlement
```

When the preflight budget fails:

> **Do not call the provider.**

Preferred pattern:

```text
reserve maximum permitted budget
 ↓
call provider
 ↓
measure actual usage
 ↓
settle actual cost
 ↓
release unused reservation
```

---

## 29. PROVIDER PRICE CATALOG

Never hardcode model prices in feature code.

Required conceptual fields:

```text
provider
model
currency
input_rate
cached_input_rate
output_rate
effective_from
effective_until
pricing_version
source
```

Historical usage must remain reproducible after price changes.

---

## 30. AI COST SIMULATOR

Before finalizing included AI allowance numbers, simulate:

```text
Free
Pro
Power
```

under:

```text
P50
P75
P90
P95
P99
abuse/heavy-user
```

Inputs:

```text
provider
model
feature
request count
input tokens
cached input tokens
output tokens
cache ratio
```

Outputs:

```text
provider COGS
Kinevo normalized cost
included budget exposure
overage exposure
contribution margin
```

Required feature profiles:

```text
Goal Breakdown
Task Extraction
Note Summary
Planning
Deep Analysis
Wrapped Narrative
```

---

## 31. NOTIFICATION ARCHITECTURE

Kinevo owns notification semantics:

```text
Notification
NotificationPreference
NotificationEvent
```

Gotify is a transport/provider.

Architecture:

```text
Kinevo Notification Domain
      ↓
NotificationProvider
      ↓
Gotify
```

Future providers can be inserted without rewriting the notification domain.

Channels should conceptually separate:

```text
security
billing
productivity
marketing
```

Marketing notifications require appropriate consent/policy handling.

---

## 32. ADMIN ARCHITECTURE

Filament is the preferred Kinevo admin UI.

Suggested operator navigation:

```text
Operations
├── Overview
├── Users
├── Subscriptions
├── Entitlements
├── AI Usage
├── Billing
├── Email
├── Health
├── Feature Flags
├── Incidents
└── Audit Log
```

Admin actions should call Kinevo application services rather than directly mutating Eloquent state where business rules exist.

No v1 impersonation unless explicitly approved later.

---

## 33. ADMIN PRIVACY

Admins must not casually see:

```text
passwords
raw tokens
provider secrets
BYOK plaintext credentials
raw Notes
raw Canvas documents
raw AI prompts
```

Aggregated operational metrics are preferred.

---

## 34. ERROR TRACKING

GlitchTip integration:

```text
Kinevo
 ↓
redaction layer
 ↓
GlitchTip
```

Allowed safe metadata:

```text
request_id
app_version
environment
route
exception_type
safe context
```

Forbidden:

```text
password
session token
API key
BYOK secret
payment secret
private note contents
raw canvas document
```

GlitchTip outage must not break the customer application.

---

## 35. EXTERNAL SERVICE FAILURE POLICY

### OpenPanel unavailable

Kinevo continues.

### Langfuse unavailable

AI may continue; observability is degraded.

### Gotify unavailable

In-app notifications continue; delivery retries where configured.

### GlitchTip unavailable

Application continues.

### Lago unavailable

Billing must fail safe; do not fabricate entitlements.

### Storage unavailable

Upload fails visibly and offers retry/recovery.

Observability failures must not silently corrupt business data.

---

## 36. DEVELOPMENT RESOURCE PROFILES

Do NOT automatically start all external services.

Recommended Docker profiles:

```text
core
billing
analytics
ai-observability
notifications
error-tracking
```

Default development should run only the minimum needed for the current task.

Example conceptual commands:

```bash
make up
make infra-up BILLING=1
make infra-up ANALYTICS=1
make infra-up AI_OBS=1
make infra-up NOTIFICATIONS=1
make infra-up ERRORS=1
```

Local Ollama must never start because it happens to be installed on the developer machine. Active AI provider selection must be explicit.

This is a hard requirement because the developer environment should not silently consume CPU/RAM/GPU simply due to an installed local model.

---

## 37. DEPENDENCY RESOURCE BUDGET

Every external service records:

```text
CPU
RAM
disk
network
startup time
backup requirements
failure impact
upgrade complexity
```

Classify:

```text
always-on
optional
dev-only
cloud-only
local-only
```

Do not run heavyweight services on a low-resource VPS simply because they are open-source.

---

## 38. OPEN-SOURCE REPOSITORY STRATEGY

Target repositories:

```text
github.com/sedam-or/Kinevo
    → public Kinevo Core

github.com/sedam-or/kinevo-cloud
    → private hosted SaaS / cloud control plane

github.com/sedam-or/kinevo-site
    → public product marketing website
```

Kinevo Core should remain genuinely self-hostable.

Kinevo Cloud provides managed convenience such as:

- hosted infrastructure;
- managed billing;
- managed AI;
- operational tooling;
- managed backup/sync;
- support.

The OSS edition must not be intentionally crippled merely to force conversion.

---

## 39. REPOSITORY MIGRATION RULE

Migration MUST occur in this order:

```text
freeze
 ↓
inventory
 ↓
ownership classification
 ↓
dependency graph
 ↓
create destination repos
 ↓
migrate/copy
 ↓
establish package/API boundaries
 ↓
test destinations
 ↓
validate builds
 ↓
license audit
 ↓
link audit
 ↓
compare behavior
 ↓
publish migration notes
 ↓
only then remove/archive obsolete files
```

Never delete first and discover dependencies afterward.

---

## 40. DOCUMENT MIGRATION POLICY

For every current documentation asset:

```text
SRS.md
TASK.md
AGENTS.md
architecture docs
design docs
billing docs
AI docs
third-party docs
```

classify:

```text
public product contract
public contributor documentation
internal engineering control
historical record
cloud-private
obsolete
```

Do not blindly delete SRS/TASK/AGENTS.

First classify, migrate/archive, update references, then remove only when safe.

---

## 41. PRODUCT WEBSITE

Target domains:

```text
https://kinevo.app
        → product marketing

https://app.kinevo.app
        → application

https://docs.kinevo.app
        → documentation

https://status.kinevo.app
        → optional public status
```

Do not claim these domains are operational until DNS/deployment evidence exists.

---

## 42. LANDING PAGE PRODUCT FUNNEL

The product website should communicate transformation, not a feature dump.

Recommended sequence:

```text
Problem
 ↓
Kinevo promise
 ↓
How it works
 ↓
Goal → AI Breakdown → Task → Today
 ↓
Workspace
 ↓
Knowledge / Canvas
 ↓
Progress / Analytics
 ↓
Wrapped
 ↓
Open-source philosophy
 ↓
Pricing
 ↓
FAQ
 ↓
CTA
```

Hero should communicate the intention→execution value proposition.

---

## 43. PRICING POSITIONING

Launch pricing remains:

```text
FREE   = Rp0 / month
PRO    = Rp49.900 / month
POWER  = Rp89.900 / month
```

Positioning:

```text
FREE
Discover Kinevo.

PRO
Make Kinevo part of your workflow.

POWER
Make Kinevo deeply personal.
```

Power must be differentiated by:

- capacity;
- AI allowance;
- depth of analytics/history;
- advanced reflection;
- richer Wrapped/share capabilities;
- justified convenience.

No Teams/RBAC/enterprise in this phase.

---

## 44. AI CREDITS / AI BALANCE

Kinevo AI credits are an internal economic abstraction.

Tokens are telemetry.

Provider cost is the economic input.

Do not define:

```text
1 credit = X tokens
```

as the fundamental pricing rule.

If prepaid AI balance exists:

```text
Subscription ledger
+
AI usage ledger
```

must remain separate.

---

## 45. CUSTOMER-FACING AI USAGE

Suggested display:

```text
AI Usage

Included
73 / 150 credits

Hosted AI
estimated usage: RpX

BYOK
Active
```

Optional technical detail:

```text
Input tokens
Cached input tokens
Output tokens
Estimated provider cost
Kinevo usage charge
```

Do not force ordinary users to understand provider-infrastructure terminology.

---

## 46. RETENTION LOOPS

### Daily

```text
Today
 ↓
NOW
 ↓
Start
 ↓
Complete
 ↓
Progress feedback
 ↓
Next action
```

### Weekly

```text
Review
 ↓
What moved?
 ↓
What stalled?
 ↓
What needs adjustment?
 ↓
Next-week plan
```

### Long-term

```text
months of execution
 ↓
progress history
 ↓
insight
 ↓
Wrapped
 ↓
reflection
 ↓
next goal
```

---

## 47. WRAPPED INTEGRATION

Wrapped should be a product reflection layer, not a random chart generator.

Data sources:

```text
Goals
Milestones
Tasks
Progress Events
Activity Logs
Focus Sessions
Scheduling outcomes
Workspace context
```

Output:

```text
achievements
patterns
progress
reflection
next direction
```

Sharing is:

```text
OFF by default
 ↓
Preview
 ↓
Privacy summary
 ↓
Confirm
 ↓
Share
```

No raw Notes/Canvas/private task content may leak through the share artifact.

---

## 48. PRODUCT EVENT TAXONOMY

Kinevo product analytics events should be semantic and content-minimal.

Minimum event set:

```text
signup
verification_completed
onboarding_completed
workspace_created
goal_created
goal_progressed
ai_breakdown_requested
ai_proposal_accepted
task_completed
review_opened
wrapped_opened
wrapped_shared
upgrade_started
subscription_activated
```

Never send raw note/canvas content merely to create an analytics event.

---

## 49. NORTH STAR

Recommended North Star Metric:

> **Weekly Goal Progressing Users (WGPU)**

Definition:

> unique users in a seven-day window who perform at least one meaningful progress action on one or more active Goals.

Secondary metrics:

```text
Goal-to-Execution Rate
Activation Rate
D7 retention
D30 retention
Free → Pro conversion
Pro → Power conversion
AI COGS/user
Wrapped share rate
```

---

## 50. THIRD-PARTY UX RETENTION RULE

A third-party dependency is considered product-successful only if it improves at least one of:

```text
clarity
speed
reliability
continuity
reduced effort
trust
```

and does not increase:

```text
confusion
navigation fragmentation
visual inconsistency
data uncertainty
operational fragility
```

For example:

- Uppy succeeds if users understand upload state and recover from network failures.
- Pic Smaller succeeds if image handling becomes faster/smaller without visible quality loss.
- Excalidraw succeeds if visual thinking feels native to Kinevo.
- Lago succeeds if billing becomes reliable without exposing billing infrastructure terminology.
- OpenPanel succeeds if the team learns what users do without polluting the product with analytics tooling.
- Langfuse succeeds if AI quality/cost can be improved without becoming part of the customer mental model.
- Gotify succeeds if notifications become reliable without making Kinevo feel like a messaging app.

---

## 51. EXIT STRATEGY

Every external system must answer:

```text
If the project disappears tomorrow, what happens?
```

Required:

```text
Kinevo adapter contract
stored-data format
exportability
replacement difficulty
migration procedure
```

Examples:

```text
Uppy       → replace upload UI/transport layer
Pic Smaller→ replace compression engine
Filament   → rebuild admin UI over Application services
Gotify     → replace NotificationProvider
OpenPanel  → send same event taxonomy elsewhere
Langfuse   → retain Kinevo AI ledger independently
Lago       → retain Kinevo billing/usage contract independently
Excalidraw → document scene-format migration difficulty explicitly
```

---

## 52. TESTING STRATEGY

Every third-party integration uses four levels where applicable:

```text
UNIT
↓
contract/adapter
↓
integration
↓
E2E/browser/device
```

The external service should NOT be required for the default unit-test suite.

Use mocks/fakes for normal CI and dedicated integration environments for external-service verification.

---

## 53. THIRD-PARTY ADOPTION DOD

A third-party task is DONE only when:

```text
[ ] capability verified
[ ] exact version recorded
[ ] license recorded
[ ] integration mode recorded
[ ] Kinevo-owned interface exists
[ ] ownership boundaries documented
[ ] failure behavior defined
[ ] tests exist
[ ] integration evidence exists where appropriate
[ ] browser/device evidence exists where relevant
[ ] docs updated
[ ] attribution updated
[ ] resource profile documented
[ ] exit strategy documented
[ ] TASK.md evidence recorded
```

---

## 54. UX AUDIT DOD

P28 UX audit is DONE only when:

```text
[ ] every major screen has a purpose
[ ] every critical screen has clear CTA hierarchy
[ ] every major empty state explains purpose and next step
[ ] loading states are meaningful
[ ] error states provide recovery
[ ] offline states explain queued/sync behavior
[ ] workspace context is visible
[ ] user personalization is evidence-based
[ ] cross-feature connections are visible
[ ] analytics provide interpretation
[ ] third-party UI is visually unified
[ ] keyboard/accessibility baseline passes
[ ] reduced-motion behavior works
[ ] real-browser evidence exists
```

---

## 55. RETENTION FAILURE CONDITIONS

The UX gate fails if:

```text
A new user reaches a blank screen and doesn't know what to do.

A Goal exists but AI Breakdown is difficult to find.

AI Breakdown succeeds but gives no obvious next action.

Task exists but its relationship to Goal is unclear.

Today shows tasks without explaining why they are there.

Notes feel disconnected from actual work.

Canvas feels like a separate product.

Analytics show graphs without interpretation.

Wrapped shows numbers without a meaningful story.

Pricing does not explain Pro vs Power.
```

These are product defects, not optional polish.

---

## 56. IMPLEMENTATION ORDER

Recommended order:

### Stage 1 — Foundation / embedded wins

```text
Uppy
Pic Smaller
Filament
```

### Stage 2 — Adapter contracts

```text
BillingAdapter
AnalyticsAdapter
AIObservabilityAdapter
NotificationProvider
ErrorTelemetryAdapter
Storage/AssetStorage
```

### Stage 3 — Service integrations

```text
Lago
OpenPanel
Langfuse
Gotify
GlitchTip
```

### Stage 4 — Pattern harvesting

```text
Open SaaS
```

### Stage 5 — UX integration

```text
empty states
personalization
cross-feature continuity
pricing UX
onboarding
micro-interactions
retention loops
```

This order prevents infrastructure work from becoming detached from product value.

---

## 57. TASK BOARD ADDITIONS

Add a dedicated foundation slice:

```text
P28-TPI-001 — Third-party adoption matrix
P28-TPI-002 — License/provenance audit
P28-TPI-003 — Integration mode classification
P28-TPI-004 — Capability ownership map
P28-TPI-005 — Adapter contract inventory
P28-TPI-006 — Dependency resource budget
P28-TPI-007 — Development profiles
P28-TPI-008 — Exit strategy matrix
P28-TPI-009 — Third-party UX consistency audit
P28-TPI-010 — Retention workflow continuity audit
```

Every capability-specific task must depend on the relevant TPI foundation task.

---

## 58. FILE-LEVEL EXPECTATIONS

Expected additions/changes may include:

```text
docs/third-party/adoption-matrix.md
docs/third-party/licenses.md
docs/third-party/attributions.md

docs/integrations/billing.md
docs/integrations/analytics.md
docs/integrations/ai-observability.md
docs/integrations/notifications.md
docs/integrations/uploads.md
docs/integrations/error-tracking.md

docs/ux/empty-states.md
docs/ux/retention-journeys.md
docs/ux/information-architecture.md
docs/ux/state-matrix.md
```

Reuse existing documents when they already own the subject. Do not create duplicate authorities.

---

## 59. REPOSITORY BOUNDARY RULE

### Kinevo Core

Must contain:

```text
core domain
core application
core frontend
self-hosting
embedded packages required by core
```

### Kinevo Cloud

May contain:

```text
cloud billing operations
managed AI infrastructure
managed analytics integration
managed observability
commercial administration
email provider configuration
cloud-only secrets/config
```

### Kinevo Site

Contains:

```text
landing
pricing
marketing content
FAQ
product education
SEO
conversion funnel
```

Open SaaS is not copied wholesale into any of these repositories.

---

## 60. NO-FRANKENSTEIN RULE

Reject any adoption if it would create:

```text
a second backend

a second source of truth

a second identity system

a second billing meaning

a second analytics event definition

a second notification domain

a second storage authority
```

unless the architecture decision explicitly documents why the duplication is necessary.

---

## 61. NO-GREENFIELD-IF-MATURE-CAPABILITY-EXISTS RULE

Before writing a new subsystem, the agent MUST check this specification and the adoption matrix.

If an adopted project already supplies the necessary capability, the agent must either:

- integrate it;
- adapt its pattern;
- or document why it is unsuitable.

The agent must not build a competing subsystem simply because writing it from scratch seems straightforward.

---

## 62. NO-DEPENDENCY-FOR-FEATURE-COUNT RULE

Do not add a project merely because it has more features.

The dependency must solve a real current problem.

The adoption decision must include:

```text
capability value
integration cost
resource cost
maintenance burden
license risk
security risk
exit difficulty
```

---

## 63. FINAL PRODUCT EXPERIENCE TEST

A newly registered user should be able to accomplish the following without developer explanation:

```text
understand Kinevo
 ↓
create workspace
 ↓
create Goal
 ↓
find AI Breakdown
 ↓
review proposal
 ↓
accept proposal
 ↓
see milestones/tasks
 ↓
see Today
 ↓
start task
 ↓
complete task
 ↓
see progress
 ↓
connect a Note
 ↓
open Canvas when useful
 ↓
understand Analytics
 ↓
review progress
 ↓
return tomorrow
```

If the workflow breaks because the user does not understand the purpose of a feature, the problem is classified as a UX/product defect, not as a documentation problem.

---

## 64. FINAL RETENTION TEST

Ask five questions for every feature:

1. Does this help the user make progress?
2. Does it reduce cognitive overhead?
3. Does it connect to existing context?
4. Does it create a useful next action?
5. Does it create a reason to return based on real value?

If the answer is “no” to all five, the feature should not be allowed to dominate the product shell.

---

## 65. FINAL ARCHITECTURAL PRINCIPLE

```text
USE THE TOOL
where the tool should own the problem.

USE AN ADAPTER
where Kinevo must own the contract.

HARVEST THE PATTERN
where the external architecture does not fit.

REIMPLEMENT
where the capability is small or domain-specific.

KEEP IT OUT
when it adds more complexity than value.
```

And:

> **Reuse as much as possible; couple as little as possible.**

---

## 66. FINAL RELEASE GATE

Before claiming the third-party adoption program is complete:

```text
[ ] third-party adoption matrix complete
[ ] license/provenance audit complete
[ ] all embedded dependencies verified
[ ] all service dependencies verified
[ ] adapter contracts verified
[ ] asset/upload pipeline verified
[ ] billing boundary verified
[ ] product analytics boundary verified
[ ] AI observability boundary verified
[ ] notification boundary verified
[ ] error telemetry boundary verified
[ ] repository boundaries verified
[ ] migration evidence verified
[ ] UX audit complete
[ ] empty-state audit complete
[ ] personalization audit complete
[ ] cross-feature workflow audit complete
[ ] retention loops browser-verified
[ ] no third-party UI breaks Kinevo branding
[ ] no external outage corrupts core data
[ ] exit strategies documented
```

---

## 67. SOURCES

Primary sources reviewed for the current baseline:

- Pic Smaller official repository and README/license. 
- Open SaaS official repository. 
- OpenPanel official repository. 
- Gotify official repository. 
- Lago official repository. 
- Filament official repository. 
- Uppy official repository and uploader documentation. 
- Langfuse official repository and `ee/LICENSE`. 
- GlitchTip official repository and license. 

## 68. LEGAL DISCLAIMER

This document is engineering/planning guidance, not legal advice. Before distributing combined software, modified third-party source, self-hosted bundles, or commercial deployments, conduct a formal license/compliance review against the exact versions, files, and distribution model used.

# END OF KINEVO THIRD-PARTY ADOPTION, INTEGRATION & RETENTION UX SPECIFICATION
