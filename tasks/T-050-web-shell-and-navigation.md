---
id: T-050
title: Build the module-driven shell, navigation, Today placeholder and descriptor nav entries
milestone: M1
kind: feature
status: todo
depends_on: [T-049, T-034, T-046]
requirements: [MOD-01, MOD-09, FE-01, FE-11, FE-12, DS-A11Y-07, DS-A11Y-08, DS-A11Y-12, UXS-02]
branch: m1-t050-web-shell-and-navigation
budget_lines: 390
assignee: ""
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

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
