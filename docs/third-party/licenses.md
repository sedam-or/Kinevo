# Third-Party License Ledger

### Purpose
Track every dependency or copied source component that creates redistribution obligations.

### Rules
- Never list a dependency as license-approved without checking the exact package/version.
- Do not copy source code from AGPL/GPL projects without explicit review.
- Preserve notices required by component licenses.
- Re-run license review when upgrading dependencies or vendoring source.

## Runtime / first-class dependencies

| Component | Version/Commit | License | Source | Modified? | Vendored? | Notice Required? | Notes |
|---|---|---|---|---|---|---|---|
| Laravel framework | `^13.17` (composer.lock) | MIT | https://github.com/laravel/framework | No | No | No | PHP backend framework |
| Laravel Sanctum | `^4.3` (composer.lock) | MIT | https://github.com/laravel/sanctum | No | No | No | Bearer-token API auth |
| Laravel Tinker | `^3.0` (composer.lock) | MIT | https://github.com/laravel/tinker | No | No | No | REPL for artisan |
| smalot/pdfparser | `^2.12` (composer.lock) | MIT | https://github.com/smalot/pdfparser | No | No | No | Pure-PHP PDF text extraction for KRS import (TASK-141) |
| Vue 3 + TypeScript | `vue@^3.5.41`, `typescript@^5.9.3` (package-lock) | MIT / Apache-2.0 | https://github.com/vuejs/core | No | No | No | Frontend framework (Vue MIT; TypeScript Apache-2.0) |
| Inertia.js | pin at integration | MIT | https://github.com/inertiajs/inertia | No | No | No | Server-driven frontend bridge (not yet installed) |
| Vite | `^8.0` (server/package.json) | MIT | https://github.com/vitejs/vite | No | No | No | Build tool |
| Pinia | `^4.0.3` (package-lock) | MIT | https://github.com/vuejs/pinia | No | No | No | Vue state store |
| Tailwind CSS | `^4.0` (server/package.json) | MIT | https://github.com/tailwindlabs/tailwindcss | No | No | No | Styling |
| laravel-vite-plugin | `^3.1` (server/package.json) | MIT | https://github.com/laravel/vite-plugin | No | No | No | Vite + Laravel bridge |
| PostgreSQL | 17 (docker image) | PostgreSQL License | https://git.postgresql.org/gitweb/?p=postgresql.git | No | No | No | Canonical database |
| Tiptap | `@tiptap/core@3.30.1` (package-lock) | MIT | https://github.com/ueberdosis/tiptap | No | No | Check chain | Editor engine, behind `EditorAdapter` boundary (TASK-031) |
| @tiptap/pm | `3.30.1` (package-lock) | MIT | https://github.com/ProseMirror | No | No | Check | Tiptap ProseMirror chain |
| @tiptap/starter-kit | `3.30.1` (package-lock) | MIT | https://github.com/ueberdosis/tiptap | No | No | No | Bounded extension baseline |
| @tiptap/vue-3 | `3.30.1` (package-lock) | MIT | https://github.com/ueberdosis/tiptap | No | No | No | Tiptap Vue bindings |
| Excalidraw | `@excalidraw/excalidraw@0.18.1` (package-lock) | MIT | https://github.com/excalidraw/excalidraw | No | No | Yes | Canvas engine, behind `CanvasAdapter` boundary (TASK-040 spike) |
| React | `react@19.2.8` (package-lock) | MIT | https://github.com/facebook/react | No | No | No | React island for Excalidraw (ADR-002/ADR-005) |
| react-dom | `react-dom@19.2.8` (package-lock) | MIT | https://github.com/facebook/react | No | No | No | React island DOM renderer |

## Editor / canvas engines (integration adapters — not yet integrated)

| Component | Version/Commit | License | Source | Modified? | Vendored? | Notice Required? | Notes |
|---|---|---|---|---|---|---|---|
| Ollama | deployment version | inspect distribution terms | https://github.com/ollama/ollama | No | No | Check | Optional runtime tool |

## Dev / CI dependencies (PHP)

| Component | Version/Commit | License | Source | Modified? | Vendored? | Notice Required? | Notes |
|---|---|---|---|---|---|---|---|
| PHPUnit | `^12.5` | BSD-3-Clause | https://github.com/sebastianbergmann/phpunit | No | No | No | Test runner |
| Pint | `^1.27` | MIT | https://github.com/laravel/pint | No | No | No | Code style |
| Larastan | `^3.10` | MIT | https://github.com/larastan/larastan | No | No | No | PHPStan Laravel integration |
| Mockery | `^1.6` | BSD-3-Clause | https://github.com/mockery/mockery | No | No | No | Mocking |
| Faker | `^1.23` | MIT | https://github.com/FakerPHP/Faker | No | No | No | Test fixtures |
| Collision | `^8.6` | MIT | https://github.com/nunomaduro/collision | No | No | No | Error rendering |

## Dev / CI dependencies (Node)

