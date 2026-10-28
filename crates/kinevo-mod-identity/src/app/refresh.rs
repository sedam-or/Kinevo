use std::sync::{
    Arc,
    atomic::{AtomicU64, Ordering},
};
use std::time::Duration;

use kinevo_db::{
    Db, SystemTx,
    audit::{AuditAction, AuditEntry, AuditWriter},
};
use kinevo_http::token::{Claims, TokenCodec};
use kinevo_kernel::{
    crypto::{decode_token, encode_token, hash_token},
    errors::{AppError, DetailValue, Details, ErrorCode},
    id::{AccountId, FamilyId, IdGen, TokenGen},
    secret::Secret,
    time::{Clock, Instant},
};
use moka::{Expiry, future::Cache};

use crate::{
    domain::session::{RefreshDecision, RevocationReason, SessionPolicy, decide, idle_expiry},
    infra::sessions,
};

pub use kinevo_ports::platform::Client;

const GRACE_CACHE_CAPACITY: u64 = 10_000;

/// Tokens returned after a successful rotation.
pub struct Refreshed {
    pub access_token: String,
    pub expires_in: u32,
    pub refresh_token: Secret<String>,
    pub account_id: AccountId,
    pub family_id: FamilyId,
    pub refresh_cookie_max_age: u32,
}

/// Sensitive token pair held in the in-process retry cache.
pub struct GracePair {
    access_token: Secret<String>,
    refresh_token: Secret<String>,
    expires_in: u32,
    account_id: AccountId,
    family_id: FamilyId,
    absolute_expires_at: Instant,
}

impl GracePair {
    #[must_use]
    pub fn new(
        access_token: Secret<String>,
        refresh_token: Secret<String>,
        expires_in: u32,
        account_id: AccountId,
        family_id: FamilyId,
        absolute_expires_at: Instant,
    ) -> Self {
        Self {
            access_token,
            refresh_token,
            expires_in,
            account_id,
            family_id,
            absolute_expires_at,
        }
    }

    fn refreshed(&self, now: Instant) -> Refreshed {
        Refreshed {
            access_token: self.access_token.expose().clone(),
            expires_in: self.expires_in,
            refresh_token: Secret::new(self.refresh_token.expose().clone()),
            account_id: self.account_id,
            family_id: self.family_id,
            refresh_cookie_max_age: seconds_until(self.absolute_expires_at, now),
        }
    }
}

struct CacheEntry {
    expires_at: Instant,
    ttl: Duration,
    pair: Arc<GracePair>,
}

impl Clone for CacheEntry {
    fn clone(&self) -> Self {
        Self {
            expires_at: self.expires_at,
            ttl: self.ttl,
            pair: Arc::clone(&self.pair),
        }
    }
}

#[derive(Clone, Copy)]
struct GraceExpiry;

impl Expiry<[u8; 32], CacheEntry> for GraceExpiry {
    fn expire_after_create(
        &self,
        _key: &[u8; 32],
        value: &CacheEntry,
        _created_at: std::time::Instant,
    ) -> Option<Duration> {
        Some(value.ttl)
    }
}

/// Process-local, bounded idempotency cache for simultaneous refresh requests.
pub struct GraceCache {
    entries: Cache<[u8; 32], CacheEntry>,
    hits: AtomicU64,
    misses: AtomicU64,
}

/// Snapshot of grace-cache hit and miss counters.
#[derive(Clone, Copy, Debug, Default, Eq, PartialEq)]
pub struct GraceCacheMetrics {
    pub hits: u64,
    pub misses: u64,
}

impl GraceCache {
    #[must_use]
    pub fn new() -> Self {
        Self {
            entries: Cache::builder()
                .max_capacity(GRACE_CACHE_CAPACITY)
                .expire_after(GraceExpiry)
                .build(),
            hits: AtomicU64::new(0),
            misses: AtomicU64::new(0),
        }
    }

