# ADR-0007 — Stateless access tokens signed with HS256; opaque rotating refresh tokens

- Status: **Accepted** (2026-10-25) — implements D-11.

## Context
Access tokens must be short-lived and verified on every request without a database round trip; refresh tokens must be revocable and reuse-detectable. The issuer and the verifier are the same process (a single binary).

## Decision
- Access token: compact JWS, algorithm **HS256**, key derived by HKDF from `KINEVO_SECRET_KEY` (label `kinevo/jwt/v1`), header `kid` for rotation, claims `sub, sid, jti, iat, exp, mfa`; verification allow-lists exactly this algorithm.
- Refresh token: 256-bit random opaque value stored only as SHA-256; rotation + reuse detection + RAM-only grace cache (`standards/auth.md` §3).

## Alternatives
| Option | For | Against |
|---|---|---|
| EdDSA (Ed25519) JWT | public verification possible | key distribution not needed in 1.0; more code/keys |
| PASETO v4 | safer defaults than JWT | smaller ecosystem; extra dependency for no 1.0 benefit |
| Server-side sessions only | simple revocation | DB hit per request; cookies for Android cross-site issues |

## Consequences
- A leaked `KINEVO_SECRET_KEY` allows forging access tokens ⇒ it is S3, rotated via `kid` with one-TTL overlap, and documented in runbooks.
- Access tokens cannot be revoked individually (TTL ≤ 10 min is the bound).
- **Revisit if** third-party services must verify tokens (switch to EdDSA with a JWKS endpoint).
