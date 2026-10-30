---
id: T-043
title: Scaffold the React + Vite web app and serve it from the binary
milestone: M1
kind: feature
status: in_progress
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

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
