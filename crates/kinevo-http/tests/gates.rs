#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "acceptance tests use assertions with explicit synthetic fixtures"
)]

mod support {
    use async_trait::async_trait;
    use axum::{
        Json, Router,
        body::{Body, to_bytes},
        http::{Method, Request, Response, StatusCode, header},
        response::IntoResponse,
    };
    use kinevo_http::{
        auth::{AccountCache, Auth},
        gate::{Gate, ModuleKinds},
        i18n::Catalog,
        problem::ApiError,
        rbac::PermissionTable,
        route::{Access, IdemMode, RouteSpec, RouteTable},
        state::{HttpState, ReadyCheck, ReadyFailure},
        token::{Claims, FamilyId, KeyRing, TokenCodec},
    };
    use kinevo_kernel::{
        errors::{AppError, ErrorCode},
        id::{AccountId, IdGen, TokenGen},
        page::CursorCodec,
        secret::Secret,
        time::{Clock, Instant},
        validate::{net::HttpsUrl, tz::Locale},
    };
    use kinevo_ports::{
        fakes::{FakeAccountAccess, FakeModuleSwitches},
        module::{ModuleDescriptor, ModuleKey, ModuleKind, PermissionDef},
        platform::{AccountAccessInfo, AccountStatus, InstanceInfo},
    };
    use serde_json::{Value, json};
    use std::{
        error::Error,
        sync::{
            Arc,
            atomic::{AtomicBool, Ordering},
        },
    };
    use tower::ServiceExt;
    use uuid::Uuid;

