//! Instance administration module.

#![forbid(unsafe_code)]

pub mod app;
pub mod domain;
pub mod http;
pub mod infra;
pub mod ports;

use std::sync::Arc;

use kinevo_db::{Tx, jobs::JobHandler, migrate::MigrationSource};
use kinevo_http::route::RouteTable;
use kinevo_ports::module::{
    AccountExporter, AccountProvisioner, ModuleDescriptor, ModuleKind, PermissionDef,
};

static MIGRATOR: sqlx::migrate::Migrator = sqlx::migrate!("./migrations");

const PERMISSIONS: &[PermissionDef] = &[
    PermissionDef {
        key: "admin.policies.account.read",
        owner: true,
    },
    PermissionDef {
        key: "admin.policies.account.write",
        owner: true,
    },
    PermissionDef {
        key: "admin.policies.instance.read",
        owner: true,
    },
    PermissionDef {
        key: "admin.instance.read",
        owner: true,
    },
    PermissionDef {
        key: "admin.audit.read_own",
        owner: true,
    },
    PermissionDef {
        key: "admin.policies.instance.write",
        owner: false,
    },
    PermissionDef {
        key: "admin.modules.manage",
        owner: false,
    },
    PermissionDef {
        key: "admin.audit.read",
        owner: false,
    },
    PermissionDef {
        key: "admin.jobs.read",
        owner: false,
    },
    PermissionDef {
        key: "admin.jobs.retry",
        owner: false,
    },
    PermissionDef {
        key: "admin.secrets.manage",
        owner: false,
    },
];

const POLICY_KEYS: &[&str] = &[
    "audit.retention_days",
    "api.idempotency.ttl",
    "modules.cache_ttl_seconds",
    "jobs.lease_seconds",
    "jobs.retry.max_attempts",
    "jobs.retry.base_seconds",
    "jobs.retry.cap_seconds",
    "ratelimit.auth_per_minute",
    "ratelimit.api_per_minute",
    "ratelimit.ai_per_minute",
    "ratelimit.upload_per_minute",
    "ratelimit.sync_per_minute",
    "ratelimit.export_per_minute",
];

const JOBS: &[&str] = &[
    "admin.audit.prune",
    "admin.idempotency.prune",
    "jobs.prune",
    "outbox.prune",
    "outbox.dispatch",
    "outbox.retry",
];

/// Static metadata for the instance administration module.
pub const MODULE: ModuleDescriptor = ModuleDescriptor {
    key: "admin",
    kind: ModuleKind::Core,
    requires: &["identity"],
    permissions: PERMISSIONS,
    policy_keys: POLICY_KEYS,
    nav: &[],
    settings_panels: &[],
    message_namespaces: &[],
    jobs: JOBS,
    job_schedules: &[],
    subscriptions: &[],
};

/// Returns an empty route table until admin endpoints are implemented.
#[must_use]
pub fn router() -> RouteTable {
    RouteTable::new()
}

/// Returns admin job handlers once their use cases are implemented.
#[must_use]
pub fn jobs() -> Vec<Arc<dyn JobHandler>> {
    Vec::new()
}

/// Returns subscribers once admin events are consumed.
#[must_use]
pub fn subscribers() -> Vec<()> {
    Vec::new()
}

/// Returns account provisioners once admin account provisioning is implemented.
#[must_use]
pub fn provisioners() -> Vec<Arc<dyn AccountProvisioner<Tx>>> {
    Vec::new()
}

/// Returns account exporters once admin exports are implemented.
#[must_use]
pub fn exporters() -> Vec<Arc<dyn AccountExporter<Tx>>> {
    Vec::new()
}

/// Returns the admin crate's embedded migrations.
#[must_use]
pub fn migrations() -> MigrationSource {
    MigrationSource {
        crate_name: "kinevo-mod-admin",
        module: "admin",
        migrator: &MIGRATOR,
    }
}
