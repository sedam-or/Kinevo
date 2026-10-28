#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "registration acceptance tests use isolated synthetic PostgreSQL fixtures"
)]

use std::{
    net::{IpAddr, Ipv4Addr, SocketAddr},
    sync::{Arc, Mutex},
};

use async_trait::async_trait;
use axum::{
    Router,
    body::{Body, to_bytes},
    extract::ConnectInfo,
    http::{HeaderMap, Method, Request, StatusCode, header},
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
    errors::{AppError, ErrorCode},
    id::{AccountId, IdGen},
    policy::{PolicyValue, ResolutionSource, Resolved},
    secret::Secret,
    time::{FixedClock, Instant},
    validate::tz::Locale,
};
use kinevo_mod_identity::{
    MODULE,
    app::{
        accounts::AccountCreator,
        login::ClientAddr,
        refresh::Client,
        register::{RegisterRequest, RegisterService},
    },
    migrations,
};
use kinevo_ports::{
    fakes::{FakeAccountAccess, FakeInstanceState, FakeModuleSwitches, FakePolicyReader},
    module::{AccountProvisioner, ModuleDescriptor, ProvisionedAccount},
    platform::{InstanceInfo, InstanceState, ResolvedPolicies},
};
use sqlx::{PgPool, Row};
use tower::ServiceExt;

fn now() -> Instant {
    Instant::parse_rfc3339("2026-11-08T00:00:00Z").expect("fixed test instant is valid")
}

fn resolved(value: PolicyValue) -> Resolved {
    Resolved {
        value,
        source: ResolutionSource::Default,
        version_id: None,
        out_of_bounds: false,
    }
}

fn policy_reader(mode: &'static str) -> Arc<FakePolicyReader> {
    let mut values = ResolvedPolicies::default();
    for (key, value) in [
        ("auth.registration.mode", PolicyValue::Str(mode)),
        ("auth.password.min_length", PolicyValue::Int(12)),
        ("auth.access_token.ttl", PolicyValue::Seconds(600)),
        (
            "auth.refresh_token.idle_ttl",
            PolicyValue::Seconds(1_209_600),
        ),
        (
            "auth.refresh_token.absolute_ttl",
            PolicyValue::Seconds(2_592_000),
        ),
        ("auth.refresh.grace", PolicyValue::Seconds(10)),
        ("ratelimit.auth_per_minute", PolicyValue::Int(120)),
        ("ratelimit.api_per_minute", PolicyValue::Int(120)),
    ] {
        values.insert(key, resolved(value));
    }
    Arc::new(FakePolicyReader::new(values))
}

#[derive(Clone)]
struct JournalProvisioner {
    name: &'static str,
    calls: Arc<Mutex<Vec<(String, String, String)>>>,
    fail: bool,
}

impl JournalProvisioner {
    fn new(name: &'static str, calls: Arc<Mutex<Vec<(String, String, String)>>>) -> Self {
        Self {
            name,
            calls,
            fail: false,
        }
    }

    fn failing(name: &'static str, calls: Arc<Mutex<Vec<(String, String, String)>>>) -> Self {
        Self {
            name,
            calls,
            fail: true,
        }
    }
}

#[async_trait]
impl AccountProvisioner<Tx> for JournalProvisioner {
    fn module(&self) -> &'static str {
        self.name
    }

    async fn provision(&self, tx: &mut Tx, account: &ProvisionedAccount) -> Result<(), AppError> {
        self.calls.lock().expect("journal lock is healthy").push((
            self.name.to_owned(),
            tx.account_id().to_string(),
            account.id.to_string(),
        ));
        if self.fail {
            return Err(AppError::new(ErrorCode::InternalError));
        }
        Ok(())
    }
}

struct Harness {
    app: TestApp,
    service: Arc<RegisterService>,
    clock: Arc<FixedClock>,
    codec: Arc<TokenCodec>,
    policies: Arc<FakePolicyReader>,
    instance: Arc<FakeInstanceState>,
    calls: Arc<Mutex<Vec<(String, String, String)>>>,
    now: Instant,
}

async fn test_app() -> TestApp {
    let sources: [MigrationSource; 2] = [kinevo_db::migrations_source(), migrations()];
    TestApp::builder()
        .migrations(&sources)
        .clock_at(now())
        .build()
        .await
        .expect("disposable PostgreSQL test database should start")
}

