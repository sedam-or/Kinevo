#![allow(
    clippy::expect_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "setup acceptance tests use synthetic isolated fixtures"
)]

use std::{
    io,
    net::{IpAddr, Ipv4Addr, SocketAddr},
    sync::{Arc, Mutex},
};

use async_trait::async_trait;
use axum::{
    Router,
    body::{Body, to_bytes},
    http::{Method, Request, Response, StatusCode},
    routing::get,
};
use kinevo_db::{migrate::MigrationSource, testing::TestApp};
use kinevo_http::{
    auth::AccountCache,
    gate::{Gate, ModuleKinds},
    i18n::Catalog,
    ratelimit::{RateLimiter, TrustedProxies},
    rbac::PermissionTable,
    route::{Access, IdemMode, RateClass, RouteSpec},
    state::{HttpState, ReadyCheck, ReadyFailure},
    token::{KeyRing, TokenCodec},
};
use kinevo_kernel::{
    errors::ErrorCode,
    policy::{EffectiveDating, PolicySpec, PolicyType, PolicyValue, Scope},
    secret::Secret,
    time::Instant,
    validate::net::HttpsUrl,
};
use kinevo_mod_admin::{
    app::{
        setup::{SetupService, WizardPatch},
        templates::TemplateRegistry,
    },
    domain::template::ValidationCtx,
    infra::{instance_state::CachedInstanceState, wizard_store::WizardStore},
    migrations,
};
use kinevo_ports::{
    fakes::{FakeAccountAccess, FakeModuleSwitches, FakePolicyReader},
    module::{ModuleDescriptor, ModuleKind, PermissionDef},
    platform::ResolvedPolicies,
};
use serde_json::{Value, json};
use sqlx::Row;
use tower::ServiceExt;
use tracing_subscriber::fmt::MakeWriter;

const NOW: &str = "2026-11-08T00:00:00Z";
const EMPTY_PERMISSIONS: &[PermissionDef] = &[];
const EMPTY_KEYS: &[&str] = &[];
const BOTH_SCOPES: &[Scope] = &[Scope::Instance, Scope::Account];
const MODES: &[&str] = &["open", "closed"];
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

