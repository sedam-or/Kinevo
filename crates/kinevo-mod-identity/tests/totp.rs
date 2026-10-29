#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "TOTP acceptance tests use synthetic isolated fixtures"
)]

mod totp {
    use std::{
        collections::BTreeMap,
        fmt,
        net::{IpAddr, Ipv4Addr, SocketAddr},
        sync::{
            Arc, Mutex,
            atomic::{AtomicU64, Ordering},
        },
    };

    use async_trait::async_trait;
    use axum::{
        body::{Body, to_bytes},
        extract::ConnectInfo,
        http::{Method, Request, Response, StatusCode, header},
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
        errors::{AppError, ErrorCode},
        id::{AccountId, IdGen, TokenGen},
        page::CursorCodec,
        policy::{PolicyValue, ResolutionSource, Resolved},
        secret::Secret,
        time::{FixedClock, Instant},
        validate::net::HttpsUrl,
    };
    use kinevo_mod_identity::{
        MODULE,
        app::{
            login::{ClientAddr, LoginPolicy, LoginRequest, LoginService, PasswordWork},
            totp::{ReplayCache, TotpMfaVerifier, TotpService},
        },
        domain::{
            lockout::LockoutPolicy,
            session::SessionPolicy,
            totp::{
                RECOVERY_ALPHABET, RecoveryCode, accepts, generate_recovery_codes, hash, step_for,
            },
        },
        migrations,
    };
    use kinevo_ports::{
        fakes::{
            FakeAccountAccess, FakeInstanceState, FakeModuleSwitches, FakePolicyReader,
            FakeSecretStore,
        },
        module::ModuleDescriptor,
        platform::{
            AccountAccessInfo, AccountStatus, InstanceInfo, ResolvedPolicies, SecretName,
            SecretScope, SecretStore,
        },
    };
    use totp_rs::{Builder, Secret as TotpSecret};
    use tower::ServiceExt;
    use tracing::{
        Event, Subscriber,
        field::{Field, Visit},
        instrument::WithSubscriber,
        span,
    };

    const NOW: &str = "2026-11-04T00:00:00Z";
    const TEST_ORIGIN: &str = "https://api.example.test";
    const EMAIL: &str = "totp-user@example.test";
    const PASSWORD: &str = "synthetic correct password";
    const PASSWORD_HASH: &str = "synthetic-password-hash";

    struct SyntheticTokenGen(AtomicU64);

    impl SyntheticTokenGen {
        fn new(seed: u64) -> Self {
            Self(AtomicU64::new(seed))
        }
    }

    impl TokenGen for SyntheticTokenGen {
        fn fill(&self, buffer: &mut [u8]) {
            let sequence = self.0.fetch_add(1, Ordering::Relaxed);
            for (index, byte) in buffer.iter_mut().enumerate() {
                let value = sequence
                    .wrapping_add(u64::try_from(index).unwrap_or(u64::MAX))
                    .wrapping_mul(37);
                *byte = value.to_le_bytes()[0];
            }
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

    fn policies(required: bool) -> Arc<FakePolicyReader> {
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
            ("auth.totp.required", PolicyValue::Bool(required)),
            ("ratelimit.api_per_minute", PolicyValue::Int(120)),
            ("ratelimit.auth_per_minute", PolicyValue::Int(120)),
        ] {
            values.insert(key, resolved(value));
        }
        Arc::new(FakePolicyReader::new(values))
    }

    #[derive(Default)]
    struct SyntheticPasswords;

    #[async_trait]
    impl PasswordWork for SyntheticPasswords {
        async fn verify(
            &self,
            candidate: &Secret<String>,
            stored_hash: &str,
        ) -> kinevo_mod_identity::infra::hasher::VerifyOutcome {
            if candidate.expose() == PASSWORD && stored_hash == PASSWORD_HASH {
                kinevo_mod_identity::infra::hasher::VerifyOutcome::Ok
            } else {
                kinevo_mod_identity::infra::hasher::VerifyOutcome::Wrong
            }
        }

        async fn verify_dummy(&self, _candidate: &Secret<String>) {}

        async fn hash(&self, _candidate: &Secret<String>) -> Result<String, AppError> {
            Ok("synthetic-new-password-hash".to_owned())
        }
    }

