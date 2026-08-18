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