async fn harness(mode: &'static str, fail_provisioner: bool) -> Harness {
    let app = test_app().await;
    let clock = Arc::new(FixedClock::new(now()));
    let codec = Arc::new(TokenCodec::new(
        KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x77; 32]))])
            .expect("synthetic test key ring is valid"),
    ));
    let policies = policy_reader(mode);
    let instance = Arc::new(FakeInstanceState::new(InstanceInfo {
        setup_completed: true,
        base_url: None,
        default_locale: Locale::En,
    }));
    let calls = Arc::new(Mutex::new(Vec::new()));
    let first: Arc<dyn AccountProvisioner<Tx>> =
        Arc::new(JournalProvisioner::new("first", calls.clone()));
    let second: Arc<dyn AccountProvisioner<Tx>> = if fail_provisioner {
        Arc::new(JournalProvisioner::failing("second", calls.clone()))
    } else {
        Arc::new(JournalProvisioner::new("second", calls.clone()))
    };
    let creator = Arc::new(AccountCreator::new(
        app.ids.clone(),
        app.tokens.clone(),
        vec![first, second],
        SubscriberRegistry::default(),
    ));
    let service = Arc::new(RegisterService::new(
        app.db.clone(),
        Arc::new(SyntheticPasswordWork),
        policies.clone(),
        instance.clone(),
        creator,
        app.ids.clone(),
        codec.clone(),
    ));
    Harness {
        app,
        service,
        clock,
        codec,
        policies,
        instance,
        calls,
        now: now(),
    }
}

struct SyntheticPasswordWork;

#[async_trait]
impl kinevo_mod_identity::app::login::PasswordWork for SyntheticPasswordWork {
    async fn verify(
        &self,
        _candidate: &Secret<String>,
        _stored_hash: &str,
    ) -> kinevo_mod_identity::infra::hasher::VerifyOutcome {
        kinevo_mod_identity::infra::hasher::VerifyOutcome::Wrong
    }

    async fn verify_dummy(&self, _candidate: &Secret<String>) {}

    async fn hash(&self, _candidate: &Secret<String>) -> Result<String, AppError> {
        Ok("synthetic-phc-hash".to_owned())
    }
}

fn request(email: &str, password: &str, invite_token: Option<String>) -> RegisterRequest {
    RegisterRequest {
        email: email.to_owned(),
        password: Secret::new(password.to_owned()),
        display_name: "Synthetic Account".to_owned(),
        timezone: "UTC".to_owned(),
        locale: None,
        invite_token,
    }
}

fn address() -> ClientAddr {
    ClientAddr::new(IpAddr::V4(Ipv4Addr::new(192, 0, 2, 20)))
}

async fn register(
    h: &Harness,
    email: &str,
    invite_token: Option<String>,
) -> Result<kinevo_mod_identity::app::login::LoginOutcome, AppError> {
    h.service
        .register(
            request(email, "synthetic registration password", invite_token),
            Client::Web,
            address(),
            h.now,
        )
        .await
}

async fn account_count(pool: &PgPool, email: &str) -> i64 {
    sqlx::query_scalar("SELECT count(*) FROM public.accounts WHERE email=$1")
        .bind(email)
        .fetch_one(pool)
        .await
        .expect("account count should be queryable")
}

async fn seed_invite(
    h: &Harness,
    token: &str,
    email: Option<&str>,
    expires_at: Instant,
    used_at: Option<Instant>,
) {
    let bytes = kinevo_kernel::crypto::decode_token(token)
        .expect("synthetic invite token has valid base64url encoding");
    let hash = kinevo_kernel::crypto::hash_token(&bytes);
    sqlx::query(
        "INSERT INTO public.invites (id, token_hash, email, expires_at, used_at, created_at) \
         VALUES ($1, $2, $3, $4::timestamptz, $5::timestamptz, $6::timestamptz)",
    )
    .bind(h.app.ids.new_uuid_v7())
    .bind(hash.as_slice())
    .bind(email)
    .bind(expires_at.to_rfc3339())
    .bind(used_at.map(|instant| instant.to_rfc3339()))
    .bind(h.now.to_rfc3339())
    .execute(h.app.admin_pool())
    .await
    .expect("synthetic invite should be inserted");
}

