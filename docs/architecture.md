# Kinevo — Technical Architecture

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Canonical architecture authority.
> Every element is labeled **CURRENT** (verified implemented today),
> **TARGET** (decided, not yet built), **MIGRATION_REQUIRED** (current state
> must change), **OPTIONAL**, or **DEFERRED**. Domain meaning:
> `docs/domain-model.md`; requirements: `docs/SRS.md`; third-party adoption:
> `docs/third-party/adoption-matrix.md` (governance) — this file does not
> re-list it. TARGET items are never presented as installed.

## 1. Architectural style — CURRENT

**Laravel modular monolith**: one deployable Laravel application with explicit
domain/application/infrastructure boundaries (`server/app/{Domain,Application,
Infrastructure,Http}`), PostgreSQL, Vue 3 + TypeScript SPA (Vite, Pinia,
Tailwind 4) served by the same app. **Inertia was planned in ADR-002 but is NOT
installed** — the frontend is a plain SPA over `docs/api/openapi.yaml`.

Why a monolith: single-user product, one owner-domain boundary, transactional
integrity across scheduling/offline/billing, one deployable for self-hosters.
Repository split (Core/Cloud) is TARGET for P34 — never before.

## 2. High-level architecture — CURRENT

```
Vue 3 SPA (Vite/Pinia/Tailwind) ─┐
NativePHP Android (OpenAPI) ─────┼─► HTTP API (Laravel controllers)
                                  │        ↓
                          Application use cases (transactions)
                                  ↓
                    Domain services / entities (rules)
                                  ↓
                Infrastructure: Eloquent repositories, providers
                                  ↓
              PostgreSQL · Storage · AI providers · (TARGET: email/assets/analytics)
```

Layering rules: controllers validate + authorize + call use cases; use cases own
transactions; domain objects own rules; repositories own persistence; Vue never
contains scheduling/domain logic; the browser is never authoritative for
schedule state.

## 3. Runtime — CURRENT vs TARGET

- **CURRENT:** nginx + php-fpm (production compose) / `artisan serve` (dev);
  PHP 8.x; PostgreSQL 17.
- **TARGET_LOCKED (MIGRATION_REQUIRED → P30):** Laravel Octane + FrankenPHP.
  Benefit is NOT assumed proven — **BENCHMARK_REQUIRED** (memory soak,
  isolation, rollback drill) before any claim. Migration is never an incidental
  refactor.

## 4. Frontend architecture — CURRENT

- Vue 3 + TS + Pinia stores per domain (`server/resources/js/*`), Vite build,
  Tailwind 4 + design tokens (`tokens/`).
- **Tiptap** owns rich-text editing behind the Kinevo editor boundary (ADR-009).
- **Excalidraw** owns drawing inside a lazy React island behind the
  `CanvasAdapter` seam (ADR-010); bundle-split; the seam is compile-time
  (`KINEVO_E2E_SEAM`) so production builds dead-code-eliminate it.
- Offline: Service Worker + IndexedDB cache/queue; **MutationQueue** drains via
  `POST /sync/reconcile`; IndexedDB is cache — the server is canonical (ADR-017).

## 5. Mobile architecture — CURRENT (contract) / P36 (production)

NativePHP Android shell consumes the same OpenAPI: locked IA (Today · Tasks ·
Capture · Workspace · More), billing web-first, `operation_id` on every mutating
call, offline status labels only. **Durable mobile offline = TARGET P36 via the
SAME ADR-017 protocol — no second protocol.** Authority: `docs/mobile-architecture.md`.

## 6. Scheduling architecture — CURRENT

Deterministic pipeline (ADR-015/016, full contract in `docs/scheduling-engine.md`):
SOURCE EVENT → RECURRENCE EXPANSION → BASE OCCURRENCE → OVERRIDE RESOLUTION
(exception > latest shift > base) → EFFECTIVE OCCURRENCE → EFFECTIVE LANDSCAPE →
CAPACITY → DETERMINISTIC SCHEDULER → DRAFT/PROPOSAL → USER APPROVAL → ACCEPTED
SCHEDULE (+ schedule_assignment_history). Weekly automation may calculate, must
not auto-apply; Sync Now recalculates/proposes, never silently mutates. AI is
excluded from scheduling authority by construction.

