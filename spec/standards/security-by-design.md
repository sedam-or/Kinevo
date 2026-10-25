# Standard — Security by design (SEC)

Companion: `10-security-privacy.md` (threat model, privacy), `standards/auth.md`, `standards/rbac.md`.
Principle: **deny by default; the tenant comes only from authenticated context; secrets are unprintable; every control has an abuse-case test.**

## 1. Data classification

| Class | Definition | Examples | Handling |
|---|---|---|---|
| **S0** Public | Safe to publish | OpenAPI document, version, module list names, templates, UI strings | None |
| **S1** Operational | Not user content, not secret | job counts, module switches, policy values, aggregate counters, request ids, account UUIDs | May appear in logs/metrics/audit |
| **S2** User data | Personal or user-authored | email, display name, titles/descriptions, notes, canvases, landscape events, schedule, focus notes, AI hints and proposals, ICS files, IP address | Never in logs/metrics/audit details/error messages/AI prompts beyond AI-FR-01; stored only in tenant tables under RLS; included in export; deleted with the account |
| **S3** Secret | Grants access or decrypts | password hashes, access/refresh tokens, setup/invite/reset tokens, TOTP seeds, recovery codes, API keys, webhook secrets, master key, signing keys | Redacting wrapper types; hashed or AEAD-encrypted at rest; never returned after creation (except one-time display); never logged, even masked beyond last 4 |

## 2. Requirements

