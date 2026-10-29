#![allow(
    clippy::expect_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "setup acceptance tests use isolated databases and synthetic data"
)]

use std::{
    net::{IpAddr, Ipv4Addr, SocketAddr},
    sync::Arc,
};

use async_trait::async_trait;
use axum::{
    Router,
    body::to_bytes,
    extract::ConnectInfo,
    http::{HeaderMap, Method, Request, StatusCode},
    response::Response,
};
use kinevo_db::{Tx, migrate::MigrationSource, outbox::SubscriberRegistry, testing::TestApp};
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
    errors::{AppError, FieldCode},
    policy::{EffectiveDating, PolicySpec, PolicyType, PolicyValue, ResolutionSource, Scope},
    secret::Secret,
    time::Instant,
    validate::net::HttpsUrl,
};
use kinevo_mod_admin::{
    app::{
        finish_setup::{
            FinishInput, FinishSetup, NewSetupAccount, SetupBootstrap, SetupPasswordProcessor,
        },
        setup::{SetupService, WizardPatch},
        templates::TemplateRegistry,
    },
    domain::template::ValidationCtx,
    http::setup,
    infra::{instance_state::CachedInstanceState, wizard_store::WizardStore},
    migrations,
};
use kinevo_mod_identity::{
    app::accounts::AccountCreator,
    domain::password::{CommonPasswords, PasswordPolicy, PlainPassword},
    migrations as identity_migrations,
};
use kinevo_ports::{
    fakes::FakePolicyReader,
    module::{AccountProvisioner, ModuleDescriptor, ModuleKind, PermissionDef, ProvisionedAccount},
    platform::{AccountCreation, ResolvedPolicies},
};
use tower::ServiceExt;

const NOW: &str = "2026-11-08T00:00:00Z";
const EMPTY_PERMISSIONS: &[PermissionDef] = &[];
const EMPTY_KEYS: &[&str] = &[];
const TERMS: &[&str] = &[
    "term.goal",
    "term.milestone",
    "term.task",
    "term.workspace",
    "term.hard_landscape",
    "term.focus_session",
];