## 7. Offline architecture — CURRENT

ADR-017: client mutation (operation UUID) → durable IndexedDB queue → reconnect
→ `POST /sync/reconcile` → `offline_operations` ledger (idempotent replay;
different-payload rejection) → same application use cases as online (per-operation
transactions) → canonical outcome (+ optimistic base_version 409 conflicts) →
client rehydration. No LWW, no client-clock authority, no generic API replay
proxy. Contract: `docs/offline-sync.md`.

## 8. Provider boundaries (Kinevo-owned ports)

| Boundary | CURRENT | TARGET | Phase |
|---|---|---|---|
| **AIProvider** (ADR-011) | disabled/ollama/openai-compatible/mock + capability catalog + connection test + usage metering (hosted/BYOK ledger) | production quota policy (P33), AI observability adapter (P32) | P32/P33 |
| **EmailProvider** | none installed | Resend via Kinevo-owned abstraction (decision register #20/#21) | P30 |
| **AssetStorage / Upload** | direct attachment upload | Uppy → validation → Pic Smaller → AssetStorage → object storage | P31 |
| **AnalyticsProvider** | none (retention taxonomy v1 defined) | OpenPanel via adapter; redaction + degradation tests | P32 |
| **AIObservabilityProvider** | AI Ledger (billing truth) | Langfuse-compatible adapter; ledger stays financial truth | P32 |
| **NotificationProvider** | in-app notifications | provider abstraction (Gotify reference-only) | P30+ |
| **Billing/Payment** | Midtrans sandbox; signed idempotent webhooks; Kinevo-owned subscription/entitlement | production Midtrans flip + FinOps quotas | P33 |

Provider implementation never becomes domain authority; every provider sits
behind a Kinevo-owned port with a documented failure/degradation mode.

## 9. Third-party adoption — CURRENT vs TARGET

Governance (modes EMBED/HARVEST/REIMPLEMENT/ADAPTER+SERVICE/REFERENCE ONLY/
REJECT, licenses, exit strategy): `docs/third-party/adoption-matrix.md` (ADR-014).
- **CURRENT_EMBED:** Excalidraw (drawing island), Tiptap (editor).
- **TARGET:** Pic Smaller (P31), Uppy (P31), Filament (operator control plane,
  P35), OpenPanel (P32), Lago (evaluation, P33), Gotify (reference), Langfuse
  (adapter, P32), GlitchTip (P35), Open-SaaS pattern harvest (reference).
- Compose reality: `app` / `postgres` / `ollama` (opt-in profile) — no admin/
  monitoring services installed. Nothing documented-only may be presented as
  installed.

## 10. Deployment shape — CURRENT

Docker Compose dev stack (app, postgres, ollama profile); production profile
(nginx + php-fpm + postgres); asset build via Vite; migrations additive-only;
secrets via environment (`docs/environment.md`, `docs/deployment.md`).

## 11. Network trust boundaries — CURRENT

Browser and mobile are untrusted clients; the API validates authorization,
ownership, payload shape, state transition, and idempotency server-side for
every business mutation. AI output is untrusted input until validated + approved.
Offline replay passes the same validation as online mutations.

## 12. Architecture invariants

1. One canonical API contract (`docs/api/openapi.yaml`) consumed by web + mobile.
2. Deterministic scheduling; AI never authoritative.
3. Server is canonical state; clients are caches/queues.
4. Additive migrations only; schema drift requires a migration.
5. Provider ports are Kinevo-owned; no vendor becomes domain authority.
6. Repository split (Core/Cloud) is P34 — no premature boundary.
7. Optimistic versioning + stable 409 on every mutable aggregate with
   concurrent-edit risk.

## 13. Repository boundaries — TARGET (P34)

Core (public MIT, self-hostable) / Cloud (private SaaS layer) / Site (public).
No split before P34; never-delete-first process + pre-split tag.
