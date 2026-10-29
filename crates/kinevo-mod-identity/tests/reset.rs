#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "password reset acceptance tests use isolated synthetic fixtures"
)]

mod reset {
    use std::{
        collections::BTreeMap,
        net::{IpAddr, Ipv4Addr, SocketAddr, TcpListener},
        sync::{
            Arc,
            atomic::{AtomicUsize, Ordering},
        },
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
        errors::{AppError, ErrorCode},
        id::{AccountId, FamilyId, IdGen},
        page::CursorCodec,
        policy::{PolicyValue, ResolutionSource, Resolved},
        secret::Secret,
        time::Instant,
        validate::{
            net::{Email, HttpsUrl},
            tz::Locale,
        },
    };
    use kinevo_mod_identity::infra::mail::SmtpConfig;
    use kinevo_mod_identity::{
        MODULE,
        app::{
            login::PasswordWork,
            reset::{ResetAccount, ResetAccountLookup, ResetService},
        },
        infra::hasher::VerifyOutcome,
        migrations,
    };
    use kinevo_ports::{
        fakes::{
            FakeAccountAccess, FakeInstanceState, FakeMailer, FakeModuleSwitches, FakePolicyReader,
        },
        module::ModuleDescriptor,
        platform::{
            AccountAccessInfo, AccountStatus, Delivery, InstanceInfo, InstanceState,
            ResolvedPolicies,
        },
    };
    use sqlx::Row;
    use tower::ServiceExt;

    const NOW: &str = "2026-11-08T00:00:00Z";
    const PASSWORD: &str = "synthetic sufficiently long password";

    struct CountingLookup {
        values: BTreeMap<String, ResetAccount>,
        calls: AtomicUsize,
    }

    impl CountingLookup {
        fn new(values: impl IntoIterator<Item = (String, ResetAccount)>) -> Self {
            Self {
                values: values.into_iter().collect(),
                calls: AtomicUsize::new(0),
            }
        }

        fn calls(&self) -> usize {
            self.calls.load(Ordering::SeqCst)
        }
    }

    #[async_trait]
    impl ResetAccountLookup for CountingLookup {
        async fn find_by_email(&self, email: &Email) -> Result<Option<ResetAccount>, AppError> {
            self.calls.fetch_add(1, Ordering::SeqCst);
            Ok(self.values.get(email.as_str()).cloned())
        }
    }

    #[derive(Default)]
    struct CountingPasswords {
        dummy_verifies: AtomicUsize,
    }

    #[async_trait]
    impl PasswordWork for CountingPasswords {
        async fn verify(&self, candidate: &Secret<String>, stored_hash: &str) -> VerifyOutcome {
            if stored_hash == format!("synthetic-hash:{}", candidate.expose()) {
                VerifyOutcome::Ok
            } else {
                VerifyOutcome::Wrong
            }
        }

        async fn verify_dummy(&self, _candidate: &Secret<String>) {
            self.dummy_verifies.fetch_add(1, Ordering::SeqCst);
        }

        async fn hash(&self, candidate: &Secret<String>) -> Result<String, AppError> {
            Ok(format!("synthetic-hash:{}", candidate.expose()))
        }
    }

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
        values.insert("auth.password.min_length", resolved(PolicyValue::Int(12)));
        values.insert(
            "auth.password_reset.ttl",
            resolved(PolicyValue::Seconds(3_600)),
        );
        values.insert("ratelimit.auth_per_minute", resolved(PolicyValue::Int(120)));
        values.insert("ratelimit.api_per_minute", resolved(PolicyValue::Int(120)));
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

    async fn insert_account(app: &TestApp, id: AccountId, email: &str, status: &str) {
        sqlx::query(
            "INSERT INTO public.accounts (id,email,display_name,password_hash,timezone,status) \
             VALUES ($1,$2,'Synthetic Account','synthetic-old-hash','UTC',$3)",
        )
        .bind(*id.as_uuid())
        .bind(email)
        .bind(status)
        .execute(app.admin_pool())
        .await
        .expect("synthetic account should insert");
    }

    fn instance() -> Arc<dyn InstanceState> {
        Arc::new(FakeInstanceState::new(InstanceInfo {
            setup_completed: true,
            base_url: Some(
                kinevo_kernel::validate::net::HttpsUrl::parse("https://identity.example.test")
                    .expect("test URL is valid"),
            ),
            default_locale: Locale::En,
        }))
    }