const fn module(
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

static IDENTITY: ModuleDescriptor = module("identity", ModuleKind::Core, &[]);
static WORKSPACES: ModuleDescriptor = module("workspaces", ModuleKind::Core, &["identity"]);
static ADMIN: ModuleDescriptor = module("admin", ModuleKind::Core, &["identity"]);
static GOALS: ModuleDescriptor = module("goals", ModuleKind::Toggleable, &["workspaces"]);
static TASKS: ModuleDescriptor = module("tasks", ModuleKind::Core, &[]);
static LANDSCAPE: ModuleDescriptor = module("landscape", ModuleKind::Core, &["identity"]);
static SCHEDULER: ModuleDescriptor = module("scheduler", ModuleKind::Core, &["tasks", "landscape"]);
static EXECUTION: ModuleDescriptor = module("execution", ModuleKind::Core, &["tasks", "scheduler"]);
static SYNC: ModuleDescriptor = module("sync", ModuleKind::Toggleable, &["tasks"]);
static AI: ModuleDescriptor = module("ai", ModuleKind::Toggleable, &["goals", "tasks"]);
static KNOWLEDGE: ModuleDescriptor = module("knowledge", ModuleKind::Toggleable, &["workspaces"]);
static CANVAS: ModuleDescriptor = module("canvas", ModuleKind::Toggleable, &["workspaces"]);
static BILLING: ModuleDescriptor = module("billing", ModuleKind::Toggleable, &["identity"]);
static MODULES: &[&ModuleDescriptor] = &[
    &IDENTITY,
    &WORKSPACES,
    &ADMIN,
    &GOALS,
    &TASKS,
    &LANDSCAPE,
    &SCHEDULER,
    &EXECUTION,
    &SYNC,
    &AI,
    &KNOWLEDGE,
    &CANVAS,
    &BILLING,
];
const BOTH_SCOPES: &[Scope] = &[Scope::Instance, Scope::Account];
const REGISTRATION_MODES: &[&str] = &["open", "closed"];

static REGISTRATION: PolicySpec = PolicySpec {
    key: "auth.registration.mode",
    ty: PolicyType::Enum {
        allowed: REGISTRATION_MODES,
    },
    default: PolicyValue::Str("closed"),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: false,
};
static DAY_START: PolicySpec = PolicySpec {
    key: "scheduler.day_start",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(480),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static DAY_END: PolicySpec = PolicySpec {
    key: "scheduler.day_end",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(1320),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static EARLIEST: PolicySpec = PolicySpec {
    key: "scheduler.anchor.earliest_time",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(360),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static POLICIES: &[&PolicySpec] = &[&REGISTRATION, &DAY_START, &DAY_END, &EARLIEST];

fn policies() -> &'static [&'static PolicySpec] {
    POLICIES
}

struct Harness {
    app: TestApp,
    setup: Arc<SetupService>,
    finish: Arc<FinishSetup>,
    codec: Arc<TokenCodec>,
}

struct TestReady;

#[async_trait]
impl ReadyCheck for TestReady {
    async fn check(&self) -> Result<(), ReadyFailure> {
        Ok(())
    }
}

struct TestPasswords;

#[async_trait]
impl SetupPasswordProcessor for TestPasswords {
    fn validate(&self, raw: Secret<String>, minimum: usize) -> Result<Secret<String>, FieldCode> {
        PlainPassword::parse(
            raw,
            &PasswordPolicy::new(minimum),
            &CommonPasswords::embedded(),
        )
        .map(|password| Secret::new(password.expose().to_owned()))
    }

    async fn hash(&self, candidate: &Secret<String>) -> Result<String, AppError> {
        Ok(format!("synthetic:{}", candidate.expose().len()))
    }
}

async fn harness() -> Harness {
    harness_with_failure(false).await
}

async fn harness_with_failure(fail_provisioner: bool) -> Harness {
    let now = Instant::parse_rfc3339(NOW).expect("fixed test instant is valid");
    let sources: [MigrationSource; 3] = [
        kinevo_db::migrations_source(),
        identity_migrations(),
        migrations(),
    ];
    let app = TestApp::builder()
        .migrations(&sources)
        .clock_at(now)
        .build()
        .await
        .expect("disposable PostgreSQL test database should start");
    let context = ValidationCtx::new(MODULES, policies(), TERMS);
    let templates = Arc::new(TemplateRegistry::embedded(&context).expect("templates validate"));
    let instance = Arc::new(CachedInstanceState::new(
        app.db.clone(),
        app.clock.clone(),
        1,
    ));
    let setup_service = Arc::new(SetupService::new(
        app.db.clone(),
        WizardStore::new(3600, 60, app.clock.clone()),
        app.tokens.clone(),
        app.ids.clone(),
        app.clock.clone(),
        instance.clone(),
        templates.clone(),
        MODULES,
        policies(),
        "https://setup.example.test".to_owned(),
    ));
    let token_codec = Arc::new(TokenCodec::new(
        KeyRing::new(
            "setup-test",
            vec![("setup-test".to_owned(), Secret::new([0x31; 32]))],
        )
        .expect("synthetic key ring is valid"),
    ));
    let mut provisioners: Vec<Arc<dyn AccountProvisioner<Tx>>> =
        kinevo_mod_identity::provisioners();
    if fail_provisioner {
        provisioners.push(Arc::new(FailingProvisioner));
    }
    let accounts: Arc<dyn AccountCreation<Tx>> = Arc::new(AccountCreator::new(
        app.ids.clone(),
        app.tokens.clone(),
        provisioners,
        SubscriberRegistry::default(),
    ));
    let finish = Arc::new(FinishSetup::new(
        app.db.clone(),
        Arc::new(TestPasswords),
        accounts,
        token_codec.clone(),
        app.ids.clone(),
        app.clock.clone(),
        instance,
        templates,
        MODULES,
        policies(),
        SubscriberRegistry::default(),
    ));
    Harness {
        app,
        setup: setup_service,
        finish,
        codec: token_codec,
    }
}

async fn open_session(setup_service: &SetupService) -> kinevo_mod_admin::app::setup::WizardState {
    let token = setup_service
        .ensure_token()
        .await
        .expect("setup token query succeeds")
        .expect("fresh instance gets a token");
    setup_service
        .open_session("127.0.0.1", &token)
        .await
        .expect("valid token opens a wizard session")
}

struct FailingProvisioner;

#[async_trait]
impl AccountProvisioner<Tx> for FailingProvisioner {
    fn module(&self) -> &'static str {
        "identity"
    }

    async fn provision(&self, _tx: &mut Tx, _account: &ProvisionedAccount) -> Result<(), AppError> {
        Err(AppError::new(
            kinevo_kernel::errors::ErrorCode::ServiceUnavailable,
        ))
    }
}

fn router(harness: &Harness) -> axum::Router {
    let descriptors: [&ModuleDescriptor; 1] = [&ADMIN];
    let permissions =
        Arc::new(PermissionTable::from_descriptors(&descriptors).expect("permissions are valid"));
    let kinds = Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds valid"));
    let gate = Arc::new(Gate::new(
        harness.codec.clone(),
        Arc::new(AccountCache::new(Arc::new(
            kinevo_ports::fakes::FakeAccountAccess::default(),
        ))),
        Arc::new(kinevo_ports::fakes::FakeModuleSwitches::default()),
        permissions,
        kinds,
    ));
    let mut rate_policy = ResolvedPolicies::default();
    rate_policy.insert(
        "ratelimit.auth_per_minute",
        kinevo_kernel::policy::Resolved {
            value: PolicyValue::Int(10),
            source: ResolutionSource::Default,
            version_id: None,
            out_of_bounds: false,
        },
    );
    let state = HttpState::new(
        harness.app.clock.clone(),
        harness.app.ids.clone(),
        harness.app.tokens.clone(),
        Arc::new(Catalog::load()),
        harness.setup.instance_state().clone(),
        Arc::new(TestReady),
        Some(HttpsUrl::parse("https://setup.example.test").expect("URL is valid")),
        Arc::new(kinevo_kernel::page::CursorCodec::new(Secret::new(
            [0x22; 32],
        ))),
        1_048_576,
        gate,
    )
    .with_origin_config(OriginConfig {
        public_origin: Some("https://setup.example.test".to_owned()),
        android_origin_enabled: true,
    })
    .with_rate_limiter(Arc::new(RateLimiter::new(
        Arc::new(FakePolicyReader::new(rate_policy)),
        harness.app.clock.clone(),
        TrustedProxies::default(),
    )));
    setup::routes_with_finish(harness.setup.clone(), harness.finish.clone())
        .into_router(state)
        .expect("setup routes satisfy the gate")
}

fn session_text(session: &kinevo_mod_admin::app::setup::WizardState) -> String {
    serde_json::to_value(&session.session_id)
        .expect("session id serializes")
        .as_str()
        .expect("session id is a string")
        .to_owned()
}

fn finish_request(session_id: &str, key: &str, password: &str) -> Request<axum::body::Body> {
    let mut request = Request::builder()
        .method(Method::POST)
        .uri(format!("/api/v1/setup/session/{session_id}/finish"))
        .header("Origin", "https://setup.example.test")
        .header("X-Kinevo-Client", "web")
        .header("Idempotency-Key", key)
        .header("content-type", "application/json")
        .body(axum::body::Body::from(
            serde_json::json!({
                "account": {
                    "email": "first@example.test",
                    "password": password,
                },
            })
            .to_string(),
        ))
        .expect("synthetic request is valid");
    request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
        IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
        12345,
    )));
    request
}

