//! Static module descriptors and pure dependency planning.

// SPEC: MOD-03, MOD-10, MOD-11, CA-10, CQ-09

use async_trait::async_trait;
use kinevo_kernel::{
    errors::{AppError, DetailValue, ErrorCode},
    id::AccountId,
    validate::{
        text::Key,
        tz::{IanaTz, Locale},
    },
};
use regex::Regex;
use std::{
    borrow::Borrow,
    collections::{BTreeMap, BTreeSet},
    sync::OnceLock,
};

static MODULE_KEY_PATTERN: OnceLock<Result<Regex, regex::Error>> = OnceLock::new();
static PERMISSION_SEGMENT_PATTERN: OnceLock<Result<Regex, regex::Error>> = OnceLock::new();

/// Validated stable identifier for a compiled module.
#[derive(Clone, Debug, Eq, Hash, Ord, PartialEq, PartialOrd)]
// SPEC: MOD-03
pub struct ModuleKey(String);

impl ModuleKey {
    /// Parses the module-key grammar; invalid and non-ASCII keys are rejected.
    pub fn parse(value: &str) -> Result<Self, ModuleKeyError> {
        if !value.is_ascii() {
            return Err(ModuleKeyError);
        }
        let pattern = MODULE_KEY_PATTERN.get_or_init(|| Regex::new(r"^[a-z][a-z0-9_]{1,23}$"));
        let pattern = pattern.as_ref().map_err(|_| ModuleKeyError)?;
        let key = Key::parse(value, pattern).map_err(|_| ModuleKeyError)?;
        if key.as_str() != value {
            return Err(ModuleKeyError);
        }
        Ok(Self(key.as_str().to_owned()))
    }

    /// Returns the canonical module key.
    /// Returns the canonical permission key.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

impl Borrow<str> for ModuleKey {
    fn borrow(&self) -> &str {
        self.as_str()
    }
}

/// Indicates that a module key did not match its required grammar.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct ModuleKeyError;

/// Validated `<module>.<resource>.<action>` permission identifier.
#[derive(Clone, Debug, Eq, Hash, Ord, PartialEq, PartialOrd)]
// SPEC: RBAC-03
pub struct PermissionKey(String);

impl PermissionKey {
    /// Parses exactly three lower-case key segments.
    pub fn parse(value: &str) -> Result<Self, PermissionKeyError> {
        if !value.is_ascii() {
            return Err(PermissionKeyError);
        }
        let pattern =
            PERMISSION_SEGMENT_PATTERN.get_or_init(|| Regex::new(r"^[a-z][a-z0-9_]{1,23}$"));
        let pattern = pattern.as_ref().map_err(|_| PermissionKeyError)?;
        let mut segments = value.split('.');
        let module = segments.next().ok_or(PermissionKeyError)?;
        let resource = segments.next().ok_or(PermissionKeyError)?;
        let action = segments.next().ok_or(PermissionKeyError)?;
        if segments.next().is_some() {
            return Err(PermissionKeyError);
        }
        for segment in [module, resource, action] {
            let key = Key::parse(segment, pattern).map_err(|_| PermissionKeyError)?;
            if key.as_str() != segment {
                return Err(PermissionKeyError);
            }
        }
        Ok(Self(value.to_owned()))
    }

    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

/// Indicates that a permission key did not match its required grammar.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct PermissionKeyError;

/// Whether a compiled module is always enabled or can be switched off.
#[derive(Clone, Copy, Debug, Eq, PartialEq, serde::Serialize, serde::Deserialize)]
// SPEC: MOD-02
pub enum ModuleKind {
    /// Required for core product flows and cannot be disabled.
    Core,
    /// Optional per instance once all dependencies are enabled.
    Toggleable,
}

/// Static navigation metadata returned only while the owning module is enabled.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
// SPEC: MOD-01
pub struct NavItem {
    /// Stable item identifier within its module.
    pub key: &'static str,
    /// Message-catalogue key for the item label.
    pub label_key: &'static str,
    /// Client route mounted by this module.
    pub route: &'static str,
    /// Stable order within this module navigation.
    pub order: u16,
    /// Permission required before the item is returned to a caller.
    pub required_permission: &'static str,
}

/// Static settings-panel metadata exposed with a module descriptor.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
// SPEC: MOD-01
pub struct SettingsPanel {
    /// Stable panel identifier within its module.
    pub key: &'static str,
    /// Message-catalogue key for the panel label.
    pub label_key: &'static str,
    /// Permission required before the panel is returned to a caller.
    pub required_permission: &'static str,
}

/// Stable identifier for a module-owned job handler.
#[derive(Clone, Copy, Debug, Eq, Hash, PartialEq)]
pub struct JobKind(pub &'static str);

/// Static metadata for materializing a recurring job from a policy cadence.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
// SPEC: MOD-05
pub struct JobSchedule {
    /// Job kind registered by this module.
    pub kind: &'static str,
    /// Typed policy key that supplies the cadence.
    pub cadence_policy_key: &'static str,
}

/// Permission exported by a module and its account-owner visibility.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct PermissionDef {
    /// Permission identifier in `<module>.<resource>.<action>` form.
    pub key: &'static str,
    /// Whether `account_owner` receives this permission; instance admins receive all keys.
    pub owner: bool,
}

/// Const-constructible static metadata for one compiled module.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
// SPEC: D-43, MOD-03, MOD-08
pub struct ModuleDescriptor {
    /// Stable module key.
    pub key: &'static str,
    /// Core or instance-toggleable classification.
    pub kind: ModuleKind,
    /// Module keys that must be enabled before this module can be enabled.
    pub requires: &'static [&'static str],
    /// Permissions owned by this module.
    pub permissions: &'static [PermissionDef],
    /// Typed policy keys owned by this module.
    pub policy_keys: &'static [&'static str],
    /// Navigation items exposed while enabled.
    pub nav: &'static [NavItem],
    /// Settings panels exposed while enabled.
    pub settings_panels: &'static [SettingsPanel],
    /// Message namespaces required by the module.
    pub message_namespaces: &'static [&'static str],
    /// Job kinds registered by the module.
    pub jobs: &'static [&'static str],
    /// Recurring schedules owned by the module.
    pub job_schedules: &'static [JobSchedule],
    /// Event kinds consumed by the module.
    pub subscriptions: &'static [&'static str],
}

