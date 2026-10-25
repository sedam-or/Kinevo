# 08 — Frontend (web PWA; also the Android WebView content)

Depends on: `07-api-conventions.md`, `modules/*`. Decision: D-07, ADR-0003. Prefix: `FE`.
UI visuals, tokens and components: `09-design-system.md`. States and copy keys: `standards/ux-states.md`.
Validation catalogue: `standards/validation.md`. Versions and reasons for every library: `standards/open-source-stack.md`.

## 1. Principles

1. **The browser is never authoritative.** It renders server state, queues intents, and previews; it does not decide schedules, permissions or business rules (CA-01 applies to the web too: no business logic in components).
2. **Module-driven shell.** Navigation, settings panels and routes appear only for enabled modules, from `GET /api/v1/modules` (MOD-01). There is no hard-coded nav list.
3. **Server state ≠ UI state.** Server data lives in TanStack Query; UI state lives in components, URL search params, or small React contexts. No global store library.
4. **Typed end to end.** API types are generated from the OpenAPI document (`openapi-typescript`), never hand-written.
5. **Every state is designed.** Each async surface renders loading, empty, error, offline and conflict states from the catalogue.

## 2. Project layout (`web/`)

```
web/
  index.html  vite.config.ts  tsconfig.json  package.json
  src/
    main.tsx  app/            # providers: Query, Router, I18n, Auth, Theme, Offline; error boundary; shell layout
    api/                      # generated schema.d.ts, client factory, error mapper (problem+json → AppError), idempotency helper
    auth/                     # AuthManager: in-memory access token, single-flight refresh, session events
    offline/                  # queue (IndexedDB), drain loop, cache persister, connectivity, conflict store
    features/<module>/        # one folder per module: routes.tsx, api hooks, components, forms, tests, nav.ts (descriptor mirror)
    components/               # design-system components (09-design-system.md), no business logic
    lib/                      # time (Intl wrappers), money, ids (uuidv7), i18n, a11y utils, keyboard shortcuts
    i18n/                     # en.json, id.json (message catalogs; keys from standards/ux-states.md)
    styles/                   # tokens.css (generated from tokens.json), base.css
    sw/                       # service worker sources
    wasm/                     # generated scheduler bindings (loaded lazily in a worker)
  tests/e2e/                  # Playwright journeys (standards/user-journeys.md)
```

## 3. Routes and information architecture (IA)

Four verbs organise the product: **NOW · PLAN · THINK · REFLECT**.

| Group | Route | Screen | Module | Permission |
|---|---|---|---|---|
| NOW | `/today` | Today (now, next, timeline, timer) | `execution` | `execution.today.read` |
| PLAN | `/plan/goals`, `/plan/goals/$goalId` | Goals list / detail (milestones, tasks, AI breakdown entry) | `goals` | |
| PLAN | `/plan/tasks` | Task list with filters | `tasks` | |
| PLAN | `/plan/schedule` | Week schedule, drafts, Sync Now, review banner | `scheduler` | |
| PLAN | `/plan/landscape` | Fixed commitments, overrides, ICS import/export | `landscape` | |
| THINK | `/think/notes`, `/think/notes/$noteId` | Notes list / editor | `knowledge` | |
| THINK | `/think/canvases`, `/think/canvases/$canvasId` | Canvas list / board | `canvas` | |
| REFLECT | `/reflect/progress` | Summary, goals progress, weekly review | `progress` | |
| — | `/settings/profile`, `/settings/security`, `/settings/ai`, `/settings/data`, `/settings/billing` | Account settings panels (from `settings_panels`) | `identity`, `ai`, `billing` | |
| — | `/admin/modules`, `/admin/policies`, `/admin/accounts`, `/admin/audit`, `/admin/jobs`, `/admin/secrets` | Instance admin (only `instance_admin`) | `admin` | |
| — | `/login`, `/register`, `/reset/$token`, `/setup` | Public pages | `identity`, `admin` | public |

Navigation: desktop = left rail with the four groups; mobile (< 768 px) = bottom bar with **Today, Plan, Think, Reflect** and a "More" sheet (Settings, Admin). Groups with no enabled module are hidden; if THINK is empty (both modules off) the bar shows three items.