    /// Stores a sensitive pair under the old token hash and its injected-clock expiry.
    pub async fn put(
        &self,
        old_hash: [u8; 32],
        expires_at: Instant,
        ttl: Duration,
        pair: GracePair,
    ) -> Arc<GracePair> {
        let pair = Arc::new(pair);
        self.entries
            .insert(
                old_hash,
                CacheEntry {
                    expires_at,
                    ttl,
                    pair: Arc::clone(&pair),
                },
            )
            .await;
        pair
    }

    /// Returns a cached pair only while its injected-clock expiry is in the future.
    pub async fn get(&self, old_hash: &[u8; 32], now: Instant) -> Option<Arc<GracePair>> {
        if let Some(entry) = self.entries.get(old_hash).await {
            if now < entry.expires_at {
                self.hits.fetch_add(1, Ordering::Relaxed);
                return Some(entry.pair);
            }
            self.entries.invalidate(old_hash).await;
        }
        self.misses.fetch_add(1, Ordering::Relaxed);
        None
    }

    /// Returns the number of entries after pending capacity evictions finish.
    pub async fn entry_count(&self) -> u64 {
        self.entries.run_pending_tasks().await;
        self.entries.entry_count()
    }

    #[must_use]
    pub fn metrics(&self) -> GraceCacheMetrics {
        GraceCacheMetrics {
            hits: self.hits.load(Ordering::Relaxed),
            misses: self.misses.load(Ordering::Relaxed),
        }
    }
}

impl Default for GraceCache {
    fn default() -> Self {
        Self::new()
    }
}

/// Coordinates refresh-token state changes in the restricted system context.
pub struct RefreshService {
    db: Db,
    clock: Arc<dyn Clock>,
    ids: Arc<dyn IdGen>,
    tokens: Arc<dyn TokenGen>,
    codec: Arc<TokenCodec>,
    cache: GraceCache,
    audit: AuditWriter,
}

impl RefreshService {
    #[must_use]
    pub fn new(
        db: Db,
        clock: Arc<dyn Clock>,
        ids: Arc<dyn IdGen>,
        tokens: Arc<dyn TokenGen>,
        codec: Arc<TokenCodec>,
    ) -> Self {
        let audit = AuditWriter::new(Arc::clone(&ids));
        Self {
            db,
            clock,
            ids,
            tokens,
            codec,
            cache: GraceCache::new(),
            audit,
        }
    }

    /// Rotates a refresh token, handles grace retries and revokes detected reuse.
    pub async fn refresh(
        &self,
        presented: Secret<String>,
        policy: SessionPolicy,
        _client: Client,
    ) -> Result<Refreshed, AppError> {
        let raw = decode_token(presented.expose()).map_err(|_| unauthenticated())?;
        let raw = Secret::new(raw);
        let old_hash = hash_token(raw.expose());
        let now = self.clock.now();
        let mut tx = SystemTx::begin(&self.db).await?;
        let Some((row, family)) = sessions::lock_by_hash(&mut tx, &old_hash).await? else {
            tx.rollback().await?;
            return Err(unauthenticated());
        };
        let cached = if row.used_at.is_some() {
            self.cache.get(&old_hash, now).await
        } else {
            None
        };
        let decision = decide(&row, &family, now, cached.is_some());

        match decision {
            RefreshDecision::Unknown => {
                tx.rollback().await?;
                Err(unauthenticated())
            }
            RefreshDecision::Revoked => {
                tx.commit().await?;
                Err(unauthenticated())
            }
            RefreshDecision::Expired => {
                sessions::revoke_family(&mut tx, family.id, RevocationReason::Expired, now).await?;
                tx.commit().await?;
                Err(unauthenticated())
            }
            RefreshDecision::Rotate => self.rotate(tx, &row, &family, old_hash, now, policy).await,
            RefreshDecision::Grace => {
                let Some(pair) = cached else {
                    tx.rollback().await?;
                    return Err(unauthenticated());
                };
                tx.commit().await?;
                Ok(pair.refreshed(now))
            }
            RefreshDecision::Reuse => {
                sessions::revoke_family(&mut tx, family.id, RevocationReason::ReuseDetected, now)
                    .await?;
                self.audit
                    .record(
                        &mut tx,
                        AuditEntry {
                            account_id: Some(family.account_id),
                            actor_id: None,
                            action: AuditAction::AuthRefreshReuse,
                            target_type: None,
                            target_id: None,
                            details: Details::new()
                                .with_detail("family_id", DetailValue::Id(*family.id.as_uuid())),
                            request_id: None,
                            occurred_at: now,
                        },
                    )
                    .await?;
                tx.commit().await?;
                Err(AppError::new(ErrorCode::RefreshReuseDetected))
            }
        }
    }

