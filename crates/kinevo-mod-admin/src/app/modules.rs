//! Module switch use cases, cached module metadata, and instance summary.

use std::{collections::BTreeSet, sync::Arc};

use kinevo_db::{
    Db,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    outbox::{self, Subscriber, SubscriberRegistry},
};
use kinevo_http::auth::{AuthCtx, Role};
use kinevo_kernel::{
    errors::{AppError, DetailValue, Details, ErrorCode, version_conflict},
    event::{EventEnumValue, EventEnvelope, EventKind, EventPayload},
    id::{EventId, IdGen},
    policy::registry::identity::REGISTRATION_MODE,
    time::Clock,
};
use kinevo_ports::{
    module::{ModuleDescriptor, ModuleKey, ModuleKind, SwitchOutcome, plan_switch},
    platform::PolicyReader,
};
use serde::Serialize;

use crate::infra::{
    module_repo::{InstanceRow, ModuleRepository, ModuleRow, ModuleSwitchUpdate},
    module_switches::{CachedModuleSwitches, ModuleChangedSubscriber, register_module_subscriber},
};

/// One module descriptor and its caller-visible runtime state.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
pub struct ModuleView {
    pub key: &'static str,
    pub kind: &'static str,
    pub enabled: bool,
    pub version: i64,
    pub nav: Vec<NavView>,
    pub settings_panels: Vec<SettingsPanelView>,
}

/// Caller-visible module navigation metadata.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
pub struct NavView {
    pub key: &'static str,
    pub label_key: &'static str,
    pub route: &'static str,
    pub order: u16,
}

/// Caller-visible module settings metadata.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
pub struct SettingsPanelView {
    pub key: &'static str,
    pub label_key: &'static str,
}

/// Public instance settings and the resolved registration policy.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
pub struct InstanceSummary {
    pub version: i64,
    pub setup_completed: bool,
    pub base_url: Option<String>,
    pub default_locale: String,
    pub template_id: Option<String>,
    pub registration_mode: &'static str,
}

/// Module switching and metadata queries shared by the admin HTTP routes.
pub struct ModuleService {
    db: Db,
    repository: Arc<ModuleRepository>,
    descriptors: Vec<&'static ModuleDescriptor>,
    switches: Arc<CachedModuleSwitches>,
    audit: AuditWriter,
    ids: Arc<dyn IdGen>,
    clock: Arc<dyn Clock>,
    policies: Arc<dyn PolicyReader>,
    subscribers: SubscriberRegistry,
}

impl ModuleService {
    /// Builds a switch service from the compiled descriptor catalogue and platform adapters.
    #[must_use]
    pub fn new(
        db: Db,
        descriptors: Vec<&'static ModuleDescriptor>,
        ids: Arc<dyn IdGen>,
        clock: Arc<dyn Clock>,
        policies: Arc<dyn PolicyReader>,
        mut subscribers: SubscriberRegistry,
    ) -> Self {
        let repository = Arc::new(ModuleRepository::new(db.clone()));
        let switches = Arc::new(CachedModuleSwitches::new(
            Arc::clone(&repository),
            descriptors.clone(),
            Arc::clone(&policies),
            Arc::clone(&clock),
        ));
        register_module_subscriber(&mut subscribers);
        Self {
            db,
            repository,
            descriptors,
            switches,
            audit: AuditWriter::new(Arc::clone(&ids)),
            ids,
            clock,
            policies,
            subscribers,
        }
    }

    /// Shares the same process-local switch cache with the HTTP module gate.
    #[must_use]
    pub fn switches(&self) -> &Arc<CachedModuleSwitches> {
        &self.switches
    }

    /// Returns the local module-cache invalidation subscriber for application wiring.
    #[must_use]
    pub fn cache_subscriber(&self) -> Arc<dyn Subscriber> {
        Arc::new(ModuleChangedSubscriber::new(Arc::clone(&self.switches)))
    }

    /// Lists compiled modules in descriptor order with only the caller's usable panels.
    pub async fn list(&self, auth: &AuthCtx) -> Result<Vec<ModuleView>, AppError> {
        let snapshot = self.switches.current_snapshot().await?;
        self.descriptors
            .iter()
            .map(|descriptor| {
                let key = ModuleKey::parse(descriptor.key)
                    .map_err(|_| AppError::new(ErrorCode::InternalError))?;
                let enabled =
                    descriptor.kind == ModuleKind::Core || snapshot.enabled.contains(&key);
                let version = snapshot
                    .rows
                    .get(descriptor.key)
                    .map_or(0, |row| row.version);
                Ok(module_view(descriptor, enabled, version, auth.role()))
            })
            .collect()
    }

