#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "refresh HTTP acceptance tests use isolated synthetic fixtures"
)]

use std::{
    net::{IpAddr, Ipv4Addr, SocketAddr},
    sync::{
        Arc,
        atomic::{AtomicUsize, Ordering},
    },
};

use async_trait::async_trait;
use axum::{
    Router,
    body::{Body, to_bytes},
    extract::ConnectInfo,
    http::{HeaderMap, Method, Request, StatusCode, header},
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
    token::{Claims, KeyRing, TokenCodec},
};
use kinevo_kernel::{
    errors::AppError,
    id::{AccountId, FamilyId, IdGen},
    page::CursorCodec,
    policy::{PolicyValue, ResolutionSource, Resolved},
    secret::Secret,
    time::{Clock, FixedClock, Instant},
    validate::net::HttpsUrl,
};
use kinevo_mod_identity::{
    MODULE,
    app::refresh::RefreshService,
    domain::session::SessionPolicy,
    http::cookie::{
        REFRESH_COOKIE_PLAIN, REFRESH_COOKIE_SECURE, build_refresh_cookie, clear_refresh_cookie,
        read_refresh_cookie,
    },
    infra::sessions::{self, NewSession},
    migrations,
};
use kinevo_ports::{
    fakes::{FakeAccountAccess, FakeInstanceState, FakeModuleSwitches},
    module::ModuleDescriptor,
    platform::{AccountAccessInfo, AccountStatus, InstanceInfo, PolicyReader, ResolvedPolicies},
};
use sqlx::{PgPool, Row};
use tower::ServiceExt;

const ACCESS_TTL: u32 = 600;
const IDLE_TTL: u32 = 1_209_600;
const ABSOLUTE_TTL: u32 = 2_592_000;
const GRACE: u32 = 10;
const TEST_ORIGIN: &str = "https://api.example.test";

