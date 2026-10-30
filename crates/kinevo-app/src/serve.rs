//! Application startup sequence and graceful HTTP shutdown.

use axum::Router;
use kinevo_db::{
    Db, DbConfig, TokioSleeper, Tx,
    isolation::verify_isolation,
    migrate::{self, MigrationSource},
};
use kinevo_http::{
    auth::AccountCache,
    gate::{Gate, ModuleKinds},
    i18n::Catalog,
    origin::OriginConfig,
    ratelimit::{RateLimiter, TrustedProxies},
    rbac::PermissionTable,
    state::{HttpState, ReadyCheck, ReadyFailure},
    token::{KeyRing, TokenCodec},
};
use kinevo_kernel::{
    crypto::{KeyLabel, derive_subkey},
    id::{IdGen, TokenGen},
    page::CursorCodec,
    policy::{EffectiveDating, PolicySpec, PolicyType, PolicyValue, Scope, registry},
    time::Clock,
};
use kinevo_mod_admin::{
    MODULE as ADMIN,
    app::{
        audit::AuditService,
        finish_setup::{FinishSetup, SetupBootstrap},
        jobs_admin::JobsAdminService,
        modules::ModuleService,
        policies::PolicyService,
        secrets::SecretsService,
        setup::SetupService,
        templates::TemplateRegistry,
    },
    domain::template::ValidationCtx,
    infra::{
        instance_state::CachedInstanceState, secret_store::AdminSecretStore,
        wizard_store::WizardStore,
    },
};
use kinevo_mod_identity::MODULE as IDENTITY;
use kinevo_mod_identity::{
    app::{
        accounts::AccountCreator,
        admin_accounts::AdminAccountsService,
        deletion::DeletionService,
        export::ExportService,
        invites::InviteService,
        login::{HasherPasswordWork, LoginService, PasswordWork},
        password::PasswordService,
        profile::ProfileService,
        refresh::RefreshService,
        reset::ResetService,
        sessions::SessionService,
        totp::{ReplayCache, TotpMfaVerifier, TotpService},
    },
    infra::{
        hasher::{Hasher, HasherConfig},
        mail::SmtpConfig as IdentitySmtpConfig,
    },
};
use kinevo_ports::{
    module::{ModuleDescriptor, ModuleKind, PermissionDef, validate_graph},
    platform::{AccountCreation, InstanceState, Mailer, ModuleSwitches, NoMailer, SecretStore},
};
use sqlx::ConnectOptions;
use std::sync::OnceLock;
use std::{error::Error, fmt, future::Future, sync::Arc, time::Duration};
use tokio::{net::TcpListener, sync::watch, time::timeout};

use crate::{config::Config, health};

const SHUTDOWN_TIMEOUT: Duration = Duration::from_secs(30);
pub(crate) const ACTIVE_SECRET_KEY_VERSION: u8 = 1;

const EMPTY_PERMISSIONS: &[PermissionDef] = &[];
const EMPTY_KEYS: &[&str] = &[];
const BOTH_SCOPES: &[Scope] = &[Scope::Instance, Scope::Account];
pub(crate) const TERMS: &[&str] = &[
    "term.goal",
    "term.milestone",
    "term.task",
    "term.workspace",
    "term.hard_landscape",
    "term.focus_session",
];

const fn template_module(
    key: &'static str,
    kind: ModuleKind,
    requires: &'static [&'static str],
) -> ModuleDescriptor {
    ModuleDescriptor {
        key,
        kind,
        requires,
        permissions: EMPTY_PERMISSIONS,
        policy_keys: EMPTY_KEYS,
        nav: &[],
        settings_panels: &[],
        message_namespaces: &[],
        jobs: &[],
        job_schedules: &[],
        subscriptions: &[],
    }
}

static TEMPLATE_WORKSPACES: ModuleDescriptor =
    template_module("workspaces", ModuleKind::Core, &["identity"]);
