# ADR-008 — NativePHP Mobile Architecture

### Decision
Adopt **NativePHP for Mobile (v4, SuperNative/EDGE)** as the delivery vehicle for the Kinevo
native mobile app, reusing the existing single Laravel modular-monolith codebase. The web/SaaS
deployment and the mobile app share the **Domain + Application + Infrastructure** layers; they
diverge only at the presentation boundary (web = Vue SPA via Inertia; mobile = Blade/EDGE native
UI rendered by the embedded on-device PHP runtime).

### Context
Phase 26 was marked **BLOCKED until verified vs official docs**. Feasibility was verified against
the NativePHP Mobile v4 official documentation (nativephp.com/docs/mobile):

- NativePHP embeds a **pre-compiled PHP runtime** alongside Laravel inside a Swift/Kotlin shell —
  **no web server, no separate backend process required**. The full PHP/Laravel app runs **on
  device**, shipped with each install.
- It renders **real native UI** via **EDGE** components — Blade components that render as native
  views (Livewire-like), not a WebView. This is a distinct presentation layer from the web Vue SPA.
- It is **offline-first**: the app runs entirely on-device and can operate without a network.
- It exposes **native APIs** through a custom PHP extension/bridge: biometrics, camera, push
  (Firebase), secure storage, deep links, network, geolocation, etc., plus the full Laravel
  ecosystem (queues, databases, auth, events, routing).
- **One codebase → iOS + Android** (cross-platform).

This fits Kinevo's architecture constraints:

- `docs/architecture.md` Dependency rule: the **Domain MUST NOT import Vue/Inertia/HTTP/presentation
  concerns**. NativePHP's Blade/EDGE presentation sits above the same domain/application layers, so
  reuse is clean and the modular-monolith (ADR-001) is preserved — we are not forking a second
  backend.
- `docs/offline-sync.md`: Postgres remains authoritative for the SaaS; on device we use **SQLite**
  as the local canonical store and reconcile to the server through the existing
  `operation_id`/base-version queue contract. NativePHP's offline-first model makes this the primary
  path rather than an exception.

### Alternatives rejected
- **React Native / Flutter** — would require re-implementing the entire domain/application layer in
  another language and a second backend boundary; violates the single-codebase principle and the
  team's PHP skillset (per NativePHP's own positioning).
- **PWA / WebView wrapper** — NativePHP explicitly goes beyond this; a PWA cannot render true native
  UI or access the same native API surface, and the existing Vue SPA already covers the browser.
- **Separate Laravel API + native Swift/Kotlin client** — doubles maintenance and breaks the
  modular-monolith boundary discipline.

### Consequences
Positive:
- The business logic (scheduling, entitlements, AI metering, offline sync) is written **once** and
  shared by web + mobile.
- Offline-first is native to the platform, not bolted on.
- Reuses the existing OpenAPI contracts; mobile can either call the same HTTP API (Sanctum) or
  invoke use cases directly on-device.

Negative / required work (tracked in Phase 27+):
- **Persistence divergence**: server = PostgreSQL, device = SQLite. A sync engine is mandatory
  (extends `docs/offline-sync.md`); this is the single largest risk and the gating dependency for
  P27.
- **Presentation re-implementation**: mobile UI is NOT the Vue SPA; it is a new Blade/EDGE native
  surface. Web components cannot be copy-pasted.
- Some Laravel server assumptions (long-running workers, web-server middleware, certain packages)
  do not map 1:1 to the embedded runtime; mobile-specific guards are needed.
- NativePHP is a young, commercial-adjacent ecosystem (some plugins/features behind paid tiers) —
  pin versions and avoid hard dependencies on paid-only capabilities for core flows.

### Status
Accepted — Phase 26 feasibility verified. Mobile **app build** (features, nav, MVP) is Phase 27,
gated on the sync engine + API stability.

---