fn now() -> Instant {
    Instant::parse_rfc3339("2026-11-04T00:00:00Z").expect("fixed test instant is valid")
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

fn resolved(value: PolicyValue) -> Resolved {
    Resolved {
        value,
        source: ResolutionSource::Default,
        version_id: None,
        out_of_bounds: false,
    }
}

struct CountingPolicyReader {
    values: ResolvedPolicies,
    reads: AtomicUsize,
}

impl CountingPolicyReader {
    fn new() -> Self {
        let mut values = ResolvedPolicies::default();
        for (key, value) in [
            (
                "auth.access_token.ttl",
                PolicyValue::Seconds(i64::from(ACCESS_TTL)),
            ),
            (
                "auth.refresh_token.idle_ttl",
                PolicyValue::Seconds(i64::from(IDLE_TTL)),
            ),
            (
                "auth.refresh_token.absolute_ttl",
                PolicyValue::Seconds(i64::from(ABSOLUTE_TTL)),
            ),
            ("auth.refresh.grace", PolicyValue::Seconds(i64::from(GRACE))),
            ("ratelimit.auth_per_minute", PolicyValue::Int(120)),
            ("ratelimit.api_per_minute", PolicyValue::Int(120)),
        ] {
            values.insert(key, resolved(value));
        }
        Self {
            values,
            reads: AtomicUsize::new(0),
        }
    }

    fn reset_reads(&self) {
        self.reads.store(0, Ordering::Relaxed);
    }
}

#[async_trait]
impl PolicyReader for CountingPolicyReader {
    async fn snapshot_values(
        &self,
        _account: Option<AccountId>,
        _at: Instant,
    ) -> Result<ResolvedPolicies, AppError> {
        self.reads.fetch_add(1, Ordering::Relaxed);
        Ok(self.values.clone())
    }
}

struct Harness {
    service: Arc<RefreshService>,
    app: TestApp,
    clock: Arc<FixedClock>,
    codec: Arc<TokenCodec>,
    policies: Arc<CountingPolicyReader>,
    account_access: Arc<FakeAccountAccess>,
}

fn policy(absolute_ttl: u32) -> SessionPolicy {
    SessionPolicy {
        access_ttl: ACCESS_TTL,
        idle_ttl: IDLE_TTL,
        absolute_ttl,
        grace: GRACE,
    }
}

async fn harness() -> Harness {
    let app = test_app().await;
    let clock = Arc::new(FixedClock::new(now()));
    let codec = Arc::new(TokenCodec::new(
        KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
            .expect("synthetic test key ring is valid"),
    ));
    let policies = Arc::new(CountingPolicyReader::new());
    let service = Arc::new(RefreshService::new(
        app.db.clone(),
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
        account_access: Arc::new(FakeAccountAccess::default()),
    }
}

async fn account(h: &Harness) -> AccountId {
    let account = h.app.new_account();
    let email = format!("refresh-{}@example.test", account.as_uuid());
    let mut tx = Tx::begin(&h.app.db, account)
        .await
        .expect("account transaction should begin");
    sqlx::query(
        "INSERT INTO public.accounts (id, email, display_name, password_hash, timezone) \
         VALUES ($1, $2, $3, $4, $5)",
    )
    .bind(*account.as_uuid())
    .bind(email)
    .bind("Synthetic Account")
    .bind("synthetic-password-hash")
    .bind("UTC")
    .execute(tx.conn())
    .await
    .expect("synthetic account should insert");
    tx.commit()
        .await
        .expect("account transaction should commit");
    h.account_access
        .insert(AccountAccessInfo {
            id: account,
            status: AccountStatus::Active,
            is_instance_admin: false,
            version: 1,
        })
        .expect("synthetic authorization row should insert");
    account
}

async fn family(h: &Harness, account: AccountId, ordinal: u8, policy: SessionPolicy) -> NewSession {
    let mut tx = Tx::begin(&h.app.db, account)
        .await
        .expect("account transaction should begin");
    let session = sessions::create_family_with_token(
        &mut tx,
        h.app.ids.as_ref(),
        h.app.tokens.as_ref(),
        h.clock.now(),
        account,
        Some(&format!("synthetic-device-{ordinal}")),
        false,
        policy,
    )
    .await
    .expect("synthetic session should be created");
    tx.commit().await.expect("family transaction should commit");
    session
}

fn http_router(h: &Harness, public_origin: &str) -> Router {
    let descriptors: [&ModuleDescriptor; 1] = [&MODULE];
    let permissions =
        Arc::new(PermissionTable::from_descriptors(&descriptors).expect("permissions are valid"));
    let module_kinds =
        Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds are valid"));
    let gate = Arc::new(Gate::new(
        h.codec.clone(),
        Arc::new(AccountCache::new(h.account_access.clone())),
        Arc::new(FakeModuleSwitches::default()),
        permissions,
        module_kinds,
    ));
    let instance = Arc::new(FakeInstanceState::new(InstanceInfo {
        setup_completed: true,
        base_url: None,
        default_locale: kinevo_kernel::validate::tz::Locale::En,
    }));
    let state = HttpState::new(
        h.clock.clone(),
        h.app.ids.clone(),
        h.app.tokens.clone(),
        Arc::new(Catalog::load()),
        instance,
        Arc::new(TestReady),
        Some(HttpsUrl::parse(TEST_ORIGIN).expect("test URL is valid")),
        Arc::new(CursorCodec::new(Secret::new([0_u8; 32]))),
        1_048_576,
        gate,
    )
    .with_origin_config(OriginConfig {
        public_origin: Some(public_origin.to_owned()),
        android_origin_enabled: true,
    })
    .with_rate_limiter(Arc::new(RateLimiter::new(
        h.policies.clone(),
        h.clock.clone(),
        TrustedProxies::default(),
    )));
    kinevo_mod_identity::refresh_router(h.service.clone(), h.policies.clone())
        .into_router(state)
        .expect("identity refresh routes should validate")
}

struct TestReady;

#[async_trait]
impl ReadyCheck for TestReady {
    async fn check(&self) -> Result<(), ReadyFailure> {
        Ok(())
    }
}

fn request(
    method: Method,
    path: &str,
    client: Option<&str>,
    origin: Option<&str>,
    cookie: Option<&str>,
    bearer: Option<&str>,
    body: Option<String>,
) -> Request<Body> {
    let mut builder = Request::builder().method(method).uri(path);
    if let Some(client) = client {
        builder = builder.header("X-Kinevo-Client", client);
    }
    if let Some(origin) = origin {
        builder = builder.header(header::ORIGIN, origin);
    }
    if let Some(cookie) = cookie {
        builder = builder.header(header::COOKIE, cookie);
    }
    if let Some(bearer) = bearer {
        builder = builder.header(header::AUTHORIZATION, format!("Bearer {bearer}"));
    }
    if body.is_some() {
        builder = builder.header(header::CONTENT_TYPE, "application/json");
    }
    let mut request = builder
        .body(Body::from(body.unwrap_or_default()))
        .expect("synthetic HTTP request is valid");
    request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
        IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
        12345,
    )));
    request
}

