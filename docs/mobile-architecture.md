# Mobile Architecture (Phase 26)

> **Status:** Architecture + feasibility **DONE** (verified against NativePHP official docs,
> 2026-08-26). The **app build** is Phase 27 (P27-001..011), gated on the sync engine + API
> stability. See `docs/adr/ADR-008-mobile.md`.

Kinevo ships a native mobile app **without forking a second backend**. The web/SaaS app and the
mobile app are two **presentation fronts** over the **same Laravel modular monolith** (ADR-001).

---

## 1. Single-backend principle

Shared across web + mobile:

```
Domain (entities, services, value objects)
  ↑
Application (use cases — the only mutation entry points)
  ↑
Infrastructure (repositories, providers, persistence adapters)
  ↑
Presentation FRONT
   ├─ Web : Inertia + Vue SPA  (resources/js)        ── SaaS / browser
   └─ Mobile : NativePHP EDGE (Blade → native views) ── iOS / Android
```

- The **Domain MUST NOT** import Vue, Inertia, HTTP, or any presentation concern
  (`docs/architecture.md` Dependency rule). NativePHP's Blade/EDGE layer respects this — it is a
  presentation adapter, not a domain change.
- Business logic (scheduling, entitlements, AI metering, offline-sync envelope) is written **once**.

## 2. Presentation boundary

| Concern            | Web/SaaS                         | Mobile (NativePHP)                     |
|--------------------|----------------------------------|----------------------------------------|
| Runtime            | Laravel on server (PHP-FPM)      | Embedded pre-compiled PHP on device    |
| UI                 | Vue SPA via Inertia              | Blade **EDGE** components → native views |
| Rendering          | Browser DOM                      | Real native iOS/Android widgets        |
| Offline            | IndexedDB cache/queue            | On-device **SQLite** + sync queue       |
| Persistence        | PostgreSQL (authoritative)       | SQLite (local canonical) → sync → PG   |

The existing Vue components are **not** reusable on mobile; the mobile UI is a new EDGE surface.
Shared logic lives in use cases, never in components.

## 3. Persistence & sync

- **On device:** SQLite is the local canonical store (NativePHP embedded runtime).
- **Server:** PostgreSQL remains authoritative for the SaaS (`docs/offline-sync.md`).
- **Reconciliation:** reuse the existing operation envelope — every offline mutation carries an
  `operation_id` (UUID), `base_version`, and payload, then reconciles through the server contract
  (`docs/offline-sync.md`). Optimistic concurrency uses the existing `base_version` rule (409 on
  stale writes, never silent overwrite).
- **Risk:** schema parity between SQLite (mobile) and PostgreSQL (server) must be enforced by the
  migration + repository abstractions — the single largest P27 gating item.

## 4. Auth & identity

- **Server API auth:** Sanctum bearer token (same `POST /auth/login`, `GET /auth/me` contracts the
  web uses). Mobile obtains a token and stores it in the NativePHP **SecureStorage** plugin.
- **Local unlock:** the **Biometrics** core plugin gates in-app access on the device (does not
  replace server auth — it only protects the stored token).
- **Ownership/scope:** the same workspace/ownership rules apply; mobile is a client of the user's
  existing account, not a new identity.

## 5. Feature matrix (P27 MVP → NativePHP capability)

| P27 feature            | NativePHP capability used                     | Feasibility |
|------------------------|-----------------------------------------------|-------------|
| Shell / bottom nav     | EDGE Bottom Navigation, Top Bar, Safe Area    | ✓           |
| Today / NOW task       | Routing, Data Binding, Reactivity, Refreshable| ✓           |
| Quick capture          | Text Input, FAB, Native Functions             | ✓           |
| Execution timer        | Lifecycle hooks, Background-safe queue        | ✓ (foreground-safe) |
| Goal + AI breakdown    | HTTP API → AI use cases (BYOK/ledger honored) | ✓           |
| Notes companion        | List/Text/Modal, SecureStorage                | ✓           |
| Canvas companion       | Web View (embed existing canvas) or native    | △ (use WebView bridge; full native later) |
| Analytics              | HTTP API read-only                            | ✓           |
| Notifications          | Push (Firebase plugin) / local scheduler      | ✓           |
| Subscription visibility| HTTP API (billing/plan read-only)             | ✓           |
| Device evidence        | Device, File, Share plugins                   | ✓           |

✓ = directly supported. △ = supported via WebView bridge; native rewrite deferred.

## 6. Navigation

Mobile nav mirrors the web information architecture: a bottom tab row
(`Today · Capture · Goals · Notes · Settings`) backed by NativePHP **Routing** + EDGE screens.
Each screen is a Blade/EDGE view that invokes the **same application use cases** the web calls.

## 7. Contracts

- **No new API endpoints required for parity.** Mobile consumes the existing OpenAPI surface
  (`docs/api/openapi.yaml`) over Sanctum. The same validation/authorization/ownership rules apply
  server-side — the AI rule, offline rule, and API rule from `AGENTS.md` are unchanged.
- Mobile must carry an `operation_id` on every mutating call for offline reconciliation.

## 8. Deep links

- Scheme: `kinevo://` (configured in the NativePHP deep-link manifest).
- Map to in-app screens:
  - `kinevo://today` → Today/NOW
  - `kinevo://task/{id}` → Task detail
  - `kinevo://note/{id}` → Note detail
  - `kinevo://goal/{id}` → Goal detail
- Incoming links resolve through NativePHP **Deep Links** → router → screen; the same resource ids
  the web uses.

## 9. Offline reuse (already specified)

Reuse, do not reinvent:

- `docs/offline-sync.md` operation envelope (`operation_id`, `entity_type`, `entity_id`,
  `operation_type`, `client_timestamp`, `base_version`, `payload`).
- The existing `AGENTS.md` Offline rule: IndexedDB/cache is never canonical; for mobile the on-device
  SQLite **is** the local canonical and syncs up — the rule's spirit (server Postgres stays
  authoritative for multi-device truth) still holds.
- Conflict resolution: 409 stale-version, never overwrite.

## 10. Risks / open questions

1. **Sync engine** — the binding dependency for P27; design before any feature build.
2. **Schema parity** — SQLite↔Postgres type/driver differences must be abstracted in repositories.
3. **NativePHP maturity** — pin versions; avoid hard dependency on paid-only plugins for core flows.
4. **App-store review** — keep the embedded runtime lean; follow NativePHP publishing guidance.

## 11. References

- `docs/adr/ADR-008-mobile.md` — adoption decision.
- `docs/architecture.md` — dependency rule + module boundaries.
- `docs/offline-sync.md` — operation envelope + reconciliation.
- `docs/api/openapi.yaml` — shared contracts.
- NativePHP Mobile v4 docs — nativephp.com/docs/mobile (verified 2026-08-26).