static TEMPLATE_GOALS: ModuleDescriptor =
    template_module("goals", ModuleKind::Toggleable, &["workspaces"]);
static TEMPLATE_TASKS: ModuleDescriptor = template_module("tasks", ModuleKind::Core, &[]);
static TEMPLATE_LANDSCAPE: ModuleDescriptor =
    template_module("landscape", ModuleKind::Core, &["identity"]);
static TEMPLATE_SCHEDULER: ModuleDescriptor =
    template_module("scheduler", ModuleKind::Core, &["tasks", "landscape"]);
static TEMPLATE_EXECUTION: ModuleDescriptor =
    template_module("execution", ModuleKind::Core, &["tasks", "scheduler"]);
static TEMPLATE_SYNC: ModuleDescriptor =
    template_module("sync", ModuleKind::Toggleable, &["tasks"]);
static TEMPLATE_AI: ModuleDescriptor =
    template_module("ai", ModuleKind::Toggleable, &["goals", "tasks"]);
static TEMPLATE_KNOWLEDGE: ModuleDescriptor =
    template_module("knowledge", ModuleKind::Toggleable, &["workspaces"]);
static TEMPLATE_CANVAS: ModuleDescriptor =
    template_module("canvas", ModuleKind::Toggleable, &["workspaces"]);
static TEMPLATE_BILLING: ModuleDescriptor =
    template_module("billing", ModuleKind::Toggleable, &["identity"]);

pub(crate) static TEMPLATE_MODULES: &[&ModuleDescriptor] = &[
    &IDENTITY,
    &TEMPLATE_WORKSPACES,
    &ADMIN,
    &TEMPLATE_GOALS,
    &TEMPLATE_TASKS,
    &TEMPLATE_LANDSCAPE,
    &TEMPLATE_SCHEDULER,
    &TEMPLATE_EXECUTION,
    &TEMPLATE_SYNC,
    &TEMPLATE_AI,
    &TEMPLATE_KNOWLEDGE,
    &TEMPLATE_CANVAS,
    &TEMPLATE_BILLING,
];

static SCHEDULER_DAY_START: PolicySpec = PolicySpec {
    key: "scheduler.day_start",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(480),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static SCHEDULER_DAY_END: PolicySpec = PolicySpec {
    key: "scheduler.day_end",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(1_320),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static SCHEDULER_EARLIEST: PolicySpec = PolicySpec {
    key: "scheduler.anchor.earliest_time",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(360),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};

pub(crate) fn template_policies() -> &'static [&'static PolicySpec] {
    static POLICIES: OnceLock<Vec<&'static PolicySpec>> = OnceLock::new();
    POLICIES
        .get_or_init(|| {
            let mut policies = registry::all_specs().to_vec();
            policies.extend([
                &SCHEDULER_DAY_START,
                &SCHEDULER_DAY_END,
                &SCHEDULER_EARLIEST,
            ]);
            policies
        })
        .as_slice()
}

/// Safe startup error code exposed to the CLI and health checks.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct StartupError {
    code: &'static str,
}

impl StartupError {
    fn new(code: &'static str) -> Self {
        Self { code }
    }

    /// Stable process error code without connection strings or other secret values.
    #[must_use]
    pub const fn code(&self) -> &'static str {
        self.code
    }
}

impl fmt::Display for StartupError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str(self.code)
    }
}

impl Error for StartupError {}

/// A validated router and the database migration state observed during startup.
pub struct PreparedApp {
    /// HTTP routes ready to serve.
    pub router: Router,
    /// Route descriptors for the validated application router and public health routes.
    pub route_specs: Vec<kinevo_http::route::RouteSpec>,
    /// Whether every compiled migration is applied.
    pub migrations_current: bool,
    db: Db,
}

struct SetupRuntime {
    setup: Arc<SetupService>,
    finish: Arc<FinishSetup>,
}

