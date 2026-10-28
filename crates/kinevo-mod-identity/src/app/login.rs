use std::{net::IpAddr, sync::Arc, time::Duration};

use async_trait::async_trait;
use kinevo_db::{
    Db, SystemTx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    outbox::{self, SubscriberRegistry},
};
use kinevo_http::token::{Claims, TokenCodec};
use kinevo_kernel::{
    crypto::hash_token,
    errors::{AppError, DetailValue, Details, ErrorCode},
    event::{EventEnvelope, EventKind, EventPayload},
    id::{AccountId, EventId, IdGen, TokenGen},
    policy::{PolicyDuration, registry::identity as policy},
    secret::Secret,
    time::{Clock, Instant},
    validate::net::Email,
};
use kinevo_ports::platform::PolicyReader;
use moka::{Expiry, future::Cache};
use tokio::sync::Mutex;

use crate::{
    app::mfa::{MfaInput, MfaResult, MfaVerifier},
    domain::{
        device,
        lockout::{LockoutPolicy, LockoutState, is_locked, on_failure},
        session::SessionPolicy,
    },
    infra::{
        accounts,
        hasher::{Hasher, VerifyOutcome},
        sessions,
    },
};

const UNKNOWN_EMAIL_CAPACITY: u64 = 100_000;

/// Request address passed by the HTTP adapter.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct ClientAddr(IpAddr);

impl ClientAddr {
    #[must_use]
    pub const fn new(address: IpAddr) -> Self {
        Self(address)
    }
}

/// Password operations separated from the use case for constant-work testing.
#[async_trait]
pub trait PasswordWork: Send + Sync {
    async fn verify(&self, candidate: &Secret<String>, stored_hash: &str) -> VerifyOutcome;
    async fn verify_dummy(&self, candidate: &Secret<String>);
    async fn hash(&self, candidate: &Secret<String>) -> Result<String, AppError>;
}

/// Production adapter around the bounded Argon2id hasher.
pub struct HasherPasswordWork {
    hasher: Arc<Hasher>,
}

impl HasherPasswordWork {
    #[must_use]
    pub fn new(hasher: Arc<Hasher>) -> Self {
        Self { hasher }
    }
}

#[async_trait]
impl PasswordWork for HasherPasswordWork {
    async fn verify(&self, candidate: &Secret<String>, stored_hash: &str) -> VerifyOutcome {
        self.hasher.verify_encoded(candidate, stored_hash).await
    }

    async fn verify_dummy(&self, candidate: &Secret<String>) {
        self.hasher.verify_dummy(candidate).await;
    }

    async fn hash(&self, candidate: &Secret<String>) -> Result<String, AppError> {
        self.hasher
            .rehash_candidate(candidate)
            .await
            .map(|hash| hash.as_str().to_owned())
    }
}

/// Normalized authentication policy snapshot.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct LoginPolicy {
    pub password_min_length: u32,
    pub lockout: LockoutPolicy,
    pub totp_required: bool,
    pub session: SessionPolicy,
}

/// Validated credentials and optional second factor.
pub struct LoginRequest {
    pub email: String,
    pub password: Secret<String>,
    pub totp_code: Option<String>,
    pub recovery_code: Option<Secret<String>>,
}

/// The safe account fields returned by login and `GET /account`.
#[derive(Clone, Debug, Eq, PartialEq, serde::Serialize, utoipa::ToSchema)]
pub struct AccountProfile {
    pub id: String,
    pub email: String,
    pub display_name: String,
    pub locale: String,
    pub timezone: String,
    pub week_start: i16,
    pub totp_enabled: bool,
    pub is_instance_admin: bool,
    pub version: i64,
}

/// Tokens and account profile created by a successful login.
pub struct LoginOutcome {
    pub profile: AccountProfile,
    pub access_token: String,
    pub expires_in: u32,
    pub refresh_token: Secret<String>,
    pub refresh_cookie_max_age: u32,
    pub mfa_enrol_required: bool,
}

impl std::fmt::Debug for LoginOutcome {
    fn fmt(&self, formatter: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        formatter
            .debug_struct("LoginOutcome")
            .field("profile", &self.profile)
            .field("access_token", &"<redacted>")
            .field("expires_in", &self.expires_in)
            .field("refresh_token", &self.refresh_token)
            .field("refresh_cookie_max_age", &self.refresh_cookie_max_age)
            .field("mfa_enrol_required", &self.mfa_enrol_required)
            .finish()
    }
}

