mod http_core {
    use axum::{
        Json, Router,
        body::{Body, to_bytes},
        http::{Method, Request, StatusCode, header},
        response::IntoResponse,
        routing::{get, post},
    };
    use kinevo_http::{
        auth::AccountCache,
        extract::{SortParam, StrictQuery, Validate, ValidatedJson},
        gate::{Gate, ModuleKinds},
        i18n::Catalog,
        problem::ApiError,
        rbac::PermissionTable,
        route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
        state::{HttpState, ReadyCheck, ReadyFailure},
        system_routes,
        token::{Claims, FamilyId, KeyRing, TokenCodec},
    };
    use kinevo_kernel::{
        errors::{AppError, DetailValue, ErrorCode, FieldCode},
        id::{AccountId, IdGen, TokenGen},
        page::CursorCodec,
        secret::Secret,
        time::{Clock, Instant},
        validate::{Collector, net::HttpsUrl, tz::Locale},
    };
    use kinevo_ports::{
        fakes::{FakeAccountAccess, FakeModuleSwitches},
        module::{ModuleDescriptor, ModuleKind, PermissionDef},
        platform::{AccountAccessInfo, AccountStatus, InstanceInfo, InstanceState},
    };
    use serde::{Deserialize, Serialize};
    use std::{
        error::Error,
        io,
        sync::{
            Arc, Mutex,
            atomic::{AtomicBool, Ordering},
        },
    };
    use tower::ServiceExt;

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

    const KERNEL_MODULE: ModuleDescriptor = descriptor("kernel", ModuleKind::Core, &[]);
    const TEST_MODULE: ModuleDescriptor = descriptor("test", ModuleKind::Core, &[]);
    const IDENTITY_MODULE: ModuleDescriptor =
        descriptor("identity", ModuleKind::Core, IDENTITY_PERMISSIONS);

    fn gate() -> Result<Arc<Gate>, Box<dyn Error>> {
        let descriptors = [&KERNEL_MODULE, &TEST_MODULE, &IDENTITY_MODULE];
        let permissions = Arc::new(PermissionTable::from_descriptors(&descriptors)?);
        let module_kinds = Arc::new(ModuleKinds::from_descriptors(&descriptors)?);
        let account_id = AccountId::from_uuid(uuid::Uuid::from_u128(111));
        let account_access = Arc::new(FakeAccountAccess::default());
        account_access.insert(AccountAccessInfo {
            id: account_id,
            status: AccountStatus::Active,
            is_instance_admin: false,
            version: 1,
        })?;
        let accounts = Arc::new(AccountCache::new(account_access));
        let modules = Arc::new(FakeModuleSwitches::default());
        let ring = KeyRing::new(
            "current",
            vec![("current".to_owned(), Secret::new([1; 32]))],
        )?;
        let tokens = Arc::new(TokenCodec::new(ring));
        Ok(Arc::new(Gate::new(
            tokens,
            accounts,
            modules,
            permissions,
            module_kinds,
        )))
    }

    fn time_token() -> Result<String, Box<dyn Error>> {
        let now = Instant::parse_rfc3339("2026-10-28T00:00:00Z")?;
        let ring = KeyRing::new(
            "current",
            vec![("current".to_owned(), Secret::new([1; 32]))],
        )?;
        let codec = TokenCodec::new(ring);
        Ok(codec.issue(&Claims {
            sub: AccountId::from_uuid(uuid::Uuid::from_u128(111)),
            sid: FamilyId::from_uuid(uuid::Uuid::from_u128(112)),
            jti: uuid::Uuid::from_u128(113),
            iat: now,
            exp: now.checked_add_seconds(600).ok_or("invalid token expiry")?,
            mfa: false,
        }))
    }

    struct TestClock(Instant);

    impl Clock for TestClock {
        fn now(&self) -> Instant {
            self.0
        }
    }

    struct TestIds;

    impl IdGen for TestIds {
        fn new_uuid_v7(&self) -> uuid::Uuid {
            uuid::Uuid::from_bytes([0, 0, 0, 0, 0, 0, 0x70, 0, 0x80, 0, 0, 0, 0, 0, 0, 1])
        }
    }

