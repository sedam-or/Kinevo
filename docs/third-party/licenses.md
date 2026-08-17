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
| Vue 3 + TypeScript | pin at integration | MIT | https://github.com/vuejs/core | No | No | No | Frontend framework |
| Inertia.js | pin at integration | MIT | https://github.com/inertiajs/inertia | No | No | No | Server-driven frontend bridge |
| Vite | `^8.0` (server/package.json) | MIT | https://github.com/vitejs/vite | No | No | No | Build tool |
| Pinia | pin at integration | MIT | https://github.com/vuejs/pinia | No | No | No | Vue state store |
| Tailwind CSS | `^4.0` (server/package.json) | MIT | https://github.com/tailwindlabs/tailwindcss | No | No | No | Styling |
| laravel-vite-plugin | `^3.1` (server/package.json) | MIT | https://github.com/laravel/vite-plugin | No | No | No | Vite + Laravel bridge |
| PostgreSQL | 17 (docker image) | PostgreSQL License | https://git.postgresql.org/gitweb/?p=postgresql.git | No | No | No | Canonical database |

## Editor / canvas engines (integration adapters — NOT yet integrated)

| Component | Version/Commit | License | Source | Modified? | Vendored? | Notice Required? | Notes |
|---|---|---|---|---|---|---|---|
| Excalidraw | pin at integration | MIT | https://github.com/excalidraw/excalidraw | TBD | TBD | Yes | Canvas engine, behind bounded adapter |
| Tiptap | pin at integration | MIT | https://github.com/ueberdosis/tiptap | TBD | No | Check exact package | Editor engine, behind LIFESYNC adapter |
| ProseMirror dependencies | lockfile | inspect each | https://github.com/ProseMirror | No | No | Check | Tiptap dependency chain |
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

### Verification note
`composer.lock` is the authoritative source for exact runtime/dev PHP versions. The
ProseMirror/Tiptap/Excalidraw chain is not yet installed and MUST be pinned and
re-reviewed at integration time.