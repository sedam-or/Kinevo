# Third-Party Adoption Matrix

> **Status:** PLANNING BASELINE — 2026-08-29. Source of requirements:
> `KINEVO_THIRD_PARTY_ADOPTION_INTEGRATION_AND_RETENTION_UX_SPEC.md` (§3, §5–§8).
> Decision record: `docs/adr/ADR-014-third-party-adoption-strategy.md`.
>
> **Rule:** No new dependency may be introduced without a row here. Every row
> starts as a planning baseline; the entry becomes **binding** only when the
> exact version/tag/commit, license re-check, and Kinevo-owned contract are
> recorded at adoption time (per `docs/third-party/licenses.md` rules).

## Integration modes

| Mode | Meaning | Kinevo rule |
|---|---|---|
| EMBED | Consumed as package/component inside Kinevo | Wrap behind a Kinevo boundary; no type/CSS leakage into the domain layer; Kinevo owns persistence + authorization; external branding must not dominate |
| HARVEST | Study external architecture/UX, adapt patterns; no runtime introduced | Classify each item TAKE / ADAPT / REIMPLEMENT / REFERENCE / IGNORE before implementation |
| REIMPLEMENT | Native implementation after studying the external concept | Independent code — never copied source unless a license review explicitly permits it |
| ADAPTER + SERVICE | External system stays a separate service | Kinevo owns the application port and adapter; domain never imports SDK types |
| REFERENCE ONLY | Studied, not adopted | No runtime, no vendored code |

## Matrix (baseline rows)

| # | Project | Capability | License baseline | Integration mode | Runtime dependency | Kinevo-owned contract | External-owned behavior | Failure behavior (service) | Exit strategy |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Excalidraw | Canvas editor | MIT | EMBED | npm package (`@excalidraw/excalidraw`) | `CanvasAdapter` + canvas domain model (ADR-005); scene persistence owned by Kinevo | Drawing/canvas editing UX | n/a (embedded; crashes isolated to editor surface) | Documented scene-format migration difficulty |
| 2 | Tiptap | Rich text editor | MIT (per-package check required) | EMBED | npm packages (`@tiptap/*`) | `EditorAdapter` (TASK-031); document JSON model owned by Kinevo (SRS §10.2) | Editing UX/commands | n/a (embedded) | Swap editor behind adapter; JSON is the portable artifact |
| 3 | Pic Smaller | Browser-local image compression | MIT | EMBED (engine) / ADAPTER | WASM/WebWorker engine package or vendored build | Upload/asset pipeline contract (SRS FR-65); compression profile owned by Kinevo | Compression execution | Compression failure → upload proceeds with original bytes + visible notice | Replace compression engine; assets unaffected |
| 4 | Uppy | File upload UX/transport | MIT | EMBED / ADAPTER | npm package | `AssetStorage` port + Asset record (SRS FR-65); authorization + ownership always Kinevo | Upload UX, resumable transport | Upload fails visibly with retry/recovery (SRS FR-65) | Replace upload UI/transport layer; storage keys owned by Kinevo |
| 5 | Filament | Laravel admin UI | MIT | EMBED | composer package | Admin actions call Kinevo application services (never raw Eloquent mutations for business rules) | Admin panel rendering/CRUD scaffolding | n/a (embedded) | Rebuild admin UI over the same application services |
| 6 | Open SaaS | SaaS starter/patterns | MIT | HARVEST (no runtime) | **None** (Wasp/React/Node/Prisma stack NOT introduced) | Kinevo identity/mail/queue/billing adapters + Kinevo UX | n/a | n/a | n/a — patterns only |
| 7 | Gotify | Real-time notification transport | MIT | ADAPTER + SERVICE | Docker service (optional profile) | `NotificationProvider` port; Notification/NotificationPreference/NotificationEvent domain owned by Kinevo (SRS FR-66) | WebSocket/REST message delivery | In-app notifications continue; delivery retries where configured | Replace `NotificationProvider` implementation |
| 8 | Lago | Usage metering/billing infrastructure | AGPLv3 | ADAPTER + SERVICE (concepts may be REIMPLEMENTED) | Docker service (optional profile) | Billing/entitlement boundary owned by Kinevo (plan/subscription/entitlement meaning; `docs/billing.md`) | Metering, billing calculations, invoice/subscription infrastructure | Billing fails safe; entitlements never fabricated | Retain Kinevo billing/usage contract independently; adapter swap |
| 9 | OpenPanel | Product analytics | AGPL-3.0 | ADAPTER + SERVICE (concepts may be REIMPLEMENTED) | Docker service (optional profile) | Product event taxonomy owned by Kinevo (SRS FR-69); AI ledger NEVER sourced from it | Event ingestion/aggregation UI | Kinevo continues when unavailable | Send same event taxonomy to another sink |
| 10 | Langfuse | AI observability | MIT core; `ee/` separately licensed | ADAPTER + SERVICE | Docker service (optional profile) | AI usage ledger (billing truth) owned by Kinevo; trace/telemetry is not entitlement truth | Trace/generation/latency capture | AI continues; observability degraded | Retain Kinevo AI ledger independently |
| 11 | GlitchTip | Error tracking | MIT baseline | ADAPTER + SERVICE | Docker service (optional profile) | Redaction layer owned by Kinevo (safe metadata only — SRS §15/NFR-03) | Exception aggregation UI | Application continues when unavailable | Point telemetry at an alternative collector |

