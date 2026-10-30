# Implementation run report

## Disk and cleanup checkpoints

The existing run report was absent when checked during T-036. The values below are the first retained checkpoint for this continuation; earlier run-wide disk output was not available in the current checkout and is not reconstructed.

### T-036 continuation start

Captured before the first test/build in this continuation on 2026-10-29:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  408G   57G  88% /
```

### T-036 continuation final

Captured after the final `just check` on 2026-10-29:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  408G   57G  88% /
```

Cleanup: none. Reused the existing `kinevo-dev-pg` PostgreSQL 17 container. No image was built or pulled; no containers, images, volumes, or `target/` files were removed.

### T-037 continuation start

Captured before the first T-037 test/build on 2026-10-29:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  409G   57G  88% /
```

### T-037 continuation final

Captured after the final `just check` on 2026-10-30:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  412G   54G  89% /
```

Cleanup: none. Reused the already-running `kinevo-dev-pg` PostgreSQL 17 container. No image was built or pulled; no containers, images, volumes, or `target/` files were removed.

### T-038 continuation start

Captured before the first T-038 test/build on 2026-10-30:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  412G   54G  89% /
```

### T-038 continuation final

Captured after the final `just check` on 2026-10-30:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  412G   53G  89% /
```

Cleanup: none. Reused the already-running `kinevo-dev-pg` PostgreSQL 17 container. No image was built or pulled; no containers, images, volumes, or `target/` files were removed.

## Run-wide resource decisions

| Decision | Rule recorded | Current evidence/status |
|---|---|---|
| D-102 | One shared PostgreSQL server; each database test gets and drops a unique database; no Testcontainers fallback. | Reused active `kinevo-dev-pg` (`postgres:17-alpine`) for T-038; T-011's original Testcontainers run is historical and documented as superseded. |
| D-103 | Root development profile uses `debug = 0`; `CARGO_PROFILE_DEV_DEBUG=2` is the local override. | Verified in root `Cargo.toml`. |
| D-104 | Install `cargo-sweep` once and sweep at milestone boundaries; never use `cargo clean` or manually delete `target/`. | `cargo-sweep` is installed and `cargo sweep --help` exited 0; M1 boundary sweep remains due after its integration task. |
| D-105 / D-109 / D-125 | No Docker image builds or pulls; check disk before operations; prune only verified Kinevo resources below 20 GB, stop below 15 GB, and preserve exact run-start/end measurements and cleanup records. | This continuation began at 53 GB free, above the cleanup threshold. No resources were pruned. |

### D-125 documentation continuation start

Captured on 2026-10-30 at 01:01:23 WIB before documentation and consistency checks:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  413G   53G  89% /
```

Cleanup: none. No build, test, or container was started for this documentation update.

### D-125 documentation continuation final

Captured on 2026-10-30 at 01:04:54 WIB after `spec_tool.py check`, `tasks_index.py --check`, shell syntax validation, and `git diff --check`:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  414G   52G  89% /
```

Cleanup: none. Free space remained above 20 GB; no Docker cleanup, image operation, build, test, or container start was performed for this documentation update.

### Requested cleanup review (2026-10-30)

Before Kinevo target sweep:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  405G   61G  87% /
```

`CARGO_TARGET_DIR=/home/kepolu/project/open-source/nivaras/Kinevo/target cargo sweep --time 7` exited 0 and reported `Cleaned nothing`. The shared Cargo registry cache was 247 MB and registry sources were 1.7 GB; they were retained because they are shared with other projects. Docker inventory showed the active Kinevo PostgreSQL 17 and app containers, stopped containers labeled only as Testcontainers-managed, and dangling images without Kinevo ownership metadata. No container, image, or volume was removed; volumes were retained as required by D-125.

After cleanup review:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  405G   61G  87% /
```

### T-039 continuation start