async fn invite_used_at(h: &Harness, token: &str) -> Option<String> {
    let bytes = kinevo_kernel::crypto::decode_token(token)
        .expect("synthetic invite token has valid base64url encoding");
    let hash = kinevo_kernel::crypto::hash_token(&bytes);
    sqlx::query_scalar("SELECT used_at::text FROM public.invites WHERE token_hash=$1")
        .bind(hash.as_slice())
        .fetch_one(h.app.admin_pool())
        .await
        .expect("synthetic invite should exist")
}

fn token(byte: u8) -> String {
    kinevo_kernel::crypto::encode_token(&[byte; 32])
}

fn http_router(h: &Harness) -> Router {
    let descriptors: [&ModuleDescriptor; 1] = [&MODULE];
    let permissions =
        Arc::new(PermissionTable::from_descriptors(&descriptors).expect("permissions are valid"));
    let module_kinds =
        Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds are valid"));
    let gate = Arc::new(Gate::new(
        h.codec.clone(),
        Arc::new(AccountCache::new(Arc::new(FakeAccountAccess::default()))),
        Arc::new(FakeModuleSwitches::default()),
        permissions,
        module_kinds,
    ));
    let instance: Arc<dyn InstanceState> = h.instance.clone();
    let state = HttpState::new(
        h.clock.clone(),
        h.app.ids.clone(),
        h.app.tokens.clone(),
        Arc::new(Catalog::load()),
        instance,
        Arc::new(TestReady),
        Some(
            kinevo_kernel::validate::net::HttpsUrl::parse("https://api.example.test")
                .expect("test URL is valid"),
        ),
        Arc::new(kinevo_kernel::page::CursorCodec::new(Secret::new(
            [0_u8; 32],
        ))),
        1_048_576,
        gate,
    )
    .with_origin_config(OriginConfig {
        public_origin: Some("https://api.example.test".to_owned()),
        android_origin_enabled: true,
    })
    .with_rate_limiter(Arc::new(RateLimiter::new(
        h.policies.clone(),
        h.clock.clone(),
        TrustedProxies::default(),
    )));
    kinevo_mod_identity::register_router(h.service.clone())
        .into_router(state)
        .expect("registration route should validate")
}

struct TestReady;

#[async_trait]
impl ReadyCheck for TestReady {
    async fn check(&self) -> Result<(), ReadyFailure> {
        Ok(())
    }
}

fn http_request(email: &str, password: &str) -> Request<Body> {
    let body = serde_json::json!({
        "email": email,
        "password": password,
        "display_name": "Synthetic Account",
        "timezone": "UTC"
    });
    let mut request = Request::builder()
        .method(Method::POST)
        .uri("/api/v1/auth/register")
        .header(header::CONTENT_TYPE, "application/json")
        .header("X-Kinevo-Client", "web")
        .header(header::ORIGIN, "https://api.example.test")
        .body(Body::from(body.to_string()))
        .expect("synthetic registration request is valid");
    request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
        IpAddr::V4(Ipv4Addr::new(192, 0, 2, 20)),
        12345,
    )));
    request
}

struct Observed {
    status: StatusCode,
    headers: HeaderMap,
    json: serde_json::Value,
}

async fn send(router: &Router, request: Request<Body>) -> Observed {
    let response = router
        .clone()
        .oneshot(request)
        .await
        .expect("in-process router should respond");
    let (parts, body) = response.into_parts();
    let bytes = to_bytes(body, usize::MAX)
        .await
        .expect("response body should be readable");
    Observed {
        status: parts.status,
        headers: parts.headers,
        json: serde_json::from_slice(&bytes).expect("response body should be JSON"),
    }
}

