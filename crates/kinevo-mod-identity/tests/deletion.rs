#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::too_many_lines,
    reason = "account-deletion acceptance tests use synthetic PostgreSQL fixtures"
)]

#[path = "support/fixtures.rs"]
#[allow(
    dead_code,
    reason = "shared identity fixtures are consumed by other tests"
)]
mod fixtures;

mod deletion {
    use std::{
        net::{IpAddr, Ipv4Addr, SocketAddr},
        sync::Arc,
    };

    use crate::fixtures::ISOLATION_FIXTURES;
    use async_trait::async_trait;
    use axum::{
        body::{Body, to_bytes},
        extract::ConnectInfo,
        http::{Method, Request, StatusCode},
    };
    use kinevo_db::{
        Tx,
        jobs::{ClaimedJob, JobContext, JobHandler},
        migrate::MigrationSource,
        outbox::SubscriberRegistry,
        testing::{TestApp, TestIds},
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
        errors::{AppError, ErrorCode},
        id::{AccountId, FamilyId, IdGen, JobId},
        page::CursorCodec,
        policy::{PolicyValue, ResolutionSource, Resolved},
        secret::Secret,
        time::Instant,
        validate::{net::HttpsUrl, tz::Locale},
    };
    use kinevo_mod_identity::{
        MODULE,
        app::{deletion::DeletionService, jobs::AccountPurge, login::PasswordWork},
        infra::hasher::VerifyOutcome,
        migrations,
    };
    use kinevo_ports::{
        fakes::{FakeAccountAccess, FakeInstanceState, FakeModuleSwitches, FakePolicyReader},
        module::ModuleDescriptor,
        platform::{AccountAccessInfo, AccountStatus, InstanceInfo, ResolvedPolicies},
    };
    use serde_json::json;
    use sqlx::Row;
    use tower::ServiceExt;

    const NOW: &str = "2026-11-08T00:00:00Z";
    const PASSWORD: &str = "synthetic sufficiently long password";

    struct SyntheticPasswords;

    #[async_trait]
    impl PasswordWork for SyntheticPasswords {
        async fn verify(&self, candidate: &Secret<String>, stored_hash: &str) -> VerifyOutcome {
            if stored_hash == "synthetic-password-hash" && candidate.expose() == PASSWORD {
                VerifyOutcome::Ok
            } else {
                VerifyOutcome::Wrong
            }
        }

        async fn verify_dummy(&self, _candidate: &Secret<String>) {}

