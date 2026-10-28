#![allow(
    dead_code,
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "shared HTTP acceptance fixture is consumed selectively by sibling tests"
)]

use async_trait::async_trait;
use axum::{
    Router,
    body::{Body, Bytes, to_bytes},
    http::{HeaderMap, Method, Request, StatusCode},
    routing::MethodRouter,
};
use kinevo_http::{
    auth::AccountCache,
    gate::{Gate, ModuleKinds},
    i18n::Catalog,
    origin::OriginConfig,
    rbac::PermissionTable,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::{HttpState, ReadyCheck, ReadyFailure},
    token::{Claims, FamilyId, KeyRing, TokenCodec},
};
use kinevo_kernel::{
    errors::AppError,
    id::{AccountId, IdGen, TokenGen},
    page::CursorCodec,
    secret::Secret,
    time::{Clock, Instant},
    validate::{net::HttpsUrl, tz::Locale},
};
use kinevo_ports::{
    fakes::FakeAccountAccess,
    module::{ModuleDescriptor, ModuleKind, PermissionDef},
    platform::{AccountAccessInfo, AccountStatus, InstanceInfo, InstanceState},
};
use std::{error::Error, sync::Arc};
use tower::ServiceExt;
use uuid::Uuid;

const PERMISSIONS: &[PermissionDef] = &[PermissionDef {
    key: "identity.account.read",
    owner: true,
}];

const IDENTITY: ModuleDescriptor = ModuleDescriptor {
    key: "identity",
    kind: ModuleKind::Core,
    requires: &[],
    permissions: PERMISSIONS,
    policy_keys: &[],
    nav: &[],
    settings_panels: &[],
    message_namespaces: &[],
    jobs: &[],
    job_schedules: &[],
    subscriptions: &[],
};

struct FixedClock(Instant);

impl Clock for FixedClock {
    fn now(&self) -> Instant {
        self.0
    }
}

struct FixedIds;

impl IdGen for FixedIds {
    fn new_uuid_v7(&self) -> Uuid {
        Uuid::from_u128(900)
    }
}

struct FixedTokens;

impl TokenGen for FixedTokens {
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

pub struct Fixture {
    pub state: HttpState,
    codec: Arc<TokenCodec>,
    now: Instant,
    pub account_a: AccountId,
    pub account_b: AccountId,
}

impl Fixture {
    pub fn token(&self, account_id: AccountId) -> String {
        self.codec.issue(&Claims {
            sub: account_id,
            sid: FamilyId::from_uuid(Uuid::from_u128(901)),
            jti: Uuid::from_u128(902),
            iat: self.now,
            exp: self
                .now
                .checked_add_seconds(600)
                .expect("synthetic token expiry is in range"),
            mfa: false,
        })
    }
}

pub fn fixture() -> Result<Fixture, Box<dyn Error>> {
    let now = Instant::parse_rfc3339("2026-10-28T00:00:00Z")?;
    let account_a = AccountId::from_uuid(Uuid::from_u128(1));
    let account_b = AccountId::from_uuid(Uuid::from_u128(2));
    let account_access = Arc::new(FakeAccountAccess::default());
    for account_id in [account_a, account_b] {
        account_access.insert(AccountAccessInfo {
            id: account_id,
            status: AccountStatus::Active,
            is_instance_admin: false,
            version: 1,
        })?;
    }
    let descriptors = [&IDENTITY];
    let permissions = Arc::new(PermissionTable::from_descriptors(&descriptors)?);
    let module_kinds = Arc::new(ModuleKinds::from_descriptors(&descriptors)?);
    let codec = Arc::new(TokenCodec::new(KeyRing::new(
        "current",
        vec![("current".to_owned(), Secret::new([1; 32]))],
    )?));
    let gate = Arc::new(Gate::new(
        codec.clone(),
        Arc::new(AccountCache::new(account_access)),
        Arc::new(kinevo_ports::fakes::FakeModuleSwitches::default()),
        permissions,
        module_kinds,
    ));
    let state = HttpState::new(
        Arc::new(FixedClock(now)),
        Arc::new(FixedIds),
        Arc::new(FixedTokens),
        Arc::new(Catalog::load()),
        Arc::new(TestInstance),
        Arc::new(TestReady),
        Some(HttpsUrl::parse("https://api.example.test").map_err(|_| "invalid synthetic URL")?),
        Arc::new(CursorCodec::new(Secret::new([0; 32]))),
        1_048_576,
        gate,
    )
    .with_origin_config(OriginConfig {
        public_origin: Some("https://api.example.test".to_owned()),
        android_origin_enabled: true,
    });
    Ok(Fixture {
        state,
        codec,
        now,
        account_a,
        account_b,
    })
}

pub fn route_spec(
    method: Method,
    path: &'static str,
    access: Access,
    idempotency: IdemMode,
    store_response_body: bool,
    cookie_endpoint: bool,
) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "identity",
        access,
        rate: RateClass::None,
        idempotency,
        store_response_body,
        cookie_endpoint,
        summary: "synthetic acceptance route",
        allow_disabled: false,
    }
}

pub fn router(
    state: HttpState,
    spec: RouteSpec,
    method_router: MethodRouter<HttpState>,
) -> Result<Router, Box<dyn Error>> {
    Ok(RouteTable::new()
        .route(spec, method_router)
        .into_router(state)?)
}

pub fn request(method: Method, path: &str, token: Option<&str>, body: &[u8]) -> Request<Body> {
    let mut builder = Request::builder()
        .method(method)
        .uri(path)
        .header("content-type", "application/json");
    if let Some(token) = token {
        builder = builder.header("authorization", format!("Bearer {token}"));
    }
    builder
        .body(Body::from(body.to_vec()))
        .expect("synthetic request is valid")
}

pub struct Observed {
    pub status: StatusCode,
    pub headers: HeaderMap,
    pub body: Bytes,
}

pub async fn send(router: Router, request: Request<Body>) -> Result<Observed, Box<dyn Error>> {
    let response = router
        .oneshot(request)
        .await
        .map_err(|never| match never {})?;
    let (parts, body) = response.into_parts();
    Ok(Observed {
        status: parts.status,
        headers: parts.headers,
        body: to_bytes(body, usize::MAX).await?,
    })
}
