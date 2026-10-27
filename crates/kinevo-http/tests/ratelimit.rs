#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "acceptance tests use explicit synthetic fixtures"
)]

use async_trait::async_trait;
use axum::{
    Router,
    body::{Body, to_bytes},
    extract::ConnectInfo,
    http::{Method, Request, Response, StatusCode, header},
    routing::get,
};
use kinevo_http::{
    auth::AccountCache,
    gate::{Gate, ModuleKinds},
    i18n::Catalog,
    ratelimit::{RateLimiter, TrustedProxies},
    rbac::PermissionTable,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::{HttpState, ReadyCheck, ReadyFailure},
    token::{Claims, FamilyId, KeyRing, TokenCodec},
};
use kinevo_kernel::{
    errors::AppError,
    id::{AccountId, IdGen, TokenGen},
    page::CursorCodec,
    policy::{PolicyValue, ResolutionSource, Resolved},
    secret::Secret,
    time::{Clock, Instant},
    validate::{net::HttpsUrl, tz::Locale},
};
use kinevo_ports::{
    fakes::{FakeAccountAccess, FakeModuleSwitches, FakePolicyReader},
    module::{ModuleDescriptor, ModuleKind, PermissionDef},
    platform::{AccountAccessInfo, AccountStatus, InstanceInfo, InstanceState, ResolvedPolicies},
};
use serde_json::Value;
use std::{
    collections::BTreeMap,
    error::Error,
    net::{IpAddr, Ipv4Addr, SocketAddr},
    sync::{
        Arc, RwLock,
        atomic::{AtomicUsize, Ordering},
    },
};
use tower::ServiceExt;
use uuid::Uuid;

const IDENTITY_PERMISSIONS: &[PermissionDef] = &[PermissionDef {
    key: "identity.account.read",
    owner: true,
}];

const fn descriptor(
    key: &'static str,
    kind: ModuleKind,
    permissions: &'static [PermissionDef],
) -> ModuleDescriptor {
    ModuleDescriptor {
        key,
        kind,
        requires: &[],
        permissions,
        policy_keys: &[],
        nav: &[],
        settings_panels: &[],
        message_namespaces: &[],
        jobs: &[],
        job_schedules: &[],
        subscriptions: &[],
    }
}

const KERNEL: ModuleDescriptor = descriptor("kernel", ModuleKind::Core, &[]);
const IDENTITY: ModuleDescriptor = descriptor("identity", ModuleKind::Core, IDENTITY_PERMISSIONS);
const DESCRIPTORS: &[&ModuleDescriptor] = &[&KERNEL, &IDENTITY];

struct MutableClock(RwLock<Instant>);

impl MutableClock {
    fn new(now: Instant) -> Self {
        Self(RwLock::new(now))
    }

    fn advance(&self, seconds: i64) -> Result<(), Box<dyn Error>> {
        let mut now = self
            .0
            .write()
            .unwrap_or_else(std::sync::PoisonError::into_inner);
        *now = now
            .checked_add_seconds(seconds)
            .ok_or("clock advance is outside supported range")?;
        Ok(())
    }
}

impl Clock for MutableClock {
    fn now(&self) -> Instant {
        *self
            .0
            .read()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
    }
}

struct TestIds;

impl IdGen for TestIds {
    fn new_uuid_v7(&self) -> Uuid {
        Uuid::from_u128(100)
    }
}

struct TestTokens;

impl TokenGen for TestTokens {
    fn fill(&self, buffer: &mut [u8]) {
        buffer.fill(7);
    }
}

struct TestInstance;

#[async_trait]
impl InstanceState for TestInstance {
    async fn get(&self) -> Result<InstanceInfo, AppError> {
        Ok(InstanceInfo {
            setup_completed: true,
            base_url: None,
            default_locale: Locale::En,
        })
    }
}

struct TestReady;

#[async_trait]
impl ReadyCheck for TestReady {
    async fn check(&self) -> Result<(), ReadyFailure> {
        Ok(())
    }
}

struct Fixture {
    state: HttpState,
    limiter: Arc<RateLimiter>,
    policy_reader: Arc<FakePolicyReader>,
    clock: Arc<MutableClock>,
    codec: Arc<TokenCodec>,
    owner: AccountId,
    other: AccountId,
}