        async fn hash(&self, _candidate: &Secret<String>) -> Result<String, AppError> {
            Ok("synthetic-password-hash".to_owned())
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

    fn policies(purge_delay_seconds: i64) -> Arc<FakePolicyReader> {
        let mut values = ResolvedPolicies::default();
        values.insert(
            "identity.purge.delay",
            resolved(PolicyValue::Seconds(purge_delay_seconds)),
        );
        values.insert("jobs.retry.max_attempts", resolved(PolicyValue::Int(8)));
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

    async fn insert_account(app: &TestApp, account: AccountId, email: &str, admin: bool) {
        let mut tx = Tx::begin(&app.db, account)
            .await
            .expect("account transaction should start");
        crate::fixtures::insert_account(&mut tx, account, email)
            .await
            .expect("synthetic account should insert");
        tx.commit().await.expect("account should commit");
        if admin {
            sqlx::query("UPDATE public.accounts SET is_instance_admin=true WHERE id=$1")
                .bind(*account.as_uuid())
                .execute(app.admin_pool())
                .await
                .expect("synthetic admin flag should update");
        }
    }

    async fn insert_family(app: &TestApp, account: AccountId) {
        let family = app.ids.new_uuid_v7();
        sqlx::query(
            "INSERT INTO public.refresh_token_families \
             (id, account_id, created_at, last_used_at, absolute_expires_at) \
             VALUES ($1,$2,$3::timestamptz,$3::timestamptz,$4::timestamptz)",
        )
        .bind(family)
        .bind(*account.as_uuid())
        .bind(now().to_rfc3339())
        .bind(
            now()
                .checked_add_seconds(86_400)
                .expect("expiry is valid")
                .to_rfc3339(),
        )
        .execute(app.admin_pool())
        .await
        .expect("synthetic session family should insert");
    }

    fn service(app: &TestApp, purge_delay_seconds: i64) -> DeletionService {
        DeletionService::new(
            app.db.clone(),
            app.ids.clone(),
            Arc::new(SyntheticPasswords),
            policies(purge_delay_seconds),
            app.clock.clone(),
            SubscriberRegistry::default(),
        )
    }

    fn http_state(app: &TestApp, account: AccountId) -> HttpState {
        let access = Arc::new(FakeAccountAccess::default());
        access
            .insert(AccountAccessInfo {
                id: account,
                status: AccountStatus::Active,
                is_instance_admin: false,
                version: 1,
            })
            .expect("synthetic account access should insert");
        let descriptors: [&ModuleDescriptor; 1] = [&MODULE];
        let permissions = Arc::new(
            PermissionTable::from_descriptors(&descriptors)
                .expect("identity permissions should validate"),
        );
        let kinds = Arc::new(
            ModuleKinds::from_descriptors(&descriptors)
                .expect("identity module kinds should validate"),
        );
        let codec = Arc::new(TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic token key should be valid"),
        ));
        let gate = Arc::new(Gate::new(
            codec,
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
                base_url: Some(
                    HttpsUrl::parse("https://identity.example.test").expect("test URL is valid"),
                ),
                default_locale: Locale::En,
            })),
            Arc::new(TestReady),
            Some(HttpsUrl::parse("https://identity.example.test").expect("test URL is valid")),
            Arc::new(CursorCodec::new(Secret::new([0x22; 32]))),
            1_048_576,
            gate,
        )
        .with_origin_config(OriginConfig {
            public_origin: Some("https://identity.example.test".to_owned()),
            android_origin_enabled: true,
        })
        .with_rate_limiter(Arc::new(RateLimiter::new(
            policies(0),
            app.clock.clone(),
            TrustedProxies::default(),
        )))
    }

    fn access_token(app: &TestApp, account: AccountId) -> String {
        TokenCodec::new(
            KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
                .expect("synthetic token key should be valid"),
        )
        .issue(&Claims {
            sub: account,
            sid: FamilyId::from_uuid(app.ids.new_uuid_v7()),
            jti: app.ids.new_uuid_v7(),
            iat: now(),
            exp: now()
                .checked_add_seconds(600)
                .expect("synthetic token expiry should be valid"),
            mfa: false,
        })
    }

    fn purge_job(app: &TestApp, account: AccountId) -> (JobContext, ClaimedJob) {
        (
            JobContext::new(Arc::new(app.db.clone()), app.ids.clone(), app.clock.clone()),
            ClaimedJob {
                id: JobId::from_uuid(app.ids.new_uuid_v7()),
                account_id: Some(account),
                kind: "account.purge".to_owned(),
                payload: json!({ "account_id": account.to_string() }),
                attempts: 0,
                max_attempts: 8,
            },
        )
    }

