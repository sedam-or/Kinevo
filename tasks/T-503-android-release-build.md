---
id: T-503
title: Add the signed-release build scripts, F-Droid metadata and the release dry run
milestone: M10
kind: feature
status: todo
depends_on: [T-502]
requirements: [AND-09, AND-11, DST-05, DST-08, SEC-13]
branch: m10-t503-android-release-build
budget_lines: 340
assignee: ""
---

# T-503: Add the signed-release build scripts, F-Droid metadata and the release dry run

## Goal

A tagged build can produce a signed APK/AAB with checksums and an SBOM using keys supplied from outside the repository, and F-Droid metadata is maintained in-repo; nothing secret is ever committed.

## Context

> **AND-09** signed release APK/AAB built in CI from a tag, attached to the GitHub Release with SHA-256 and SBOM; F-Droid metadata maintained in-repo; the Play Store listing is a maintainer action (`15-cloud.md` M-STORE); **signing keys are never in the repo**. **DST-05** per-tag artefacts include checksums and SBOM; **DST-08** operators can verify authenticity (checksums for APK documented).
> **Scripts** (the CI workflow is created in Phase 5 and calls these): `scripts/android-release.sh` — requires `KINEVO_ANDROID_KEYSTORE_FILE`, `KINEVO_ANDROID_KEYSTORE_PASSWORD_FILE`, `KINEVO_ANDROID_KEY_ALIAS`, `KINEVO_ANDROID_KEY_PASSWORD_FILE`; refuses to run when any is missing (exit 4, message names the missing variable, never prints values); builds `assembleRelease` and `bundleRelease` with the web build embedded and the version from the Git tag (`versionName`) and a monotonically increasing `versionCode` derived from `MAJOR*10000+MINOR*100+PATCH`; writes `SHA256SUMS` and a CycloneDX SBOM (via the Gradle CycloneDX plugin, added to the stack map and dependency allow-list) into `dist/android/`. `scripts/android-debug-keystore.sh` creates a throwaway **debug** keystore in a temp dir for dry runs (never reused for releases).
> **Dry run** (`just android-release-dry-run`): builds an unsigned or debug-signed artefact with the same pipeline, so the pipeline is verified without secrets; **environment-limited** without SDK. **F-Droid**: `fdroid/metadata/org.kinevo.app.yml` (build recipe from the tag, no proprietary deps, AntiFeatures empty), fastlane-style `fdroid/metadata/org.kinevo.app/en-US/*.txt` and `id/*.txt` text, screenshots placeholders listed but not invented. **Repo hygiene**: `.gitignore` covers keystores, `local.properties`, `*.jks`, `*.keystore`; a test scans the repo tree for such files.
> **Secrets** (SEC-13): a gitleaks-style pattern test asserts no signing material patterns in tracked files.

## Scope

**In:**
- `scripts/{android-release.sh,android-debug-keystore.sh}`, Gradle signing config reading environment-provided paths, CycloneDX plugin wiring, `fdroid/**` metadata, `.gitignore` additions, tests, `just android-release-dry-run`.

**Out:**
- GitHub Actions workflow (Phase 5), Play Console steps (maintainer).

## Files

| Path | Action | Notes |
|---|---|---|
| `scripts/{android-release.sh,android-debug-keystore.sh}` | create |  |
| `android/app/build.gradle.kts` | modify | signing from env, version from tag |
| `fdroid/**` | create | metadata |
| `.gitignore` | modify | keystores |
| `scripts/tests/test_android_release.py` | create | AC-1…AC-7 |
| `justfile` | modify | dry-run recipe |

## Interfaces

```text
$ scripts/android-release.sh v1.2.3     # exit 0 | 4 missing signing env | 3 environment-limited (no SDK)
dist/android/{kinevo-1.2.3.apk, kinevo-1.2.3.aab, SHA256SUMS, kinevo-android.cdx.json}
```

## Business rules

1. Signing inputs come only from environment-named files; values are never echoed.
2. `versionCode` is deterministic from the tag.
3. The dry run never uses real keys.

## Security

- Tracked-file scan finds no keystore or key patterns.
- The debug keystore is created in a temp dir with mode 600 and removed after use.

## Implementation notes

- Android SDK, emulator and device steps are **environment-limited** when `scripts/android-sdk.sh` cannot install the SDK (no network) or no emulator can run; the Report names the exact command and error. They never block the PR.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** missing signing variables **When** the release script runs **Then** exit 4 naming the variable without values | `test_android_release::and_09_refuses_without_signing_env` |
| AC-2 | **Given** tags `v1.2.3` and `v0.10.0` **When** versions are derived **Then** `versionCode` 10203 and 1000 and `versionName` match | `test_android_release::version_derivation` |
| AC-3 | **Given** the tracked files **When** scanned **Then** no keystores, `local.properties` or key patterns | `test_android_release::sec_13_no_signing_material_tracked` |
| AC-4 | **Given** the F-Droid metadata **When** linted **Then** required fields exist in en and id and no proprietary dependency is listed | `test_android_release::fdroid_metadata_lint` |
| AC-5 | **Given** a dry run with an SDK **When** executed **Then** an artefact, `SHA256SUMS` and an SBOM are produced | `just android-release-dry-run` |
| AC-6 | **Given** `SHA256SUMS` **When** verified with `sha256sum -c` **Then** it passes | `test_android_release::dst_08_checksums_verify` |
| AC-7 | **Given** the Gradle dependency list **When** the SBOM is generated **Then** it includes no allow-list violations | `test_android_release::and_11_sbom_matches_allow_list` |

## Tests

```
python3 -m unittest scripts.tests.test_android_release -v
just android-release-dry-run   # environment-limited without SDK
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
