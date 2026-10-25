---
id: T-049
title: Build the AuthManager, the API client auth wiring and the login, register and reset pages
milestone: M1
kind: feature
status: todo
depends_on: [T-048, T-045, T-046, T-024, T-025, T-026]
requirements: [FE-02, FE-03, FE-04, ID-FR-03, ID-FR-05, ID-FR-06, SEC-AC-01, DS-A11Y-05, DS-A11Y-14, D-37]
branch: m1-t049-web-auth-client-and-login
budget_lines: 400
assignee: ""
---

# T-049: Build the AuthManager, the API client auth wiring and the login, register and reset pages

## Goal

A user can register (when open), sign in (with TOTP when enabled), reload without losing the session, and use a reset link; the access token lives only in memory and refresh is silent and single-flight.

## Context

> **FE-02** (`spec/08-frontend.md`): access token only in memory; at app start (and after reload) one `POST /auth/refresh` restores the session; concurrent 401s trigger one refresh (single-flight) and retry the failed request once; refresh failure clears state and goes to `/login?next=`. No token in `localStorage`, `sessionStorage`, IndexedDB or the URL.
> **FE-03**: every mutation sends `Idempotency-Key` (new UUID per user intent, reused on retry), `If-Match` for versioned resources; client header `X-Kinevo-Client: web` is sent on all requests (CSRF guard, `standards/auth.md`).
> **Endpoints** (`modules/identity.md` §2): `POST /auth/register` (201 `{account, access_token, expires_in}` + cookie; `registration_closed`, `invite_invalid`, `email_taken`, password errors), `POST /auth/login` (`invalid_credentials`, `mfa_required` with `details.methods`, `invalid_mfa_code`, `account_locked`), `POST /auth/refresh`, `POST /auth/logout`, `POST /auth/password-reset/request` (202 always), `POST /auth/password-reset/confirm`.
> **UX keys** (`ux-states.md` §3): `auth.login.invalid` (identical for unknown email and wrong password, ID-FR-03), `auth.login.locked`, `auth.login.mfa_prompt`, `auth.login.mfa_invalid`, `auth.register.closed`, `auth.register.email_taken`, `auth.password.too_short`, `auth.password.too_common`, `auth.reset.requested`, `auth.reset.invalid`, `state.session.expired`, `state.session.revoked`.
> **DS-A11Y-05**: paste allowed in password fields, `autocomplete` tokens (`username`, `current-password`, `new-password`, `one-time-code`), no cognitive tests. **DS-A11Y-14**: refresh is silent; unsaved text survives re-authentication.
> **ID-FR-05**: a refresh response with reuse detection revokes the family; the client then shows `state.session.revoked`.

## Scope

**In:**
- `auth/AuthManager.ts` (state machine: `unknown → refreshing → authenticated | anonymous`), single-flight refresh, `fetch` middleware adding `Authorization`, `X-Kinevo-Client`, `Idempotency-Key`, retry-once on 401 after refresh, session events (`signedOut`, `revoked`) via a tiny event emitter.
- `AuthProvider` + `useAuth()`; route guards for TanStack Router (`beforeLoad`) with `next` handling that accepts only same-origin relative paths.
- Pages `/login` (email, password, TOTP step and recovery code), `/register`, `/reset/$token` and a request-reset form on `/login`; `state.session.expired` modal and `state.session.revoked` page.
- MSW handlers for these endpoints generated from the OpenAPI examples, used by the tests.

**Out:**
- App shell and Today: T-050. Security settings (password change, TOTP enrol, sessions): T-052. Offline queue: M5.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/auth/AuthManager.ts, AuthProvider.tsx, guards.ts` | create | core |
| `web/src/api/client.ts` | modify | middleware |
| `web/src/features/identity/{LoginPage,RegisterPage,ResetPage,MfaStep}.tsx` | create | pages |
| `web/src/features/identity/schemas.ts` | create | zod forms |
| `web/src/test/handlers/auth.ts` | create | MSW |
| `web/src/auth/AuthManager.test.ts, web/src/features/identity/*.test.tsx` | create | unit/component tests |
| `web/tests/e2e/auth.spec.ts` | create | reload + storage inspection |

## Interfaces

```ts
class AuthManager { state: AuthState; accessToken(): string | undefined; async restore(): Promise<void>; async login(i): Promise<LoginResult>; async logout(): Promise<void>; onEvent(cb): Unsub }
// 401 handling: first failing request awaits one shared refresh promise, then retries once.
export function safeNext(raw: string | null): string;   // '/today' unless a same-origin relative path
```

## Business rules

1. Login errors show one identical message for unknown email and wrong password.
2. `mfa_required` switches to the code step without losing the typed email; the password is kept only in component state for the duration of the step.
3. `next` must start with `/` and not `//`; anything else becomes `/today`.
4. On `account_locked` the message uses the minutes from `details`; on `rate_limited` the countdown from `Retry-After`.
5. Registration page shows `auth.register.closed` when the server answers `registration_closed` (no form shown).

## Security

- The access token is never written to any storage, URL or log; a Playwright test inspects `localStorage`, `sessionStorage` and IndexedDB after login and reload (SEC-AC-01b).
- The refresh cookie is `HttpOnly` (server); the client never reads it.
- Passwords live in React form state only, are cleared on unmount and after submit, and never reach a query cache, log or error report.

## Implementation notes

- Use `crypto.randomUUID()` for idempotency keys (uuidv7 helper arrives in M5).
- The 401 retry applies only to requests with an `Authorization` header and never to `/auth/*`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a valid session cookie **When** the app starts **Then** exactly one refresh runs and the user is authenticated | `auth::fe_02_restore_single_refresh_on_start` |
| AC-2 | **Given** three parallel requests failing with 401 **When** handled **Then** one refresh runs and each request is retried once | `auth::fe_02_single_flight_refresh` |
| AC-3 | **Given** a failed refresh **When** it happens **Then** state clears and navigation goes to `/login?next=…` | `auth::fe_02_refresh_failure_redirects` |
| AC-4 | **Given** wrong email and wrong password **When** logging in **Then** the same message is shown | `auth::id_fr_03_identical_failure_message` |
| AC-5 | **Given** `mfa_required` **When** the code step completes **Then** login succeeds; an invalid code shows `auth.login.mfa_invalid` | `auth::mfa_step_flow` |
| AC-6 | **Given** `next=//evil.test` **When** login completes **Then** the user lands on `/today` | `auth::safe_next_only_relative` |
| AC-7 | **Given** login and reload in a browser **When** storage is inspected **Then** no token exists anywhere (SEC-AC-01b) | `auth.e2e::sec_ac_01b_no_token_in_storage` |
| AC-8 | **Given** a reuse-detected refresh **When** it fails with the revoked code **Then** `state.session.revoked` is shown | `auth::session_revoked_page` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
just check
pnpm --dir web exec playwright test tests/e2e/auth.spec.ts   # needs the running stack (T-056)
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