impl Fixture {
    fn token(&self, account_id: AccountId) -> String {
        let now = self.clock.now();
        self.codec.issue(&Claims {
            sub: account_id,
            sid: FamilyId::from_uuid(Uuid::from_u128(200)),
            jti: Uuid::from_u128(201),
            iat: now,
            exp: now
                .checked_add_seconds(600)
                .expect("test token expiry is valid"),
            mfa: false,
        })
    }
}

fn policy_value(value: i64) -> Resolved {
    Resolved {
        value: PolicyValue::Int(value),
        source: ResolutionSource::Instance,
        version_id: None,
        out_of_bounds: false,
    }
}

fn policies(auth: i64, api: i64) -> ResolvedPolicies {
    let mut values = BTreeMap::new();
    values.insert("ratelimit.auth_per_minute".to_owned(), policy_value(auth));
    values.insert("ratelimit.api_per_minute".to_owned(), policy_value(api));
    values.insert("ratelimit.ai_per_minute".to_owned(), policy_value(10));
    values.insert("ratelimit.upload_per_minute".to_owned(), policy_value(20));
    values.insert("ratelimit.sync_per_minute".to_owned(), policy_value(120));
    values.insert("ratelimit.export_per_minute".to_owned(), policy_value(10));
    ResolvedPolicies::new(values)
}

fn fixture(auth_limit: i64, api_limit: i64) -> Result<Fixture, Box<dyn Error>> {
    let now = Instant::parse_rfc3339("2026-10-28T00:00:00Z")?;
    let clock = Arc::new(MutableClock::new(now));
    let owner = AccountId::from_uuid(Uuid::from_u128(1));
    let other = AccountId::from_uuid(Uuid::from_u128(2));
    let account_access = Arc::new(FakeAccountAccess::default());
    for id in [owner, other] {
        account_access.insert(AccountAccessInfo {
            id,
            status: AccountStatus::Active,
            is_instance_admin: false,
            version: 1,
        })?;
    }
    let accounts = Arc::new(AccountCache::new(account_access));
    let switches = Arc::new(FakeModuleSwitches::default());
    let permissions = Arc::new(PermissionTable::from_descriptors(DESCRIPTORS)?);
    let module_kinds = Arc::new(ModuleKinds::from_descriptors(DESCRIPTORS)?);
    let codec = Arc::new(TokenCodec::new(KeyRing::new(
        "current",
        vec![("current".to_owned(), Secret::new([1; 32]))],
    )?));
    let gate = Arc::new(Gate::new(
        codec.clone(),
        accounts,
        switches,
        permissions,
        module_kinds,
    ));
    let policy_reader = Arc::new(FakePolicyReader::new(policies(auth_limit, api_limit)));
    let limiter = Arc::new(RateLimiter::new(
        policy_reader.clone(),
        clock.clone(),
        TrustedProxies::parse("").map_err(|_| "empty proxy list is invalid")?,
    ));
    let state = HttpState::new(
        clock.clone(),
        Arc::new(TestIds),
        Arc::new(TestTokens),
        Arc::new(Catalog::load()),
        Arc::new(TestInstance),
        Arc::new(TestReady),
        Some(HttpsUrl::parse("https://example.test").map_err(|_| "invalid test URL")?),
        Arc::new(CursorCodec::new(Secret::new([0; 32]))),
        1024,
        gate,
    )
    .with_rate_limiter(limiter.clone());
    Ok(Fixture {
        state,
        limiter,
        policy_reader,
        clock,
        codec,
        owner,
        other,
    })
}

fn router(
    state: HttpState,
    access: Access,
    rate: RateClass,
    handler: axum::routing::MethodRouter<HttpState>,
) -> Result<Router, Box<dyn Error>> {
    let spec = RouteSpec {
        method: Method::GET,
        path: "limited",
        module: "identity",
        access,
        rate,
        idempotency: IdemMode::Off,
        summary: "rate limit test route",
        allow_disabled: false,
    };
    Ok(RouteTable::new().route(spec, handler).into_router(state)?)
}

fn request(path: &str, token: Option<&str>, peer: IpAddr) -> Result<Request<Body>, Box<dyn Error>> {
    let mut builder = Request::builder().method(Method::GET).uri(path);
    if let Some(token) = token {
        builder = builder.header(header::AUTHORIZATION, format!("Bearer {token}"));
    }
    let mut request = builder.body(Body::empty())?;
    request
        .extensions_mut()
        .insert(ConnectInfo(SocketAddr::new(peer, 12345)));
    Ok(request)
}

