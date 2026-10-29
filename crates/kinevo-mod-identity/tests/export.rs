#![allow(
    clippy::expect_used,
    reason = "state-machine acceptance cases use fixed valid timestamps"
)]

#[path = "support/fixtures.rs"]
#[allow(
    dead_code,
    reason = "shared identity fixtures are consumed by other tests"
)]
mod fixtures;

mod export {
    use std::{
        io::{Cursor, Read},
        path::Path,
        sync::Arc,
    };

    use async_trait::async_trait;
    use kinevo_db::{
        jobs::{ClaimedJob, JobContext, JobHandler},
        migrate::MigrationSource,
        testing::TestApp,
    };
    use kinevo_kernel::{
        errors::{AppError, DetailValue, ErrorCode},
        id::{AccountId, IdGen, JobId},
        policy::{PolicyValue, ResolutionSource, Resolved},
        time::Instant,
    };
    use kinevo_mod_identity::{
        app::jobs::AccountExportBuild,
        domain::export::{ExportEvent, ExportState, transition},
        migrations,
    };
    use kinevo_ports::{
        fakes::FakePolicyReader,
        module::{AccountExporter, ExportSection, check_exporters},
        platform::ResolvedPolicies,
    };
    use serde_json::{Value, json};
    use sqlx::types::Uuid;
    use zip::ZipArchive;

    use crate::fixtures;

    #[test]
    fn sm_21_state_machine_table() {
        let now = Instant::parse_rfc3339("2026-11-08T00:00:00Z").expect("valid test instant");
        let future = now.checked_add_seconds(60).expect("valid future instant");
        let states = [
            ExportState::None,
            ExportState::Queued,
            ExportState::Running,
            ExportState::Ready,
            ExportState::Failed,
            ExportState::Expired,
        ];
        let events = [
            ExportEvent::Request,
            ExportEvent::WorkerPicked,
            ExportEvent::LeaseExpired,
            ExportEvent::Built,
            ExportEvent::BuildFailed,
            ExportEvent::Expire,
        ];

        for state in states {
            for event in events {
                let expires_at = match (state, event) {
                    (ExportState::Running, ExportEvent::Built) => Some(future),
                    (ExportState::Ready, ExportEvent::Expire) => Some(now),
                    _ => None,
                };
                let expected = match (state, event) {
                    (ExportState::None, ExportEvent::Request)
                    | (ExportState::Running, ExportEvent::LeaseExpired) => {
                        Some(ExportState::Queued)
                    }
                    (ExportState::Queued, ExportEvent::WorkerPicked) => Some(ExportState::Running),
                    (ExportState::Running, ExportEvent::Built) => Some(ExportState::Ready),
                    (ExportState::Running, ExportEvent::BuildFailed) => Some(ExportState::Failed),
                    (ExportState::Ready, ExportEvent::Expire) => Some(ExportState::Expired),
                    _ => None,
                };
                let result = transition(state, event, now, expires_at);
                match expected {
                    Some(expected) => {
                        assert_eq!(result.ok(), Some(expected), "{state:?} + {event:?}");
                    }
                    None => assert!(result.is_err(), "{state:?} + {event:?} must be rejected"),
                }
            }
        }
    }

    const NOW: &str = "2026-11-08T00:00:00Z";

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
        values.insert("export.max_age_days", resolved(PolicyValue::Int(7)));
        values.insert("jobs.retry.max_attempts", resolved(PolicyValue::Int(8)));
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

    async fn insert_account(app: &TestApp, account: AccountId, email: &str) {
        let mut tx = kinevo_db::Tx::begin(&app.db, account)
            .await
            .expect("tenant transaction should start");
        fixtures::insert_account(&mut tx, account, email)
            .await
            .expect("synthetic account should insert");
        tx.commit().await.expect("account should commit");
    }

    struct StaticExporter {
        name: &'static str,
        rows: Value,
    }

    const WORKSPACES: &[&str] = &["workspaces"];
    const GOALS: &[&str] = &["goals"];

    #[async_trait]
    impl AccountExporter<kinevo_db::Tx> for StaticExporter {
        fn sections(&self) -> &'static [&'static str] {
            match self.name {
                "workspaces" => WORKSPACES,
                _ => GOALS,
            }
        }

