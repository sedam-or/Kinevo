#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "login acceptance tests use isolated synthetic PostgreSQL fixtures"
)]

use std::{
    collections::BTreeMap,
    fmt,
    net::{IpAddr, Ipv4Addr, SocketAddr},
    sync::{
        Arc, Mutex,
        atomic::{AtomicUsize, Ordering},
    },
};

use async_trait::async_trait;
use axum::{
    Router,
    body::{Body, to_bytes},
    extract::ConnectInfo,
    http::{HeaderMap, Method, Request, StatusCode, header},
    response::IntoResponse,
};
use kinevo_db::{Tx, migrate::MigrationSource, testing::TestApp};
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
    id::AccountId,
    page::CursorCodec,
    policy::{PolicyValue, ResolutionSource, Resolved},
    secret::Secret,
    time::{Clock, FixedClock, Instant},
    validate::net::HttpsUrl,
};
use kinevo_mod_identity::{
    MODULE,
    app::{
        login::{ClientAddr, LoginPolicy, LoginRequest, LoginService, PasswordWork},
        mfa::{MfaInput, MfaResult, MfaVerifier},
    },
    domain::{lockout::LockoutPolicy, session::SessionPolicy},
    infra::hasher::VerifyOutcome,
    migrations,
};
use kinevo_ports::{
    fakes::{FakeAccountAccess, FakeInstanceState, FakeModuleSwitches, FakePolicyReader},
    module::ModuleDescriptor,
    platform::{InstanceInfo, InstanceState, ResolvedPolicies},
};
use sqlx::Row;
use tower::ServiceExt;
use tracing::{
    Event, Subscriber,
    field::{Field, Visit},
    instrument::WithSubscriber,
    span,
};

