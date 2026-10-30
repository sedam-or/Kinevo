---
id: T-050
title: Build the module-driven shell, navigation, Today placeholder and descriptor nav entries
milestone: M1
kind: feature
status: review
depends_on: [T-049, T-034, T-046]
requirements: [MOD-01, MOD-09, FE-01, FE-11, FE-12, DS-A11Y-07, DS-A11Y-08, DS-A11Y-12, UXS-02]
branch: m1-t050-web-shell-and-navigation
budget_lines: 390
assignee: "OpenAI Codex"
---

# T-050: Build the module-driven shell, navigation, Today placeholder and descriptor nav entries

## Goal

After login the shell renders navigation only from `GET /modules`, shows the disabled-module page for off modules, and lands on a Today route with the empty state; the identity and admin descriptors carry their nav items and settings panels.

## Context

> **Information architecture** (`spec/08-frontend.md` §3): four groups NOW · PLAN · THINK · REFLECT plus Settings and Admin. Desktop: left rail; mobile (< 768 px): bottom bar with Today, Plan, Think, Reflect and a More sheet (Settings, Admin). Groups with no enabled module are hidden. Routes in M1: `/today` (placeholder), `/settings/*`, `/admin/*`, public pages.
> **FE-01**: the shell fetches `GET /modules` after login and renders nav and routes from it; an unknown module key is ignored (MOD-09); a route of a disabled module renders `state.module.disabled`, not a blank page. **MOD-01**: the response carries per-module `nav[]` and `settings_panels[]` the caller may use.
> **Descriptor entries (decision of this task)**: nav items map to routes; the group is derived from the first path segment (`/settings`, `/admin`, `/plan`, `/think`, `/reflect`, `/today`). `identity` adds settings panels `profile`, `security`, `data` (label keys `settings.profile`, `settings.security`, `settings.data`) and the nav item `accounts` (`/admin/accounts`, permission `identity.admin.accounts.read`). `admin` adds nav items `modules`, `policies`, `audit`, `jobs`, `secrets` (`/admin/<key>`) with permissions `admin.modules.manage`, `admin.policies.instance.read`, `admin.audit.read`, `admin.jobs.read`, `admin.secrets.manage`.
> **FE-11 keyboard**: global chords `g t` (Today), `g p`, `g n`, `g r`, `n`, `?` (help), `Cmd/Ctrl+K`; disabled inside text inputs; can be turned off in settings. In M1 only `g t` and `?` act; the rest are registered only when their route exists. **DS-A11Y-07**: a "Help & shortcuts" entry sits in the same place on every page. **DS-A11Y-12**: landmarks, one `h1` per route, skip link first. **DS-A11Y-08**: no horizontal scroll at 320 px or 400 % zoom.
> **UX keys**: `today.empty.no_tasks`, `state.forbidden`, `state.not_found`, `state.module.disabled`, `state.server_error`.

## Scope

**In:**
- Rust: add `nav` and `settings_panels` to the `identity` and `admin` descriptors as above; update the descriptor tests (MOD-08: every nav permission and label key exists in registries/catalogue).
- `GET /modules` already assembles these (T-034); no endpoint change.
- Web: `app/Shell.tsx` (rail, bottom bar, More sheet, header with account menu, Help & shortcuts button, skip link), `useModules()` query, route registry mapping module key → lazy routes, `ModuleGate` rendering the disabled/forbidden/not-found states, keyboard chord provider, `/today` placeholder with `today.empty.no_tasks` and a primary action that is hidden until the `goals` module exists.
- `/`, `/setup` redirect rules: `setup_required` (409) from any API call routes to `/setup`; authenticated `/` goes to `/today`.

