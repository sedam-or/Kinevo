//! Cached reader for the singleton instance setup state.

use std::sync::Arc;

use async_trait::async_trait;
use kinevo_db::{Db, SystemTx};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    time::{Clock, Instant},
    validate::{net::HttpsUrl, tz::Locale},
};
use kinevo_ports::platform::{InstanceInfo, InstanceState};
use moka::sync::Cache;
use sqlx::Row;

#[derive(Clone)]
struct CachedState {
    info: InstanceInfo,
    expires_at: Instant,
}

/// Implements [`InstanceState`] with a short, injected-clock cache.
pub struct CachedInstanceState {
    db: Db,
    clock: Arc<dyn Clock>,
    cache_ttl_seconds: i64,
    cache: Cache<(), CachedState>,
}

impl CachedInstanceState {
    /// Builds an adapter; composition supplies the short cache lifetime.
    #[must_use]
    pub fn new(db: Db, clock: Arc<dyn Clock>, cache_ttl_seconds: i64) -> Self {
        Self {
            db,
            clock,
            cache_ttl_seconds,
            cache: Cache::new(1),
        }
    }

    /// Drops the cached snapshot after a committed instance-settings change.
    pub fn invalidate(&self) {
        self.cache.invalidate(&());
    }

    /// Marks setup complete in the local snapshot after the finish transaction commits.
    pub fn mark_completed(&self) {
        if let Some(mut cached) = self.cache.get(&()) {
            cached.info.setup_completed = true;
            self.cache.insert((), cached);
        }
    }

    async fn load(&self) -> Result<InstanceInfo, AppError> {
        let mut tx = SystemTx::begin(&self.db).await?;
        let row = sqlx::query(
            "SELECT setup_completed, base_url, default_locale \
             FROM public.instance_settings WHERE id=1",
        )
        .fetch_one(tx.conn())
        .await
        .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let setup_completed = row
            .try_get::<bool, _>("setup_completed")
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let base_url = row
            .try_get::<Option<String>, _>("base_url")
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?
            .map(|raw| {
                HttpsUrl::parse(&raw).map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))
            })
            .transpose()?;
        let locale = row
            .try_get::<String, _>("default_locale")
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let default_locale =
            Locale::parse(&locale).map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        tx.commit().await?;
        Ok(InstanceInfo {
            setup_completed,
            base_url,
            default_locale,
        })
    }
}

#[async_trait]
impl InstanceState for CachedInstanceState {
    async fn get(&self) -> Result<InstanceInfo, AppError> {
        let now = self.clock.now();
        if let Some(cached) = self.cache.get(&())
            && cached.expires_at > now
        {
            return Ok(cached.info);
        }
        let info = self.load().await?;
        let expires_at = now
            .checked_add_seconds(self.cache_ttl_seconds)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        self.cache.insert(
            (),
            CachedState {
                info: info.clone(),
                expires_at,
            },
        );
        Ok(info)
    }
}