async fn send_finish(
    router: &Router,
    session: &kinevo_mod_admin::app::setup::WizardState,
    key: &str,
    password: &str,
) -> Response<axum::body::Body> {
    router
        .clone()
        .oneshot(finish_request(&session_text(session), key, password))
        .await
        .expect("router responds")
}

async fn response_parts(
    response: Response<axum::body::Body>,
) -> (StatusCode, HeaderMap, serde_json::Value) {
    let status = response.status();
    let headers = response.headers().clone();
    let body = to_bytes(response.into_body(), 1_048_576)
        .await
        .expect("response body fits");
    let payload: serde_json::Value = serde_json::from_slice(&body).expect("response is JSON");
    (status, headers, payload)
}

async fn assert_no_finish_rows(harness: &Harness, has_setup_token: bool) {
    for table in [
        "accounts",
        "instance_modules",
        "policy_versions",
        "refresh_token_families",
        "refresh_tokens",
        "audit_log",
        "outbox_events",
    ] {
        let query = format!("SELECT count(*) FROM public.{table}");
        let count: i64 = sqlx::query_scalar(sqlx::AssertSqlSafe(query))
            .fetch_one(harness.app.admin_pool())
            .await
            .expect("table is readable");
        assert_eq!(count, 0, "unexpected persisted rows in {table}");
    }
    let setup_completed: bool =
        sqlx::query_scalar("SELECT setup_completed FROM public.instance_settings WHERE id=1")
            .fetch_one(harness.app.admin_pool())
            .await
            .expect("instance settings are readable");
    let token_hash: Option<Vec<u8>> =
        sqlx::query_scalar("SELECT setup_token_hash FROM public.instance_settings WHERE id=1")
            .fetch_one(harness.app.admin_pool())
            .await
            .expect("setup token state is readable");
    assert!(!setup_completed);
    assert_eq!(token_hash.is_some(), has_setup_token);
}

