#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "admin acceptance tests use isolated synthetic PostgreSQL fixtures"
)]

mod admin_accounts {
    use std::{
        net::{IpAddr, Ipv4Addr, SocketAddr},
        sync::Arc,
    };

    use async_trait::async_trait;
    use axum::{
        body::{Body, to_bytes},
        extract::ConnectInfo,
        http::{Method, Request, StatusCode},
    };
    use kinevo_db::{migrate::MigrationSource, outbox::SubscriberRegistry, testing::TestApp};
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
        errors::ErrorCode,
        id::{AccountId, FamilyId, IdGen},
        page::CursorCodec,
        policy::{PolicyValue, ResolutionSource, Resolved},
        secret::Secret,
        time::Instant,
        validate::{net::HttpsUrl, tz::Locale},
    };
    use kinevo_mod_identity::{
        MODULE,
        app::{
            admin_accounts::{AdminAccountRow, AdminAccountsService},
            login::{ClientAddr, LoginPolicy, LoginRequest, LoginService, PasswordWork},
            mfa::{MfaInput, MfaResult, MfaVerifier},
            refresh::Client,
        },
        domain::{lockout::LockoutPolicy, session::SessionPolicy},
        infra::hasher::VerifyOutcome,
        migrations,
    };
    use kinevo_ports::{
        fakes::{FakeAccountAccess, FakeInstanceState, FakeModuleSwitches, FakePolicyReader},
        module::ModuleDescriptor,
        platform::{AccountAccessInfo, AccountStatus, InstanceInfo, ResolvedPolicies},
    };
    use serde_json::Value;
    use sqlx::Row;
    use tower::ServiceExt;
    use utoipa::PartialSchema;

    struct AcceptSyntheticPassword;

    #[async_trait]
    impl PasswordWork for AcceptSyntheticPassword {
        async fn verify(&self, _candidate: &Secret<String>, _stored_hash: &str) -> VerifyOutcome {
            VerifyOutcome::Ok
        }

        async fn verify_dummy(&self, _candidate: &Secret<String>) {}

        async fn hash(
            &self,
            _candidate: &Secret<String>,
        ) -> Result<String, kinevo_kernel::errors::AppError> {
            Ok("synthetic-password-hash".to_owned())
        }
    }

    struct AcceptNoMfa;

    #[async_trait]
    impl MfaVerifier for AcceptNoMfa {
        async fn verify(&self, _account: AccountId, _input: &MfaInput, _now: Instant) -> MfaResult {
            MfaResult::Valid
        }
    }

    const NOW: &str = "2026-11-08T00:00:00Z";
    const ORIGIN: &str = "https://api.example.test";

    fn now() -> Instant {
        Instant::parse_rfc3339(NOW).expect("fixed test instant is valid")
    }

    fn resolved(value: PolicyValue) -> Resolved {
        Resolved {
            value,
            source: ResolutionSource::Default,
            version_id: None,
            out_of_bounds: false,
        }
    }

    fn policies() -> Arc<FakePolicyReader> {
        let mut values = ResolvedPolicies::default();
        values.insert("ratelimit.api_per_minute", resolved(PolicyValue::Int(120)));
        values.insert("auth.invite.ttl", resolved(PolicyValue::Seconds(604_800)));
        values.insert(
            "auth.password_reset.ttl",
            resolved(PolicyValue::Seconds(3_600)),
        );
        values.insert("auth.password.min_length", resolved(PolicyValue::Int(12)));
        Arc::new(FakePolicyReader::new(values))
    }

    async fn app() -> TestApp {
        let sources: [MigrationSource; 2] = [kinevo_db::migrations_source(), migrations()];
        TestApp::builder()
            .migrations(&sources)
            .clock_at(now())
            .build()
            .await
            .expect("disposable PostgreSQL test database should start")
    }

    async fn insert_account(
        app: &TestApp,
        id: AccountId,
        email: &str,
        admin: bool,
        status: AccountStatus,
    ) {
        sqlx::query(
            "INSERT INTO public.accounts \
             (id, email, display_name, password_hash, timezone, is_instance_admin, status, \
              created_at, updated_at) \
             VALUES ($1, $2, 'Synthetic Account', 'synthetic-password-hash', 'UTC', $3, $4, \
                     $5::timestamptz, $5::timestamptz)",
        )
        .bind(*id.as_uuid())
        .bind(email)
        .bind(admin)
        .bind(status.as_str())
        .bind(now().to_rfc3339())
        .execute(app.admin_pool())
        .await
        .expect("synthetic account should insert");
    }

    fn service(app: &TestApp) -> Arc<AdminAccountsService> {
        Arc::new(AdminAccountsService::new(
            app.db.clone(),
            app.ids.clone(),
            app.clock.clone(),
            SubscriberRegistry::default(),
            CursorCodec::new(Secret::new([0x22; 32])),
        ))
    }

    fn http_state(app: &TestApp, id: AccountId, is_admin: bool) -> HttpState {
        let access = Arc::new(FakeAccountAccess::default());
        access
            .insert(AccountAccessInfo {
                id,
                status: AccountStatus::Active,
                is_instance_admin: is_admin,
                version: 1,
            })
            .expect("synthetic account access row should insert");
        let descriptors: [&ModuleDescriptor; 1] = [&MODULE];
        let permissions = Arc::new(
            PermissionTable::from_descriptors(&descriptors).expect("permissions are valid"),
        );
        let kinds =
            Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds are valid"));
        let tokens = Arc::new(TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic key ring is valid"),
        ));
        let gate = Arc::new(Gate::new(
            tokens,
            Arc::new(AccountCache::new(access)),
            Arc::new(FakeModuleSwitches::default()),
            permissions,
            kinds,
        ));
        HttpState::new(
            app.clock.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            Arc::new(Catalog::load()),
            Arc::new(FakeInstanceState::new(InstanceInfo {
                setup_completed: true,
                base_url: Some(HttpsUrl::parse(ORIGIN).expect("test URL is valid")),
                default_locale: Locale::En,
            })),
            Arc::new(TestReady),
            Some(HttpsUrl::parse(ORIGIN).expect("test URL is valid")),
            Arc::new(kinevo_kernel::page::CursorCodec::new(Secret::new(
                [0x22; 32],
            ))),
            1_048_576,
            gate,
        )
        .with_origin_config(OriginConfig {
            public_origin: Some(ORIGIN.to_owned()),
            android_origin_enabled: true,
        })
        .with_rate_limiter(Arc::new(RateLimiter::new(
            policies(),
            app.clock.clone(),
            TrustedProxies::default(),
        )))
    }

    fn access_token(app: &TestApp, account: AccountId) -> String {
        let codec = TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic key ring is valid"),
        );
        codec.issue(&Claims {
            sub: account,
            sid: FamilyId::from_uuid(app.ids.new_uuid_v7()),
            jti: app.ids.new_uuid_v7(),
            iat: now(),
            exp: now()
                .checked_add_seconds(600)
                .expect("synthetic token expiration is valid"),
            mfa: false,
        })
    }

    async fn json(response: axum::response::Response<Body>) -> Value {
        let bytes = to_bytes(response.into_body(), 1_048_576)
            .await
            .expect("response body should read");
        serde_json::from_slice(&bytes).expect("response should be JSON")
    }

    #[tokio::test]
    async fn sec_ac_10_metadata_only_and_admin_only() {
        let app = app().await;
        let admin = app.new_account();
        let first = app.new_account();
        let second = app.new_account();
        insert_account(
            &app,
            admin,
            "admin@example.test",
            true,
            AccountStatus::Active,
        )
        .await;
        insert_account(
            &app,
            first,
            "first@example.test",
            false,
            AccountStatus::Active,
        )
        .await;
        insert_account(
            &app,
            second,
            "second@example.test",
            false,
            AccountStatus::Active,
        )
        .await;

        let account_service = service(&app);
        let admin_router = kinevo_mod_identity::admin_accounts_router(Arc::clone(&account_service))
            .into_router(http_state(&app, admin, true))
            .expect("admin account route should validate");
        let mut admin_request = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/admin/accounts?limit=10")
            .header(
                "Authorization",
                format!("Bearer {}", access_token(&app, admin)),
            )
            .body(Body::empty())
            .expect("synthetic request should build");
        admin_request
            .extensions_mut()
            .insert(ConnectInfo(SocketAddr::new(
                IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
                12345,
            )));
        let response = admin_router
            .oneshot(admin_request)
            .await
            .expect("admin route should respond");
        assert_eq!(response.status(), StatusCode::OK);
        let page = json(response).await;
        assert_eq!(
            page["items"].as_array().expect("page contains items").len(),
            3
        );
        let value = page["items"][0].clone();
        let mut keys = value
            .as_object()
            .expect("row serializes as an object")
            .keys()
            .map(String::as_str)
            .collect::<Vec<_>>();
        keys.sort_unstable();
        assert_eq!(
            keys,
            [
                "created_at",
                "display_name",
                "email",
                "id",
                "last_login_at",
                "status",
            ]
        );
        assert!(!value.to_string().contains("synthetic-password-hash"));

        let router = kinevo_mod_identity::admin_accounts_router(account_service)
            .into_router(http_state(&app, first, false))
            .expect("admin account route should validate");
        let mut request = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/admin/accounts")
            .header(
                "Authorization",
                format!("Bearer {}", access_token(&app, first)),
            )
            .body(Body::empty())
            .expect("synthetic request should build");
        request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
            IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
            12345,
        )));
        let response = router
            .oneshot(request)
            .await
            .expect("admin route should respond");
        assert_eq!(response.status(), StatusCode::FORBIDDEN);
    }

    #[test]
    fn sec_ac_10_schema_has_no_content_fields() {
        let schema = AdminAccountRow::schema();
        let value = serde_json::to_value(schema).expect("account response schema serializes");
        let mut keys = value["properties"]
            .as_object()
            .expect("account response schema has properties")
            .keys()
            .map(String::as_str)
            .collect::<Vec<_>>();
        keys.sort_unstable();
        assert_eq!(
            keys,
            [
                "created_at",
                "display_name",
                "email",
                "id",
                "last_login_at",
                "status",
            ]
        );
    }

    #[tokio::test]
    async fn id_fr_10_last_admin_race() {
        let app = app().await;
        let first = app.new_account();
        let second = app.new_account();
        insert_account(
            &app,
            first,
            "first-admin@example.test",
            true,
            AccountStatus::Active,
        )
        .await;
        insert_account(
            &app,
            second,
            "second-admin@example.test",
            true,
            AccountStatus::Active,
        )
        .await;
        let service = service(&app);
        let (left, right) = tokio::join!(
            service.set_status(first, second, AccountStatus::Disabled, 1),
            service.set_status(second, first, AccountStatus::Disabled, 1),
        );
        assert_ne!(left.is_ok(), right.is_ok());
        let error = left
            .err()
            .or_else(|| right.err())
            .expect("one update must fail");
        assert_eq!(error.code, ErrorCode::LastAdmin);
        let active: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.accounts WHERE is_instance_admin AND status='active'",
        )
        .fetch_one(app.admin_pool())
        .await
        .expect("active admin count should be readable");
        assert_eq!(active, 1);
    }

    #[tokio::test]
    async fn id_fr_07_disable_revokes_sessions() {
        let app = app().await;
        let actor = app.new_account();
        let target = app.new_account();
        insert_account(
            &app,
            actor,
            "admin@example.test",
            true,
            AccountStatus::Active,
        )
        .await;
        insert_account(
            &app,
            target,
            "user@example.test",
            false,
            AccountStatus::Active,
        )
        .await;
        let family = app.ids.new_uuid_v7();
        sqlx::query(
            "INSERT INTO public.refresh_token_families \
             (id, account_id, created_at, last_used_at, absolute_expires_at) \
             VALUES ($1, $2, $3::timestamptz, $3::timestamptz, $4::timestamptz)",
        )
        .bind(family)
        .bind(*target.as_uuid())
        .bind(now().to_rfc3339())
        .bind(
            now()
                .checked_add_seconds(86_400)
                .expect("family expiry is valid")
                .to_rfc3339(),
        )
        .execute(app.admin_pool())
        .await
        .expect("synthetic refresh family should insert");

        let router = kinevo_mod_identity::admin_accounts_router(service(&app))
            .into_router(http_state(&app, actor, true))
            .expect("admin account route should validate");
        let mut request = Request::builder()
            .method(Method::PATCH)
            .uri(format!("/api/v1/admin/accounts/{target}"))
            .header(
                "Authorization",
                format!("Bearer {}", access_token(&app, actor)),
            )
            .header("If-Match", "\"1\"")
            .header("content-type", "application/json")
            .header("origin", ORIGIN)
            .body(Body::from(r#"{"status":"disabled"}"#))
            .expect("synthetic status request should build");
        request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
            IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
            12345,
        )));
        let response = router
            .oneshot(request)
            .await
            .expect("admin status route should respond");
        assert_eq!(response.status(), StatusCode::OK);
        let revoked: Option<String> = sqlx::query_scalar(
            "SELECT revoked_at::text FROM public.refresh_token_families WHERE id=$1",
        )
        .bind(family)
        .fetch_one(app.admin_pool())
        .await
        .expect("family should remain for audit");
        assert!(revoked.is_some());
        let action: String = sqlx::query_scalar(
            "SELECT action FROM public.audit_log WHERE action='account.status_changed' \
             AND account_id=$1 ORDER BY occurred_at DESC LIMIT 1",
        )
        .bind(*target.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("account status change should be audited");
        assert_eq!(action, "account.status_changed");
        let event: String = sqlx::query_scalar(
            "SELECT kind FROM public.outbox_events WHERE account_id=$1 \
             AND kind='AccountDisabled' ORDER BY seq DESC LIMIT 1",
        )
        .bind(*target.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("account disable event should be emitted");
        assert_eq!(event, "AccountDisabled");
        let status: String = sqlx::query_scalar("SELECT status FROM public.accounts WHERE id=$1")
            .bind(*target.as_uuid())
            .fetch_one(app.admin_pool())
            .await
            .expect("account should remain");
        assert_eq!(status, "disabled");

        let codec = Arc::new(TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic key ring is valid"),
        ));
        let login = LoginService::new(
            app.db.clone(),
            Arc::new(AcceptSyntheticPassword),
            Arc::new(AcceptNoMfa),
            policies(),
            app.clock.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            codec,
        );
        let error = login
            .login(
                LoginRequest {
                    email: "user@example.test".to_owned(),
                    password: Secret::new("synthetic password".to_owned()),
                    totp_code: None,
                    recovery_code: None,
                },
                Client::Web,
                ClientAddr::new("192.0.2.1".parse().expect("test address is valid")),
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
                        idle_ttl: 86_400,
                        absolute_ttl: 604_800,
                        grace: 10,
                    },
                },
            )
            .await
            .expect_err("disabled account cannot log in");
        assert_eq!(error.code, ErrorCode::AccountDisabled);
    }

    #[tokio::test]
    async fn version_conflict_on_status_change() {
        let app = app().await;
        let actor = app.new_account();
        let target = app.new_account();
        insert_account(
            &app,
            actor,
            "admin@example.test",
            true,
            AccountStatus::Active,
        )
        .await;
        insert_account(
            &app,
            target,
            "user@example.test",
            false,
            AccountStatus::Active,
        )
        .await;
        let error = service(&app)
            .set_status(actor, target, AccountStatus::Disabled, 0)
            .await
            .expect_err("stale account versions are rejected");
        assert_eq!(error.code, ErrorCode::VersionConflict);
    }

    #[tokio::test]
    async fn invite_creation() {
        let app = app().await;
        let admin = app.new_account();
        insert_account(
            &app,
            admin,
            "admin@example.test",
            true,
            AccountStatus::Active,
        )
        .await;
        let service = Arc::new(kinevo_mod_identity::app::invites::InviteService::new(
            app.db.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            policies(),
            Arc::new(FakeInstanceState::new(InstanceInfo {
                setup_completed: true,
                base_url: Some(HttpsUrl::parse("https://nivaras.example.test").expect("base URL")),
                default_locale: Locale::En,
            })),
            app.clock.clone(),
        ));
        let router = kinevo_mod_identity::admin_invites_router(service)
            .into_router(http_state(&app, admin, true))
            .expect("invite route should validate");
        let mut request = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/admin/invites")
            .header(
                "Authorization",
                format!("Bearer {}", access_token(&app, admin)),
            )
            .header("content-type", "application/json")
            .body(Body::from(r#"{"email":"invitee@example.test"}"#))
            .expect("synthetic request should build");
        request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
            IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
            12345,
        )));
        let response = router
            .oneshot(request)
            .await
            .expect("invite route should respond");
        assert_eq!(response.status(), StatusCode::CREATED);
        assert_eq!(
            response
                .headers()
                .get("cache-control")
                .and_then(|value| value.to_str().ok()),
            Some("no-store")
        );
        let body = json(response).await;
        let url = body["invite_url"]
            .as_str()
            .expect("response contains the invite URL");
        assert!(url.starts_with("https://nivaras.example.test/register?invite="));
        let token = url
            .split_once("invite=")
            .map(|(_, token)| token)
            .expect("invite URL contains its token");
        assert_eq!(token.len(), 43);
        let row = sqlx::query(
            "SELECT token_hash, email::text AS email, \
                    to_char(expires_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS expires_at \
             FROM public.invites",
        )
        .fetch_one(app.admin_pool())
        .await
        .expect("invite row should exist");
        let token_hash: Vec<u8> = row.try_get("token_hash").expect("hash is selected");
        let decoded =
            kinevo_kernel::crypto::decode_token(token).expect("issued invite token is base64url");
        assert_eq!(token_hash, kinevo_kernel::crypto::hash_token(&decoded));
        let email: String = row.try_get("email").expect("bound email is selected");
        assert_eq!(email, "invitee@example.test");
        let expires_at: String = row.try_get("expires_at").expect("expiry is selected");
        let expires_at = Instant::parse_rfc3339(&expires_at)
            .or_else(|_| Instant::parse_rfc3339(&format!("{}Z", &expires_at[..19])))
            .expect("expiry is an instant");
        assert_eq!(
            expires_at,
            now()
                .checked_add_seconds(604_800)
                .expect("policy expiry is valid")
        );
    }

    struct TestReady;

    #[async_trait]
    impl ReadyCheck for TestReady {
        async fn check(&self) -> Result<(), ReadyFailure> {
            Ok(())
        }
    }
}