Captured immediately before the first T-039 build on 2026-10-30:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  327G  136G  71% /
```

### T-039 continuation final

Captured after `just check` and the D-42 E2E clock test on 2026-10-30:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  329G  134G  72% /
```

Cleanup: none. Reused the already-running `kinevo-dev-pg` PostgreSQL 17 container. No image was built or pulled; no containers, images, volumes, or `target/` files were removed.

### Ad-hoc cleanup request review (2026-10-30 10:48 WIB)

Disk before the review and documentation checks:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  330G  133G  72% /
```

The Cargo registry cache was 247 MB, registry sources 1.7 GB, and this repository's `target/` was 21 GB. No Cargo build process was active. Docker reported 0 B build cache; all eight listed containers were running, every listed image had a name, and no stopped container or dangling image was listed. Seven volumes were present; `infrastructure_lifesync_pgdata` had no links but belongs to another project. No Cargo or Docker resources were removed: the shared registry was retained, the repo-scoped `cargo sweep` remains scheduled for the M1 milestone boundary, and Docker pruning is not allowed above 20 GB free. Volumes, named images, active containers and other projects' resources were preserved. No build, test, image operation or container start was performed for this review.

Disk after the review and documentation checks:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  330G  133G  72% /
```

### T-042 architecture checks (2026-10-30)

Before the final targeted acceptance diagnostic:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  341G  122G  74% /
```

The targeted `architecture` and `rbac_matrix` diagnostic ran 9 tests: 7 passed and 2 failed on the existing CA-02 / CA-04 findings recorded in `T-042` Q-1. PostgreSQL was not started for that diagnostic. An earlier exploratory full `just check` reused the existing disposable PostgreSQL server; it did not pull or build images. Out-of-scope experimental changes were reverted. No containers, images, volumes, registry cache, or `target/` files were removed.

Cleanup: none. No environment-limited checks.

After the T-042 diagnostic and documentation updates:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  341G  122G  74% /
```

Cleanup: none. The disk stayed above the 20 GB cleanup threshold; no container, image, volume, registry cache, or build output was removed.

### T-043 web scaffold continuation start

Captured immediately before the final T-043 `just check` attempt on 2026-10-30:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  342G  120G  75% /
```

### T-043 web scaffold continuation final

Captured after the successful full `just check` and the isolated web-assets acceptance suite on 2026-10-30:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  342G  120G  75% /
```

Cleanup: none. Reused the existing disposable PostgreSQL 17 server. No Docker image was built or pulled, and no container, image, volume, Cargo target, or shared package cache was removed. The first retry used a broken Node 22/Corepack path and stopped at web typecheck; the gate passed after selecting the pinned Node 26.13.0 and pnpm 12.16.1 toolchain under `/tmp`.

### T-044 token and font continuation start

Captured before the first T-044 font subset, build, or test on 2026-10-30:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  342G  120G  75% /
```

### T-044 token and font continuation final

Captured after the final successful `just check` on 2026-10-30:

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  343G  120G  75% /
```

Cleanup: none. Reused the existing `kinevo-dev-pg` PostgreSQL 17 container; no image was built or pulled, and no container, image, volume, `target/`, or shared cache was removed. All recorded T-044 pre-operation checks showed 120 GB available, above the D-128 cleanup and stop thresholds.

### T-045 continuation checkpoint before typecheck and tests (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup was performed. This checkpoint was captured before TypeScript checks and the component test suite. The existing PostgreSQL server and Docker resources were left unchanged.

### T-045 checkpoint before component suite retry (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup was performed. Existing disposable PostgreSQL and Docker resources were preserved.

### T-045 checkpoint before final Vitest retry (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup was performed. No container or image operation was started.

### T-045 checkpoint before lint, browser and full gate (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup was performed. No container start, image build, or registry pull was performed for this checkpoint.

### T-045 checkpoint before formatting/lint and focused test pass (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup was performed. Existing PostgreSQL and Docker resources remain untouched.