struct Observed {
    status: StatusCode,
    headers: HeaderMap,
    body: Vec<u8>,
}

impl Observed {
    fn json(&self) -> serde_json::Value {
        serde_json::from_slice(&self.body).expect("response body should be JSON")
    }
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
        body: bytes.to_vec(),
    }
}

fn cookie_pair(secure: bool, token: &str) -> String {
    let name = if secure {
        REFRESH_COOKIE_SECURE
    } else {
        REFRESH_COOKIE_PLAIN
    };
    format!("{name}={token}")
}

fn response_cookie(response: &Observed) -> String {
    response
        .headers
        .get(header::SET_COOKIE)
        .expect("response should set a refresh cookie")
        .to_str()
        .expect("synthetic cookie header is valid")
        .to_owned()
}

fn cookie_token(cookie: &str) -> String {
    cookie
        .split(';')
        .next()
        .expect("cookie has a name and value")
        .split_once('=')
        .expect("cookie has an equals separator")
        .1
        .to_owned()
}

async fn family_state(
    pool: &PgPool,
    family_id: FamilyId,
) -> (bool, Option<String>, Option<String>) {
    let row = sqlx::query(
        "SELECT revoked_at IS NOT NULL AS revoked, revoked_reason, \
                to_char(revoked_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS revoked_at \
         FROM public.refresh_token_families WHERE id = $1",
    )
    .bind(*family_id.as_uuid())
    .fetch_one(pool)
    .await
    .expect("family state should be queryable");
    (
        row.try_get("revoked").expect("revoked column is boolean"),
        row.try_get("revoked_reason")
            .expect("revocation reason is nullable text"),
        row.try_get("revoked_at")
            .expect("revocation instant is nullable text"),
    )
}

async fn absolute_expiry(pool: &PgPool, family_id: FamilyId) -> String {
    sqlx::query_scalar(
        "SELECT to_char(absolute_expires_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') \
         FROM public.refresh_token_families WHERE id = $1",
    )
    .bind(*family_id.as_uuid())
    .fetch_one(pool)
    .await
    .expect("absolute expiry should be queryable")
}

fn access_token(h: &Harness, account: AccountId, family_id: FamilyId) -> String {
    h.codec.issue(&Claims {
        sub: account,
        sid: family_id,
        jti: h.app.ids.new_uuid_v7(),
        iat: h.clock.now(),
        exp: h
            .clock
            .now()
            .checked_add_seconds(i64::from(ACCESS_TTL))
            .expect("access-token expiry should be representable"),
        mfa: false,
    })
}

#[tokio::test]
async fn id_fr_05_web_refresh_rotates_cookie() {
    let h = harness().await;
    let account = account(&h).await;
    let session = family(&h, account, 1, policy(ABSOLUTE_TTL)).await;
    let router = http_router(&h, TEST_ORIGIN);
    let response = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/refresh",
            Some("web"),
            Some(TEST_ORIGIN),
            Some(&cookie_pair(true, session.refresh_token.expose())),
            None,
            Some("{}".to_owned()),
        ),
    )
    .await;

    assert_eq!(response.status, StatusCode::OK);
    let body = response.json();
    assert!(body["access_token"].is_string());
    assert_eq!(body["expires_in"], ACCESS_TTL);
    assert!(body.get("refresh_token").is_none());
    let cookie = response_cookie(&response);
    assert!(cookie.starts_with("__Secure-kinevo_rt="));
    assert!(cookie.contains("HttpOnly"));
    assert!(cookie.contains("Secure"));
    assert!(cookie.contains("SameSite=Strict"));
    assert!(cookie.contains("Path=/api/v1/auth"));
    assert!(!cookie.contains("Domain="));
    assert_ne!(
        cookie_token(&cookie),
        session.refresh_token.expose().as_str()
    );
}

#[tokio::test]
async fn d_37_android_refresh_body_flow() {
    let h = harness().await;
    let account = account(&h).await;
    let session = family(&h, account, 2, policy(ABSOLUTE_TTL)).await;
    let router = http_router(&h, TEST_ORIGIN);
    let body = serde_json::json!({"refresh_token": session.refresh_token.expose()});
    let response = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/refresh",
            Some("android"),
            Some("https://app.kinevo.localhost"),
            None,
            None,
            Some(body.to_string()),
        ),
    )
    .await;

    assert_eq!(response.status, StatusCode::OK);
    let body = response.json();
    assert!(body["access_token"].is_string());
    assert!(body["refresh_token"].is_string());
    assert!(response.headers.get(header::SET_COOKIE).is_none());
}

