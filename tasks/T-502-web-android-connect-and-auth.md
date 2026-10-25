---
id: T-502
title: Build the connect screen, the Android token transport and the compatibility, back-button and layout handling in the web app
milestone: M10
kind: feature
status: todo
depends_on: [T-501, T-049, T-254]
requirements: [AND-01, AND-02, AND-03, AND-04, AND-05, AND-06, AND-07, D-37, FE-04, UXS-02, DS-A11Y-13, J9]
branch: m10-t502-web-android-connect-and-auth
budget_lines: 420
assignee: ""
---

# T-502: Build the connect screen, the Android token transport and the compatibility, back-button and layout handling in the web app

## Goal

The same web build, running inside the shell, connects to a user-entered server, signs in with the native token transport, refuses incompatible servers and behaves like an Android app (back, insets, TalkBack).

## Context

> **Connect flow** (`spec/modules/android.md` §4): AND-01 first run shows "Connect to your Kinevo" (server address field, or the deep link/QR); the app calls `GET <url>/api/v1/instance` over HTTPS (http only for `localhost`/private LAN with the visible warning and the `allowCleartextLan` flag) and shows the instance name and version before sign-in. Failures: unreachable → "Can't reach this address" + checklist (HTTPS? same network? firewall?) + retry; invalid certificate → hard fail with an explanation, **no "proceed anyway"**. The server URL is stored in `SecureStore` (T-501).
> **Auth transport (D-37, AND-02)**: in the shell the API client sets `X-Kinevo-Client: android`; `/auth/login` and `/auth/refresh` return and accept the rotating refresh token in the JSON body; the refresh token lives only in `SecureStore`, the access token only in JS memory; logout calls `/auth/logout` then deletes the stored token; a revoked/reused refresh token shows the sign-in screen with `auth.session_revoked`. The AuthManager (T-049) gains a `TokenTransport` strategy (`cookie` for browsers, `native` for the shell) and the API client gains a configurable base URL (browser: same origin, shell: the stored server URL); no `Authorization` header is ever sent to a different origin than the stored server.
> **AND-04 compatibility**: compare the server's `api_version` and `min_client_build` (from `GET /instance`) with the build's own values (`AppInfo`); unsupported → "Update required" screen (link to the release page), no login. **AND-03** CORS: the server allows only the shell origin for the API when `KINEVO_ANDROID_ORIGIN=true` — this task adds the CORS integration test against the real server (allowed origin, disallowed origin, preflight headers `Authorization, Content-Type, Idempotency-Key, If-Match, X-Kinevo-Client, X-Request-Id`, no credentials mode); fix in `kinevo-http` if the test finds a gap (note in the Report).
> **AND-05 offline**: identical to the PWA (IndexedDB queue + cache, `sync`); the queue drains when the app is foregrounded (SYN-C3 focus event); no background sync. **AND-06**: back/gesture navigates SPA history, closes dialogs first, exits only from the Today root (Capacitor `App` back-button listener), edge-to-edge insets via CSS `env(safe-area-inset-*)`, status/navigation bar contrast follows the theme. **AND-07**: titles announced, live regions for toasts, touch targets ≥ 48 dp (the design system already requires 44 px; raise to 48 dp in the shell via a CSS custom property switched by platform), font scale up to 200 % without clipping.
> **Journey J9** (web part): connect → sign in → Today → offline → complete a task → reconnect → synced; sign-out clears secure storage; unsupported server → update screen.

## Scope

**In:**
- Feature folder `web/src/features/connect/**` and `web/src/native/transport.ts`, AuthManager and API-client changes, back-button handler, platform CSS, i18n (en, id), MSW + Vitest tests, a Playwright project `android-webview-sim` that runs the built web app with a stubbed native bridge against the real server, a CORS integration test in `kinevo-app` tests, a11y checks for the connect screen.

**Out:**
- Real-device runs (T-504, environment-limited).

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/connect/**` | create |  |
| `web/src/native/transport.ts` | create |  |
| `web/src/auth/{AuthManager,client}.ts` | modify | transport strategy and base URL |
| `web/src/app/backButton.ts` | create |  |
| `web/src/styles/platform-android.css` | create |  |
| `web/src/i18n/{en,id}/connect.json` | create |  |
| `web/tests/{connect.test.tsx,transport.test.ts}` | create | AC-1…AC-10 |
| `web/tests/e2e/android-sim.spec.ts` | create | simulated shell journey |
| `crates/kinevo-app/tests/android_cors.rs` | create | AC-9 |

## Interfaces

```ts
export interface TokenTransport { loadRefresh(): Promise<string | null>; storeRefresh(t: string): Promise<void>; clear(): Promise<void>; headers(): Record<string, string> }
export function compatibility(server: { api_version: string; min_client_build: number }, build: { versionCode: number; apiVersion: string }): 'ok' | 'update_required';
```

## Business rules

1. No "proceed anyway" for certificate errors.
2. No token ever reaches WebView storage in `native` mode.
3. Compatibility check runs before the login form is shown.

## Security

- Base URL changes require the connect flow; the origin of every request equals the stored server origin.
- The refresh token is never put in a URL, log or error.

## Implementation notes

- Android SDK, emulator and device steps are **environment-limited** when `scripts/android-sdk.sh` cannot install the SDK (no network) or no emulator can run; the Report names the exact command and error. They never block the PR.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a valid https server **When** connecting **Then** the instance name and version are shown before sign-in | `connect::and_01_shows_instance_info` |
| AC-2 | **Given** an unreachable address and an invalid certificate **When** connecting **Then** the unreachable checklist and the hard-fail explanation | `connect::and_01_failure_states` |
| AC-3 | **Given** native transport **When** logging in and refreshing **Then** the refresh token is read from and written to SecureStore only | `transport::and_02_native_transport_storage` |
| AC-4 | **Given** logout **When** done **Then** `/auth/logout` is called and SecureStore is cleared | `transport::and_02_logout_clears_store` |
| AC-5 | **Given** an unsupported `min_client_build` **When** connecting **Then** the update screen and no login form | `connect::and_04_update_required` |
| AC-6 | **Given** back presses **When** a dialog is open, on a sub-route and at Today **Then** it closes the dialog, navigates back, and exits respectively | `connect::and_06_back_button_rules` |
| AC-7 | **Given** a request with the base URL **When** sent **Then** it goes only to the stored server origin | `transport::requests_only_to_stored_origin` |
| AC-8 | **Given** the queue with items **When** the app returns to foreground **Then** it drains | `connect::and_05_drain_on_foreground` |
| AC-9 | **Given** the real server with Android origin enabled **When** CORS requests are sent **Then** the allowed origin passes, others fail, headers exact, no credentials | `android_cors::and_03_cors_allow_list` |
| AC-10 | **Given** the simulated shell **When** J9 web steps run **Then** connect, sign in, offline complete, reconnect sync, sign out clearing storage, update screen all pass | `e2e::j9_web_part_in_simulated_shell` |

## Tests

```
pnpm --dir web test
pnpm --dir web check-i18n
just e2e   # environment-limited without browsers
just check
cargo nextest run -p kinevo-app --test android_cors --features kinevo-db/testing
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