### T-045 checkpoint before Playwright browser tests (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup was performed. Browser availability will be reported from the exact Playwright result; no browser download or license prompt is initiated.

### T-045 checkpoint before Playwright retry (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup or browser installation was performed. The first Playwright attempt exposed a `webServer` argument separator issue and ended with `Error: Process from config.webServer exited early.` The config command was corrected before this retry.

### T-045 checkpoint before Chromium-only browser verification (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No browser binaries were installed and no system packages were changed. Chromium was available; Firefox/WebKit availability was checked by the exact task command.

### T-045 checkpoint before `just check` (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

The existing `/tmp/kinevo-dev-pg/mode` identifies the reused `docker` PostgreSQL mode. The shared `postgres:17-alpine` server is reused; the script is configured with image pulls disabled. No cleanup was performed.

### T-045 checkpoint before full gate retry (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

The first `just check` stopped in Biome because Playwright had generated `web/test-results/.last-run.json`; that generated file was removed, and Playwright output was redirected to `/tmp/kinevo-playwright-results`. No cache, Cargo target, image, container, or volume was removed.

### T-045 checkpoint before URL normalization regression test (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup or container operation was performed.

### T-045 checkpoint before green URL regression verification (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup was performed. Regression test initially failed as expected because non-root path slashes were incorrectly removed; normalization was corrected to trim only the origin root slash.

### T-045 checkpoint before updated Chromium accessibility tests (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup or browser installation was performed. Chromium is an existing Playwright browser installation.

### T-045 checkpoint before final `just check` (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

The existing disposable PostgreSQL 17 container is reused with the script's image-pull-disabled configuration. No cleanup was performed.

### T-045 final checkpoint after full verification (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  344G  119G  75% /
```

No cleanup was performed; the shared PostgreSQL 17 container and all Docker resources were preserved.

### T-045 verification and environment limits

- TDD red was observed for the missing component modules, then the six named acceptance tests were added and passed. A URL-root normalization regression test also failed first (`https://example.test/path/` became `https://example.test/path`) and passed after the fix.
- `pnpm --dir web typecheck`: passed.
- `pnpm --dir web lint`: passed; four existing `!important` reduced-motion warnings remain.
- `pnpm --dir web test`: 22 tests passed across 15 files.
- `just check`: exit 0. Rust nextest: 385 passed, 1 skipped. Web Vitest: 22 passed. Cargo fmt, Clippy, typecheck, lint, production web build, cargo-deny, spec validation, contrast, task-index validation and OpenAPI drift checks completed. `spec_tool.py check`: 0 findings / 60 files; `tasks_index.py --check`: 0 problems / 132 tasks; contrast failures: 0; OpenAPI drift: clean. Cargo-deny reported pre-existing duplicate-crate and unmatched-license-allowance warnings; all deny categories ended `ok`.
- `pnpm --dir web exec playwright test tests/e2e/components.a11y.spec.ts --project=chromium`: 2 passed, including axe serious/critical scan and target-size check.
- Exact all-project command `pnpm --dir web exec playwright test tests/e2e/components.a11y.spec.ts`: 2 Chromium tests passed; Firefox and WebKit could not launch due to environment dependencies. Firefox error: `Executable doesn't exist at /home/kepolu/.cache/ms-playwright/firefox-1555/firefox/firefox` (Playwright suggested `pnpm exec playwright install`; not run). WebKit error: `Host system is missing dependencies to run browsers`; suggested dependencies were `libicu74` and `libflite1` (the tool also suggested sudo commands; none were run).
- `budget_lines: 400` was exceeded: the measured change was approximately 1,469 added/removed lines excluding `web/pnpm-lock.yaml` (includes the run log and stack allowance update). Per D-55, this is guidance and not a blocker.

### T-046 checkpoint before tests (2026-10-30)

```text
Filesystem                    Size  Used Avail Use% Mounted on
/dev/mapper/nvme0n1p3crypted  468G  345G  118G  75% /
```

No cleanup or container operation was performed.
