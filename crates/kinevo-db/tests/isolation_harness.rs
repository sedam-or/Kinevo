#![cfg(feature = "testing")]
#![allow(clippy::expect_used)] // Integration assertions keep setup failures explicit.

use std::process::Command;

use futures::future::BoxFuture;
use kinevo_db::testing::{
    TestApp, TestIds,
    isolation::{IsolationFixture, run_isolation},
};
use kinevo_db::{SystemTx, Tx, require_db};
use sqlx::AssertSqlSafe;

async fn create_probe(app: &TestApp, table: &str, force: bool) {
    let statement =
        format!("CREATE TABLE public.{table} (id uuid PRIMARY KEY, account_id uuid NOT NULL)");
    sqlx::query(AssertSqlSafe(statement.as_str()))
        .execute(app.admin_pool())
        .await
        .expect("probe table should be created");
    sqlx::query(AssertSqlSafe(
        format!("ALTER TABLE public.{table} ENABLE ROW LEVEL SECURITY").as_str(),
    ))
    .execute(app.admin_pool())
    .await
    .expect("probe table should enable RLS");
    if force {
        sqlx::query(AssertSqlSafe(
            format!("ALTER TABLE public.{table} FORCE ROW LEVEL SECURITY").as_str(),
        ))
        .execute(app.admin_pool())
        .await
        .expect("probe table should force RLS");
    }
    let policy = format!(
        "CREATE POLICY tenant_isolation ON public.{table} USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid) WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)"
    );
    sqlx::query(AssertSqlSafe(policy.as_str()))
        .execute(app.admin_pool())
        .await
        .expect("probe table should have the tenant policy");
    sqlx::query(AssertSqlSafe(
        format!("GRANT SELECT, INSERT, UPDATE, DELETE ON public.{table} TO kinevo_app").as_str(),
    ))
    .execute(app.admin_pool())
    .await
    .expect("the app role should have probe DML grants");
}

fn insert_probe<'a>(tx: &'a mut Tx, ids: &'a TestIds) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        sqlx::query("INSERT INTO public.t_probe (id, account_id) VALUES ($1, $2)")
            .bind(ids.row_id)
            .bind(*ids.account_id.as_uuid())
            .execute(tx.conn())
            .await?;
        Ok(())
    })
}

fn insert_unforced<'a>(tx: &'a mut Tx, ids: &'a TestIds) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        sqlx::query("INSERT INTO public.t_unforced (id, account_id) VALUES ($1, $2)")
            .bind(ids.row_id)
            .bind(*ids.account_id.as_uuid())
            .execute(tx.conn())
            .await?;
        Ok(())
    })
}

#[tokio::test]
async fn test_db_is_isolated_per_test() {
    let (first_result, second_result, observer_result) = tokio::join!(
        TestApp::builder().build(),
        TestApp::builder().build(),
        TestApp::builder().build()
    );
    require_db!(first = first_result);
    require_db!(second = second_result);
    require_db!(observer = observer_result);

    assert_ne!(first.database_name(), second.database_name());
    let first_name = first.database_name().to_owned();
    let second_name = second.database_name().to_owned();
    for app in [&first, &second] {
        let exists: bool = sqlx::query_scalar(
            "SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'kinevo_migrator') AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'kinevo_app') AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'kinevo_system')",
        )
        .fetch_one(app.admin_pool())
        .await
        .expect("the three database roles should exist");
        assert!(exists);
        let tables: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p') AND (c.relname = 'accounts' OR EXISTS (SELECT 1 FROM pg_attribute a WHERE a.attrelid = c.oid AND a.attname = 'account_id' AND NOT a.attisdropped))",
        )
        .fetch_one(app.admin_pool())
        .await
        .expect("the empty database should be queryable");
        assert_eq!(tables, 0);
    }

    drop(first);
    drop(second);
    for database_name in [&first_name, &second_name] {
        let exists: bool =
            sqlx::query_scalar("SELECT EXISTS (SELECT 1 FROM pg_database WHERE datname = $1)")
                .bind(database_name)
                .fetch_one(observer.admin_pool())
                .await
                .expect("the shared PostgreSQL server should list test databases");
        assert!(!exists, "test database {database_name} should be dropped");
    }
}

