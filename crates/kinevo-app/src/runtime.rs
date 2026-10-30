//! System implementations for the kernel's injected runtime ports.

use kinevo_kernel::{
    errors::{AppError, ErrorCode, FieldCode},
    id::AccountId,
    id::{IdGen, TokenGen},
    secret::Secret,
    time::{Clock, Instant},
};

pub struct SystemClock;

impl Clock for SystemClock {
    #[allow(
        clippy::disallowed_methods,
        reason = "application composition root, CQ-07"
    )]
    fn now(&self) -> Instant {
        let timestamp = jiff::Timestamp::now();
        match Instant::from_unix_millis(timestamp.as_millisecond()) {
            Ok(instant) => instant,
            Err(_) => std::process::abort(),
        }
    }
}

/// Uses the fixed E2E instant when that non-release feature is enabled.
pub fn clock_from_process() -> Result<std::sync::Arc<dyn Clock>, &'static str> {
    #[cfg(feature = "e2e")]
    {
        return clock_from_env(std::env::var("KINEVO_E2E_NOW").ok().as_deref());
    }

    #[cfg(not(feature = "e2e"))]
    {
        Ok(std::sync::Arc::new(SystemClock))
    }
}

/// Parses the fixed clock override without ever retaining an invalid value.
#[cfg(feature = "e2e")]
pub fn clock_from_env(value: Option<&str>) -> Result<std::sync::Arc<dyn Clock>, &'static str> {
    use kinevo_kernel::time::FixedClock;

    match value {
        Some(value) => {
            let instant = Instant::parse_rfc3339(value)
                .map_err(|_| "KINEVO_E2E_NOW must be a valid RFC3339 instant")?;
            Ok(std::sync::Arc::new(FixedClock::new(instant)))
        }
        None => Ok(std::sync::Arc::new(SystemClock)),
    }
}

pub struct UuidV7Gen;

impl IdGen for UuidV7Gen {
    #[allow(
        clippy::disallowed_methods,
        reason = "application composition root, CQ-07"
    )]
    fn new_uuid_v7(&self) -> uuid::Uuid {
        uuid::Uuid::now_v7()
    }
}

pub struct OsTokenGen;

impl TokenGen for OsTokenGen {
    fn fill(&self, buffer: &mut [u8]) {
        // getrandom is the stack-approved OS CSPRNG; rand_core 0.10.1 has no OsRng type.
        if getrandom::fill(buffer).is_err() {
            std::process::abort();
        }
    }
}

/// Production password adapter used by non-interactive first-account setup.
pub struct SetupPasswords {
    hasher: kinevo_mod_identity::infra::hasher::Hasher,
}

impl SetupPasswords {
    /// Builds the configured Argon2id hasher using the operating system's parallelism.
    pub fn new(
        tokens: std::sync::Arc<dyn TokenGen>,
        clock: std::sync::Arc<dyn Clock>,
    ) -> Result<Self, AppError> {
        let concurrency =
            std::thread::available_parallelism().map_or(1, std::num::NonZeroUsize::get);
        let hasher = kinevo_mod_identity::infra::hasher::Hasher::new(
            tokens,
            clock,
            kinevo_mod_identity::infra::hasher::HasherConfig::new(concurrency),
        )?;
        Ok(Self { hasher })
    }
}

#[async_trait::async_trait]
impl kinevo_mod_admin::app::finish_setup::SetupPasswordProcessor for SetupPasswords {
    fn validate(&self, raw: Secret<String>, minimum: usize) -> Result<Secret<String>, FieldCode> {
        use kinevo_mod_identity::domain::password::{
            CommonPasswords, PasswordPolicy, PlainPassword,
        };

        PlainPassword::parse(
            raw,
            &PasswordPolicy::new(minimum),
            &CommonPasswords::embedded(),
        )
        .map(|password| Secret::new(password.expose().to_owned()))
    }

    async fn hash(&self, password: &Secret<String>) -> Result<String, AppError> {
        use kinevo_mod_identity::domain::password::{
            CommonPasswords, PasswordPolicy, PlainPassword,
        };

        let password = PlainPassword::parse(
            Secret::new(password.expose().to_owned()),
            &PasswordPolicy::new(1),
            &CommonPasswords::embedded(),
        )
        .map_err(|_| AppError::new(kinevo_kernel::errors::ErrorCode::ValidationFailed))?;
        self.hasher
            .hash(&password)
            .await
            .map(|hash| hash.as_str().to_owned())
    }
}

/// System-role adapter used by the HTTP authorization cache.
pub struct DatabaseAccountAccess(kinevo_db::Db);

impl DatabaseAccountAccess {
    #[must_use]
    pub fn new(db: kinevo_db::Db) -> Self {
        Self(db)
    }
}