| Component | Version/Commit | License | Source | Modified? | Vendored? | Notice Required? | Notes |
|---|---|---|---|---|---|---|---|
| @vitejs/plugin-vue | `6.0.8` (package-lock) | MIT | https://github.com/vitejs/vite-plugin-vue | No | No | No | Vue SFC plugin for Vite |
| @vue/test-utils | `2.4.11` (package-lock) | MIT | https://github.com/vuejs/test-utils | No | No | No | Vue component testing |
| happy-dom | `20.11.2` (package-lock) | MIT | https://github.com/capricorn86/happy-dom | No | No | No | DOM environment for Vitest |
| vitest | `4.1.10` (package-lock) | MIT | https://github.com/vitest-dev/vitest | No | No | No | Frontend test runner |
| vue-tsc | `3.3.10` (package-lock) | MIT | https://github.com/vuejs/language-tools | No | No | No | Vue + TS typecheck |
| TypeScript | `^5.9.3` (package-lock) | Apache-2.0 | https://github.com/microsoft/TypeScript | No | No | No | Frontend type language |

### Verification note
`composer.lock` is the authoritative source for exact runtime/dev PHP versions.
`server/package-lock.json` is the authoritative source for exact Node dependency
versions. Tiptap and its ProseMirror chain were re-reviewed at integration time
(TASK-031); Excalidraw/React were re-reviewed at their integration (TASK-040
architecture spike). Ollama remains unintegrated and MUST be re-reviewed at its
integration.

## Adoption baseline (planned — NOT yet adopted)

> Source: `KINEVO_THIRD_PARTY_ADOPTION_INTEGRATION_AND_RETENTION_UX_SPEC.md`
> §3/§9 + `docs/third-party/adoption-matrix.md`. These are **planning baselines
> only**. Per the ledger rules above, none of these may be listed as
> license-approved for adoption until the exact package/version is checked at
> integration time. AGPL rows carry a no-copy rule: adapter calls over the
> network are the default safe boundary; copying source into Kinevo Core (MIT)
> requires a deliberate, documented licensing decision.

| Component | Version/Commit | License | Source | Modified? | Vendored? | Notice Required? | Notes |
|---|---|---|---|---|---|---|---|
| Pic Smaller | pending (record at adoption) | MIT (repo baseline) | official repo — record exact URL at adoption | No | Planned (engine) | Check | Browser-local image compression; engine embed, not its UI (planning mode EMBED/ADAPTER) |
| Uppy | pending (record at adoption) | MIT | https://github.com/transloadit/uppy | No | No | Check | Upload UX/transport behind Kinevo `AssetStorage` port (planning mode EMBED/ADAPTER) |
| Filament | pending (record at adoption) | MIT | https://github.com/filamentphp/filament | No | No | Check | Admin UI over Kinevo application services (planning mode EMBED) |
| Open SaaS | n/a — patterns only | MIT | https://github.com/wasp-lang/open-saas | No | No | No | HARVEST: OAuth/billing/onboarding/email UX patterns; Wasp/Prisma/Node runtime NOT introduced |
| Gotify | pending (record at adoption) | MIT | https://github.com/gotify/server | No | No | Check | Notification transport behind `NotificationProvider` port (planning mode ADAPTER + SERVICE) |
| Lago | pending (record at adoption) | AGPLv3 | https://github.com/getlago/lago | No | No | Check | Billing/metering infrastructure; AGPL — no source copying into Kinevo Core (planning mode ADAPTER + SERVICE) |
| OpenPanel | pending (record at adoption) | AGPL-3.0 | official repo — record exact URL at adoption | No | No | Check | Product analytics; AGPL — event taxonomy owned by Kinevo, no source copying (planning mode ADAPTER + SERVICE) |
| Langfuse | pending (record at adoption) | MIT core; `ee/` separately licensed | https://github.com/langfuse/langfuse | No | No | Check | AI observability; self-hosted setup MUST verify only core OSS features are enabled (planning mode ADAPTER + SERVICE) |
| GlitchTip | pending (record at adoption) | MIT (backend baseline) | https://github.com/glitchtip/glitchtip | No | No | Check | Error tracking behind Kinevo redaction layer (planning mode ADAPTER + SERVICE) |

### Verification note
Open SaaS has no runtime row because no runtime is adopted (HARVEST mode —
patterns only). Excalidraw, Tiptap, React and Ollama already have rows above.
The authoritative mode/contract/failure/exit detail for every baseline row
lives in `docs/third-party/adoption-matrix.md`.

## Vendored UI assets

| Component | Version/Commit | License | Source | Modified? | Vendored? | Notice Required? | Notes |
|---|---|---|---|---|---|---|---|
| Heroicons (SVG path subset) | heroicons v2.x upstream `master` paths, 2026-08-27 | MIT | https://github.com/tailwindlabs/heroicons | No | Yes (inline in `components/KIcon.vue`) | Yes — MIT notice below | Outline 24px paths embedded verbatim as a name→path map; no npm dependency added |

**MIT license notice (Heroicons):**

> Copyright (c) Tailwind Labs, Inc. ([@tailwindlabs](https://github.com/tailwindlabs))
>
> Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
>
> The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
>
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.