async fn send(app: Router, request: Request<Body>) -> Result<Response<Body>, Box<dyn Error>> {
    Ok(app.oneshot(request).await?)
}

async fn json_body(response: Response<Body>) -> Result<Value, Box<dyn Error>> {
    Ok(serde_json::from_slice(
        &to_bytes(response.into_body(), 2048).await?,
    )?)
}

#[allow(
    clippy::needless_pass_by_value,
    reason = "Axum test handlers own their shared call counter"
)]
fn ok(calls: Arc<AtomicUsize>) -> std::future::Ready<StatusCode> {
    calls.fetch_add(1, Ordering::SeqCst);
    std::future::ready(StatusCode::OK)
}

fn assert_rate_headers(response: &Response<Body>, limit: u32, remaining: u32) {
    assert_eq!(response.headers()["RateLimit-Limit"], limit.to_string());
    assert_eq!(
        response.headers()["RateLimit-Remaining"],
        remaining.to_string()
    );
    assert!(response.headers().contains_key("RateLimit-Reset"));
}

mod ratelimit {
    use super::*;

    #[tokio::test]
    async fn api_50_sixth_request_is_limited() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(5, 10)?;
        let calls = Arc::new(AtomicUsize::new(0));
        let app = router(
            fixture.state.clone(),
            Access::Public,
            RateClass::Auth,
            get({
                let calls = calls.clone();
                move || ok(calls.clone())
            }),
        )?;
        let peer = IpAddr::V4(Ipv4Addr::new(8, 8, 8, 8));
        for remaining in (0..5).rev() {
            let response = send(app.clone(), request("/api/v1/limited", None, peer)?).await?;
            assert_eq!(response.status(), StatusCode::OK);
            assert_rate_headers(&response, 5, remaining);
        }
        let limited = send(app, request("/api/v1/limited", None, peer)?).await?;
        assert_eq!(limited.status(), StatusCode::TOO_MANY_REQUESTS);
        assert_eq!(limited.headers()[header::RETRY_AFTER], "60");
        assert_rate_headers(&limited, 5, 0);
        let body = json_body(limited).await?;
        assert_eq!(
            body.get("code").and_then(Value::as_str),
            Some("rate_limited")
        );
        assert_eq!(
            body.get("details")
                .and_then(|details| details.get("retry_after_seconds"))
                .and_then(Value::as_i64),
            Some(60)
        );
        assert_eq!(calls.load(Ordering::SeqCst), 5);
        Ok(())
    }

    #[tokio::test]
    async fn window_resets_with_injected_clock() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(5, 10)?;
        let peer = IpAddr::V4(Ipv4Addr::new(1, 2, 3, 4));
        let first = fixture
            .limiter
            .decide(RateClass::Auth, peer, None)
            .await?
            .ok_or("Auth must have a policy")?;
        assert!(first.allowed);
        assert_eq!(first.remaining, 4);
        fixture.clock.advance(60)?;
        let after_reset = fixture
            .limiter
            .decide(RateClass::Auth, peer, None)
            .await?
            .ok_or("Auth must have a policy")?;
        assert!(after_reset.allowed);
        assert_eq!(after_reset.remaining, 4);
        assert_eq!(after_reset.reset_seconds, 60);
        Ok(())
    }

    #[tokio::test]
    async fn api_51_authenticated_keyed_by_account() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(5, 10)?;
        let calls = Arc::new(AtomicUsize::new(0));
        let app = router(
            fixture.state.clone(),
            Access::Permission("identity.account.read"),
            RateClass::Api,
            get({
                let calls = calls.clone();
                move || ok(calls.clone())
            }),
        )?;
        let peer = IpAddr::V4(Ipv4Addr::new(8, 8, 4, 4));
        for account in [fixture.owner, fixture.other] {
            let token = fixture.token(account);
            for _ in 0..10 {
                let response =
                    send(app.clone(), request("/api/v1/limited", Some(&token), peer)?).await?;
                assert_eq!(response.status(), StatusCode::OK);
            }
        }
        assert_eq!(calls.load(Ordering::SeqCst), 20);
        Ok(())
    }

    mod tests {
        use super::*;

        #[test]
        fn api_51_trusted_proxy_resolution() -> Result<(), Box<dyn Error>> {
            let proxies =
                TrustedProxies::parse("10.0.0.0/8").map_err(|_| "valid proxy CIDR must parse")?;
            let header = Some("1.2.3.4, 10.0.0.5");
            assert_eq!(
                proxies.client_addr(IpAddr::V4(Ipv4Addr::new(10, 0, 0, 9)), header),
                IpAddr::V4(Ipv4Addr::new(1, 2, 3, 4))
            );
            assert_eq!(
                proxies.client_addr(IpAddr::V4(Ipv4Addr::new(8, 8, 8, 8)), header),
                IpAddr::V4(Ipv4Addr::new(8, 8, 8, 8))
            );
            assert_eq!(
                proxies.client_addr(
                    IpAddr::V4(Ipv4Addr::new(10, 0, 0, 9)),
                    Some("invalid, 10.0.0.5")
                ),
                IpAddr::V4(Ipv4Addr::new(10, 0, 0, 9))
            );
            Ok(())
        }
    }

    #[tokio::test]
    async fn policy_change_applies_within_30s() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(5, 10)?;
        let peer = IpAddr::V4(Ipv4Addr::new(9, 9, 9, 9));
        for _ in 0..2 {
            assert!(
                fixture
                    .limiter
                    .decide(RateClass::Auth, peer, None)
                    .await?
                    .ok_or("Auth must have a policy")?
                    .allowed
            );
        }
        fixture.policy_reader.replace(policies(2, 10))?;
        fixture.clock.advance(31)?;
        let third = fixture
            .limiter
            .decide(RateClass::Auth, peer, None)
            .await?
            .ok_or("Auth must have a policy")?;
        assert!(!third.allowed);
        assert_eq!(third.limit, 2);
        assert_eq!(third.remaining, 0);
        Ok(())
    }

    #[tokio::test]
    async fn none_class_skips() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(1, 10)?;
        let calls = Arc::new(AtomicUsize::new(0));
        let app = router(
            fixture.state,
            Access::Public,
            RateClass::None,
            get({
                let calls = calls.clone();
                move || ok(calls.clone())
            }),
        )?;
        let peer = IpAddr::V4(Ipv4Addr::new(7, 7, 7, 7));
        for _ in 0..20 {
            let response = send(app.clone(), request("/api/v1/limited", None, peer)?).await?;
            assert_eq!(response.status(), StatusCode::OK);
            assert!(!response.headers().contains_key("RateLimit-Limit"));
            assert!(!response.headers().contains_key("RateLimit-Remaining"));
            assert!(!response.headers().contains_key("RateLimit-Reset"));
        }
        assert_eq!(calls.load(Ordering::SeqCst), 20);
        Ok(())
    }

    #[tokio::test]
    async fn sec_ac_03_credential_stuffing_is_throttled() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(5, 10)?;
        let calls = Arc::new(AtomicUsize::new(0));
        let app = router(
            fixture.state,
            Access::Public,
            RateClass::Auth,
            get({
                let calls = calls.clone();
                move || ok(calls.clone())
            }),
        )?;
        let peer = IpAddr::V4(Ipv4Addr::new(3, 4, 5, 6));
        let mut limited = 0;
        for _ in 0..100 {
            let response = send(app.clone(), request("/api/v1/limited", None, peer)?).await?;
            if response.status() == StatusCode::TOO_MANY_REQUESTS {
                limited += 1;
            } else {
                assert_eq!(response.status(), StatusCode::OK);
            }
        }
        assert_eq!(limited, 95);
        assert_eq!(calls.load(Ordering::SeqCst), 5);
        Ok(())
    }

    #[tokio::test]
    async fn cac_1_bounded_keys() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(120, 120)?;
        for index in 0_u32..100_001 {
            let ip = IpAddr::V4(Ipv4Addr::from(index));
            assert!(
                fixture
                    .limiter
                    .decide(RateClass::Auth, ip, None)
                    .await?
                    .ok_or("Auth must have a policy")?
                    .allowed
            );
        }
        assert!(fixture.limiter.entry_count().await <= 100_000);
        Ok(())
    }
}