#[derive(Clone)]
struct UnknownAttempt {
    count: u32,
    expires_at: Instant,
    remaining_ttl: Duration,
}

#[derive(Clone, Copy)]
struct UnknownExpiry;

impl Expiry<[u8; 32], UnknownAttempt> for UnknownExpiry {
    fn expire_after_create(
        &self,
        _key: &[u8; 32],
        value: &UnknownAttempt,
        _created_at: std::time::Instant,
    ) -> Option<Duration> {
        Some(value.remaining_ttl)
    }

    fn expire_after_update(
        &self,
        _key: &[u8; 32],
        value: &UnknownAttempt,
        _updated_at: std::time::Instant,
        _duration_until_expiry: Option<Duration>,
    ) -> Option<Duration> {
        Some(value.remaining_ttl)
    }
}

struct UnknownEmailTracker {
    attempts: Cache<[u8; 32], UnknownAttempt>,
    updates: Mutex<()>,
}

impl UnknownEmailTracker {
    fn new() -> Self {
        Self {
            attempts: Cache::builder()
                .max_capacity(UNKNOWN_EMAIL_CAPACITY)
                .expire_after(UnknownExpiry)
                .build(),
            updates: Mutex::new(()),
        }
    }

    async fn record(&self, email: &Email, now: Instant, policy: LockoutPolicy) -> Option<u32> {
        let key = hash_token(email.as_str().as_bytes());
        let _guard = self.updates.lock().await;
        let current = self
            .attempts
            .get(&key)
            .await
            .filter(|attempt| now < attempt.expires_at);
        let (count, expires_at) = match current {
            Some(attempt) => (attempt.count.saturating_add(1), attempt.expires_at),
            None => (
                1,
                now.checked_add_seconds(i64::from(policy.window_seconds))
                    .unwrap_or(now),
            ),
        };
        let remaining_millis = expires_at
            .unix_millis()
            .saturating_sub(now.unix_millis())
            .max(0);
        let remaining_ttl = Duration::from_millis(u64::try_from(remaining_millis).unwrap_or(0));
        self.attempts
            .insert(
                key,
                UnknownAttempt {
                    count,
                    expires_at,
                    remaining_ttl,
                },
            )
            .await;
        (count > policy.max_attempts)
            .then(|| u32::try_from(remaining_ttl.as_secs().max(1)).unwrap_or(u32::MAX))
    }
}

/// Coordinates password verification, lockout, MFA and first-session creation.
pub struct LoginService {
    db: Db,
    passwords: Arc<dyn PasswordWork>,
    mfa: Arc<dyn MfaVerifier>,
    policies: Arc<dyn PolicyReader>,
    clock: Arc<dyn Clock>,
    ids: Arc<dyn IdGen>,
    tokens: Arc<dyn TokenGen>,
    codec: Arc<TokenCodec>,
    unknown_emails: UnknownEmailTracker,
    audit: AuditWriter,
}

impl LoginService {
    #[allow(clippy::too_many_arguments)]
    #[must_use]
    pub fn new(
        db: Db,
        passwords: Arc<dyn PasswordWork>,
        mfa: Arc<dyn MfaVerifier>,
        policies: Arc<dyn PolicyReader>,
        clock: Arc<dyn Clock>,
        ids: Arc<dyn IdGen>,
        tokens: Arc<dyn TokenGen>,
        codec: Arc<TokenCodec>,
    ) -> Self {
        let audit = AuditWriter::new(Arc::clone(&ids));
        Self {
            db,
            passwords,
            mfa,
            policies,
            clock,
            ids,
            tokens,
            codec,
            unknown_emails: UnknownEmailTracker::new(),
            audit,
        }
    }