    struct Harness {
        app: TestApp,
        account: AccountId,
        clock: Arc<FixedClock>,
        tokens: Arc<TokenCodec>,
        secrets: Arc<FakeSecretStore>,
        policies: Arc<FakePolicyReader>,
        replay: Arc<ReplayCache>,
        service: Arc<TotpService>,
        router: axum::Router,
    }

    async fn harness(required: bool) -> Harness {
        let sources: [MigrationSource; 2] = [kinevo_db::migrations_source(), migrations()];
        let app = TestApp::builder()
            .migrations(&sources)
            .clock_at(now())
            .build()
            .await
            .expect("disposable PostgreSQL test database should start");
        let account = app.new_account();
        let mut tx = Tx::begin(&app.db, account)
            .await
            .expect("account transaction should begin");
        sqlx::query(
            "INSERT INTO public.accounts \
             (id, email, display_name, password_hash, timezone, status) \
             VALUES ($1, $2, 'Synthetic TOTP Account', $3, 'UTC', 'active')",
        )
        .bind(*account.as_uuid())
        .bind(EMAIL)
        .bind(PASSWORD_HASH)
        .execute(tx.conn())
        .await
        .expect("synthetic account should insert");
        tx.commit()
            .await
            .expect("account transaction should commit");

        let clock = Arc::new(FixedClock::new(now()));
        let tokens = Arc::new(TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic key ring is valid"),
        ));
        let secrets = Arc::new(FakeSecretStore::default());
        let policies = policies(required);
        let replay = Arc::new(ReplayCache::new());
        let passwords = Arc::new(SyntheticPasswords);
        let service = Arc::new(TotpService::new(
            app.db.clone(),
            secrets.clone(),
            Arc::new(SyntheticTokenGen::new(7)),
            policies.clone(),
            passwords,
            app.ids.clone(),
            clock.clone(),
            replay.clone(),
        ));
        let router = totp_router(
            &app,
            account,
            clock.clone(),
            tokens.clone(),
            policies.clone(),
            service.clone(),
        );
        Harness {
            app,
            account,
            clock,
            tokens,
            secrets,
            policies,
            replay,
            service,
            router,
        }
    }

    fn totp_router(
        app: &TestApp,
        account: AccountId,
        clock: Arc<FixedClock>,
        tokens: Arc<TokenCodec>,
        policies: Arc<FakePolicyReader>,
        service: Arc<TotpService>,
    ) -> axum::Router {
        let account_access = Arc::new(FakeAccountAccess::default());
        account_access
            .insert(AccountAccessInfo {
                id: account,
                status: AccountStatus::Active,
                is_instance_admin: false,
                version: 1,
            })
            .expect("synthetic account access row should insert");
        let descriptors: [&ModuleDescriptor; 1] = [&MODULE];
        let permissions = Arc::new(
            PermissionTable::from_descriptors(&descriptors).expect("permissions are valid"),
        );
        let kinds =
            Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds are valid"));
        let gate = Arc::new(Gate::new(
            tokens,
            Arc::new(AccountCache::new(account_access)),
            Arc::new(FakeModuleSwitches::default()),
            permissions,
            kinds,
        ));
        let state = HttpState::new(
            clock.clone(),
            app.ids.clone(),
            app.tokens.clone(),
            Arc::new(Catalog::load()),
            Arc::new(FakeInstanceState::new(InstanceInfo {
                setup_completed: true,
                base_url: None,
                default_locale: kinevo_kernel::validate::tz::Locale::En,
            })),
            Arc::new(TestReady),
            Some(HttpsUrl::parse(TEST_ORIGIN).expect("test URL is valid")),
            Arc::new(CursorCodec::new(Secret::new([0x22; 32]))),
            1_048_576,
            gate,
        )
        .with_origin_config(OriginConfig {
            public_origin: Some(TEST_ORIGIN.to_owned()),
            android_origin_enabled: true,
        })
        .with_rate_limiter(Arc::new(RateLimiter::new(
            policies,
            clock,
            TrustedProxies::default(),
        )));
        kinevo_mod_identity::totp_router(service)
            .into_router(state)
            .expect("TOTP routes should validate")
    }

