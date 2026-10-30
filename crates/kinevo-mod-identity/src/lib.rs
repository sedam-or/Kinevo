//! Identity module for Nivaras.

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
    AccountExporter, AccountProvisioner, ModuleDescriptor, ModuleKind, NavItem, PermissionDef,
    SettingsPanel,
};
use kinevo_ports::platform::PolicyReader;

pub use infra::mail::smtp_mailer;

static MIGRATOR: sqlx::migrate::Migrator = sqlx::migrate!("./migrations");

const PERMISSIONS: &[PermissionDef] = &[
    PermissionDef {
        key: "identity.session.manage",
        owner: true,
    },
    PermissionDef {
        key: "identity.account.read",
        owner: true,
    },
    PermissionDef {
        key: "identity.account.update",
        owner: true,
    },
    PermissionDef {
        key: "identity.account.delete",
        owner: true,
    },
    PermissionDef {
        key: "identity.account.export",
        owner: true,
    },
    PermissionDef {
        key: "identity.totp.manage",
        owner: true,
    },
    PermissionDef {
        key: "identity.admin.accounts.read",
        owner: false,
    },
    PermissionDef {
        key: "identity.admin.accounts.update",
        owner: false,
    },
    PermissionDef {
        key: "identity.admin.invite.create",
        owner: false,
    },
    PermissionDef {
        key: "identity.admin.password_reset.issue",
        owner: false,
    },
];

const POLICY_KEYS: &[&str] = &[
    "auth.registration.mode",
    "auth.password.min_length",
    "auth.access_token.ttl",
    "auth.refresh_token.absolute_ttl",
    "auth.refresh_token.idle_ttl",
    "auth.lockout.max_attempts",
    "auth.lockout.window",
    "auth.lockout.duration",
    "auth.totp.required",
    "auth.refresh.grace",
    "identity.purge.delay",
    "auth.invite.ttl",
    "auth.password_reset.ttl",
    "export.max_age_days",
];

const JOBS: &[&str] = &[
    "account.purge",
    "account.export.build",
    "identity.sessions.prune",
    "identity.resets.prune",
    "identity.exports.prune",
];

const NAV: &[NavItem] = &[NavItem {
    key: "accounts",
    label_key: "nav.admin.accounts",
    route: "/admin/accounts",
    order: 1,
    required_permission: "identity.admin.accounts.read",
}];

const SETTINGS_PANELS: &[SettingsPanel] = &[
    SettingsPanel {
        key: "profile",
        label_key: "settings.profile",
        required_permission: "identity.account.read",
    },
    SettingsPanel {
        key: "security",
        label_key: "settings.security",
        required_permission: "identity.session.manage",
    },
    SettingsPanel {
        key: "data",
        label_key: "settings.data",
        required_permission: "identity.account.export",
    },
];

/// Static metadata for the identity module.
pub const MODULE: ModuleDescriptor = ModuleDescriptor {
    key: "identity",
    kind: ModuleKind::Core,
    requires: &[],
    permissions: PERMISSIONS,
    policy_keys: POLICY_KEYS,
    nav: NAV,
    settings_panels: SETTINGS_PANELS,
    message_namespaces: &["identity"],
    jobs: JOBS,
    job_schedules: &[],
    subscriptions: &[],
};

/// Builds the identity public authentication routes.
#[must_use]
pub fn router(service: Arc<app::login::LoginService>) -> RouteTable {
    let (spec, route) = http::login::route(service);
    RouteTable::new().route(spec, route)
}

/// Builds the public registration route for module composition.
#[must_use]
pub fn register_router(service: Arc<app::register::RegisterService>) -> RouteTable {
    let (spec, route) = http::register::route(service);
    RouteTable::new().route(spec, route)
}

/// Builds the login, refresh and revocation routes for module composition.
#[must_use]
pub fn router_with_refresh(
    login: Arc<app::login::LoginService>,
    refresh: Arc<app::refresh::RefreshService>,
    policies: Arc<dyn PolicyReader>,
) -> RouteTable {
    let (spec, route) = http::login::route(login);
    let table = RouteTable::new().route(spec, route);
    http::refresh::routes(refresh, policies)
        .into_iter()
        .fold(table, |table, (spec, route)| table.route(spec, route))
}

/// Builds only the refresh and revocation routes for focused HTTP adapters.
#[must_use]
pub fn refresh_router(
    refresh: Arc<app::refresh::RefreshService>,
    policies: Arc<dyn PolicyReader>,
) -> RouteTable {
    http::refresh::route_table(refresh, policies)
}

/// Builds the authenticated profile, password and session management routes.
#[must_use]
pub fn account_router(
    profiles: Arc<app::profile::ProfileService>,
    passwords: Arc<app::password::PasswordService>,
    sessions: Arc<app::sessions::SessionService>,
) -> RouteTable {
    http::account::routes(profiles, passwords, sessions)
}

/// Builds the authenticated TOTP enrolment, confirmation and disable routes.
#[must_use]
pub fn totp_router(service: Arc<app::totp::TotpService>) -> RouteTable {
    http::totp::routes(service)
}

/// Builds the administrator account list and status-change routes.
#[must_use]
pub fn admin_accounts_router(
    service: Arc<app::admin_accounts::AdminAccountsService>,
) -> RouteTable {
    http::admin::accounts(service)
}

/// Builds the administrator invite-creation route.
#[must_use]
pub fn admin_invites_router(service: Arc<app::invites::InviteService>) -> RouteTable {
    http::admin::invites(service)
}

/// Builds the administrator-issued password-reset route.
#[must_use]
pub fn admin_reset_router(service: Arc<app::reset::ResetService>) -> RouteTable {
    http::admin::issue_reset(service)
}

/// Builds the public forgot-password request and confirmation routes.
#[must_use]
pub fn reset_router(service: Arc<app::reset::ResetService>) -> RouteTable {
    http::reset::routes(service)
}

/// Builds the authenticated self-service account-deletion route.
#[must_use]
pub fn deletion_router(service: Arc<app::deletion::DeletionService>) -> RouteTable {
    http::deletion::route(service)
}

/// Builds the authenticated account-export request, status and download routes.
#[must_use]
pub fn export_router(service: Arc<app::export::ExportService>) -> RouteTable {
    http::export::routes(service)
}

/// Returns the identity crate's embedded migrations.
#[must_use]
pub fn migrations() -> MigrationSource {
    MigrationSource {
        crate_name: "kinevo-mod-identity",
        module: "identity",
        migrator: &MIGRATOR,
    }
}

/// Returns the module's job handlers; handlers are added with their owning use cases.
#[must_use]
pub fn jobs() -> Vec<Arc<dyn JobHandler>> {
    app::jobs::handlers()
}

/// Returns identity jobs with an export builder configured for compiled exporters.
#[must_use]
pub fn jobs_with_exports(service: Arc<app::export::ExportService>) -> Vec<Arc<dyn JobHandler>> {
    app::jobs::handlers_with_exports(service)
}

/// Returns the module's outbox subscribers; subscribers are added with their owning use cases.
#[must_use]
pub fn subscribers() -> Vec<()> {
    Vec::new()
}

/// Returns the account provisioners implemented by this module.
#[must_use]
pub fn provisioners() -> Vec<Arc<dyn AccountProvisioner<Tx>>> {
    Vec::new()
}

/// Returns the account exporters implemented by this module.
#[must_use]
pub fn exporters() -> Vec<Arc<dyn AccountExporter<Tx>>> {
    vec![Arc::new(app::export::IdentityExporter)]
}