/// Typed descriptor-validation failure that contains no untrusted input text.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
// SPEC: MOD-03
pub enum GraphError {
    InvalidModuleKey,
    DuplicateModuleKey,
    UnknownRequirement,
    CoreRequiresToggleable,
    Cycle,
    InvalidPermissionKey,
    InvalidJobKind,
    PermissionOwnerMismatch,
    DuplicatePermissionKey,
    DuplicateJobKind,
}

/// Validates descriptor identities, references, permission ownership, and acyclicity.
// SPEC: MOD-03
pub fn validate_graph(descriptors: &[&ModuleDescriptor]) -> Result<(), GraphError> {
    let mut by_key = BTreeMap::new();
    for descriptor in descriptors {
        ModuleKey::parse(descriptor.key).map_err(|_| GraphError::InvalidModuleKey)?;
        if by_key.insert(descriptor.key, *descriptor).is_some() {
            return Err(GraphError::DuplicateModuleKey);
        }
    }

    let mut permissions = BTreeSet::new();
    let mut jobs = BTreeSet::new();
    for descriptor in descriptors {
        for required in descriptor.requires {
            ModuleKey::parse(required).map_err(|_| GraphError::InvalidModuleKey)?;
            let dependency = by_key.get(required).ok_or(GraphError::UnknownRequirement)?;
            if descriptor.kind == ModuleKind::Core && dependency.kind == ModuleKind::Toggleable {
                return Err(GraphError::CoreRequiresToggleable);
            }
        }
        for permission in descriptor.permissions {
            let parsed = PermissionKey::parse(permission.key)
                .map_err(|_| GraphError::InvalidPermissionKey)?;
            let owner = parsed
                .as_str()
                .split('.')
                .next()
                .ok_or(GraphError::InvalidPermissionKey)?;
            if owner != descriptor.key {
                return Err(GraphError::PermissionOwnerMismatch);
            }
            if !permissions.insert(permission.key) {
                return Err(GraphError::DuplicatePermissionKey);
            }
        }
        for job in descriptor.jobs {
            if job.is_empty()
                || !job.is_ascii()
                || !job.bytes().all(|byte| {
                    byte.is_ascii_lowercase()
                        || byte.is_ascii_digit()
                        || matches!(byte, b'_' | b'.')
                })
            {
                return Err(GraphError::InvalidJobKind);
            }
            if !jobs.insert(*job) {
                return Err(GraphError::DuplicateJobKind);
            }
        }
    }

    let mut visiting = BTreeSet::new();
    let mut visited = BTreeSet::new();
    for descriptor in descriptors {
        visit(descriptor.key, &by_key, &mut visiting, &mut visited)?;
    }
    Ok(())
}

fn visit<'a>(
    key: &'a str,
    by_key: &BTreeMap<&'a str, &'a ModuleDescriptor>,
    visiting: &mut BTreeSet<&'a str>,
    visited: &mut BTreeSet<&'a str>,
) -> Result<(), GraphError> {
    if visited.contains(key) {
        return Ok(());
    }
    if !visiting.insert(key) {
        return Err(GraphError::Cycle);
    }
    let descriptor = by_key.get(key).ok_or(GraphError::UnknownRequirement)?;
    for required in descriptor.requires {
        visit(required, by_key, visiting, visited)?;
    }
    visiting.remove(key);
    visited.insert(key);
    Ok(())
}

