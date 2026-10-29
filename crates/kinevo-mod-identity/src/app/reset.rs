//! Password reset issuance, public request, and confirmation use cases.

use std::sync::Arc;

use async_trait::async_trait;
use kinevo_db::{
    Db, SystemTx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    outbox::{self, SubscriberRegistry},
};
use kinevo_kernel::{
    crypto::{decode_token, encode_token, hash_token},
    errors::{AppError, Details, ErrorCode},
    event::{EventEnvelope, EventKind, EventPayload},
    id::{AccountId, EventId, IdGen, TokenGen},
    policy::{PolicyDuration, registry::identity as policy},
    secret::Secret,
    time::{Clock, Instant},
    validate::{Collector, net::Email},
};
use kinevo_ports::platform::{
    Delivery, InstanceState, MailMessage, MailTemplate, Mailer, PolicyReader,
};
use sqlx::Row;

use crate::{
    app::login::PasswordWork,
    domain::{
        password::{CommonPasswords, PasswordPolicy, PlainPassword},
        reset::ResetToken,
        session::RevocationReason,
    },
    infra::{resets, sessions},
};

/// Account metadata used by the reset request flow.
#[derive(Clone)]
pub struct ResetAccount {
    pub id: AccountId,
    pub locale: kinevo_kernel::validate::tz::Locale,
    pub active: bool,
}

/// Injectable email lookup used to keep reset-request work measurable in tests.
#[async_trait]
pub trait ResetAccountLookup: Send + Sync {
    async fn find_by_email(&self, email: &Email) -> Result<Option<ResetAccount>, AppError>;
}

struct DatabaseResetLookup(Db);

#[async_trait]
impl ResetAccountLookup for DatabaseResetLookup {
    async fn find_by_email(&self, email: &Email) -> Result<Option<ResetAccount>, AppError> {
        let mut tx = SystemTx::begin(&self.0).await?;
        let row = sqlx::query("SELECT id, locale, status FROM public.accounts WHERE email=$1")
            .bind(email.as_str())
            .fetch_optional(tx.conn())
            .await
            .map_err(service_unavailable)?;
        tx.commit().await?;
        row.map(|row| {
            let locale = row
                .try_get::<String, _>("locale")
                .map_err(service_unavailable)?;
            let status = row
                .try_get::<String, _>("status")
                .map_err(service_unavailable)?;
            Ok(ResetAccount {
                id: AccountId::from_uuid(row.try_get("id").map_err(service_unavailable)?),
                locale: match locale.as_str() {
                    "en" => kinevo_kernel::validate::tz::Locale::En,
                    "id" => kinevo_kernel::validate::tz::Locale::Id,
                    _ => return Err(AppError::new(ErrorCode::ServiceUnavailable)),
                },
                active: status == "active",
            })
        })
        .transpose()
    }
}

/// A one-time link returned to the administrator or CLI.
pub struct IssuedLink {
    pub url: Secret<String>,
}

/// Coordinates reset issuance and confirmation while keeping token data redacted.
pub struct ResetService {
    db: Db,
    ids: Arc<dyn IdGen>,
    tokens: Arc<dyn TokenGen>,
    passwords: Arc<dyn PasswordWork>,
    policies: Arc<dyn PolicyReader>,
    instance: Arc<dyn InstanceState>,
    mailer: Arc<dyn Mailer>,
    clock: Arc<dyn Clock>,
    subscribers: SubscriberRegistry,
    lookup: Arc<dyn ResetAccountLookup>,
}

