//! Cached, policy-TTL module switches and outbox invalidation.

use std::{
    collections::{BTreeMap, BTreeSet},
    sync::Arc,
};

use async_trait::async_trait;
use kinevo_db::outbox::{Subscriber, SubscriberName};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    policy::{PolicyDuration, registry::admin::MODULES_CACHE_TTL_SECONDS},
    time::{Clock, Instant},
};
use kinevo_ports::{
    module::{ModuleDescriptor, ModuleKey, ModuleKind},
    platform::{ModuleSwitches, PolicyReader},
};
use moka::sync::Cache;

use super::module_repo::{ModuleRepository, ModuleRow};

#[derive(Clone)]
pub(crate) struct ModuleSnapshot {
    pub rows: BTreeMap<String, ModuleRow>,
    pub enabled: BTreeSet<ModuleKey>,
    expires_at: Instant,
}

/// Module switch cache shared by the admin service and HTTP module gate.
pub struct CachedModuleSwitches {
    cache: Cache<(), ModuleSnapshot>,
    repository: Arc<ModuleRepository>,
    descriptors: Vec<&'static ModuleDescriptor>,
    policies: Arc<dyn PolicyReader>,
    clock: Arc<dyn Clock>,
}

impl CachedModuleSwitches {
    /// Builds a bounded module snapshot cache using the registered module TTL policy.
    #[must_use]
    pub fn new(
        repository: Arc<ModuleRepository>,
        descriptors: Vec<&'static ModuleDescriptor>,
        policies: Arc<dyn PolicyReader>,
        clock: Arc<dyn Clock>,
    ) -> Self {
        Self {
            cache: Cache::new(1),
            repository,
            descriptors,
            policies,
            clock,
        }
    }

    /// Returns a current snapshot, refreshing it when the configured policy TTL elapsed.
    pub(crate) async fn current_snapshot(&self) -> Result<ModuleSnapshot, AppError> {
        let now = self.clock.now();
        if let Some(snapshot) = self.cache.get(&())
            && snapshot.expires_at > now
        {
            return Ok(snapshot);
        }
        self.refresh_snapshot().await?;
        self.cache
            .get(&())
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))
    }

    /// Reloads all persisted rows and updates the local module-gate snapshot.
    pub(crate) async fn refresh_snapshot(&self) -> Result<(), AppError> {
        let now = self.clock.now();
        let policy_values = self.policies.snapshot_values(None, now).await?;
        let PolicyDuration(ttl_seconds) = policy_values
            .get(&MODULES_CACHE_TTL_SECONDS)
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let expires_at = now
            .checked_add_seconds(ttl_seconds)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        let rows = self.repository.load_rows().await?;
        let mut enabled = self
            .descriptors
            .iter()
            .filter(|descriptor| descriptor.kind == ModuleKind::Core)
            .filter_map(|descriptor| ModuleKey::parse(descriptor.key).ok())
            .collect::<BTreeSet<_>>();
        for (raw_key, row) in &rows {
            let Some(descriptor) = self
                .descriptors
                .iter()
                .find(|descriptor| descriptor.key == raw_key)
            else {
                continue;
            };
            let key = ModuleKey::parse(raw_key)
                .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
            if descriptor.kind == ModuleKind::Core || row.enabled {
                enabled.insert(key);
            } else {
                enabled.remove(&key);
            }
        }
        self.cache.insert(
            (),
            ModuleSnapshot {
                rows,
                enabled,
                expires_at,
            },
        );
        Ok(())
    }

    /// Refreshes the cache through the port interface, retaining the last valid snapshot on error.
    pub async fn refresh(&self) {
        let _ = self.refresh_snapshot().await;
    }

    /// Reads enabled state from the current cache, failing closed after its policy TTL.
    #[must_use]
    pub fn is_enabled(&self, key: &ModuleKey) -> bool {
        self.cache.get(&()).is_some_and(|snapshot| {
            snapshot.expires_at > self.clock.now() && snapshot.enabled.contains(key)
        })
    }
}

#[async_trait]
impl ModuleSwitches for CachedModuleSwitches {
    fn is_enabled(&self, key: &ModuleKey) -> bool {
        Self::is_enabled(self, key)
    }

    async fn refresh(&self) {
        Self::refresh(self).await;
    }
}

/// Invalidates this process's module snapshot when another node switches a module.
pub struct ModuleChangedSubscriber {
    switches: Arc<CachedModuleSwitches>,
}

impl ModuleChangedSubscriber {
    /// Shares the application's module switch cache.
    #[must_use]
    pub fn new(switches: Arc<CachedModuleSwitches>) -> Self {
        Self { switches }
    }
}

#[async_trait]
impl Subscriber for ModuleChangedSubscriber {
    fn name(&self) -> SubscriberName {
        subscriber_name()
    }

    async fn handle(&self, event: &kinevo_kernel::event::EventEnvelope) -> Result<(), AppError> {
        if event.kind == kinevo_kernel::event::EventKind::ModuleSwitched {
            self.switches.refresh_snapshot().await?;
        }
        Ok(())
    }
}

/// Stable outbox subscriber name registered for `ModuleSwitched`.
#[must_use]
pub const fn subscriber_name() -> SubscriberName {
    SubscriberName::new("admin.module.cache")
}

/// Registers module-cache invalidation for outbox event delivery.
pub fn register_module_subscriber(registry: &mut kinevo_db::outbox::SubscriberRegistry) {
    registry.register(subscriber_name(), "ModuleSwitched", 1);
}