    /// Reads a consistent policy snapshot for the public HTTP endpoint.
    pub async fn resolved_policy(&self) -> Result<LoginPolicy, AppError> {
        let now = self.clock.now();
        let snapshot = self.policies.snapshot_values(None, now).await?;
        let required = |value| {
            AppError::new(ErrorCode::ServiceUnavailable)
                .with_detail("status", DetailValue::Str(value))
        };
        let password_min_length = snapshot
            .get(&policy::PASSWORD_MIN_LENGTH)
            .ok_or_else(|| required("policy_missing"))?;
        let lockout_max_attempts = snapshot
            .get(&policy::LOCKOUT_MAX_ATTEMPTS)
            .ok_or_else(|| required("policy_missing"))?;
        let lockout_window = snapshot
            .get(&policy::LOCKOUT_WINDOW)
            .ok_or_else(|| required("policy_missing"))?;
        let lockout_duration = snapshot
            .get(&policy::LOCKOUT_DURATION)
            .ok_or_else(|| required("policy_missing"))?;
        let access_ttl = snapshot
            .get(&policy::ACCESS_TOKEN_TTL)
            .ok_or_else(|| required("policy_missing"))?;
        let idle_ttl = snapshot
            .get(&policy::REFRESH_TOKEN_IDLE_TTL)
            .ok_or_else(|| required("policy_missing"))?;
        let absolute_ttl = snapshot
            .get(&policy::REFRESH_TOKEN_ABSOLUTE_TTL)
            .ok_or_else(|| required("policy_missing"))?;
        let grace = snapshot
            .get(&policy::REFRESH_GRACE)
            .ok_or_else(|| required("policy_missing"))?;
        let totp_required = snapshot
            .get(&policy::TOTP_REQUIRED)
            .ok_or_else(|| required("policy_missing"))?;
        Ok(LoginPolicy {
            password_min_length: u32::try_from(password_min_length)
                .map_err(|_| AppError::new(ErrorCode::InternalError))?,
            lockout: LockoutPolicy {
                max_attempts: u32::try_from(lockout_max_attempts)
                    .map_err(|_| AppError::new(ErrorCode::InternalError))?,
                window_seconds: policy_seconds(lockout_window)?,
                duration_seconds: policy_seconds(lockout_duration)?,
            },
            totp_required,
            session: SessionPolicy {
                access_ttl: policy_seconds(access_ttl)?,
                idle_ttl: policy_seconds(idle_ttl)?,
                absolute_ttl: policy_seconds(absolute_ttl)?,
                grace: policy_seconds(grace)?,
            },
        })
    }

    /// Authenticates with no user-agent metadata, for internal callers and tests.
    pub async fn login(
        &self,
        request: LoginRequest,
        client: super::refresh::Client,
        addr: ClientAddr,
        policy: LoginPolicy,
    ) -> Result<LoginOutcome, AppError> {
        self.login_with_user_agent(request, client, addr, policy, None)
            .await
    }

    /// Authenticates and retains only a fixed safe device label from the user-agent.
    pub async fn login_with_user_agent(
        &self,
        request: LoginRequest,
        client: super::refresh::Client,
        _addr: ClientAddr,
        policy: LoginPolicy,
        user_agent: Option<&str>,
    ) -> Result<LoginOutcome, AppError> {
        let email = Email::parse(&request.email).map_err(|_| validation_error("email"))?;
        let mut tx = SystemTx::begin(&self.db).await?;
        let account = accounts::find_by_email(&mut tx, &email).await?;
        tx.commit().await?;

        let Some(account) = account else {
            let now = self.clock.now();
            let retry_after = self
                .unknown_emails
                .record(&email, now, policy.lockout)
                .await;
            self.audit_unknown(now).await?;
            if let Some(retry_after) = retry_after {
                return Err(rate_limited(retry_after));
            }
            self.passwords.verify_dummy(&request.password).await;
            return Err(AppError::new(ErrorCode::InvalidCredentials));
        };

        let verification = self
            .passwords
            .verify(&request.password, &account.password_hash)
            .await;
        let now = self.clock.now();

        let current = self.locked_snapshot(account.id, now).await?;
        if let Some(retry_after) = current {
            return Err(account_locked(retry_after));
        }
        if verification == VerifyOutcome::Wrong {
            if let Some(retry_after) = self
                .record_failure(account.id, now, policy.lockout, "bad_password")
                .await?
            {
                return Err(account_locked(retry_after));
            }
            return Err(AppError::new(ErrorCode::InvalidCredentials));
        }

        let account = self.current_account(account.id).await?;
        if is_locked(account.lockout, now) {
            return Err(account_locked(retry_after(account.lockout, now)));
        }
        if account.status != kinevo_ports::platform::AccountStatus::Active {
            return Err(AppError::new(ErrorCode::AccountDisabled));
        }

        let mfa = if account.totp_enabled {
            let input = match (request.totp_code, request.recovery_code) {
                (Some(code), None) => Some(MfaInput::Totp(Secret::new(code))),
                (None, Some(code)) => Some(MfaInput::Recovery(code)),
                (None, None) => {
                    return Err(AppError::new(ErrorCode::MfaRequired).with_detail(
                        "methods",
                        DetailValue::StrList(vec!["totp".to_owned(), "recovery_code".to_owned()]),
                    ));
                }
                (Some(_), Some(_)) => return Err(validation_error("totp_code")),
            };
            let input = input.ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
            if self.mfa.verify(account.id, &input, now).await == MfaResult::Invalid {
                if let Some(retry_after) = self
                    .record_failure(account.id, now, policy.lockout, "bad_mfa")
                    .await?
                {
                    return Err(account_locked(retry_after));
                }
                return Err(AppError::new(ErrorCode::InvalidMfaCode));
            }
            true
        } else {
            false
        };

        let rehash = (verification == VerifyOutcome::OkNeedsRehash)
            .then(|| self.passwords.hash(&request.password));
        let password_hash = match rehash {
            Some(hash) => Some(hash.await?),
            None => None,
        };
        self.finish_login(
            account.id,
            policy,
            mfa,
            policy.totp_required && !account.totp_enabled,
            device::label(user_agent, client),
            password_hash.as_deref(),
        )
        .await
    }