    /// Validates and applies one module switch with its audit and event in one transaction.
    pub async fn set(
        &self,
        auth: &AuthCtx,
        raw_key: &str,
        enabled: bool,
        version: i64,
    ) -> Result<ModuleView, AppError> {
        auth.require_admin()?;
        let descriptor = self
            .descriptors
            .iter()
            .copied()
            .find(|descriptor| descriptor.key == raw_key)
            .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
        let current_key = ModuleKey::parse(descriptor.key)
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        let mut tx = kinevo_db::Tx::begin_admin(&self.db, auth.account_id()).await?;
        self.repository.lock_instance(&mut tx).await?;
        let rows = self.repository.load_rows_tx(&mut tx).await?;
        let current = enabled_modules(&self.descriptors, &rows)?;
        let current_version = rows.get(descriptor.key).map_or(0, |row| row.version);
        if version != current_version {
            let enabled_now = descriptor.kind == ModuleKind::Core || current.contains(&current_key);
            return Err(version_conflict(
                current_version,
                &module_view(descriptor, enabled_now, current_version, auth.role()),
            ));
        }

        match plan_switch(&self.descriptors, &current, descriptor.key, enabled)? {
            SwitchOutcome::Unchanged => {
                tx.commit().await?;
                let enabled_now =
                    descriptor.kind == ModuleKind::Core || current.contains(&current_key);
                Ok(module_view(
                    descriptor,
                    enabled_now,
                    current_version,
                    auth.role(),
                ))
            }
            SwitchOutcome::Changed => {
                let next_version = current_version
                    .checked_add(1)
                    .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
                let now = self.clock.now();
                self.repository
                    .save(
                        &mut tx,
                        ModuleSwitchUpdate {
                            key: descriptor.key,
                            enabled,
                            expected_version: current_version,
                            next_version,
                            actor: auth.account_id(),
                            now,
                        },
                    )
                    .await?;
                self.audit
                    .record(
                        &mut tx,
                        AuditEntry {
                            account_id: None,
                            actor_id: Some(*auth.account_id().as_uuid()),
                            action: AuditAction::ModuleSwitched,
                            target_type: Some(AuditTarget::from_static("module")),
                            target_id: Some(descriptor.key.to_owned()),
                            details: Details::new().with_detail(
                                "status",
                                DetailValue::Str(if enabled { "enabled" } else { "disabled" }),
                            ),
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
                        kind: EventKind::ModuleSwitched,
                        v: 1,
                        account_id: None,
                        occurred_at: now,
                        payload: EventPayload::new()
                            .with_enum("module", EventEnumValue::new(descriptor.key))
                            .with_enum(
                                "enabled",
                                EventEnumValue::new(if enabled { "enabled" } else { "disabled" }),
                            ),
                    },
                    &self.subscribers,
                )
                .await?;
                tx.commit().await?;
                self.switches.refresh_snapshot().await?;
                Ok(module_view(descriptor, enabled, next_version, auth.role()))
            }
        }
    }

    /// Reads the documented instance summary and its typed registration-mode policy.
    pub async fn instance_summary(&self, auth: &AuthCtx) -> Result<InstanceSummary, AppError> {
        let instance: InstanceRow = self.repository.load_instance().await?;
        let policies = self
            .policies
            .snapshot_values(Some(auth.account_id()), self.clock.now())
            .await?;
        let registration_mode = policies
            .get(&REGISTRATION_MODE)
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        Ok(InstanceSummary {
            version: instance.version,
            setup_completed: instance.setup_completed,
            base_url: instance.base_url,
            default_locale: instance.default_locale,
            template_id: instance.template_id,
            registration_mode: registration_mode.0,
        })
    }
}

fn enabled_modules(
    descriptors: &[&'static ModuleDescriptor],
    rows: &std::collections::BTreeMap<String, ModuleRow>,
) -> Result<BTreeSet<ModuleKey>, AppError> {
    let mut enabled = BTreeSet::new();
    for descriptor in descriptors {
        let key = ModuleKey::parse(descriptor.key)
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        if descriptor.kind == ModuleKind::Core
            || rows.get(descriptor.key).is_some_and(|row| row.enabled)
        {
            enabled.insert(key);
        }
    }
    Ok(enabled)
}

fn module_view(
    descriptor: &ModuleDescriptor,
    enabled: bool,
    version: i64,
    role: Role,
) -> ModuleView {
    let nav = if enabled {
        descriptor
            .nav
            .iter()
            .filter(|item| has_permission(descriptor, role, item.required_permission))
            .map(|item| NavView {
                key: item.key,
                label_key: item.label_key,
                route: item.route,
                order: item.order,
            })
            .collect()
    } else {
        Vec::new()
    };
    let settings_panels = if enabled {
        descriptor
            .settings_panels
            .iter()
            .filter(|panel| has_permission(descriptor, role, panel.required_permission))
            .map(|panel| SettingsPanelView {
                key: panel.key,
                label_key: panel.label_key,
            })
            .collect()
    } else {
        Vec::new()
    };
    ModuleView {
        key: descriptor.key,
        kind: match descriptor.kind {
            ModuleKind::Core => "core",
            ModuleKind::Toggleable => "toggleable",
        },
        enabled,
        version,
        nav,
        settings_panels,
    }
}

fn has_permission(descriptor: &ModuleDescriptor, role: Role, key: &str) -> bool {
    descriptor
        .permissions
        .iter()
        .find(|permission| permission.key == key)
        .is_some_and(|permission| role == Role::InstanceAdmin || permission.owner)
}