impl fmt::Debug for PreparedApp {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter
            .debug_struct("PreparedApp")
            .field("migrations_current", &self.migrations_current)
            .field("route_count", &self.route_specs.len())
            .finish_non_exhaustive()
    }
}

impl PreparedApp {
    /// Keeps the pools alive for the lifetime of the server.
    #[must_use]
    pub fn database(&self) -> &Db {
        &self.db
    }
}

/// Connects pools, applies optional migrations and verifies startup invariants.
#[allow(
    clippy::too_many_lines,
    reason = "startup stages are kept in their required order at the composition root"
)]
pub async fn prepare(cfg: Config, clock: Arc<dyn Clock>) -> Result<PreparedApp, StartupError> {
    let setup = cfg.setup;
    let public_url = cfg.public_url;
    let secret_key = cfg.secret_key;
    let trusted_proxies = cfg.trusted_proxies;
    let android_origin = cfg.android_origin;
    let max_body_bytes = cfg.max_body_bytes;
    let smtp = cfg.smtp;
    let ids: Arc<dyn IdGen> = Arc::new(crate::runtime::UuidV7Gen);
    let tokens: Arc<dyn TokenGen> = Arc::new(crate::runtime::OsTokenGen);
    let db = Db::connect(
        &DbConfig {
            app_url: cfg.database_url,
            system_url: cfg.system_database_url,
            pool_max: cfg.pool_max,
            retries: cfg.db_connect_retries,
            backoff: cfg.db_connect_backoff,
        },
        &TokioSleeper,
    )
    .await
    .map_err(|_| StartupError::new("database_connect_failed"))?;

    let sources = migration_sources();
    if cfg.auto_migrate
        && let Some(url) = cfg.migrator_database_url.as_ref()
    {
        migrate::run(url, &sources)
            .await
            .map_err(|_| StartupError::new("migration_failed"))?;
    }

    let migrations_current = if let Some(url) = cfg.migrator_database_url.as_ref() {
        let options = url
            .expose()
            .parse::<sqlx::postgres::PgConnectOptions>()
            .map_err(|_| StartupError::new("migration_status_failed"))?
            .disable_statement_logging();
        let pool = sqlx::postgres::PgPoolOptions::new()
            .max_connections(1)
            .connect_with(options)
            .await
            .map_err(|_| StartupError::new("migration_status_failed"))?;
        let status = migrate::status(&pool, &sources)
            .await
            .map_err(|_| StartupError::new("migration_status_failed"));
        pool.close().await;
        status?.up_to_date
    } else {
        false
    };
    let isolation_verified = if migrations_current {
        verify_isolation(db.app_pool_for_health())
            .await
            .map_err(|_| StartupError::new("isolation_check_failed"))?;
        true
    } else {
        false
    };

    let descriptors = [&IDENTITY, &ADMIN];
    validate_graph(&descriptors).map_err(|_| StartupError::new("config_invalid"))?;
    PermissionTable::from_descriptors(&descriptors)
        .map_err(|_| StartupError::new("config_invalid"))?;
    ModuleKinds::from_descriptors(&descriptors).map_err(|_| StartupError::new("config_invalid"))?;

    let codec = token_codec(&secret_key)?;
    let mut route_specs;
    let router = if migrations_current {
        let setup = configure_setup(
            &db,
            &clock,
            &public_url,
            setup,
            Arc::clone(&codec),
            Arc::clone(&ids),
            Arc::clone(&tokens),
        )
        .await?;
        let (module_router, module_specs) = module_router(
            &db,
            &clock,
            public_url,
            &secret_key,
            &codec,
            &ids,
            &tokens,
            &setup,
            &trusted_proxies,
            android_origin,
            max_body_bytes,
            smtp,
            isolation_verified,
        )?;
        route_specs = module_specs;
        module_router.merge(health::router(
            db.app_pool_for_health().clone(),
            clock,
            migrations_current,
            isolation_verified,
        ))
    } else {
        route_specs = Vec::new();
        health::router(
            db.app_pool_for_health().clone(),
            clock,
            migrations_current,
            isolation_verified,
        )
    };
    route_specs.extend(crate::openapi::active_health_route_specs());
    let router = crate::web_assets::with_fallback(router, true);
    Ok(PreparedApp {
        router,
        route_specs,
        migrations_current,
        db,
    })
}