    struct TestTokens;

    impl TokenGen for TestTokens {
        fn fill(&self, buffer: &mut [u8]) {
            buffer.fill(0);
        }
    }

    struct TestInstance;

    #[async_trait::async_trait]
    impl InstanceState for TestInstance {
        async fn get(&self) -> Result<InstanceInfo, AppError> {
            Ok(InstanceInfo {
                setup_completed: true,
                base_url: None,
                default_locale: Locale::En,
            })
        }
    }

    struct TestReady(bool);

    #[async_trait::async_trait]
    impl ReadyCheck for TestReady {
        async fn check(&self) -> Result<(), ReadyFailure> {
            if self.0 { Ok(()) } else { Err(ReadyFailure) }
        }
    }

    fn state(
        ready: bool,
        public_url: Option<&str>,
        max_body_bytes: usize,
    ) -> Result<HttpState, Box<dyn Error>> {
        let instant = Instant::parse_rfc3339("2026-10-28T00:00:00Z")?;
        let public_url = public_url
            .map(HttpsUrl::parse)
            .transpose()
            .map_err(|_| io::Error::other("invalid test URL"))?;
        Ok(HttpState::new(
            Arc::new(TestClock(instant)),
            Arc::new(TestIds),
            Arc::new(TestTokens),
            Arc::new(Catalog::load()),
            Arc::new(TestInstance),
            Arc::new(TestReady(ready)),
            public_url,
            Arc::new(CursorCodec::new(Secret::new([0; 32]))),
            max_body_bytes,
            gate()?,
        ))
    }

    fn spec(method: Method, path: &'static str, access: Access) -> RouteSpec {
        RouteSpec {
            method,
            path,
            module: "test",
            access,
            rate: RateClass::None,
            idempotency: IdemMode::Off,
            store_response_body: true,
            cookie_endpoint: false,
            summary: "test route",
            allow_disabled: false,
        }
    }