#[async_trait::async_trait]
impl kinevo_ports::platform::AccountAccess for DatabaseAccountAccess {
    async fn load(
        &self,
        id: AccountId,
    ) -> Result<Option<kinevo_ports::platform::AccountAccessInfo>, AppError> {
        use sqlx::Row as _;

        let mut tx = kinevo_db::SystemTx::begin(&self.0).await?;
        let row = sqlx::query(
            "SELECT status, is_instance_admin, version FROM public.accounts WHERE id=$1",
        )
        .bind(*id.as_uuid())
        .fetch_optional(tx.conn())
        .await
        .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        tx.commit().await?;
        row.map(|row| {
            let status = match row
                .try_get::<String, _>("status")
                .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?
                .as_str()
            {
                "active" => kinevo_ports::platform::AccountStatus::Active,
                "disabled" => kinevo_ports::platform::AccountStatus::Disabled,
                "deleting" => kinevo_ports::platform::AccountStatus::Deleting,
                _ => return Err(AppError::new(ErrorCode::InternalError)),
            };
            Ok(kinevo_ports::platform::AccountAccessInfo {
                id,
                status,
                is_instance_admin: row
                    .try_get("is_instance_admin")
                    .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?,
                version: row
                    .try_get("version")
                    .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?,
            })
        })
        .transpose()
    }
}

/// PostgreSQL-backed implementation of the shared idempotency middleware port.
pub struct DatabaseIdempotency {
    db: kinevo_db::Db,
    clock: std::sync::Arc<dyn Clock>,
}

impl DatabaseIdempotency {
    #[must_use]
    pub fn new(db: kinevo_db::Db, clock: std::sync::Arc<dyn Clock>) -> Self {
        Self { db, clock }
    }
}

#[async_trait::async_trait]
impl kinevo_ports::platform::IdempotencyPort for DatabaseIdempotency {
    async fn begin(
        &self,
        account: AccountId,
        key: &str,
        method: &str,
        path: &str,
        request_hash: [u8; 32],
        ttl_seconds: i64,
    ) -> Result<kinevo_ports::platform::IdemBegin, AppError> {
        let key = kinevo_db::idempotency::IdemKey::parse(key).map_err(|code| {
            AppError::validation(vec![kinevo_kernel::errors::FieldError {
                path: "Idempotency-Key".to_owned(),
                code,
                details: kinevo_kernel::errors::Details::new(),
            }])
        })?;
        let mut tx = kinevo_db::Tx::begin(&self.db, account).await?;
        let result = kinevo_db::idempotency::begin(
            &mut tx,
            &key,
            method,
            path,
            request_hash,
            self.clock.now(),
            ttl_seconds,
        )
        .await?;
        tx.commit().await?;
        Ok(match result {
            kinevo_db::idempotency::Begin::Fresh => kinevo_ports::platform::IdemBegin::Fresh,
            kinevo_db::idempotency::Begin::Replay(response) => {
                kinevo_ports::platform::IdemBegin::Replay {
                    status: response.status,
                    content_type: response.content_type,
                    body: response.body,
                }
            }
            kinevo_db::idempotency::Begin::InProgress => {
                kinevo_ports::platform::IdemBegin::InProgress
            }
            kinevo_db::idempotency::Begin::Mismatch => kinevo_ports::platform::IdemBegin::Mismatch,
        })
    }

    async fn finish(
        &self,
        account: AccountId,
        key: &str,
        status: u16,
        content_type: Option<String>,
        body: Option<Vec<u8>>,
    ) -> Result<(), AppError> {
        let key = kinevo_db::idempotency::IdemKey::parse(key).map_err(|code| {
            AppError::validation(vec![kinevo_kernel::errors::FieldError {
                path: "Idempotency-Key".to_owned(),
                code,
                details: kinevo_kernel::errors::Details::new(),
            }])
        })?;
        let mut tx = kinevo_db::Tx::begin(&self.db, account).await?;
        kinevo_db::idempotency::finish_with_content_type(
            &mut tx,
            &key,
            status,
            content_type.as_deref(),
            body.as_deref().unwrap_or_default(),
            body.is_some(),
        )
        .await?;
        tx.commit().await
    }

    async fn release(&self, account: AccountId, key: &str) -> Result<(), AppError> {
        let key = kinevo_db::idempotency::IdemKey::parse(key).map_err(|code| {
            AppError::validation(vec![kinevo_kernel::errors::FieldError {
                path: "Idempotency-Key".to_owned(),
                code,
                details: kinevo_kernel::errors::Details::new(),
            }])
        })?;
        let mut tx = kinevo_db::Tx::begin(&self.db, account).await?;
        kinevo_db::idempotency::release(&mut tx, &key).await?;
        tx.commit().await
    }
}

#[cfg(all(test, feature = "e2e"))]
mod tests {
    use super::clock_from_env;
    use kinevo_kernel::time::Instant;

    #[test]
    fn d_42_e2e_clock_uses_rfc3339_environment_value() {
        let clock =
            clock_from_env(Some("2030-01-02T03:04:05Z")).expect("valid E2E clock should parse");
        assert_eq!(
            clock.now(),
            Instant::parse_rfc3339("2030-01-02T03:04:05Z").expect("fixture should parse")
        );
        assert!(clock_from_env(Some("not-an-instant")).is_err());
    }
}