fn now() -> Instant {
    Instant::parse_rfc3339("2026-11-03T00:00:00Z").expect("fixed test instant is valid")
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

#[derive(Default)]
struct TestPasswords {
    verifies: AtomicUsize,
    dummy_verifies: AtomicUsize,
}

#[async_trait]
impl PasswordWork for TestPasswords {
    async fn verify(&self, candidate: &Secret<String>, stored_hash: &str) -> VerifyOutcome {
        self.verifies.fetch_add(1, Ordering::Relaxed);
        if candidate.expose() != "synthetic correct password" {
            return VerifyOutcome::Wrong;
        }
        if stored_hash == "synthetic old hash" {
            VerifyOutcome::OkNeedsRehash
        } else {
            VerifyOutcome::Ok
        }
    }

    async fn verify_dummy(&self, _candidate: &Secret<String>) {
        self.dummy_verifies.fetch_add(1, Ordering::Relaxed);
    }

    async fn hash(&self, _candidate: &Secret<String>) -> Result<String, AppError> {
        Ok("synthetic upgraded hash".to_owned())
    }
}

struct FakeMfa;

#[async_trait]
impl MfaVerifier for FakeMfa {
    async fn verify(&self, _account: AccountId, input: &MfaInput, _now: Instant) -> MfaResult {
        let code = match input {
            MfaInput::Totp(value) | MfaInput::Recovery(value) => value.expose(),
        };
        if code == "123456" || code == "ABC2345678" {
            MfaResult::Valid
        } else {
            MfaResult::Invalid
        }
    }
}

struct Harness {
    service: Arc<LoginService>,
    app: TestApp,
    clock: Arc<FixedClock>,
    codec: Arc<TokenCodec>,
    policies: Arc<FakePolicyReader>,
    passwords: Arc<TestPasswords>,
}

fn resolved(value: PolicyValue) -> Resolved {
    Resolved {
        value,
        source: ResolutionSource::Default,
        version_id: None,
        out_of_bounds: false,
    }
}

fn policy_reader(auth_per_minute: i64) -> Arc<FakePolicyReader> {
    let mut values = ResolvedPolicies::default();
    for (key, value) in [
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
        ("auth.lockout.max_attempts", PolicyValue::Int(5)),
        ("auth.lockout.window", PolicyValue::Seconds(900)),
        ("auth.lockout.duration", PolicyValue::Seconds(900)),
        ("auth.refresh.grace", PolicyValue::Seconds(10)),
        ("auth.totp.required", PolicyValue::Bool(false)),
        (
            "ratelimit.auth_per_minute",
            PolicyValue::Int(auth_per_minute),
        ),
    ] {
        values.insert(key, resolved(value));
    }
    Arc::new(FakePolicyReader::new(values))
}

async fn harness(auth_per_minute: i64) -> Harness {
    let app = test_app().await;
    let clock = Arc::new(FixedClock::new(now()));
    let codec = Arc::new(TokenCodec::new(
        KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
            .expect("synthetic test key ring is valid"),
    ));
    let policies = policy_reader(auth_per_minute);
    let passwords = Arc::new(TestPasswords::default());
    let service = Arc::new(LoginService::new(
        app.db.clone(),
        passwords.clone(),
        Arc::new(FakeMfa),
        policies.clone(),
        clock.clone(),
        app.ids.clone(),
        app.tokens.clone(),
        codec.clone(),
    ));
    Harness {
        service,
        app,
        clock,
        codec,
        policies,
        passwords,
    }
}

fn policy() -> LoginPolicy {
    LoginPolicy {
        password_min_length: 12,
        lockout: LockoutPolicy {
            max_attempts: 5,
            window_seconds: 900,
            duration_seconds: 900,
        },
        totp_required: false,
        session: SessionPolicy {
            access_ttl: 600,
            idle_ttl: 14 * 24 * 60 * 60,
            absolute_ttl: 30 * 24 * 60 * 60,
            grace: 10,
        },
    }
}

async fn create_account(
    harness: &Harness,
    ordinal: u8,
    stored_hash: &str,
    status: &str,
    totp_enabled: bool,
) -> AccountId {
    let account = harness.app.new_account();
    let mut tx = Tx::begin(&harness.app.db, account)
        .await
        .expect("account transaction should begin");
    sqlx::query(
        "INSERT INTO public.accounts \
         (id, email, display_name, password_hash, timezone, status, totp_enabled) \
         VALUES ($1, $2, $3, $4, 'UTC', $5, $6)",
    )
    .bind(*account.as_uuid())
    .bind(format!("account-{ordinal}@example.test"))
    .bind("Synthetic Account")
    .bind(stored_hash)
    .bind(status)
    .bind(totp_enabled)
    .execute(tx.conn())
    .await
    .expect("synthetic account should insert");
    tx.commit()
        .await
        .expect("account fixture transaction should commit");
    account
}

fn request_for(
    email: &str,
    password: &str,
    _client: &str,
    totp_code: Option<&str>,
) -> LoginRequest {
    LoginRequest {
        email: email.to_owned(),
        password: Secret::new(password.to_owned()),
        totp_code: totp_code.map(str::to_owned),
        recovery_code: None,
    }
}

fn http_router(harness: &Harness) -> Router {
    let descriptors: [&ModuleDescriptor; 1] = [&MODULE];
    let permissions =
        Arc::new(PermissionTable::from_descriptors(&descriptors).expect("permissions are valid"));
    let module_kinds = Arc::new(
        ModuleKinds::from_descriptors(&descriptors).expect("module descriptors are valid"),
    );
    let gate = Arc::new(Gate::new(
        harness.codec.clone(),
        Arc::new(AccountCache::new(Arc::new(FakeAccountAccess::default()))),
        Arc::new(FakeModuleSwitches::default()),
        permissions,
        module_kinds,
    ));
    let instance: Arc<dyn InstanceState> = Arc::new(FakeInstanceState::new(InstanceInfo {
        setup_completed: true,
        base_url: None,
        default_locale: kinevo_kernel::validate::tz::Locale::En,
    }));
    let mut state = HttpState::new(
        harness.clock.clone(),
        harness.app.ids.clone(),
        harness.app.tokens.clone(),
        Arc::new(Catalog::load()),
        instance,
        Arc::new(TestReady),
        Some(HttpsUrl::parse("https://api.example.test").expect("test URL is valid")),
        Arc::new(CursorCodec::new(Secret::new([0_u8; 32]))),
        1_048_576,
        gate,
    )
    .with_origin_config(OriginConfig {
        public_origin: Some("https://api.example.test".to_owned()),
        android_origin_enabled: true,
    });
    state = state.with_rate_limiter(Arc::new(RateLimiter::new(
        harness.policies.clone(),
        harness.clock.clone(),
        TrustedProxies::default(),
    )));
    kinevo_mod_identity::router(harness.service.clone())
        .into_router(state)
        .expect("identity login route should validate")
}

struct TestReady;

#[async_trait]
impl ReadyCheck for TestReady {
    async fn check(&self) -> Result<(), ReadyFailure> {
        Ok(())
    }
}

fn http_request(email: &str, password: &str, client: &str, totp: Option<&str>) -> Request<Body> {
    let body = serde_json::json!({
        "email": email,
        "password": password,
        "totp_code": totp,
    });
    let origin = if client == "android" {
        "https://app.kinevo.localhost"
    } else {
        "https://api.example.test"
    };
    let mut request = Request::builder()
        .method(Method::POST)
        .uri("/api/v1/auth/login")
        .header(header::CONTENT_TYPE, "application/json")
        .header("X-Kinevo-Client", client)
        .header(header::ORIGIN, origin)
        .body(Body::from(body.to_string()))
        .expect("synthetic login request is valid");
    request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
        IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
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
        json: serde_json::from_slice(&bytes).expect("login response should be JSON"),
    }
}