/// Whether a switch request changes the enabled state.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum SwitchOutcome {
    /// Requested state differs from the current state.
    Changed,
    /// Module already has the requested state.
    Unchanged,
}

/// Plans one switch without consulting storage or mutating the enabled set.
// SPEC: MOD-03, MOD-04
pub fn plan_switch<K, Q>(
    descriptors: &[&ModuleDescriptor],
    enabled: &BTreeSet<K>,
    key: &Q,
    want_enabled: bool,
) -> Result<SwitchOutcome, AppError>
where
    K: Borrow<str> + Ord,
    Q: Borrow<str> + ?Sized,
{
    let key = key.borrow();
    let descriptor = descriptors
        .iter()
        .find(|descriptor| descriptor.key == key)
        .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;

    if want_enabled {
        if enabled.contains(key) {
            return Ok(SwitchOutcome::Unchanged);
        }
        let missing = descriptor
            .requires
            .iter()
            .filter(|required| !enabled.contains(**required))
            .map(|required| (*required).to_owned())
            .collect::<Vec<_>>();
        if !missing.is_empty() {
            return Err(AppError::new(ErrorCode::ModuleDependencyUnmet)
                .with_detail("missing", DetailValue::StrList(missing)));
        }
        return Ok(SwitchOutcome::Changed);
    }

    if descriptor.kind == ModuleKind::Core {
        return Err(AppError::new(ErrorCode::ModuleCoreLocked));
    }
    let mut dependents = descriptors
        .iter()
        .filter(|candidate| enabled.contains(candidate.key) && candidate.requires.contains(&key))
        .map(|candidate| candidate.key.to_owned())
        .collect::<Vec<_>>();
    dependents.sort();
    if !dependents.is_empty() {
        return Err(AppError::new(ErrorCode::ModuleRequiredBy)
            .with_detail("dependents", DetailValue::StrList(dependents)));
    }
    if !enabled.contains(key) {
        return Ok(SwitchOutcome::Unchanged);
    }
    Ok(SwitchOutcome::Changed)
}

/// Non-sensitive account data passed to compiled-module provisioners.
#[derive(Clone, Eq, PartialEq)]
pub struct ProvisionedAccount {
    /// Generated account identifier.
    pub id: AccountId,
    /// Display name used to initialize module-owned rows.
    pub display_name: String,
    /// Validated account timezone.
    pub timezone: IanaTz,
    /// Supported account locale.
    pub locale: Locale,
    /// Whether this account is the first instance administrator.
    pub is_instance_admin: bool,
}

/// Named section contributed to the account export document.
#[derive(Clone, Debug, PartialEq)]
pub struct ExportSection {
    /// Unique section name in `export_v1`.
    pub name: &'static str,
    /// Rows encoded according to the section export schema.
    pub rows: serde_json::Value,
}

#[async_trait]
// SPEC: MOD-10, ADR-0011
pub trait AccountProvisioner<T: Send>: Send + Sync {
    /// Returns the owning module key.
    fn module(&self) -> &'static str;

    /// Creates module-owned rows inside the caller's transaction.
    async fn provision(&self, tx: &mut T, account: &ProvisionedAccount) -> Result<(), AppError>;
}

#[async_trait]
// SPEC: MOD-11
pub trait AccountExporter<T: Send>: Send + Sync {
    /// Returns all section names this exporter may contribute.
    fn sections(&self) -> &'static [&'static str];

    /// Reads this account's export data through the caller's transaction.
    async fn export(&self, tx: &mut T, account: AccountId) -> Result<Vec<ExportSection>, AppError>;
}

/// Rejects duplicate section claims before an export job starts.
// SPEC: MOD-11
pub fn check_exporters<T: Send>(exporters: &[&dyn AccountExporter<T>]) -> Result<(), AppError> {
    let mut sections = BTreeSet::new();
    for exporter in exporters {
        for section in exporter.sections() {
            if !sections.insert(*section) {
                return Err(AppError::new(ErrorCode::InternalError)
                    .with_detail("name", DetailValue::Str(section)));
            }
        }
    }
    Ok(())
}

/// Runs account provisioners in the caller-supplied dependency order and stops on failure.
// SPEC: MOD-10, ADR-0011
pub async fn run_provisioners<T: Send>(
    order: &[&dyn AccountProvisioner<T>],
    tx: &mut T,
    account: &ProvisionedAccount,
) -> Result<(), AppError> {
    for provisioner in order {
        provisioner.provision(tx, account).await?;
    }
    Ok(())
}

#[cfg(test)]
mod tests;
