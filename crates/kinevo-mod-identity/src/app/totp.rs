//! TOTP enrolment, account verification, and replay protection.

use std::{
    sync::{Mutex, PoisonError},
    time::Duration,
};

use async_trait::async_trait;
use kinevo_db::{Db, Tx};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::{AccountId, IdGen, TokenGen},
    policy::registry::identity::TOTP_REQUIRED,
    secret::Secret,
    time::{Clock, Instant},
};
use kinevo_ports::platform::{PolicyReader, SecretName, SecretScope, SecretStore};
use moka::sync::Cache;
use sqlx::Row;
use totp_rs::Secret as TotpSecret;
use zeroize::Zeroize;

use crate::{
    app::{
        login::PasswordWork,
        mfa::{MfaInput, MfaResult, MfaVerifier},
    },
    domain::totp::{self, RecoveryCode, generate_recovery_codes},
    infra::recovery,
};

const REPLAY_CACHE_CAPACITY: u64 = 10_000;
const REPLAY_CACHE_TTL_SECONDS: u64 = 90;

/// A one-time TOTP provisioning URI and recovery-code set.
pub struct Enrolment {
    pub otpauth_uri: Secret<String>,
    pub recovery_codes: Vec<RecoveryCode>,
}

/// Process-local cache that accepts an account and time-step pair only once.
pub struct ReplayCache {
    values: Cache<(AccountId, u64), Instant>,
    check_lock: Mutex<()>,
}

impl ReplayCache {
    /// Creates a bounded cache with the authentication standard's 90-second lifetime.
    #[must_use]
    pub fn new() -> Self {
        Self {
            values: Cache::builder()
                .max_capacity(REPLAY_CACHE_CAPACITY)
                .time_to_live(Duration::from_secs(REPLAY_CACHE_TTL_SECONDS))
                .build(),
            check_lock: Mutex::new(()),
        }
    }

    /// Records the first use of `(account, step)` and rejects another use in its lifetime.
    pub fn check_and_mark(&self, account: AccountId, step: u64, now: Instant) -> bool {
        let _guard = self
            .check_lock
            .lock()
            .unwrap_or_else(PoisonError::into_inner);
        let key = (account, step);
        if let Some(previous) = self.values.get(&key) {
            let age = now.unix_millis().saturating_sub(previous.unix_millis());
            if age
                < i64::try_from(REPLAY_CACHE_TTL_SECONDS.saturating_mul(1_000)).unwrap_or(i64::MAX)
            {
                return false;
            }
        }
        self.values.insert(key, now);
        true
    }
}

impl Default for ReplayCache {
    fn default() -> Self {
        Self::new()
    }
}

/// Owns the account's TOTP enrolment, confirmation and disable flows.
pub struct TotpService {
    db: Db,
    secrets: std::sync::Arc<dyn SecretStore>,
    tokens: std::sync::Arc<dyn TokenGen>,
    policies: std::sync::Arc<dyn PolicyReader>,
    passwords: std::sync::Arc<dyn PasswordWork>,
    ids: std::sync::Arc<dyn IdGen>,
    clock: std::sync::Arc<dyn Clock>,
    replay: std::sync::Arc<ReplayCache>,
}

