---
id: T-030
title: Add the optional SMTP mailer adapter and the password-reset email
milestone: M1
kind: feature
status: todo
depends_on: [T-028, T-016]
requirements: [D-27, ID-FR-13, CA-12, ARCH-03, SEC-08]
branch: m1-t030-identity-smtp
budget_lines: 280
assignee: ""
---

# T-030: Add the optional SMTP mailer adapter and the password-reset email

## Goal

When an operator configures SMTP, password-reset requests send one plain-text email with the one-time link. Without SMTP the system behaves exactly as before (admin-issued links). The adapter is optional, TLS-verified and never logs addresses or links.

## Context

> **D-27**: Email is an optional SMTP adapter (lettre). Without SMTP, password reset is done by the instance admin through the CLI. Notifications in 1.0 are in-app only.
> **ID-FR-13**: With SMTP, an email with a one-time link (TTL `auth.password_reset.ttl`) is sent; the email contains **no account data other than the link**.
> **CA-12**: Adapters at the edge — SMTP is a trait in `ports` (`Mailer`, T-028) with real and fake implementations.
> **Configuration** (`11-operations.md` §2): `KINEVO_SMTP_URL` / `KINEVO_SMTP_PASSWORD_FILE`, optional, empty by default. Instance secret `smtp.password` (registered name, `admin.md` §2) may hold the password instead.
> **ARCH-03**: SMTP is an optional outbound integration. **SEC-08**: outbound calls verify TLS.
> **Stack**: `lettre` 0.11.23 (MIT), rustls, SMTP only (no sendmail, no file transport in production code).
> **Logging** (SEC-04): never log recipient, subject, body or link; log only `event="mail_sent"|"mail_failed"`, `error_code`.
> **Localisation**: the email text is chosen by the target account's locale; message keys `email.password_reset.subject` and `email.password_reset.body` live in `web/src/i18n/en.json` and `id.json` (the catalogues of T-016). The body has one placeholder `{link}` and a sentence stating the link's validity in minutes `{minutes}` (computed from `auth.password_reset.ttl`).

## Scope

**In:**
- `SmtpConfig::parse(url: &str, password: Option<Secret<String>>) -> Result<SmtpConfig, ConfigError>`: accepted schemes `smtp` (STARTTLS **required**) and `smtps` (implicit TLS); `user@host:port`; query parameter `from` (required, an address) and optional `reply_to`; plaintext without TLS is rejected with an error naming `KINEVO_SMTP_URL` and the rule, never the password.
- `SmtpMailer` implementing `Mailer` with `lettre::AsyncSmtpTransport<Tokio1Executor>` over rustls, connection timeout and send timeout constants (10 s and 30 s, technical), one message per call, `MailTemplate::PasswordReset` rendering from the catalogue with the account locale, plain text only, header `Auto-Submitted: auto-generated`.
- Password resolution: `SecretStore.get(Instance, smtp.password)` first; otherwise the file value from `KINEVO_SMTP_PASSWORD_FILE`; otherwise none (anonymous relay allowed only when the URL has no user).
- Errors map to `AppError` with code `service_unavailable` (internal log with `error_code` only); the reset **request** endpoint swallows them (always 202, T-028); admin-issued links are unaffected.
- Catalogue keys `email.password_reset.subject` and `.body` in both languages (Indonesian wording noted for maintainer review).
- `NoMailer` remains the default when `KINEVO_SMTP_URL` is empty (wiring in T-039).
- A `FakeSmtpServer` in tests (a tiny in-process TCP listener speaking enough SMTP to capture the message) for the adapter test; real TLS handshake is covered by a unit test of the lettre transport builder parameters only (certificate validation stays on; there is no `danger_accept_invalid_certs`).