**Out:**
- Real Today content: M4. Settings and admin pages: T-052…T-055.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/src/lib.rs, crates/kinevo-mod-admin/src/lib.rs` | modify | nav and panels |
| `crates/kinevo-mod-{identity,admin}/tests/descriptor.rs` | modify | extend |
| `web/src/app/Shell.tsx, Nav.tsx, MoreSheet.tsx, ModuleGate.tsx, Chords.tsx` | create | shell |
| `web/src/features/shell/useModules.ts, routes.tsx` | create | module-driven routes |
| `web/src/features/today/TodayPage.tsx` | create | placeholder |
| `web/src/app/Shell.test.tsx, web/tests/e2e/shell.a11y.spec.ts` | create | tests |

## Interfaces

```ts
type ModuleItem = { key: string; kind: 'core'|'toggleable'; enabled: boolean; nav: NavItem[]; settings_panels: Panel[] };
function useModules(): UseQueryResult<{ items: ModuleItem[] }>;
<ModuleGate module="canvas" permission="…">{children}</ModuleGate>   // renders state.module.disabled / state.forbidden
```

## Business rules

1. The nav list is built from the response only; a test greps `src` for hard-coded route lists outside the route registry.
2. Unknown module keys are ignored; a module present but disabled hides its nav and its routes show `state.module.disabled`.
3. Admin entries appear only for callers holding the item's permission (the server already filters; the client does not re-implement permissions).
4. The More sheet contains everything not in the bottom bar and is reachable by keyboard.

## Security

- Client-side hiding is convenience; every protected call is enforced server-side (SEC-AC-09).
- `next` redirects use `safeNext` (T-049).

## Implementation notes

- Group order and labels: Now, Plan, Think, Reflect, then Settings, Admin. The labels are message keys `nav.group.*` and `settings.*` that `ux-states.md` does not list; add them to `en.json` and `id.json` and list them in `web/scripts/i18n.allow` with the reason "shell and settings labels" so the T-047 gate accepts them.
- Use `matchMedia` only for the bottom-bar switch, never for business logic.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a response without `canvas` **When** the shell renders **Then** no Canvas entry exists | `shell::mod_01_nav_only_from_response` |
| AC-2 | **Given** an unknown module key in the response **When** rendered **Then** it is ignored without error | `shell::mod_09_unknown_module_ignored` |
| AC-3 | **Given** a disabled module route **When** opened **Then** `state.module.disabled` shows with a way back | `shell::fe_01_disabled_route_shows_state` |
| AC-4 | **Given** an owner **When** `/modules` is returned **Then** Admin items are absent; an admin sees all six | `shell::admin_items_follow_permissions` |
| AC-5 | **Given** a descriptor **When** its nav permission or label key is unknown **Then** the Rust descriptor test fails | `descriptor::mod_08_nav_keys_exist` |
| AC-6 | **Given** a viewport of 360 px **When** rendered **Then** the bottom bar shows and nothing scrolls horizontally | `shell.a11y::ds_a11y_08_reflow_320` |
| AC-7 | **Given** the shell **When** scanned by axe and tabbed **Then** the skip link is first, one `h1`, landmarks present, Help & shortcuts in the same place on two routes | `shell.a11y::ds_a11y_12_landmarks_skip_link_help` |
| AC-8 | **Given** `g` then `t` outside an input **When** pressed **Then** navigation goes to Today; inside an input nothing happens | `shell::fe_11_chords_not_in_inputs` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
cargo nextest run -p kinevo-mod-identity -p kinevo-mod-admin --test descriptor
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

### Result

Implemented the module-driven shell, server-filtered navigation, mobile bottom bar and More sheet, account menu, Help & shortcuts dialog, Today empty state, module-gated placeholders, and identity/admin descriptor metadata. All eight acceptance tests pass on this environment; `just check` passes.

### Acceptance

| Test | Result |
|---|---|
| `shell::mod_01_nav_only_from_response` | PASS |
| `shell::mod_09_unknown_module_ignored` | PASS |
| `shell::fe_01_disabled_route_shows_state` | PASS |
| `shell::admin_items_follow_permissions` | PASS |
| `descriptor::mod_08_nav_keys_exist` | PASS (identity and admin descriptor suites) |
| `shell.a11y::ds_a11y_08_reflow_320` | PASS (Chromium, 320 px and 360 px) |
| `shell.a11y::ds_a11y_12_landmarks_skip_link_help` | PASS (Chromium and axe) |
| `shell::fe_11_chords_not_in_inputs` | PASS |

Additional checks: `shell::fe_01_forbidden_route_shows_state`, `shell::fe_12_feature_error_is_local`, `shell.e2e::fe_01_disabled_module_route_has_way_back`, and the updated auth redirect E2E all pass.

### Commands and counts

- `pnpm --dir web typecheck`: pass; Node 26.12.1 emitted the package engine warning (package requests 26.13.0).
- `pnpm --dir web lint`: pass; i18n checked 523 messages with no findings. Biome reported four existing `!important` warnings in `web/src/styles/base.css`.
- `pnpm --dir web test`: 31 files, 65 passed.
- `cargo nextest run -p kinevo-mod-identity -p kinevo-mod-admin --test descriptor`: 5 passed.
- `pnpm --dir web exec playwright test tests/e2e/auth.spec.ts tests/e2e/shell.a11y.spec.ts --project=chromium`: 4 passed.
- `eval "$(scripts/dev-postgres.sh env)" && PATH="$HOME/.cargo/bin:/usr/bin:$PATH" COREPACK_ENABLE_PROJECT_SPEC=0 just check`: exit 0; Rust 387 passed / 1 skipped, web 65 passed, cargo deny advisories/bans/licenses/sources passed, OpenAPI check passed, `spec_tool.py check` reported 0 findings in 60 files, contrast reported 0 failures, and `tasks_index.py --check` reported 0 problems in 132 tasks.
- `git diff --check`: pass. A first `just check` attempt caught formatting and then a Clippy test-index warning; `cargo fmt --all` and safe `.first()` access fixed both before the passing final gate.

### Environment-limited

- Firefox: `PATH=/usr/bin:$PATH COREPACK_ENABLE_PROJECT_SPEC=0 pnpm --dir web exec playwright test tests/e2e/shell.a11y.spec.ts --project=firefox --grep shell.a11y::ds_a11y_08_reflow_320` — Playwright error: `Executable doesn't exist at /home/kepolu/.cache/ms-playwright/firefox-1555/firefox/firefox`.
- WebKit: `PATH=/usr/bin:$PATH COREPACK_ENABLE_PROJECT_SPEC=0 pnpm --dir web exec playwright test tests/e2e/shell.a11y.spec.ts --project=webkit --grep shell.a11y::ds_a11y_08_reflow_320` — Playwright reports missing host dependencies `libicu74` and `libflite1`. No packages or browser binaries were installed.