impl TotpService {
    #[allow(
        clippy::too_many_arguments,
        reason = "these are the ports required by the TOTP use cases"
    )]
    #[must_use]
    pub fn new(
        db: Db,
        secrets: std::sync::Arc<dyn SecretStore>,
        tokens: std::sync::Arc<dyn TokenGen>,
        policies: std::sync::Arc<dyn PolicyReader>,
        passwords: std::sync::Arc<dyn PasswordWork>,
        ids: std::sync::Arc<dyn IdGen>,
        clock: std::sync::Arc<dyn Clock>,
        replay: std::sync::Arc<ReplayCache>,
    ) -> Self {
        Self {
            db,
            secrets,
            tokens,
            policies,
            passwords,
            ids,
            clock,
            replay,
        }
    }

    /// Starts or replaces an unconfirmed TOTP enrolment.
    pub async fn enroll(&self, account: AccountId) -> Result<Enrolment, AppError> {
        let mut tx = Tx::begin(&self.db, account).await?;
        let row = sqlx::query(
            "SELECT email::text AS email, totp_enabled FROM public.accounts WHERE id = $1",
        )
        .bind(*account.as_uuid())
        .fetch_optional(tx.conn())
        .await
        .map_err(database_error)?
        .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
        let email: String = row.try_get("email").map_err(database_error)?;
        let enabled: bool = row.try_get("totp_enabled").map_err(database_error)?;
        tx.commit().await?;
        if enabled {
            return Err(AppError::new(ErrorCode::TotpAlreadyEnabled));
        }

        let mut seed_bytes = [0_u8; 20];
        self.tokens.fill(&mut seed_bytes);
        let totp_secret = TotpSecret::from(seed_bytes.as_slice());
        seed_bytes.zeroize();
        let seed = Secret::new(totp_secret.to_base32());
        let uri = totp::otpauth_uri(seed.expose(), &email)
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let recovery_codes = generate_recovery_codes(self.tokens.as_ref());
        let seed_name = seed_name()?;
        self.secrets
            .put(
                SecretScope::Account(account),
                seed_name,
                Secret::new(seed.expose().clone()),
            )
            .await?;

        let mut tx = Tx::begin(&self.db, account).await?;
        let enabled: bool =
            sqlx::query_scalar("SELECT totp_enabled FROM public.accounts WHERE id = $1 FOR UPDATE")
                .bind(*account.as_uuid())
                .fetch_one(tx.conn())
                .await
                .map_err(database_error)?;
        if enabled {
            return Err(AppError::new(ErrorCode::TotpAlreadyEnabled));
        }
        recovery::replace(
            &mut tx,
            self.ids.as_ref(),
            account,
            &recovery_codes,
            self.clock.now(),
        )
        .await?;
        tx.commit().await?;

        Ok(Enrolment {
            otpauth_uri: Secret::new(uri),
            recovery_codes: recovery_codes.into(),
        })
    }

    /// Confirms the seed using the current or adjacent RFC 6238 time-step.
    pub async fn confirm(
        &self,
        account: AccountId,
        code: Secret<String>,
        now: Instant,
    ) -> Result<(), AppError> {
        let seed = self
            .secrets
            .get(SecretScope::Account(account), seed_name()?)
            .await?
            .ok_or_else(|| AppError::new(ErrorCode::TotpCodeInvalid))?;
        let valid = totp::accepts(seed.expose(), code.expose(), now, |step| {
            self.replay.check_and_mark(account, step, now)
        });
        if valid.is_none() {
            return Err(AppError::new(ErrorCode::TotpCodeInvalid));
        }

        let mut tx = Tx::begin(&self.db, account).await?;
        let changed = sqlx::query(
            "UPDATE public.accounts SET totp_enabled = true, version = version + 1 \
             WHERE id = $1 AND totp_enabled = false",
        )
        .bind(*account.as_uuid())
        .execute(tx.conn())
        .await
        .map_err(database_error)?
        .rows_affected();
        if changed == 0 {
            return Err(AppError::new(ErrorCode::TotpAlreadyEnabled));
        }
        tx.commit().await?;
        Ok(())
    }

    /// Disables TOTP only after password and second-factor verification.
    pub async fn disable(
        &self,
        account: AccountId,
        password: Secret<String>,
        code: Secret<String>,
    ) -> Result<(), AppError> {
        let mut tx = Tx::begin(&self.db, account).await?;
        let row =
            sqlx::query("SELECT password_hash, totp_enabled FROM public.accounts WHERE id = $1")
                .bind(*account.as_uuid())
                .fetch_optional(tx.conn())
                .await
                .map_err(database_error)?
                .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
        let password_hash: String = row.try_get("password_hash").map_err(database_error)?;
        let enabled: bool = row.try_get("totp_enabled").map_err(database_error)?;
        tx.commit().await?;
        if self.passwords.verify(&password, &password_hash).await
            == crate::infra::hasher::VerifyOutcome::Wrong
        {
            return Err(AppError::new(ErrorCode::CurrentPasswordIncorrect));
        }
        if !enabled {
            return Err(AppError::new(ErrorCode::TotpCodeInvalid));
        }

        let policy = self
            .policies
            .snapshot_values(Some(account), self.clock.now())
            .await?;
        let required = policy
            .get(&TOTP_REQUIRED)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        if required {
            return Err(AppError::new(ErrorCode::TotpRequiredByPolicy));
        }
        if !self
            .verify_factor(account, code.expose(), self.clock.now())
            .await?
        {
            return Err(AppError::new(ErrorCode::TotpCodeInvalid));
        }

        let mut tx = Tx::begin(&self.db, account).await?;
        let changed = sqlx::query(
            "UPDATE public.accounts SET totp_enabled = false, version = version + 1 \
             WHERE id = $1 AND totp_enabled = true",
        )
        .bind(*account.as_uuid())
        .execute(tx.conn())
        .await
        .map_err(database_error)?
        .rows_affected();
        if changed == 0 {
            return Err(AppError::new(ErrorCode::TotpCodeInvalid));
        }
        recovery::delete_all(&mut tx, account).await?;
        tx.commit().await?;
        self.secrets
            .delete(SecretScope::Account(account), seed_name()?)
            .await?;
        Ok(())
    }

    async fn verify_factor(
        &self,
        account: AccountId,
        code: &str,
        now: Instant,
    ) -> Result<bool, AppError> {
        if let Some(seed) = self
            .secrets
            .get(SecretScope::Account(account), seed_name()?)
            .await?
            && totp::accepts(seed.expose(), code, now, |step| {
                self.replay.check_and_mark(account, step, now)
            })
            .is_some()
        {
            return Ok(true);
        }
        let Some(code_hash) = totp::hash_input(code) else {
            return Ok(false);
        };
        let mut tx = Tx::begin(&self.db, account).await?;
        let valid = recovery::consume(&mut tx, account, &code_hash, now).await?;
        tx.commit().await?;
        Ok(valid)
    }
}