async fn configure_setup(
    db: &Db,
    clock: &Arc<dyn Clock>,
    public_url: &kinevo_kernel::validate::net::HttpsUrl,
    mut setup: crate::config::SetupBootstrap,
    codec: Arc<TokenCodec>,
    ids: Arc<dyn IdGen>,
    tokens: Arc<dyn TokenGen>,
) -> Result<SetupRuntime, StartupError> {
    let policies = template_policies();
    let context = ValidationCtx::new(TEMPLATE_MODULES, policies, TERMS);
    let templates = Arc::new(
        TemplateRegistry::embedded(&context).map_err(|_| StartupError::new("config_invalid"))?,
    );
    let instance_cache_ttl = default_policy_seconds("modules.cache_ttl_seconds")
        .ok_or_else(|| StartupError::new("config_invalid"))?;
    let instance = Arc::new(CachedInstanceState::new(
        db.clone(),
        Arc::clone(clock),
        instance_cache_ttl,
    ));
    let password_processor = Arc::new(
        crate::runtime::SetupPasswords::new(Arc::clone(&tokens), Arc::clone(clock))
            .map_err(|_| StartupError::new("config_invalid"))?,
    );
    let accounts: Arc<dyn AccountCreation<Tx>> = Arc::new(AccountCreator::new(
        Arc::clone(&ids),
        Arc::clone(&tokens),
        kinevo_mod_identity::provisioners(),
        kinevo_db::outbox::SubscriberRegistry::default(),
    ));
    let finish = Arc::new(FinishSetup::new(
        db.clone(),
        password_processor,
        accounts,
        codec,
        Arc::clone(&ids),
        Arc::clone(clock),
        Arc::clone(&instance),
        Arc::clone(&templates),
        TEMPLATE_MODULES,
        policies,
        kinevo_db::outbox::SubscriberRegistry::default(),
    ));
    let session_ttl = i64::try_from(setup.session_ttl.as_secs())
        .map_err(|_| StartupError::new("config_invalid"))?;
    let lockout_seconds = default_policy_seconds("auth.lockout.duration")
        .ok_or_else(|| StartupError::new("config_invalid"))?;
    let setup_service = Arc::new(SetupService::new(
        db.clone(),
        WizardStore::new(session_ttl, lockout_seconds, Arc::clone(clock)),
        Arc::clone(&tokens),
        Arc::clone(&ids),
        Arc::clone(clock),
        Arc::clone(&instance),
        Arc::clone(&templates),
        TEMPLATE_MODULES,
        policies,
        public_url.as_str().to_owned(),
    ));
    let setup_completed = instance
        .get()
        .await
        .map_err(|_| StartupError::new("setup_state_failed"))?
        .setup_completed;

    match (setup.email.take(), setup.password.take()) {
        (Some(email), Some(password)) => {
            if !setup_completed {
                finish
                    .bootstrap_from_env(SetupBootstrap {
                        email: email.as_str().to_owned(),
                        password,
                        template_id: Some(setup.template),
                    })
                    .await
                    .map_err(|_| StartupError::new("setup_bootstrap_failed"))?;
            }
        }
        (None, None) => {
            setup_service
                .ensure_token()
                .await
                .map_err(|_| StartupError::new("setup_token_failed"))?;
        }
        _ => return Err(StartupError::new("config_invalid")),
    }
    Ok(SetupRuntime {
        setup: setup_service,
        finish,
    })
}

#[derive(Clone)]
struct StartupReadiness {
    pool: sqlx::PgPool,
    migrations_current: bool,
    isolation_verified: bool,
}

