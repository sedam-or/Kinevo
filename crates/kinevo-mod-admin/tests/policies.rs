#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "policy acceptance tests use synthetic isolated database fixtures"
)]

mod policies {
    use std::{
        io,
        net::{IpAddr, Ipv4Addr, SocketAddr},
        sync::{Arc, Mutex},
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
        token::{Claims, FamilyId, KeyRing, TokenCodec},
    };
    use kinevo_kernel::{
        errors::ErrorCode,
        event::{EventEnumValue, EventEnvelope, EventKind, EventPayload},
        id::{AccountId, IdGen},
        policy::{
            EffectiveDating, PolicySpec, PolicyType, PolicyValue, ResolutionSource, Resolved,
            Scope, check_effective,
        },
        secret::Secret,
        time::Instant,
        validate::net::HttpsUrl,
        validate::tz::Locale,
    };
    use kinevo_mod_admin::{
        MODULE, app::policies::PolicyService, http::policies::routes, migrations,
    };
    use kinevo_mod_identity::migrations as identity_migrations;
    use kinevo_ports::{
        fakes::{FakeAccountAccess, FakeInstanceState, FakeModuleSwitches, FakePolicyReader},
        module::ModuleDescriptor,
        platform::{AccountAccessInfo, AccountStatus, InstanceInfo, ResolvedPolicies},
    };
    use serde_json::Value;
    use sqlx::Row;
    use tower::ServiceExt;
    use tracing_subscriber::fmt::MakeWriter;

    const NOW: &str = "2026-11-08T00:00:00Z";
    const ORIGIN: &str = "https://api.example.test";

    struct TestReady;

    #[async_trait]
    impl ReadyCheck for TestReady {
        async fn check(&self) -> Result<(), ReadyFailure> {
            Ok(())
        }
    }

    struct Harness {
        app: TestApp,
        admin: AccountId,
        owner: AccountId,
        service: Arc<PolicyService>,
        admin_token: String,
        owner_token: String,
    }

    async fn harness() -> Harness {
        let now = Instant::parse_rfc3339(NOW).expect("fixed test instant is valid");
        let sources: [MigrationSource; 3] = [
            kinevo_db::migrations_source(),
            identity_migrations(),
            migrations(),
        ];
        let app = TestApp::builder()
            .migrations(&sources)
            .clock_at(now)
            .build()
            .await
            .expect("disposable PostgreSQL test database should start");
        let admin = app.new_account();
        let owner = app.new_account();
        insert_account(&app, admin, "admin@example.test", true).await;
        insert_account(&app, owner, "owner@example.test", false).await;
        let service = Arc::new(PolicyService::new(
            app.db.clone(),
            app.ids.clone(),
            app.clock.clone(),
            SubscriberRegistry::default(),
        ));
        let admin_token = access_token(&app, admin);
        let owner_token = access_token(&app, owner);
        Harness {
            app,
            admin,
            owner,
            service,
            admin_token,
            owner_token,
        }
    }

    async fn insert_account(app: &TestApp, id: AccountId, email: &str, admin: bool) {
        sqlx::query(
            "INSERT INTO public.accounts \
             (id, email, display_name, password_hash, timezone, is_instance_admin, status) \
             VALUES ($1, $2, 'Synthetic Account', 'synthetic-password-hash', 'UTC', $3, 'active')",
        )
        .bind(*id.as_uuid())
        .bind(email)
        .bind(admin)
        .execute(app.admin_pool())
        .await
        .expect("synthetic account should insert");
    }