`cargo clippy` and `cargo deny` emitted existing warnings (`rand::thread_rng` entry and unmatched BSD-2-Clause/MPL-2.0 allowances); neither gate failed. The workspace used the existing disposable PostgreSQL container; no image was built or pulled.

### Deviations and decisions

- **D-130, pending architect review:** preserve the API-filtered owner-visible Policies entry; identity settings panels use their existing registry permissions. The UI trusts the response and does not re-check permissions.
- **D-131, pending architect review:** register `/think/canvas` as a gated placeholder because AC-3 requires a direct disabled-module route but does not specify its path. Canvas stays out of navigation unless the API returns its item.
- Today is a fixed shell route for the M1 placeholder and bottom bar; all module navigation and settings panels are built from `GET /modules` and the route registry.
- Minimal routing/auth wiring changed `web/src/app/router.tsx` and `web/src/auth/AuthManager.ts`; `web/tests/e2e/auth.spec.ts` was updated to expect `/today` and mock the new `/modules` request. These files are outside the task Files table but are required to wire its route and setup redirects and keep the existing E2E contract accurate.
- D-130 and D-131 were added to `spec/decisions.md` and mapped in `spec/index.md`, per the D-56 implementer-resolution rule.
- The line budget was exceeded; D-55 makes it guidance, and generated files are not blockers.

### Files touched

`crates/kinevo-mod-identity/src/lib.rs`, `crates/kinevo-mod-identity/tests/descriptor.rs`, `crates/kinevo-mod-admin/src/lib.rs`, `crates/kinevo-mod-admin/tests/descriptor.rs`, `web/src/app/Shell.tsx`, `web/src/app/Shell.test.tsx`, `web/src/app/router.tsx`, `web/src/auth/AuthManager.ts`, `web/src/features/shell/Chords.tsx`, `web/src/features/shell/ModuleGate.tsx`, `web/src/features/shell/MoreSheet.tsx`, `web/src/features/shell/Nav.tsx`, `web/src/features/shell/routes.tsx`, `web/src/features/shell/useModules.ts`, `web/src/features/today/TodayPage.tsx`, `web/src/i18n/en.json`, `web/src/i18n/id.json`, `web/scripts/i18n.allow`, `web/tests/e2e/auth.spec.ts`, `web/tests/e2e/shell.a11y.spec.ts`, `spec/decisions.md`, `spec/index.md`, and `tasks/RUN_REPORT.md`.

## Questions

### Q-1 — Owner-visible Policies navigation

Task detail gives the Policies nav item permission `admin.policies.instance.read`, and the admin permission spec grants that key to `account_owner`, while AC-4 says Admin items are absent for an owner. Resolved by implementer, pending architect review (D-130): keep the server-filtered Policies item visible to owners, hide the five admin-only entries and the Accounts item, and show all six items to an instance admin. The identity settings panels use existing permissions: profile → `identity.account.read`, security → `identity.session.manage`, data → `identity.account.export`. Hiding Policies in the client or changing its declared permission was rejected because the client must trust the server response.

### Q-2 — Disabled Canvas route path

The task requires a route registry and a disabled-module route state, but it does not name a Canvas route. Resolved by implementer, pending architect review (D-131): register `/think/canvas` as a placeholder gated by the server's `canvas` module item. This lets a direct visit show `state.module.disabled` when Canvas is off and keeps Canvas out of navigation unless its item is returned by `GET /modules`. The alternative was to defer all Canvas routes until M8, which would leave AC-3 without a registered toggleable module route.