#[async_trait::async_trait]
impl ReadyCheck for StartupReadiness {
    async fn check(&self) -> Result<(), ReadyFailure> {
        if !self.migrations_current || !self.isolation_verified {
            return Err(ReadyFailure);
        }
        sqlx::query("SELECT 1")
            .execute(&self.pool)
            .await
            .map_err(|_| ReadyFailure)?;
        Ok(())
    }
}

#[allow(
    clippy::too_many_arguments,
    clippy::too_many_lines,
    reason = "the composition root wires each module adapter explicitly"
)]
fn module_router(
    db: &Db,
    clock: &Arc<dyn Clock>,
    public_url: kinevo_kernel::validate::net::HttpsUrl,
    secret_key: &kinevo_kernel::crypto::MasterKey,
    codec: &Arc<TokenCodec>,
    ids: &Arc<dyn IdGen>,
    tokens: &Arc<dyn TokenGen>,
    setup: &SetupRuntime,
    trusted_proxies: &[crate::config::Cidr],
    android_origin: bool,
    max_body_bytes: usize,
    smtp: Option<crate::config::SmtpConfig>,
    isolation_verified: bool,
) -> Result<(Router, Vec<kinevo_http::route::RouteSpec>), StartupError> {
    let descriptors = [&IDENTITY, &ADMIN];
    let catalog = Arc::new(Catalog::load());
    let policies = Arc::new(PolicyService::new(
        db.clone(),
        Arc::clone(ids),
        Arc::clone(clock),
        kinevo_db::outbox::SubscriberRegistry::default(),
    ));
    let policy_reader = policies.reader();
    let modules = Arc::new(ModuleService::new(
        db.clone(),
        descriptors.to_vec(),
        Arc::clone(ids),
        Arc::clone(clock),
        Arc::clone(&policy_reader),
        kinevo_db::outbox::SubscriberRegistry::default(),
    ));
    let secret_store = Arc::new(AdminSecretStore::new(
        db.clone(),
        derive_subkey(secret_key, KeyLabel::Secrets, ACTIVE_SECRET_KEY_VERSION),
        u16::from(ACTIVE_SECRET_KEY_VERSION),
        Arc::clone(ids),
        Arc::clone(tokens),
        Arc::clone(clock),
    ));
    let secret_store_port: Arc<dyn SecretStore> = Arc::clone(&secret_store) as _;
    let instance: Arc<dyn InstanceState> = setup.setup.instance_state().clone();
    let creator: Arc<dyn AccountCreation<Tx>> = Arc::new(AccountCreator::new(
        Arc::clone(ids),
        Arc::clone(tokens),
        kinevo_mod_identity::provisioners(),
        kinevo_db::outbox::SubscriberRegistry::default(),
    ));

    let concurrency = std::thread::available_parallelism().map_or(1, std::num::NonZeroUsize::get);
    let hasher = Arc::new(
        Hasher::new(
            Arc::clone(tokens),
            Arc::clone(clock),
            HasherConfig::new(concurrency),
        )
        .map_err(|_| StartupError::new("config_invalid"))?,
    );
    let passwords: Arc<dyn PasswordWork> = Arc::new(HasherPasswordWork::new(hasher));
    let mailer: Arc<dyn Mailer> = if let Some(smtp) = smtp {
        let mail_config = IdentitySmtpConfig::parse_with_password_configured(
            smtp.url.expose(),
            smtp.password.is_some(),
        )
        .map_err(|_| StartupError::new("config_invalid"))?;
        kinevo_mod_identity::smtp_mailer(mail_config, smtp.password, Arc::clone(&catalog))
    } else {
        Arc::new(NoMailer)
    };
    let replay = Arc::new(ReplayCache::new());
    let mfa = Arc::new(TotpMfaVerifier::new(
        db.clone(),
        Arc::clone(&secret_store_port),
        Arc::clone(&replay),
    ));
    let login = Arc::new(LoginService::new(
        db.clone(),
        Arc::clone(&passwords),
        mfa,
        Arc::clone(&policy_reader),
        Arc::clone(clock),
        Arc::clone(ids),
        Arc::clone(tokens),
        Arc::clone(codec),
    ));
    let refresh = Arc::new(RefreshService::new(
        db.clone(),
        Arc::clone(clock),
        Arc::clone(ids),
        Arc::clone(tokens),
        Arc::clone(codec),
    ));
    let register = Arc::new(kinevo_mod_identity::app::register::RegisterService::new(
        db.clone(),
        Arc::clone(&passwords),
        Arc::clone(&policy_reader),
        Arc::clone(&instance),
        creator,
        Arc::clone(ids),
        Arc::clone(codec),
    ));

    let mut routes =
        kinevo_mod_identity::router_with_refresh(login, refresh, Arc::clone(&policy_reader))
            .merge(kinevo_mod_identity::register_router(register));

    let profiles = Arc::new(ProfileService::new(db.clone()));
    let password_changes = Arc::new(PasswordService::new(
        db.clone(),
        Arc::clone(&passwords),
        Arc::clone(&policy_reader),
        Arc::clone(ids),
        Arc::clone(clock),
        kinevo_db::outbox::SubscriberRegistry::default(),
    ));
    let sessions = Arc::new(SessionService::new(db.clone(), Arc::clone(clock)));
    routes = routes.merge(kinevo_mod_identity::account_router(
        profiles,
        password_changes,
        sessions,
    ));
    routes = routes.merge(kinevo_mod_identity::totp_router(Arc::new(
        TotpService::new(
            db.clone(),
            Arc::clone(&secret_store_port),
            Arc::clone(tokens),
            Arc::clone(&policy_reader),
            Arc::clone(&passwords),
            Arc::clone(ids),
            Arc::clone(clock),
            Arc::clone(&replay),
        ),
    )));
    routes = routes.merge(kinevo_mod_identity::admin_accounts_router(Arc::new(
        AdminAccountsService::new(
            db.clone(),
            Arc::clone(ids),
            Arc::clone(clock),
            kinevo_db::outbox::SubscriberRegistry::default(),
            CursorCodec::new(derive_subkey(secret_key, KeyLabel::Cursor, 1)),
        ),
    )));
    routes = routes.merge(kinevo_mod_identity::admin_invites_router(Arc::new(
        InviteService::new(
            db.clone(),
            Arc::clone(ids),
            Arc::clone(tokens),
            Arc::clone(&policy_reader),
            Arc::clone(&instance),
            Arc::clone(clock),
        ),
    )));
    let reset = Arc::new(ResetService::new(
        db.clone(),
        Arc::clone(ids),
        Arc::clone(tokens),
        Arc::clone(&passwords),
        Arc::clone(&policy_reader),
        Arc::clone(&instance),
        Arc::clone(&mailer),
        Arc::clone(clock),
        kinevo_db::outbox::SubscriberRegistry::default(),
    ));
    routes = routes
        .merge(kinevo_mod_identity::admin_reset_router(Arc::clone(&reset)))
        .merge(kinevo_mod_identity::reset_router(reset));
    routes = routes.merge(kinevo_mod_identity::deletion_router(Arc::new(
        DeletionService::new(
            db.clone(),
            Arc::clone(ids),
            Arc::clone(&passwords),
            Arc::clone(&policy_reader),
            Arc::clone(clock),
            kinevo_db::outbox::SubscriberRegistry::default(),
        ),
    )));
    let exporter: Arc<dyn kinevo_ports::module::AccountExporter<Tx>> =
        Arc::new(kinevo_mod_identity::app::export::IdentityExporter);
    let exports = Arc::new(
        ExportService::new(
            db.clone(),
            Arc::clone(ids),
            Arc::clone(clock),
            Arc::clone(&policy_reader),
            vec![exporter],
        )
        .map_err(|_| StartupError::new("config_invalid"))?,
    );
    routes = routes.merge(kinevo_mod_identity::export_router(exports));

    let admin_routes = kinevo_mod_admin::router_with_finish_and_audit_jobs(
        Arc::new(SecretsService::new(
            Arc::clone(&secret_store),
            kinevo_db::outbox::SubscriberRegistry::default(),
        )),
        Arc::clone(&policies),
        Arc::clone(&modules),
        Arc::clone(&setup.setup),
        Arc::clone(&setup.finish),
        Arc::new(AuditService::new(db.clone())),
        Arc::new(JobsAdminService::new(
            db.clone(),
            Arc::clone(ids),
            Arc::clone(clock),
            Arc::clone(&policy_reader),
        )),
    );
    routes = routes.merge(admin_routes);

    let permissions = Arc::new(
        PermissionTable::from_descriptors(&descriptors)
            .map_err(|_| StartupError::new("config_invalid"))?,
    );
    let module_kinds = Arc::new(
        ModuleKinds::from_descriptors(&descriptors)
            .map_err(|_| StartupError::new("config_invalid"))?,
    );
    let switches: Arc<dyn ModuleSwitches> = Arc::clone(modules.switches()) as _;
    let gate = Arc::new(Gate::new(
        Arc::clone(codec),
        Arc::new(AccountCache::new(Arc::new(
            crate::runtime::DatabaseAccountAccess::new(db.clone()),
        ))),
        switches,
        permissions,
        module_kinds,
    ));
    let idempotency_ttl = default_policy_seconds("api.idempotency.ttl")
        .ok_or_else(|| StartupError::new("config_invalid"))?;
    let proxy_text = trusted_proxies
        .iter()
        .map(crate::config::Cidr::as_cidr)
        .collect::<Vec<_>>()
        .join(",");
    let proxy_policy =
        TrustedProxies::parse(&proxy_text).map_err(|_| StartupError::new("config_invalid"))?;
    let ready: Arc<dyn ReadyCheck> = Arc::new(StartupReadiness {
        pool: db.app_pool_for_health().clone(),
        migrations_current: true,
        isolation_verified,
    });
    let origin = OriginConfig {
        public_origin: Some(public_url.as_str().to_owned()),
        android_origin_enabled: android_origin,
    };
    let state = HttpState::new(
        Arc::clone(clock),
        Arc::clone(ids),
        Arc::clone(tokens),
        catalog,
        instance,
        ready,
        Some(public_url),
        Arc::new(CursorCodec::new(derive_subkey(
            secret_key,
            KeyLabel::Cursor,
            1,
        ))),
        max_body_bytes,
        gate,
    )
    .with_rate_limiter(Arc::new(RateLimiter::new(
        policy_reader,
        Arc::clone(clock),
        proxy_policy,
    )))
    .with_idempotency(
        Arc::new(crate::runtime::DatabaseIdempotency::new(
            db.clone(),
            Arc::clone(clock),
        )),
        idempotency_ttl,
    )
    .with_origin_config(origin);
    let specs = routes.specs().to_vec();
    let app_router = routes
        .into_router(state)
        .map_err(|_| StartupError::new("config_invalid"))?;
    Ok((app_router, specs))
}