    const EMPTY_PERMISSIONS: &[PermissionDef] = &[];
    const IDENTITY_PERMISSIONS: &[PermissionDef] = &[PermissionDef {
        key: "identity.account.read",
        owner: true,
    }];
    const ADMIN_PERMISSIONS: &[PermissionDef] = &[
        PermissionDef {
            key: "admin.instance.read",
            owner: true,
        },
        PermissionDef {
            key: "admin.modules.manage",
            owner: false,
        },
    ];
    const DEMO_PERMISSIONS: &[PermissionDef] = &[PermissionDef {
        key: "demo.resource.read",
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

    pub const KERNEL: ModuleDescriptor = descriptor("kernel", ModuleKind::Core, EMPTY_PERMISSIONS);
    pub const IDENTITY: ModuleDescriptor =
        descriptor("identity", ModuleKind::Core, IDENTITY_PERMISSIONS);
    pub const ADMIN: ModuleDescriptor = descriptor("admin", ModuleKind::Core, ADMIN_PERMISSIONS);
    pub const DEMO: ModuleDescriptor = descriptor("demo", ModuleKind::Toggleable, DEMO_PERMISSIONS);
    pub const DESCRIPTORS: &[&ModuleDescriptor] = &[&KERNEL, &IDENTITY, &ADMIN, &DEMO];

    pub struct FixedClock(pub Instant);

    impl Clock for FixedClock {
        fn now(&self) -> Instant {
            self.0
        }
    }

    pub struct FixedIds;

    impl IdGen for FixedIds {
        fn new_uuid_v7(&self) -> Uuid {
            Uuid::from_bytes([0, 0, 0, 0, 0, 0, 0x70, 0, 0x80, 0, 0, 0, 0, 0, 0, 1])
        }
    }

    pub struct FixedTokens;

    impl TokenGen for FixedTokens {
        fn fill(&self, buffer: &mut [u8]) {
            buffer.fill(7);
        }
    }

    pub struct TestReady;

    #[async_trait]
    impl ReadyCheck for TestReady {
        async fn check(&self) -> Result<(), ReadyFailure> {
            Ok(())
        }
    }

    #[derive(Clone)]
    pub struct TestInstance {
        pub setup_completed: Arc<AtomicBool>,
    }

    #[async_trait]
    impl kinevo_ports::platform::InstanceState for TestInstance {
        async fn get(&self) -> Result<InstanceInfo, AppError> {
            Ok(InstanceInfo {
                setup_completed: self.setup_completed.load(Ordering::SeqCst),
                base_url: None,
                default_locale: Locale::En,
            })
        }
    }

    pub struct Fixture {
        pub state: HttpState,
        pub codec: Arc<TokenCodec>,
        pub accounts: Arc<FakeAccountAccess>,
        pub switches: Arc<FakeModuleSwitches>,
        pub setup_completed: Arc<AtomicBool>,
        pub now: Instant,
        pub owner: AccountId,
        pub admin: AccountId,
        pub disabled: AccountId,
    }

    impl Fixture {
        pub fn token(&self, account: AccountId) -> String {
            self.codec.issue(&claims(account, self.now, 600))
        }
    }

    pub fn fixture(setup_completed: bool) -> Result<Fixture, Box<dyn Error>> {
        let now = Instant::parse_rfc3339("2026-10-28T00:00:00Z")?;
        let owner = AccountId::from_uuid(Uuid::from_u128(1));
        let admin = AccountId::from_uuid(Uuid::from_u128(2));
        let disabled = AccountId::from_uuid(Uuid::from_u128(3));
        let accounts = Arc::new(FakeAccountAccess::default());
        accounts.insert(AccountAccessInfo {
            id: owner,
            status: AccountStatus::Active,
            is_instance_admin: false,
            version: 1,
        })?;
        accounts.insert(AccountAccessInfo {
            id: admin,
            status: AccountStatus::Active,
            is_instance_admin: true,
            version: 1,
        })?;
        accounts.insert(AccountAccessInfo {
            id: disabled,
            status: AccountStatus::Disabled,
            is_instance_admin: false,
            version: 2,
        })?;
        let demo_key = ModuleKey::parse("demo").map_err(|_| "invalid synthetic module key")?;
        let switches = Arc::new(FakeModuleSwitches::new([demo_key]));
        let permissions = Arc::new(PermissionTable::from_descriptors(DESCRIPTORS)?);
        let module_kinds = Arc::new(ModuleKinds::from_descriptors(DESCRIPTORS)?);
        let ring = KeyRing::new(
            "current",
            vec![("current".to_owned(), Secret::new([1; 32]))],
        )?;
        let codec = Arc::new(TokenCodec::new(ring));
        let account_cache = Arc::new(AccountCache::new(accounts.clone()));
        let gate = Arc::new(Gate::new(
            codec.clone(),
            account_cache,
            switches.clone(),
            permissions,
            module_kinds,
        ));
        let setup_completed = Arc::new(AtomicBool::new(setup_completed));
        let instance = Arc::new(TestInstance {
            setup_completed: setup_completed.clone(),
        });
        let state = HttpState::new(
            Arc::new(FixedClock(now)),
            Arc::new(FixedIds),
            Arc::new(FixedTokens),
            Arc::new(Catalog::load()),
            instance,
            Arc::new(TestReady),
            Some(HttpsUrl::parse("https://example.test").map_err(|_| "invalid URL")?),
            Arc::new(CursorCodec::new(Secret::new([0; 32]))),
            1024,
            gate,
        );
        Ok(Fixture {
            state,
            codec,
            accounts,
            switches,
            setup_completed,
            now,
            owner,
            admin,
            disabled,
        })
    }

    pub fn claims(account: AccountId, now: Instant, ttl_seconds: i64) -> Claims {
        Claims {
            sub: account,
            sid: FamilyId::from_uuid(Uuid::from_u128(9)),
            jti: Uuid::from_u128(10),
            iat: now,
            exp: now
                .checked_add_seconds(ttl_seconds)
                .expect("test expiry is in supported range"),
            mfa: false,
        }
    }

    pub fn codec() -> Result<TokenCodec, Box<dyn Error>> {
        Ok(TokenCodec::new(KeyRing::new(
            "current",
            vec![("current".to_owned(), Secret::new([1; 32]))],
        )?))
    }

    pub fn route_spec(
        method: Method,
        path: &'static str,
        module: &'static str,
        access: Access,
    ) -> RouteSpec {
        RouteSpec {
            method,
            path,
            module,
            access,
            rate: kinevo_http::route::RateClass::None,
            idempotency: IdemMode::Off,
            store_response_body: true,
            cookie_endpoint: false,
            summary: "gate test route",
            allow_disabled: false,
        }
    }

    pub fn router(
        state: HttpState,
        spec: RouteSpec,
        method_router: axum::routing::MethodRouter<HttpState>,
    ) -> Result<Router, Box<dyn Error>> {
        Ok(RouteTable::new()
            .route(spec, method_router)
            .into_router(state)?)
    }

    pub async fn send(
        router: Router,
        request: Request<Body>,
    ) -> Result<Response<Body>, Box<dyn Error>> {
        Ok(router.oneshot(request).await?)
    }

    pub async fn json_body(response: Response<Body>) -> Result<Value, Box<dyn Error>> {
        Ok(serde_json::from_slice(
            &to_bytes(response.into_body(), 2048).await?,
        )?)
    }

    pub async fn ok() -> impl IntoResponse {
        (StatusCode::OK, "ok")
    }

    pub async fn authed(Auth(ctx): Auth) -> Result<impl IntoResponse, ApiError> {
        Ok((
            StatusCode::OK,
            Json(json!({"account_id": ctx.account_id().to_string(), "is_admin": ctx.is_admin()})),
        ))
    }

    pub fn error_code(value: &Value) -> Option<&str> {
        value.get("code").and_then(Value::as_str)
    }

    pub fn error_code_is(value: &Value, code: ErrorCode) -> bool {
        error_code(value) == Some(code.as_str())
    }

    pub fn request(
        method: Method,
        uri: &str,
        token: Option<&str>,
    ) -> Result<Request<Body>, Box<dyn Error>> {
        let mut builder = Request::builder().method(method).uri(uri);
        if let Some(token) = token {
            builder = builder.header(header::AUTHORIZATION, format!("Bearer {token}"));
        }
        Ok(builder.body(Body::empty())?)
    }
}

mod token {
    pub mod tests {
        use super::super::support::{claims, codec};
        use jsonwebtoken::{Algorithm, EncodingKey, Header, encode};
        use kinevo_kernel::{id::AccountId, time::Instant};
        use serde_json::{Value, json};
        use uuid::Uuid;

        fn signed(payload: &Value, algorithm: Algorithm, kid: &str, key: &[u8]) -> String {
            let mut header = Header::new(algorithm);
            header.kid = Some(kid.to_owned());
            encode(&header, &payload, &EncodingKey::from_secret(key))
                .expect("synthetic token signs")
        }

        fn payload(sub: AccountId, now: Instant, exp: i64) -> Value {
            json!({
                "sub": sub,
                "sid": Uuid::from_u128(9),
                "jti": Uuid::from_u128(10),
                "iat": now.unix_millis() / 1000,
                "exp": exp,
                "mfa": false
            })
        }

        #[test]
        fn auth_02_verification_matrix() -> Result<(), Box<dyn std::error::Error>> {
            let codec = codec()?;
            let now = Instant::parse_rfc3339("2026-10-28T00:00:00Z")?;
            let account = AccountId::from_uuid(Uuid::from_u128(1));
            let valid = claims(account, now, 600);
            let valid_token = codec.issue(&valid);
            assert!(codec.verify(&valid_token, now).is_ok());

            let segments = valid_token.split('.').collect::<Vec<_>>();
            let valid_payload = segments.get(1).copied().expect("three JWT segments");
            let none = format!("eyJ0eXAiOiJKV1QiLCJhbGciOiJub25lIn0.{valid_payload}.");
            let hs384 = signed(
                &payload(account, now, now.unix_millis() / 1000 + 600),
                Algorithm::HS384,
                "current",
                &[1; 32],
            );
            let wrong_key = signed(
                &payload(account, now, now.unix_millis() / 1000 + 600),
                Algorithm::HS256,
                "current",
                &[2; 32],
            );
            let unknown_kid = signed(
                &payload(account, now, now.unix_millis() / 1000 + 600),
                Algorithm::HS256,
                "old-unknown",
                &[1; 32],
            );
            let expired = signed(
                &payload(account, now, now.unix_millis() / 1000 - 31),
                Algorithm::HS256,
                "current",
                &[1; 32],
            );
            let future_iat = signed(
                &json!({"sub":account,"sid":Uuid::from_u128(9),"jti":Uuid::from_u128(10),"iat":now.unix_millis()/1000+31,"exp":now.unix_millis()/1000+600,"mfa":false}),
                Algorithm::HS256,
                "current",
                &[1; 32],
            );
            let mut missing_sid = payload(account, now, now.unix_millis() / 1000 + 600);
            missing_sid
                .as_object_mut()
                .expect("object payload")
                .remove("sid");
            let missing_sid = signed(&missing_sid, Algorithm::HS256, "current", &[1; 32]);

            for rejected in [
                none,
                hs384,
                wrong_key,
                unknown_kid,
                expired,
                future_iat,
                missing_sid,
            ] {
                assert!(codec.verify(&rejected, now).is_err());
            }
            Ok(())
        }

        #[test]
        fn auth_02_skew_boundaries() -> Result<(), Box<dyn std::error::Error>> {
            let codec = codec()?;
            let account = AccountId::from_uuid(Uuid::from_u128(1));
            let now = Instant::parse_rfc3339("2026-10-28T00:00:00Z")?;
            let exp = now.checked_add_seconds(60).expect("test expiry is valid");
            let token = codec.issue(&claims(account, now, 60));
            assert!(
                codec
                    .verify(&token, exp.checked_add_seconds(-1).expect("before expiry"))
                    .is_ok()
            );
            assert!(
                codec
                    .verify(&token, exp.checked_add_seconds(30).expect("within leeway"))
                    .is_ok()
            );
            assert!(
                codec
                    .verify(&token, exp.checked_add_seconds(31).expect("past leeway"))
                    .is_err()
            );
            Ok(())
        }
    }
}

mod auth {
    pub mod tests {
        use super::super::support::{claims, fixture};
        use kinevo_http::auth::AccountCache;
        use kinevo_kernel::time::Instant;
        use kinevo_ports::{
            fakes::FakeAccountAccess,
            platform::{AccountAccessInfo, AccountStatus},
        };
        use std::{error::Error, sync::Arc, time::Duration};

        #[tokio::test]
        async fn cac_1_cache_invalidation_and_equivalence() -> Result<(), Box<dyn Error>> {
            let fixture = fixture(true)?;
            let account = fixture.disabled;
            let cache = AccountCache::new(fixture.accounts.clone());
            assert_eq!(
                cache.get(account).await?.map(|value| value.status),
                Some(AccountStatus::Disabled)
            );
            fixture.accounts.insert(AccountAccessInfo {
                id: account,
                status: AccountStatus::Active,
                is_instance_admin: false,
                version: 3,
            })?;
            cache.invalidate(account).await;
            assert_eq!(
                cache.get(account).await?.map(|value| value.status),
                Some(AccountStatus::Active)
            );

            let zero_ttl_source = Arc::new(FakeAccountAccess::default());
            zero_ttl_source.insert(AccountAccessInfo {
                id: account,
                status: AccountStatus::Disabled,
                is_instance_admin: false,
                version: 1,
            })?;
            let no_cache = AccountCache::with_ttl(zero_ttl_source.clone(), Duration::ZERO);
            assert_eq!(
                no_cache.get(account).await?.map(|value| value.status),
                Some(AccountStatus::Disabled)
            );
            zero_ttl_source.insert(AccountAccessInfo {
                id: account,
                status: AccountStatus::Active,
                is_instance_admin: false,
                version: 2,
            })?;
            assert_eq!(
                no_cache.get(account).await?.map(|value| value.status),
                Some(AccountStatus::Active)
            );
            let _clock_only_for_cache_contract = Instant::parse_rfc3339("2026-10-28T00:00:00Z")?;
            let _ = claims(account, fixture.now, 60);
            Ok(())
        }
    }
}

mod rbac {
    pub mod tests {
        use super::super::support::DEMO;
        use axum::http::Method;
        use kinevo_http::{
            rbac::{PermissionTable, check_routes},
            route::{Access, IdemMode, RateClass, RouteSpec},
        };

        #[test]
        fn mod_08_unknown_permission_fails() {
            let table = PermissionTable::from_descriptors(&[&DEMO]).expect("descriptor is valid");
            let route = RouteSpec {
                method: Method::GET,
                path: "never",
                module: "demo",
                access: Access::Permission("demo.missing.read"),
                rate: RateClass::None,
                idempotency: IdemMode::Off,
                store_response_body: true,
                cookie_endpoint: false,
                summary: "unknown permission",
                allow_disabled: false,
            };
            let error = check_routes(&table, &[route]).expect_err("unknown permission must fail");
            assert!(error.to_string().contains("never"));
        }
    }
}

mod gates {
    use super::support::{
        authed, error_code_is, fixture, json_body, ok, request, route_spec, router, send,
    };
    use axum::{
        body::Body,
        http::{Method, Request, StatusCode, header},
        routing::{get, post},
    };
    use kinevo_http::{
        auth::Role,
        rbac::PermissionTable,
        route::{Access, RouteTable},
        system_routes,
    };
    use kinevo_kernel::{errors::ErrorCode, id::AccountId};
    use serde_json::{Value, json};
    use std::error::Error;
    use uuid::Uuid;

    #[tokio::test]
    async fn api_10_missing_or_bad_bearer() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(true)?;
        let app = router(
            fixture.state,
            route_spec(
                Method::GET,
                "secure",
                "demo",
                Access::Permission("demo.resource.read"),
            ),
            get(ok),
        )
        .expect("route table is valid");
        for request in [
            request(Method::GET, "/api/v1/secure", None)?,
            request(Method::GET, "/api/v1/secure", Some("not-a-token"))?,
        ] {
            let response = send(app.clone(), request).await?;
            assert_eq!(response.status(), StatusCode::UNAUTHORIZED);
            assert_eq!(response.headers()[header::WWW_AUTHENTICATE], "Bearer");
            let body = json_body(response).await?;
            assert!(error_code_is(&body, ErrorCode::Unauthenticated));
            assert_eq!(body.get("details"), None);
        }
        Ok(())
    }

    #[tokio::test]
    async fn rbac_disabled_account_blocked_except_instance() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(true)?;
        let token = fixture.token(fixture.disabled);
        let private = route_spec(
            Method::GET,
            "resource",
            "demo",
            Access::Permission("demo.resource.read"),
        );
        let public_instance = {
            let mut spec = route_spec(
                Method::GET,
                "instance",
                "admin",
                Access::Permission("admin.instance.read"),
            );
            spec.allow_disabled = true;
            spec
        };
        let app = router(fixture.state.clone(), private, get(ok))?.merge(router(
            fixture.state,
            public_instance,
            get(ok),
        )?);
        let denied = send(
            app.clone(),
            request(Method::GET, "/api/v1/resource", Some(&token))?,
        )
        .await?;
        assert_eq!(denied.status(), StatusCode::FORBIDDEN);
        assert!(error_code_is(
            &json_body(denied).await?,
            ErrorCode::AccountDisabled
        ));
        let allowed = send(app, request(Method::GET, "/api/v1/instance", Some(&token))?).await?;
        assert_eq!(allowed.status(), StatusCode::OK);
        Ok(())
    }

