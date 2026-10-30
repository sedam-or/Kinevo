#![allow(
    clippy::expect_used,
    clippy::panic,
    clippy::unwrap_used,
    reason = "acceptance fixtures should report setup failures directly"
)]

use std::{collections::BTreeMap, io, path::Path, sync::Arc};

use axum::{
    Router,
    body::{Body, to_bytes},
    http::{Request, StatusCode},
    routing::get,
};
use kinevo_app::{
    config::Config,
    runtime::SystemClock,
    serve::{prepare, run_http_server},
};
use kinevo_db::{migrate::MigrationSource, testing::TestApp};
use kinevo_kernel::time::{Clock, FixedClock, Instant};
use sqlx::PgPool;
use tokio::{
    io::{AsyncReadExt, AsyncWriteExt},
    net::{TcpListener, TcpStream},
    sync::{Notify, oneshot},
};
use tower::ServiceExt;

fn config_for(app: &TestApp, auto_migrate: bool, setup: bool) -> Config {
    let mut values = BTreeMap::from([
        (
            "KINEVO_DATABASE_URL".to_owned(),
            app.app_database_url().to_owned(),
        ),
        (
            "KINEVO_MIGRATOR_DATABASE_URL".to_owned(),
            app.migrator_database_url().to_owned(),
        ),
        (
            "KINEVO_SYSTEM_DATABASE_URL".to_owned(),
            app.system_database_url().to_owned(),
        ),
        (
            "KINEVO_SECRET_KEY".to_owned(),
            "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=".to_owned(),
        ),
        (
            "KINEVO_PUBLIC_URL".to_owned(),
            "http://127.0.0.1".to_owned(),
        ),
        ("KINEVO_LISTEN".to_owned(), "127.0.0.1:0".to_owned()),
        ("KINEVO_AUTO_MIGRATE".to_owned(), auto_migrate.to_string()),
    ]);
    if setup {
        values.insert(
            "KINEVO_SETUP_EMAIL".to_owned(),
            "first-admin@example.test".to_owned(),
        );
        values.insert(
            "KINEVO_SETUP_PASSWORD_FILE".to_owned(),
            "/synthetic/setup-password".to_owned(),
        );
    }
    match Config::from_env(&|name| values.get(name).cloned(), &|path: &Path| {
        if setup && path == Path::new("/synthetic/setup-password") {
            Ok("Synthetic-Initial-Password-Only-For-Tests-428!".to_owned())
        } else {
            Err(io::Error::new(
                io::ErrorKind::NotFound,
                "missing synthetic file",
            ))
        }
    }) {
        Ok(config) => config,
        Err(issues) => panic!("synthetic configuration should be valid: {issues:?}"),
    }
}

fn migrations() -> Vec<MigrationSource> {
    vec![
        kinevo_db::migrations_source(),
        kinevo_mod_identity::migrations(),
        kinevo_mod_admin::migrations(),
    ]
}

async fn fresh_app(sources: &[MigrationSource]) -> TestApp {
    TestApp::builder()
        .migrations(sources)
        .build()
        .await
        .expect("shared PostgreSQL should create a unique test database")
}

async fn status(router: Router, path: &str) -> (StatusCode, String) {
    let response = router
        .oneshot(
            Request::builder()
                .uri(path)
                .body(Body::empty())
                .expect("request should be valid"),
        )
        .await
        .expect("router should answer health requests");
    let status = response.status();
    let body = to_bytes(response.into_body(), usize::MAX)
        .await
        .expect("health body should be readable");
    (status, String::from_utf8_lossy(&body).into_owned())
}

async fn database_now(pool: &PgPool) -> Instant {
    let value: String = sqlx::query_scalar(
        "SELECT to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"')",
    )
    .fetch_one(pool)
    .await
    .expect("test database clock should be readable");
    Instant::parse_rfc3339(&value).expect("database should return an RFC3339 instant")
}

#[tokio::test]
async fn ops_02_ready_after_migrate() {
    let app = fresh_app(&[]).await;
    let prepared = prepare(config_for(&app, true, false), Arc::new(SystemClock))
        .await
        .expect("serve preparation should migrate and validate a fresh database");

    let (status, body) = status(prepared.router.clone(), "/readyz").await;
    assert_eq!(status, StatusCode::OK);
    assert!(body.contains("\"status\":\"ok\"") || body.contains("\"status\":\"degraded\""));
    assert!(prepared.migrations_current);
}