fn token_codec(
    secret_key: &kinevo_kernel::crypto::MasterKey,
) -> Result<Arc<TokenCodec>, StartupError> {
    let signing_key = derive_subkey(secret_key, KeyLabel::Jwt, 1);
    let key_ring = KeyRing::new("v1", vec![("v1".to_owned(), signing_key)])
        .map_err(|_| StartupError::new("config_invalid"))?;
    Ok(Arc::new(TokenCodec::new(key_ring)))
}

pub(crate) fn default_policy_seconds(key: &str) -> Option<i64> {
    registry::all_specs()
        .iter()
        .find(|spec| spec.key == key)
        .and_then(|spec| match spec.default {
            PolicyValue::Seconds(seconds) => Some(seconds),
            _ => None,
        })
}

/// The module migrations are merged by version before application.
#[must_use]
pub fn migration_sources() -> Vec<MigrationSource> {
    vec![
        kinevo_db::migrations_source(),
        kinevo_mod_identity::migrations(),
        kinevo_mod_admin::migrations(),
    ]
}

/// Starts the HTTP listener and drains in-flight requests on shutdown.
pub async fn serve(
    cfg: Config,
    clock: Arc<dyn Clock>,
    shutdown: impl Future<Output = ()> + Send + 'static,
) -> Result<(), StartupError> {
    let address = cfg.listen;
    let jobs_tick = cfg.jobs_tick;
    let worker_clock = Arc::clone(&clock);
    let prepared = prepare(cfg, clock).await?;
    let listener = TcpListener::bind(address)
        .await
        .map_err(|_| StartupError::new("listen_failed"))?;
    let (shutdown_tx, shutdown_rx) = watch::channel(false);
    let shutdown_sender = shutdown_tx.clone();
    tokio::spawn(async move {
        shutdown.await;
        let _ = shutdown_sender.send(true);
    });

    let mut server = tokio::spawn(run_http_server(
        listener,
        prepared.router,
        wait_for_shutdown(shutdown_rx.clone()),
    ));
    let mut worker = tokio::spawn(crate::worker::run(
        prepared.db.clone(),
        worker_clock,
        Arc::new(crate::runtime::UuidV7Gen),
        jobs_tick,
        wait_for_shutdown(shutdown_rx.clone()),
    ));
    let shutdown_observed = shutdown_rx.clone();

    tokio::select! {
        server_result = &mut server => {
            let _ = shutdown_tx.send(true);
            let worker_result = timeout(SHUTDOWN_TIMEOUT, &mut worker)
                .await
                .map_err(|_| StartupError::new("shutdown_timeout"))?;
            worker_result.map_err(|_| StartupError::new("worker_failed"))?;
            server_result
                .map_err(|_| StartupError::new("server_failed"))?
        }
        worker_result = &mut worker => {
            let stopping = *shutdown_observed.borrow();
            let _ = shutdown_tx.send(true);
            if stopping {
                worker_result.map_err(|_| StartupError::new("worker_failed"))?;
                let server_result = timeout(SHUTDOWN_TIMEOUT, &mut server)
                    .await
                    .map_err(|_| StartupError::new("shutdown_timeout"))?;
                server_result
                    .map_err(|_| StartupError::new("server_failed"))?
            } else {
                let _ = timeout(SHUTDOWN_TIMEOUT, &mut server).await;
                Err(StartupError::new("worker_failed"))
            }
        }
        () = wait_for_shutdown(shutdown_rx) => {
            let _ = shutdown_tx.send(true);
            match timeout(SHUTDOWN_TIMEOUT, async {
                tokio::join!(&mut server, &mut worker)
            }).await {
                Err(_) => Err(StartupError::new("shutdown_timeout")),
                Ok((server_result, worker_result)) => {
                    worker_result.map_err(|_| StartupError::new("worker_failed"))?;
                    server_result
                        .map_err(|_| StartupError::new("server_failed"))?
                }
            }
        }
    }
}

/// Runs an Axum server until shutdown, allowing up to thirty seconds for requests to drain.
pub async fn run_http_server(
    listener: TcpListener,
    router: Router,
    shutdown: impl Future<Output = ()> + Send + 'static,
) -> Result<(), StartupError> {
    let server = axum::serve(listener, router).with_graceful_shutdown(shutdown);
    timeout(SHUTDOWN_TIMEOUT, server)
        .await
        .map_err(|_| StartupError::new("shutdown_timeout"))?
        .map_err(|_| StartupError::new("server_failed"))
}

async fn wait_for_shutdown(mut shutdown: watch::Receiver<bool>) {
    while !*shutdown.borrow() {
        if shutdown.changed().await.is_err() {
            return;
        }
    }
}