#[tokio::test]
async fn id_login_success_flow() {
    let h = harness(120).await;
    let account = create_account(&h, 1, "synthetic hash", "active", false).await;
    let router = http_router(&h);
    let response = send(
        &router,
        http_request(
            "account-1@example.test",
            "synthetic correct password",
            "web",
            None,
        ),
    )
    .await;

    assert_eq!(response.status, StatusCode::OK);
    assert!(response.json["account"]["id"].is_string());
    assert!(response.json["access_token"].is_string());
    assert_eq!(response.json["expires_in"], 600);
    assert!(response.json.get("refresh_token").is_none());
    let cookie = response
        .headers
        .get(header::SET_COOKIE)
        .expect("web login should set refresh cookie")
        .to_str()
        .expect("cookie header is valid");
    assert!(cookie.starts_with("__Secure-kinevo_rt="));

    let rows = sqlx::query(
        "SELECT (SELECT count(*) FROM public.refresh_token_families WHERE account_id=$1) AS families, \
                (SELECT count(*) FROM public.refresh_tokens WHERE account_id=$1) AS tokens, \
                (SELECT count(*) FROM public.audit_log WHERE account_id=$1 AND action='auth.login') AS audits, \
                (SELECT count(*) FROM public.outbox_events WHERE account_id=$1 AND kind='AccountLoggedIn') AS events",
    )
    .bind(*account.as_uuid())
    .fetch_one(h.app.admin_pool())
    .await
    .expect("login side effects should be queryable");
    assert_eq!(
        rows.try_get::<i64, _>("families")
            .expect("count is integer"),
        1
    );
    assert_eq!(
        rows.try_get::<i64, _>("tokens").expect("count is integer"),
        1
    );
    assert_eq!(
        rows.try_get::<i64, _>("audits").expect("count is integer"),
        1
    );
    assert_eq!(
        rows.try_get::<i64, _>("events").expect("count is integer"),
        1
    );
}

#[tokio::test]
async fn id_fr_03_unknown_vs_wrong_password_identical() {
    let h = harness(120).await;
    create_account(&h, 2, "synthetic hash", "active", false).await;
    let unknown_router = http_router(&h);
    let wrong_router = http_router(&h);
    let unknown = send(
        &unknown_router,
        http_request(
            "missing@example.test",
            "synthetic wrong password",
            "web",
            None,
        ),
    )
    .await;
    let wrong = send(
        &wrong_router,
        http_request(
            "account-2@example.test",
            "synthetic wrong password",
            "web",
            None,
        ),
    )
    .await;

    assert_eq!(unknown.status, StatusCode::UNAUTHORIZED);
    assert_eq!(unknown.status, wrong.status);
    let mut unknown_body = unknown.json;
    let mut wrong_body = wrong.json;
    unknown_body
        .as_object_mut()
        .expect("problem is an object")
        .remove("request_id");
    wrong_body
        .as_object_mut()
        .expect("problem is an object")
        .remove("request_id");
    assert_eq!(unknown_body, wrong_body);
    let mut unknown_headers = unknown.headers;
    let mut wrong_headers = wrong.headers;
    unknown_headers.remove("x-request-id");
    wrong_headers.remove("x-request-id");
    assert_eq!(unknown_headers, wrong_headers);
}

