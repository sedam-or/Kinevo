//! Cached, typed policy snapshots for application ports.

use async_trait::async_trait;
use kinevo_db::outbox::{Subscriber, SubscriberName};
use kinevo_kernel::{
    errors::AppError,
    event::{EventEnvelope, EventKind},
    id::AccountId,
    policy::{PolicyVersion, registry::all_specs, resolve},
    time::Instant,
};
use kinevo_ports::platform::{PolicyReader, ResolvedPolicies};
use moka::future::Cache;
use sqlx::types::Uuid;
use std::{
    collections::HashSet,
    sync::{
        Arc, Mutex, OnceLock,
        atomic::{AtomicU64, Ordering},
    },
    time::Duration,
};

use super::policy_repo::PolicyRepository;

const CACHE_TTL_SECONDS: u64 = 30;
const CACHE_CAPACITY: u64 = 10_000;

type SnapshotKey = (Option<Uuid>, Instant);

/// Process-local cache shared by readers and the outbox invalidation subscriber.
pub struct PolicyCache {
    entries: Cache<SnapshotKey, ResolvedPolicies>,
    generation: AtomicU64,
}

impl PolicyCache {
    /// Creates the bounded cache with the compiled thirty-second policy lifetime.
    #[must_use]
    pub fn new() -> Self {
        Self {
            entries: Cache::builder()
                .max_capacity(CACHE_CAPACITY)
                .time_to_live(Duration::from_secs(CACHE_TTL_SECONDS))
                .build(),
            generation: AtomicU64::new(0),
        }
    }

    /// Invalidates every account and instance snapshot after a committed policy change.
    pub async fn invalidate_all(&self) {
        self.generation.fetch_add(1, Ordering::AcqRel);
        self.entries.invalidate_all();
        self.entries.run_pending_tasks().await;
    }

    fn generation(&self) -> u64 {
        self.generation.load(Ordering::Acquire)
    }
}

impl Default for PolicyCache {
    fn default() -> Self {
        Self::new()
    }
}

/// Resolves policy values from the registered kernel specs and the visible version rows.
pub struct CachedPolicyReader {
    repository: Arc<PolicyRepository>,
    cache: Arc<PolicyCache>,
}

impl CachedPolicyReader {
    /// Shares the repository and cache used by the policy service.
    #[must_use]
    pub fn new(repository: Arc<PolicyRepository>, cache: Arc<PolicyCache>) -> Self {
        Self { repository, cache }
    }
}

#[async_trait]
impl PolicyReader for CachedPolicyReader {
    async fn snapshot_values(
        &self,
        account: Option<AccountId>,
        at: Instant,
    ) -> Result<ResolvedPolicies, AppError> {
        let account_uuid = account.map(|id| *id.as_uuid());
        let key = (account_uuid, at);
        if let Some(snapshot) = self.cache.entries.get(&key).await {
            return Ok(snapshot);
        }

        let generation = self.cache.generation();
        let versions = self.repository.load_visible(account, at).await?;
        let snapshot = resolve_snapshot(&versions, account, at);
        if generation == self.cache.generation() {
            self.cache.entries.insert(key, snapshot.clone()).await;
        }
        Ok(snapshot)
    }
}

fn resolve_snapshot(
    versions: &[PolicyVersion],
    account: Option<AccountId>,
    at: Instant,
) -> ResolvedPolicies {
    let mut resolved = ResolvedPolicies::default();
    for spec in all_specs() {
        let value = resolve(spec, versions, account, at);
        if value.out_of_bounds {
            warn_out_of_bounds_once(spec.key);
        }
        resolved.insert(spec.key, value);
    }
    resolved
}

fn warn_out_of_bounds_once(key: &'static str) -> bool {
    static WARNED: OnceLock<Mutex<HashSet<&'static str>>> = OnceLock::new();
    let warned = WARNED.get_or_init(|| Mutex::new(HashSet::new()));
    let mut warned = warned
        .lock()
        .unwrap_or_else(std::sync::PoisonError::into_inner);
    if warned.insert(key) {
        tracing::warn!(event = "policy_out_of_bounds", policy_key = key);
        true
    } else {
        false
    }
}

/// Outbox handler that invalidates local policy snapshots on changes from another node.
pub struct PolicyChangedSubscriber {
    cache: Arc<PolicyCache>,
}

impl PolicyChangedSubscriber {
    /// Shares the policy cache invalidated by the service after local commits.
    #[must_use]
    pub fn new(cache: Arc<PolicyCache>) -> Self {
        Self { cache }
    }
}

#[async_trait]
impl Subscriber for PolicyChangedSubscriber {
    fn name(&self) -> SubscriberName {
        SubscriberName::new("admin.policy.cache")
    }

    async fn handle(&self, event: &EventEnvelope) -> Result<(), AppError> {
        if event.kind == EventKind::PolicyChanged {
            self.cache.invalidate_all().await;
        }
        Ok(())
    }
}

/// Returns the stable subscriber name used by the policy service's outbox registry.
#[must_use]
pub const fn subscriber_name() -> SubscriberName {
    SubscriberName::new("admin.policy.cache")
}

/// Registers the outbox invalidation handler for policy changes.
pub fn register_policy_subscriber(registry: &mut kinevo_db::outbox::SubscriberRegistry) {
    registry.register(subscriber_name(), EventKind::PolicyChanged.as_str(), 1);
}

#[cfg(test)]
mod tests {
    use super::warn_out_of_bounds_once;

    #[test]
    fn warning_is_emitted_once_per_policy_key() {
        assert!(warn_out_of_bounds_once("test.warning.once"));
        assert!(!warn_out_of_bounds_once("test.warning.once"));
    }
}