#[tokio::test]
async fn sec_ac_05_csrf_refresh() {
    let h = harness().await;
    let router = http_router(&h, TEST_ORIGIN);
    h.policies.reset_reads();

    let evil_origin = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/refresh",
            Some("web"),
            Some("https://evil.example"),
            Some(&cookie_pair(true, "synthetic-invalid-token")),
            None,
            Some("{}".to_owned()),
        ),
    )
    .await;
    assert_eq!(evil_origin.status, StatusCode::FORBIDDEN);
    assert_eq!(evil_origin.json()["code"], "csrf_check_failed");
    assert_eq!(h.policies.reads.load(Ordering::Relaxed), 0);

    let missing_client = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/refresh",
            None,
            Some(TEST_ORIGIN),
            Some(&cookie_pair(true, "synthetic-invalid-token")),
            None,
            Some("{}".to_owned()),
        ),
    )
    .await;
    assert_eq!(missing_client.status, StatusCode::FORBIDDEN);
    assert_eq!(missing_client.json()["code"], "csrf_check_failed");
    assert_eq!(h.policies.reads.load(Ordering::Relaxed), 0);
}

#[tokio::test]
async fn sec_ac_04_replay_kills_family() {
    let h = harness().await;
    let account = account(&h).await;
    let session = family(&h, account, 3, policy(ABSOLUTE_TTL)).await;
    let router = http_router(&h, TEST_ORIGIN);
    let rotated = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/refresh",
            Some("web"),
            Some(TEST_ORIGIN),
            Some(&cookie_pair(true, session.refresh_token.expose())),
            None,
            Some("{}".to_owned()),
        ),
    )
    .await;
    assert_eq!(rotated.status, StatusCode::OK);
    let rotated_token = cookie_token(&response_cookie(&rotated));
    h.clock
        .advance_seconds(i64::from(GRACE) + 1)
        .expect("fake clock should advance past the grace window");

    let replay = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/refresh",
            Some("web"),
            Some(TEST_ORIGIN),
            Some(&cookie_pair(true, session.refresh_token.expose())),
            None,
            Some("{}".to_owned()),
        ),
    )
    .await;
    assert_eq!(replay.status, StatusCode::UNAUTHORIZED);
    assert_eq!(replay.json()["code"], "refresh_reuse_detected");
    assert!(response_cookie(&replay).contains("Max-Age=0"));
    assert_eq!(
        family_state(h.app.admin_pool(), session.family_id).await.1,
        Some("reuse_detected".to_owned())
    );

    let victim = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/refresh",
            Some("web"),
            Some(TEST_ORIGIN),
            Some(&cookie_pair(true, &rotated_token)),
            None,
            Some("{}".to_owned()),
        ),
    )
    .await;
    assert_eq!(victim.status, StatusCode::UNAUTHORIZED);
}

#[tokio::test]
async fn logout_is_idempotent() {
    let h = harness().await;
    let account = account(&h).await;
    let session = family(&h, account, 4, policy(ABSOLUTE_TTL)).await;
    let router = http_router(&h, TEST_ORIGIN);
    let logout_request = || {
        request(
            Method::POST,
            "/api/v1/auth/logout",
            Some("web"),
            Some(TEST_ORIGIN),
            Some(&cookie_pair(true, session.refresh_token.expose())),
            None,
            Some("{}".to_owned()),
        )
    };

    let first = send(&router, logout_request()).await;
    assert_eq!(first.status, StatusCode::NO_CONTENT);
    assert!(response_cookie(&first).contains("Max-Age=0"));
    let state_after_first = family_state(h.app.admin_pool(), session.family_id).await;
    assert!(state_after_first.0);
    assert_eq!(state_after_first.1, Some("logout".to_owned()));

    let second = send(&router, logout_request()).await;
    assert_eq!(second.status, StatusCode::NO_CONTENT);
    assert!(response_cookie(&second).contains("Max-Age=0"));
    assert_eq!(
        family_state(h.app.admin_pool(), session.family_id).await,
        state_after_first
    );
}