## 4. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FE-01 | The shell fetches `GET /modules` after login and renders nav and routes from it; an unknown module key is ignored (MOD-09); a route of a disabled module renders the `module.disabled` state, not a blank page. | Unit + E2E (module-matrix). |
| FE-02 | **Auth manager**: access token only in memory; on app start (and after reload) a single `POST /auth/refresh` restores the session; concurrent 401s trigger one refresh (single-flight) and retry the failed request once; refresh failure ⇒ clear state, go to `/login?next=`. No token in `localStorage`/`sessionStorage`/IndexedDB/URL. | Unit tests; Playwright storage-inspection test (SEC-AC-01b). |
| FE-03 | **API client**: generated types; every mutation sends `Idempotency-Key` (new UUID per user intent, reused on retry) unless it is a sync operation (`operation_id`); `If-Match` for versioned resources; `problem+json` mapped to `AppError {code, status, fields, details, requestId}`; UI branches on `code`, never on text. | Unit tests; contract test against the OpenAPI example responses. |
| FE-04 | **Forms**: React Hook Form + zod schemas that mirror `standards/validation.md` field kinds; client validation is a convenience, the server remains authoritative; server `errors[]` are mapped onto fields (all at once). Submit buttons are disabled only while submitting (never to hide validation). | Unit tests per form kind; E2E error mapping. |
| FE-05 | **i18n**: all user-visible strings come from catalogs `en` and `id` via keys; missing key in `id` falls back to `en` in production and **fails CI** (`check-i18n`); pluralisation via ICU rules of `Intl.PluralRules`; dates/numbers via `Intl` with the account locale and timezone; no string concatenation of translated fragments. | CI check; unit tests. |
| FE-06 | **Time**: the client never derives "today" or day boundaries from its own clock for business decisions; it uses `server_now`/`date` fields and offsets (EXE-FR-10). Formatting uses `Intl.DateTimeFormat` with the account timezone. | Test with a browser clock set to a different zone/year. |
| FE-07 | **Offline** (when `sync` enabled): service worker precaches the app shell (hashed assets) and serves it offline; API responses are **not** cached by the service worker; TanStack Query cache for allow-listed read keys (`today`, `tasks`, `goals`, `schedule input`, `notes list`) is persisted to IndexedDB with a max age `7 d` and cleared on logout of a different account; mutations follow SYN-C1…C7. Offline banner and "N changes waiting" indicator are always accurate. | E2E offline journey J5 on Chromium, Firefox, WebKit. |
| FE-08 | **PWA**: web app manifest (name, short_name, icons incl. maskable, `display: standalone`, theme colours from tokens, `start_url: /today`, shortcuts to Today/New task), installable on Android Chrome and desktop; iOS "Add to Home Screen" works (no push). | Lighthouse PWA audit passes (installability checks) `[ASSUMED tool availability]` + manual. |
| FE-09 | **Code splitting**: Tiptap, Excalidraw, the WASM scheduler and the admin area load only on their routes. Initial JS budget ≤ 200 KB gzip on `/today` `[ASSUMED]`; CI reports bundle sizes and fails if the budget grows > 10 % without an explicit approval label. | CI bundle report. |
| FE-10 | **Security headers** are set by the server (not meta tags): CSP `default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`, `Permissions-Policy` empty, HSTS when behind HTTPS. The Android shell's origin adds the instance URL to `connect-src` at build time. No `dangerouslySetInnerHTML` (lint rule). | Header test; ESLint rule test. |
| FE-11 | **Keyboard**: every action reachable by keyboard; global chords `g t` (Today), `g p` (Plan), `g n` (Notes), `g r` (Reflect), `n` (new task), `?` (shortcut help), `Cmd/Ctrl+K` (command palette: navigate + create). Shortcuts are disabled inside text inputs and editors and can be turned off in settings. | E2E keyboard journey; a11y test. |
| FE-12 | **Error boundaries**: a render error in one feature shows a local error card with "Reload this section" and copies the `request_id`/error id; it does not blank the shell. | Unit test. |
| FE-13 | **No telemetry**: no third-party scripts, fonts or images are loaded; fonts are self-hosted (design system). Network requests go only to the instance origin (and, on Android, the configured server). | E2E network-allow-list test. |
| FE-14 | **Conflict & stale handling** follows `standards/ux-states.md` (`conflict.*`, `stale.*`): the UI never discards a user's text on a failed save. | E2E. |
| FE-15 | **Preview scheduler** (WASM) runs in a Web Worker; results are labelled "Preview — not saved"; failures to load WASM degrade to "Preview unavailable offline". | E2E. |
| FE-16 | **Testing**: component tests with Vitest + Testing Library; API mocked by MSW generated from the OpenAPI document; E2E with Playwright on Chromium, Firefox, WebKit running the journeys; axe checks (WCAG 2.2 AA tags `wcag2a`, `wcag2aa`, `wcag21aa`, `wcag22aa`) on every route and state in the catalogue. | CI. |

## 5. Screen contracts (summary; states in `standards/ux-states.md`)

| Screen | Primary content | Primary action | Must show states |
|---|---|---|---|
| Today | Now card (timer), Next card, day timeline (tasks + landscape), counts | Start / Complete / Skip | loading, empty (no tasks / nothing scheduled), review-needed banner, running-session, stale-session, offline, error |
| Goals | list with progress facts; detail with milestones/tasks | New goal; AI breakdown (if enabled) | empty, error, conflict |
| Schedule | week view: assignments + landscape; draft review panel (moves, reasons) | Generate / Sync Now / Apply | pending draft, stale draft, infeasible draft, busy, no changes |
| Landscape | list + overrides + import wizard | Add / Import | preview with warnings, import result, undo |
| Notes | list + search + editor | New note | saving, saved, failed, conflict, readonly (archived) |
| Canvas | list + board | New board | online-only/readonly offline, save failed, conflict |
| Progress | daily bars (net completed, minutes), goals table, weekly review | — | empty, freshness hint |
| Settings | profile, security (password, TOTP, sessions), AI (BYOK), data (export/delete), billing | per panel | per panel |
| Admin | modules switches, policies form (generated from metadata), accounts, audit, jobs | Switch / Save | dependency errors, version conflict |

## 6. Failure and recovery (cross-cutting)

| Failure | Behaviour |
|---|---|
| Network lost mid-request | Request fails with `network_error`; for sync-supported intents the queue keeps it; others show a retry toast |
| 401 after refresh failure | Sign-in with `next`; unsent form text preserved in memory for the session |
| 429 | Honour `Retry-After`; show countdown; auto-retry once |
| 5xx | Retry with backoff for idempotent GET/with Idempotency-Key; then error card with request id |
| New server version (API/asset hash mismatch) | Banner "Update available — reload"; reload applies the new service worker |
