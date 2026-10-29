#![allow(
    clippy::expect_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    clippy::unwrap_used,
    reason = "acceptance tests use synthetic isolated PostgreSQL fixtures"
)]

mod audit_jobs {
    use std::{
        net::{IpAddr, Ipv4Addr, SocketAddr},
        sync::Arc,
    };

    use async_trait::async_trait;
    use axum::{
        Router,
        body::{Body, to_bytes},
        extract::ConnectInfo,
        http::{Method, Request, StatusCode},
    };
    use kinevo_db::{
        SystemTx, Tx,
        jobs::{self, JobContext, JobKind, WorkerId},
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
        token::{Claims, FamilyId, KeyRing, TokenCodec},
    };
    use kinevo_kernel::{
        id::{AccountId, IdGen},
        policy::{PolicyValue, ResolutionSource, Resolved},
        secret::Secret,
        time::Instant,
        validate::{net::HttpsUrl, tz::Locale},
    };
    use kinevo_mod_admin::{
        MODULE,
        app::{audit::AuditService, jobs_admin::JobsAdminService, policies::PolicyService},
        http::{audit as audit_routes, jobs as jobs_routes},
        jobs as admin_jobs, migrations,
    };
    use kinevo_mod_identity::migrations as identity_migrations;
    use kinevo_ports::{
        fakes::{FakeAccountAccess, FakeInstanceState, FakeModuleSwitches, FakePolicyReader},
        module::ModuleDescriptor,
        platform::{AccountAccessInfo, AccountStatus, InstanceInfo, ResolvedPolicies},
    };
    use serde_json::{Value, json};
    use sqlx::{Row, types::Uuid};
    use tower::ServiceExt;