impl ResetService {
    #[allow(
        clippy::too_many_arguments,
        reason = "the reset workflow receives explicit ports"
    )]
    #[must_use]
    pub fn new(
        db: Db,
        ids: Arc<dyn IdGen>,
        tokens: Arc<dyn TokenGen>,
        passwords: Arc<dyn PasswordWork>,
        policies: Arc<dyn PolicyReader>,
        instance: Arc<dyn InstanceState>,
        mailer: Arc<dyn Mailer>,
        clock: Arc<dyn Clock>,
        subscribers: SubscriberRegistry,
    ) -> Self {
        let lookup = Arc::new(DatabaseResetLookup(db.clone()));
        Self {
            db,
            ids,
            tokens,
            passwords,
            policies,
            instance,
            mailer,
            clock,
            subscribers,
            lookup,
        }
    }

    #[allow(
        clippy::too_many_arguments,
        reason = "the counting lookup is injected by acceptance tests"
    )]
    #[must_use]
    pub fn new_with_lookup(
        db: Db,
        ids: Arc<dyn IdGen>,
        tokens: Arc<dyn TokenGen>,
        passwords: Arc<dyn PasswordWork>,
        policies: Arc<dyn PolicyReader>,
        instance: Arc<dyn InstanceState>,
        mailer: Arc<dyn Mailer>,
        clock: Arc<dyn Clock>,
        subscribers: SubscriberRegistry,
        lookup: Arc<dyn ResetAccountLookup>,
    ) -> Self {
        Self {
            db,
            ids,
            tokens,
            passwords,
            policies,
            instance,
            mailer,
            clock,
            subscribers,
            lookup,
        }
    }

    /// Creates an administrator or CLI reset link; the optional actor is saved for support.
    pub async fn issue_for_account(
        &self,
        account: AccountId,
        created_by: Option<AccountId>,
    ) -> Result<IssuedLink, AppError> {
        let now = self.clock.now();
        let ttl = self.reset_ttl(account, now).await?;
        let instance = self.instance.get().await?;
        let base_url = instance
            .base_url
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        let mut tx = SystemTx::begin(&self.db).await?;
        let row = sqlx::query(
            "SELECT email::text AS email, locale, status FROM public.accounts WHERE id=$1",
        )
        .bind(*account.as_uuid())
        .fetch_optional(tx.conn())
        .await
        .map_err(service_unavailable)?
        .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
        let email = Email::parse(
            &row.try_get::<String, _>("email")
                .map_err(service_unavailable)?,
        )
        .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let locale = parse_locale(
            &row.try_get::<String, _>("locale")
                .map_err(service_unavailable)?,
        )?;
        let url = insert_reset(
            &mut tx,
            self.ids.as_ref(),
            self.tokens.as_ref(),
            account,
            created_by,
            base_url.as_str(),
            now,
            ttl,
        )
        .await?;
        tx.commit().await?;
        let message = MailMessage {
            to: email,
            template: MailTemplate::PasswordReset,
            locale,
            vars: vec![("reset_url", url.expose().clone())],
        };
        let _ = self.mailer.send(message).await;
        Ok(IssuedLink { url })
    }

    /// Resolves an email for the admin CLI, returning `not_found` for unknown addresses.
    pub async fn issue_for_email(&self, email: &Email) -> Result<IssuedLink, AppError> {
        let account = self
            .lookup
            .find_by_email(email)
            .await?
            .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
        self.issue_for_account(account.id, None).await
    }

    /// Public forgot-password request. Every outcome is intentionally opaque.
    pub async fn request(&self, email: Email) -> Result<(), AppError> {
        let found = self.lookup.find_by_email(&email).await?;
        self.passwords
            .verify_dummy(&Secret::new(String::new()))
            .await;
        let Some(account) = found.filter(|account| account.active) else {
            return Ok(());
        };
        let now = self.clock.now();
        let ttl = self.reset_ttl(account.id, now).await?;
        let instance = self.instance.get().await?;
        let Some(base_url) = instance.base_url else {
            return Ok(());
        };
        let mut tx = SystemTx::begin(&self.db).await?;
        let url = insert_reset(
            &mut tx,
            self.ids.as_ref(),
            self.tokens.as_ref(),
            account.id,
            None,
            base_url.as_str(),
            now,
            ttl,
        )
        .await?;
        let message = MailMessage {
            to: email,
            template: MailTemplate::PasswordReset,
            locale: account.locale,
            vars: vec![("reset_url", url.expose().clone())],
        };
        match self.mailer.send(message).await {
            Ok(Delivery::Sent) => tx.commit().await,
            Ok(Delivery::NotConfigured) | Err(_) => tx.rollback().await,
        }
    }

    /// Consumes one token, updates the password, revokes all sessions, audits, and emits an event.
    pub async fn confirm(
        &self,
        token: Secret<String>,
        new_password: Secret<String>,
    ) -> Result<(), AppError> {
        let now = self.clock.now();
        let minimum = self.password_minimum(now).await?;
        let password = PlainPassword::parse(
            new_password,
            &PasswordPolicy::new(minimum),
            &CommonPasswords::embedded(),
        );
        let mut errors = Collector::new();
        let password = errors.check("new_password", password);
        errors.finish()?;
        let password = password.ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let new_hash = self
            .passwords
            .hash(&Secret::new(password.expose().to_owned()))
            .await?;
        let raw = decode_token(token.expose())
            .map_err(|_| AppError::new(ErrorCode::ResetTokenInvalid))?;
        let token_hash = hash_token(&raw);
        let mut tx = SystemTx::begin(&self.db).await?;
        let reset = resets::lock_by_hash(&mut tx, &token_hash)
            .await?
            .ok_or_else(|| AppError::new(ErrorCode::ResetTokenInvalid))?;
        if reset.status == "deleting" {
            return Err(AppError::new(ErrorCode::ResetTokenInvalid));
        }
        resets::consume(&mut tx, reset.id, now).await?;
        let changed = sqlx::query(
            "UPDATE public.accounts SET password_hash=$2, version=version+1, updated_at=$3::timestamptz \
             WHERE id=$1 AND status <> 'deleting'",
        )
        .bind(*reset.account_id.as_uuid())
        .bind(new_hash)
        .bind(now.to_rfc3339())
        .execute(tx.conn()).await.map_err(service_unavailable)?.rows_affected();
        if changed != 1 {
            return Err(AppError::new(ErrorCode::ResetTokenInvalid));
        }
        sessions::revoke_all_for_account(
            &mut tx,
            reset.account_id,
            RevocationReason::PasswordChanged,
            now,
            None,
        )
        .await?;
        AuditWriter::new(Arc::clone(&self.ids))
            .record(
                &mut tx,
                AuditEntry {
                    account_id: Some(reset.account_id),
                    actor_id: Some(*reset.account_id.as_uuid()),
                    action: AuditAction::AuthPasswordChanged,
                    target_type: Some(AuditTarget::ACCOUNT),
                    target_id: Some(reset.account_id.to_string()),
                    details: Details::new(),
                    request_id: None,
                    occurred_at: now,
                },
            )
            .await?;
        outbox::emit(
            &mut tx,
            self.ids.as_ref(),
            now,
            EventEnvelope {
                id: EventId::from_uuid(self.ids.new_uuid_v7()),
                kind: EventKind::PasswordChanged,
                v: 1,
                account_id: Some(reset.account_id),
                occurred_at: now,
                payload: EventPayload::new().with_id("account_id", &reset.account_id),
            },
            &self.subscribers,
        )
        .await?;
        tx.commit().await
    }

    async fn reset_ttl(&self, account: AccountId, now: Instant) -> Result<i64, AppError> {
        self.policies
            .snapshot_values(Some(account), now)
            .await?
            .get::<PolicyDuration>(&policy::PASSWORD_RESET_TTL)
            .map(|ttl| ttl.0)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))
    }

    async fn password_minimum(&self, now: Instant) -> Result<usize, AppError> {
        let minimum = self
            .policies
            .snapshot_values(None, now)
            .await?
            .get(&policy::PASSWORD_MIN_LENGTH)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        usize::try_from(minimum).map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))
    }
}