static REGISTRATION: PolicySpec = PolicySpec {
    key: "auth.registration.mode",
    ty: PolicyType::Enum { allowed: MODES },
    default: PolicyValue::Str("closed"),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: false,
};
static DAY_START: PolicySpec = PolicySpec {
    key: "scheduler.day_start",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(8 * 60),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static DAY_END: PolicySpec = PolicySpec {
    key: "scheduler.day_end",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(22 * 60),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static EARLIEST_TIME: PolicySpec = PolicySpec {
    key: "scheduler.anchor.earliest_time",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(6 * 60),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static POLICIES: &[&PolicySpec] = &[&REGISTRATION, &DAY_START, &DAY_END, &EARLIEST_TIME];

struct Harness {
    app: TestApp,
    service: SetupService,
}

struct TestReady;

#[derive(Clone)]
struct TestBuffer(Arc<Mutex<Vec<u8>>>);

impl<'a> MakeWriter<'a> for TestBuffer {
    type Writer = Self;

    fn make_writer(&'a self) -> Self::Writer {
        self.clone()
    }
}

impl io::Write for TestBuffer {
    fn write(&mut self, bytes: &[u8]) -> io::Result<usize> {
        self.0
            .lock()
            .expect("log buffer lock")
            .extend_from_slice(bytes);
        Ok(bytes.len())
    }

    fn flush(&mut self) -> io::Result<()> {
        Ok(())
    }
}

#[async_trait]
impl ReadyCheck for TestReady {
    async fn check(&self) -> Result<(), ReadyFailure> {
        Ok(())
    }
}

async fn harness() -> Harness {
    let now = Instant::parse_rfc3339(NOW).expect("fixed test instant is valid");
    let sources: [MigrationSource; 3] = [
        kinevo_db::migrations_source(),
        kinevo_mod_identity::migrations(),
        migrations(),
    ];
    let app = TestApp::builder()
        .migrations(&sources)
        .clock_at(now)
        .build()
        .await
        .expect("disposable PostgreSQL test database should start");
    let ctx = ValidationCtx::new(MODULES, POLICIES, TERMS);
    let templates =
        Arc::new(TemplateRegistry::embedded(&ctx).expect("embedded templates validate"));
    let instance = Arc::new(CachedInstanceState::new(
        app.db.clone(),
        app.clock.clone(),
        1,
    ));
    let service = SetupService::new(
        app.db.clone(),
        WizardStore::new(3600, 60, app.clock.clone()),
        app.tokens.clone(),
        app.ids.clone(),
        app.clock.clone(),
        instance,
        templates,
        MODULES,
        POLICIES,
        "https://setup.example.test".to_owned(),
    );
    Harness { app, service }
}

fn http_router(app: &TestApp, service: Arc<SetupService>) -> axum::Router {
    let descriptors: [&ModuleDescriptor; 1] = [&kinevo_mod_admin::MODULE];
    let permissions =
        Arc::new(PermissionTable::from_descriptors(&descriptors).expect("permissions are valid"));
    let kinds =
        Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds are valid"));
    let codec = Arc::new(TokenCodec::new(
        KeyRing::new(
            "setup-test",
            vec![("setup-test".to_owned(), Secret::new([0x31; 32]))],
        )
        .expect("synthetic key ring is valid"),
    ));
    let gate = Arc::new(Gate::new(
        codec,
        Arc::new(AccountCache::new(Arc::new(FakeAccountAccess::default()))),
        Arc::new(FakeModuleSwitches::default()),
        permissions,
        kinds,
    ));
    let mut policy_values = ResolvedPolicies::default();
    policy_values.insert(
        "ratelimit.auth_per_minute",
        kinevo_kernel::policy::Resolved {
            value: PolicyValue::Int(10),
            source: kinevo_kernel::policy::ResolutionSource::Default,
            version_id: None,
            out_of_bounds: false,
        },
    );
    let state = HttpState::new(
        app.clock.clone(),
        app.ids.clone(),
        app.tokens.clone(),
        Arc::new(Catalog::load()),
        service.instance_state().clone(),
        Arc::new(TestReady),
        Some(HttpsUrl::parse("https://setup.example.test").expect("public URL is valid")),
        Arc::new(kinevo_kernel::page::CursorCodec::new(Secret::new(
            [0x22; 32],
        ))),
        1_048_576,
        gate,
    )
    .with_rate_limiter(Arc::new(RateLimiter::new(
        Arc::new(FakePolicyReader::new(policy_values)),
        app.clock.clone(),
        TrustedProxies::default(),
    )));

    kinevo_mod_admin::http::setup::routes(service)
        .route(
            RouteSpec {
                method: Method::GET,
                path: "/probe",
                module: "admin",
                access: Access::Public,
                rate: RateClass::None,
                idempotency: IdemMode::Off,
                store_response_body: false,
                cookie_endpoint: false,
                summary: "Probe setup gate",
                allow_disabled: false,
            },
            get(|| async { "ok" }),
        )
        .route(
            RouteSpec {
                method: Method::GET,
                path: "/livez",
                module: "admin",
                access: Access::Public,
                rate: RateClass::None,
                idempotency: IdemMode::Off,
                store_response_body: false,
                cookie_endpoint: false,
                summary: "Liveness probe",
                allow_disabled: false,
            },
            get(|| async { "ok" }),
        )
        .route(
            RouteSpec {
                method: Method::GET,
                path: "/readyz",
                module: "admin",
                access: Access::Public,
                rate: RateClass::None,
                idempotency: IdemMode::Off,
                store_response_body: false,
                cookie_endpoint: false,
                summary: "Readiness probe",
                allow_disabled: false,
            },
            get(|| async { "ok" }),
        )
        .into_router(state)
        .expect("setup routes validate")
}

fn request(method: Method, path: &str, body: Body, with_peer: bool) -> Request<Body> {
    let mut request = Request::builder()
        .method(method)
        .uri(path)
        .header("content-type", "application/json")
        .body(body)
        .expect("synthetic request is valid");
    if with_peer {
        request
            .extensions_mut()
            .insert(axum::extract::ConnectInfo(SocketAddr::new(
                IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
                12345,
            )));
    }
    request
}

async fn response_json(response: Response<Body>) -> Value {
    let bytes = to_bytes(response.into_body(), 1_048_576)
        .await
        .expect("response body should read");
    serde_json::from_slice(&bytes).expect("response should be JSON")
}

async fn post_setup_session(router: &Router, token: &str) -> Response<Body> {
    router
        .clone()
        .oneshot(request(
            Method::POST,
            "/api/v1/setup/session",
            Body::from(json!({"token": token}).to_string()),
            true,
        ))
        .await
        .expect("setup session endpoint responds")
}

async fn open_setup_session(router: &Router, token: &str) -> Value {
    let response = post_setup_session(router, token).await;
    assert_eq!(response.status(), StatusCode::CREATED);
    response_json(response).await
}

#[tokio::test]
async fn set_04_token_printed_once_hash_stored() {
    let h = harness().await;
    let buffer = TestBuffer(Arc::new(Mutex::new(Vec::new())));
    let subscriber = tracing_subscriber::fmt()
        .with_ansi(false)
        .without_time()
        .with_writer(buffer.clone())
        .finish();
    let _guard = tracing::subscriber::set_default(subscriber);
    let first = h
        .service
        .ensure_token()
        .await
        .expect("first token is generated");
    let token = first.expect("first start returns the setup token");
    assert!(
        h.service
            .ensure_token()
            .await
            .expect("second start succeeds")
            .is_none()
    );
    h.service
        .open_session("192.0.2.9", &token)
        .await
        .expect("token can be used without another token log");

    let row = sqlx::query("SELECT setup_token_hash FROM public.instance_settings WHERE id=1")
        .fetch_one(h.app.admin_pool())
        .await
        .expect("settings row exists");
    let stored: Option<Vec<u8>> = row.get("setup_token_hash");
    let expected_hash = kinevo_kernel::crypto::hash_token(token.expose().as_bytes());
    assert_eq!(stored.as_deref(), Some(expected_hash.as_slice()));
    assert_ne!(stored.as_deref(), Some(token.expose().as_bytes()));
    let logs =
        String::from_utf8_lossy(&buffer.0.lock().expect("log buffer lock").to_vec()).into_owned();
    assert!(!logs.contains(token.expose()));
    assert_eq!(logs.matches("/setup?token=<redacted>").count(), 1);
}

#[tokio::test]
async fn adm_fr_01_gate_before_setup() {
    let h = harness().await;
    let service = Arc::new(h.service);
    let router = http_router(&h.app, Arc::clone(&service));
    let blocked = router
        .clone()
        .oneshot(request(Method::GET, "/api/v1/probe", Body::empty(), false))
        .await
        .expect("non-setup route responds");
    assert_eq!(blocked.status(), StatusCode::CONFLICT);
    let status = router
        .clone()
        .oneshot(request(
            Method::GET,
            "/api/v1/setup/status",
            Body::empty(),
            false,
        ))
        .await
        .expect("setup status responds");
    assert_eq!(status.status(), StatusCode::OK);
    let live = router
        .clone()
        .oneshot(request(Method::GET, "/livez", Body::empty(), false))
        .await
        .expect("liveness probe responds");
    let ready = router
        .oneshot(request(Method::GET, "/readyz", Body::empty(), false))
        .await
        .expect("readiness probe responds");
    assert_eq!(live.status(), StatusCode::OK);
    assert_eq!(ready.status(), StatusCode::OK);
}

#[tokio::test]
async fn open_session_ok() {
    let h = harness().await;
    let service = Arc::new(h.service);
    let token = service
        .ensure_token()
        .await
        .expect("token bootstrap succeeds")
        .expect("fresh token exists");
    let router = http_router(&h.app, service);
    let opened = open_setup_session(&router, token.expose()).await;
    assert_eq!(opened["step"], 1);
    let session_id = opened["session_id"]
        .as_str()
        .expect("created response includes a session id");
    let response = router
        .oneshot(request(
            Method::GET,
            &format!("/api/v1/setup/session/{session_id}"),
            Body::empty(),
            false,
        ))
        .await
        .expect("setup session endpoint responds");
    assert_eq!(response.status(), StatusCode::OK);
    let state = response_json(response).await;
    assert_eq!(state["session_id"], session_id);
    assert_eq!(state["step"], 1);
}

#[tokio::test]
async fn sec_ac_08_three_tries_then_429() {
    let h = harness().await;
    let service = Arc::new(h.service);
    let token = service
        .ensure_token()
        .await
        .expect("token bootstrap succeeds")
        .expect("fresh token exists");
    let router = http_router(&h.app, service);
    for attempt in 1..=3 {
        let response = post_setup_session(&router, "wrong-token").await;
        let expected = if attempt == 3 {
            StatusCode::TOO_MANY_REQUESTS
        } else {
            StatusCode::UNPROCESSABLE_ENTITY
        };
        assert_eq!(response.status(), expected);
        assert_eq!(
            response_json(response).await["code"],
            if attempt == 3 {
                "rate_limited"
            } else {
                "setup_token_invalid"
            }
        );
    }
    let response = post_setup_session(&router, token.expose()).await;
    assert_eq!(response.status(), StatusCode::TOO_MANY_REQUESTS);
    assert_eq!(response_json(response).await["code"], "rate_limited");
    let failed: i64 = sqlx::query_scalar(
        "SELECT count(*) FROM public.audit_log WHERE action='setup.token_failed'",
    )
    .fetch_one(h.app.admin_pool())
    .await
    .expect("failed token attempts were audited");
    assert_eq!(failed, 3);
    let accounts: i64 = sqlx::query_scalar("SELECT count(*) FROM public.accounts")
        .fetch_one(h.app.admin_pool())
        .await
        .expect("account table is readable");
    assert_eq!(accounts, 0);
}

#[tokio::test]
async fn set_05_restart_resets_wizard_keeps_token() {
    let h = harness().await;
    let token = h
        .service
        .ensure_token()
        .await
        .expect("token bootstrap succeeds")
        .expect("fresh token exists");
    let old = h
        .service
        .open_session("192.0.2.3", &token)
        .await
        .expect("token opens session");
    h.service
        .update(
            old.session_id.clone(),
            WizardPatch {
                step: 2,
                ..WizardPatch::default()
            },
        )
        .await
        .expect("step two is stored");
    let new_service = h
        .service
        .with_wizard(WizardStore::new(3600, 60, h.app.clock.clone()));
    assert_eq!(
        new_service
            .get(&old.session_id)
            .expect_err("a fresh process has an empty store")
            .code,
        ErrorCode::NotFound
    );
    let new = new_service
        .open_session("192.0.2.3", &token)
        .await
        .expect("persisted token still opens a session");
    assert_eq!(new.step, 1);
}

#[tokio::test]
async fn set_05_session_expires() {
    let h = harness().await;
    let token = h
        .service
        .ensure_token()
        .await
        .expect("token bootstrap succeeds")
        .expect("fresh token exists");
    let state = h
        .service
        .open_session("192.0.2.4", &token)
        .await
        .expect("token opens session");
    h.app
        .clock
        .advance_seconds(3601)
        .expect("clock advance is valid");
    assert_eq!(
        h.service
            .get(&state.session_id)
            .expect_err("expired session is unavailable")
            .code,
        ErrorCode::NotFound
    );
}

#[tokio::test]
async fn put_validates_modules_and_url() {
    let h = harness().await;
    let service = Arc::new(h.service);
    let token = service
        .ensure_token()
        .await
        .expect("token bootstrap succeeds")
        .expect("fresh token exists");
    let router = http_router(&h.app, service);
    let opened = open_setup_session(&router, token.expose()).await;
    let session_id = opened["session_id"]
        .as_str()
        .expect("created response includes a session id");
    let path = format!("/api/v1/setup/session/{session_id}");
    let dependency = router
        .clone()
        .oneshot(request(
            Method::PUT,
            &path,
            Body::from(json!({"step": 1, "modules": {"ai": true}}).to_string()),
            false,
        ))
        .await
        .expect("setup session endpoint responds");
    assert_eq!(dependency.status(), StatusCode::CONFLICT);
    assert_eq!(
        response_json(dependency).await["code"],
        "module_dependency_unmet"
    );
    let url = router
        .oneshot(request(
            Method::PUT,
            &path,
            Body::from(json!({"step": 1, "base_url": "https://example.test/path"}).to_string()),
            false,
        ))
        .await
        .expect("setup session endpoint responds");
    assert_eq!(url.status(), StatusCode::UNPROCESSABLE_ENTITY);
    let body = response_json(url).await;
    assert_eq!(body["code"], "validation_failed");
    assert_eq!(body["errors"][0]["path"], "base_url");
}

#[tokio::test]
async fn adm_fr_01_setup_routes_closed_after() {
    let h = harness().await;
    let token = h
        .service
        .ensure_token()
        .await
        .expect("token bootstrap succeeds")
        .expect("fresh token exists");
    sqlx::query("UPDATE public.instance_settings SET setup_completed=true WHERE id=1")
        .execute(h.app.admin_pool())
        .await
        .expect("test marks setup complete");
    h.service.instance_state().invalidate();
    let service = Arc::new(h.service);
    let router = http_router(&h.app, service);
    let body = Body::from(json!({"token": token.expose()}).to_string());
    let response = router
        .oneshot(request(Method::POST, "/api/v1/setup/session", body, true))
        .await
        .expect("setup session endpoint responds");
    assert_eq!(response.status(), StatusCode::CONFLICT);
}
