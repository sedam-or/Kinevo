# Android app (Capacitor shell)

Not a server module (no toggle). Decisions: D-21, D-37, ADR-0006. Prefix: `AND`. Milestone M10.
The Android app is the **same React build** as the PWA, wrapped by Capacitor (WebView), plus a small Kotlin layer
for what a WebView does badly. Native Compose screens are deferred until a measured limit justifies them.

## 1. Why a shell, not a native rewrite (summary of ADR-0006)

| Option | Cost | Benefit | Verdict |
|---|---|---|---|
| Installable PWA only | lowest | no store, instant updates | **ships first** (M4+); iOS users stay here |
| Capacitor shell + Kotlin plugins | low–medium | store presence, Keystore, deep links, later widget | **chosen for M10** |
| Native Kotlin + Compose | high (second UI, second offline stack) | best UX, widgets, background work | deferred; needs evidence from usability trials |
| TWA | very low | store listing of the PWA | rejected: cannot hold the refresh token outside cookies, no native plugins, cookie scope tied to one origin |

## 2. Structure

```
android/
  capacitor.config.ts          # appId, appName, webDir=../web/dist, server.androidScheme=https, server.hostname=app.kinevo.localhost
  app/src/main/java/.../       # Kotlin: MainActivity, plugins below
  app/src/main/res/            # icons (adaptive), splash, strings (en, id)
```

## 3. Kotlin plugins (the whole native surface for 1.0)

| Plugin | Purpose | Notes |
|---|---|---|
| `SecureStore` | Store/read/delete the refresh token and server URL | `EncryptedSharedPreferences` / Keystore-backed; excluded from Android Auto Backup (`android:allowBackup=false` for these prefs); no other plugin may read it |
| `DeepLink` | Handle `kinevo://connect?url=<https server url>` to prefill the server URL (QR/link from the admin) | validates the URL as in ADR-0006 before use |
| `AppInfo` | Version name/code, API compatibility check | `GET /instance` supplies `api_version`, `min_client_build` |

No other native code in 1.0. A home-screen widget (Today/Now) is **explicitly post-1.0** (RFC with usage evidence).

## 4. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| AND-01 | **First run**: a "Connect to your Kinevo" screen asks for the server address (or uses the deep link/QR). The app calls `GET <url>/api/v1/instance` over HTTPS (HTTP allowed only for `localhost`/private-LAN addresses with a visible warning and a build flag `allowCleartextLan`, default off in release builds) and shows the instance name/version before the user signs in. | Instrumented test with a fake server. |
| AND-02 | **Auth**: login/refresh use the Android token transport (D-37). The refresh token lives only in `SecureStore`; the access token only in JS memory. Logging out deletes the stored token and calls `/auth/logout`. | Tests; storage inspection test shows no token in WebView storage. |
| AND-03 | **Origin/CORS**: the WebView origin is `https://app.kinevo.localhost`; the server's CORS allow-list contains only that origin for the API (`KINEVO_ANDROID_ORIGIN=true`), with `Authorization`, `Content-Type`, `Idempotency-Key`, `If-Match`, `X-Kinevo-Client`, `X-Request-Id` allowed and no credentials mode. | CORS tests incl. a disallowed origin. |
| AND-04 | **Compatibility**: the shell compares the server's `api_version` and `min_client_build` with its own build; if unsupported, it shows "Update required" (link to the release page) and does not log in. The web build inside the APK is the exact web release it was built with. | Tests with stubbed responses. |
| AND-05 | **Offline**: identical to the PWA (IndexedDB queue + cache, `sync` module). No Android `WorkManager` background sync in 1.0; the queue drains when the app is foregrounded (SYN-C3). | E2E offline on emulator. |
| AND-06 | **Back button/gestures**: hardware/gesture back navigates the SPA history, closes dialogs first, and exits only from the Today root. **Edge-to-edge** layout respects insets. Status/navigation bar contrast follows theme. | Instrumented UI test. |
| AND-07 | **Accessibility**: TalkBack announces screen titles, live regions for toasts; touch targets ≥ 48 dp; font scale up to 200 % without clipping (`09-design-system.md` §Accessibility). | Manual TalkBack checklist + Espresso accessibility checks. |
| AND-08 | **Permissions**: only `INTERNET` and `ACCESS_NETWORK_STATE`. No location, contacts, storage, notifications, camera. Cleartext traffic disabled by network security config except the LAN flag in AND-01. | Manifest lint test. |
| AND-09 | **Distribution**: signed release APK/AAB built in CI from a tag; attached to the GitHub Release with SHA-256 and SBOM; F-Droid metadata maintained in-repo; Play Store listing is a maintainer action (`15-cloud.md`). Signing keys are never in the repo. | Release dry-run. |
| AND-10 | **WebView hardening**: JavaScript interface limited to Capacitor's; `setAllowFileAccess(false)` except bundled assets; navigation limited to the app origin and the configured server origin; external links open in the system browser; `mixedContentMode = never`. | Instrumented tests. |
| AND-11 | **Privacy**: no analytics SDKs, no crash reporter that uploads data without opt-in; release builds exclude debugging. | Dependency allow-list check. |

## 5. Failure states

| Failure | UX |
|---|---|
| Server unreachable at connect | "Can't reach this address" + checklist (HTTPS? same network? firewall?) + retry |
| Certificate invalid | Hard fail with explanation; no "proceed anyway" |
| Refresh token revoked (reuse detected) | Sign-in screen with message `auth.session_revoked` |
| Server upgraded beyond client | AND-04 screen |