| ID | Requirement | Check |
|---|---|---|
| SEC-01 | A threat model (STRIDE per component, `10-security-privacy.md` §2) is maintained; every threat maps to a control here and an abuse-case test; new modules update it in the same PR. | review + `check-spec-refs` |
| SEC-02 | Each field and table is classified (S0–S3) in the domain model annotations; export and logging code are generated/checked from the classification (S2/S3 columns are denied in log structs by type). | architecture test |
| SEC-03 | **Secrets:** read from environment/files once at startup into types without `Debug`/`Display`/`Serialize`/`Clone` (CQ-10); at rest: passwords argon2id, tokens SHA-256 of 256-bit random values, secrets AEAD (XChaCha20-Poly1305) under HKDF-derived subkeys of `KINEVO_SECRET_KEY` with a `key_id` for rotation; comparisons are constant-time; secrets are zeroised on drop. | compile-fail tests; unit tests |
| SEC-04 | **Logging allow-list.** Log fields are limited to: `request_id`, `account_id` (UUID), `module`, `route` (template, not URL with ids when ids are sensitive), `method`, `status`, `latency_ms`, `error_code`, `event`, `job_kind`, `attempt`, `policy_key`, `constraint_name`, `count`. Request/response bodies, headers (except `user-agent` family truncated), query strings, emails, titles, notes, prompts, tokens, IPs (except in security events: truncated /24 or /48) are never logged. | `log-scan` test fixtures + review |
| SEC-05 | **Deny by default.** Unauthenticated requests reach only the public route list (`/livez`, `/readyz`, `/api/v1/openapi.json`, `/api/v1/instance` (limited fields), `/setup/*` pre-setup, `/auth/*` flows, `/billing/webhooks/*`, static assets). Every other route requires authentication and a permission check declared in the route's descriptor; a route without a declared permission fails the architecture test (CA-05d). The tenant is taken **only** from the authenticated context and never from path, query or body. | architecture test + SEC-AC-01 |
| SEC-06 | **Database isolation is verified at startup and in CI:** `kinevo_app` is not superuser, has no `BYPASSRLS`, owns no tenant table; every tenant table has RLS enabled **and forced** with the standard policy; `kinevo-db::verify_isolation()` aborts startup otherwise. Tests exercising isolation run as `kinevo_app`. The only extra policies allowed are `system_auth` on `accounts`, `refresh_token_families`, `refresh_tokens`, `password_resets` (scoped `TO kinevo_system`) the admin-context rules on `policy_versions` and `audit_log`, `instance_secrets_read` and `instance_secrets_write` on `secrets` (D-46), `system_insert` on `audit_log` (`FOR INSERT TO kinevo_system`) and `retention` on `audit_log` and `idempotency_keys` (`FOR DELETE TO kinevo_migrator`, used by the retention functions) (ADR-0011); `verify_isolation()` compares the exact policy set and fails on any other. Only `kinevo-db` may call `set_config` (CI grep). | startup check + SEC-AC-01/02 |
| SEC-07 | **Non-disclosure:** a resource owned by another account is indistinguishable from a missing one (404), including in sync results and error `details`. Registration/login/reset do not reveal whether an email exists, except registration conflict on open instances (accepted trade-off, rate-limited, documented). | abuse tests |
| SEC-08 | **Transport:** the app speaks HTTP behind a TLS-terminating proxy or with built-in rustls (documented profiles); cookies `Secure`; HSTS (`max-age=31536000; includeSubDomains`) when `KINEVO_PUBLIC_URL` is https; no mixed content; outbound calls verify TLS. | header test |
| SEC-09 | **CSRF/origin defence for cookie endpoints** (`/auth/refresh`, `/auth/logout`): `SameSite=Strict`, required `X-Kinevo-Client` header (not settable by cross-site forms), `Origin` must equal the public URL; all other endpoints use bearer tokens (not ambient credentials) and need no CSRF token. CORS: no origin allowed except the Android shell origin (D-37); `Access-Control-Allow-Credentials` never sent. | SEC-AC-05 |
| SEC-10 | **Audit:** every security-relevant action is appended to `audit_log` in the same transaction (actions listed in ADM-FR-05); details contain ids/enums/counts only; the application role cannot update/delete; retention per policy. | tests |
| SEC-11 | **Rate limiting & lockout** per `ratelimit.*` and `auth.lockout.*`, enforced in a tower layer before handlers; limiter state is in-process (single-node assumption, documented; multi-node requires the DB-backed limiter RFC). | tests |
| SEC-12 | **Telemetry:** none by default. Optional instance metrics (`/metrics`) contain S1 aggregates only. Product measures (PRD-M3) are computed from `progress_events` counts on the instance and never leave it. | review |
| SEC-13 | **Supply chain:** lockfiles committed; GitHub Actions pinned by commit SHA; `cargo-deny` (licences, bans, sources), `cargo-audit`/RustSec and `npm audit --omit=dev` in CI (fail on high/critical); Dependabot grouped weekly; release images built reproducibly where feasible, signed (cosign keyless), with SBOM (CycloneDX) and SLSA provenance attestations; secrets scanning (gitleaks) on every push. | CI |
| SEC-14 | **Setup token** (SET-04): 128-bit, printed once, hash stored, 3 wrong tries ⇒ lockout, invalid after completion. | SEC-AC-08 |
| SEC-15 | **SSRF defence** for any server-initiated request to a user-influenced URL (AI BYOK): scheme allow-list, resolve then connect to the validated IP, deny loopback/private/link-local/CGNAT/multicast/metadata ranges (IPv4 and IPv6), no redirects, response size/time caps; admin-configured instance URLs are trusted. | SEC-AC-30 |
| SEC-16 | **Backups:** the operations guide requires encrypting backups at rest and keeping `KINEVO_SECRET_KEY` separately (a backup without the key cannot decrypt secrets; losing the key loses secrets only, never user content). | docs review |
| SEC-17 | **Export & deletion:** ID-FR-11/12; deletion cascades through FKs; derived caches are invalidated; backups age out per operator policy (documented, not controlled by the app). | tests |
| SEC-18 | **Session security:** short access tokens; rotating refresh with reuse detection; revocation on password change, disable, deletion; session list with revoke; no concurrent-session cap in 1.0. | tests |
| SEC-19 | **Content security:** React text rendering only; CSP (FE-10); user files served `nosniff` + `sandbox`; SVG uploads rejected; ICS/Markdown/JSON exports escape per format; link `href` scheme allow-list. | SEC-AC-32/36/38 |
| SEC-20 | **Vulnerability handling:** `SECURITY.md` (Phase 5) defines private reporting via GitHub Security Advisories, acknowledgement ≤ 3 business days, fix target by severity, coordinated disclosure; patch releases are tagged and announced. | docs |
| SEC-21 | **Least privilege in deployment:** container runs as non-root user (UID ≥ 10000), read-only root filesystem except a tmpfs, no capabilities, no shell tools beyond what health checks need (D-14), egress only to the database and configured providers (documented for operators). | image scan + compose review |
| SEC-22 | **Time-of-check/time-of-use:** authorisation and the state check occur in the same transaction as the write (row locks where needed). | concurrency tests |
| SEC-23 | **Randomness:** all tokens/ids from the OS CSPRNG via `rand_core::OsRng` behind the `IdGen`/`TokenGen` ports; tests inject deterministic generators. | review |

