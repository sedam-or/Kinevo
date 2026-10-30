---
id: T-052
title: Build the profile and security settings (password, two-step verification, sessions)
milestone: M1
kind: feature
status: review
depends_on: [T-050, T-027, T-026, T-045]
requirements: [ID-FR-02, ID-FR-07, ID-FR-08, ID-FR-14, SEC-AC-01, DS-A11Y-05, UXS-04, UXS-02]
branch: m1-t052-web-settings-profile-security
budget_lines: 390
assignee: "OpenAI Codex"
---

# T-052: Build the profile and security settings (password, two-step verification, sessions)

## Goal

A signed-in user can edit their profile, change their password, enrol or remove TOTP with recovery codes, and review and revoke sessions.

## Context

> **Endpoints** (`modules/identity.md` §2): `GET /account`, `PATCH /account` (If-Match; `version_conflict`, `timezone_invalid`), `POST /account/password` (`current_password_incorrect`, `password_too_short`; revokes other families), `POST /account/totp/enroll` (200 `{ otpauth_uri, recovery_codes[10] }`, shown once), `POST /account/totp/confirm {code}`, `DELETE /account/totp {password, code}` (`totp_required_by_policy`), `GET /account/sessions`, `DELETE /account/sessions/{id}`, `POST /auth/logout-all`.
> **Profile fields** (`validation.md`): `display_name` (short text), `locale` ∈ {`en`,`id`}, `timezone` (IANA name from the embedded tz list), `week_start`. **ID-FR-14**: timezone must be valid; locale ∈ {en, id}.
> **ID-FR-02**: password length between `auth.password.min_length` and 128, NFKC-normalised before hashing, never trimmed. **ID-FR-07**: changing password revokes all other refresh families. **ID-FR-08**: TOTP RFC 6238 (SHA-1, 6 digits, 30 s, ±1 step), optional unless `auth.totp.required`; recovery codes shown once.
> **UX keys**: `auth.totp.enrolled` (toast with Download codes), `auth.totp.recovery_codes` (modal), `auth.password.too_short`, `auth.password.too_common`, `state.session.revoked`, `state.rate_limited`. **UXS-04**: destructive actions use `alertdialog` naming the object, Cancel initially focused. **DS-A11Y-05**: `autocomplete="one-time-code"` on the code field; QR code is supplemented by the text secret (no cognitive test).
> **Concurrency**: profile edits send `If-Match` with the account `version`; on `version_conflict` the form keeps the user's text and shows the conflict card (FE-14).

## Scope

**In:**
- Settings layout with panels from `settings_panels` of `GET /modules` (profile, security); `/settings/profile`, `/settings/security`.
- Profile form (display name, language, time zone from `Intl.supportedValuesOf('timeZone')` with the server as authority, week start) with optimistic-lock handling.
- Password change form; TOTP enrol flow (QR rendered client-side from `otpauth_uri` as inline SVG with `qrcode-generator`, added to the stack map; the secret text is also shown), recovery-codes modal with copy/download, disable flow; sessions list with revoke and sign-out-everywhere.
- Tests with MSW; Playwright steps are added in T-057.