#[tokio::test]
async fn id_fr_09_open_mode_success() {
    let h = harness("open", false).await;
    let router = http_router(&h);
    let response = send(
        &router,
        http_request("open@example.test", "synthetic registration password"),
    )
    .await;

    assert_eq!(response.status, StatusCode::CREATED);
    assert_eq!(response.json["account"]["email"], "open@example.test");
    assert!(response.json["account"]["id"].is_string());
    assert!(
        response.json["access_token"]
            .as_str()
            .is_some_and(|value| !value.is_empty())
    );
    assert_eq!(response.json["expires_in"], 600);
    assert_eq!(response.headers[header::CACHE_CONTROL], "no-store");
    assert!(
        response.headers[header::SET_COOKIE]
            .to_str()
            .expect("cookie header is valid")
            .starts_with("__Secure-kinevo_rt=")
    );

    let account_id = AccountId::parse(response.json["account"]["id"].as_str().unwrap())
        .expect("response account id is canonical");
    let rows = sqlx::query(
        "SELECT (SELECT count(*) FROM public.accounts WHERE id=$1) AS accounts, \
                (SELECT count(*) FROM public.refresh_token_families WHERE account_id=$1) AS families, \
                (SELECT count(*) FROM public.refresh_tokens WHERE account_id=$1) AS tokens, \
                (SELECT count(*) FROM public.audit_log WHERE account_id=$1 AND action='auth.login') AS audits, \
                (SELECT count(*) FROM public.outbox_events WHERE account_id=$1 AND kind='AccountRegistered') AS events",
    )
    .bind(*account_id.as_uuid())
    .fetch_one(h.app.admin_pool())
    .await
    .expect("registration side effects should be queryable");
    for key in ["accounts", "families", "tokens", "audits", "events"] {
        assert_eq!(rows.try_get::<i64, _>(key).unwrap(), 1, "{key}");
    }
    assert_eq!(
        h.calls
            .lock()
            .unwrap()
            .iter()
            .map(|call| call.0.as_str())
            .collect::<Vec<_>>(),
        ["first", "second"]
    );
    assert!(
        h.calls
            .lock()
            .unwrap()
            .iter()
            .all(|call| { call.1 == account_id.to_string() && call.2 == account_id.to_string() })
    );
}

#[tokio::test]
async fn sec_ac_06_closed_registration() {
    let h = harness("closed", false).await;
    let response = send(
        &http_router(&h),
        http_request("closed@example.test", "synthetic registration password"),
    )
    .await;
    assert_eq!(response.status, StatusCode::FORBIDDEN);
    assert_eq!(response.json["code"], "registration_closed");
    assert_eq!(
        account_count(h.app.admin_pool(), "closed@example.test").await,
        0
    );
}

#[tokio::test]
async fn id_fr_09_invite_mode_table() {
    let h = harness("invite", false).await;
    let valid = token(11);
    let expired = token(12);
    let used = token(13);
    let wrong_email = token(14);
    seed_invite(
        &h,
        &valid,
        Some("invite-valid@example.test"),
        h.now.checked_add_seconds(600).unwrap(),
        None,
    )
    .await;
    seed_invite(
        &h,
        &expired,
        None,
        h.now.checked_add_seconds(-1).unwrap(),
        None,
    )
    .await;
    seed_invite(
        &h,
        &used,
        None,
        h.now.checked_add_seconds(600).unwrap(),
        Some(h.now),
    )
    .await;
    seed_invite(
        &h,
        &wrong_email,
        Some("someone-else@example.test"),
        h.now.checked_add_seconds(600).unwrap(),
        None,
    )
    .await;

    assert!(
        register(&h, "invite-valid@example.test", Some(valid.clone()))
            .await
            .is_ok()
    );
    for (email, supplied) in [
        ("expired@example.test", Some(expired)),
        ("used@example.test", Some(used)),
        ("wrong-email@example.test", Some(wrong_email)),
        ("missing@example.test", None),
    ] {
        assert_eq!(
            register(&h, email, supplied).await.unwrap_err().code,
            ErrorCode::InviteInvalid
        );
    }
    assert!(invite_used_at(&h, &valid).await.is_some());
}