    #[tokio::test]
    async fn id_fr_11_request_flow() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "owner@example.test", false).await;
        insert_family(&app, account).await;
        let service = Arc::new(service(&app, 3_600));
        let router = kinevo_mod_identity::deletion_router(service)
            .into_router(http_state(&app, account))
            .expect("deletion route should validate");
        let mut request = Request::builder()
            .method(Method::DELETE)
            .uri("/api/v1/account")
            .header(
                "Authorization",
                format!("Bearer {}", access_token(&app, account)),
            )
            .header("Content-Type", "application/json")
            .body(Body::from(
                json!({
                    "password": PASSWORD,
                    "confirm": "delete my account"
                })
                .to_string(),
            ))
            .expect("deletion request should build");
        request.extensions_mut().insert(ConnectInfo(SocketAddr::new(
            IpAddr::V4(Ipv4Addr::new(192, 0, 2, 10)),
            12345,
        )));
        let response = router
            .oneshot(request)
            .await
            .expect("deletion endpoint should respond");
        let status = response.status();
        let body = to_bytes(response.into_body(), 1_048_576)
            .await
            .expect("deletion response body should read");
        assert_eq!(
            status,
            StatusCode::ACCEPTED,
            "{}",
            String::from_utf8_lossy(&body)
        );

        let row = sqlx::query("SELECT status FROM public.accounts WHERE id=$1")
            .bind(*account.as_uuid())
            .fetch_one(app.admin_pool())
            .await
            .expect("account should remain until the purge job runs");
        assert_eq!(row.get::<String, _>("status"), "deleting");
        let family = sqlx::query(
            "SELECT revoked_reason, (extract(epoch FROM revoked_at) * 1000)::bigint AS revoked_ms \
             FROM public.refresh_token_families WHERE account_id=$1",
        )
        .bind(*account.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("session family should remain revoked");
        assert_eq!(family.get::<String, _>("revoked_reason"), "logout");
        assert_eq!(family.get::<i64, _>("revoked_ms"), now().unix_millis());

        let job = sqlx::query(
            "SELECT payload, (extract(epoch FROM run_at) * 1000)::bigint AS run_ms \
             FROM public.jobs WHERE kind='account.purge' AND unique_key=$1",
        )
        .bind(account.to_string())
        .fetch_one(app.admin_pool())
        .await
        .expect("purge job should be queued");
        let payload = job.get::<serde_json::Value, _>("payload");
        let expected_account_id = account.to_string();
        assert_eq!(
            payload
                .get("account_id")
                .and_then(serde_json::Value::as_str),
            Some(expected_account_id.as_str())
        );
        assert_eq!(job.get::<i64, _>("run_ms"), now().unix_millis() + 3_600_000);

        let event_count: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.outbox_events WHERE account_id=$1 \
             AND kind='AccountDeletionRequested'",
        )
        .bind(*account.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("deletion event count should be queryable");
        assert_eq!(event_count, 1);
    }

    #[tokio::test]
    async fn id_fr_10_failures_change_nothing() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "sole-admin@example.test", true).await;
        insert_family(&app, account).await;
        let service = service(&app, 0);

        let wrong_password = service
            .request_deletion(
                account,
                Secret::new("wrong password".to_owned()),
                "delete my account",
            )
            .await
            .expect_err("wrong password should fail");
        assert_eq!(wrong_password.code, ErrorCode::CurrentPasswordIncorrect);

        let wrong_phrase = service
            .request_deletion(
                account,
                Secret::new(PASSWORD.to_owned()),
                "Delete my account",
            )
            .await
            .expect_err("confirmation phrase is case-sensitive");
        assert_eq!(wrong_phrase.code, ErrorCode::ValidationFailed);
        assert_eq!(
            wrong_phrase
                .errors
                .first()
                .expect("confirmation error should be present")
                .path,
            "confirm"
        );

        let last_admin = service
            .request_deletion(
                account,
                Secret::new(PASSWORD.to_owned()),
                "delete my account",
            )
            .await
            .expect_err("the final active administrator cannot self-delete");
        assert_eq!(last_admin.code, ErrorCode::LastAdmin);

        let status: String = sqlx::query_scalar("SELECT status FROM public.accounts WHERE id=$1")
            .bind(*account.as_uuid())
            .fetch_one(app.admin_pool())
            .await
            .expect("account status should remain available");
        assert_eq!(status, "active");
        let revoked: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.refresh_token_families WHERE account_id=$1 AND revoked_at IS NOT NULL",
        )
        .bind(*account.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("revocation count should be queryable");
        assert_eq!(revoked, 0);
        let jobs: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM public.jobs WHERE kind='account.purge' AND unique_key=$1",
        )
        .bind(account.to_string())
        .fetch_one(app.admin_pool())
        .await
        .expect("purge job count should be queryable");
        assert_eq!(jobs, 0);
    }

    #[tokio::test]
    async fn deleting_account_is_locked_out() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "deleting@example.test", false).await;
        let service = service(&app, 0);
        service
            .request_deletion(
                account,
                Secret::new(PASSWORD.to_owned()),
                "delete my account",
            )
            .await
            .expect("first delete request should succeed");

        let error = service
            .request_deletion(
                account,
                Secret::new(PASSWORD.to_owned()),
                "delete my account",
            )
            .await
            .expect_err("a deleting account cannot use account APIs");
        assert_eq!(error.code, ErrorCode::AccountDisabled);
    }

    #[tokio::test]
    async fn id_fr_11_purge_cascades_and_keeps_audit() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "purge@example.test", false).await;
        for fixture in ISOLATION_FIXTURES
            .iter()
            .filter(|fixture| fixture.table != "accounts")
        {
            let ids = TestIds {
                account_id: account,
                row_id: app.ids.new_uuid_v7(),
            };
            let mut tx = Tx::begin(&app.db, account)
                .await
                .expect("tenant transaction should start");
            (fixture.insert)(&mut tx, &ids)
                .await
                .expect("isolation fixture row should insert");
            tx.commit()
                .await
                .expect("fixture transaction should commit");
        }
        sqlx::query("UPDATE public.accounts SET status='deleting' WHERE id=$1")
            .bind(*account.as_uuid())
            .execute(app.admin_pool())
            .await
            .expect("account should be marked deleting for the purge handler");

        let (ctx, job) = purge_job(&app, account);
        AccountPurge
            .run(ctx, job)
            .await
            .expect("purge should remove the tenant account");

        let remaining: i64 = sqlx::query_scalar(
            "SELECT (SELECT count(*) FROM public.accounts WHERE id=$1) + \
                    (SELECT count(*) FROM public.refresh_token_families WHERE account_id=$1) + \
                    (SELECT count(*) FROM public.refresh_tokens WHERE account_id=$1) + \
                    (SELECT count(*) FROM public.recovery_codes WHERE account_id=$1) + \
                    (SELECT count(*) FROM public.password_resets WHERE account_id=$1) + \
                    (SELECT count(*) FROM public.account_exports WHERE account_id=$1) + \
                    (SELECT count(*) FROM public.idempotency_keys WHERE account_id=$1) + \
                    (SELECT count(*) FROM public.outbox_events WHERE account_id=$1)",
        )
        .bind(*account.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("tenant cascade counts should be queryable");
        assert_eq!(remaining, 0);
        let audit = sqlx::query(
            "SELECT account_id, action, details FROM public.audit_log WHERE action='account.deleted'",
        )
        .fetch_one(app.admin_pool())
        .await
        .expect("deletion audit should remain");
        assert!(
            audit
                .get::<Option<sqlx::types::Uuid>, _>("account_id")
                .is_none()
        );
        assert_eq!(audit.get::<String, _>("action"), "account.deleted");
        assert_eq!(audit.get::<serde_json::Value, _>("details"), json!({}));
    }

    #[tokio::test]
    async fn purge_is_idempotent() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "purge-twice@example.test", false).await;
        sqlx::query("UPDATE public.accounts SET status='deleting' WHERE id=$1")
            .bind(*account.as_uuid())
            .execute(app.admin_pool())
            .await
            .expect("account should be marked deleting");

        let (ctx, job) = purge_job(&app, account);
        AccountPurge
            .run(ctx.clone(), job.clone())
            .await
            .expect("first purge should succeed");
        AccountPurge
            .run(ctx, job)
            .await
            .expect("purging an absent account should be a no-op");
    }

    struct TestReady;

    #[async_trait]
    impl ReadyCheck for TestReady {
        async fn check(&self) -> Result<(), ReadyFailure> {
            Ok(())
        }
    }
}