    async fn current_account(&self, id: AccountId) -> Result<accounts::AccountRow, AppError> {
        let mut tx = SystemTx::begin(&self.db).await?;
        let account = accounts::lock_by_id(&mut tx, id).await?;
        tx.commit().await?;
        Ok(account)
    }

    async fn locked_snapshot(&self, id: AccountId, now: Instant) -> Result<Option<u32>, AppError> {
        let mut tx = SystemTx::begin(&self.db).await?;
        let account = accounts::lock_by_id(&mut tx, id).await?;
        if is_locked(account.lockout, now) {
            self.audit_failure(&mut tx, Some(id), "locked", now).await?;
            let retry_after = retry_after(account.lockout, now);
            tx.commit().await?;
            return Ok(Some(retry_after));
        }
        tx.commit().await?;
        Ok(None)
    }

    async fn record_failure(
        &self,
        id: AccountId,
        now: Instant,
        policy: LockoutPolicy,
        reason: &'static str,
    ) -> Result<Option<u32>, AppError> {
        let mut tx = SystemTx::begin(&self.db).await?;
        let account = accounts::lock_by_id(&mut tx, id).await?;
        if is_locked(account.lockout, now) {
            self.audit_failure(&mut tx, Some(id), "locked", now).await?;
            let retry_after = retry_after(account.lockout, now);
            tx.commit().await?;
            return Ok(Some(retry_after));
        }
        let next = on_failure(account.lockout, now, policy);
        accounts::record_failure(&mut tx, id, next, now).await?;
        self.audit_failure(&mut tx, Some(id), reason, now).await?;
        tx.commit().await?;
        Ok(None)
    }

    async fn audit_unknown(&self, now: Instant) -> Result<(), AppError> {
        let mut tx = SystemTx::begin(&self.db).await?;
        self.audit_failure(&mut tx, None, "unknown_email", now)
            .await?;
        tx.commit().await
    }

    async fn audit_failure(
        &self,
        tx: &mut SystemTx,
        account_id: Option<AccountId>,
        reason: &'static str,
        now: Instant,
    ) -> Result<(), AppError> {
        self.audit
            .record(
                tx,
                AuditEntry {
                    account_id,
                    actor_id: None,
                    action: AuditAction::AuthLoginFailed,
                    target_type: account_id.map(|_| AuditTarget::ACCOUNT),
                    target_id: account_id.map(|id| id.to_string()),
                    details: Details::new().with_detail("reason", DetailValue::Str(reason)),
                    request_id: None,
                    occurred_at: now,
                },
            )
            .await
    }

