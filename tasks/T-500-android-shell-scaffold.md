---
id: T-500
title: Scaffold the Capacitor Android shell with the hardened manifest, network config and SDK setup script
milestone: M10
kind: feature
status: todo
depends_on: [T-455]
requirements: [AND-08, AND-10, AND-11, D-21, D-37, ARCH-02]
branch: m10-t500-android-shell-scaffold
budget_lines: 400
assignee: ""
---

# T-500: Scaffold the Capacitor Android shell with the hardened manifest, network config and SDK setup script

## Goal

`android/` contains a buildable Capacitor shell that bundles the web build, requests only the two allowed permissions, forbids cleartext in release builds and hardens the WebView; the SDK can be installed by one script.

## Context

> **Structure** (`spec/modules/android.md` §2): `android/capacitor.config.ts` (`appId`, `appName`, `webDir=../web/dist`, `server.androidScheme=https`, `server.hostname=app.kinevo.localhost`), `android/app/src/main/java/.../` (Kotlin: `MainActivity` and the plugins of T-501), `android/app/src/main/res/` (adaptive icons, splash, strings en and id). Capacitor 8 (`@capacitor/core`, `/android`, `/cli`) from the stack map; Kotlin + Gradle + Android Gradle Plugin versions resolved in this task and recorded in the stack map (CQ-16: pin and note licences).
> **AND-08** permissions: only `INTERNET` and `ACCESS_NETWORK_STATE`; no location, contacts, storage, notifications, camera. Cleartext traffic disabled by the network security config except for the LAN flag of AND-01 (`allowCleartextLan`, default off in release builds; debug builds may enable it for emulator testing against `10.0.2.2`). **AND-10** WebView hardening: JavaScript interface limited to Capacitor's; `setAllowFileAccess(false)` except bundled assets; navigation limited to the app origin and the configured server origin; external links open in the system browser; `mixedContentMode = never`. **AND-11** no analytics SDKs and no crash reporter that uploads data; release builds exclude debugging; a dependency allow-list check fails on any new Gradle dependency outside the list. **`android:allowBackup=false`**.
> **SDK setup** (`scripts/android-sdk.sh`): idempotent; checks `JAVA_HOME`/`java` (JDK 17+), downloads the Android command-line tools from `dl.google.com` into `$HOME/.local/share/android-sdk` (override `ANDROID_HOME`), accepts licences non-interactively, installs `platform-tools`, `platforms;android-<compileSdk>` and `build-tools;<version>` read from the Gradle config, and prints `export ANDROID_HOME=…`. With no network it exits 3 and prints `environment-limited`. It never edits shell profiles.
> **Gradle wrapper**: committed (`gradlew`, `gradle/wrapper/*`) with a verified distribution SHA-256 (`distributionSha256Sum`) so the build is reproducible.

## Scope

**In:**
- `android/**` scaffold (Gradle files, manifest, network security config, resources, `capacitor.config.ts`), `web` script `build:android` (build then `cap sync android`), `scripts/android-sdk.sh`, `scripts/check-android-manifest.py` (permission, cleartext, backup, exported components, dependency allow-list checks) wired into `just spec`, `just android-build` (debug APK) and `just android-instrumented` (instrumented tests on an emulator/device); both environment-limited without SDK/emulator.

**Out:**
- Kotlin plugins: T-501. Connect flow: T-502. Release: T-503.

## Files

| Path | Action | Notes |
|---|---|---|
| `android/**` | create | shell |
| `scripts/{android-sdk.sh,check-android-manifest.py}` | create |  |
| `scripts/tests/test_android_manifest.py` | create | AC-1…AC-7 |
| `web/package.json` | modify | scripts and Capacitor dev dependencies |
| `justfile` | modify | `android-build`, manifest check in `spec` |
| `spec/standards/open-source-stack.md` | modify | record resolved Android versions |

## Interfaces

```text
$ scripts/android-sdk.sh        # exit 0 installed | 3 environment-limited
$ just android-build            # debug APK at android/app/build/outputs/apk/debug/
```

## Business rules

1. Release builds have no cleartext, no debuggable flag and no backup.
2. Only the two permissions exist; the check fails on any other.
3. The app origin is exactly `https://app.kinevo.localhost`.

## Security

- No signing material in the repository (AND-09).
- Exported components are limited to `MainActivity` (launcher and the `kinevo://connect` intent filter added in T-501).
- Gradle dependency verification metadata is generated and committed.

## Implementation notes

- Android SDK, emulator and device steps are **environment-limited** when `scripts/android-sdk.sh` cannot install the SDK (no network) or no emulator can run; the Report names the exact command and error. They never block the PR.
- If Capacitor 8 requires a minSdk or JDK different from the local one, record it and make the script check it.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the manifest **When** checked **Then** only `INTERNET` and `ACCESS_NETWORK_STATE` are requested | `test_android_manifest::and_08_permissions_exact` |
| AC-2 | **Given** the release network config **When** checked **Then** cleartext is disabled and the LAN flag is off | `test_android_manifest::and_08_cleartext_off_in_release` |
| AC-3 | **Given** the manifest **When** checked **Then** `allowBackup=false`, not debuggable in release, only the launcher activity exported | `test_android_manifest::and_10_exported_and_backup` |
| AC-4 | **Given** the Gradle dependency list **When** checked **Then** only allow-listed coordinates are present (no analytics or crash SDKs) | `test_android_manifest::and_11_dependency_allow_list` |
| AC-5 | **Given** the Capacitor config **When** read **Then** the https scheme and app hostname are exact | `test_android_manifest::capacitor_config_origin` |
| AC-6 | **Given** no network **When** `android-sdk.sh` runs **Then** exit 3 with the environment-limited message | `test_android_manifest::sdk_script_environment_limited_exit_code` |
| AC-7 | **Given** an SDK **When** `just android-build` runs **Then** a debug APK is produced (or the Report says environment-limited) | `just android-build` |

## Tests

```
python3 -m unittest scripts.tests.test_android_manifest -v
just android-build   # environment-limited without SDK
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