#[allow(
    clippy::too_many_arguments,
    reason = "the reset row and one-time URL share explicit injected security inputs"
)]
async fn insert_reset(
    tx: &mut SystemTx,
    ids: &dyn IdGen,
    tokens: &dyn TokenGen,
    account: AccountId,
    created_by: Option<AccountId>,
    base_url: &str,
    now: Instant,
    ttl_seconds: i64,
) -> Result<Secret<String>, AppError> {
    let token = tokens.token_256();
    let encoded = encode_token(&token);
    let hash = hash_token(&token);
    let lifetime = ResetToken::issue(now, ttl_seconds)?;
    sqlx::query("UPDATE public.password_resets SET used_at=$2::timestamptz WHERE account_id=$1 AND used_at IS NULL")
        .bind(*account.as_uuid()).bind(now.to_rfc3339()).execute(tx.conn()).await.map_err(service_unavailable)?;
    sqlx::query(
        "INSERT INTO public.password_resets (id,account_id,token_hash,expires_at,created_by,created_at) \
         VALUES ($1,$2,$3,$4::timestamptz,$5,$6::timestamptz)",
    )
    .bind(ids.new_uuid_v7()).bind(*account.as_uuid()).bind(hash.as_slice())
    .bind(lifetime.expires_at.to_rfc3339()).bind(created_by.map(|id| *id.as_uuid())).bind(now.to_rfc3339())
    .execute(tx.conn()).await.map_err(service_unavailable)?;
    Ok(Secret::new(format!(
        "{base_url}/reset-password?token={encoded}"
    )))
}

fn parse_locale(value: &str) -> Result<kinevo_kernel::validate::tz::Locale, AppError> {
    match value {
        "en" => Ok(kinevo_kernel::validate::tz::Locale::En),
        "id" => Ok(kinevo_kernel::validate::tz::Locale::Id),
        _ => Err(AppError::new(ErrorCode::ServiceUnavailable)),
    }
}

fn service_unavailable(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
