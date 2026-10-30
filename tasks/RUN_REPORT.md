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
