---
id: T-043
title: Scaffold the React + Vite web app and serve it from the binary
milestone: M1
kind: feature
status: review
depends_on: [T-041, T-039]
requirements: [D-07, D-48, FE-01, FE-09, FE-10, FE-13, FE-16, ARCH-02]
branch: m1-t043-web-scaffold
budget_lines: 380
assignee: "OpenAI Codex"
---

# T-043: Scaffold the React + Vite web app and serve it from the binary

## Goal

`web/` builds with Vite, generates API types from `contracts/openapi.json`, and the binary serves the built files with the required security headers and an SPA fallback.

## Context

> **Project layout** (`spec/08-frontend.md` §2): `web/{index.html, vite.config.ts, tsconfig.json, package.json, src/{main.tsx, app/, api/, auth/, features/, components/, lib/, i18n/, styles/}, tests/e2e/}`.
> **Principles** (§1): the browser is never authoritative; the shell is module-driven; server state is TanStack Query, UI state is local; API types are generated from OpenAPI (`openapi-typescript`), never hand-written.
> **Stack** (`open-source-stack.md` §2): React, Vite, TypeScript, `@tanstack/react-router`, `@tanstack/react-query`, Tailwind 4, `react-aria-components`, `react-hook-form` + `zod`, `i18next`, `openapi-fetch`, Biome, Vitest + Testing Library + MSW, Playwright, pnpm (D-48). Versions: resolve the latest stable at implementation time and pin exactly; record them in the Report and update the stack map if a pinned version differs.
> **FE-10** (headers set by the server, not meta tags): CSP `default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`, empty `Permissions-Policy`, HSTS when behind HTTPS.
> **FE-13**: no third-party scripts, fonts or images; requests only to the instance origin. **FE-09**: code splitting for heavy routes (applied in later tasks); the build prints a bundle-size report.
> **ARCH-02**: the SPA is embedded in the binary (`rust-embed`), with a fallback to `index.html` for unknown non-API paths; `/api/*` never falls back.

## Scope

**In:**
- `web/` project files: `package.json` (scripts `dev`, `build`, `typecheck`, `lint`, `test`, `gen:api`), `pnpm-lock.yaml`, `tsconfig.json` (strict, `noUncheckedIndexedAccess`), `vite.config.ts` (dev proxy to `:8080`, manifest off for now), Biome config, Vitest config, `index.html`, `src/main.tsx` rendering a placeholder route tree with TanStack Router and the providers (Query, Router).
- `gen:api` script generating `src/api/schema.d.ts` from `contracts/openapi.json`; the generated file is committed and a check fails on drift (`pnpm gen:api && git diff --exit-code`).
- `src/api/client.ts` creating the `openapi-fetch` client (no auth yet).
- Embedding and serving in `kinevo-app`: `web_assets.rs` with `rust-embed` on `web/dist`, ETag and cache headers (hashed assets immutable, `index.html` no-cache), SPA fallback, security headers middleware for HTML and static responses.
- `justfile`: `setup` enables corepack and installs; `lint`, `test`, `spec` include the web scripts when `web/` exists; `build-web` recipe.