    #[tokio::test]
    async fn mod_04_module_gate_after_auth() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(true)?;
        fixture.switches.set_enabled(
            kinevo_ports::module::ModuleKey::parse("demo")
                .map_err(|_| "invalid synthetic module key")?,
            false,
        )?;
        let token = fixture.token(fixture.owner);
        let app = router(
            fixture.state,
            route_spec(
                Method::GET,
                "resource",
                "demo",
                Access::Permission("demo.resource.read"),
            ),
            get(ok),
        )?;
        let no_token = send(app.clone(), request(Method::GET, "/api/v1/resource", None)?).await?;
        assert_eq!(no_token.status(), StatusCode::UNAUTHORIZED);
        let response = send(app, request(Method::GET, "/api/v1/resource", Some(&token))?).await?;
        assert_eq!(response.status(), StatusCode::NOT_FOUND);
        let body = json_body(response).await?;
        assert!(error_code_is(&body, ErrorCode::ModuleDisabled));
        assert_eq!(
            body.get("details")
                .and_then(|details| details.get("module"))
                .and_then(Value::as_str),
            Some("demo")
        );
        Ok(())
    }

    #[tokio::test]
    async fn rbac_02_role_matrix_generated() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(true)?;
        let route_specs = vec![
            route_spec(
                Method::GET,
                "owner",
                "demo",
                Access::Permission("demo.resource.read"),
            ),
            route_spec(
                Method::GET,
                "admin",
                "admin",
                Access::Permission("admin.modules.manage"),
            ),
            route_spec(Method::GET, "public", "identity", Access::Public),
        ];
        let permissions = PermissionTable::from_descriptors(super::support::DESCRIPTORS)?;
        let mut table = RouteTable::new();
        for spec in route_specs {
            table = table.route(spec, get(ok));
        }
        let specs = table.specs().to_vec();
        let app = table.into_router(fixture.state.clone())?;

        for (account_id, role) in [
            (fixture.owner, Role::AccountOwner),
            (fixture.admin, Role::InstanceAdmin),
        ] {
            let token = fixture.token(account_id);
            for spec in &specs {
                let path = format!("/api/v1/{}", spec.path);
                let response =
                    send(app.clone(), request(Method::GET, &path, Some(&token))?).await?;
                let allowed = match spec.access {
                    Access::Public => true,
                    Access::Permission(key) => permissions.allows(role, key),
                };
                assert_eq!(
                    response.status(),
                    if allowed {
                        StatusCode::OK
                    } else {
                        StatusCode::FORBIDDEN
                    }
                );
                if !allowed {
                    assert!(error_code_is(
                        &json_body(response).await?,
                        ErrorCode::Forbidden
                    ));
                }
            }
        }

        let public = send(app.clone(), request(Method::GET, "/api/v1/public", None)?).await?;
        assert_eq!(public.status(), StatusCode::OK);
        let private = send(app, request(Method::GET, "/api/v1/admin", None)?).await?;
        assert_eq!(private.status(), StatusCode::UNAUTHORIZED);
        Ok(())
    }

    #[tokio::test]
    async fn adm_fr_01_setup_gate_table() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(false)?;
        let app = router(
            fixture.state.clone(),
            route_spec(Method::GET, "secure", "demo", Access::Public),
            get(ok),
        )?
        .merge(router(
            fixture.state.clone(),
            route_spec(Method::GET, "setup/test", "admin", Access::Public),
            get(ok),
        )?)
        .merge(system_routes::system_routes().into_router(fixture.state.clone())?);
        let secure = send(app.clone(), request(Method::GET, "/api/v1/secure", None)?).await?;
        assert_eq!(secure.status(), StatusCode::CONFLICT);
        assert!(error_code_is(
            &json_body(secure).await?,
            ErrorCode::SetupRequired
        ));
        let setup = send(
            app.clone(),
            request(Method::GET, "/api/v1/setup/test", None)?,
        )
        .await?;
        assert_eq!(setup.status(), StatusCode::OK);
        fixture
            .setup_completed
            .store(true, std::sync::atomic::Ordering::SeqCst);
        let completed = send(
            app.clone(),
            request(Method::GET, "/api/v1/setup/test", None)?,
        )
        .await?;
        assert_eq!(completed.status(), StatusCode::CONFLICT);
        assert!(error_code_is(
            &json_body(completed).await?,
            ErrorCode::SetupAlreadyCompleted
        ));
        let livez = send(app, request(Method::GET, "/livez", None)?).await?;
        assert_eq!(livez.status(), StatusCode::OK);
        Ok(())
    }

    #[tokio::test]
    async fn api_12_claims_cannot_elevate() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(true)?;
        let account = fixture.owner;
        let claims = super::support::claims(account, fixture.now, 600);
        let mut payload = json!({
            "sub": claims.sub,
            "sid": claims.sid,
            "jti": claims.jti,
            "iat": claims.iat.unix_millis().div_euclid(1_000),
            "exp": claims.exp.unix_millis().div_euclid(1_000),
            "mfa": claims.mfa,
        });
        payload
            .as_object_mut()
            .expect("claims serialize as object")
            .insert("role".to_owned(), json!("instance_admin"));
        let mut header = jsonwebtoken::Header::new(jsonwebtoken::Algorithm::HS256);
        header.kid = Some("current".to_owned());
        let token = jsonwebtoken::encode(
            &header,
            &payload,
            &jsonwebtoken::EncodingKey::from_secret(&[1; 32]),
        )?;
        let app = router(
            fixture.state,
            route_spec(
                Method::GET,
                "identity",
                "identity",
                Access::Permission("identity.account.read"),
            ),
            get(authed),
        )?;
        let response = send(app, request(Method::GET, "/api/v1/identity", Some(&token))?).await?;
        assert_eq!(response.status(), StatusCode::OK);
        let body = json_body(response).await?;
        assert_eq!(body.get("is_admin").and_then(Value::as_bool), Some(false));
        assert_eq!(
            body.get("account_id").and_then(Value::as_str),
            Some(account.to_string().as_str())
        );
        Ok(())
    }

    #[tokio::test]
    async fn sec_05_tenant_only_from_context() -> Result<(), Box<dyn Error>> {
        let fixture = fixture(true)?;
        let owner = fixture.owner;
        let foreign = AccountId::from_uuid(Uuid::from_u128(999));
        let token = fixture.token(owner);
        let spec = route_spec(
            Method::POST,
            "tenant/{id}",
            "demo",
            Access::Permission("demo.resource.read"),
        );
        let app = router(fixture.state, spec, post(authed))?;
        let request = Request::builder()
            .method(Method::POST)
            .uri(format!("/api/v1/tenant/{foreign}?account_id={foreign}"))
            .header(header::AUTHORIZATION, format!("Bearer {token}"))
            .header(header::CONTENT_TYPE, "application/json")
            .body(Body::from(json!({"account_id": foreign}).to_string()))?;
        let response = send(app, request).await?;
        assert_eq!(response.status(), StatusCode::OK);
        let body = json_body(response).await?;
        assert_eq!(
            body.get("account_id").and_then(Value::as_str),
            Some(owner.to_string().as_str())
        );
        Ok(())
    }
}