**Out:**
- Data export and account deletion: T-053. Admin screens: T-054/T-055.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/identity/settings/{ProfilePanel,SecurityPanel,PasswordForm,TotpPanel,SessionsPanel,RecoveryCodesDialog}.tsx` | create | panels |
| `web/src/features/identity/settings/schemas.ts` | create | zod |
| `web/src/features/shell/SettingsLayout.tsx` | create | panel nav |
| `web/src/features/identity/settings/*.test.tsx` | create | tests |
| `spec/standards/open-source-stack.md` | modify | add the QR library row |

## Interfaces

```ts
useAccount(): UseQueryResult<Account>;  usePatchAccount(): mutate({ patch, version });
useTotpEnroll(): mutate() → { otpauth_uri, recovery_codes };  useSessions(): list;  useRevokeSession(id)
```

## Business rules

1. Recovery codes are shown once in a modal that cannot be dismissed until the user confirms they saved them (checkbox) and are never kept in the query cache after the modal closes.
2. Removing TOTP requires password and a current code; if the policy requires TOTP the action explains `totp_required_by_policy`.
3. The current session is marked and cannot be revoked from the list (use Sign out).
4. Changing the language updates the app immediately after the save succeeds.

## Security

- `otpauth_uri` and recovery codes live only in component state and are cleared on close; they are excluded from React Query persistence.
- Password fields allow paste and use `new-password`/`current-password` autocomplete.
- Session list shows device labels and times only.

## Implementation notes

- Render the QR code as inline SVG with `qrcode-generator` (MIT, no dependencies). Verify the licence and pin the current version when adding it, and record both in the stack map (CQ-16). The secret is always also shown as grouped text.
- Reuse `ErrorCard`, `ConflictCard` and `useFormErrors`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a profile edit **When** saved **Then** `If-Match` carries the version and the new version replaces it | `settings::profile_save_uses_if_match` |
| AC-2 | **Given** a stale version **When** saving **Then** the conflict card appears and the typed text is kept | `settings::fe_14_profile_conflict_keeps_text` |
| AC-3 | **Given** a wrong current password **When** changing it **Then** the field error shows and no session is revoked | `settings::password_change_error_mapping` |
| AC-4 | **Given** TOTP enrolment **When** the code is confirmed **Then** the recovery-codes modal shows 10 codes once and `auth.totp.enrolled` toasts | `settings::id_fr_08_enrol_shows_codes_once` |
| AC-5 | **Given** the recovery modal **When** closed **Then** no code remains in the query cache or DOM | `settings::recovery_codes_not_retained` |
| AC-6 | **Given** the sessions list **When** a session is revoked via an alertdialog **Then** it disappears and Cancel was focused first | `settings::uxs_04_revoke_session_alertdialog` |
| AC-7 | **Given** `totp_required_by_policy` **When** disabling TOTP **Then** the explanation shows | `settings::totp_required_by_policy_message` |
| AC-8 | **Given** the panels **When** scanned by axe **Then** zero serious/critical violations | `settings.a11y::tst_11_settings_axe_clean` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
cargo nextest run -p kinevo-mod-identity -p kinevo-mod-admin --test descriptor
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes.
- [x] `just check` passes locally.
- [x] Task files were changed along with the minimal wiring recorded below; D-55 says the line budget is guidance.
- [x] Report below is filled in.

## Report

### Verification

- All eight named acceptance tests passed in `settings.test.tsx` (8/8); the panel accessibility scan passed with zero serious/critical axe violations.
- `PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH pnpm --dir web typecheck`: passed.
- `PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH pnpm --dir web lint`: passed; Biome printed six warnings and nine infos in pre-existing files, with no diagnostics in the T-052 components.
- `PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH pnpm --dir web test`: 33 files, 80 tests passed.
- `cargo nextest run -p kinevo-mod-identity -p kinevo-mod-admin --test descriptor`: 5 passed.
- `eval "$(scripts/dev-postgres.sh env)" && PATH=/tmp/kinevo-pnpm-shim:/usr/bin:$PATH just check`: passed. Rust nextest: 388 passed, 1 skipped; web: 80 passed. Formatting, clippy, typecheck, lint, API generation/build, cargo-deny, contrast, OpenAPI and generated API checks completed. `scripts/spec_tool.py check`: 0 findings in 60 files. `scripts/tasks_index.py --check`: 0 problems in 132 tasks.
- `git diff --check`: passed.

### Retries and warnings

- A full suite run without the exported shared `KINEVO_TEST_DATABASE_URL` failed in `d_37_android_receives_token_in_body` during disposable-database cleanup (326 passed, 1 failed, 1 skipped). The isolated test then reported PostgreSQL `Unavailable` without that variable; rerunning it with `scripts/dev-postgres.sh env` passed 1/1. The final full `just check` used that shared database and passed.
- One retry with the default login PATH stopped at web lint because Corepack resolved a missing `pnpm.cjs`. The passing run used the cached pnpm 12 shim and Node 26 from `/usr/bin`.
- Non-failing diagnostics: clippy warned that the existing `rand::thread_rng` lint entry is unreachable; Vite reported a 918.60 kB bundle and the existing `ModuleGate` static/dynamic import overlap; Biome reported six warnings and nine infos. `cargo deny check` exited successfully with its existing dependency warnings.

### Deviations

- Minimal wiring outside the task's explicit file table was needed in `web/src/app/router.tsx`, `web/src/api/client.ts`, and `web/src/i18n/{en,id}.json` to mount the routes, resolve the API base URL, and display localized settings. `web/package.json` and `web/pnpm-lock.yaml` pin the QR library listed in the stack map. D-56 allows necessary small wiring files; D-55 makes the line budget non-blocking.
- The generated OpenAPI type for `PATCH /api/v1/account` has no request-body type although the task and Rust handler require the profile patch fields. The request uses a narrow local type cast at that boundary, preserving the server contract. Resolved by implementer, pending architect review.

## Questions

- Q-1 (Resolved by implementer, pending architect review): `contracts/openapi.json` omits the request body for `PATCH /api/v1/account`, while the task and server handler require it. The implementation uses a narrow typed boundary cast and sends the documented `If-Match` header; the profile acceptance tests and full suite pass. Recommend aligning the OpenAPI contract in a follow-up task.

## Questions

_None yet._