#[tokio::test]
async fn d_37_android_logout_body_flow() {
    let h = harness().await;
    let account = account(&h).await;
    let session = family(&h, account, 9, policy(ABSOLUTE_TTL)).await;
    let router = http_router(&h, TEST_ORIGIN);
    let body = serde_json::json!({"refresh_token": session.refresh_token.expose()});
    let response = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/logout",
            Some("android"),
            Some("https://app.kinevo.localhost"),
            None,
            None,
            Some(body.to_string()),
        ),
    )
    .await;

    assert_eq!(response.status, StatusCode::NO_CONTENT);
    assert!(response.headers.get(header::SET_COOKIE).is_none());
    let state = family_state(h.app.admin_pool(), session.family_id).await;
    assert!(state.0);
    assert_eq!(state.1, Some("logout".to_owned()));
    assert!(state.2.is_some());
}

#[tokio::test]
async fn logout_all_revokes_every_family() {
    let h = harness().await;
    let account = account(&h).await;
    let sessions = [
        family(&h, account, 5, policy(ABSOLUTE_TTL)).await,
        family(&h, account, 6, policy(ABSOLUTE_TTL)).await,
        family(&h, account, 7, policy(ABSOLUTE_TTL)).await,
    ];
    let token = access_token(&h, account, sessions[0].family_id);
    let router = http_router(&h, TEST_ORIGIN);
    let response = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/logout-all",
            None,
            None,
            None,
            Some(&token),
            None,
        ),
    )
    .await;

    assert_eq!(response.status, StatusCode::NO_CONTENT);
    assert!(response_cookie(&response).contains("Max-Age=0"));
    for session in &sessions {
        let state = family_state(h.app.admin_pool(), session.family_id).await;
        assert!(state.0);
        assert_eq!(state.1, Some("logout".to_owned()));
        let refreshed = send(
            &router,
            request(
                Method::POST,
                "/api/v1/auth/refresh",
                Some("web"),
                Some(TEST_ORIGIN),
                Some(&cookie_pair(true, session.refresh_token.expose())),
                None,
                Some("{}".to_owned()),
            ),
        )
        .await;
        assert_eq!(refreshed.status, StatusCode::UNAUTHORIZED);
    }
}

#[tokio::test]
async fn absolute_lifetime_not_extended() {
    let h = harness().await;
    let account = account(&h).await;
    let session = family(&h, account, 8, policy(3 * 24 * 60 * 60)).await;
    let expiry_before = absolute_expiry(h.app.admin_pool(), session.family_id).await;
    h.clock
        .advance_seconds(2 * 24 * 60 * 60)
        .expect("fake clock should advance");
    let router = http_router(&h, TEST_ORIGIN);
    let refreshed = send(
        &router,
        request(
            Method::POST,
            "/api/v1/auth/refresh",
            Some("web"),
            Some(TEST_ORIGIN),
            Some(&cookie_pair(true, session.refresh_token.expose())),
            None,
            Some("{}".to_owned()),
        ),
    )
    .await;

    assert_eq!(refreshed.status, StatusCode::OK);
    assert!(response_cookie(&refreshed).contains("Max-Age=86400"));
    assert_eq!(
        absolute_expiry(h.app.admin_pool(), session.family_id).await,
        expiry_before
    );
}

mod cookie {
    pub mod tests {
        use super::super::*;

        #[test]
        fn auth_cookie_attributes() {
            let secure = build_refresh_cookie("synthetic-token", 86_400, true)
                .expect("secure cookie header should be valid");
            assert_eq!(
                secure.to_str().expect("header is valid"),
                "__Secure-kinevo_rt=synthetic-token; HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth; Max-Age=86400"
            );
            let plain = build_refresh_cookie("synthetic-token", 86_400, false)
                .expect("development cookie header should be valid");
            assert_eq!(
                plain.to_str().expect("header is valid"),
                "kinevo_rt=synthetic-token; HttpOnly; SameSite=Strict; Path=/api/v1/auth; Max-Age=86400"
            );
            assert_eq!(
                clear_refresh_cookie(true)
                    .expect("secure clear header should be valid")
                    .to_str()
                    .expect("header is valid"),
                "__Secure-kinevo_rt=; HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth; Max-Age=0"
            );

            let headers = HeaderMap::from_iter([(
                header::COOKIE,
                format!("{REFRESH_COOKIE_SECURE}=synthetic-token")
                    .parse()
                    .unwrap(),
            )]);
            assert_eq!(
                read_refresh_cookie(&headers, true)
                    .expect("refresh cookie should be found")
                    .expose(),
                "synthetic-token"
            );
        }
    }
}
