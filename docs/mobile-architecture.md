# Mobile Architecture (Phase 26)

> STATUS: AUTHORITATIVE (P29 2026-08-31). Mobile architecture + feasibility
> contract; web counterpart is the plain Vue SPA (ADR-002 amendment). P29 fixed
> stale Inertia references; ADR-017 server contract unchanged.

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
   ├─ Web : Vue 3 SPA  (resources/js; Inertia was planned in ADR-002 but is NOT installed) ── SaaS / browser
   └─ Mobile : NativePHP EDGE (Blade → native views) ── iOS / Android
```

- The **Domain MUST NOT** import Vue, HTTP, or any presentation concern
  (`docs/architecture.md` Dependency rule). NativePHP's Blade/EDGE layer respects this — it is a
  presentation adapter, not a domain change.
- Business logic (scheduling, entitlements, AI metering, offline-sync envelope) is written **once**.

## 2. Presentation boundary

| Concern            | Web/SaaS                         | Mobile (NativePHP)                     |
|--------------------|----------------------------------|----------------------------------------|
| Runtime            | Laravel on server (PHP-FPM)      | Embedded pre-compiled PHP on device    |
| UI                 | Vue 3 SPA (no Inertia)           | Blade **EDGE** components → native views |
| Rendering          | Browser DOM                      | Real native iOS/Android widgets        |
| Offline            | IndexedDB cache/queue            | On-device **SQLite** + sync queue       |
| Persistence        | PostgreSQL (authoritative)       | SQLite (local canonical) → sync → PG   |

The existing Vue components are **not** reusable on mobile; the mobile UI is a new EDGE surface.
Shared logic lives in use cases, never in components.

## 3. Persistence & sync

- **On device:** SQLite is the local canonical store (NativePHP embedded runtime).
- **Server:** PostgreSQL remains authoritative for the SaaS (`docs/offline-sync.md`).
- **Reconciliation (ADR-017):** the SERVER protocol (operation envelope +
  `offline_operations` ledger + `POST /sync/reconcile`) is the mobile contract
  and is reusable unchanged. Optimistic concurrency uses the existing
  `base_version` rule (409 on stale writes, never silent overwrite).
- **Current mobile offline status (truthful):** the shipped mobile screens show
  `offline`/`queued` status labels ONLY — there is no durable offline queue on
  device yet (a queued CaptureScreen draft is a component property lost on
  navigation; all mobile mutations are live HTTP). Durable mobile offline
  persistence is deferred to the Android production-hardening phase; the server
  contract above is the intended target and is NOT a second protocol.
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

Primary bottom tabs (**locked IA**): **Today · Tasks · Capture · Workspace · More**.
- Today is the single obvious primary path from the shell.
- More hosts Settings / Review / Notifications (no dead ends).
- Do NOT copy the desktop sidebar; Android is a capture/decide/execute/review companion,
  not a shrunken clone (mobile-IA principle; billing web-first per ADR-013).
- Android back behavior verified during P27-001 device work.

## 7. Contracts

- **No new API endpoints required for parity.** Mobile consumes the existing OpenAPI surface
  (`docs/api/openapi.yaml`) over Sanctum. The same validation/authorization/ownership rules apply
  server-side — the AI rule, offline rule, and API rule from `AGENTS.md` are unchanged.
- **Billing boundary (locked, ADR-013): web-first.** Android v1 shows plan/subscription state
  (`/billing/subscription`, `/saas/plan`) and deep-links to web for checkout/manage; NO Google Play
  checkout in v1. Extension slots reserved for future Android/iOS native provider adapters without
  redesign.
- Mobile must carry an `operation_id` on every mutating call for offline reconciliation.

## 7b. Desktop-vs-Mobile capability matrix (P26-008)

| Scope | Surfaces |
|-------|----------|
| **Mobile-first** | Today; Capture; Task execution; Goals; AI Breakdown; Notes; Progress; Notifications; Workspace switching; concise review |
| **Desktop-first** | full Canvas authoring; advanced analytics; deep planning; bulk editing; advanced workspace administration |

Companion behaviors for desktop-first surfaces: Canvas → read/companion + WebView bridge;
analytics → summary review surface; planning → goal/task quick actions; bulk editing and
workspace administration → deferred to a desktop link.

## 8. Deep links

- Scheme: `kinevo://` (configured in the NativePHP deep-link manifest).
- Map to in-app screens (runtime verification pending device work, P26-010/P27):
  - `kinevo://today` → Today/NOW
  - `kinevo://task/{id}` → Task detail
  - `kinevo://note/{id}` → Note detail
  - `kinevo://goal/{id}` → Goal detail
  - `kinevo://workspace/{id}` → Workspace context switch
  - `kinevo://ai-proposal/{id}` → Pending AI proposal review
- Incoming links resolve through NativePHP **Deep Links** → router → screen; the same resource ids
  the web uses. Authenticated targets open; unauthorized targets are rejected by the same server
  ownership rules; unknown targets fail safely to the shell root.

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

## 12. Phase 26 verification evidence (real runs, 2026-08-27)

The toolchain was actually installed and run on a real Android 14 (API 34) emulator on `/dev/kvm`,
with every package checksum-verified against its official source.

| Component | Version / artifact | Evidence |
|---|---|---|
| Android cmdline-tools | 16111833 (`commandlinetools-linux-16111833_latest.zip`) | SHA1 `e025545c62a8e64c7559119566a569fb1dec5f60` read from Google's official repo XML (`dl.google.com/android/repository/repository2-1.xml`) |
| platform-tools | r37.0.1 | `adb` present after install |
| emulator | r37.1.11 | `emulator-5554 device`; AVD `kinevo_emu` booted, `sys.boot_completed=1` |
| Build tools / NDK / CMake | 34.0.0 / 27.0.12077973 / 3.22.1 | `aapt2`, NDK+CMake binaries present; installed via official `sdkmanager` |
| API platforms | 34 + 35 | `android.jar` + `source.properties` present |
| JDK | 17.0.20.1 (Arch) | `gradle --version` launcher JVM 17 |
| NativePHP Mobile | 4.2.0 (official Packagist `nativephp/mobile`) in Laravel 12 | `native:install` scaffolded the Android Gradle project |
| Debug builds | `app-debug.apk` ~30 MB (NativePHP app) + bare Java APK | `./gradlew assembleDebug` → `BUILD SUCCESSFUL` |
| Device run | NativePHP shell launched | logcat: `Fully drawn com.kinevo.spike/com.nativephp.mobile.ui.MainActivity` + `NativePHP module initializing` |
| Backend reachability | from inside the app on the emulator | `KINEVO_BACKEND_HTTP -> HTTP 200 {"status":"ok",...}` (dev backend is HTTP-only) |
| TLS egress | from the device | `HTTPS_TLS -> HTTP 200` (`https://api.github.com/zen`) |

**Toolchain realities (honest, repeatable):**
- NativePHP's official `native:build`/`native:run` requires macOS; on Linux we compile the
  scaffolded Android Gradle project directly (fill NativePHP `REPLACE_*` tokens, set
  `local.properties` `sdk.dir`, `compileSdk 35`).
- The Laravel app bundle inside the APK is injected only by macOS `native:run` — our direct build
  boots the embedded PHP runtime but cannot `require vendor/nativephp/mobile` yet. Full app boot,
  and therefore on-device auth/entitlement/billing runtime checks, land in P27 once the bundle is
  injected (or a release runner is available).
- First-run gotchas recorded so the pipeline is repeatable: `/tmp` is a 7.5 GB tmpfs — set
  `TMPDIR` on a real disk or SDK extraction is silently truncated; reinstall any partial package.