mod finish_setup {
    use super::*;

    #[tokio::test]
    async fn set_01_defaults_only() {
        let h = harness().await;
        let wizard = open_session(&h.setup).await;
        let app = router(&h);
        let response = send_finish(
            &app,
            &wizard,
            "setup-finish-001",
            "a sufficiently strong synthetic password",
        )
        .await;
        let (status, headers, payload) = response_parts(response).await;
        assert_eq!(
            status,
            StatusCode::CREATED,
            "unexpected response: {payload}"
        );
        assert!(headers.get("set-cookie").is_some());
        assert_eq!(payload["account"]["email"], "first@example.test");
        assert_eq!(payload["account"]["is_instance_admin"], true);
        assert_eq!(payload["expires_in"], 600);
        assert!(
            !payload
                .as_object()
                .is_some_and(|body| body.contains_key("refresh_token"))
        );

        let account_count: i64 = sqlx::query_scalar("SELECT count(*) FROM public.accounts")
            .fetch_one(h.app.admin_pool())
            .await
            .expect("account table is readable");
        let setup_completed: bool =
            sqlx::query_scalar("SELECT setup_completed FROM public.instance_settings WHERE id=1")
                .fetch_one(h.app.admin_pool())
                .await
                .expect("instance settings are readable");
        let token_hash: Option<Vec<u8>> =
            sqlx::query_scalar("SELECT setup_token_hash FROM public.instance_settings WHERE id=1")
                .fetch_one(h.app.admin_pool())
                .await
                .expect("setup token state is readable");
        assert_eq!(account_count, 1);
        assert!(setup_completed);
        assert!(token_hash.is_none());
    }

