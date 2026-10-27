#![cfg(feature = "testing")]
#![allow(clippy::expect_used)] // Integration assertions keep setup failures explicit.

use kinevo_db::{
    SystemTx, Tx,
    isolation::{IsolationError, verify_isolation},
    require_db,
    testing::TestApp,
};
use kinevo_kernel::id::AccountId;
use sqlx::{AssertSqlSafe, PgPool};

struct Probe {
    app: TestApp,
    table: &'static str,
}

fn account(value: u128) -> AccountId {
    AccountId::from_uuid(sqlx::types::Uuid::from_u128(value))
}

async fn setup_probe(app: TestApp) -> Probe {
    let table = "t_probe";
    for statement in [
        format!("CREATE TABLE public.{table} (id uuid PRIMARY KEY, account_id uuid NOT NULL)"),
        format!("ALTER TABLE public.{table} ENABLE ROW LEVEL SECURITY"),
        format!("ALTER TABLE public.{table} FORCE ROW LEVEL SECURITY"),
        format!(
            "CREATE POLICY tenant_isolation ON public.{table} USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid) WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)"
        ),
        format!("GRANT SELECT, INSERT, UPDATE, DELETE ON public.{table} TO kinevo_app"),
    ] {
        sqlx::query(AssertSqlSafe(statement.as_str()))
            .execute(app.admin_pool())
            .await
            .expect("probe schema should be created");
    }
    Probe { app, table }
}

async fn insert_admin(probe: &Probe, row: u128, account_id: AccountId) {
    let sql = format!(
        "INSERT INTO public.{} (id, account_id) VALUES ($1, $2)",
        probe.table
    );
    sqlx::query(AssertSqlSafe(sql.as_str()))
        .bind(sqlx::types::Uuid::from_u128(row))
        .bind(*account_id.as_uuid())
        .execute(probe.app.admin_pool())
        .await
        .expect("admin should seed probe rows");
}

fn plain_app_pool(probe: &Probe) -> PgPool {
    probe.app.app_pool_for_test().clone()
}

#[tokio::test]
async fn arch_22_tx_scopes_rows_to_account() {
    require_db!(app = TestApp::builder().build().await);
    let probe = setup_probe(app).await;
    let account_a = account(1);
    let account_b = account(2);
    insert_admin(&probe, 11, account_a).await;
    insert_admin(&probe, 12, account_b).await;

    let mut tx_a = Tx::begin(&probe.app.db, account_a)
        .await
        .expect("account A transaction should begin");
    let sql = format!(
        "SELECT account_id FROM public.{} ORDER BY account_id",
        probe.table
    );
    let rows_a = sqlx::query_scalar::<_, sqlx::types::Uuid>(AssertSqlSafe(sql.as_str()))
        .fetch_all(tx_a.conn())
        .await
        .expect("account A should read its probe rows");
    assert_eq!(rows_a, vec![*account_a.as_uuid()]);
    tx_a.commit()
        .await
        .expect("account A transaction should commit");

    let mut tx_b = Tx::begin(&probe.app.db, account_b)
        .await
        .expect("account B transaction should begin");
    let rows_b = sqlx::query_scalar::<_, sqlx::types::Uuid>(AssertSqlSafe(sql.as_str()))
        .fetch_all(tx_b.conn())
        .await
        .expect("account B should read its probe rows");
    assert_eq!(rows_b, vec![*account_b.as_uuid()]);
    tx_b.commit()
        .await
        .expect("account B transaction should commit");
}

#[tokio::test]
async fn arch_22_with_check_blocks_foreign_insert() {
    require_db!(app = TestApp::builder().build().await);
    let probe = setup_probe(app).await;
    let mut tx = Tx::begin(&probe.app.db, account(1))
        .await
        .expect("account transaction should begin");
    let sql = format!(
        "INSERT INTO public.{} (id, account_id) VALUES ($1, $2)",
        probe.table
    );
    let insert = sqlx::query(AssertSqlSafe(sql.as_str()))
        .bind(sqlx::types::Uuid::from_u128(13))
        .bind(*account(2).as_uuid())
        .execute(tx.conn())
        .await;
    assert!(insert.is_err(), "RLS should reject a foreign account row");
}

#[tokio::test]
async fn sec_05_no_context_sees_nothing() {
    require_db!(app = TestApp::builder().build().await);
    let probe = setup_probe(app).await;
    insert_admin(&probe, 21, account(1)).await;
    let pool = plain_app_pool(&probe);
    let sql = format!("SELECT count(*) FROM public.{}", probe.table);
    let count = sqlx::query_scalar::<_, i64>(AssertSqlSafe(sql.as_str()))
        .fetch_one(&pool)
        .await
        .expect("plain app query should succeed under RLS");
    assert_eq!(count, 0);
    pool.close().await;
}