    fn access_token(app: &TestApp, account: AccountId) -> String {
        let now = Instant::parse_rfc3339(NOW).expect("fixed test instant is valid");
        let codec = TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic key ring is valid"),
        );
        codec.issue(&Claims {
            sub: account,
            sid: FamilyId::from_uuid(app.ids.new_uuid_v7()),
            jti: app.ids.new_uuid_v7(),
            iat: now,
            exp: now.checked_add_seconds(600).expect("token expiry is valid"),
            mfa: false,
        })
    }

    fn http_state(app: &TestApp, admin: AccountId, owner: AccountId) -> HttpState {
        let access = Arc::new(FakeAccountAccess::default());
        for (id, is_admin) in [(admin, true), (owner, false)] {
            access
                .insert(AccountAccessInfo {
                    id,
                    status: AccountStatus::Active,
                    is_instance_admin: is_admin,
                    version: 1,
                })
                .expect("synthetic account access row should insert");
        }
        let descriptors: [&ModuleDescriptor; 2] = [&kinevo_mod_identity::MODULE, &MODULE];
        let permissions = Arc::new(
            PermissionTable::from_descriptors(&descriptors).expect("permissions are valid"),
        );
        let kinds =
            Arc::new(ModuleKinds::from_descriptors(&descriptors).expect("module kinds are valid"));
        let codec = Arc::new(TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic key ring is valid"),
        ));
        let gate = Arc::new(Gate::new(
            codec,
            Arc::new(AccountCache::new(access)),
            Arc::new(FakeModuleSwitches::default()),
            permissions,
            kinds,
        ));
        let mut policy_values = ResolvedPolicies::default();
        policy_values.insert(
            "ratelimit.api_per_minute",
            Resolved {
                value: PolicyValue::Int(120),
                source: ResolutionSource::Default,
                version_id: None,
                out_of_bounds: false,
            },
        );
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
            Arc::new(FakePolicyReader::new(policy_values)),
            app.clock.clone(),
            TrustedProxies::default(),
        )))
    }

    fn policy_router(h: &Harness) -> axum::Router {
        routes(Arc::clone(&h.service))
            .into_router(http_state(&h.app, h.admin, h.owner))
            .expect("policy routes should validate")
    }

    fn request(method: Method, path: &str, token: &str, body: &str) -> Request<Body> {
        let mut request = Request::builder()
            .method(method)
            .uri(path)
            .header("Authorization", format!("Bearer {token}"))
            .header("Origin", ORIGIN)
            .header("X-Kinevo-Client", "web")
            .header("Content-Type", "application/json")
            .body(Body::from(body.to_owned()))
            .expect("synthetic request should build");
        request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
            IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
            12345,
        )));
        request
    }

    async fn json(response: axum::response::Response<Body>) -> Value {
        let bytes = to_bytes(response.into_body(), 1_048_576)
            .await
            .expect("response body should read");
        serde_json::from_slice(&bytes).expect("response should be JSON")
    }

    #[tokio::test]
    async fn pol_02_list_resolved_with_metadata() {
        let h = harness().await;
        let response = policy_router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/policies?scope=account",
                &h.owner_token,
                "",
            ))
            .await
            .expect("list route responds");
        assert_eq!(response.status(), StatusCode::OK);
        let body = json(response).await;
        let items = body["items"].as_array().expect("items is an array");
        assert_eq!(
            items.len(),
            kinevo_kernel::policy::registry::all_specs().len()
        );
        assert!(items.iter().all(|item| item["source"] == "default"));
        for item in items {
            assert!(item["type"].is_string());
            assert!(item.get("bounds").is_some());
            assert!(item["label_key"].is_string());
            assert!(item["effective_from"].is_null());
            assert!(item["version_id"].is_null());
        }
    }

    #[tokio::test]
    async fn pol_05_write_audits_and_emits() {
        let h = harness().await;
        let response = policy_router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/policies/auth.password.min_length",
                &h.admin_token,
                r#"{"scope":"instance","value":14}"#,
            ))
            .await
            .expect("write route responds");
        assert_eq!(response.status(), StatusCode::OK);
        let result = json(response).await;
        assert!(result["version_id"].is_string());
        assert_eq!(result["effective_from"], NOW);

        let version_count: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.policy_versions \
             WHERE key='auth.password.min_length' AND scope='instance'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("new policy version should exist");
        assert_eq!(version_count, 1);
        let audit = sqlx::query(
            "SELECT action, details FROM public.audit_log WHERE action='policy.changed'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("policy change audit should exist");
        let action: String = audit.get("action");
        let details: Value = audit.get("details");
        assert_eq!(action, "policy.changed");
        assert_eq!(details["key"], "auth.password.min_length");
        assert_eq!(details["scope"], "instance");
        assert_eq!(details["new"], 14);
        assert_eq!(details["old"], 12);
        let outbox_count: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.outbox_events WHERE kind='PolicyChanged'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("policy change outbox event should exist");
        assert_eq!(outbox_count, 1);
        let deliveries: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.outbox_deliveries \
             WHERE subscriber='admin.policy.cache'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("policy cache subscriber delivery should exist");
        assert_eq!(deliveries, 1);
    }

    #[tokio::test]
    async fn adm_fr_04_owner_cannot_write_instance() {
        let h = harness().await;
        let response = policy_router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/policies/auth.password.min_length",
                &h.owner_token,
                r#"{"scope":"instance","value":14}"#,
            ))
            .await
            .expect("owner write route responds");
        assert_eq!(response.status(), StatusCode::FORBIDDEN);
        assert_eq!(json(response).await["code"], "forbidden");
    }

    #[tokio::test]
    async fn pol_03_value_invalid_has_bounds() {
        let h = harness().await;
        let response = policy_router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/policies/auth.password.min_length",
                &h.admin_token,
                r#"{"scope":"instance","value":1000}"#,
            ))
            .await
            .expect("invalid write route responds");
        assert_eq!(response.status(), StatusCode::UNPROCESSABLE_ENTITY);
        let body = json(response).await;
        assert_eq!(body["code"], "policy_value_invalid");
        assert_eq!(body["details"]["bounds"]["min"], 10);
        assert_eq!(body["details"]["bounds"]["max"], 128);
    }

    #[tokio::test]
    async fn pol_03_unknown_key_and_scope() {
        let h = harness().await;
        let unknown = policy_router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/policies/private.unknown",
                &h.admin_token,
                r#"{"scope":"instance","value":1}"#,
            ))
            .await
            .expect("unknown policy route responds");
        assert_eq!(unknown.status(), StatusCode::NOT_FOUND);
        assert_eq!(json(unknown).await["code"], "policy_unknown");

        let wrong_scope = policy_router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/policies/auth.password.min_length",
                &h.admin_token,
                r#"{"scope":"account","value":14}"#,
            ))
            .await
            .expect("wrong scope route responds");
        assert_eq!(wrong_scope.status(), StatusCode::UNPROCESSABLE_ENTITY);
        assert_eq!(json(wrong_scope).await["code"], "policy_scope_invalid");
    }

    #[test]
    fn pol_04_effective_rules() {
        static INSTANCE: &[Scope] = &[Scope::Instance];
        let now = Instant::parse_rfc3339(NOW).expect("fixed test instant is valid");
        let past = now.checked_add_seconds(-1).expect("past instant is valid");
        let later = now.checked_add_seconds(1).expect("future instant is valid");
        let future_only = PolicySpec {
            key: "test.future_only",
            ty: PolicyType::Int { min: 0, max: 10 },
            default: PolicyValue::Int(0),
            scopes: INSTANCE,
            effective_dating: EffectiveDating::FutureOnly,
            snapshot: false,
        };
        assert_eq!(
            check_effective(&future_only, now, Some(past), None)
                .expect_err("past effective time should fail")
                .code,
            ErrorCode::PolicyEffectiveInPast
        );
        let immediate = PolicySpec {
            effective_dating: EffectiveDating::Immediate,
            ..future_only
        };
        assert_eq!(
            check_effective(&immediate, now, Some(past), Some(now))
                .expect_err("earlier version order should fail")
                .code,
            ErrorCode::PolicyEffectiveOrder
        );
        assert_eq!(
            check_effective(&immediate, now, Some(later), Some(now))
                .expect("later version order should pass"),
            later
        );
    }

    #[derive(Clone)]
    struct TestBuffer(Arc<Mutex<Vec<u8>>>);

    impl<'a> MakeWriter<'a> for TestBuffer {
        type Writer = Self;

        fn make_writer(&'a self) -> Self::Writer {
            self.clone()
        }
    }

    impl io::Write for TestBuffer {
        fn write(&mut self, bytes: &[u8]) -> io::Result<usize> {
            self.0
                .lock()
                .expect("log buffer lock")
                .extend_from_slice(bytes);
            Ok(bytes.len())
        }

        fn flush(&mut self) -> io::Result<()> {
            Ok(())
        }
    }

    #[tokio::test]
    async fn pol_08_out_of_bounds_uses_default_logs_once() {
        let h = harness().await;
        sqlx::query(
            "INSERT INTO public.policy_versions \
             (id, scope, account_id, key, value, effective_from, created_by) \
             VALUES ($1, 'instance', NULL, 'auth.password.min_length', '999999'::jsonb, $2::timestamptz, $3)",
        )
        .bind(h.app.ids.new_uuid_v7())
        .bind(NOW)
        .bind(*h.admin.as_uuid())
        .execute(h.app.admin_pool())
        .await
        .expect("out-of-bounds synthetic value should insert");
        let buffer = TestBuffer(Arc::new(Mutex::new(Vec::new())));
        let subscriber = tracing_subscriber::fmt()
            .with_ansi(false)
            .without_time()
            .with_writer(buffer.clone())
            .finish();
        let reader = h.service.reader();
        let account = Some(h.admin);
        let at = Instant::parse_rfc3339(NOW).expect("fixed test instant is valid");
        let dispatch = tracing::Dispatch::new(subscriber);
        let guard = tracing::dispatcher::set_default(&dispatch);
        let first = reader
            .snapshot_values(account, at)
            .await
            .expect("policy snapshot should resolve");
        let later = at.checked_add_seconds(1).expect("later instant is valid");
        reader
            .snapshot_values(account, later)
            .await
            .expect("later policy snapshot should resolve");
        drop(guard);
        assert_eq!(
            first.get(&kinevo_kernel::policy::registry::identity::PASSWORD_MIN_LENGTH),
            Some(12)
        );
        let logs = String::from_utf8(buffer.0.lock().expect("captured logs are readable").clone())
            .expect("captured logs are UTF-8");
        assert_eq!(logs.matches("policy_out_of_bounds").count(), 1);
        assert!(logs.contains("auth.password.min_length"));
        assert!(!logs.contains("999999"));
    }

    #[tokio::test]
    async fn cache_invalidated_on_write() {
        let h = harness().await;
        let before = policy_router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/policies?scope=instance",
                &h.admin_token,
                "",
            ))
            .await
            .expect("initial policy read responds");
        let before = json(before).await;
        let old = before["items"]
            .as_array()
            .expect("items array")
            .iter()
            .find(|item| item["key"] == "auth.password.min_length")
            .expect("password minimum entry exists");
        assert_eq!(old["value"], 12);

        let write = policy_router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/policies/auth.password.min_length",
                &h.admin_token,
                r#"{"scope":"instance","value":15}"#,
            ))
            .await
            .expect("policy write responds");
        assert_eq!(write.status(), StatusCode::OK);

        let after = policy_router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/policies?scope=instance",
                &h.admin_token,
                "",
            ))
            .await
            .expect("policy read after commit responds");
        let after = json(after).await;
        let new = after["items"]
            .as_array()
            .expect("items array")
            .iter()
            .find(|item| item["key"] == "auth.password.min_length")
            .expect("password minimum entry exists");
        assert_eq!(new["value"], 15);

        sqlx::query(
            "INSERT INTO public.policy_versions \
             (id, scope, account_id, key, value, effective_from, created_by) \
             VALUES ($1, 'instance', NULL, 'auth.password.min_length', '16'::jsonb, $2::timestamptz, $3)",
        )
        .bind(h.app.ids.new_uuid_v7())
        .bind(NOW)
        .bind(*h.admin.as_uuid())
        .execute(h.app.admin_pool())
        .await
        .expect("external policy change should insert");
        let cached = policy_router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/policies?scope=instance",
                &h.admin_token,
                "",
            ))
            .await
            .expect("cached policy read responds");
        let cached = json(cached).await;
        assert_eq!(
            cached["items"]
                .as_array()
                .expect("items array")
                .iter()
                .find(|item| item["key"] == "auth.password.min_length")
                .expect("password minimum entry exists")["value"],
            15
        );

        h.service
            .cache_subscriber()
            .handle(&EventEnvelope {
                id: kinevo_kernel::id::EventId::from_uuid(h.app.ids.new_uuid_v7()),
                kind: EventKind::PolicyChanged,
                v: 1,
                account_id: None,
                occurred_at: Instant::parse_rfc3339(NOW).expect("fixed test instant is valid"),
                payload: EventPayload::new().with_enum("scope", EventEnumValue::new("instance")),
            })
            .await
            .expect("policy event should invalidate the cache");
        let refreshed = policy_router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/policies?scope=instance",
                &h.admin_token,
                "",
            ))
            .await
            .expect("refreshed policy read responds");
        let refreshed = json(refreshed).await;
        assert_eq!(
            refreshed["items"]
                .as_array()
                .expect("items array")
                .iter()
                .find(|item| item["key"] == "auth.password.min_length")
                .expect("password minimum entry exists")["value"],
            16
        );
    }
}