**Out:**
- Design tokens and fonts: T-044. Auth and routes beyond a placeholder: T-048+. PWA and service worker: M5.
- Docker image: T-055.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/package.json, pnpm-lock.yaml, tsconfig.json, vite.config.ts, biome.json, vitest.config.ts, index.html` | create | tooling |
| `web/src/main.tsx, src/app/providers.tsx, src/app/router.tsx` | create | placeholder shell |
| `web/src/api/client.ts, src/api/schema.d.ts` | create | generated types, client |
| `web/src/test/setup.ts` | create | Testing Library + MSW setup |
| `crates/kinevo-app/src/web_assets.rs` | create | embed, fallback, headers |
| `crates/kinevo-app/build.rs` | create | fail with a clear message when `web/dist` is missing in release builds |
| `justfile` | modify | web recipes |
| `crates/kinevo-app/tests/web_assets.rs` | create | server-side ACs |

## Interfaces

```text
pnpm --dir web gen:api     # openapi-typescript contracts/openapi.json -o web/src/api/schema.d.ts
pnpm --dir web build       # web/dist
GET /anything-not-api  → 200 index.html (+ headers)      GET /api/v1/unknown → 404 problem+json
```

## Business rules

1. Hashed asset files are served `Cache-Control: public, max-age=31536000, immutable`; `index.html` is `no-cache`.
2. Unknown `GET` paths outside `/api` and `/assets` return the SPA shell; other methods return 405/404 as normal.
3. The CSP string is a single constant in `web_assets.rs`, tested against FE-10 verbatim.
4. In debug builds without `web/dist`, the server serves a small text page explaining `just build-web` instead of failing.

## Security

- No `unsafe-inline` for scripts, no inline event handlers; Vite output is checked to emit no inline `<script>` (test over `dist/index.html`).
- Source maps are not embedded in release builds.
- Headers also apply to the error pages.

## Implementation notes

- Keep `src/app/router.tsx` to a root route and `/` placeholder; real routes arrive with their tasks.
- Pin Node via `.nvmrc`/`engines` to the active LTS at implementation time and record it.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** `contracts/openapi.json` **When** `gen:api` runs **Then** `schema.d.ts` is unchanged (committed file is current) | `web_scaffold::fe_types_generated_from_openapi` |
| AC-2 | **Given** the built app **When** `/` is requested **Then** 200, the exact CSP and the other FE-10 headers | `web_assets::fe_10_security_headers_exact` |
| AC-3 | **Given** an unknown path **When** requested **Then** the shell; `/api/v1/nope` returns problem+json 404 | `web_assets::spa_fallback_not_for_api` |
| AC-4 | **Given** hashed assets **When** requested **Then** immutable cache headers; `index.html` is `no-cache` | `web_assets::cache_headers_by_asset_kind` |
| AC-5 | **Given** `dist/index.html` **When** scanned **Then** no inline script and no external origin | `web_scaffold::fe_13_no_inline_script_no_third_party` |
| AC-6 | **Given** the placeholder app **When** rendered in Vitest **Then** it mounts without console errors | `web_scaffold::app_mounts` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
just check
cargo nextest run -p kinevo-app --test web_assets
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [x] Only task-scope files and the minimal D-56 wiring described in the Report changed; the D-55 budget overage is recorded.
- [x] Report below is filled in.

## Report

### Summary

Scaffolded the React/Vite SPA, generated and checked OpenAPI TypeScript types, configured strict TypeScript and Vitest, and embedded the production assets in the app binary. The app serves the SPA fallback outside `/api` and `/assets`, applies the FE-10 headers to static/fallback/error responses, and rejects API misses with `application/problem+json`.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `web_scaffold::fe_types_generated_from_openapi` | pass; `pnpm gen:api` and the committed-schema drift check passed |
| AC-2 | `web_assets::fe_10_security_headers_exact` | pass |
| AC-3 | `web_assets::spa_fallback_not_for_api` | pass |
| AC-4 | `web_assets::cache_headers_by_asset_kind` | pass |
| AC-5 | `web_scaffold::fe_13_no_inline_script_no_third_party` | pass against the production Vite output |
| AC-6 | `web_scaffold::app_mounts` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `pnpm --dir web typecheck` | 0 | Strict TypeScript check passed. |
| `pnpm --dir web lint` | 0 | Biome checked 15 files; no fixes required. |
| `pnpm --dir web test` | 0 | 2 files, 3 tests passed. |
| `pnpm --dir web build` | 0 | Vite 8.3.5 built production output; JS bundle 319.21 kB (101.48 kB gzip). |
| `cargo nextest run -p kinevo-app --test web_assets` | 0 | 3 tests passed, 0 skipped. |
| `just check` | 0 | Clippy, web typecheck/lint, Vite build, full Rust suite (385 passed, 1 skipped), Vitest (3 passed), cargo-deny, contrast, spec, task-index, OpenAPI, and generated-type drift gates completed. |
| `python3 scripts/spec_tool.py check` | 0 | 0 findings in 60 spec files. |
| `python3 scripts/contrast.py` | 0 | 0 failures. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated `tasks/index.md` after the status change. |
| `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks. |
| `git diff --check` | 0 | No whitespace errors. |

