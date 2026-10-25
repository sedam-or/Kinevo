# 14 — Distribution

Depends on: `11-operations.md`, `05-templates-and-setup.md`, `modules/android.md`. Decision: D-05. Prefix: `DST`.
**Every channel ends in the same setup wizard** (`05-templates-and-setup.md` §3) and runs the same OCI image.

## 1. Channels

| Channel | Audience | What the user does | What we ship | Milestone |
|---|---|---|---|---|
| **Docker one-liner / Compose** | self-hosters | `curl -fsSL …/compose.yaml` (or copy), set 3 values, `docker compose up -d`, open the URL, follow the wizard | `compose.yaml` (kinevo + postgres, pinned digests), `.env.example`, `scripts/quickstart.sh` (generates `KINEVO_SECRET_KEY` and DB passwords into a local `.env` with mode 600, prints next steps) | M1 |
| **PWA** | everyone with a browser | open the instance URL, "Install" | manifest, service worker (FE-07/08) | M4 |
| **1-click templates** | people without Docker experience | click "Deploy", fill 1–2 fields | template files for Railway, Render, Fly.io, Coolify, Cloudron `[ASSUMED priorities; confirm by demand]` kept in `deploy/<provider>/` and tested by a smoke script | M9 |
| **Managed cloud** | people who prefer hosted | sign up | the same image operated by the maintainers (`15-cloud.md`) | M9 |
| **Android app** | Android users | install APK/F-Droid/Play, enter server URL or scan QR | signed APK/AAB (`modules/android.md`) | M10 |

Out of scope: desktop/LAN installer with embedded DB (D-05), iOS app, OS packages (deb/rpm), Helm chart (RFC if requested).

## 2. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| DST-01 | One image serves all channels; behaviour differences are configuration and templates only. | Review; smoke test per channel against the same digest. |
| DST-02 | The quick-start never prints secrets to shared logs; generated secrets are written once to a local `.env` (0600) and the script tells the user to back up `KINEVO_SECRET_KEY`. | Script test. |
| DST-03 | After first start the **setup URL with token** is printed in the container log (and `docker compose logs kinevo | grep setup` is documented). | Log test (SET-04). |
| DST-04 | Each 1-click template pre-fills only non-secret defaults and generates secrets via the platform's secret mechanism; each lists the exact environment variables and the post-deploy URL. | Template lint + manual deploy test per provider (environment-limited). |
| DST-05 | Release artefacts per tag: OCI image (multi-arch amd64, arm64), Android APK/AAB (M10), checksums, SBOM, provenance, release notes with upgrade instructions and schema-change notes (RELEASING.md). | Release dry-run. |
| DST-06 | README quick start is verified by CI: a job executes the documented commands in a clean runner (compose up, health, setup via non-interactive variables, smoke journey). | CI `quickstart`. |
| DST-07 | The wizard is available in English and Indonesian from the first screen; language is auto-detected and changeable on every step. | E2E. |
| DST-08 | Operators can verify authenticity: `cosign verify` command documented; checksums for APK. | Docs test. |
| DST-09 | Telemetry: none. Update checks: none by default; the admin UI shows the running version and a link to releases. | Review. |