## Baseline detail — fields that bind at adoption

For every row above, these fields MUST be filled when the dependency is
actually introduced (they cannot be truthfully recorded in advance):

```text
Exact version/tag/commit   — from composer.lock / package-lock.json / git tag / image digest
Files imported             — package boundaries actually used
Files adapted              — modified copies (requires per-file license note)
Files reimplemented        — pointer to independent Kinevo implementation
Data exchanged             — payload shapes crossing the adapter
Resource profile           — CPU/RAM/disk/network/startup/backup/failure impact/upgrade complexity
Security considerations    — secrets exposure, network surface, PDP/privacy review
Upgrade risk               — breaking-change history + pinned-version policy
Attribution requirement    — notices added to docs/third-party/attributions.md
Commercial distribution    — license implications for Kinevo Cloud (hosted) distribution
```

## License boundary warnings (from spec §4/§9)

- **Lago, OpenPanel are AGPL** — no source may be copied into Kinevo Core
  (MIT) without a deliberate, documented licensing decision. Adapter calls
  over the network are the default safe boundary.
- **Langfuse core is MIT, `ee/` is not** — self-hosted deployment MUST verify
  it enables only core OSS functionality when claiming OSS-compatible setup.
- **Tiptap is multi-package** — verify the license of every selected package at
  adoption (baseline check covers `@tiptap/core` chain only).

## P28-TPI-000 inspection results (source-verified 2026-08-29)

Result of the required FIRST execution task (TASK.md P28-TPI-000): repository
reality audit — every existing Kinevo capability inspected in source, every
planned project re-classified, duplicate risks enumerated, target task mapping
completed. Documentation only: no production change, no dependency installed.

### Per-project classification

Legend: ✅ EXISTS_AND_COMPATIBLE · 🟡 PARTIAL · ⛔ MISSING · 🔄 NEAR_DUPLICATE.
Source evidence is the primary classification input (implementation > docs).

