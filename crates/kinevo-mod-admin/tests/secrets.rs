#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "admin secret acceptance tests use synthetic isolated database fixtures"
)]

mod secrets {
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
        crypto::{KeyLabel, MasterKey, Sealed, derive_subkey, open},
        id::{AccountId, IdGen},
        policy::{PolicyValue, ResolutionSource, Resolved},
        secret::Secret,
        time::Instant,
        validate::net::HttpsUrl,
        validate::tz::Locale,
    };
    use kinevo_mod_admin::{
        MODULE,
        app::secrets::SecretsService,
        domain::secrets::aad,
        infra::secret_store::{AdminSecretStore, rotate_master_key},
        migrations, router,
    };
    use kinevo_mod_identity::migrations as identity_migrations;
    use kinevo_ports::{
        fakes::{FakeAccountAccess, FakeInstanceState, FakeModuleSwitches, FakePolicyReader},
        module::ModuleDescriptor,
        platform::{
            AccountAccessInfo, AccountStatus, InstanceInfo, ResolvedPolicies, SecretName,
            SecretScope, SecretStore,
        },
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
        store: Arc<AdminSecretStore>,
        service: Arc<SecretsService>,
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

        let master = master_key(1);
        let store = Arc::new(AdminSecretStore::new(
            app.db.clone(),
            derive_subkey(&master, KeyLabel::Secrets, 1),
            1,
            app.ids.clone(),
            app.tokens.clone(),
            app.clock.clone(),
        ));
        let service = Arc::new(SecretsService::new(
            Arc::clone(&store),
            SubscriberRegistry::default(),
        ));
        let admin_token = access_token(&app, admin);
        let owner_token = access_token(&app, owner);
        Harness {
            app,
            admin,
            owner,
            store,
            service,
            admin_token,
            owner_token,
        }
    }

    fn master_key(byte: u8) -> MasterKey {
        let encoded = if byte == 1 {
            "AQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQE="
        } else {
            "AgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgI="
        };
        MasterKey::from_base64(&Secret::new(encoded.to_owned()))
            .expect("synthetic master key is valid")
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

    fn admin_router(h: &Harness) -> axum::Router {
        router(Arc::clone(&h.service))
            .into_router(http_state(&h.app, h.admin, h.owner))
            .expect("admin secrets routes should validate")
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
    async fn adm_fr_06_nonce_differs_per_write() {
        let h = harness().await;
        let name = SecretName::parse("smtp.password").expect("registered name");
        h.store
            .put(
                SecretScope::Instance,
                name,
                Secret::new("same-value-001".to_owned()),
            )
            .await
            .expect("first write should succeed");
        let first = sqlx::query("SELECT nonce, ciphertext FROM public.secrets WHERE account_id IS NULL AND name='smtp.password'")
            .fetch_one(h.app.admin_pool())
            .await
            .expect("first ciphertext should exist");
        h.store
            .put(
                SecretScope::Instance,
                name,
                Secret::new("same-value-001".to_owned()),
            )
            .await
            .expect("second write should succeed");
        let second = sqlx::query("SELECT nonce, ciphertext FROM public.secrets WHERE account_id IS NULL AND name='smtp.password'")
            .fetch_one(h.app.admin_pool())
            .await
            .expect("second ciphertext should exist");
        let first_nonce: Vec<u8> = first.get("nonce");
        let second_nonce: Vec<u8> = second.get("nonce");
        let first_ciphertext: Vec<u8> = first.get("ciphertext");
        let second_ciphertext: Vec<u8> = second.get("ciphertext");
        assert_ne!(first_nonce, second_nonce);
        assert_ne!(first_ciphertext, second_ciphertext);
        let first_nonce: [u8; 24] = first_nonce
            .try_into()
            .expect("nonce has the XChaCha length");
        let opened = open(
            &derive_subkey(&master_key(1), KeyLabel::Secrets, 1),
            &Sealed {
                nonce: first_nonce,
                ciphertext: first_ciphertext,
            },
            &aad(name, SecretScope::Instance),
        )
        .expect("first ciphertext decrypts");
        assert_eq!(opened.expose(), b"same-value-001");
        assert_eq!(
            h.store
                .get(SecretScope::Instance, name)
                .await
                .expect("decrypt works")
                .expect("secret exists")
                .expose(),
            "same-value-001"
        );
    }

    #[tokio::test]
    async fn adm_secret_put_and_list_masked() {
        let h = harness().await;
        let response = admin_router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/secrets/smtp.password",
                &h.admin_token,
                r#"{"value":"synthetic-secret-7631"}"#,
            ))
            .await
            .expect("put route responds");
        let status = response.status();
        let error = if status == StatusCode::NO_CONTENT {
            Value::Null
        } else {
            json(response).await
        };
        assert_eq!(status, StatusCode::NO_CONTENT, "API response: {error}");
        let response = admin_router(&h)
            .oneshot(request(
                Method::GET,
                "/api/v1/admin/secrets",
                &h.admin_token,
                "",
            ))
            .await
            .expect("list route responds");
        assert_eq!(response.status(), StatusCode::OK);
        let body = json(response).await;
        let smtp = body
            .as_array()
            .expect("list is an array")
            .iter()
            .find(|item| item["name"] == "smtp.password")
            .expect("smtp entry exists");
        assert_eq!(smtp["is_set"], true);
        assert_eq!(smtp["last4"], "••••7631");
        assert!(smtp.get("value").is_none());
        assert!(!body.to_string().contains("synthetic-secret-7631"));
    }

    #[tokio::test]
    async fn secret_name_unknown_lists_allowed() {
        let h = harness().await;
        let response = admin_router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/secrets/not.registered",
                &h.admin_token,
                r#"{"value":"synthetic-unknown-secret"}"#,
            ))
            .await
            .expect("unknown secret route responds");
        assert_eq!(response.status(), StatusCode::UNPROCESSABLE_ENTITY);
        let body = json(response).await;
        assert_eq!(body["code"], "secret_name_unknown");
        let allowed = body["details"]["allowed"]
            .as_array()
            .expect("allowed names listed");
        assert!(allowed.iter().any(|name| name == "smtp.password"));
        assert!(!body.to_string().contains("synthetic-unknown-secret"));
    }

    #[tokio::test]
    async fn sec_ac_09_owner_forbidden() {
        let h = harness().await;
        for (method, path, body) in [
            (
                Method::PUT,
                "/api/v1/admin/secrets/smtp.password",
                r#"{"value":"synthetic-owner-secret"}"#,
            ),
            (Method::GET, "/api/v1/admin/secrets", ""),
            (Method::DELETE, "/api/v1/admin/secrets/smtp.password", ""),
        ] {
            let response = admin_router(&h)
                .oneshot(request(method, path, &h.owner_token, body))
                .await
                .expect("owner request responds");
            assert_eq!(response.status(), StatusCode::FORBIDDEN);
        }
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
    async fn sec_ac_11_no_secret_in_audit_or_logs() {
        let h = harness().await;
        let value = "synthetic-private-secret-8347";
        let buffer = TestBuffer(Arc::new(Mutex::new(Vec::new())));
        let subscriber = tracing_subscriber::fmt()
            .with_ansi(false)
            .without_time()
            .with_writer(buffer.clone())
            .finish();
        let response_future = tracing::subscriber::with_default(subscriber, || async {
            admin_router(&h)
                .oneshot(request(
                    Method::PUT,
                    "/api/v1/admin/secrets/smtp.password",
                    &h.admin_token,
                    &format!(r#"{{"value":"{value}"}}"#),
                ))
                .await
                .expect("put request responds")
        });
        let response = response_future.await;
        assert_eq!(response.status(), StatusCode::NO_CONTENT);
        let audit: String = sqlx::query_scalar(
            "SELECT COALESCE(string_agg(details::text, ''), '') FROM public.audit_log WHERE action='secret.set'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("audit details should be readable");
        let logs = String::from_utf8(buffer.0.lock().expect("log buffer lock").clone())
            .expect("captured logs are UTF-8");
        assert!(!audit.contains(value));
        assert!(!logs.contains(value));
        assert!(audit.contains("smtp.password"));
    }

    #[tokio::test]
    async fn aad_binds_name_and_scope() {
        let h = harness().await;
        let smtp = SecretName::parse("smtp.password").expect("registered name");
        h.store
            .put(
                SecretScope::Instance,
                smtp,
                Secret::new("synthetic-aad-secret".to_owned()),
            )
            .await
            .expect("secret write succeeds");
        sqlx::query("UPDATE public.secrets SET name='billing.gateway_key' WHERE account_id IS NULL AND name='smtp.password'")
            .execute(h.app.admin_pool())
            .await
            .expect("synthetic ciphertext relocation should succeed");
        let moved = SecretName::parse("billing.gateway_key").expect("registered name");
        let result = h.store.get(SecretScope::Instance, moved).await;
        assert!(
            result.is_err(),
            "ciphertext moved to a different name must not decrypt"
        );
    }

    #[tokio::test]
    async fn adm_fr_06_rotation_keeps_values_readable() {
        let h = harness().await;
        let account = h.app.new_account();
        insert_account(&h.app, account, "rotation@example.test", false).await;
        for (scope, name, value) in [
            (
                SecretScope::Instance,
                "smtp.password",
                "synthetic-instance-smtp",
            ),
            (
                SecretScope::Instance,
                "billing.gateway_key",
                "synthetic-instance-gateway",
            ),
            (
                SecretScope::Account(account),
                "identity.totp_seed",
                "synthetic-account-totp",
            ),
            (
                SecretScope::Account(account),
                "ai.api_key",
                "synthetic-account-ai",
            ),
        ] {
            h.store
                .put(
                    scope,
                    SecretName::parse(name).expect("registered name"),
                    Secret::new(value.to_owned()),
                )
                .await
                .expect("secret write succeeds");
        }
        let old = master_key(1);
        let new = master_key(2);
        let changed = rotate_master_key(&h.app.db, &old, &new, 2)
            .await
            .expect("rotation should complete");
        assert_eq!(changed, 4);
        let new_store = AdminSecretStore::new(
            h.app.db.clone(),
            derive_subkey(&new, KeyLabel::Secrets, 1),
            2,
            h.app.ids.clone(),
            h.app.tokens.clone(),
            h.app.clock.clone(),
        );
        for (scope, name, expected) in [
            (
                SecretScope::Instance,
                "smtp.password",
                "synthetic-instance-smtp",
            ),
            (
                SecretScope::Instance,
                "billing.gateway_key",
                "synthetic-instance-gateway",
            ),
            (
                SecretScope::Account(account),
                "identity.totp_seed",
                "synthetic-account-totp",
            ),
            (
                SecretScope::Account(account),
                "ai.api_key",
                "synthetic-account-ai",
            ),
        ] {
            let value = new_store
                .get(scope, SecretName::parse(name).expect("registered name"))
                .await
                .expect("rotated secret should decrypt")
                .expect("rotated secret exists");
            assert_eq!(value.expose(), expected);
        }
        let key_ids: Vec<i16> = sqlx::query_scalar(
            "SELECT key_id FROM public.secrets ORDER BY name, account_id NULLS FIRST",
        )
        .fetch_all(h.app.admin_pool())
        .await
        .expect("key ids should be readable");
        assert_eq!(key_ids, [2, 2, 2, 2]);

        let corrupt_account = h.app.new_account();
        insert_account(&h.app, corrupt_account, "rollback@example.test", false).await;
        for (name, value) in [
            ("ai.api_key", "synthetic-first-valid"),
            ("identity.totp_seed", "synthetic-second-invalid"),
        ] {
            new_store
                .put(
                    SecretScope::Account(corrupt_account),
                    SecretName::parse(name).expect("registered name"),
                    Secret::new(value.to_owned()),
                )
                .await
                .expect("fixture secret should insert");
        }
        let original = sqlx::query(
            "SELECT ciphertext, nonce FROM public.secrets WHERE account_id=$1 AND name='identity.totp_seed'",
        )
        .bind(*corrupt_account.as_uuid())
        .fetch_one(h.app.admin_pool())
        .await
        .expect("original ciphertext should be available for the synthetic failure case");
        let original_ciphertext: Vec<u8> = original.get("ciphertext");
        let original_nonce: Vec<u8> = original.get("nonce");
        sqlx::query("UPDATE public.secrets SET ciphertext = decode('00', 'hex') WHERE account_id=$1 AND name='identity.totp_seed'")
            .bind(*corrupt_account.as_uuid())
            .execute(h.app.admin_pool())
            .await
            .expect("fixture ciphertext should corrupt");
        let failed = rotate_master_key(&h.app.db, &new, &old, 3).await;
        assert!(
            failed.is_err(),
            "corrupt ciphertext aborts its account scope"
        );
        let failed_scope: Vec<i16> = sqlx::query_scalar(
            "SELECT key_id FROM public.secrets WHERE account_id=$1 ORDER BY name",
        )
        .bind(*corrupt_account.as_uuid())
        .fetch_all(h.app.admin_pool())
        .await
        .expect("failed scope key ids should be readable");
        assert_eq!(
            failed_scope,
            [2, 2],
            "the account transaction rolls back as a unit"
        );
        let mixed_store = AdminSecretStore::new(
            h.app.db.clone(),
            derive_subkey(&old, KeyLabel::Secrets, 1),
            3,
            h.app.ids.clone(),
            h.app.tokens.clone(),
            h.app.clock.clone(),
        )
        .with_fallback_key(2, derive_subkey(&new, KeyLabel::Secrets, 1));
        assert_eq!(
            mixed_store
                .get(
                    SecretScope::Account(account),
                    SecretName::parse("ai.api_key").expect("registered name"),
                )
                .await
                .expect("partially rotated account secret reads with active key")
                .expect("account secret exists")
                .expose(),
            "synthetic-account-ai"
        );
        assert_eq!(
            mixed_store
                .get(
                    SecretScope::Instance,
                    SecretName::parse("smtp.password").expect("registered name"),
                )
                .await
                .expect("unrotated instance secret reads with fallback key")
                .expect("instance secret exists")
                .expose(),
            "synthetic-instance-smtp"
        );
        sqlx::query("UPDATE public.secrets SET ciphertext=$1, nonce=$2 WHERE account_id=$3 AND name='identity.totp_seed'")
            .bind(original_ciphertext)
            .bind(original_nonce)
            .bind(*corrupt_account.as_uuid())
            .execute(h.app.admin_pool())
            .await
            .expect("synthetic ciphertext should be restored");
        let resumed = rotate_master_key(&h.app.db, &new, &old, 3)
            .await
            .expect("rerunning rotation should finish the remaining scopes");
        assert_eq!(resumed, 4);
        let final_store = AdminSecretStore::new(
            h.app.db.clone(),
            derive_subkey(&old, KeyLabel::Secrets, 1),
            3,
            h.app.ids.clone(),
            h.app.tokens.clone(),
            h.app.clock.clone(),
        );
        for (scope, name, expected) in [
            (
                SecretScope::Account(corrupt_account),
                "ai.api_key",
                "synthetic-first-valid",
            ),
            (
                SecretScope::Account(corrupt_account),
                "identity.totp_seed",
                "synthetic-second-invalid",
            ),
            (
                SecretScope::Instance,
                "billing.gateway_key",
                "synthetic-instance-gateway",
            ),
        ] {
            assert_eq!(
                final_store
                    .get(scope, SecretName::parse(name).expect("registered name"))
                    .await
                    .expect("resumed secret decrypts")
                    .expect("secret exists")
                    .expose(),
                expected
            );
        }
    }

    #[tokio::test]
    async fn delete_audits_name_only() {
        let h = harness().await;
        let missing = admin_router(&h)
            .oneshot(request(
                Method::DELETE,
                "/api/v1/admin/secrets/smtp.password",
                &h.admin_token,
                "",
            ))
            .await
            .expect("delete missing responds");
        assert_eq!(missing.status(), StatusCode::NOT_FOUND);
        let put = admin_router(&h)
            .oneshot(request(
                Method::PUT,
                "/api/v1/admin/secrets/smtp.password",
                &h.admin_token,
                r#"{"value":"synthetic-delete-secret"}"#,
            ))
            .await
            .expect("put responds");
        assert_eq!(put.status(), StatusCode::NO_CONTENT);
        let deleted = admin_router(&h)
            .oneshot(request(
                Method::DELETE,
                "/api/v1/admin/secrets/smtp.password",
                &h.admin_token,
                "",
            ))
            .await
            .expect("delete existing responds");
        assert_eq!(deleted.status(), StatusCode::NO_CONTENT);
        let details: String = sqlx::query_scalar(
            "SELECT COALESCE(string_agg(details::text, ''), '') FROM public.audit_log WHERE action='secret.deleted'",
        )
        .fetch_one(h.app.admin_pool())
        .await
        .expect("delete audit should be readable");
        assert!(details.contains("smtp.password"));
        assert!(!details.contains("synthetic-delete-secret"));
    }
}
