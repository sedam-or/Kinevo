# ADR-0005 — Small Alpine runtime image with a shell

- Status: **Accepted** (2026-10-25) — D-14.

## Context
The runtime image should be small and hardened, yet operators and PaaS templates (Railway, Render, Coolify, Cloudron) often need a shell for health checks, one-off commands (`kinevo admin …`) and debugging. The binary is static (musl), so it needs almost nothing from the base image.

## Decision
Ship `alpine` (pinned by digest) containing only the static `kinevo` binary, CA certificates and busybox `sh`, running as non-root UID 10001 with a read-only root filesystem, no capabilities, and an in-binary `kinevo health` command for `HEALTHCHECK` (no curl/wget needed).

## Alternatives
| Option | For | Against |
|---|---|---|
| distroless/static | smallest attack surface, no shell | `docker exec` debugging and some PaaS hooks are harder; CLI admin tasks need `docker run … kinevo admin` (possible but less friendly) |
| `scratch` | minimal | no CA bundle unless copied, no shell, no user database |
| Debian slim | familiar | larger, glibc-linked binary |

## Consequences
- Slightly larger attack surface (busybox) accepted; scanned in CI (Trivy/Grype); `cap_drop: ALL`, `no-new-privileges`, read-only rootfs mitigate.
- **Revisit if** scan findings in the base image recur without fixes, or size/operator feedback favours distroless — switching is a Dockerfile change plus documenting `docker run … admin` usage.