| # | Project | Inspected current state (source evidence) | Class | Gap to close | Decision (one primary mode) |
|---|---|---|---|---|---|
| 1 | Excalidraw | `@excalidraw/excalidraw ^0.18.1` (package.json); `canvas/CanvasHost.vue` Vue→React boundary; `CanvasAdapter` (ADR-005); canvas domain (AddCanvasFileUseCase, ListCanvasFilesUseCase in CanvasController); canvas_documents + canvas_files migrations | ✅ | none — already EMBED with Kinevo-owned persistence | EMBED (maintain) |
| 2 | Tiptap | `@tiptap/* 3.30.2` (package.json); `editor/EditorHost.vue`; document JSON owned by Kinevo (note/NoteEditView) | ✅ | none — already EMBED | EMBED (maintain) |
| 3 | Pic Smaller | no image-processing code anywhere (no Intervention, sharp, WebP, resize) | ⛔ | image pipeline completely absent | EMBED/ADAPTER behind `ImageCompressionProvider` (P30) |
| 4 | Uppy | attachments exist but minimal: AttachmentController + `Application/Attachments/{Upload,Delete,Get,List}TaskAttachmentUseCase`; `Domain/Attachments/AttachmentRule` (3 max / 5 MB / JPG/PNG/PDF); `AttachmentList.vue`; storage default `local` (S3 disk configured in filesystems.php, not active) | 🟡 | no resumable/UX transport; upload only task-scoped; storage pivot | EMBED/ADAPTER behind `AssetStorage` (P30) |
| 5 | Filament | no admin UI; DiagnosticsPanel.vue + /dev/canvas-diagnostics are dev-only diagnostics | ⛔ | admin console absent | EMBED (P34 admin) — only after resource+privacy plan |
| 6 | Open SaaS | identity core real: Register/Login/Logout UserUseCase; Sanctum; single-owner gate; EnsureDefaultWorkspace; ProfileSettings. Email: MISSING (config/mail.php defaults `log`; zero Mailables / `Mail::` / notify in app/). OAuth: MISSING (no Socialite). Reset: MISSING (MustVerifyEmail commented out) | 🟡 | email verification, password reset, OAuth absent | HARVEST patterns only (P29) — email/OAuth reimplemented, never adopted |
| 7 | Gotify | in-app notifications real: Notification model; `Application/Notifications/{List,MarkNotificationRead}UseCase`; NotificationController routes; NotificationCenter.vue | 🟡 | external push transport absent (no FCM/APNS/Gotify client) | ADAPTER + SERVICE behind `NotificationProvider` (P34) |
| 8 | Lago | billing real: BillingController; `Application/Billing/BillingService`; MidtransGateway (Infrastructure/Billing) + config/billing.php + Domain/Billing/BillingEventType; billing tables migration (2026_08_26_110000); BillingReconcileCommand; `Application/Saas/EntitlementService` | 🟡 | Midtrans gate exists; usage-metering/invoice infra for scale absent | ADAPTER + SERVICE / REIMPLEMENT concepts (P32 billing boundary first) |
| 9 | OpenPanel | product analytics derive from domain tables (AnalyticsView + /analytics endpoints); NO product event taxonomy; ActivityLog model + controller exist for export only | 🟡 | raw product-event stream + taxonomy + sink absent | ADAPTER + SERVICE / REIMPLEMENT concepts (P31 taxonomy first) |
| 10 | Langfuse | AI ledger real: `Domain/Ai/BillingLedger`; EloquentAiRunRepository; add_ai_run_billing_ledger migration; AiCreditGuard; AiCostAlertService; AIUsageSummaryCard.vue | 🟡 | trace/generation/latency telemetry absent; ledger is billing truth, not telemetry | ADAPTER + SERVICE behind `AIObservabilityProvider` (P31) |
| 11 | GlitchTip | no error telemetry (no Sentry/GlitchTip/bugsnag; default exception handler) | ⛔ | error capture + redaction layer absent | ADAPTER + SERVICE behind `ErrorTelemetryProvider` (P34) |

### Capability inventory (spec §2/§6 list)

Classification of every Kinevo capability the program must not duplicate.