    #[allow(
        clippy::expect_used,
        reason = "test route descriptors must validate before the test runs"
    )]
    fn route(
        state: HttpState,
        method: Method,
        path: &'static str,
        mr: axum::routing::MethodRouter<HttpState>,
    ) -> Router {
        RouteTable::new()
            .route(spec(method, path, Access::Public), mr)
            .into_router(state)
            .expect("static test route validates")
    }

    async fn send(
        router: Router,
        request: Request<Body>,
    ) -> Result<axum::response::Response, Box<dyn Error>> {
        Ok(router.oneshot(request).await?)
    }

    fn json_at<'a>(value: &'a serde_json::Value, path: &[&str]) -> Option<&'a serde_json::Value> {
        path.iter()
            .try_fold(value, |current, key| current.get(*key))
    }

    fn json_array_at<'a>(
        value: &'a serde_json::Value,
        path: &[&str],
        index: usize,
    ) -> Option<&'a serde_json::Value> {
        json_at(value, path)
            .and_then(serde_json::Value::as_array)
            .and_then(|items| items.get(index))
    }

    fn assert_json_at(value: &serde_json::Value, path: &[&str], expected: serde_json::Value) {
        assert_eq!(
            json_at(value, path).cloned(),
            Some(expected),
            "JSON path {path:?}"
        );
    }

    fn assert_json_array_at(
        value: &serde_json::Value,
        path: &[&str],
        index: usize,
        key: &str,
        expected: serde_json::Value,
    ) {
        assert_eq!(
            json_array_at(value, path, index)
                .and_then(|item| item.get(key))
                .cloned(),
            Some(expected),
            "JSON path {path:?} item {index} key {key}"
        );
    }

    async fn ok() -> &'static str {
        "ok"
    }

    async fn conflict() -> Result<&'static str, ApiError> {
        Err(ApiError(
            AppError::new(ErrorCode::VersionConflict)
                .with_detail("current_version", DetailValue::Int(7)),
        ))
    }

    #[allow(clippy::panic)]
    async fn panic_handler() -> &'static str {
        panic!("panic-canary-must-not-leak")
    }

    #[test]
    fn api_13_request_id_echo_or_generate() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let app = route(state(true, None, 1024)?, Method::GET, "/ping", get(ok));
                let requested = "0192b8e1-1234-7abc-8def-0123456789ab";
                let request = Request::builder()
                    .uri("/api/v1/ping")
                    .header("X-Request-Id", requested)
                    .body(Body::empty())?;
                let response = send(app.clone(), request).await?;
                assert_eq!(response.headers()["X-Request-Id"], requested);

                let response = send(
                    app,
                    Request::builder()
                        .uri("/api/v1/ping")
                        .header("X-Request-Id", "not-a-uuid")
                        .body(Body::empty())?,
                )
                .await?;
                let id = response.headers()["X-Request-Id"].to_str()?;
                let uuid = uuid::Uuid::parse_str(id)?;
                assert_eq!(uuid.get_version_num(), 7);

                let error_app = route(
                    state(true, None, 1024)?,
                    Method::GET,
                    "/conflict",
                    get(conflict),
                );
                let response = send(
                    error_app,
                    Request::builder()
                        .uri("/api/v1/conflict")
                        .body(Body::empty())?,
                )
                .await?;
                let response_id = response.headers()["X-Request-Id"].to_str()?.to_owned();
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_json_at(&body, &["request_id"], serde_json::json!(response_id));
                Ok(())
            })
    }

    #[test]
    fn api_20_problem_json_shape_and_locale() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let app = route(
                    state(true, None, 1024)?,
                    Method::GET,
                    "/conflict",
                    get(conflict),
                );
                let response = send(
                    app,
                    Request::builder()
                        .uri("/api/v1/conflict")
                        .header(header::ACCEPT_LANGUAGE, "id")
                        .body(Body::empty())?,
                )
                .await?;
                assert_eq!(response.status(), StatusCode::CONFLICT);
                assert_eq!(
                    response.headers()[header::CONTENT_TYPE],
                    "application/problem+json"
                );
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_json_at(&body, &["code"], serde_json::json!("version_conflict"));
                assert_json_at(
                    &body,
                    &["type"],
                    serde_json::json!("urn:kinevo:error:version_conflict"),
                );
                assert_json_at(&body, &["title"], serde_json::json!("Data berubah"));
                assert_json_at(&body, &["details", "current_version"], serde_json::json!(7));
                assert!(
                    json_at(&body, &["request_id"])
                        .and_then(serde_json::Value::as_str)
                        .is_some()
                );
                Ok(())
            })
    }

    #[test]
    fn api_22_panic_is_opaque() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let app = route(
                    state(true, None, 1024)?,
                    Method::GET,
                    "/panic",
                    get(panic_handler),
                );
                let response = send(
                    app,
                    Request::builder()
                        .uri("/api/v1/panic")
                        .body(Body::empty())?,
                )
                .await?;
                assert_eq!(response.status(), StatusCode::INTERNAL_SERVER_ERROR);
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                let object = body
                    .as_object()
                    .ok_or_else(|| io::Error::other("problem must be object"))?;
                assert_eq!(object.len(), 6);
                assert_json_at(&body, &["code"], serde_json::json!("internal_error"));
                assert!(
                    json_at(&body, &["request_id"])
                        .and_then(serde_json::Value::as_str)
                        .is_some()
                );
                assert!(!body.to_string().contains("panic-canary-must-not-leak"));
                Ok(())
            })
    }

    #[test]
    fn api_02_unsupported_media_type() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let app = route(state(true, None, 1024)?, Method::POST, "/post", post(ok));
                let response = send(
                    app,
                    Request::builder()
                        .method(Method::POST)
                        .uri("/api/v1/post")
                        .header(header::CONTENT_TYPE, "text/plain")
                        .body(Body::from("payload"))?,
                )
                .await?;
                assert_eq!(response.status(), StatusCode::UNSUPPORTED_MEDIA_TYPE);
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_eq!(
                    json_array_at(&body, &["details", "accepted"], 0),
                    Some(&serde_json::json!("application/json"))
                );
                Ok(())
            })
    }

    #[test]
    fn payload_too_large_before_handler() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let called = Arc::new(AtomicBool::new(false));
                let handler_called = called.clone();
                let handler = post(move || {
                    let called = handler_called.clone();
                    async move {
                        called.store(true, Ordering::SeqCst);
                        "handled"
                    }
                });
                let app = route(state(true, None, 4)?, Method::POST, "/limited", handler);
                let response = send(
                    app,
                    Request::builder()
                        .method(Method::POST)
                        .uri("/api/v1/limited")
                        .header(header::CONTENT_TYPE, "application/json")
                        .body(Body::from("12345"))?,
                )
                .await?;
                assert_eq!(response.status(), StatusCode::PAYLOAD_TOO_LARGE);
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_json_at(&body, &["code"], serde_json::json!("payload_too_large"));
                assert_json_at(&body, &["details", "max_bytes"], serde_json::json!(4));
                assert!(!called.load(Ordering::SeqCst));
                Ok(())
            })
    }

    #[derive(Debug, Deserialize, Serialize)]
    #[serde(deny_unknown_fields)]
    struct Payload {
        title: String,
    }

    impl Validate for Payload {
        fn validate(&self) -> Collector {
            Collector::new()
        }
    }

    async fn payload(ValidatedJson(value): ValidatedJson<Payload>) -> Json<Payload> {
        Json(value)
    }

    #[test]
    fn api_04_unknown_field_rejected() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let app = route(
                    state(true, None, 1024)?,
                    Method::POST,
                    "/json",
                    post(payload),
                );
                let response = send(
                    app.clone(),
                    Request::builder()
                        .method(Method::POST)
                        .uri("/api/v1/json")
                        .header(header::CONTENT_TYPE, "application/json")
                        .body(Body::from(r#"{"title":"x","extra":1}"#))?,
                )
                .await?;
                assert_eq!(response.status(), StatusCode::UNPROCESSABLE_ENTITY);
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_json_at(&body, &["code"], serde_json::json!("validation_failed"));
                assert_json_array_at(&body, &["errors"], 0, "path", serde_json::json!("extra"));
                assert_json_array_at(
                    &body,
                    &["errors"],
                    0,
                    "code",
                    serde_json::json!("unknown_field"),
                );

                let response = send(
                    app,
                    Request::builder()
                        .method(Method::POST)
                        .uri("/api/v1/json")
                        .header(header::CONTENT_TYPE, "application/json")
                        .body(Body::from(r#"{"title":7}"#))?,
                )
                .await?;
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_json_array_at(&body, &["errors"], 0, "path", serde_json::json!("title"));
                assert_json_array_at(
                    &body,
                    &["errors"],
                    0,
                    "code",
                    serde_json::json!("schema_invalid"),
                );
                Ok(())
            })
    }

    struct InvalidPayload {
        name: String,
    }

    impl<'de> Deserialize<'de> for InvalidPayload {
        fn deserialize<D>(deserializer: D) -> Result<Self, D::Error>
        where
            D: serde::Deserializer<'de>,
        {
            #[derive(Deserialize)]
            struct Raw {
                name: String,
            }
            let raw = Raw::deserialize(deserializer)?;
            Ok(Self { name: raw.name })
        }
    }

    impl Validate for InvalidPayload {
        fn validate(&self) -> Collector {
            let _ = &self.name;
            let mut collector = Collector::new();
            let _: Option<()> = collector.check("name", Err(FieldCode::TooShort));
            let _: Option<()> = collector.check("name", Err(FieldCode::InvalidCharacters));
            collector
        }
    }

    async fn invalid_payload(ValidatedJson(_value): ValidatedJson<InvalidPayload>) -> StatusCode {
        StatusCode::OK
    }

    #[test]
    fn api_21_all_validation_errors_at_once() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let app = route(
                    state(true, None, 1024)?,
                    Method::POST,
                    "/validate",
                    post(invalid_payload),
                );
                let response = send(
                    app,
                    Request::builder()
                        .method(Method::POST)
                        .uri("/api/v1/validate")
                        .header(header::CONTENT_TYPE, "application/json")
                        .body(Body::from(r#"{"name":"x"}"#))?,
                )
                .await?;
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_eq!(
                    json_at(&body, &["errors"])
                        .and_then(serde_json::Value::as_array)
                        .map(Vec::len),
                    Some(2)
                );
                assert_json_array_at(
                    &body,
                    &["errors"],
                    0,
                    "code",
                    serde_json::json!("too_short"),
                );
                assert_json_array_at(
                    &body,
                    &["errors"],
                    1,
                    "code",
                    serde_json::json!("invalid_characters"),
                );
                Ok(())
            })
    }

    #[derive(Deserialize)]
    #[serde(deny_unknown_fields)]
    struct QueryParams {
        sort: Option<String>,
    }

    async fn query(StrictQuery(params): StrictQuery<QueryParams>) -> impl IntoResponse {
        if let Some(raw) = params.sort
            && let Err(error) = SortParam::parse(&raw, &["title", "created_at"])
        {
            return ApiError(error).into_response();
        }
        StatusCode::OK.into_response()
    }

    #[test]
    fn api_32_unknown_query_and_sort() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let app = route(state(true, None, 1024)?, Method::GET, "/query", get(query));
                let response = send(
                    app.clone(),
                    Request::builder()
                        .uri("/api/v1/query?bogus=1")
                        .body(Body::empty())?,
                )
                .await?;
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_json_at(&body, &["code"], serde_json::json!("query_param_unknown"));
                assert_json_at(&body, &["details", "name"], serde_json::json!("bogus"));

                let response = send(
                    app,
                    Request::builder()
                        .uri("/api/v1/query?sort=nope")
                        .body(Body::empty())?,
                )
                .await?;
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_json_at(&body, &["code"], serde_json::json!("sort_invalid"));
                assert_json_at(
                    &body,
                    &["details", "allowed"],
                    serde_json::json!(["title", "created_at", "id"]),
                );
                Ok(())
            })
    }

    #[test]
    fn fe_10_security_headers() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread().enable_all().build()?.block_on(async {
            let app = route(state(true, Some("https://example.test"), 1024)?, Method::GET, "/ping", get(ok));
            let response = send(app, Request::builder().uri("/api/v1/ping").body(Body::empty())?).await?;
            let headers = response.headers();
            assert_eq!(headers["Content-Security-Policy"], "default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
            assert_eq!(headers["Referrer-Policy"], "no-referrer");
            assert_eq!(headers["X-Content-Type-Options"], "nosniff");
            assert_eq!(headers["Permissions-Policy"], "");
            assert_eq!(headers["Strict-Transport-Security"], "max-age=31536000; includeSubDomains");
            assert_eq!(headers[header::CACHE_CONTROL], "no-store");
            let local_app = route(
                state(true, Some("http://localhost:8080"), 1024)?,
                Method::GET,
                "/ping",
                get(ok),
            );
            let local_response = send(
                local_app,
                Request::builder().uri("/api/v1/ping").body(Body::empty())?,
            )
            .await?;
            assert!(!local_response
                .headers()
                .contains_key("Strict-Transport-Security"));
            Ok(())
        })
    }

    #[test]
    fn ops_02_health_endpoints() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let app = system_routes::system_routes().into_router(state(false, None, 1024)?)?;
                let live = send(
                    app.clone(),
                    Request::builder().uri("/livez").body(Body::empty())?,
                )
                .await?;
                assert_eq!(live.status(), StatusCode::OK);
                assert_eq!(
                    serde_json::from_slice::<serde_json::Value>(
                        &to_bytes(live.into_body(), 1024).await?
                    )?,
                    serde_json::json!({"status":"ok"})
                );
                let ready =
                    send(app, Request::builder().uri("/readyz").body(Body::empty())?).await?;
                assert_eq!(ready.status(), StatusCode::SERVICE_UNAVAILABLE);
                assert_eq!(
                    serde_json::from_slice::<serde_json::Value>(
                        &to_bytes(ready.into_body(), 1024).await?
                    )?,
                    serde_json::json!({"status":"unavailable"})
                );
                Ok(())
            })
    }

    #[test]
    fn api_42_time_uses_injected_clock() -> Result<(), Box<dyn Error>> {
        tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?
            .block_on(async {
                let app = system_routes::system_routes().into_router(state(true, None, 1024)?)?;
                let token = time_token()?;
                let response = send(
                    app,
                    Request::builder()
                        .uri("/api/v1/time")
                        .header(header::AUTHORIZATION, format!("Bearer {token}"))
                        .body(Body::empty())?,
                )
                .await?;
                let body: serde_json::Value =
                    serde_json::from_slice(&to_bytes(response.into_body(), 1024).await?)?;
                assert_json_at(&body, &["now"], serde_json::json!("2026-10-28T00:00:00Z"));
                Ok(())
            })
    }

    #[derive(Clone)]
    struct SharedBuffer(Arc<Mutex<Vec<u8>>>);

    impl io::Write for SharedBuffer {
        fn write(&mut self, bytes: &[u8]) -> io::Result<usize> {
            self.0
                .lock()
                .unwrap_or_else(std::sync::PoisonError::into_inner)
                .extend_from_slice(bytes);
            Ok(bytes.len())
        }

        fn flush(&mut self) -> io::Result<()> {
            Ok(())
        }
    }

    impl<'a> tracing_subscriber::fmt::MakeWriter<'a> for SharedBuffer {
        type Writer = Self;
        fn make_writer(&'a self) -> Self::Writer {
            self.clone()
        }
    }

    #[tokio::test(flavor = "current_thread")]
    async fn sec_04_log_allow_list() -> Result<(), Box<dyn Error>> {
        let buffer = SharedBuffer(Arc::new(Mutex::new(Vec::new())));
        let subscriber = tracing_subscriber::fmt()
            .with_ansi(false)
            .with_writer(buffer.clone())
            .finish();
        let _guard = tracing::subscriber::set_default(subscriber);
        let app = route(state(true, None, 1024)?, Method::POST, "/log", post(ok));
        let request = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/log?query-canary-secret")
            .header(header::CONTENT_TYPE, "application/json")
            .body(Body::from(r#"{"body":"body-canary-secret"}"#))?;
        let _ = send(app, request).await?;
        let text = String::from_utf8(
            buffer
                .0
                .lock()
                .unwrap_or_else(std::sync::PoisonError::into_inner)
                .clone(),
        )?;
        assert!(!text.contains("query-canary-secret"));
        assert!(!text.contains("body-canary-secret"));
        assert!(text.contains("request_id"));
        assert!(text.contains("route"));
        assert!(text.contains("status"));
        assert!(text.contains("route=\"/api/v1/log\""), "log output: {text}");
        Ok(())
    }

    #[test]
    fn err_01_catalogs_match_registry() -> Result<(), Box<dyn Error>> {
        let catalog = Catalog::load();
        let en: serde_json::Value =
            serde_json::from_str(include_str!("../../../web/src/i18n/en.json"))?;
        let id: serde_json::Value =
            serde_json::from_str(include_str!("../../../web/src/i18n/id.json"))?;
        for code in ErrorCode::ALL {
            let title = format!("error.{}.title", code.as_str());
            let detail = format!("error.{}.detail", code.as_str());
            assert!(en.get(&title).is_some(), "missing English title {title}");
            assert!(en.get(&detail).is_some(), "missing English detail {detail}");
            assert!(id.get(&title).is_some(), "missing Indonesian title {title}");
            assert!(
                id.get(&detail).is_some(),
                "missing Indonesian detail {detail}"
            );
            assert!(catalog.title(code.as_str(), Locale::En).is_some());
            assert!(catalog.detail(code.as_str(), Locale::Id).is_some());
        }
        for map in [en.as_object(), id.as_object()] {
            let map = map.ok_or_else(|| io::Error::other("catalog must be an object"))?;
            assert_eq!(map.len(), ErrorCode::ALL.len() * 2);
            assert!(map.keys().all(|key| key.starts_with("error.")));
        }
        Ok(())
    }
}