    fn service(
        app: &TestApp,
        mailer: Arc<dyn kinevo_ports::platform::Mailer>,
        lookup: Arc<dyn ResetAccountLookup>,
    ) -> ResetService {
        ResetService::new_with_lookup(
            app.db.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            Arc::new(CountingPasswords::default()),
            policies(),
            instance(),
            mailer,
            app.clock.clone(),
            SubscriberRegistry::default(),
            lookup,
        )
    }

    fn http_router(
        app: &TestApp,
        service: Arc<ResetService>,
        policies: Arc<FakePolicyReader>,
    ) -> axum::Router {
        let descriptors: [&ModuleDescriptor; 1] = [&MODULE];
        let permissions = Arc::new(
            PermissionTable::from_descriptors(&descriptors).expect("module permissions are valid"),
        );
        let kinds =
            Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds are valid"));
        let codec = Arc::new(TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("test key is valid"),
        ));
        let gate = Arc::new(Gate::new(
            codec,
            Arc::new(AccountCache::new(Arc::new(FakeAccountAccess::default()))),
            Arc::new(FakeModuleSwitches::default()),
            permissions,
            kinds,
        ));
        let state = HttpState::new(
            app.clock.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            Arc::new(Catalog::load()),
            instance(),
            Arc::new(TestReady),
            Some(HttpsUrl::parse("https://api.example.test").expect("test URL is valid")),
            Arc::new(CursorCodec::new(Secret::new([0x22; 32]))),
            1_048_576,
            gate,
        )
        .with_origin_config(OriginConfig {
            public_origin: Some("https://api.example.test".to_owned()),
            android_origin_enabled: true,
        })
        .with_rate_limiter(Arc::new(RateLimiter::new(
            policies,
            app.clock.clone(),
            TrustedProxies::default(),
        )));
        kinevo_mod_identity::reset_router(service)
            .into_router(state)
            .expect("public reset routes should validate")
    }

    fn admin_reset_router(
        app: &TestApp,
        service: Arc<ResetService>,
        admin: AccountId,
        policies: Arc<FakePolicyReader>,
    ) -> axum::Router {
        let descriptors: [&ModuleDescriptor; 1] = [&MODULE];
        let permissions = Arc::new(
            PermissionTable::from_descriptors(&descriptors).expect("module permissions are valid"),
        );
        let kinds =
            Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds are valid"));
        let codec = Arc::new(TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("test key is valid"),
        ));
        let access = Arc::new(FakeAccountAccess::default());
        access
            .insert(AccountAccessInfo {
                id: admin,
                status: AccountStatus::Active,
                is_instance_admin: true,
                version: 1,
            })
            .expect("admin access row is valid");
        let gate = Arc::new(Gate::new(
            codec,
            Arc::new(AccountCache::new(access)),
            Arc::new(FakeModuleSwitches::default()),
            permissions,
            kinds,
        ));
        let state = HttpState::new(
            app.clock.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            Arc::new(Catalog::load()),
            instance(),
            Arc::new(TestReady),
            Some(HttpsUrl::parse("https://api.example.test").expect("test URL is valid")),
            Arc::new(CursorCodec::new(Secret::new([0x22; 32]))),
            1_048_576,
            gate,
        )
        .with_origin_config(OriginConfig {
            public_origin: Some("https://api.example.test".to_owned()),
            android_origin_enabled: true,
        })
        .with_rate_limiter(Arc::new(RateLimiter::new(
            policies,
            app.clock.clone(),
            TrustedProxies::default(),
        )));
        kinevo_mod_identity::admin_reset_router(service)
            .into_router(state)
            .expect("admin reset route should validate")
    }

    fn access_token(app: &TestApp, account: AccountId) -> String {
        let codec = TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("test key is valid"),
        );
        codec.issue(&Claims {
            sub: account,
            sid: FamilyId::from_uuid(app.ids.new_uuid_v7()),
            jti: app.ids.new_uuid_v7(),
            iat: now(),
            exp: now()
                .checked_add_seconds(600)
                .expect("test token expiry is valid"),
            mfa: false,
        })
    }

    async fn issue_reset_request(
        router: &axum::Router,
        app: &TestApp,
        admin: AccountId,
        target: AccountId,
    ) -> axum::response::Response {
        let mut request = Request::builder()
            .method(Method::POST)
            .uri(format!("/api/v1/admin/accounts/{target}/password-reset"))
            .header(
                "Authorization",
                format!("Bearer {}", access_token(app, admin)),
            )
            .header("origin", "https://api.example.test")
            .body(Body::empty())
            .expect("synthetic reset issue request should build");
        request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
            IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
            12345,
        )));
        router
            .clone()
            .oneshot(request)
            .await
            .expect("reset issue route should respond")
    }

    async fn request_reset(router: &axum::Router, email: &str) -> (StatusCode, Vec<u8>) {
        let body = serde_json::json!({"email": email}).to_string();
        let mut request = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/password-reset/request")
            .header("content-type", "application/json")
            .header("origin", "https://api.example.test")
            .body(Body::from(body))
            .expect("synthetic reset request should build");
        request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
            IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
            12345,
        )));
        let response = router
            .clone()
            .oneshot(request)
            .await
            .expect("reset route should respond");
        let status = response.status();
        let body = to_bytes(response.into_body(), 1_048_576)
            .await
            .expect("response body should read")
            .to_vec();
        (status, body)
    }

    struct TestReady;

    #[async_trait]
    impl ReadyCheck for TestReady {
        async fn check(&self) -> Result<(), ReadyFailure> {
            Ok(())
        }
    }

    fn token_from(link: &str) -> String {
        link.split_once("token=")
            .map(|(_, token)| token.to_owned())
            .expect("reset URL contains its token")
    }

    #[tokio::test]
    async fn sm_22_new_token_invalidates_old() {
        let app = app().await;
        let admin = app.new_account();
        let account = app.new_account();
        insert_account(&app, admin, "admin@example.test", "active").await;
        insert_account(&app, account, "reset@example.test", "active").await;
        sqlx::query("UPDATE public.accounts SET is_instance_admin=true WHERE id=$1")
            .bind(*admin.as_uuid())
            .execute(app.admin_pool())
            .await
            .expect("admin role should be set");
        let service = Arc::new(service(
            &app,
            Arc::new(FakeMailer::default()),
            Arc::new(CountingLookup::new([])),
        ));
        let router = admin_reset_router(&app, Arc::clone(&service), admin, policies());
        let first_response = issue_reset_request(&router, &app, admin, account).await;
        assert_eq!(first_response.status(), StatusCode::CREATED);
        assert_eq!(
            first_response
                .headers()
                .get("cache-control")
                .and_then(|value| value.to_str().ok()),
            Some("no-store")
        );
        let first_body = to_bytes(first_response.into_body(), 1_048_576)
            .await
            .expect("first response body should read");
        let first_value: serde_json::Value =
            serde_json::from_slice(&first_body).expect("first response is JSON");
        let first_url = first_value["reset_url"]
            .as_str()
            .expect("first reset link is returned");
        assert!(first_url.starts_with("https://identity.example.test/reset-password?token="));
        let second_response = issue_reset_request(&router, &app, admin, account).await;
        assert_eq!(second_response.status(), StatusCode::CREATED);
        let second_body = to_bytes(second_response.into_body(), 1_048_576)
            .await
            .expect("second response body should read");
        let second_value: serde_json::Value =
            serde_json::from_slice(&second_body).expect("second response is JSON");
        let second_url = second_value["reset_url"]
            .as_str()
            .expect("second reset link is returned");
        let first_token = token_from(first_url);
        let second_token = token_from(second_url);
        assert_ne!(first_token, second_token);
        let first_error = service
            .confirm(Secret::new(first_token), Secret::new(PASSWORD.to_owned()))
            .await
            .expect_err("old token is invalidated");
        assert_eq!(first_error.code, ErrorCode::ResetTokenInvalid);
        let row = sqlx::query("SELECT token_hash, used_at IS NOT NULL AS used FROM public.password_resets ORDER BY created_at")
            .fetch_all(app.admin_pool()).await.expect("reset rows should be queryable");
        assert_eq!(row.len(), 2);
        assert!(
            row[0]
                .try_get::<bool, _>("used")
                .expect("used flag is selected")
        );
        let stored: Vec<u8> = row[1].try_get("token_hash").expect("hash is stored");
        let raw = kinevo_kernel::crypto::decode_token(&second_token).expect("token is base64url");
        assert_eq!(stored, kinevo_kernel::crypto::hash_token(&raw));
    }

    #[tokio::test]
    async fn sm_22_confirm_once_and_expiry() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "reset@example.test", "active").await;
        let family = app.ids.new_uuid_v7();
        sqlx::query(
            "INSERT INTO public.refresh_token_families \
             (id,account_id,created_at,last_used_at,absolute_expires_at) \
             VALUES ($1,$2,$3::timestamptz,$3::timestamptz,$4::timestamptz)",
        )
        .bind(family)
        .bind(*account.as_uuid())
        .bind(now().to_rfc3339())
        .bind(
            now()
                .checked_add_seconds(86_400)
                .expect("family expiry is valid")
                .to_rfc3339(),
        )
        .execute(app.admin_pool())
        .await
        .expect("synthetic family should insert");
        let service = service(
            &app,
            Arc::new(FakeMailer::default()),
            Arc::new(CountingLookup::new([])),
        );
        let issued = service
            .issue_for_account(account, None)
            .await
            .expect("reset link should issue");
        let token = token_from(issued.url.expose());
        service
            .confirm(Secret::new(token.clone()), Secret::new(PASSWORD.to_owned()))
            .await
            .expect("valid reset should commit");
        let stored: String =
            sqlx::query_scalar("SELECT password_hash FROM public.accounts WHERE id=$1")
                .bind(*account.as_uuid())
                .fetch_one(app.admin_pool())
                .await
                .expect("updated password is readable");
        assert_eq!(stored, format!("synthetic-hash:{PASSWORD}"));
        let revoked: Option<String> = sqlx::query_scalar(
            "SELECT revoked_reason FROM public.refresh_token_families WHERE id=$1",
        )
        .bind(family)
        .fetch_one(app.admin_pool())
        .await
        .expect("family state should be readable");
        assert_eq!(revoked.as_deref(), Some("password_changed"));
        let event: String = sqlx::query_scalar(
            "SELECT kind FROM public.outbox_events WHERE account_id=$1 AND kind='PasswordChanged'",
        )
        .bind(*account.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("password event should be emitted");
        assert_eq!(event, "PasswordChanged");
        let audit: String = sqlx::query_scalar("SELECT action FROM public.audit_log WHERE account_id=$1 AND action='auth.password_changed'")
            .bind(*account.as_uuid()).fetch_one(app.admin_pool()).await.expect("password change should be audited");
        assert_eq!(audit, "auth.password_changed");
        let reused = service
            .confirm(Secret::new(token), Secret::new(PASSWORD.to_owned()))
            .await
            .expect_err("reset link is one-time");
        assert_eq!(reused.code, ErrorCode::ResetTokenInvalid);

        let expired = service
            .issue_for_account(account, None)
            .await
            .expect("another reset link should issue");
        app.clock
            .advance_seconds(3_601)
            .expect("fake clock should advance past TTL");
        let error = service
            .confirm(
                Secret::new(token_from(expired.url.expose())),
                Secret::new(PASSWORD.to_owned()),
            )
            .await
            .expect_err("expired link should fail");
        assert_eq!(error.code, ErrorCode::ResetTokenInvalid);
    }

    #[tokio::test]
    async fn id_fr_13_request_never_leaks_existence() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "known@example.test", "active").await;
        let lookup = Arc::new(CountingLookup::new([
            (
                "known@example.test".to_owned(),
                ResetAccount {
                    id: account,
                    locale: Locale::En,
                    active: true,
                },
            ),
            (
                "disabled@example.test".to_owned(),
                ResetAccount {
                    id: account,
                    locale: Locale::En,
                    active: false,
                },
            ),
        ]));
        let no_mail = Arc::new(FakeMailer::default());
        let no_mail_service = service(&app, no_mail.clone(), lookup.clone());
        let no_mail_router = http_router(&app, Arc::new(no_mail_service), policies());
        let unknown = request_reset(&no_mail_router, "unknown@example.test").await;
        let disabled = request_reset(&no_mail_router, "disabled@example.test").await;
        let active = request_reset(&no_mail_router, "known@example.test").await;
        assert_eq!(unknown, (StatusCode::ACCEPTED, Vec::new()));
        assert_eq!(disabled, unknown);
        assert_eq!(active, unknown);
        assert!(no_mail.messages().is_empty());

        let mailer = Arc::new(FakeMailer::with_delivery(Delivery::Sent));
        let mail_service = service(&app, mailer.clone(), lookup.clone());
        let mail_router = http_router(&app, Arc::new(mail_service), policies());
        assert_eq!(
            request_reset(&mail_router, "known@example.test").await,
            unknown
        );
        let messages = mailer.messages();
        assert_eq!(messages.len(), 1);
        assert_eq!(messages[0].to.as_str(), "known@example.test");
        assert_eq!(messages[0].vars.len(), 2);
        assert_eq!(messages[0].vars[0].0, "reset_url");
        assert_eq!(messages[0].vars[1], ("minutes", "60".to_owned()));
        assert!(!messages[0].vars[0].1.contains("known@example.test"));

        let failing_mailer = Arc::new(FakeMailer::failing());
        let failing_service = service(&app, failing_mailer, lookup);
        let failing_router = http_router(&app, Arc::new(failing_service), policies());
        assert_eq!(
            request_reset(&failing_router, "known@example.test").await,
            unknown
        );
    }

    #[tokio::test]
    async fn id_fr_13_equivalent_work() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "known@example.test", "active").await;
        let lookup = Arc::new(CountingLookup::new([(
            "known@example.test".to_owned(),
            ResetAccount {
                id: account,
                locale: Locale::En,
                active: true,
            },
        )]));
        let passwords = Arc::new(CountingPasswords::default());
        let service = ResetService::new_with_lookup(
            app.db.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            passwords.clone(),
            policies(),
            instance(),
            Arc::new(FakeMailer::default()),
            app.clock.clone(),
            SubscriberRegistry::default(),
            lookup.clone(),
        );
        service
            .request(Email::parse("known@example.test").expect("synthetic email is valid"))
            .await
            .expect("known reset request is opaque");
        let known_lookups = lookup.calls();
        let known_hashes = passwords.dummy_verifies.load(Ordering::SeqCst);
        lookup.calls.store(0, Ordering::SeqCst);
        passwords.dummy_verifies.store(0, Ordering::SeqCst);
        service
            .request(Email::parse("unknown@example.test").expect("synthetic email is valid"))
            .await
            .expect("unknown reset request is opaque");
        assert_eq!(lookup.calls(), known_lookups);
        assert_eq!(
            passwords.dummy_verifies.load(Ordering::SeqCst),
            known_hashes
        );
    }

    #[tokio::test]
    async fn sec_ac_45_no_reset_tokens_in_logs() {
        let app = app().await;
        let actor = app.new_account();
        let account = app.new_account();
        insert_account(&app, actor, "admin@example.test", "active").await;
        insert_account(&app, account, "reset@example.test", "active").await;
        let service = service(
            &app,
            Arc::new(FakeMailer::default()),
            Arc::new(CountingLookup::new([])),
        );
        let issued = service
            .issue_for_account(account, Some(actor))
            .await
            .expect("admin reset link should issue");
        let token = token_from(issued.url.expose());
        service
            .confirm(Secret::new(token.clone()), Secret::new(PASSWORD.to_owned()))
            .await
            .expect("reset should complete");
        let row = sqlx::query(
            "SELECT token_hash::text AS token_hash FROM public.password_resets WHERE account_id=$1",
        )
        .bind(*account.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("reset hash is queryable");
        let stored: String = row.try_get("token_hash").expect("hash is selected");
        let audit: Vec<String> =
            sqlx::query_scalar("SELECT details::text FROM public.audit_log WHERE account_id=$1")
                .bind(*account.as_uuid())
                .fetch_all(app.admin_pool())
                .await
                .expect("audit details are queryable");
        assert!(!stored.contains(&token));
        assert!(audit.iter().all(|details| !details.contains(&token)));
    }

    mod mail {
        use super::*;

        #[tokio::test]
        async fn request_endpoint_swallows_send_errors() {
            let app = app().await;
            let account = app.new_account();
            insert_account(&app, account, "known@example.test", "active").await;
            let lookup = Arc::new(CountingLookup::new([(
                "known@example.test".to_owned(),
                ResetAccount {
                    id: account,
                    locale: Locale::En,
                    active: true,
                },
            )]));
            let listener =
                TcpListener::bind("127.0.0.1:0").expect("synthetic SMTP port should be allocated");
            let port = listener
                .local_addr()
                .expect("synthetic SMTP listener should have an address")
                .port();
            drop(listener);
            let config = SmtpConfig::parse(
                &format!("smtp://127.0.0.1:{port}/?from=sender@example.test"),
                None,
            )
            .expect("synthetic SMTP configuration should parse");
            let mailer = kinevo_mod_identity::smtp_mailer(config, None, Arc::new(Catalog::load()));
            let reset_service = service(&app, mailer, lookup);
            let router = http_router(&app, Arc::new(reset_service), policies());
            assert_eq!(
                request_reset(&router, "known@example.test").await,
                (StatusCode::ACCEPTED, Vec::new())
            );
        }
    }
}