    #[tokio::test]
    async fn set_02_atomic_at_every_step() {
        let h = harness().await;
        let wizard = open_session(&h.setup).await;
        let wizard = h
            .setup
            .update(
                wizard.session_id.clone(),
                WizardPatch {
                    step: 1,
                    template_id: Some("student".to_owned()),
                    locale: None,
                    base_url: None,
                    modules: None,
                    policies: None,
                },
            )
            .await
            .expect("student template is valid");
        let app = router(&h);
        let failure_points = [
            ("accounts", "INSERT"),
            ("instance_modules", "INSERT"),
            ("policy_versions", "INSERT"),
            ("refresh_token_families", "INSERT"),
            ("refresh_tokens", "INSERT"),
            ("instance_settings", "UPDATE"),
            ("audit_log", "INSERT"),
            ("outbox_events", "INSERT"),
        ];

        for (index, (table, operation)) in failure_points.into_iter().enumerate() {
            sqlx::query(
                "CREATE FUNCTION public.fail_setup_finish() RETURNS trigger \
                 LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'injected setup failure'; END $$",
            )
            .execute(h.app.admin_pool())
            .await
            .expect("failure trigger function is created");
            let create_trigger = format!(
                "CREATE TRIGGER fail_setup_finish BEFORE {operation} ON public.{table} \
                 FOR EACH ROW EXECUTE FUNCTION public.fail_setup_finish()"
            );
            sqlx::query(sqlx::AssertSqlSafe(create_trigger))
                .execute(h.app.admin_pool())
                .await
                .expect("failure trigger is created");

            let response = send_finish(
                &app,
                &wizard,
                &format!("atomic-failure-{index}"),
                "a sufficiently strong synthetic password",
            )
            .await;
            let (status, _, payload) = response_parts(response).await;
            assert_eq!(
                status,
                StatusCode::SERVICE_UNAVAILABLE,
                "{table}: {payload}"
            );

            let drop_trigger = format!("DROP TRIGGER fail_setup_finish ON public.{table}");
            sqlx::query(sqlx::AssertSqlSafe(drop_trigger))
                .execute(h.app.admin_pool())
                .await
                .expect("failure trigger is removed");
            sqlx::query("DROP FUNCTION public.fail_setup_finish()")
                .execute(h.app.admin_pool())
                .await
                .expect("failure trigger function is removed");
            assert_no_finish_rows(&h, true).await;
        }
    }

    #[tokio::test]
    async fn set_03_idempotent_per_key() {
        let h = harness().await;
        let wizard = open_session(&h.setup).await;
        let app = router(&h);
        let (left, right) = tokio::join!(
            send_finish(
                &app,
                &wizard,
                "setup-finish-concurrent",
                "a sufficiently strong synthetic password",
            ),
            send_finish(
                &app,
                &wizard,
                "setup-finish-concurrent",
                "a sufficiently strong synthetic password",
            )
        );
        let (left_status, left_headers, left_payload) = response_parts(left).await;
        let (right_status, right_headers, right_payload) = response_parts(right).await;
        assert_eq!(left_status, StatusCode::CREATED, "{left_payload}");
        assert_eq!(right_status, StatusCode::CREATED, "{right_payload}");
        assert_eq!(left_payload, right_payload);
        assert_eq!(
            left_headers.get("set-cookie"),
            right_headers.get("set-cookie")
        );
        let replay_headers = [&left_headers, &right_headers]
            .into_iter()
            .filter(|headers| headers.get("Idempotency-Replayed").is_some())
            .count();
        assert_eq!(replay_headers, 1);
        let accounts: i64 = sqlx::query_scalar("SELECT count(*) FROM public.accounts")
            .fetch_one(h.app.admin_pool())
            .await
            .expect("account table is readable");
        assert_eq!(accounts, 1);
    }

    #[tokio::test]
    async fn set_03_second_finish_conflicts() {
        let h = harness().await;
        let wizard = open_session(&h.setup).await;
        let app = router(&h);
        let first = send_finish(
            &app,
            &wizard,
            "setup-finish-first",
            "a sufficiently strong synthetic password",
        )
        .await;
        assert_eq!(first.status(), StatusCode::CREATED);
        let second = send_finish(
            &app,
            &wizard,
            "setup-finish-second",
            "a sufficiently strong synthetic password",
        )
        .await;
        let (status, _, payload) = response_parts(second).await;
        assert_eq!(status, StatusCode::CONFLICT, "{payload}");
        assert_eq!(payload["code"], "setup_already_completed");
    }

