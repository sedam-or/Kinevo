//! Instance administration module.

#![forbid(unsafe_code)]

pub mod app;
pub mod domain;
pub mod http;
pub mod infra;
pub mod jobs;
pub mod ports;

use std::sync::Arc;

use app::{
    modules::ModuleService, policies::PolicyService, secrets::SecretsService, setup::SetupService,
};
use kinevo_db::{Tx, jobs::JobHandler, migrate::MigrationSource, outbox::Subscriber};
use kinevo_http::route::RouteTable;
use kinevo_ports::module::{
    AccountExporter, AccountProvisioner, JobSchedule, ModuleDescriptor, ModuleKind, PermissionDef,
};
use kinevo_ports::platform::PolicyReader;

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
    "jobs.schedule.daily_seconds",
    "jobs.schedule.hourly_seconds",
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

const JOB_SCHEDULES: &[JobSchedule] = &[
    JobSchedule {
        kind: "admin.audit.prune",
        cadence_policy_key: "jobs.schedule.daily_seconds",
    },
    JobSchedule {
        kind: "admin.idempotency.prune",
        cadence_policy_key: "jobs.schedule.hourly_seconds",
    },
    JobSchedule {
        kind: "jobs.prune",
        cadence_policy_key: "jobs.schedule.daily_seconds",
    },
    JobSchedule {
        kind: "outbox.prune",
        cadence_policy_key: "jobs.schedule.daily_seconds",
    },
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
    job_schedules: JOB_SCHEDULES,
    subscriptions: &["ModuleSwitched", "PolicyChanged"],
};

/// Returns the admin secret routes assembled for the application.
#[must_use]
pub fn router(secrets: Arc<SecretsService>) -> RouteTable {
    http::secrets::routes(secrets)
}

/// Returns all current admin routes for application assembly.
#[must_use]
pub fn router_with_policies(
    secrets: Arc<SecretsService>,
    policies: Arc<PolicyService>,
) -> RouteTable {
    http::secrets::routes(secrets).merge(http::policies::routes(policies))
}

/// Returns all current admin routes, including modules and instance summary.
#[must_use]
pub fn router_with_modules(
    secrets: Arc<SecretsService>,
    policies: Arc<PolicyService>,
    modules: Arc<ModuleService>,
) -> RouteTable {
    router_with_policies(secrets, policies)
        .merge(http::modules::routes(Arc::clone(&modules)))
        .merge(http::instance::routes(modules))
}

/// Returns current admin routes together with public first-run setup routes.
#[must_use]
pub fn router_with_setup(
    secrets: Arc<SecretsService>,
    policies: Arc<PolicyService>,
    modules: Arc<ModuleService>,
    setup: Arc<SetupService>,
) -> RouteTable {
    router_with_modules(secrets, policies, modules).merge(http::setup::routes(setup))
}

/// Returns all current admin routes, including audit and job operations.
#[must_use]
pub fn router_with_audit_jobs(
    secrets: Arc<SecretsService>,
    policies: Arc<PolicyService>,
    modules: Arc<ModuleService>,
    setup: Arc<SetupService>,
    audit: Arc<app::audit::AuditService>,
    jobs: Arc<app::jobs_admin::JobsAdminService>,
) -> RouteTable {
    router_with_setup(secrets, policies, modules, setup)
        .merge(http::audit::routes(audit))
        .merge(http::jobs::routes(jobs))
}

/// Returns admin job handlers once their use cases are implemented.
#[must_use]
pub fn jobs(policies: Arc<dyn PolicyReader>) -> Vec<Arc<dyn JobHandler>> {
    crate::jobs::handlers(policies)
}

/// Returns admin outbox subscribers bound to their process-local services.
#[must_use]
pub fn subscribers(policies: &PolicyService) -> Vec<Arc<dyn Subscriber>> {
    vec![policies.cache_subscriber()]
}

/// Returns policy and module-cache subscribers for application event dispatch.
#[must_use]
pub fn subscribers_with_modules(
    policies: &PolicyService,
    modules: &ModuleService,
) -> Vec<Arc<dyn Subscriber>> {
    vec![policies.cache_subscriber(), modules.cache_subscriber()]
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
