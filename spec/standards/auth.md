# Standard — Authentication and sessions (AUTH)

Decisions: D-11, D-37. Module behaviour and endpoints: `modules/identity.md`. Security controls: `security-by-design.md`.
This file fixes **mechanics and parameters**; values that operators may change are policies (`04-policies.md`).

## 1. Credentials

| Item | Mechanism | Parameters |
|---|---|---|
| Password hash | argon2id (RFC 9106) via the `argon2` crate | memory 64 MiB, iterations 3, parallelism 1, 16-byte random salt, 32-byte output; stored as PHC string. Parameters are *technical constants* recorded in `open-source-stack.md`; a rehash-on-login upgrades old parameters. Hashing runs on a blocking thread pool with a concurrency cap of `min(4, cores)` to bound memory (DoS control). |
| Common-password check | embedded list of the 10 000 most common passwords (public-domain lists), case-insensitive compare after NFKC | rejects `password_too_common` |
| TOTP | RFC 6238 SHA-1, 6 digits, 30 s, ±1 step, 160-bit secret | seed stored as AEAD secret; replay cache `(account, step)` in memory for 90 s |
| Recovery codes | 10 codes, 10 chars from a 32-symbol unambiguous alphabet (≈ 50 bits each), stored as SHA-256 of the normalised code | single use |
| One-time tokens (invite, reset, setup) | 256-bit random, base64url (43 chars) | stored as SHA-256; shown once |

## 2. Tokens

| Token | Format | Lifetime | Storage (client) | Storage (server) |
|---|---|---|---|---|
| Access | Compact signed token (JWS, EdDSA/Ed25519 or HS256 per ADR-0007) with claims `sub, sid, jti, iat, exp, mfa` and header `kid` | `auth.access_token.ttl` (600 s) | JS memory only (web, Android) | none (stateless) — verified by signature; revocation = TTL + family revocation checks on refresh |
| Refresh | 256-bit random opaque value | idle `auth.refresh_token.idle_ttl`, absolute `auth.refresh_token.absolute_ttl` | web: cookie `__Secure-kinevo_rt` (HttpOnly, Secure, SameSite=Strict, Path=/api/v1/auth); Android: Keystore-backed encrypted storage (D-37) | `refresh_tokens.token_hash` (SHA-256) |

Signing keys derive from `KINEVO_SECRET_KEY` via HKDF-SHA256 with distinct `info` labels (`kinevo/jwt/v1`, `kinevo/cursor/v1`, `kinevo/secrets/v1`, `kinevo/csrf/v1`); a `kid` identifies the derivation version to allow rotation with an overlap window of one access-token TTL.

## 3. Refresh rotation and reuse detection (normative algorithm)

```
refresh(presented_token):
  h = sha256(presented_token)
  BEGIN (system context) ; row = SELECT ... FROM refresh_tokens WHERE token_hash = h FOR UPDATE
  if row is none                         → 401 unauthenticated                       (unknown token)
  family = SELECT ... FOR UPDATE row.family_id
  if family.revoked_at is set            → 401 unauthenticated
  if now ≥ family.absolute_expires_at or now ≥ row.idle_expires_at → revoke(family,'expired') ; 401
  if row.used_at is none:
        issue new token T2 (idle_expires_at = now + idle_ttl, capped by absolute) ; row.used_at = now ; row.replaced_by = T2
        family.last_used_at = now ; COMMIT → 200 (T2 + new access token)
  else (token already used):
        cached = grace_cache.get(h)                       -- in-process, RAM only, TTL = auth.refresh.grace
        if cached exists                                  → return the SAME token pair (idempotent retry after a lost response) ; COMMIT
        else  → revoke(family,'reuse_detected') ; audit 'auth.refresh_reuse' ; COMMIT ; 401 refresh_reuse_detected
```
**Grace cache.** After every successful rotation the server keeps `sha256(old token) → (new refresh token, new access token)` in a process-local cache for `auth.refresh.grace` seconds. It exists so that a client whose response was lost (flaky mobile network) or two tabs refreshing at once do not trigger a false reuse alarm. Plaintext tokens therefore live only in RAM for ≤ 10 s and never at rest (refresh tokens stay hash-only in the database). Consequences, accepted and documented: on a restart or in a multi-node deployment a replay inside the window is treated as reuse (family revoked, user signs in again); the cache is bounded (LRU 10 000 entries). The web client additionally serialises refresh across tabs with the Web Locks API (`navigator.locks`) so the cache is a safety net, not the primary mechanism.

## 4. Cookies and headers

| Cookie | Attributes |
|---|---|
| `__Secure-kinevo_rt` | `HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth; Max-Age=<remaining absolute lifetime>`. The `__Host-` prefix is **not** used because it forces `Path=/`, which would send the refresh token with every request; `__Secure-` allows the narrow path. No `Domain` attribute (host-only). |

Required headers on cookie endpoints: `X-Kinevo-Client: web` (or `android` for the body flow) and `Origin` equal to the public URL.

## 5. Android flow (D-37)

- `POST /auth/login` with `X-Kinevo-Client: android` ⇒ body includes `refresh_token`; **no** `Set-Cookie`.
- `POST /auth/refresh` with the same header and body `{ "refresh_token": "…" }`; rotation/reuse/grace identical (§3).
- The shell writes the refresh token through the `SecureStore` plugin immediately after each response; if the write fails the session is treated as expired (never kept in WebView storage).

## 6. Lockout and rate limits

Per-account failed-login counter and lock (ID-FR-04) plus per-address rate limit (`ratelimit.auth_per_minute`). Unknown-email attempts use a per-address limit only. Responses for locked accounts are identical regardless of password correctness.

## 7. Password reset and invites

- Reset tokens are single use, expire per `auth.password_reset.ttl`, and are invalidated by any password change; confirming a reset revokes all sessions.
- Invites expire per `auth.invite.ttl`; consumed atomically with registration (`SELECT … FOR UPDATE`).

## 8. Sessions UI (web/Android)

Settings → Security lists sessions (`device_label`, created, last used, current) with revoke; "Sign out everywhere" revokes all families.

## 9. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| AUTH-01 | The refresh algorithm in §3 is implemented exactly, including grace behaviour, with tests for: normal rotation; reuse after grace ⇒ family revoked; concurrent double-submit within grace ⇒ no revocation and both callers hold valid tokens; expired idle; expired absolute. | Table + concurrency tests with injected clock. |
| AUTH-02 | Access-token verification rejects `alg: none`, wrong `kid`, expired, not-yet-valid (skew ≤ 30 s), and tokens whose family is revoked **when checked on refresh** (access tokens are stateless; the residual window is `auth.access_token.ttl`). | Tests. |
| AUTH-03 | Argon2id parameters are checked at startup against minimums (m ≥ 19 MiB, t ≥ 2) and a self-benchmark logs a warning if one hash takes > 1 s on the host. | Test. |
| AUTH-04 | Password change requires the current password (or a valid reset token) and revokes other families. | Test. |
| AUTH-05 | No credential, token or hash appears in any log, error body, audit detail or metric. | SEC-AC-45. |