    #[tokio::test]
    async fn mod_10_provisioner_failure_rolls_back() {
        let h = harness_with_failure(true).await;
        let wizard = open_session(&h.setup).await;
        let app = router(&h);
        let response = send_finish(
            &app,
            &wizard,
            "setup-finish-provisioner-failure",
            "a sufficiently strong synthetic password",
        )
        .await;
        let (status, _, payload) = response_parts(response).await;
        assert_eq!(status, StatusCode::SERVICE_UNAVAILABLE, "{payload}");
        assert!(h.setup.get(&wizard.session_id).is_ok());
        assert_no_finish_rows(&h, true).await;
    }

    #[tokio::test]
    async fn set_06_env_bootstrap() {
        let h = harness().await;
        h.finish
            .bootstrap_from_env(SetupBootstrap {
                email: "bootstrap@example.test".to_owned(),
                password: Secret::new("a sufficiently strong synthetic password".to_owned()),
                template_id: Some("personal".to_owned()),
            })
            .await
            .expect("configured bootstrap completes setup without a setup token");

        let setup_completed: bool =
            sqlx::query_scalar("SELECT setup_completed FROM public.instance_settings WHERE id=1")
                .fetch_one(h.app.admin_pool())
                .await
                .expect("instance settings are readable");
        let token_hash: Option<Vec<u8>> =
            sqlx::query_scalar("SELECT setup_token_hash FROM public.instance_settings WHERE id=1")
                .fetch_one(h.app.admin_pool())
                .await
                .expect("setup token state is readable");
        let audit_count: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.audit_log WHERE action='setup.completed'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("audit table is readable");
        assert!(setup_completed);
        assert!(token_hash.is_none());
        assert_eq!(audit_count, 1);
    }

    #[tokio::test]
    async fn template_applied_at_finish() {
        let h = harness().await;
        let wizard = open_session(&h.setup).await;
        let wizard = h
            .setup
            .update(
                wizard.session_id.clone(),
                WizardPatch {
                    step: 1,
                    template_id: Some("student".to_owned()),
                    locale: None,
                    base_url: None,
                    modules: None,
                    policies: None,
                },
            )
            .await
            .expect("student template is valid");
        let outcome = h
            .finish
            .run_idempotent(
                FinishInput {
                    account: NewSetupAccount {
                        email: "student@example.test".to_owned(),
                        password: Secret::new(
                            "a sufficiently strong synthetic password".to_owned(),
                        ),
                        display_name: "student".to_owned(),
                        timezone: "UTC".to_owned(),
                    },
                    session: wizard,
                },
                kinevo_ports::platform::Client::Web,
                kinevo_kernel::validate::IdemKey::parse("student-template-finish")
                    .expect("idempotency key is valid"),
            )
            .await
            .expect("student setup finishes");
        let canvas_enabled: bool = sqlx::query_scalar(
            "SELECT enabled FROM public.instance_modules WHERE module_key='canvas'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("canvas module state is readable");
        let instance_day_start: String = sqlx::query_scalar(
            "SELECT value::text FROM public.policy_versions \
             WHERE key='scheduler.day_start' AND scope='instance' ORDER BY created_at DESC LIMIT 1",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("instance policy is readable");
        let account_day_start: String = sqlx::query_scalar(
            "SELECT value::text FROM public.policy_versions \
             WHERE key='scheduler.day_start' AND scope='account' AND account_id=$1::uuid \
             ORDER BY created_at DESC LIMIT 1",
        )
        .bind(&outcome.account.id)
        .fetch_one(h.app.admin_pool())
        .await
        .expect("account policy is readable");
        assert!(canvas_enabled);
        assert_eq!(instance_day_start, "\"07:00\"");
        assert_eq!(account_day_start, "\"07:00\"");
    }

    #[tokio::test]
    async fn password_policy_enforced() {
        let h = harness().await;
        let wizard = open_session(&h.setup).await;
        let app = router(&h);
        let response = send_finish(&app, &wizard, "weak-password", "short").await;
        let (status, _, payload) = response_parts(response).await;
        assert_eq!(status, StatusCode::UNPROCESSABLE_ENTITY, "{payload}");
        assert_eq!(payload["code"], "validation_failed");
        assert_eq!(payload["errors"][0]["code"], "password_too_short");
        assert_no_finish_rows(&h, true).await;
    }
}