| Capability | Class | Evidence |
|---|---|---|
| identity/auth core | ✅ | AuthController; Application/Identity/*; Sanctum |
| email delivery | 🟡 | config/mail.php only — zero consumers |
| OAuth | ⛔ | absent |
| attachments (task) | ✅ | Application/Attachments + AttachmentRule |
| object storage | 🟡 | disk config present; `local` default; no migration hook |
| notes attachments | ⛔ | notes have no attachment path |
| canvas files | ✅ | canvas_files migration + CanvasController use cases |
| upload handling | 🟡 | minimal; no resumable/UX layer |
| image processing | ⛔ | absent |
| admin/back-office | ⛔ | absent (dev diagnostics only) |
| billing (Midtrans) | ✅ | MidtransGateway + BillingService + tables + reconcile |
| usage metering/invoice infra | 🟡 | absent beyond Midtrans gate |
| entitlement | ✅ | EntitlementService; plan overview use case |
| AI usage/economic ledger | ✅ | BillingLedger + ai_runs + credit guard + cost alert |
| AI observability traces | 🟡 | absent (ledger != telemetry) |
| product analytics events | 🟡 | derived views only; no raw event taxonomy |
| notifications (in-app) | ✅ | Notification domain + center + controller |
| notification push transport | 🟡 | absent |
| error telemetry | ⛔ | absent |
| docker/infrastructure | 🟡 | compose: app/postgres/ollama only; no admin/monitoring services |
| android/native shell | ✅ | NativeComponents/* + routes/native.php + KinevoApi |

### Duplicate-risk list (execution contract §14)

Adoption MUST NOT duplicate an existing capability. Current duplicate risks:

1. **Billing meaning**: Lago would appear to duplicate BillingService + MidtransGateway —
   resolved by decision: Lago/entitlement boundary only for metering/invoice scale beyond
   Midtrans; Kinevo keeps plan/subscription/entitlement meaning (docs/billing.md).
2. **Notification domain**: Gotify must never become a source of notification semantics —
   it is transport-only behind NotificationProvider; Notification/NotificationPreference
   classes remain Kinevo-owned.
3. **Analytics event meaning**: OpenPanel must not define Kinevo's product events — the
   taxonomy (owner: P32-001 Product Event Taxonomy) is the single source; OpenPanel is a sink.
4. **AI ledger vs telemetry**: Langfuse must never act as entitlement/billing truth — the
   AI ledger stays authoritative; Langfuse receives traces only.
5. **Error redaction**: GlitchTip must never receive raw note/canvas content — a Kinevo-owned
   redaction layer (SRS §15/NFR-03) sits before any collector.
6. **Admin authority**: Filament admin must call application services, never raw Eloquent
   mutations that bypass domain rules (No-Frankenstein §60).

### License / provenance status (planning baseline)

Per `docs/third-party/licenses.md`, nothing binds at planning time. Status recorded
by project: planning values above are NOT adoption approval. Binding requires
exact-version re-check + ledger row + attribution update at the moment of
introduction (P28-TPI-002). AGPL rows (Lago, OpenPanel) locked to service-boundary
only — no source copying. Langfuse core/ee boundary to be re-verified at adoption.

### Resource profile fields (P28-TPI-006)

Planning budget with estimated CPU/RAM/disk/network/startup/backup/failure/
upgrade + class per service in `docs/deployment.md` §Third-party dependency
resource budget. Class guide: **always-on** = in-app core; **optional** =
opt-in Docker profile; **dev-only** = never in prod; **cloud-only** = external-
SaaS or dedicated host, never self-hosted on the app VPS. Bind real values at
adoption (P28-TPI-002).

| Project | Class | Notes |
|---|---|---|
| Excalidraw / Tiptap / Pic Smaller / Uppy | always-on (in-app) | zero server footprint (in-browser) |
| Filament | dev-only / cloud-only (P34) | admin console, never on-user-app VPS |
| Gotify / Langfuse / GlitchTip | optional | opt-in Docker profile (P28-TPI-007) |
| Lago | optional / cloud-only (P24/P32) | AGPL — service boundary, never source-copied |
| OpenPanel | optional / cloud-only (P31/P32) | AGPL — service boundary, never source-copied |

## Exit strategy matrix (P28-TPI-008 / spec §51)

Every external system answers: what if it disappears tomorrow? Per dependency:
adapter contract · stored-data format · exportability · replacement difficulty ·
migration procedure. Kinevo data is never stranded in an external service.

| Project | Adapter contract (port) | Stored-data format | Exportability | Replacement difficulty | Migration procedure |
|---|---|---|---|---|---|
| Excalidraw | `CanvasAdapter` (ADR-005) | scene JSON in Kinevo `canvas_documents` | full — canonical store is Kinevo Postgres | low | point `CanvasAdapter` at another canvas engine (same interface) |
| Tiptap | `EditorAdapter` | document JSON in Kinevo notes store | full | low | project JSON to alternative editor/native text |
| Pic Smaller | `ImageCompressionProvider` | none (in-browser transform) | n/a | low | drop-in alternative compression engine |
| Uppy | `AssetStorage` port | bytes in Kinevo storage (local/S3) | full — assets under Kinevo ownership | low | swap transport, keep asset records |
| Filament | admin console over app services | none added | n/a | low | alternative admin UI on same services |
| Open SaaS | HARVEST — none | none (patterns only) | n/a | n/a | nothing to migrate |
| Gotify | `NotificationProvider` | transient messages only | n/a (delivery-only, not canonical) | low | point port at another push transport |
| Lago | `BillingAdapter` | metering/invoice aggregates | exportable aggregates; billing domain stays in Kinevo | medium | keep Kinevo BillingService as gate; re-point metering |
| OpenPanel | `AnalyticsAdapter` | derived raw events to sink | events are Kinevo-owned taxonomy; sink cache exportable | medium | re-point sink or revert to derived analytics (P31 baseline) |
| Langfuse | `AIObservabilityProvider` | traces (non-authoritative) | traces are extras — ledger is truth | low | stop sending traces; no data loss of record |
| GlitchTip | `ErrorTelemetryProvider` | error events | redacted events only, not canonical | low | re-point collector |

Rule (spec §51): at adoption, each row gains the binding version forever paired
with an approved exit plan before first production data flows through it.

## Decision gates (spec §5/§60–§62)

An adoption is rejected if it would create a second backend, second source of
truth, second identity system, second billing meaning, second analytics event
definition, second notification domain, or second storage authority — unless an
ADR explicitly documents why the duplication is necessary. A dependency must
solve a real current problem; feature-count popularity is not a criterion.

## Required columns (P28-TPI-000 output) — column mapping

The third-party execution contract (§2, P28-TPI-000) requires these columns for
the authoritative matrix. Mapping to the baseline table above:

| Required column | Where it lives today | Status |
|---|---|---|
| Project | `Project` | present |
| Repository | — (verbatim URL must be verified from official source; never guessed) | PENDING TPI-000 |
| Exact reviewed version/tag/commit | "binding fields" block (recorded from lockfiles/tags at adoption) | PENDING — binds at adoption per licenses.md |
| Capability | `Capability` | present |
| Current license | `License baseline` | present (planning value; exact-version re-check at adoption) |
| Integration mode | `Integration mode` | present (one primary mode per row) |
| Current Kinevo equivalent | — | PENDING TPI-000 (source inspection) |
| Gap | — | PENDING TPI-000 |
| Kinevo-owned responsibility | `Kinevo-owned contract` | present |
| External-owned responsibility | `External-owned behavior` | present |
| Target phase | — | planned values below |
| Target task | — | planned values below |
| Runtime impact | `Runtime dependency` + resource profile block | partially present |
| Data ownership | `Kinevo-owned contract` + Data-exchanged binding field | partially present |
| Failure behavior | `Failure behavior (service)` | present |
| Exit strategy | `Exit strategy` | present (difficulty rating pending TPI-000/TPI-008) |
| Decision | spec §3 Primary decision | planned values below |

TPI-000 fills the PENDING fields by inspecting the actual source tree; it does
NOT change production behavior, install dependencies, or touch schema.

## Target phase / task mapping (planning values)

Source: spec §56 implementation order + TASK.md real phase tasks (verified
2026-08-29). "planned TPI sub-task" = the capability task does not exist yet and
must be created as an extension inside the named phase, depending on the listed
P28-TPI foundation task. Depends On refers to the TPI foundation task it must
depend on (spec §57: every capability-specific task depends on TPI foundation).

| Project | Decision | Target phase | Target task (real or planned) | Depends On |
|---|---|---|---|---|
| Excalidraw | EMBED (adopted) | — (already integrated) | maintain via `CanvasAdapter` | P28-TPI-001 (verify) |
| Tiptap | EMBED (adopted) | — (already integrated) | maintain via `EditorAdapter` | P28-TPI-001 (verify) |
| Pic Smaller | EMBED / ADAPTER | P30 Data/Assets | planned P30 sub-task (behind `ImageCompressionProvider`) | P28-TPI-001 · P28-TPI-005 |
| Uppy | EMBED / ADAPTER | P30 Data/Assets | planned P30 sub-task (behind `AssetStorage` + upload contract) | P28-TPI-001 · P28-TPI-005 |
| Filament | EMBED | P34 Operations | P34-001 Admin Access Model · P34-002 Admin Dashboard (extension) | P28-TPI-004 |
| Open SaaS | HARVEST (no runtime) | P29 Identity | P29-001..P29-006 (Email-First Identity → Google OAuth) | P28-TPI-003 |
| Gotify | ADAPTER + SERVICE | P34 Operations | planned P34 sub-task (behind `NotificationProvider`) | P28-TPI-005 |
| Lago | ADAPTER + SERVICE / REIMPLEMENT concepts | P24/P32 Billing | P24 billing phase extension + P32-005 Pricing Analytics | P28-TPI-005 |
| OpenPanel | ADAPTER + SERVICE / REIMPLEMENT concepts | P31/P32 Analytics | P31-001 Intelligence Source Matrix · P32-001 Product Event Taxonomy | P28-TPI-004 · P28-TPI-005 |
| Langfuse | ADAPTER + SERVICE | P31/P34 AI Ops | planned P31 sub-task (behind `AIObservabilityProvider`) | P28-TPI-005 |
| GlitchTip | ADAPTER + SERVICE | P34 Operations | planned P34 sub-task (behind `ErrorTelemetryProvider`) | P28-TPI-005 |