#[tokio::test]
async fn id_fr_03_unknown_email_does_dummy_work() {
    let h = harness(120).await;
    let error = h
        .service
        .login(
            request_for(
                "missing-3@example.test",
                "synthetic wrong password",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await
        .expect_err("unknown account should fail authentication");
    assert_eq!(error.code, ErrorCode::InvalidCredentials);
    assert_eq!(h.passwords.dummy_verifies.load(Ordering::Relaxed), 1);
    assert_eq!(h.passwords.verifies.load(Ordering::Relaxed), 0);
    for attempt in 2..=5 {
        let error = h
            .service
            .login(
                request_for(
                    "missing-3@example.test",
                    "synthetic wrong password",
                    "web",
                    None,
                ),
                kinevo_mod_identity::app::refresh::Client::Web,
                ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
                policy(),
            )
            .await
            .expect_err("unknown account attempts should remain indistinguishable");
        assert_eq!(
            error.code,
            ErrorCode::InvalidCredentials,
            "attempt {attempt}"
        );
    }
    let limited = h
        .service
        .login(
            request_for(
                "missing-3@example.test",
                "synthetic wrong password",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await
        .expect_err("unknown email should be throttled after the configured attempts");
    assert_eq!(limited.code, ErrorCode::RateLimited);
    assert_eq!(h.passwords.dummy_verifies.load(Ordering::Relaxed), 5);
}

#[tokio::test]
async fn id_fr_04_lockout_lifecycle() {
    let h = harness(120).await;
    let account = create_account(&h, 4, "synthetic hash", "active", false).await;
    for _ in 0..5 {
        let error = h
            .service
            .login(
                request_for(
                    "account-4@example.test",
                    "synthetic wrong password",
                    "web",
                    None,
                ),
                kinevo_mod_identity::app::refresh::Client::Web,
                ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
                policy(),
            )
            .await
            .expect_err("bad password should fail");
        assert_eq!(error.code, ErrorCode::InvalidCredentials);
    }

    let locked = h
        .service
        .login(
            request_for(
                "account-4@example.test",
                "synthetic correct password",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await
        .expect_err("the next request should see the lock");
    assert_eq!(locked.code, ErrorCode::AccountLocked);
    let response = kinevo_http::problem::ApiError(locked).into_response();
    assert_eq!(
        response
            .headers()
            .get(header::RETRY_AFTER)
            .and_then(|v| v.to_str().ok()),
        Some("900")
    );

    h.clock
        .advance_seconds(900)
        .expect("clock should reach lock expiry");
    h.service
        .login(
            request_for(
                "account-4@example.test",
                "synthetic correct password",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await
        .expect("login should work at lock expiry");
    let row = sqlx::query("SELECT failed_login_count, locked_until IS NULL AS unlocked FROM public.accounts WHERE id=$1")
        .bind(*account.as_uuid())
        .fetch_one(h.app.admin_pool())
        .await.expect("lockout should be queryable");
    assert_eq!(
        row.try_get::<i32, _>("failed_login_count")
            .expect("count is integer"),
        0
    );
    assert!(
        row.try_get::<bool, _>("unlocked")
            .expect("lock flag is boolean")
    );
}

#[tokio::test]
async fn id_fr_04_window_resets_counter() {
    let h = harness(120).await;
    let account = create_account(&h, 5, "synthetic hash", "active", false).await;
    for _ in 0..2 {
        let _ = h
            .service
            .login(
                request_for(
                    "account-5@example.test",
                    "synthetic wrong password",
                    "web",
                    None,
                ),
                kinevo_mod_identity::app::refresh::Client::Web,
                ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
                policy(),
            )
            .await;
    }
    h.clock
        .advance_seconds(901)
        .expect("clock should pass the failure window");
    let _ = h
        .service
        .login(
            request_for(
                "account-5@example.test",
                "synthetic wrong password",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await;
    let row = sqlx::query("SELECT failed_login_count, to_char(failed_window_start AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS started FROM public.accounts WHERE id=$1")
        .bind(*account.as_uuid())
        .fetch_one(h.app.admin_pool())
        .await.expect("failure window should be queryable");
    assert_eq!(
        row.try_get::<i32, _>("failed_login_count")
            .expect("count is integer"),
        1
    );
    let started = row
        .try_get::<String, _>("started")
        .expect("window start is text");
    assert_eq!(
        Instant::parse_rfc3339(&started).expect("window start is a valid instant"),
        h.clock.now()
    );
}

#[tokio::test]
async fn mfa_branches() {
    let h = harness(120).await;
    let account = create_account(&h, 6, "synthetic hash", "active", true).await;
    let missing = h
        .service
        .login(
            request_for(
                "account-6@example.test",
                "synthetic correct password",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await
        .expect_err("missing second factor should be requested");
    assert_eq!(missing.code, ErrorCode::MfaRequired);
    let methods = missing
        .details
        .as_map()
        .get("methods")
        .expect("methods detail should exist");
    assert_eq!(
        serde_json::to_value(methods).expect("methods serialize"),
        serde_json::json!(["totp", "recovery_code"])
    );

    let wrong = h
        .service
        .login(
            request_for(
                "account-6@example.test",
                "synthetic correct password",
                "web",
                Some("000000"),
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await
        .expect_err("invalid second factor should fail");
    assert_eq!(wrong.code, ErrorCode::InvalidMfaCode);

    let success = h
        .service
        .login(
            request_for(
                "account-6@example.test",
                "synthetic correct password",
                "web",
                Some("123456"),
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await
        .expect("valid second factor should succeed");
    let claims = h
        .codec
        .verify(&success.access_token, h.clock.now())
        .expect("access token should verify");
    assert!(claims.mfa);
    let failures =
        sqlx::query_scalar::<_, i32>("SELECT failed_login_count FROM public.accounts WHERE id=$1")
            .bind(*account.as_uuid())
            .fetch_one(h.app.admin_pool())
            .await
            .expect("MFA failure should be counted");
    assert_eq!(failures, 0);
}

#[tokio::test]
async fn account_disabled_after_correct_credentials() {
    let h = harness(120).await;
    let account = create_account(&h, 7, "synthetic hash", "disabled", false).await;
    let error = h
        .service
        .login(
            request_for(
                "account-7@example.test",
                "synthetic correct password",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await
        .expect_err("disabled account should not log in");
    assert_eq!(error.code, ErrorCode::AccountDisabled);
    let families = sqlx::query_scalar::<_, i64>(
        "SELECT count(*) FROM public.refresh_token_families WHERE account_id=$1",
    )
    .bind(*account.as_uuid())
    .fetch_one(h.app.admin_pool())
    .await
    .expect("family count is queryable");
    assert_eq!(families, 0);
}

#[tokio::test]
async fn password_rehash_on_login() {
    let h = harness(120).await;
    let account = create_account(&h, 8, "synthetic old hash", "active", false).await;
    h.service
        .login(
            request_for(
                "account-8@example.test",
                "synthetic correct password",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .await
        .expect("valid password should log in and rehash");
    let hash =
        sqlx::query_scalar::<_, String>("SELECT password_hash FROM public.accounts WHERE id=$1")
            .bind(*account.as_uuid())
            .fetch_one(h.app.admin_pool())
            .await
            .expect("hash should be queryable");
    assert_eq!(hash, "synthetic upgraded hash");
}

#[tokio::test]
async fn sec_ac_03_rate_limit_before_work() {
    let h = harness(5).await;
    create_account(&h, 9, "synthetic hash", "active", false).await;
    let router = http_router(&h);
    let mut responses = Vec::new();
    for _ in 0..6 {
        responses.push(
            send(
                &router,
                http_request(
                    "account-9@example.test",
                    "synthetic wrong password",
                    "web",
                    None,
                ),
            )
            .await,
        );
    }
    assert_eq!(responses.len(), 6);
    assert_eq!(responses[5].status, StatusCode::TOO_MANY_REQUESTS);
    assert_eq!(responses[5].json["code"], "rate_limited");
    assert_eq!(h.passwords.verifies.load(Ordering::Relaxed), 5);
    assert_eq!(
        responses[5]
            .headers
            .get("ratelimit-limit")
            .and_then(|v| v.to_str().ok()),
        Some("5")
    );
}

#[derive(Clone, Default)]
struct CapturingSubscriber(Arc<Mutex<Vec<BTreeMap<String, String>>>>);

impl Subscriber for CapturingSubscriber {
    fn enabled(&self, _metadata: &tracing::Metadata<'_>) -> bool {
        true
    }
    fn new_span(&self, _span: &span::Attributes<'_>) -> span::Id {
        span::Id::from_u64(1)
    }
    fn record(&self, _span: &span::Id, _values: &span::Record<'_>) {}
    fn record_follows_from(&self, _span: &span::Id, _follows: &span::Id) {}
    fn event(&self, event: &Event<'_>) {
        let mut values = BTreeMap::new();
        event.record(&mut CapturedFields(&mut values));
        self.0
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
            .push(values);
    }
    fn enter(&self, _span: &span::Id) {}
    fn exit(&self, _span: &span::Id) {}
}

struct CapturedFields<'a>(&'a mut BTreeMap<String, String>);

impl Visit for CapturedFields<'_> {
    fn record_debug(&mut self, field: &Field, value: &dyn fmt::Debug) {
        self.0.insert(field.name().to_owned(), format!("{value:?}"));
    }
    fn record_str(&mut self, field: &Field, value: &str) {
        self.0.insert(field.name().to_owned(), value.to_owned());
    }
}

#[tokio::test]
async fn sec_ac_45_no_credentials_in_logs_or_audit() {
    let h = harness(120).await;
    let account = create_account(&h, 10, "synthetic hash", "active", false).await;
    let logs = CapturingSubscriber::default();
    let failed = h
        .service
        .login(
            request_for(
                "account-10@example.test",
                "canary-password-failure",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .with_subscriber(logs.clone())
        .await;
    assert_eq!(
        failed.expect_err("wrong canary password should fail").code,
        ErrorCode::InvalidCredentials
    );
    let succeeded = h
        .service
        .login(
            request_for(
                "account-10@example.test",
                "synthetic correct password",
                "web",
                None,
            ),
            kinevo_mod_identity::app::refresh::Client::Web,
            ClientAddr::new("192.0.2.10".parse().expect("synthetic address is valid")),
            policy(),
        )
        .with_subscriber(logs.clone())
        .await
        .expect("success should create a session");
    let serialized_logs = format!(
        "{:?}",
        logs.0
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
    );
    assert!(!serialized_logs.contains("canary-password-failure"));
    assert!(!serialized_logs.contains("synthetic correct password"));
    assert!(!serialized_logs.contains(succeeded.refresh_token.expose()));
    let audit = sqlx::query_scalar::<_, String>(
        "SELECT details::text FROM public.audit_log WHERE account_id=$1",
    )
    .bind(*account.as_uuid())
    .fetch_all(h.app.admin_pool())
    .await
    .expect("audit details should be queryable");
    let audit = format!("{audit:?}");
    assert!(!audit.contains("canary-password-failure"));
    assert!(!audit.contains("synthetic correct password"));
    assert!(!audit.contains(succeeded.refresh_token.expose()));
}

#[tokio::test]
async fn d_37_android_receives_token_in_body() {
    let h = harness(120).await;
    create_account(&h, 11, "synthetic hash", "active", false).await;
    let router = http_router(&h);
    let response = send(
        &router,
        http_request(
            "account-11@example.test",
            "synthetic correct password",
            "android",
            None,
        ),
    )
    .await;
    assert_eq!(response.status, StatusCode::OK);
    assert!(response.json["refresh_token"].is_string());
    assert!(!response.headers.contains_key(header::SET_COOKIE));
}