#[tokio::test]
async fn adr_0011_admin_flag_only_in_admin_tx() {
    require_db!(app = TestApp::builder().build().await);
    let probe = setup_probe(app).await;
    let mut normal = Tx::begin(&probe.app.db, account(1))
        .await
        .expect("normal transaction should begin");
    let normal_flag = sqlx::query_scalar::<_, String>(
        "SELECT COALESCE(current_setting('app.instance_admin', true), '')",
    )
    .fetch_one(normal.conn())
    .await
    .expect("normal transaction should read its flag");
    assert_eq!(normal_flag, "");
    normal
        .commit()
        .await
        .expect("normal transaction should commit");

    let mut admin = Tx::begin_admin(&probe.app.db, account(1))
        .await
        .expect("admin transaction should begin");
    let admin_flag = sqlx::query_scalar::<_, String>(
        "SELECT COALESCE(current_setting('app.instance_admin', true), '')",
    )
    .fetch_one(admin.conn())
    .await
    .expect("admin transaction should read its flag");
    assert_eq!(admin_flag, "on");
    admin
        .commit()
        .await
        .expect("admin transaction should commit");
}

#[tokio::test]
async fn drop_rolls_back() {
    require_db!(app = TestApp::builder().build().await);
    let probe = setup_probe(app).await;
    let id = sqlx::types::Uuid::from_u128(31);
    let mut tx = Tx::begin(&probe.app.db, account(1))
        .await
        .expect("transaction should begin");
    let sql = format!(
        "INSERT INTO public.{} (id, account_id) VALUES ($1, $2)",
        probe.table
    );
    sqlx::query(AssertSqlSafe(sql.as_str()))
        .bind(id)
        .bind(*account(1).as_uuid())
        .execute(tx.conn())
        .await
        .expect("transaction should insert its own row");
    drop(tx);

    let sql = format!("SELECT count(*) FROM public.{} WHERE id = $1", probe.table);
    let count = sqlx::query_scalar::<_, i64>(AssertSqlSafe(sql.as_str()))
        .bind(id)
        .fetch_one(probe.app.admin_pool())
        .await
        .expect("admin should read the probe row count");
    assert_eq!(count, 0);
}

#[tokio::test]
async fn sec_06_verify_isolation_lists_all_findings() {
    require_db!(app = TestApp::builder().build().await);
    let probe = setup_probe(app).await;
    let suffix = 1;
    let no_force = format!("t_probe_no_force_{suffix}");
    let no_policy = format!("t_probe_no_policy_{suffix}");
    for statement in [
        format!("CREATE TABLE public.{no_force} (id uuid PRIMARY KEY, account_id uuid NOT NULL)"),
        format!("ALTER TABLE public.{no_force} ENABLE ROW LEVEL SECURITY"),
        format!(
            "CREATE POLICY tenant_isolation ON public.{no_force} USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid) WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)"
        ),
        format!("CREATE TABLE public.{no_policy} (id uuid PRIMARY KEY, account_id uuid NOT NULL)"),
        format!("ALTER TABLE public.{no_policy} ENABLE ROW LEVEL SECURITY"),
        format!("ALTER TABLE public.{no_policy} FORCE ROW LEVEL SECURITY"),
        format!(
            "GRANT SELECT, INSERT, UPDATE, DELETE ON public.{no_force}, public.{no_policy} TO kinevo_app"
        ),
    ] {
        sqlx::query(AssertSqlSafe(statement.as_str()))
            .execute(probe.app.admin_pool())
            .await
            .expect("invalid probe tables should be prepared");
    }
    let pool = plain_app_pool(&probe);
    let result = verify_isolation(&pool).await;
    assert!(matches!(result, Err(IsolationError::Findings(_))));
    let error = result.expect_err("invalid tables should fail verification");
    let findings = format!("{error:?}");
    assert!(findings.contains(&no_force));
    assert!(findings.contains(&no_policy));
    pool.close().await;
}

#[tokio::test]
async fn sec_06_verify_isolation_ok() {
    require_db!(app = TestApp::builder().build().await);
    let probe = setup_probe(app).await;
    let pool = plain_app_pool(&probe);
    verify_isolation(&pool)
        .await
        .expect("properly configured probe table should pass");
    pool.close().await;
}

#[tokio::test]
async fn adr_0011_system_tx_has_no_account_context() {
    require_db!(app = TestApp::builder().build().await);
    let probe = setup_probe(app).await;
    let mut tx = SystemTx::begin(&probe.app.db)
        .await
        .expect("system transaction should begin");
    let setting =
        sqlx::query_scalar::<_, Option<String>>("SELECT current_setting('app.account_id', true)")
            .fetch_one(tx.conn())
            .await
            .expect("system transaction should read its context");
    assert!(setting.is_none() || setting.as_deref() == Some(""));
    tx.commit().await.expect("system transaction should commit");
}