/// Implements login MFA verification using the enrolment seed and recovery table.
pub struct TotpMfaVerifier {
    db: Db,
    secrets: std::sync::Arc<dyn SecretStore>,
    replay: std::sync::Arc<ReplayCache>,
}

impl TotpMfaVerifier {
    /// Creates a verifier sharing the same process-local replay cache as account flows.
    #[must_use]
    pub fn new(
        db: Db,
        secrets: std::sync::Arc<dyn SecretStore>,
        replay: std::sync::Arc<ReplayCache>,
    ) -> Self {
        Self {
            db,
            secrets,
            replay,
        }
    }
}

#[async_trait]
impl MfaVerifier for TotpMfaVerifier {
    async fn verify(&self, account: AccountId, input: &MfaInput, now: Instant) -> MfaResult {
        match input {
            MfaInput::Totp(code) => {
                let Ok(Some(seed)) = self
                    .secrets
                    .get(
                        SecretScope::Account(account),
                        match seed_name() {
                            Ok(name) => name,
                            Err(_) => return MfaResult::Invalid,
                        },
                    )
                    .await
                else {
                    return MfaResult::Invalid;
                };
                if totp::accepts(seed.expose(), code.expose(), now, |step| {
                    self.replay.check_and_mark(account, step, now)
                })
                .is_some()
                {
                    MfaResult::Valid
                } else {
                    MfaResult::Invalid
                }
            }
            MfaInput::Recovery(code) => {
                let Some(code_hash) = totp::hash_input(code.expose()) else {
                    return MfaResult::Invalid;
                };
                let Ok(mut tx) = Tx::begin(&self.db, account).await else {
                    return MfaResult::Invalid;
                };
                let consumed = recovery::consume(&mut tx, account, &code_hash, now).await;
                match consumed {
                    Ok(true) => {
                        if tx.commit().await.is_ok() {
                            MfaResult::Valid
                        } else {
                            MfaResult::Invalid
                        }
                    }
                    Ok(false) => {
                        let _ = tx.commit().await;
                        MfaResult::Invalid
                    }
                    Err(_) => MfaResult::Invalid,
                }
            }
        }
    }
}

fn seed_name() -> Result<SecretName, AppError> {
    SecretName::parse("identity.totp_seed").map_err(|_| AppError::new(ErrorCode::InternalError))
}

fn database_error(_error: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
