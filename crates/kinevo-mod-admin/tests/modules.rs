#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "module acceptance tests use synthetic isolated database fixtures"
)]

mod modules {
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
        token::{Claims, FamilyId, KeyRing, TokenCodec},
    };
    use kinevo_kernel::{
        id::{AccountId, IdGen},
        policy::{PolicyValue, ResolutionSource, Resolved},
        secret::Secret,
        time::Instant,
        validate::net::HttpsUrl,
        validate::tz::Locale,
    };
    use kinevo_mod_admin::{
        MODULE,
        app::modules::ModuleService,
        http::{instance, modules as module_routes},
        migrations,
    };
    use kinevo_mod_identity::migrations as identity_migrations;
    use kinevo_ports::{
        fakes::{FakeAccountAccess, FakeInstanceState, FakeModuleSwitches, FakePolicyReader},
        module::{ModuleDescriptor, ModuleKind, NavItem, PermissionDef, SettingsPanel},
        platform::{AccountAccessInfo, AccountStatus, InstanceInfo, ResolvedPolicies},
    };
    use serde_json::Value;
    use sqlx::Row;
    use tower::ServiceExt;

    const NOW: &str = "2026-11-08T00:00:00Z";
    const ORIGIN: &str = "https://api.example.test";

    const CANVAS_PERMISSIONS: &[PermissionDef] = &[
        PermissionDef {
            key: "canvas.board.read",
            owner: true,
        },
        PermissionDef {
            key: "canvas.board.manage",
            owner: false,
        },
    ];
    const CANVAS_NAV: &[NavItem] = &[
        NavItem {
            key: "canvas.home",
            label_key: "canvas.nav.home",
            route: "/canvas",
            order: 10,
            required_permission: "canvas.board.read",
        },
        NavItem {
            key: "canvas.manage",
            label_key: "canvas.nav.manage",
            route: "/canvas/manage",
            order: 20,
            required_permission: "canvas.board.manage",
        },
    ];
    const CANVAS_PANELS: &[SettingsPanel] = &[
        SettingsPanel {
            key: "canvas.settings",
            label_key: "canvas.settings.title",
            required_permission: "canvas.board.read",
        },
        SettingsPanel {
            key: "canvas.admin_settings",
            label_key: "canvas.admin_settings.title",
            required_permission: "canvas.board.manage",
        },
    ];
    static CANVAS: ModuleDescriptor = ModuleDescriptor {
        key: "canvas",
        kind: ModuleKind::Toggleable,
        requires: &[],
        permissions: CANVAS_PERMISSIONS,
        policy_keys: &[],
        nav: CANVAS_NAV,
        settings_panels: CANVAS_PANELS,
        message_namespaces: &[],
        jobs: &[],
        job_schedules: &[],
        subscriptions: &[],
    };
    static TASKS: ModuleDescriptor = ModuleDescriptor {
        key: "tasks",
        kind: ModuleKind::Core,
        requires: &[],
        permissions: &[],
        policy_keys: &[],
        nav: &[],
        settings_panels: &[],
        message_namespaces: &[],
        jobs: &[],
        job_schedules: &[],
        subscriptions: &[],
    };
    static GOALS: ModuleDescriptor = ModuleDescriptor {
        key: "goals",
        kind: ModuleKind::Toggleable,
        requires: &[],
        permissions: &[],
        policy_keys: &[],
        nav: &[],
        settings_panels: &[],
        message_namespaces: &[],
        jobs: &[],
        job_schedules: &[],
        subscriptions: &[],
    };
    static AI: ModuleDescriptor = ModuleDescriptor {
        key: "ai",
        kind: ModuleKind::Toggleable,
        requires: &["goals", "tasks"],
        permissions: &[],
        policy_keys: &[],
        nav: &[],
        settings_panels: &[],
        message_namespaces: &[],
        jobs: &[],
        job_schedules: &[],
        subscriptions: &[],
    };
    static DESCRIPTORS: [&ModuleDescriptor; 6] = [
        &kinevo_mod_identity::MODULE,
        &MODULE,
        &CANVAS,
        &TASKS,
        &GOALS,
        &AI,
    ];

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
        service: Arc<ModuleService>,
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
        let policy_service = kinevo_mod_admin::app::policies::PolicyService::new(
            app.db.clone(),
            app.ids.clone(),
            app.clock.clone(),
            SubscriberRegistry::default(),
        );
        let service = Arc::new(ModuleService::new(
            app.db.clone(),
            DESCRIPTORS.to_vec(),
            app.ids.clone(),
            app.clock.clone(),
            policy_service.reader(),
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
        let permissions = Arc::new(
            PermissionTable::from_descriptors(&DESCRIPTORS).expect("permissions are valid"),
        );
        let kinds =
            Arc::new(ModuleKinds::from_descriptors(&DESCRIPTORS).expect("module kinds are valid"));
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

    fn router(h: &Harness) -> axum::Router {
        module_routes::routes(Arc::clone(&h.service))
            .merge(instance::routes(Arc::clone(&h.service)))
            .into_router(http_state(&h.app, h.admin, h.owner))
            .expect("module routes should validate")
    }

    fn request(method: Method, path: &str, token: Option<&str>, body: &str) -> Request<Body> {
        let mut builder = Request::builder()
            .method(method)
            .uri(path)
            .header("Origin", ORIGIN)
            .header("X-Kinevo-Client", "web")
            .header("Content-Type", "application/json");
        if let Some(token) = token {
            builder = builder.header("Authorization", format!("Bearer {token}"));
        }
        let mut request = builder
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

    async fn expect_json(response: axum::response::Response<Body>, expected: StatusCode) -> Value {
        let status = response.status();
        let body = json(response).await;
        assert_eq!(status, expected, "unexpected response body: {body}");
        body
    }

    async fn insert_switch(h: &Harness, key: &str, enabled: bool, version: i64) {
        sqlx::query(
            "INSERT INTO public.instance_modules(module_key, enabled, version, updated_by) \
             VALUES ($1, $2, $3, $4)",
        )
        .bind(key)
        .bind(enabled)
        .bind(version)
        .bind(*h.admin.as_uuid())
        .execute(h.app.admin_pool())
        .await
        .expect("synthetic module row should insert");
    }

    #[tokio::test]
    async fn mod_01_nav_only_for_enabled() {
        let h = harness().await;
        let response = router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/modules",
                Some(&h.owner_token),
                "",
            ))
            .await
            .expect("module list route responds");
        let body = expect_json(response, StatusCode::OK).await;
        let canvas = body["items"]
            .as_array()
            .expect("items is an array")
            .iter()
            .find(|item| item["key"] == "canvas")
            .expect("canvas is listed");
        assert_eq!(canvas["enabled"], false);
        assert_eq!(canvas["nav"], serde_json::json!([]));
        assert_eq!(canvas["settings_panels"], serde_json::json!([]));

        let enabled = router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/modules/canvas",
                Some(&h.admin_token),
                r#"{"enabled":true,"version":0}"#,
            ))
            .await
            .expect("admin switch route responds");
        let _enabled = expect_json(enabled, StatusCode::OK).await;
        let response = router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/modules",
                Some(&h.owner_token),
                "",
            ))
            .await
            .expect("updated module list route responds");
        let body = expect_json(response, StatusCode::OK).await;
        let canvas = body["items"]
            .as_array()
            .expect("items is an array")
            .iter()
            .find(|item| item["key"] == "canvas")
            .expect("canvas is listed");
        assert_eq!(canvas["enabled"], true);
        assert_eq!(canvas["nav"][0]["key"], "canvas.home");
        assert_eq!(canvas["settings_panels"][0]["key"], "canvas.settings");
        assert_eq!(canvas["nav"].as_array().map(Vec::len), Some(1));
        assert_eq!(canvas["settings_panels"].as_array().map(Vec::len), Some(1));

        let admin_response = router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/modules",
                Some(&h.admin_token),
                "",
            ))
            .await
            .expect("admin module list route responds");
        let admin_body = expect_json(admin_response, StatusCode::OK).await;
        let canvas = admin_body["items"]
            .as_array()
            .expect("items is an array")
            .iter()
            .find(|item| item["key"] == "canvas")
            .expect("canvas is listed");
        assert_eq!(canvas["nav"].as_array().map(Vec::len), Some(2));
        assert_eq!(canvas["settings_panels"].as_array().map(Vec::len), Some(2));
    }

    #[tokio::test]
    async fn sec_ac_09_owner_cannot_switch() {
        let h = harness().await;
        let response = router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/modules/ai",
                Some(&h.owner_token),
                r#"{"enabled":true,"version":0}"#,
            ))
            .await
            .expect("switch route responds");
        let body = expect_json(response, StatusCode::FORBIDDEN).await;
        assert_eq!(body["code"], "forbidden");
    }

    #[tokio::test]
    async fn mod_04_switch_audits_and_emits() {
        let h = harness().await;
        insert_switch(&h, "goals", true, 1).await;
        let response = router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/modules/ai",
                Some(&h.admin_token),
                r#"{"enabled":true,"version":0}"#,
            ))
            .await
            .expect("switch route responds");
        let body = expect_json(response, StatusCode::OK).await;
        assert_eq!(body["key"], "ai");
        assert_eq!(body["enabled"], true);
        assert_eq!(body["version"], 1);
        let row = sqlx::query(
            "SELECT enabled, version FROM public.instance_modules WHERE module_key='ai'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("module row should be persisted");
        assert!(row.get::<bool, _>("enabled"));
        assert_eq!(row.get::<i64, _>("version"), 1);

        let repeated = router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/modules/ai",
                Some(&h.admin_token),
                r#"{"enabled":true,"version":1}"#,
            ))
            .await
            .expect("idempotent switch route responds");
        let repeated_body = expect_json(repeated, StatusCode::OK).await;
        assert_eq!(repeated_body["version"], 1);
        let audit_count: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.audit_log WHERE action='module.switched' AND target_id='ai'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("audit row count should be readable");
        assert_eq!(audit_count, 1);
        let outbox_count: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.outbox_events WHERE kind='ModuleSwitched'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("outbox row count should be readable");
        assert_eq!(outbox_count, 1);
    }

    #[tokio::test]
    async fn mod_03_dependency_unmet() {
        let h = harness().await;
        let response = router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/modules/ai",
                Some(&h.admin_token),
                r#"{"enabled":true,"version":0}"#,
            ))
            .await
            .expect("switch route responds");
        let body = expect_json(response, StatusCode::CONFLICT).await;
        assert_eq!(body["code"], "module_dependency_unmet");
        assert_eq!(body["details"]["missing"], serde_json::json!(["goals"]));
    }

    #[tokio::test]
    async fn mod_03_required_by_and_core_locked() {
        let h = harness().await;
        insert_switch(&h, "goals", true, 1).await;
        insert_switch(&h, "ai", true, 1).await;
        let app = router(&h);
        let dependent = app
            .clone()
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/modules/goals",
                Some(&h.admin_token),
                r#"{"enabled":false,"version":1}"#,
            ))
            .await
            .expect("disable route responds");
        let dependent_body = expect_json(dependent, StatusCode::CONFLICT).await;
        assert_eq!(dependent_body["code"], "module_required_by");
        assert_eq!(
            dependent_body["details"]["dependents"],
            serde_json::json!(["ai"])
        );

        let core = app
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/modules/tasks",
                Some(&h.admin_token),
                r#"{"enabled":false,"version":0}"#,
            ))
            .await
            .expect("core module route responds");
        let core_body = expect_json(core, StatusCode::CONFLICT).await;
        assert_eq!(core_body["code"], "module_core_locked");
    }

    #[tokio::test]
    async fn switch_version_conflict() {
        let h = harness().await;
        insert_switch(&h, "canvas", false, 3).await;
        let response = router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/modules/canvas",
                Some(&h.admin_token),
                r#"{"enabled":true,"version":2}"#,
            ))
            .await
            .expect("switch route responds");
        let body = expect_json(response, StatusCode::CONFLICT).await;
        assert_eq!(body["code"], "version_conflict");
        assert_eq!(body["details"]["current_version"], 3);
        assert_eq!(body["details"]["current"]["enabled"], false);
    }

    #[tokio::test]
    async fn mod_04_cache_invalidated_on_switch() {
        let h = harness().await;
        h.service.switches().refresh().await;
        let key = kinevo_ports::module::ModuleKey::parse("canvas")
            .expect("synthetic module key is valid");
        assert!(!h.service.switches().is_enabled(&key));
        let response = router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/modules/canvas",
                Some(&h.admin_token),
                r#"{"enabled":true,"version":0}"#,
            ))
            .await
            .expect("switch route responds");
        let _body = expect_json(response, StatusCode::OK).await;
        assert!(h.service.switches().is_enabled(&key));
    }

    #[tokio::test]
    async fn instance_summary_fields() {
        let h = harness().await;
        let app = router(&h);
        let response = app
            .clone()
            .oneshot(request(
                Method::GET,
                "/api/v1/instance",
                Some(&h.admin_token),
                "",
            ))
            .await
            .expect("instance summary route responds");
        let body = expect_json(response, StatusCode::OK).await;
        assert_eq!(body["version"], 1);
        assert_eq!(body["setup_completed"], false);
        assert_eq!(body["base_url"], Value::Null);
        assert_eq!(body["default_locale"], "en");
        assert_eq!(body["template_id"], Value::Null);
        assert_eq!(body["registration_mode"], "closed");

        let unauthenticated = app
            .oneshot(request(Method::GET, "/api/v1/instance", None, ""))
            .await
            .expect("unauthenticated route responds");
        let _body = expect_json(unauthenticated, StatusCode::UNAUTHORIZED).await;
    }
}