**Out:**
- Bounce handling, templates for other emails, HTML mail, attachments.
- SMTP settings UI/API (the secret endpoint exists in T-032).
- The wiring and config loading: T-039.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/src/infra/mail.rs` | create | `SmtpConfig`, `SmtpMailer`, rendering |
| `crates/kinevo-mod-identity/src/lib.rs` | modify | export `smtp_mailer(cfg, secrets, catalog) -> Arc<dyn Mailer>` |
| `crates/kinevo-mod-identity/Cargo.toml` | modify | `lettre` (default features off; `tokio1-rustls`, `smtp-transport`, `builder`), `url` |
| `web/src/i18n/en.json`, `web/src/i18n/id.json` | modify | two keys |
| `crates/kinevo-mod-identity/tests/mail.rs` | create | |
| `spec/standards/open-source-stack.md` | modify | confirm `lettre` features |

## Interfaces

```rust
pub struct SmtpConfig { pub host: String, pub port: u16, pub tls: TlsMode /* StartTls | Implicit */, pub user: Option<String>, pub from: Mailbox, pub reply_to: Option<Mailbox> }
pub fn smtp_mailer(cfg: SmtpConfig, password: Option<Secret<String>>, catalog: Arc<Catalog>) -> Arc<dyn Mailer>;
```

## Business rules

1. The message has exactly: `From`, `To`, `Subject`, `Date` (injected clock), `Message-ID` (from `IdGen` + host), `Auto-Submitted`, a plain-text body in the account's locale containing the link and its validity in minutes — no display name, email, ids or IP.
2. `smtp://` without STARTTLS support on the server fails the send (no downgrade); there is no option to disable TLS verification.
3. Invalid or missing `from` is a configuration error at startup (T-039 surfaces it); a send failure never changes request outcomes.
4. At most one message per reset issuance.

## Security

- The password is a `Secret` and is never logged or included in errors; `SmtpConfig`'s `Debug` omits user and host details beyond the scheme.
- Header injection: recipient and subject come from validated types (`Email`, catalogue strings); the adapter rejects CR/LF in any header value.
- The link in the body is the only sensitive content (S3); the message is never stored.

## Implementation notes

- Use `lettre::message::Mailbox` parsing for `from`; reject display names containing control characters.
- Keep rendering pure (`render(template, locale, vars, catalog) -> (subject, body)`) so it is unit-tested without sockets.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** URLs `smtps://u@h:465/?from=a@b.test`, `smtp://h:587/?from=a@b.test`, `smtp://h/` (no from), `foo://h` **When** parsed **Then** accepted, accepted, error naming `KINEVO_SMTP_URL`, error | `mail::tests::d_27_url_parsing` |
| AC-2 | **Given** English and Indonesian locales **When** rendering **Then** the subject/body come from the catalogue, contain the link and the minutes, and nothing else about the account | `mail::tests::id_fr_13_body_has_only_the_link` |
| AC-3 | **Given** the fake SMTP server (STARTTLS-less) with `smtp://` **When** sending **Then** the send fails and nothing is delivered (no downgrade) | `mail::no_tls_downgrade` |
| AC-4 | **Given** the fake server accepting a plaintext-over-test-transport variant **When** the adapter sends in test mode **Then** the captured message has the required headers and `Auto-Submitted` | `mail::message_headers` |
| AC-5 | **Given** a recipient or subject containing CR/LF **When** building the message **Then** it is rejected | `mail::header_injection_rejected` |
| AC-6 | **Given** captured logs for a success and a failure **When** scanned for the address, link and password canaries **Then** none appear | `mail::sec_ac_45_mail_logs_are_clean` |
| AC-7 | **Given** the reset request flow (T-028) with `SmtpMailer` failing **When** `POST /auth/password-reset/request` is called **Then** 202 as before | `mail::request_endpoint_swallows_send_errors` |
| AC-8 | **Given** the catalogues **When** checked **Then** both new keys exist in `en` and `id` | `mail::tests::catalog_has_email_keys` |

## Tests

```
cargo nextest run -p kinevo-mod-identity
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Indonesian email wording listed in the Report for maintainer review.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