#[tokio::test]
async fn ops_02_not_ready_without_migrations() {
    let app = fresh_app(&[]).await;
    let prepared = prepare(config_for(&app, false, false), Arc::new(SystemClock))
        .await
        .expect("serve should start but remain unready while migrations are pending");

    let (status, body) = status(prepared.router.clone(), "/readyz").await;
    assert_eq!(status, StatusCode::SERVICE_UNAVAILABLE);
    assert!(body.contains("\"status\":\"unavailable\""));
}

#[tokio::test]
async fn ops_09_clock_skew_degraded() {
    let sources = migrations();
    let app = fresh_app(&sources).await;
    let now = database_now(app.admin_pool()).await;
    let skewed = now
        .checked_add_seconds(10)
        .expect("test instant should fit");
    let clock: Arc<dyn Clock> = Arc::new(FixedClock::new(skewed));
    let prepared = prepare(config_for(&app, false, false), clock)
        .await
        .expect("migrated database should start with a skewed application clock");

    let (status, body) = status(prepared.router.clone(), "/readyz").await;
    assert_eq!(status, StatusCode::OK);
    assert!(body.contains("\"status\":\"degraded\""));
}

#[tokio::test]
async fn sec_06_startup_refuses_without_rls() {
    let sources = migrations();
    let app = fresh_app(&sources).await;
    sqlx::query("ALTER TABLE public.accounts NO FORCE ROW LEVEL SECURITY")
        .execute(app.admin_pool())
        .await
        .expect("fixture should be able to remove forced RLS");

    let error = prepare(config_for(&app, false, false), Arc::new(SystemClock))
        .await
        .expect_err("startup must reject a role configuration without forced RLS");
    assert_eq!(error.code(), "isolation_check_failed");
}

#[tokio::test]
async fn graceful_shutdown_drains() {
    let entered = Arc::new(Notify::new());
    let release = Arc::new(Notify::new());
    let route_entered = Arc::clone(&entered);
    let route_release = Arc::clone(&release);
    let router = Router::new().route(
        "/inflight",
        get(move || {
            let entered = Arc::clone(&route_entered);
            let release = Arc::clone(&route_release);
            async move {
                entered.notify_one();
                release.notified().await;
                "request finished"
            }
        }),
    );
    let listener = TcpListener::bind("127.0.0.1:0")
        .await
        .expect("test listener should bind");
    let address = listener
        .local_addr()
        .expect("listener should expose its address");
    let (shutdown_tx, shutdown_rx) = oneshot::channel();
    let server = tokio::spawn(run_http_server(listener, router, async move {
        let _ = shutdown_rx.await;
    }));

    let mut request = TcpStream::connect(address)
        .await
        .expect("test client should connect");
    request
        .write_all(b"GET /inflight HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n")
        .await
        .expect("test request should be sent");
    entered.notified().await;
    shutdown_tx.send(()).expect("shutdown should be observed");
    release.notify_one();
    let mut response = Vec::new();
    request
        .read_to_end(&mut response)
        .await
        .expect("in-flight response should finish");
    let response = String::from_utf8_lossy(&response);
    assert!(response.contains("200 OK"));
    assert!(response.contains("request finished"));
    server
        .await
        .expect("server task should not panic")
        .expect("server should drain cleanly");
}

#[tokio::test]
async fn set_06_env_bootstrap_at_startup() {
    let app = fresh_app(&[]).await;
    let prepared = prepare(config_for(&app, true, true), Arc::new(SystemClock))
        .await
        .expect("serve should create the configured first account");
    let completed: bool =
        sqlx::query_scalar("SELECT setup_completed FROM public.instance_settings WHERE id = 1")
            .fetch_one(app.admin_pool())
            .await
            .expect("instance setup status should exist");
    let audit_count: i64 = sqlx::query_scalar(
        "SELECT count(*) FROM public.audit_log WHERE action = 'setup.completed'",
    )
    .fetch_one(app.admin_pool())
    .await
    .expect("setup audit record should be queryable");

    assert!(completed);
    assert_eq!(audit_count, 1);
    let (status, body) = status(prepared.router.clone(), "/readyz").await;
    assert_eq!(status, StatusCode::OK);
    assert!(body.contains("\"status\":\"ok\"") || body.contains("\"status\":\"degraded\""));
}

#[tokio::test]
async fn compiled_admin_setup_routes_are_mounted() {
    let app = fresh_app(&migrations()).await;
    let prepared = prepare(config_for(&app, false, false), Arc::new(SystemClock))
        .await
        .expect("migrated instance should prepare its compiled module routes");

    let (status, body) = status(prepared.router.clone(), "/api/v1/setup/status").await;
    assert_eq!(status, StatusCode::OK);
    assert!(body.contains("setup_completed"));
}