#[tokio::test]
async fn pools_use_the_real_roles() {
    require_db!(app = TestApp::builder().build().await);
    let account_id = app.new_account();
    let mut app_tx = Tx::begin(&app.db, account_id)
        .await
        .expect("app transaction should begin");
    let app_role: String = sqlx::query_scalar("SELECT current_user")
        .fetch_one(app_tx.conn())
        .await
        .expect("app current_user should be queryable");
    assert_eq!(app_role, "kinevo_app");
    app_tx
        .commit()
        .await
        .expect("app transaction should commit");

    let mut system_tx = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    let system_role: String = sqlx::query_scalar("SELECT current_user")
        .fetch_one(system_tx.conn())
        .await
        .expect("system current_user should be queryable");
    assert_eq!(system_role, "kinevo_system");
    system_tx
        .commit()
        .await
        .expect("system transaction should commit");
}

#[tokio::test]
async fn sec_ac_01_probe_table_clean() {
    require_db!(app = TestApp::builder().build().await);
    create_probe(&app, "t_probe", true).await;
    let fixtures = [IsolationFixture {
        table: "t_probe",
        insert: insert_probe,
        touch_sql: "SET id = id",
        update_allowed: true,
        delete_allowed: true,
    }];
    run_isolation(&app, &fixtures).await.assert_clean();
    let remaining_rows: i64 = sqlx::query_scalar("SELECT count(*) FROM public.t_probe")
        .fetch_one(app.admin_pool())
        .await
        .expect("probe rows should remain after rollback-safe delete checks");
    assert_eq!(remaining_rows, 2);
}

#[tokio::test]
async fn tst_06_missing_fixture_is_a_finding() {
    require_db!(app = TestApp::builder().build().await);
    create_probe(&app, "t_missing_fixture", true).await;
    let report = run_isolation(&app, &[]).await;
    assert!(
        report
            .findings
            .iter()
            .any(|finding| finding.contains("t_missing_fixture"))
    );
}

#[tokio::test]
async fn sec_ac_02_unforced_rls_is_a_finding() {
    require_db!(app = TestApp::builder().build().await);
    create_probe(&app, "t_unforced", false).await;
    let fixtures = [IsolationFixture {
        table: "t_unforced",
        insert: insert_unforced,
        touch_sql: "SET id = id",
        update_allowed: true,
        delete_allowed: true,
    }];
    let report = run_isolation(&app, &fixtures).await;
    assert!(
        report
            .findings
            .iter()
            .any(|finding| finding.contains("t_unforced"))
    );
    assert!(
        report
            .findings
            .iter()
            .any(|finding| finding.contains("FORCE"))
    );
}

#[tokio::test]
async fn require_db_fails_in_ci() {
    if std::env::var_os("KINEVO_REQUIRE_DB_CHILD").is_some() {
        require_db!(app = TestApp::builder().build().await);
        return;
    }

    let executable = std::env::current_exe().expect("test binary path should exist");
    let invoke = |mode: &str, ci: bool| {
        let mut command = Command::new(&executable);
        command
            .args(["--exact", "require_db_fails_in_ci", "--nocapture"])
            .env("KINEVO_REQUIRE_DB_CHILD", mode)
            .env(
                "KINEVO_TEST_DATABASE_URL",
                "postgres://synthetic:synthetic@127.0.0.1:1/postgres",
            );
        if ci {
            command.env("CI", "true");
        } else {
            command.env_remove("CI");
        }
        command.output().expect("child test should run")
    };
    let ci = invoke("ci", true);
    assert!(!ci.status.success(), "missing database must fail in CI");
    assert!(String::from_utf8_lossy(&ci.stderr).contains("database is required in CI"));

    let local = invoke("local", false);
    assert!(
        local.status.success(),
        "missing database should skip locally"
    );
    assert!(String::from_utf8_lossy(&local.stderr).contains("skipped: no database"));
}