    const NOW: &str = "2026-11-08T00:00:00Z";
    const ORIGIN: &str = "https://api.example.test";
    static DESCRIPTORS: [&ModuleDescriptor; 2] = [&kinevo_mod_identity::MODULE, &MODULE];

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
        admin_token: String,
        owner_token: String,
        audit: Arc<AuditService>,
        jobs: Arc<JobsAdminService>,
        policy_reader: Arc<dyn kinevo_ports::platform::PolicyReader>,
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
        let policy_service = Arc::new(PolicyService::new(
            app.db.clone(),
            app.ids.clone(),
            app.clock.clone(),
            SubscriberRegistry::default(),
        ));
        let policy_reader = policy_service.reader();
        let audit = Arc::new(AuditService::new(app.db.clone()));
        let jobs = Arc::new(JobsAdminService::new(
            app.db.clone(),
            app.ids.clone(),
            app.clock.clone(),
            Arc::clone(&policy_reader),
        ));
        Harness {
            admin_token: access_token(&app, admin),
            owner_token: access_token(&app, owner),
            app,
            admin,
            owner,
            audit,
            jobs,
            policy_reader,
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
        TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic key ring is valid"),
        )
        .issue(&Claims {
            sub: account,
            sid: FamilyId::from_uuid(app.ids.new_uuid_v7()),
            jti: app.ids.new_uuid_v7(),
            iat: now,
            exp: now.checked_add_seconds(600).expect("token expiry is valid"),
            mfa: false,
        })
    }

    fn router(h: &Harness) -> Router {
        let access = Arc::new(FakeAccountAccess::default());
        for (id, is_admin) in [(h.admin, true), (h.owner, false)] {
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
        let mut values = ResolvedPolicies::default();
        values.insert(
            "ratelimit.api_per_minute",
            Resolved {
                value: PolicyValue::Int(120),
                source: ResolutionSource::Default,
                version_id: None,
                out_of_bounds: false,
            },
        );
        let state = HttpState::new(
            h.app.clock.clone(),
            h.app.ids.clone(),
            h.app.tokens.clone(),
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
            Arc::new(FakePolicyReader::new(values)),
            h.app.clock.clone(),
            TrustedProxies::default(),
        )));
        audit_routes::routes(Arc::clone(&h.audit))
            .merge(jobs_routes::routes(Arc::clone(&h.jobs)))
            .into_router(state)
            .expect("admin audit and jobs routes should validate")
    }

    fn request(method: Method, path: &str, token: Option<&str>) -> Request<Body> {
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
            .body(Body::empty())
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

    async fn audit_row(
        h: &Harness,
        account: AccountId,
        action: &str,
        at: &str,
        details: Value,
    ) -> Uuid {
        let id = h.app.ids.new_uuid_v7();
        sqlx::query(
            "INSERT INTO public.audit_log \
             (id, account_id, actor_id, action, details, occurred_at) \
             VALUES ($1, $2, $2, $3, $4::jsonb, $5::timestamptz)",
        )
        .bind(id)
        .bind(*account.as_uuid())
        .bind(action)
        .bind(details.to_string())
        .bind(at)
        .execute(h.app.admin_pool())
        .await
        .expect("synthetic audit row should insert");
        let stored_details: Value =
            sqlx::query_scalar("SELECT details FROM public.audit_log WHERE id=$1")
                .bind(id)
                .fetch_one(h.app.admin_pool())
                .await
                .expect("synthetic audit details should be readable");
        assert_pii_safe(&stored_details);
        id
    }

    fn assert_pii_safe(details: &Value) {
        let serialized = details.to_string().to_ascii_lowercase();
        for sensitive in [
            "@example.test",
            "password",
            "secret",
            "token",
            "prompt",
            "content",
            "note body",
        ] {
            assert!(
                !serialized.contains(sensitive),
                "audit details must omit {sensitive}"
            );
        }
    }

    async fn outbox_delivery(h: &Harness, state: &str, subscriber: &str) -> Uuid {
        let event_id = h.app.ids.new_uuid_v7();
        sqlx::query(
            "INSERT INTO public.outbox_events (id, kind, payload, created_at, v) \
             VALUES ($1, 'PolicyChanged', '{}'::jsonb, $2::timestamptz, 1)",
        )
        .bind(event_id)
        .bind(NOW)
        .execute(h.app.admin_pool())
        .await
        .expect("synthetic outbox event should insert");
        sqlx::query(
            "INSERT INTO public.outbox_deliveries \
             (event_id, subscriber, state, attempts, next_attempt_at, last_error_code) \
             VALUES ($1, $2, $3, 3, $4::timestamptz, 'service_unavailable')",
        )
        .bind(event_id)
        .bind(subscriber)
        .bind(state)
        .bind(NOW)
        .execute(h.app.admin_pool())
        .await
        .expect("synthetic outbox delivery should insert");
        event_id
    }

    fn handler(h: &Harness, kind: &str) -> Arc<dyn kinevo_db::jobs::JobHandler> {
        admin_jobs(Arc::clone(&h.policy_reader))
            .into_iter()
            .find(|handler| handler.kind().as_str() == kind)
            .expect("admin handler should be registered")
    }

    #[tokio::test]
    async fn adm_audit_admin_filters_and_pages() {
        let h = harness().await;
        let first = audit_row(
            &h,
            h.owner,
            "auth.login",
            "2026-11-07T22:00:00Z",
            json!({"status":"accepted"}),
        )
        .await;
        let second = audit_row(
            &h,
            h.owner,
            "auth.login",
            "2026-11-07T23:00:00Z",
            json!({"count":1}),
        )
        .await;
        let _other = audit_row(
            &h,
            h.admin,
            "auth.login",
            "2026-11-07T23:30:00Z",
            json!({"status":"accepted"}),
        )
        .await;
        let response = router(&h)
            .oneshot(request(
                Method::GET,
                &format!(
                    "/api/v1/admin/audit?action=auth.login&from=2026-11-07T21:00:00Z&to=2026-11-08T00:00:00Z&account_id={}&limit=1",
                    h.owner
                ),
                Some(&h.admin_token),
            ))
            .await
            .expect("audit route responds");
        assert_eq!(response.status(), StatusCode::OK);
        let body = json(response).await;
        assert_eq!(body["items"][0]["id"], second.to_string());
        assert!(body["next_cursor"].is_string());
        let cursor = body["next_cursor"].as_str().expect("next cursor exists");
        let response = router(&h)
            .oneshot(request(
                Method::GET,
                &format!(
                    "/api/v1/admin/audit?action=auth.login&from=2026-11-07T21:00:00Z&to=2026-11-08T00:00:00Z&account_id={}&limit=1&cursor={cursor}",
                    h.owner
                ),
                Some(&h.admin_token),
            ))
            .await
            .expect("audit cursor route responds");
        let next = json(response).await;
        assert_eq!(next["items"][0]["id"], first.to_string());
        assert!(next["next_cursor"].is_null());
    }

    #[tokio::test]
    async fn adm_audit_owner_sees_only_own() {
        let h = harness().await;
        let own = audit_row(&h, h.owner, "auth.login", "2026-11-07T22:00:00Z", json!({})).await;
        let _other = audit_row(&h, h.admin, "auth.login", "2026-11-07T23:00:00Z", json!({})).await;
        let owner_route = router(&h)
            .oneshot(request(
                Method::GET,
                &format!("/api/v1/account/audit?account_id={}", h.admin),
                Some(&h.owner_token),
            ))
            .await
            .expect("owner audit route responds");
        let body = json(owner_route).await;
        assert_eq!(body["items"].as_array().map(Vec::len), Some(1));
        assert_eq!(body["items"][0]["id"], own.to_string());
        let admin_route = router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/admin/audit",
                Some(&h.owner_token),
            ))
            .await
            .expect("admin audit route responds");
        assert_eq!(admin_route.status(), StatusCode::FORBIDDEN);
    }

    #[tokio::test]
    async fn outbox_retry_enqueues_and_resets() {
        let h = harness().await;
        let event_id = outbox_delivery(&h, "dead", "tasks.rebuild").await;
        let response = router(&h)
            .oneshot(request(
                Method::POST,
                &format!("/api/v1/admin/outbox/{event_id}/tasks.rebuild/retry"),
                Some(&h.admin_token),
            ))
            .await
            .expect("retry route responds");
        assert_eq!(response.status(), StatusCode::ACCEPTED);
        let audit = sqlx::query(
            "SELECT action, details FROM public.audit_log WHERE action='outbox.retried'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("retry request should be audited");
        assert_eq!(audit.get::<String, _>("action"), "outbox.retried");
        let details: Value = audit.get("details");
        assert_eq!(details.as_object().map(serde_json::Map::len), Some(2));
        assert!(details.get("entity_id").is_some());
        assert!(details.get("status").is_some());
        assert_pii_safe(&details);
        let queued: Uuid = sqlx::query_scalar(
            "SELECT id FROM public.jobs WHERE kind='outbox.retry' AND state='queued'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("retry job should be queued");
        let mut tx = SystemTx::begin(&h.app.db)
            .await
            .expect("system transaction should start");
        let claimed_jobs = jobs::claim(
            &mut tx,
            &WorkerId::new("test-worker"),
            Instant::parse_rfc3339(NOW).expect("fixed time is valid"),
            120,
            10,
            &[JobKind::from_valid_static("outbox.retry")],
        )
        .await
        .expect("retry job should claim");
        tx.commit().await.expect("claim should commit");
        let retry_job = claimed_jobs
            .into_iter()
            .find(|job| *job.id.as_uuid() == queued)
            .expect("queued retry job should be claimed");
        handler(&h, "outbox.retry")
            .run(
                JobContext::new(
                    Arc::new(h.app.db.clone()),
                    h.app.ids.clone(),
                    h.app.clock.clone(),
                ),
                retry_job,
            )
            .await
            .expect("retry handler should reset dead delivery");
        let row = sqlx::query(
            "SELECT state, attempts, last_error_code FROM public.outbox_deliveries \
             WHERE event_id=$1 AND subscriber='tasks.rebuild'",
        )
        .bind(event_id)
        .fetch_one(h.app.admin_pool())
        .await
        .expect("delivery should remain present");
        assert_eq!(row.get::<String, _>("state"), "pending");
        assert_eq!(row.get::<i32, _>("attempts"), 0);
        assert_eq!(row.get::<Option<String>, _>("last_error_code"), None);
    }

    #[tokio::test]
    async fn outbox_retry_not_dead() {
        let h = harness().await;
        let event_id = outbox_delivery(&h, "pending", "tasks.rebuild").await;
        let response = router(&h)
            .oneshot(request(
                Method::POST,
                &format!("/api/v1/admin/outbox/{event_id}/tasks.rebuild/retry"),
                Some(&h.admin_token),
            ))
            .await
            .expect("retry route responds");
        assert_eq!(response.status(), StatusCode::CONFLICT);
        let body = json(response).await;
        assert_eq!(body["code"], "not_dead");

        let missing = router(&h)
            .oneshot(request(
                Method::POST,
                &format!(
                    "/api/v1/admin/outbox/{}/tasks.rebuild/retry",
                    h.app.ids.new_uuid_v7()
                ),
                Some(&h.admin_token),
            ))
            .await
            .expect("missing delivery route responds");
        assert_eq!(missing.status(), StatusCode::NOT_FOUND);
        assert_eq!(json(missing).await["code"], "not_found");
    }

    #[tokio::test]
    async fn adm_fr_07_prune_audit_by_policy() {
        let h = harness().await;
        let old = audit_row(&h, h.owner, "auth.login", "2025-11-07T23:59:59Z", json!({})).await;
        let recent = audit_row(&h, h.owner, "auth.login", "2025-11-08T00:00:00Z", json!({})).await;
        let context = JobContext::new(
            Arc::new(h.app.db.clone()),
            h.app.ids.clone(),
            h.app.clock.clone(),
        );
        let deleted = admin_jobs::AuditPrune::new(Arc::clone(&h.policy_reader))
            .run_count(&context)
            .await
            .expect("audit prune should run");
        assert_eq!(deleted, 1);
        let ids: Vec<Uuid> =
            sqlx::query_scalar("SELECT id FROM public.audit_log WHERE id=ANY($1::uuid[])")
                .bind(vec![old, recent])
                .fetch_all(h.app.admin_pool())
                .await
                .expect("audit rows should be readable");
        assert_eq!(ids, vec![recent]);
    }

    #[tokio::test]
    async fn adm_fr_07_app_role_cannot_modify_audit() {
        let h = harness().await;
        audit_row(&h, h.owner, "auth.login", NOW, json!({})).await;
        for statement in [
            "UPDATE public.audit_log SET action='changed'",
            "DELETE FROM public.audit_log",
        ] {
            let mut tx = Tx::begin_admin(&h.app.db, h.admin)
                .await
                .expect("app transaction should start");
            let error = sqlx::query(statement)
                .execute(tx.conn())
                .await
                .expect_err("application role must not modify audit rows");
            assert_eq!(
                error
                    .as_database_error()
                    .and_then(sqlx::error::DatabaseError::code)
                    .map(std::borrow::Cow::into_owned),
                Some("42501".to_owned())
            );
            tx.rollback()
                .await
                .expect("failed transaction should roll back");
        }
    }

    #[tokio::test]
    async fn adm_fr_08_prune_idempotency() {
        let h = harness().await;
        for (key, expires) in [
            ("old-key-0001", "2026-11-06T00:00:00Z"),
            ("new-key-0002", "2026-11-08T00:00:00Z"),
        ] {
            sqlx::query(
                "INSERT INTO public.idempotency_keys \
                 (account_id, key, method, path, request_hash, created_at, expires_at) \
                 VALUES ($1,$2,'POST','/api/v1/example',$3,$4::timestamptz,$5::timestamptz)",
            )
            .bind(*h.owner.as_uuid())
            .bind(key)
            .bind([0x33_u8; 32].as_slice())
            .bind(NOW)
            .bind(expires)
            .execute(h.app.admin_pool())
            .await
            .expect("synthetic idempotency key should insert");
        }
        let context = JobContext::new(
            Arc::new(h.app.db.clone()),
            h.app.ids.clone(),
            h.app.clock.clone(),
        );
        let deleted = admin_jobs::IdempotencyPrune::new(Arc::clone(&h.policy_reader))
            .run_count(&context)
            .await
            .expect("idempotency prune should run");
        assert_eq!(deleted, 1);
        let repeated = admin_jobs::IdempotencyPrune::new(Arc::clone(&h.policy_reader))
            .run_count(&context)
            .await
            .expect("idempotency prune should be safe to repeat");
        assert_eq!(repeated, 0);
        let keys: Vec<String> =
            sqlx::query_scalar("SELECT key FROM public.idempotency_keys ORDER BY key")
                .fetch_all(h.app.admin_pool())
                .await
                .expect("remaining idempotency keys should be readable");
        assert_eq!(keys, vec!["new-key-0002"]);
    }

    #[tokio::test]
    async fn sec_ac_10_summary_has_no_content() {
        let h = harness().await;
        for (kind, state, created_at) in [
            ("tasks.run", "queued", "2026-11-07T23:58:00Z"),
            ("tasks.run", "failed", "2026-11-07T23:59:00Z"),
            ("outbox.retry", "dead", "2026-11-07T23:59:30Z"),
        ] {
            sqlx::query(
                "INSERT INTO public.jobs \
                 (id, kind, payload, state, run_at, attempts, max_attempts, created_at, finished_at) \
                 VALUES ($1,$2,'{\"content\":\"synthetic-secret\"}'::jsonb,$3,$4::timestamptz,1,3,$5::timestamptz, \
                         CASE WHEN $3='queued' THEN NULL ELSE $5::timestamptz END)",
            )
            .bind(h.app.ids.new_uuid_v7())
            .bind(kind)
            .bind(state)
            .bind(created_at)
            .bind(created_at)
            .execute(h.app.admin_pool())
            .await
            .expect("synthetic job should insert");
        }
        let _dead = outbox_delivery(&h, "dead", "tasks.rebuild").await;
        let response = router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/admin/jobs/summary",
                Some(&h.admin_token),
            ))
            .await
            .expect("jobs summary route responds");
        assert_eq!(response.status(), StatusCode::OK);
        let body = json(response).await;
        assert_eq!(body["oldest_queued_age_seconds"], 120);
        assert_eq!(body["dead_deliveries"], 1);
        let rows = body["jobs"].as_array().expect("jobs is an array");
        assert!(rows.iter().any(|row| {
            row["kind"] == "tasks.run" && row["state"] == "queued" && row["count"] == 1
        }));
        assert!(rows.iter().any(|row| {
            row["kind"] == "tasks.run" && row["state"] == "failed" && row["count"] == 1
        }));
        assert!(rows.iter().any(|row| {
            row["kind"] == "outbox.retry" && row["state"] == "dead" && row["count"] == 1
        }));
        assert!(body.get("payload").is_none());
        assert!(rows.iter().all(|row| row.get("payload").is_none()));
        assert!(!body.to_string().contains("synthetic-secret"));
    }

    #[tokio::test]
    async fn admin_prunes_finished_jobs_and_delivered_outbox_idempotently() {
        let h = harness().await;
        for (kind, state, finished_at) in [
            ("tasks.run", "succeeded", "2026-10-30T00:00:00Z"),
            ("tasks.run", "failed", "2026-11-07T00:00:00Z"),
        ] {
            sqlx::query(
                "INSERT INTO public.jobs \
                 (id, kind, payload, state, run_at, attempts, max_attempts, created_at, finished_at) \
                 VALUES ($1,$2,'{}'::jsonb,$3,$4::timestamptz,1,3,$4::timestamptz,$5::timestamptz)",
            )
            .bind(h.app.ids.new_uuid_v7())
            .bind(kind)
            .bind(state)
            .bind(finished_at)
            .bind(finished_at)
            .execute(h.app.admin_pool())
            .await
            .expect("synthetic terminal job should insert");
        }
        let old_outbox = outbox_delivery(&h, "delivered", "tasks.rebuild").await;
        sqlx::query(
            "UPDATE public.outbox_events SET created_at='2026-10-30T00:00:00Z' WHERE id=$1",
        )
        .bind(old_outbox)
        .execute(h.app.admin_pool())
        .await
        .expect("old delivered outbox row should update");
        sqlx::query(
            "UPDATE public.outbox_deliveries SET delivered_at='2026-10-30T00:00:00Z' WHERE event_id=$1",
        )
        .bind(old_outbox)
        .execute(h.app.admin_pool())
        .await
        .expect("delivered outbox row timestamp should update");
        let context = JobContext::new(
            Arc::new(h.app.db.clone()),
            h.app.ids.clone(),
            h.app.clock.clone(),
        );
        let pruned_jobs = admin_jobs::JobsPrune
            .run_count(&context)
            .await
            .expect("finished job prune should run");
        assert_eq!(pruned_jobs, 1);
        assert_eq!(
            admin_jobs::JobsPrune
                .run_count(&context)
                .await
                .expect("finished job prune should be idempotent"),
            0
        );
        let pruned_outbox = admin_jobs::OutboxPrune
            .run_count(&context)
            .await
            .expect("outbox prune should run");
        assert_eq!(pruned_outbox, 2);
        assert_eq!(
            admin_jobs::OutboxPrune
                .run_count(&context)
                .await
                .expect("outbox prune should be idempotent"),
            0
        );
        let old_event_exists: bool =
            sqlx::query_scalar("SELECT EXISTS (SELECT 1 FROM public.outbox_events WHERE id=$1)")
                .bind(old_outbox)
                .fetch_one(h.app.admin_pool())
                .await
                .expect("outbox event should remain queryable");
        assert!(!old_event_exists);
    }
}
