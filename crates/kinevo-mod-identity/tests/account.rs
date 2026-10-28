#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "account acceptance tests use isolated synthetic fixtures"
)]

#[allow(
    dead_code,
    reason = "the shared fixture module exposes helpers for sibling tests"
)]
#[path = "support/fixtures.rs"]
mod fixtures;

mod account {
    use std::net::{IpAddr, Ipv4Addr, SocketAddr};
    use std::sync::{
        Arc,
        atomic::{AtomicUsize, Ordering},
    };

    use async_trait::async_trait;
    use axum::{
        Router,
        body::{Body, to_bytes},
        extract::ConnectInfo,
        http::{Method, Request, Response, StatusCode},
    };
    use kinevo_db::{
        Tx,
        jobs::{ClaimedJob, JobContext},
        migrate::MigrationSource,
        outbox::SubscriberRegistry,
        testing::TestApp,
    };
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
        id::{AccountId, FamilyId, IdGen, JobId},
        page::CursorCodec,
        policy::{PolicyValue, ResolutionSource, Resolved},
        secret::Secret,
        time::{FixedClock, Instant},
        validate::tz::Locale,
    };
    use kinevo_mod_identity::{
        MODULE,
        app::{login::PasswordWork, refresh::RefreshService},
        domain::session::SessionPolicy,
        infra::sessions::{self, NewSession},
        migrations,
    };
    use kinevo_mod_identity::{
        app::password::PasswordService, app::profile::ProfileService, app::sessions::SessionService,
    };
    use kinevo_ports::{
        fakes::{
            FakeAccountAccess, FakeIdempotency, FakeInstanceState, FakeModuleSwitches,
            FakePolicyReader,
        },
        module::ModuleDescriptor,
        platform::{AccountAccessInfo, AccountStatus, InstanceInfo, ResolvedPolicies},
    };
    use serde_json::{Value, json};
    use sqlx::{Row, types::Uuid};
    use tower::ServiceExt;

    const ACCESS_TTL: u32 = 600;
    const IDLE_TTL: u32 = 1_209_600;
    const ABSOLUTE_TTL: u32 = 2_592_000;
    const TEST_ORIGIN: &str = "https://api.example.test";

    fn now() -> Instant {
        Instant::parse_rfc3339("2026-11-04T00:00:00Z").expect("fixed test instant is valid")
    }

    fn session_policy() -> SessionPolicy {
        SessionPolicy {
            access_ttl: ACCESS_TTL,
            idle_ttl: IDLE_TTL,
            absolute_ttl: ABSOLUTE_TTL,
            grace: 10,
        }
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
        values.insert("auth.password.min_length", resolved(PolicyValue::Int(12)));
        values.insert("ratelimit.api_per_minute", resolved(PolicyValue::Int(120)));
        Arc::new(FakePolicyReader::new(values))
    }

    struct FakePasswords {
        hashes: AtomicUsize,
    }

    impl FakePasswords {
        fn new() -> Self {
            Self {
                hashes: AtomicUsize::new(0),
            }
        }
    }

    #[async_trait]
    impl PasswordWork for FakePasswords {
        async fn verify(
            &self,
            candidate: &Secret<String>,
            stored_hash: &str,
        ) -> kinevo_mod_identity::infra::hasher::VerifyOutcome {
            if candidate.expose() == "current synthetic password" && stored_hash == "old-hash" {
                kinevo_mod_identity::infra::hasher::VerifyOutcome::Ok
            } else {
                kinevo_mod_identity::infra::hasher::VerifyOutcome::Wrong
            }
        }

        async fn verify_dummy(&self, _candidate: &Secret<String>) {}

        async fn hash(&self, _candidate: &Secret<String>) -> Result<String, AppError> {
            self.hashes.fetch_add(1, Ordering::Relaxed);
            Ok("new-synthetic-hash".to_owned())
        }
    }

    struct Harness {
        app: TestApp,
        account: AccountId,
        current_family: FamilyId,
        current_refresh: String,
        clock: Arc<FixedClock>,
        codec: Arc<TokenCodec>,
        passwords: Arc<FakePasswords>,
        refresh: RefreshService,
        router: Router,
    }

    async fn harness() -> Harness {
        let sources: [MigrationSource; 2] = [kinevo_db::migrations_source(), migrations()];
        let app = TestApp::builder()
            .migrations(&sources)
            .clock_at(now())
            .build()
            .await
            .expect("disposable PostgreSQL test database should start");
        let clock = app.clock.clone();
        let account = app.new_account();
        {
            let mut tx = Tx::begin(&app.db, account)
                .await
                .expect("account transaction should begin");
            crate::fixtures::insert_account(
                &mut tx,
                account,
                &format!("account-{}@example.test", account.as_uuid()),
            )
            .await
            .expect("synthetic account should insert");
            sqlx::query("UPDATE public.accounts SET password_hash = 'old-hash' WHERE id = $1")
                .bind(*account.as_uuid())
                .execute(tx.conn())
                .await
                .expect("synthetic password hash should update");
            tx.commit()
                .await
                .expect("account transaction should commit");
        }

        let current = family(&app, account, "current").await;
        let current_family = current.family_id;
        let current_refresh = current.refresh_token.expose().clone();
        let codec = Arc::new(TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic test key ring is valid"),
        ));
        let access = Arc::new(FakeAccountAccess::default());
        access
            .insert(AccountAccessInfo {
                id: account,
                status: AccountStatus::Active,
                is_instance_admin: false,
                version: 1,
            })
            .expect("synthetic authorization row should insert");
        let policies = policies();
        let passwords = Arc::new(FakePasswords::new());
        let profile = Arc::new(ProfileService::new(app.db.clone()));
        let password = Arc::new(PasswordService::new(
            app.db.clone(),
            passwords.clone(),
            policies.clone(),
            app.ids.clone(),
            clock.clone(),
            SubscriberRegistry::default(),
        ));
        let sessions = Arc::new(SessionService::new(app.db.clone(), clock.clone()));
        let descriptors: [&ModuleDescriptor; 1] = [&MODULE];
        let permissions = Arc::new(
            PermissionTable::from_descriptors(&descriptors).expect("permissions are valid"),
        );
        let module_kinds =
            Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds are valid"));
        let gate = Arc::new(Gate::new(
            codec.clone(),
            Arc::new(AccountCache::new(access.clone())),
            Arc::new(FakeModuleSwitches::default()),
            permissions,
            module_kinds,
        ));
        let instance = Arc::new(FakeInstanceState::new(InstanceInfo {
            setup_completed: true,
            base_url: None,
            default_locale: Locale::En,
        }));
        let state = HttpState::new(
            clock.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            Arc::new(Catalog::load()),
            instance,
            Arc::new(TestReady),
            Some(
                kinevo_kernel::validate::net::HttpsUrl::parse(TEST_ORIGIN)
                    .expect("test URL is valid"),
            ),
            Arc::new(CursorCodec::new(Secret::new([0_u8; 32]))),
            1_048_576,
            gate,
        )
        .with_origin_config(OriginConfig {
            public_origin: Some(TEST_ORIGIN.to_owned()),
            android_origin_enabled: true,
        })
        .with_rate_limiter(Arc::new(RateLimiter::new(
            policies.clone(),
            clock.clone(),
            TrustedProxies::default(),
        )))
        .with_idempotency(Arc::new(FakeIdempotency::default()), 86_400);
        let router = kinevo_mod_identity::account_router(profile, password, sessions)
            .into_router(state)
            .expect("account routes should validate");
        let refresh = RefreshService::new(
            app.db.clone(),
            clock.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            codec.clone(),
        );

        Harness {
            app,
            account,
            current_family,
            current_refresh,
            clock,
            codec,
            passwords,
            refresh,
            router,
        }
    }

    async fn family(app: &TestApp, account: AccountId, label: &str) -> NewSession {
        let mut tx = Tx::begin(&app.db, account)
            .await
            .expect("account transaction should begin");
        let session = sessions::create_family_with_token(
            &mut tx,
            app.ids.as_ref(),
            app.tokens.as_ref(),
            now(),
            account,
            Some(label),
            false,
            session_policy(),
        )
        .await
        .expect("synthetic session should be created");
        tx.commit().await.expect("family transaction should commit");
        session
    }

    fn access_token(h: &Harness) -> String {
        h.codec.issue(&Claims {
            sub: h.account,
            sid: h.current_family,
            jti: h.app.ids.new_uuid_v7(),
            iat: now(),
            exp: now()
                .checked_add_seconds(i64::from(ACCESS_TTL))
                .expect("test token expiration is in range"),
            mfa: false,
        })
    }

    async fn request(
        h: &Harness,
        method: Method,
        path: &str,
        body: Option<&str>,
        headers: &[(&str, &str)],
    ) -> Response<Body> {
        let mut builder = Request::builder()
            .method(method)
            .uri(path)
            .header("Authorization", format!("Bearer {}", access_token(h)));
        if body.is_some() {
            builder = builder.header("Content-Type", "application/json");
        }
        for (name, value) in headers {
            builder = builder.header(*name, *value);
        }
        let mut request = builder
            .body(body.map_or_else(Body::empty, |value| Body::from(value.to_owned())))
            .expect("synthetic HTTP request should build");
        request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
            IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
            12345,
        )));
        h.router
            .clone()
            .oneshot(request)
            .await
            .expect("account router should respond")
    }

    async fn response_json(response: Response<Body>) -> Value {
        let bytes = to_bytes(response.into_body(), 1_048_576)
            .await
            .expect("response body should read");
        serde_json::from_slice(&bytes).expect("response should be JSON")
    }

    async fn account_version(h: &Harness) -> i64 {
        sqlx::query_scalar("SELECT version FROM public.accounts WHERE id = $1")
            .bind(*h.account.as_uuid())
            .fetch_one(h.app.admin_pool())
            .await
            .expect("synthetic account should exist")
    }

    #[tokio::test]
    async fn get_profile_shape() {
        let h = harness().await;
        let response = request(&h, Method::GET, "/api/v1/account", None, &[]).await;
        assert_eq!(response.status(), StatusCode::OK);
        let profile = response_json(response).await;
        let object = profile.as_object().expect("profile is an object");
        let mut keys = object.keys().map(String::as_str).collect::<Vec<_>>();
        keys.sort_unstable();
        assert_eq!(
            keys,
            [
                "display_name",
                "email",
                "id",
                "is_instance_admin",
                "locale",
                "timezone",
                "totp_enabled",
                "version",
                "week_start",
            ]
        );
        assert!(!profile.to_string().contains("old-hash"));
    }

    #[tokio::test]
    async fn api_60_patch_optimistic_concurrency() {
        let h = harness().await;
        let first = request(
            &h,
            Method::PATCH,
            "/api/v1/account",
            Some(r#"{"display_name":"New Name"}"#),
            &[("If-Match", "\"1\"")],
        )
        .await;
        let first_status = first.status();
        assert_eq!(
            first_status,
            StatusCode::OK,
            "response body: {}",
            response_json(first).await
        );
        let first_profile = response_json(first).await;
        assert_eq!(first_profile["version"], 2);

        let conflict = request(
            &h,
            Method::PATCH,
            "/api/v1/account",
            Some(r#"{"display_name":"Overwritten"}"#),
            &[("If-Match", "\"1\"")],
        )
        .await;
        assert_eq!(conflict.status(), StatusCode::CONFLICT);
        let body = response_json(conflict).await;
        assert_eq!(body["code"], "version_conflict");
        assert_eq!(body["details"]["current_version"], 2);
        assert_eq!(body["details"]["current"]["display_name"], "New Name");
    }

    #[tokio::test]
    async fn api_60_if_match_required() {
        let h = harness().await;
        let response = request(
            &h,
            Method::PATCH,
            "/api/v1/account",
            Some(r#"{"display_name":"New Name"}"#),
            &[],
        )
        .await;
        assert_eq!(response.status(), StatusCode::PRECONDITION_REQUIRED);
        assert_eq!(
            response_json(response).await["code"],
            "precondition_required"
        );
        assert_eq!(account_version(&h).await, 1);
    }

    #[tokio::test]
    async fn id_fr_14_patch_validation() {
        let h = harness().await;
        let response = request(
            &h,
            Method::PATCH,
            "/api/v1/account",
            Some(r#"{"timezone":"Mars/OlympusMons","locale":"fr","week_start":9}"#),
            &[("If-Match", "\"1\"")],
        )
        .await;
        assert_eq!(response.status(), StatusCode::UNPROCESSABLE_ENTITY);
        let body = response_json(response).await;
        assert_eq!(body["errors"].as_array().map(Vec::len), Some(3));

        let response = request(
            &h,
            Method::PATCH,
            "/api/v1/account",
            Some(r#"{"display_name":null}"#),
            &[("If-Match", "\"1\"")],
        )
        .await;
        assert_eq!(response.status(), StatusCode::UNPROCESSABLE_ENTITY);
        assert_eq!(response_json(response).await["code"], "field_not_nullable");
    }

    #[tokio::test]
    async fn empty_patch_does_not_bump_version() {
        let h = harness().await;
        let response = request(
            &h,
            Method::PATCH,
            "/api/v1/account",
            Some("{}"),
            &[("If-Match", "\"1\"")],
        )
        .await;
        assert_eq!(response.status(), StatusCode::OK);
        let profile = response_json(response).await;
        assert_eq!(profile["version"], 1);
        assert_eq!(account_version(&h).await, 1);
    }

    #[tokio::test]
    async fn auth_04_password_change_revokes_others() {
        let h = harness().await;
        let other_b = family(&h.app, h.account, "other-b").await;
        let other_c = family(&h.app, h.account, "other-c").await;
        let response = request(
            &h,
            Method::POST,
            "/api/v1/account/password",
            Some(r#"{"current_password":"current synthetic password","new_password":"a-new-synthetic-password"}"#),
            &[],
        )
        .await;
        assert_eq!(response.status(), StatusCode::NO_CONTENT);

        let rows = sqlx::query(
            "SELECT id, revoked_at IS NOT NULL AS revoked FROM public.refresh_token_families WHERE account_id=$1",
        )
        .bind(*h.account.as_uuid())
        .fetch_all(h.app.admin_pool())
        .await
        .expect("session rows should load");
        let revoked = rows
            .iter()
            .map(|row| {
                (
                    row.try_get::<sqlx::types::Uuid, _>("id")
                        .expect("family id should load"),
                    row.try_get::<bool, _>("revoked")
                        .expect("revocation state should load"),
                )
            })
            .collect::<std::collections::BTreeMap<_, _>>();
        assert_eq!(revoked.get(h.current_family.as_uuid()), Some(&false));
        assert_eq!(revoked.get(other_b.family_id.as_uuid()), Some(&true));
        assert_eq!(revoked.get(other_c.family_id.as_uuid()), Some(&true));
        let events: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.outbox_events WHERE account_id=$1 AND kind='PasswordChanged'",
        )
        .bind(*h.account.as_uuid())
        .fetch_one(h.app.admin_pool())
        .await
        .expect("password event should be queryable");
        let audits: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.audit_log WHERE account_id=$1 AND action='auth.password_changed'",
        )
        .bind(*h.account.as_uuid())
        .fetch_one(h.app.admin_pool())
        .await
        .expect("password audit should be queryable");
        assert_eq!(events, 1);
        assert_eq!(audits, 1);
        assert!(
            h.refresh
                .refresh(
                    Secret::new(h.current_refresh.clone()),
                    session_policy(),
                    kinevo_mod_identity::app::refresh::Client::Web,
                )
                .await
                .is_ok(),
            "the caller's existing family remains refreshable"
        );
    }

    #[tokio::test]
    async fn api_14_wrong_current_password_is_422() {
        let h = harness().await;
        let response = request(
            &h,
            Method::POST,
            "/api/v1/account/password",
            Some(r#"{"current_password":"incorrect synthetic password","new_password":"a-new-synthetic-password"}"#),
            &[],
        )
        .await;
        assert_eq!(response.status(), StatusCode::UNPROCESSABLE_ENTITY);
        assert_eq!(
            response_json(response).await["code"],
            "current_password_incorrect"
        );
        let hash: String =
            sqlx::query_scalar("SELECT password_hash FROM public.accounts WHERE id=$1")
                .bind(*h.account.as_uuid())
                .fetch_one(h.app.admin_pool())
                .await
                .expect("account hash should exist");
        assert_eq!(hash, "old-hash");
        assert_eq!(h.passwords.hashes.load(Ordering::Relaxed), 0);
    }

    #[tokio::test]
    async fn password_rules_apply_on_change() {
        let h = harness().await;
        let response = request(
            &h,
            Method::POST,
            "/api/v1/account/password",
            Some(r#"{"current_password":"current synthetic password","new_password":"password1234"}"#),
            &[],
        )
        .await;
        assert_eq!(response.status(), StatusCode::UNPROCESSABLE_ENTITY);
        let body = response_json(response).await;
        assert_eq!(body["errors"][0]["code"], "password_too_common");
        assert_eq!(h.passwords.hashes.load(Ordering::Relaxed), 0);
    }

    #[tokio::test]
    async fn sessions_list_and_revoke() {
        let h = harness().await;
        let other = family(&h.app, h.account, "other-session").await;
        let response = request(&h, Method::GET, "/api/v1/account/sessions", None, &[]).await;
        assert_eq!(response.status(), StatusCode::OK);
        let body = response_json(response).await;
        let items = body["items"].as_array().expect("page has items");
        assert_eq!(items.len(), 2);
        assert_eq!(
            items.iter().filter(|item| item["current"] == true).count(),
            1
        );

        let response = request(
            &h,
            Method::DELETE,
            &format!("/api/v1/account/sessions/{}", other.family_id),
            None,
            &[],
        )
        .await;
        assert_eq!(response.status(), StatusCode::NO_CONTENT);
        assert!(
            h.refresh
                .refresh(
                    other.refresh_token,
                    session_policy(),
                    kinevo_mod_identity::app::refresh::Client::Web,
                )
                .await
                .is_err(),
            "a revoked session must no longer refresh"
        );

        let foreign = h.app.new_account();
        {
            let mut tx = Tx::begin(&h.app.db, foreign)
                .await
                .expect("foreign account transaction should begin");
            crate::fixtures::insert_account(
                &mut tx,
                foreign,
                &format!("foreign-{}@example.test", foreign.as_uuid()),
            )
            .await
            .expect("foreign account should insert");
            tx.commit().await.expect("foreign account should commit");
        }
        let foreign_session = family(&h.app, foreign, "foreign-session").await;
        for id in [
            foreign_session.family_id.to_string(),
            Uuid::from_u128(99).to_string(),
        ] {
            let response = request(
                &h,
                Method::DELETE,
                &format!("/api/v1/account/sessions/{id}"),
                None,
                &[],
            )
            .await;
            assert_eq!(response.status(), StatusCode::NOT_FOUND);
        }
    }

    #[tokio::test]
    async fn jobs_prune_expired_sessions_and_resets() {
        let h = harness().await;
        let expired_family = Uuid::from_u128(700);
        let fresh_family = Uuid::from_u128(701);
        let expired_reset = Uuid::from_u128(702);
        let fresh_reset = Uuid::from_u128(703);
        let old = now()
            .checked_add_seconds(-8 * 86_400)
            .expect("old timestamp is valid");
        let new = now()
            .checked_add_seconds(86_400)
            .expect("new timestamp is valid");
        for (id, expires) in [(expired_family, old), (fresh_family, new)] {
            sqlx::query(
                "INSERT INTO public.refresh_token_families (id,account_id,created_at,last_used_at,absolute_expires_at) VALUES ($1,$2,$3::timestamptz,$3::timestamptz,$4::timestamptz)",
            )
            .bind(id)
            .bind(*h.account.as_uuid())
            .bind(now().to_rfc3339())
            .bind(expires.to_rfc3339())
            .execute(h.app.admin_pool())
            .await
            .expect("synthetic families should insert");
        }
        for (id, expires, salt) in [(expired_reset, old, 1_u8), (fresh_reset, new, 2_u8)] {
            sqlx::query(
                "INSERT INTO public.password_resets (id,account_id,token_hash,expires_at,created_at) VALUES ($1,$2,$3,$4::timestamptz,$5::timestamptz)",
            )
            .bind(id)
            .bind(*h.account.as_uuid())
            .bind([salt; 32].as_slice())
            .bind(expires.to_rfc3339())
            .bind(now().to_rfc3339())
            .execute(h.app.admin_pool())
            .await
            .expect("synthetic reset rows should insert");
        }

        let handlers = kinevo_mod_identity::jobs();
        for handler in handlers {
            let kind = handler.kind().as_str();
            let job = ClaimedJob {
                id: JobId::from_uuid(h.app.ids.new_uuid_v7()),
                account_id: None,
                kind: kind.to_owned(),
                payload: json!({}),
                attempts: 1,
                max_attempts: 3,
            };
            handler
                .run(
                    JobContext::new(
                        Arc::new(h.app.db.clone()),
                        h.app.ids.clone(),
                        h.clock.clone(),
                    ),
                    job,
                )
                .await
                .expect("identity prune job should complete");
        }
        let family_ids = sqlx::query_scalar::<_, Uuid>(
            "SELECT id FROM public.refresh_token_families WHERE account_id=$1",
        )
        .bind(*h.account.as_uuid())
        .fetch_all(h.app.admin_pool())
        .await
        .expect("remaining families should query");
        assert!(!family_ids.contains(&expired_family));
        assert!(family_ids.contains(&fresh_family));
        let reset_ids = sqlx::query_scalar::<_, Uuid>(
            "SELECT id FROM public.password_resets WHERE account_id=$1",
        )
        .bind(*h.account.as_uuid())
        .fetch_all(h.app.admin_pool())
        .await
        .expect("remaining resets should query");
        assert!(!reset_ids.contains(&expired_reset));
        assert!(reset_ids.contains(&fresh_reset));
    }

    #[tokio::test]
    async fn password_change_is_idempotent() {
        let h = harness().await;
        let request_body = r#"{"current_password":"current synthetic password","new_password":"a-new-synthetic-password"}"#;
        let headers = [("Idempotency-Key", "00000000-0000-0000-0000-000000000001")];
        let first = request(
            &h,
            Method::POST,
            "/api/v1/account/password",
            Some(request_body),
            &headers,
        )
        .await;
        assert_eq!(first.status(), StatusCode::NO_CONTENT);
        let second = request(
            &h,
            Method::POST,
            "/api/v1/account/password",
            Some(request_body),
            &headers,
        )
        .await;
        assert_eq!(second.status(), StatusCode::NO_CONTENT);
        assert_eq!(
            second
                .headers()
                .get("Idempotency-Replayed")
                .and_then(|v| v.to_str().ok()),
            Some("true")
        );
        assert_eq!(h.passwords.hashes.load(Ordering::Relaxed), 1);
    }

    struct TestReady;

    #[async_trait]
    impl ReadyCheck for TestReady {
        async fn check(&self) -> Result<(), ReadyFailure> {
            Ok(())
        }
    }
}