#[tokio::test]
async fn mod_10_failing_provisioner_rolls_back() {
    let h = harness("invite", true).await;
    let invite = token(21);
    seed_invite(
        &h,
        &invite,
        Some("rollback@example.test"),
        h.now.checked_add_seconds(600).unwrap(),
        None,
    )
    .await;
    assert_eq!(
        register(&h, "rollback@example.test", Some(invite.clone()))
            .await
            .unwrap_err()
            .code,
        ErrorCode::InternalError
    );
    let counts = sqlx::query(
        "SELECT (SELECT count(*) FROM public.accounts WHERE email='rollback@example.test') AS accounts, \
                (SELECT count(*) FROM public.refresh_token_families) AS families, \
                (SELECT count(*) FROM public.refresh_tokens) AS tokens, \
                (SELECT count(*) FROM public.outbox_events) AS events",
    )
    .fetch_one(h.app.admin_pool())
    .await
    .expect("rollback counts should be queryable");
    for key in ["accounts", "families", "tokens", "events"] {
        assert_eq!(counts.try_get::<i64, _>(key).unwrap(), 0, "{key}");
    }
    assert!(invite_used_at(&h, &invite).await.is_none());
}

#[tokio::test]
async fn email_taken_case_insensitive() {
    let h = harness("open", false).await;
    assert!(register(&h, "Case@example.test", None).await.is_ok());
    assert_eq!(
        register(&h, "case@EXAMPLE.test", None)
            .await
            .unwrap_err()
            .code,
        ErrorCode::EmailTaken
    );
}

#[tokio::test]
async fn val_01_all_errors_returned() {
    let h = harness("open", false).await;
    let mut invalid = request("invalid-email", "short", None);
    invalid.timezone = "UTC+7".to_owned();
    let error = h
        .service
        .register(invalid, Client::Web, address(), h.now)
        .await
        .unwrap_err();
    assert_eq!(error.code, ErrorCode::ValidationFailed);
    assert_eq!(error.errors.len(), 3);
    assert_eq!(
        error
            .errors
            .iter()
            .map(|field| field.path.as_str())
            .collect::<Vec<_>>(),
        ["email", "password", "timezone"]
    );
}

#[tokio::test]
async fn tst_20_concurrent_same_email() {
    let h = harness("open", false).await;
    let mut tasks = tokio::task::JoinSet::new();
    for _ in 0..20 {
        let service = h.service.clone();
        let at = h.now;
        tasks.spawn(async move {
            service
                .register(
                    request("race@example.test", "synthetic registration password", None),
                    Client::Web,
                    address(),
                    at,
                )
                .await
        });
    }
    let mut created = 0;
    let mut taken = 0;
    while let Some(result) = tasks.join_next().await {
        match result.expect("registration task should join") {
            Ok(_) => created += 1,
            Err(error) => {
                assert_eq!(error.code, ErrorCode::EmailTaken);
                taken += 1;
            }
        }
    }
    assert_eq!(created, 1);
    assert_eq!(taken, 19);
    assert_eq!(
        account_count(h.app.admin_pool(), "race@example.test").await,
        1
    );
}

#[tokio::test]
async fn sm_22_invite_single_use_under_race() {
    let h = harness("invite", false).await;
    let invite = token(31);
    seed_invite(
        &h,
        &invite,
        None,
        h.now.checked_add_seconds(600).unwrap(),
        None,
    )
    .await;
    let mut tasks = tokio::task::JoinSet::new();
    for email in ["race-one@example.test", "race-two@example.test"] {
        let service = h.service.clone();
        let token = invite.clone();
        let at = h.now;
        tasks.spawn(async move {
            service
                .register(
                    request(email, "synthetic registration password", Some(token)),
                    Client::Web,
                    address(),
                    at,
                )
                .await
        });
    }
    let mut created = 0;
    let mut invalid = 0;
    while let Some(result) = tasks.join_next().await {
        match result.expect("registration task should join") {
            Ok(_) => created += 1,
            Err(error) => {
                assert_eq!(error.code, ErrorCode::InviteInvalid);
                invalid += 1;
            }
        }
    }
    assert_eq!((created, invalid), (1, 1));
    assert!(invite_used_at(&h, &invite).await.is_some());
}

#[tokio::test]
async fn adr_0011_provisioning_in_new_account_context() {
    let h = harness("open", false).await;
    let outcome = register(&h, "context@example.test", None)
        .await
        .expect("registration should succeed");
    let account_id = AccountId::parse(&outcome.profile.id).expect("account id is canonical");
    let calls = h.calls.lock().unwrap();
    assert_eq!(calls.len(), 2);
    assert!(calls.iter().all(|(_, tx, provisioned)| {
        tx == &account_id.to_string() && provisioned == &account_id.to_string()
    }));
}