    #[allow(
        clippy::too_many_lines,
        reason = "the atomic login transaction keeps session, audit, and outbox writes together"
    )]
    async fn finish_login(
        &self,
        id: AccountId,
        policy: LoginPolicy,
        mfa: bool,
        mfa_enrol_required: bool,
        device_label: &'static str,
        password_hash: Option<&str>,
    ) -> Result<LoginOutcome, AppError> {
        let now = self.clock.now();
        let mut tx = SystemTx::begin(&self.db).await?;
        let account = accounts::lock_by_id(&mut tx, id).await?;
        if is_locked(account.lockout, now) {
            let retry_after = retry_after(account.lockout, now);
            self.audit_failure(&mut tx, Some(id), "locked", now).await?;
            tx.commit().await?;
            return Err(account_locked(retry_after));
        }
        if account.status != kinevo_ports::platform::AccountStatus::Active {
            tx.rollback().await?;
            return Err(AppError::new(ErrorCode::AccountDisabled));
        }
        if account.totp_enabled && !mfa {
            tx.rollback().await?;
            return Err(AppError::new(ErrorCode::MfaRequired).with_detail(
                "methods",
                DetailValue::StrList(vec!["totp".to_owned(), "recovery_code".to_owned()]),
            ));
        }
        accounts::record_success(&mut tx, id, now).await?;
        if let Some(password_hash) = password_hash {
            sqlx::query("UPDATE public.accounts SET password_hash=$2, updated_at=$3::timestamptz WHERE id=$1")
                .bind(*id.as_uuid())
                .bind(password_hash)
                .bind(now.to_rfc3339())
                .execute(tx.conn())
                .await
                .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        }
        let session = sessions::create_family_with_token(
            &mut tx,
            self.ids.as_ref(),
            self.tokens.as_ref(),
            now,
            id,
            Some(device_label),
            mfa,
            policy.session,
        )
        .await?;
        let access_expires_at = now
            .checked_add_seconds(i64::from(policy.session.access_ttl))
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let access_token = self.codec.issue(&Claims {
            sub: id,
            sid: session.family_id,
            jti: self.ids.new_uuid_v7(),
            iat: now,
            exp: access_expires_at,
            mfa,
        });
        if access_token.is_empty() {
            return Err(AppError::new(ErrorCode::InternalError));
        }
        self.audit
            .record(
                &mut tx,
                AuditEntry {
                    account_id: Some(id),
                    actor_id: None,
                    action: AuditAction::AuthLogin,
                    target_type: Some(AuditTarget::ACCOUNT),
                    target_id: Some(id.to_string()),
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
                kind: EventKind::AccountLoggedIn,
                v: 1,
                account_id: Some(id),
                occurred_at: now,
                payload: EventPayload::new().with_id("account_id", &id),
            },
            &SubscriberRegistry::default(),
        )
        .await?;
        tx.commit().await?;
        Ok(LoginOutcome {
            profile: AccountProfile {
                id: id.to_string(),
                email: account.email,
                display_name: account.display_name,
                locale: account.locale,
                timezone: account.timezone,
                week_start: account.week_start,
                totp_enabled: account.totp_enabled,
                is_instance_admin: account.is_instance_admin,
                version: account.version,
            },
            access_token,
            expires_in: policy.session.access_ttl,
            refresh_token: session.refresh_token,
            refresh_cookie_max_age: policy.session.absolute_ttl,
            mfa_enrol_required,
        })
    }
}

fn policy_seconds(value: PolicyDuration) -> Result<u32, AppError> {
    u32::try_from(value.0).map_err(|_| AppError::new(ErrorCode::InternalError))
}

fn retry_after(state: LockoutState, now: Instant) -> u32 {
    let Some(until) = state.locked_until else {
        return 1;
    };
    let millis = until.unix_millis().saturating_sub(now.unix_millis()).max(0);
    u32::try_from(millis.saturating_add(999).div_euclid(1_000).max(1)).unwrap_or(u32::MAX)
}

fn account_locked(seconds: u32) -> AppError {
    AppError::new(ErrorCode::AccountLocked)
        .with_detail("retry_after_seconds", DetailValue::Int(i64::from(seconds)))
}

fn rate_limited(seconds: u32) -> AppError {
    AppError::new(ErrorCode::RateLimited)
        .with_detail("retry_after_seconds", DetailValue::Int(i64::from(seconds)))
}

fn validation_error(path: &'static str) -> AppError {
    AppError::validation(vec![kinevo_kernel::errors::FieldError {
        path: path.to_owned(),
        code: kinevo_kernel::errors::FieldCode::EmailInvalid,
        details: Details::new(),
    }])
}