## 3. Control per trigger

| Trigger | Required controls |
|---|---|
| A request reads/writes tenant data | `Tx::begin(account)`, RLS, permission check, 404 for foreign ids, no tenant from input (SEC-05/06/07) |
| A secret is created/read | SEC-03 types, AEAD or hash, last4 display only, audit `secret.changed` (name only) |
| A log/metric/trace/audit entry is written | SEC-04 field allow-list, S2/S3 typed out |
| An outbound call is made | timeouts, size caps, TLS verify, SSRF checks for user URLs, no call inside a DB transaction (CA-08), no S2 in the payload beyond the documented minimum |
| A document/file is parsed | size/depth/time caps (VAL-08/09), schema validation, re-serialise |
| State changes | optimistic version check, audit when security-relevant, outbox event with ids only |
| Authentication events | lockout, constant-work verification, audit, rate limit, no oracle (ID-FR-03) |
| User content is rendered | text nodes only, CSP, link scheme allow-list |
| A dependency is added | stack-map entry, licence/audit checks (CQ-16, SEC-13) |
| Config/secret changes at runtime | admin permission, audit, no echo |
| Account deletion/export | ID-FR-11/12, SEC-17 |

## 4. Abuse-case registry (each has a named automated test; owner module in parentheses)

| ID | Case | Module spec |
|---|---|---|
| SEC-AC-01 | Cross-tenant read via a query that forgets the account filter (executed as `kinevo_app`) ⇒ only own rows | `06-domain-model.md` §2 |
| SEC-AC-01b | Token present in any browser storage after login ⇒ fails the build | `08-frontend.md` FE-02 |
| SEC-AC-02 | The application role cannot bypass RLS or alter policies/tables | `06-domain-model.md` §2 |
| SEC-AC-03…07 | Credential stuffing, refresh replay, CSRF, closed registration, TOTP replay | `modules/identity.md` |
| SEC-AC-08…11 | Setup brute force, role escalation to admin endpoints, admin sees content, secret leakage | `modules/admin.md` |
| SEC-AC-12 | Foreign workspace id in create calls | `modules/workspaces.md` |
| SEC-AC-13…15 | LIKE injection, mass assignment, foreign task ids | `modules/tasks.md` |
| SEC-AC-16, 17 | ICS bomb; ICS property injection through export | `modules/landscape.md` |
| SEC-AC-18…20 | Forged draft, foreign input, sync race | `modules/scheduler.md` |
| SEC-AC-21…23 | Foreign task start, future `ended_at`, replayed complete | `modules/execution.md` |
| SEC-AC-24 | Foreign workspace id in progress queries | `modules/progress.md` |
| SEC-AC-25…29 | Operation-id reuse, cross-account op ids, giant batch, foreign entity, replay storm | `modules/sync.md` |
| SEC-AC-30…34 | SSRF via BYOK URL, output bomb, XSS in AI output, foreign proposal, key leakage | `modules/ai.md` |
| SEC-AC-35…37 | FTS injection, stored XSS in notes, foreign note | `modules/knowledge.md` |
| SEC-AC-38…40 | SVG-as-PNG, oversized scene, foreign file | `modules/canvas.md` |
| SEC-AC-41…44 | Forged/replayed webhook, plan tampering, foreign subscription | `modules/billing.md` |
| SEC-AC-45 | Log scan: run the whole E2E suite and grep logs for the fixture secrets/titles/emails ⇒ none | `12-testing.md` |
| SEC-AC-46 | Backup/restore drill without `KINEVO_SECRET_KEY` ⇒ content restores, secrets are unreadable, app starts with secrets marked unset | `11-operations.md` |
| SEC-AC-47 | Container escape hygiene: image runs as non-root with read-only root | `11-operations.md` |
| SEC-AC-48 | Dependency with a known critical advisory ⇒ CI fails | `12-testing.md` |