        async fn export(
            &self,
            _tx: &mut kinevo_db::Tx,
            _account: AccountId,
        ) -> Result<Vec<ExportSection>, AppError> {
            Ok(vec![ExportSection {
                name: self.name,
                rows: self.rows.clone(),
            }])
        }
    }

    fn service(
        app: &TestApp,
        exporters: Vec<Arc<dyn AccountExporter<kinevo_db::Tx>>>,
    ) -> Arc<kinevo_mod_identity::app::export::ExportService> {
        Arc::new(
            kinevo_mod_identity::app::export::ExportService::new(
                app.db.clone(),
                app.ids.clone(),
                app.clock.clone(),
                policies(),
                exporters,
            )
            .expect("exporter section names should be unique"),
        )
    }

    fn identity_exporters() -> Vec<Arc<dyn AccountExporter<kinevo_db::Tx>>> {
        kinevo_mod_identity::exporters()
    }

    async fn archive_for(
        app: &TestApp,
        service: &Arc<kinevo_mod_identity::app::export::ExportService>,
        account: AccountId,
    ) -> (Uuid, Vec<u8>) {
        let id = service
            .request_export(account)
            .await
            .expect("export request should queue");
        AccountExportBuild::new(Arc::clone(service))
            .run(
                JobContext::new(Arc::new(app.db.clone()), app.ids.clone(), app.clock.clone()),
                ClaimedJob {
                    id: JobId::from_uuid(app.ids.new_uuid_v7()),
                    account_id: Some(account),
                    kind: "account.export.build".to_owned(),
                    payload: json!({
                        "account_id": account.to_string(),
                        "export_id": id.hyphenated().to_string(),
                    }),
                    attempts: 0,
                    max_attempts: 8,
                },
            )
            .await
            .expect("archive should build");
        let download = service
            .download(account, id)
            .await
            .expect("ready archive should download");
        (id, download.bytes)
    }

    fn entry(bytes: &[u8], name: &str) -> String {
        let mut archive = ZipArchive::new(Cursor::new(bytes)).expect("archive should be valid");
        let mut file = archive.by_name(name).expect("entry should exist");
        let mut text = String::new();
        file.read_to_string(&mut text)
            .expect("entry should be valid UTF-8");
        text
    }

    fn contains_forbidden_key(value: &Value) -> bool {
        match value {
            Value::Object(object) => object.iter().any(|(key, value)| {
                matches!(
                    key.as_str(),
                    "password_hash"
                        | "token_hash"
                        | "ciphertext"
                        | "totp_seed"
                        | "recovery"
                        | "api_key"
                ) || contains_forbidden_key(value)
            }),
            Value::Array(values) => values.iter().any(contains_forbidden_key),
            _ => false,
        }
    }

    #[tokio::test]
    async fn id_fr_12_single_active_export() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "export@example.test").await;
        let service = service(&app, identity_exporters());
        let first = service
            .request_export(account)
            .await
            .expect("first export should queue");

        let error = service
            .request_export(account)
            .await
            .expect_err("a queued export should prevent a second active request");
        assert_eq!(error.code, ErrorCode::ExportInProgress);
        assert_eq!(
            error.details.as_map().get("export_id"),
            Some(&DetailValue::Id(first))
        );
    }

    #[tokio::test]
    async fn mod_11_sections_from_all_exporters() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "sections@example.test").await;
        let first = Arc::new(StaticExporter {
            name: "workspaces",
            rows: json!([{ "id": "synthetic-workspace" }]),
        });
        let second = Arc::new(StaticExporter {
            name: "goals",
            rows: json!([{ "id": "synthetic-goal" }]),
        });
        let mut exporters = identity_exporters();
        exporters.push(first.clone());
        exporters.push(second.clone());

        let service = service(&app, exporters);
        let (_, bytes) = archive_for(&app, &service, account).await;
        let mut archive = ZipArchive::new(Cursor::new(&bytes)).expect("archive should be valid");
        let mut names = (0..archive.len())
            .map(|index| {
                archive
                    .by_index(index)
                    .expect("archive entry should exist")
                    .name()
                    .expect("archive entry name should be valid")
                    .into_owned()
            })
            .collect::<Vec<_>>();
        names.sort();
        assert_eq!(names, ["README.txt", "data.json"]);
        let data: Value =
            serde_json::from_str(&entry(&bytes, "data.json")).expect("export data should be JSON");
        assert_eq!(data.get("schema"), Some(&json!("export_v1")));
        assert!(data.get("account").is_some());
        let workspace_id = data
            .get("workspaces")
            .and_then(Value::as_array)
            .and_then(|rows| rows.first())
            .and_then(|row| row.get("id"));
        let goal_id = data
            .get("goals")
            .and_then(Value::as_array)
            .and_then(|rows| rows.first())
            .and_then(|row| row.get("id"));
        assert_eq!(workspace_id, Some(&json!("synthetic-workspace")));
        assert_eq!(goal_id, Some(&json!("synthetic-goal")));
        assert!(entry(&bytes, "README.txt").contains("export_v1"));

        let duplicate = Arc::new(StaticExporter {
            name: "goals",
            rows: json!([]),
        });
        let claims: [&dyn AccountExporter<kinevo_db::Tx>; 2] =
            [second.as_ref(), duplicate.as_ref()];
        assert_eq!(
            check_exporters(&claims)
                .expect_err("duplicate export sections should fail startup validation")
                .code,
            ErrorCode::InternalError
        );
    }

    #[tokio::test]
    async fn id_fr_12_no_secrets_in_export() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "private-export@example.test").await;
        let service = service(&app, identity_exporters());
        let (_, bytes) = archive_for(&app, &service, account).await;
        let data: Value =
            serde_json::from_str(&entry(&bytes, "data.json")).expect("export data should be JSON");
        let profile = data
            .get("account")
            .and_then(|account| account.get("profile"))
            .expect("account profile should be present");

        assert!(!contains_forbidden_key(&data));
        assert!(profile.get("password_hash").is_none());
        assert_eq!(profile.get("totp_enabled"), Some(&Value::Bool(false)));
    }

    #[tokio::test]
    async fn download_states() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "download@example.test").await;
        let service = service(&app, identity_exporters());
        let id = service
            .request_export(account)
            .await
            .expect("export request should queue");
        assert_eq!(
            service
                .download(account, id)
                .await
                .expect_err("queued export is not downloadable")
                .code,
            ErrorCode::ExportNotReady
        );
        AccountExportBuild::new(Arc::clone(&service))
            .run(
                JobContext::new(Arc::new(app.db.clone()), app.ids.clone(), app.clock.clone()),
                ClaimedJob {
                    id: JobId::from_uuid(app.ids.new_uuid_v7()),
                    account_id: Some(account),
                    kind: "account.export.build".to_owned(),
                    payload: json!({
                        "account_id": account.to_string(),
                        "export_id": id.hyphenated().to_string(),
                    }),
                    attempts: 0,
                    max_attempts: 8,
                },
            )
            .await
            .expect("archive should build");
        let download = service
            .download(account, id)
            .await
            .expect("ready archive should download");
        assert_eq!(download.content_type, "application/zip");
        assert!(download.file_name.starts_with("kinevo-export-"));
        assert!(
            Path::new(&download.file_name)
                .extension()
                .is_some_and(|extension| extension.eq_ignore_ascii_case("zip"))
        );

        app.clock
            .advance_seconds(7 * 86_400)
            .expect("fake clock should advance to expiry");
        assert_eq!(
            service
                .download(account, id)
                .await
                .expect_err("expired archive should reject download")
                .code,
            ErrorCode::ExportExpired
        );
        let bytes: Option<Vec<u8>> =
            sqlx::query_scalar("SELECT bytes FROM public.account_exports WHERE id=$1")
                .bind(id)
                .fetch_one(app.admin_pool())
                .await
                .expect("expired export row should remain");
        assert!(bytes.is_none());
    }

    #[tokio::test]
    async fn deterministic_archive() {
        let app = app().await;
        let account = app.new_account();
        insert_account(&app, account, "deterministic@example.test").await;
        let service = service(&app, identity_exporters());
        let (_, first) = archive_for(&app, &service, account).await;
        let (_, second) = archive_for(&app, &service, account).await;
        assert_eq!(first, second);
    }

    #[tokio::test]
    async fn sec_07_foreign_export_is_404() {
        let app = app().await;
        let owner = app.new_account();
        let foreign = app.new_account();
        insert_account(&app, owner, "owner-export@example.test").await;
        insert_account(&app, foreign, "foreign-export@example.test").await;
        let service = service(&app, identity_exporters());
        let id = service
            .request_export(owner)
            .await
            .expect("owner export should queue");

        assert_eq!(
            service
                .status(foreign, id)
                .await
                .expect_err("another account cannot view export metadata")
                .code,
            ErrorCode::NotFound
        );
        assert_eq!(
            service
                .download(foreign, id)
                .await
                .expect_err("another account cannot download export bytes")
                .code,
            ErrorCode::NotFound
        );
    }
}
