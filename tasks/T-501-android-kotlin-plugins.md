---
id: T-501
title: Implement the SecureStore, DeepLink and AppInfo Kotlin plugins with their bridge
milestone: M10
kind: feature
status: todo
depends_on: [T-500]
requirements: [AND-02, AND-01, AND-04, AND-10, D-37]
branch: m10-t501-android-kotlin-plugins
budget_lines: 400
assignee: ""
---

# T-501: Implement the SecureStore, DeepLink and AppInfo Kotlin plugins with their bridge

## Goal

The shell can keep the refresh token out of WebView storage, accept a `kinevo://connect` link and report its own build, with the JavaScript surface kept minimal and tested.

## Context

> **Plugins** (`spec/modules/android.md` §3, the whole native surface for 1.0): `SecureStore` stores, reads and deletes the refresh token and the server URL using `EncryptedSharedPreferences`/Keystore-backed storage, excluded from Auto Backup, readable by no other plugin; `DeepLink` handles `kinevo://connect?url=<https server url>` and returns the validated URL to JS to prefill the connect screen (validation as in ADR-0006: https only, no credentials in the URL, no fragments, length ≤ 2048; `http` only for `localhost` or private-LAN addresses when `allowCleartextLan` is on, with a visible warning); `AppInfo` returns `versionName`, `versionCode` and the minimum API contract the build was made against. No other native code. A home-screen widget is explicitly post-1.0.
> **Bridge contract** (TypeScript side in `web/src/native/`): `SecureStore.get/set/delete` accept only the keys `refresh_token` and `server_url`; `DeepLink.getInitial()` and `addListener('connect', …)`; `AppInfo.get()`. On non-Android platforms (browser/PWA) the same module exports a no-op/web implementation, so the web app has one code path.
> **AND-02 storage inspection**: tokens must not appear in WebView storage (localStorage, IndexedDB, cookies); an instrumented/JVM test inspects the app's data directories after a login simulation. **AND-10** the JavaScript interface exposes only Capacitor's plugin bridge; nothing is added with `addJavascriptInterface`.
> **Testing approach**: JVM unit tests (JUnit + Robolectric if it is already allow-listed, otherwise pure-Kotlin validators with fakes) for URL validation and storage key rules; instrumented tests (Espresso) are written but run only when an emulator/device exists (**environment-limited** otherwise).

## Scope

**In:**
- Kotlin sources and unit tests for the three plugins, intent filter for `kinevo://connect`, TypeScript bridge `web/src/native/{secureStore,deepLink,appInfo}.ts` with web fallbacks and Vitest tests, instrumented tests files (not executed here), registration in `MainActivity`.

**Out:**
- Connect screen and token transport: T-502.

## Files

| Path | Action | Notes |
|---|---|---|
| `android/app/src/main/java/**/{SecureStorePlugin,DeepLinkPlugin,AppInfoPlugin,UrlValidator}.kt` | create |  |
| `android/app/src/test/**` | create | JVM tests |
| `android/app/src/androidTest/**` | create | instrumented tests (environment-limited) |
| `android/app/src/main/AndroidManifest.xml` | modify | deep link intent filter |
| `web/src/native/**` | create | bridge and fallbacks |
| `web/tests/native.test.ts` | create | AC-1…AC-8 |

## Interfaces

```ts
export interface SecureStorePlugin { get(o: { key: 'refresh_token' | 'server_url' }): Promise<{ value: string | null }>; set(o: { key: 'refresh_token' | 'server_url'; value: string }): Promise<void>; delete(o: { key: string }): Promise<void> }
export interface DeepLinkPlugin { getInitial(): Promise<{ url: string | null }> }
```

## Business rules

1. Only the two keys are accepted; any other key is an error.
2. The deep link never carries credentials; the URL is validated before it reaches JS.
3. The refresh token never leaves SecureStore except into memory for a refresh call.

## Security

- Keystore-backed encryption; no backup of the prefs file.
- Deep-link URLs are untrusted input: https-only, no userinfo, bounded length.
- No logging of tokens or URLs with query strings.

## Implementation notes

- Android SDK, emulator and device steps are **environment-limited** when `scripts/android-sdk.sh` cannot install the SDK (no network) or no emulator can run; the Report names the exact command and error. They never block the PR.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** URLs with credentials, fragments, `javascript:`, plain http to a public host and an overlong URL **When** validated **Then** all are rejected | `url_validator::and_01_rejects_unsafe_urls` |
| AC-2 | **Given** `http://192.168.1.5:8080` **When** the LAN flag is off **Then** rejected; on **Then** accepted with a warning flag | `url_validator::and_01_lan_flag_rules` |
| AC-3 | **Given** keys other than the two allowed **When** set or read **Then** the plugin rejects them | `secure_store::only_two_keys_accepted` |
| AC-4 | **Given** a stored token **When** the app data directories are inspected **Then** it is absent from WebView storage | `secure_store::and_02_no_token_in_webview_storage (instrumented, environment-limited)` |
| AC-5 | **Given** a `kinevo://connect?url=…` link **When** received **Then** the validated URL is passed to JS | `deep_link::connect_intent_passes_validated_url (instrumented)` |
| AC-6 | **Given** the web fallbacks **When** running in a browser **Then** the bridge is a no-op without errors | `native::web_fallback_is_noop` |
| AC-7 | **Given** the bridge **When** called with a disallowed key **Then** the TypeScript wrapper rejects before calling native | `native::bridge_rejects_disallowed_keys` |
| AC-8 | **Given** the `AppInfo` plugin **When** called **Then** it returns version name, code and API contract | `app_info::returns_build_info` |

## Tests

```
pnpm --dir web test
./gradlew -p android testDebugUnitTest   # environment-limited without SDK
just check
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
