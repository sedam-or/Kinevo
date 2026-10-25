# ADR-0006 — Android app as a Capacitor shell around the web build; token transport for native

- Status: **Accepted** (2026-10-25) — D-21, D-37.

## Context
The owner asked to focus on a web app (PWA) and an Android app ("Kotlin or the right language"). Maintaining a second full UI and a second offline stack natively would double cost for one maintainer. The web app already needs offline support (IndexedDB queue) and is a PWA.

## Decision
1. **PWA first** (M4–M5), installable on Android and desktop; iOS users use it from the browser.
2. **Android app (M10)** = Capacitor shell bundling the exact web build, plus a small Kotlin layer (secure storage, deep links, app info). No native screens in 1.0; a home-screen widget and any Compose UI require an RFC with evidence of a WebView limit.
3. **Auth transport:** the shell's origin (`https://app.kinevo.localhost`) differs from the user's server, so cookies would be cross-site. With `X-Kinevo-Client: android`, the refresh token travels in the JSON body and lives in Keystore-backed encrypted storage; rotation and reuse detection are identical to the web (`standards/auth.md`).
4. **CORS:** only the shell origin is allowed, and only for the API, without credentials mode.
5. **Compatibility:** `GET /instance` returns `api_version` and `min_client_build`; the shell refuses unsupported servers.

## Alternatives
| Option | For | Against |
|---|---|---|
| TWA (Chrome-based wrapper) | easiest store listing | no native plugins; token in cookies tied to one origin; no widget path |
| Remote `server.url` in Capacitor | cookies work (same origin) | Capacitor discourages production use; online dependency for first load; no versioned bundle |
| Native Kotlin + Compose + UniFFI scheduler | best UX | second UI + second offline stack; large cost; unjustified without evidence |
| Flutter / React Native | single codebase mobile | rewrites the web UI, loses Tiptap/Excalidraw reuse |

## Consequences
- The server must expose compatibility info and the CORS allowance; web build and shell version together.
- Android release pipeline signs and publishes APK/AAB (`AND-09`).
- **Revisit if** usability trials (V3) or reviews show WebView limits (input latency, gestures, notifications) that matter; start with the widget/notification plugin before any native screens.