    /// Revokes the family named by a cookie token; malformed and unknown tokens are idempotent.
    pub async fn logout(&self, presented: Option<Secret<String>>) -> Result<(), AppError> {
        let Some(presented) = presented else {
            return Ok(());
        };
        let Ok(raw) = decode_token(presented.expose()) else {
            return Ok(());
        };
        let hash = hash_token(&raw);
        let now = self.clock.now();
        let mut tx = SystemTx::begin(&self.db).await?;
        let Some((_, family)) = sessions::lock_by_hash(&mut tx, &hash).await? else {
            tx.rollback().await?;
            return Ok(());
        };
        sessions::revoke_family(&mut tx, family.id, RevocationReason::Logout, now).await?;
        tx.commit().await?;
        Ok(())
    }

    /// Revokes every refresh family owned by an authenticated account.
    pub async fn logout_all(&self, account_id: AccountId) -> Result<(), AppError> {
        let now = self.clock.now();
        let mut tx = SystemTx::begin(&self.db).await?;
        sessions::revoke_all_for_account(&mut tx, account_id, RevocationReason::Logout, now, None)
            .await?;
        tx.commit().await?;
        Ok(())
    }

    async fn rotate(
        &self,
        mut tx: SystemTx,
        row: &crate::domain::session::TokenRow,
        family: &crate::domain::session::FamilyRow,
        old_hash: [u8; 32],
        now: Instant,
        policy: SessionPolicy,
    ) -> Result<Refreshed, AppError> {
        let raw_refresh = Secret::new(self.tokens.token_256());
        let refresh_token = Secret::new(encode_token(raw_refresh.expose()));
        let new_hash = hash_token(raw_refresh.expose());
        let new_token_id = self.ids.new_uuid_v7();
        let access_expires_at = now
            .checked_add_seconds(i64::from(policy.access_ttl))
            .ok_or_else(internal_error)?;
        let access_token = Secret::new(self.codec.issue(&Claims {
            sub: row.account_id,
            sid: family.id,
            jti: self.ids.new_uuid_v7(),
            iat: now,
            exp: access_expires_at,
            mfa: family.mfa,
        }));
        if access_token.expose().is_empty() {
            return Err(internal_error());
        }
        let idle_expires_at = idle_expiry(now, policy, family.absolute_expires_at);
        sessions::mark_rotated(&mut tx, row, new_token_id, &new_hash, now, idle_expires_at).await?;
        let grace_expires_at = now
            .checked_add_seconds(i64::from(policy.grace))
            .unwrap_or(family.absolute_expires_at);
        let pair = self
            .cache
            .put(
                old_hash,
                grace_expires_at,
                Duration::from_secs(u64::from(policy.grace)),
                GracePair::new(
                    access_token,
                    refresh_token,
                    policy.access_ttl,
                    row.account_id,
                    family.id,
                    family.absolute_expires_at,
                ),
            )
            .await;
        tx.commit().await?;
        Ok(pair.refreshed(now))
    }
}

fn seconds_until(until: Instant, now: Instant) -> u32 {
    let remaining_seconds = until.unix_millis().saturating_sub(now.unix_millis()) / 1_000;
    u32::try_from(remaining_seconds.max(0)).unwrap_or(u32::MAX)
}

fn unauthenticated() -> AppError {
    AppError::new(ErrorCode::Unauthenticated)
}

fn internal_error() -> AppError {
    AppError::new(ErrorCode::InternalError)
}