    fn request(
        h: &Harness,
        method: Method,
        path: &str,
        json: Option<serde_json::Value>,
    ) -> Request<Body> {
        let claims = Claims {
            sub: h.account,
            sid: kinevo_kernel::id::FamilyId::from_uuid(h.app.ids.new_uuid_v7()),
            jti: h.app.ids.new_uuid_v7(),
            iat: now(),
            exp: now()
                .checked_add_seconds(600)
                .expect("test expiry is valid"),
            mfa: false,
        };
        let access_token = h.tokens.issue(&claims);
        let mut builder = Request::builder()
            .method(method)
            .uri(path)
            .header(header::AUTHORIZATION, format!("Bearer {access_token}"))
            .header(header::ORIGIN, TEST_ORIGIN);
        if json.is_some() {
            builder = builder.header(header::CONTENT_TYPE, "application/json");
        }
        let mut request = builder
            .body(Body::from(
                json.map_or_else(String::new, |value| value.to_string()),
            ))
            .expect("synthetic request should build");
        request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
            IpAddr::V4(Ipv4Addr::new(192, 0, 2, 11)),
            12345,
        )));
        request
    }

    async fn send(
        h: &Harness,
        method: Method,
        path: &str,
        json: Option<serde_json::Value>,
    ) -> Response<Body> {
        h.router
            .clone()
            .oneshot(request(h, method, path, json))
            .await
            .expect("TOTP router should respond")
    }

    async fn response_json(response: Response<Body>) -> serde_json::Value {
        let bytes = to_bytes(response.into_body(), 1_048_576)
            .await
            .expect("response body should read");
        serde_json::from_slice(&bytes).expect("response should be JSON")
    }

    async fn stored_seed(h: &Harness) -> String {
        let name = SecretName::parse("identity.totp_seed").expect("TOTP seed name is registered");
        h.secrets
            .get(SecretScope::Account(h.account), name)
            .await
            .expect("secret lookup should work")
            .expect("enrolled seed should be stored")
            .expose()
            .clone()
    }

    fn code_at(seed_base32: &str, now: Instant) -> String {
        Builder::new()
            .with_secret(TotpSecret::try_from_base32(seed_base32).expect("seed is base32"))
            .build()
            .expect("RFC 6238 TOTP builds")
            .generate(
                u64::try_from(now.unix_millis().div_euclid(1_000)).expect("test time is positive"),
            )
            .to_string()
    }

    fn different_code(code: &str) -> String {
        let first = if code.starts_with('0') { '1' } else { '0' };
        format!("{first}{}", &code[1..])
    }

    fn login_service(h: &Harness) -> LoginService {
        LoginService::new(
            h.app.db.clone(),
            Arc::new(SyntheticPasswords),
            Arc::new(TotpMfaVerifier::new(
                h.app.db.clone(),
                h.secrets.clone(),
                h.replay.clone(),
            )),
            h.policies.clone(),
            h.clock.clone(),
            h.app.ids.clone(),
            h.app.tokens.clone(),
            h.tokens.clone(),
        )
    }

    fn login_policy() -> LoginPolicy {
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
                idle_ttl: 1_209_600,
                absolute_ttl: 2_592_000,
                grace: 10,
            },
        }
    }

    async fn login(
        h: &Harness,
        totp_code: Option<String>,
        recovery_code: Option<String>,
    ) -> Result<kinevo_mod_identity::app::login::LoginOutcome, AppError> {
        login_service(h)
            .login(
                LoginRequest {
                    email: EMAIL.to_owned(),
                    password: Secret::new(PASSWORD.to_owned()),
                    totp_code,
                    recovery_code: recovery_code.map(Secret::new),
                },
                kinevo_mod_identity::app::refresh::Client::Web,
                ClientAddr::new("192.0.2.12".parse().expect("synthetic client IP is valid")),
                login_policy(),
            )
            .await
    }

    #[tokio::test]
    async fn id_fr_08_enroll_shape() {
        let h = harness(false).await;
        let response = send(&h, Method::POST, "/api/v1/account/totp/enroll", None).await;
        assert_eq!(response.status(), StatusCode::OK);
        assert_eq!(
            response
                .headers()
                .get(header::CACHE_CONTROL)
                .and_then(|v| v.to_str().ok()),
            Some("no-store")
        );
        let body = response_json(response).await;
        let uri = body["otpauth_uri"]
            .as_str()
            .expect("enrolment URI should be returned");
        assert!(uri.starts_with("otpauth://totp/Nivaras:"));
        for parameter in [
            "issuer=Nivaras",
            "algorithm=SHA1",
            "digits=6",
            "period=30",
            "secret=",
        ] {
            assert!(uri.contains(parameter), "missing URI parameter {parameter}");
        }
        let codes = body["recovery_codes"]
            .as_array()
            .expect("recovery codes are returned");
        assert_eq!(codes.len(), 10);
        assert!(codes.iter().all(|code| {
            code.as_str()
                .is_some_and(|value| value.len() == 11 && value.as_bytes()[5] == b'-')
        }));
        let seed = stored_seed(&h).await;
        assert!(uri.contains(&format!("secret={seed}")));
        assert!(!totp_enabled(&h).await);
    }

    #[tokio::test]
    async fn id_fr_08_confirm_flow() {
        let h = harness(false).await;
        let body =
            response_json(send(&h, Method::POST, "/api/v1/account/totp/enroll", None).await).await;
        let seed = stored_seed(&h).await;
        let code = code_at(&seed, now());
        let wrong = different_code(&code);
        let invalid = send(
            &h,
            Method::POST,
            "/api/v1/account/totp/confirm",
            Some(serde_json::json!({"code": wrong})),
        )
        .await;
        assert_eq!(invalid.status(), StatusCode::UNPROCESSABLE_ENTITY);
        assert_eq!(response_json(invalid).await["code"], "totp_code_invalid");
        assert!(!totp_enabled(&h).await);
        let confirmed = send(
            &h,
            Method::POST,
            "/api/v1/account/totp/confirm",
            Some(serde_json::json!({"code": code})),
        )
        .await;
        assert_eq!(confirmed.status(), StatusCode::NO_CONTENT);
        assert!(totp_enabled(&h).await);
        assert_eq!(
            body["recovery_codes"]
                .as_array()
                .expect("codes exist")
                .len(),
            10
        );
    }

    #[tokio::test]
    async fn sec_ac_07_replay_in_same_step_rejected() {
        let h = harness(false).await;
        let _enrolment = h.service_enroll().await;
        set_totp_enabled(&h, true).await;
        let seed = stored_seed(&h).await;
        let code = code_at(&seed, now());
        assert!(login(&h, Some(code.clone()), None).await.is_ok());
        let second = login(&h, Some(code), None)
            .await
            .expect_err("replayed TOTP must fail");
        assert_eq!(second.code, ErrorCode::InvalidMfaCode);
    }

    #[tokio::test]
    async fn id_fr_08_recovery_code_single_use() {
        let h = harness(false).await;
        let enrolment = h.service_enroll().await;
        let seed = stored_seed(&h).await;
        let code = code_at(&seed, now());
        h.service
            .confirm(h.account, Secret::new(code), now())
            .await
            .expect("TOTP confirmation should succeed");
        let recovery = enrolment
            .recovery_codes
            .first()
            .expect("one-time codes exist")
            .as_str()
            .to_owned();
        let normalized = recovery.to_lowercase();
        let first = login(&h, None, Some(normalized))
            .await
            .expect("recovery code should authenticate once");
        let claims = h
            .tokens
            .verify(&first.access_token, now())
            .expect("access token should verify");
        assert!(claims.mfa);
        let second = login(&h, None, Some(recovery))
            .await
            .expect_err("used recovery code must fail");
        assert_eq!(second.code, ErrorCode::InvalidMfaCode);
    }

    #[tokio::test]
    async fn totp_already_enabled() {
        let h = harness(false).await;
        let _enrolment = h.service_enroll().await;
        set_totp_enabled(&h, true).await;
        let response = send(&h, Method::POST, "/api/v1/account/totp/enroll", None).await;
        assert_eq!(response.status(), StatusCode::CONFLICT);
        assert_eq!(
            response_json(response).await["code"],
            "totp_already_enabled"
        );
    }

    #[tokio::test]
    async fn disable_requires_password_and_code() {
        let h = harness(false).await;
        let enrolment = h.service_enroll().await;
        let seed = stored_seed(&h).await;
        h.service
            .confirm(h.account, Secret::new(code_at(&seed, now())), now())
            .await
            .expect("TOTP confirmation should succeed");
        let next_code = code_at(
            &seed,
            now()
                .checked_add_seconds(30)
                .expect("next step is in range"),
        );
        let wrong_password = send(
            &h,
            Method::DELETE,
            "/api/v1/account/totp",
            Some(serde_json::json!({"password":"wrong synthetic password", "code":next_code})),
        )
        .await;
        assert_eq!(wrong_password.status(), StatusCode::UNPROCESSABLE_ENTITY);
        assert_eq!(
            response_json(wrong_password).await["code"],
            "current_password_incorrect"
        );
        let wrong_code = send(
            &h,
            Method::DELETE,
            "/api/v1/account/totp",
            Some(serde_json::json!({"password":PASSWORD, "code":"000000"})),
        )
        .await;
        assert_eq!(wrong_code.status(), StatusCode::UNPROCESSABLE_ENTITY);
        assert_eq!(response_json(wrong_code).await["code"], "totp_code_invalid");
        let disabled = send(
            &h,
            Method::DELETE,
            "/api/v1/account/totp",
            Some(serde_json::json!({"password":PASSWORD, "code":next_code})),
        )
        .await;
        assert_eq!(disabled.status(), StatusCode::NO_CONTENT);
        assert!(!totp_enabled(&h).await);
        let name = SecretName::parse("identity.totp_seed").expect("seed name is registered");
        assert!(
            h.secrets
                .get(SecretScope::Account(h.account), name)
                .await
                .expect("secret lookup succeeds")
                .is_none()
        );
        let count: i64 =
            sqlx::query_scalar("SELECT count(*) FROM public.recovery_codes WHERE account_id=$1")
                .bind(*h.account.as_uuid())
                .fetch_one(h.app.admin_pool())
                .await
                .expect("recovery rows query");
        assert_eq!(count, 0);
        assert_eq!(enrolment.recovery_codes.len(), 10);
    }

    #[tokio::test]
    async fn totp_required_by_policy() {
        let h = harness(true).await;
        let _enrolment = h.service_enroll().await;
        let seed = stored_seed(&h).await;
        h.service
            .confirm(h.account, Secret::new(code_at(&seed, now())), now())
            .await
            .expect("TOTP confirmation should succeed");
        let code = code_at(
            &seed,
            now()
                .checked_add_seconds(30)
                .expect("next step is in range"),
        );
        let response = send(
            &h,
            Method::DELETE,
            "/api/v1/account/totp",
            Some(serde_json::json!({"password":PASSWORD, "code":code})),
        )
        .await;
        assert_eq!(response.status(), StatusCode::CONFLICT);
        assert_eq!(
            response_json(response).await["code"],
            "totp_required_by_policy"
        );
    }

    #[tokio::test]
    async fn sec_ac_45_no_totp_material_in_logs() {
        let h = harness(false).await;
        let logs = CapturingSubscriber::default();
        let response = h
            .router
            .clone()
            .oneshot(request(
                &h,
                Method::POST,
                "/api/v1/account/totp/enroll",
                None,
            ))
            .with_subscriber(logs.clone())
            .await
            .expect("enrolment route responds");
        assert_eq!(response.status(), StatusCode::OK);
        let body = response_json(response).await;
        let uri = body["otpauth_uri"].as_str().expect("URI exists").to_owned();
        let codes = body["recovery_codes"]
            .as_array()
            .expect("codes exist")
            .iter()
            .filter_map(serde_json::Value::as_str)
            .map(str::to_owned)
            .collect::<Vec<_>>();
        let seed = stored_seed(&h).await;
        let code = code_at(&seed, now());
        let confirm = h
            .router
            .clone()
            .oneshot(request(
                &h,
                Method::POST,
                "/api/v1/account/totp/confirm",
                Some(serde_json::json!({"code":code})),
            ))
            .with_subscriber(logs.clone())
            .await
            .expect("confirm route responds");
        assert_eq!(confirm.status(), StatusCode::NO_CONTENT);
        let _login = login(
            &h,
            Some(code_at(
                &seed,
                now()
                    .checked_add_seconds(30)
                    .expect("next step is in range"),
            )),
            None,
        )
        .await
        .expect("login with TOTP should succeed");
        let material = format!("{seed} {uri} {codes:?}");
        let serialized_logs = format!(
            "{:?}",
            logs.0
                .lock()
                .unwrap_or_else(std::sync::PoisonError::into_inner)
        );
        assert!(!serialized_logs.contains(&seed));
        assert!(!serialized_logs.contains(&uri));
        for recovery_code in &codes {
            assert!(!serialized_logs.contains(recovery_code));
        }
        let audit: Vec<String> =
            sqlx::query_scalar("SELECT details::text FROM public.audit_log WHERE account_id=$1")
                .bind(*h.account.as_uuid())
                .fetch_all(h.app.admin_pool())
                .await
                .expect("audit details query");
        let serialized_audit = format!("{audit:?}");
        assert!(!serialized_audit.contains(&material));
        assert!(!serialized_audit.contains(&seed));
        assert!(!serialized_audit.contains(&uri));
        assert!(!serialized_audit.contains(&code));
        assert!(
            codes
                .iter()
                .all(|recovery_code| !serialized_audit.contains(recovery_code))
        );
    }

    async fn totp_enabled(h: &Harness) -> bool {
        sqlx::query_scalar("SELECT totp_enabled FROM public.accounts WHERE id=$1")
            .bind(*h.account.as_uuid())
            .fetch_one(h.app.admin_pool())
            .await
            .expect("account setting query")
    }

    async fn set_totp_enabled(h: &Harness, enabled: bool) {
        let mut tx = Tx::begin(&h.app.db, h.account)
            .await
            .expect("account transaction begins");
        sqlx::query("UPDATE public.accounts SET totp_enabled=$2 WHERE id=$1")
            .bind(*h.account.as_uuid())
            .bind(enabled)
            .execute(tx.conn())
            .await
            .expect("TOTP flag update succeeds");
        tx.commit().await.expect("account transaction commits");
    }

    struct TestReady;

    #[async_trait]
    impl ReadyCheck for TestReady {
        async fn check(&self) -> Result<(), ReadyFailure> {
            Ok(())
        }
    }

    impl Harness {
        async fn service_enroll(&self) -> kinevo_mod_identity::app::totp::Enrolment {
            self.service
                .enroll(self.account)
                .await
                .expect("enrolment should succeed")
        }
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

    pub mod tests {
        use super::*;

        #[test]
        fn id_fr_08_rfc6238_vectors_and_window() {
            let seed = "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ";
            for (seconds, expected) in [
                (59_u64, "287082"),
                (1_111_111_109, "081804"),
                (1_111_111_111, "050471"),
                (1_234_567_890, "005924"),
                (2_000_000_000, "279037"),
            ] {
                let instant = Instant::from_unix_millis(
                    i64::try_from(seconds * 1_000).expect("test timestamp fits"),
                )
                .expect("test timestamp is in range");
                assert_eq!(step_for(instant, 30), seconds / 30);
                assert_eq!(
                    accepts(seed, expected, instant, |_| true),
                    Some(seconds / 30)
                );
            }
            let before = Instant::from_unix_millis(89_000).expect("test timestamp is in range");
            let after = Instant::from_unix_millis(119_000).expect("test timestamp is in range");
            assert_eq!(accepts(seed, "287082", before, |_| true), Some(1));
            assert_eq!(accepts(seed, "287082", after, |_| true), None);
            assert_eq!(
                accepts(
                    seed,
                    "287082",
                    Instant::from_unix_millis(59_000).expect("test timestamp is in range"),
                    |_| false,
                ),
                None
            );
        }

        #[test]
        fn recovery_alphabet_is_unambiguous() {
            let symbols = RECOVERY_ALPHABET
                .chars()
                .collect::<std::collections::BTreeSet<_>>();
            assert_eq!(symbols.len(), 32);
            for forbidden in ['0', 'O', '1', 'I', 'L', 'U'] {
                assert!(!symbols.contains(&forbidden));
            }
            assert!(symbols.contains(&'*'));
            assert!(symbols.contains(&'~'));
            let codes = generate_recovery_codes(&SyntheticTokenGen::new(11));
            assert_eq!(codes.len(), 10);
            assert!(
                codes
                    .iter()
                    .all(|code| code.as_str().len() == 11 && code.as_str().as_bytes()[5] == b'-')
            );
            assert_eq!(
                codes
                    .iter()
                    .map(RecoveryCode::as_str)
                    .collect::<std::collections::BTreeSet<_>>()
                    .len(),
                10
            );
            assert_eq!(
                hash(&RecoveryCode::parse("abcde-fghjk").expect("normalized code is valid")).len(),
                32
            );
        }
    }
}
