# Kinevo browser E2E smoke (rescue R1)

Real-browser verification for the UI/UX stabilization phase. Proves the app
shell and primary surfaces mount and render in an actual browser, not just via
unit/adapter tests (see `docs/browser-e2e.md` §2 and `docs/design.md` §74).

## Prerequisites

- Dev stack running and the SPA assets built (the app container serves them):
  `docker compose -f infrastructure/docker-compose.yml up -d` then
  `npm run build` in `server/` (root-owned `public/build` must be cleared first
  if a previous container build left it).
- A reachable owner account to log in (see `tests/e2e/tests/helpers.ts` for the
  default credential, overridable via `E2E_OWNER_PASSWORD`).
- Docker (network capable of `docker run --network host`).

## Running

```sh
# build the Playwright runner image (first time / after dependency changes)
make e2e-build

# run the smoke tests against http://127.0.0.1:8000
make e2e
```

The runner attaches to the host network so `E2E_BASE_URL=http://127.0.0.1:8000`
reaches the running dev SPA. `make e2e` resets the sandbox database first
(`make e2e-clean`): the shared owner account accumulates fixtures from every
run, and unbounded growth breaks layout-dependent checks (P17-021 — 671
accumulated goals pushed Analytics past the browser's 32767px full-page
screenshot cap). Run `make e2e-clean` manually any time the sandbox feels
polluted; it truncates domain tables only (users/profiles/configs survive).

## Specs

- `tests/login.spec.ts` — core loop entry: login → Today, invalid-password
  rejection.
- `tests/journeys.spec.ts` — every primary navigation destination renders its
  surface (Today, Week, Schedule, Goals, Tasks, Knowledge, Canvas, Analytics,
  Settings).
- `tests/p28-ux-audit.spec.ts` — Phase-18 UX verification (docs/ui-audit.md
  §10): full surface inventory, empty-state audit, personalization,
  information architecture, CTA hierarchy. Authenticates once over the API and
  reuses a shared page so the run stays under per-user rate limits.

## Adding browsers

The Chromium project is defined in `playwright.config.ts`. To cover Firefox
and WebKit, add corresponding `projects` entries (the `mcr.microsoft.com/playwright`
base image bundles all three) and re-run `make e2e`, then update the
`docs/browser-e2e.md` §4 matrix — do not claim a browser is covered until a run
proves it.