The first `just check` attempt used the shell's Node 22.17.0/Corepack shim and stopped at web typecheck because it referenced a missing `pnpm.cjs` at `/home/kepolu/.cache/node/corepack/v1/pnpm/12.16.1/bin/pnpm.cjs`. The gate was rerun successfully using the verified Node 26.13.0 and pnpm 12.16.1 installed under `/tmp`. The shared PostgreSQL 17 server was reused. Cargo emitted the existing non-fatal `rand::thread_rng` Clippy configuration warning and cargo-deny duplicate-base64/unmatched-allowance warnings; cargo-deny reported advisories, bans, licenses, and sources `ok`.

No acceptance check was environment-limited.

### Files changed

- `web/`: pinned package metadata and lockfile, Vite/TypeScript/Biome/Vitest config, placeholder app/providers/router, OpenAPI client and generated schema, and scaffold tests.
- `crates/kinevo-app/`: embedded asset handler, release build guard, acceptance tests, module export, route fallback wiring, and `rust-embed` dependency.
- `justfile`: web setup/build/typecheck/lint/test/OpenAPI drift gates and disk checks before builds/tests.
- `spec/standards/open-source-stack.md`: records registry-checked pins and tooling entries as required by the task.
- `Cargo.lock`: generated lockfile update.
- `tasks/T-043-web-scaffold.md`, `tasks/index.md`, and `tasks/RUN_REPORT.md`: task report, status/index, and disk/run evidence.

### Deviations

- The non-generated implementation/configuration diff is 610 changed lines (+603/-7) against the task's 380-line guidance; D-55 makes that guidance non-blocking. Generated `Cargo.lock`, `pnpm-lock.yaml`, and `schema.d.ts` are excluded. The overage is primarily app scaffolding, server asset handling, and acceptance coverage.
- Minimal adjacent wiring was added to `crates/kinevo-app/Cargo.toml`, `src/lib.rs`, and `src/serve.rs` to embed and serve the SPA. `spec/standards/open-source-stack.md` was updated because the task requires pinned dependencies and directs implementers to update the map when a selected version differs.
- The task lists `web/src/test/setup.ts` but does not enumerate separate acceptance-test filenames; `web_scaffold.test.tsx` and `app_mounts.test.tsx` were added to contain its named Vitest acceptance tests.
- Nextest is limited to four test threads in `justfile` after the first full suite attempt hit transient database `Unavailable` errors at default concurrency; the capped run passed the full suite. This is test-infrastructure tuning only.
- TypeScript 7.0.2 was available, while the stack specification explicitly starts on TypeScript 6.0.3 and assigns evaluation of 7.x to a dedicated upgrade task. The implementation pins 6.0.3 and records that precedence resolution in Q-1 pending architect review.
- `package.json` pins Node 26.13.0 via `engines`; no separate `.nvmrc` was added.

### Not done / not verified

- `just setup` was not run; the existing `web/node_modules` was reused, with Node 26.13.0 and pnpm 12.16.1 selected from the temporary toolchain under `/tmp`.
- Browser-driven E2E and a release-mode build with missing `web/dist` were not run; they are outside this task's acceptance commands. No browser check is reported as passed.
- No checks were environment-limited.

## Questions

- Q-1 (resolved by implementer, pending architect review, 2026-10-30): The task context says to pin the latest stable TypeScript, while `spec/standards/open-source-stack.md` specifies TypeScript 6.0.3 as the starting version and defers TypeScript 7.x evaluation to a dedicated upgrade task. Implemented the higher-priority stack specification and pinned 6.0.3. Recommendation: retain 6.0.3 until the planned compatibility evaluation decides otherwise.
