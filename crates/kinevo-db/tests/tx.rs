use std::sync::atomic::{AtomicU64, Ordering};
use std::time::Duration;

use kinevo_db::{
    Db, DbConfig, SystemTx, Tx,
    isolation::{IsolationError, verify_isolation},
    pool::Sleeper,
};
use kinevo_kernel::{id::AccountId, secret::Secret};
use sqlx::{AssertSqlSafe, Connection, PgConnection, PgPool, postgres::PgPoolOptions};

static NEXT_PROBE: AtomicU64 = AtomicU64::new(1);

struct ImmediateSleeper;

impl Sleeper for ImmediateSleeper {
    fn sleep<'a>(
        &'a self,
        _delay: Duration,
    ) -> std::pin::Pin<Box<dyn std::future::Future<Output = ()> + Send + 'a>> {
        Box::pin(async {})
    }
}

struct Probe {
    _lock: PgConnection,
    admin_pool: PgPool,
    db: Db,
    app_url: String,
    table: String,
}

impl Probe {
    async fn cleanup(self) {
        let sql = format!("DROP TABLE IF EXISTS public.{}", self.table);
        let result = sqlx::query(AssertSqlSafe(sql.as_str()))
            .execute(&self.admin_pool)
            .await;
        assert!(result.is_ok(), "probe table should be removed");
        self.admin_pool.close().await;
    }
}

fn database_url() -> Option<String> {
    std::env::var("KINEVO_TEST_ADMIN_DATABASE_URL")
        .or_else(|_| std::env::var("KINEVO_TEST_DATABASE_URL"))
        .ok()
}

fn role_url(admin_url: &str, role: &str) -> Option<String> {
    let (scheme, remainder) = admin_url.split_once("://")?;
    let (_, host_and_database) = remainder.rsplit_once('@')?;
    Some(format!(
        "{scheme}://{role}:synthetic-test-password@{host_and_database}"
    ))
}

fn account(value: u128) -> AccountId {
    AccountId::from_uuid(sqlx::types::Uuid::from_u128(value))
}

async fn setup_probe() -> Option<Probe> {
    let Some(admin_url) = database_url() else {
        eprintln!("skipped: no KINEVO_TEST_ADMIN_DATABASE_URL or KINEVO_TEST_DATABASE_URL");
        assert!(
            !std::env::var("CI").is_ok_and(|value| value == "true"),
            "database URL is required in CI"
        );
        return None;
    };

    let lock_result = PgConnection::connect(&admin_url).await;
    assert!(lock_result.is_ok(), "test database must accept connections");
    let Ok(mut lock) = lock_result else {
        return None;
    };
    let lock_result = sqlx::query(
        "SELECT pg_advisory_lock(hashtext('kinevo-db'), hashtext('t010-transaction-tests'))",
    )
    .execute(&mut lock)
    .await;
    assert!(lock_result.is_ok(), "database test lock should be acquired");
    if lock_result.is_err() {
        return None;
    }

    let admin_pool_result = PgPoolOptions::new()
        .max_connections(1)
        .connect(&admin_url)
        .await;
    assert!(
        admin_pool_result.is_ok(),
        "admin database pool should connect"
    );
    let Ok(admin_pool) = admin_pool_result else {
        return None;
    };

    let (app_url, system_url, table) = setup_probe_schema(&admin_url, &admin_pool).await?;
    let config = DbConfig {
        app_url: Secret::new(app_url.clone()),
        system_url: Secret::new(system_url),
        pool_max: 2,
        retries: 0,
        backoff: Duration::from_millis(1),
    };
    let db_result = Db::connect(&config, &ImmediateSleeper).await;
    assert!(db_result.is_ok(), "application pools should connect");
    let Ok(db) = db_result else {
        return None;
    };

    Some(Probe {
        _lock: lock,
        admin_pool,
        db,
        app_url,
        table,
    })
}

async fn setup_probe_schema(
    admin_url: &str,
    admin_pool: &PgPool,
) -> Option<(String, String, String)> {
    let migrator_url = role_url(admin_url, "kinevo_migrator");
    let app_url = role_url(admin_url, "kinevo_app");
    let system_url = role_url(admin_url, "kinevo_system");
    assert!(
        migrator_url.is_some() && app_url.is_some() && system_url.is_some(),
        "test URL should include scheme and authority"
    );
    let (Some(migrator_url), Some(app_url), Some(system_url)) = (migrator_url, app_url, system_url)
    else {
        return None;
    };

    let credentials = kinevo_db::bootstrap::RoleCredentials::from_urls(
        &Secret::new(migrator_url),
        &Secret::new(app_url.clone()),
        &Secret::new(system_url.clone()),
    );
    assert!(credentials.is_ok(), "synthetic role URLs should parse");
    let Ok((credentials, database_name)) = credentials else {
        return None;
    };
    let bootstrap = kinevo_db::bootstrap::bootstrap_roles(
        &Secret::new(admin_url.to_owned()),
        &credentials,
        &database_name,
    )
    .await;
    assert!(
        bootstrap.is_ok(),
        "test database roles should be bootstrapped"
    );

    let suffix = NEXT_PROBE.fetch_add(1, Ordering::Relaxed);
    let table = format!("t_probe_{suffix}");
    for statement in [
        format!("CREATE TABLE public.{table} (id uuid PRIMARY KEY, account_id uuid NOT NULL)"),
        format!("ALTER TABLE public.{table} ENABLE ROW LEVEL SECURITY"),
        format!("ALTER TABLE public.{table} FORCE ROW LEVEL SECURITY"),
        format!(
            "CREATE POLICY tenant_isolation ON public.{table} USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid) WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)"
        ),
        format!("GRANT SELECT, INSERT, UPDATE, DELETE ON public.{table} TO kinevo_app"),
    ] {
        let result = sqlx::query(AssertSqlSafe(statement.as_str()))
            .execute(admin_pool)
            .await;
        assert!(
            result.is_ok(),
            "probe schema should be created: {}",
            result
                .as_ref()
                .err()
                .map_or_else(String::new, ToString::to_string)
        );
        if result.is_err() {
            return None;
        }
    }

    Some((app_url, system_url, table))
}

async fn insert_admin(probe: &Probe, row: u128, account_id: AccountId) {
    let sql = format!(
        "INSERT INTO public.{} (id, account_id) VALUES ($1, $2)",
        probe.table
    );
    let result = sqlx::query(AssertSqlSafe(sql.as_str()))
        .bind(sqlx::types::Uuid::from_u128(row))
        .bind(*account_id.as_uuid())
        .execute(&probe.admin_pool)
        .await;
    assert!(result.is_ok(), "admin should seed probe rows");
}

async fn plain_app_pool(probe: &Probe) -> Option<PgPool> {
    let result = PgPoolOptions::new()
        .max_connections(1)
        .connect(&probe.app_url)
        .await;
    assert!(result.is_ok(), "plain app pool should connect");
    result.ok()
}

#[tokio::test]
async fn arch_22_tx_scopes_rows_to_account() {
    let Some(probe) = setup_probe().await else {
        return;
    };
    let account_a = account(1);
    let account_b = account(2);
    insert_admin(&probe, 11, account_a).await;
    insert_admin(&probe, 12, account_b).await;

    let tx_a_result = Tx::begin(&probe.db, account_a).await;
    assert!(tx_a_result.is_ok(), "account A transaction should begin");
    let Ok(mut tx_a) = tx_a_result else {
        return;
    };
    let sql = format!(
        "SELECT account_id FROM public.{} ORDER BY account_id",
        probe.table
    );
    let rows_a = sqlx::query_scalar::<_, sqlx::types::Uuid>(AssertSqlSafe(sql.as_str()))
        .fetch_all(tx_a.conn())
        .await;
    assert!(rows_a.is_ok(), "account A should read its probe rows");
    if let Ok(rows) = rows_a {
        assert_eq!(rows, vec![*account_a.as_uuid()]);
    }
    let _ = tx_a.commit().await;

    let tx_b_result = Tx::begin(&probe.db, account_b).await;
    assert!(tx_b_result.is_ok(), "account B transaction should begin");
    let Ok(mut tx_b) = tx_b_result else {
        return;
    };
    let rows_b = sqlx::query_scalar::<_, sqlx::types::Uuid>(AssertSqlSafe(sql.as_str()))
        .fetch_all(tx_b.conn())
        .await;
    assert!(rows_b.is_ok(), "account B should read its probe rows");
    if let Ok(rows) = rows_b {
        assert_eq!(rows, vec![*account_b.as_uuid()]);
    }
    let _ = tx_b.commit().await;
    probe.cleanup().await;
}

#[tokio::test]
async fn arch_22_with_check_blocks_foreign_insert() {
    let Some(probe) = setup_probe().await else {
        return;
    };
    let tx_result = Tx::begin(&probe.db, account(1)).await;
    assert!(tx_result.is_ok(), "account transaction should begin");
    let Ok(mut tx) = tx_result else {
        return;
    };
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
    drop(tx);
    probe.cleanup().await;
}

#[tokio::test]
async fn sec_05_no_context_sees_nothing() {
    let Some(probe) = setup_probe().await else {
        return;
    };
    insert_admin(&probe, 21, account(1)).await;
    let Some(pool) = plain_app_pool(&probe).await else {
        return;
    };
    let sql = format!("SELECT count(*) FROM public.{}", probe.table);
    let count = sqlx::query_scalar::<_, i64>(AssertSqlSafe(sql.as_str()))
        .fetch_one(&pool)
        .await;
    assert!(count.is_ok(), "plain app query should succeed under RLS");
    if let Ok(count) = count {
        assert_eq!(count, 0);
    }
    pool.close().await;
    probe.cleanup().await;
}

#[tokio::test]
async fn adr_0011_admin_flag_only_in_admin_tx() {
    let Some(probe) = setup_probe().await else {
        return;
    };
    let normal_result = Tx::begin(&probe.db, account(1)).await;
    assert!(normal_result.is_ok(), "normal transaction should begin");
    let Ok(mut normal) = normal_result else {
        return;
    };
    let normal_flag = sqlx::query_scalar::<_, String>(
        "SELECT COALESCE(current_setting('app.instance_admin', true), '')",
    )
    .fetch_one(normal.conn())
    .await;
    assert!(
        normal_flag.is_ok(),
        "normal transaction should read its flag"
    );
    if let Ok(flag) = normal_flag {
        assert_eq!(flag, "");
    }
    let _ = normal.commit().await;

    let admin_result = Tx::begin_admin(&probe.db, account(1)).await;
    assert!(admin_result.is_ok(), "admin transaction should begin");
    let Ok(mut admin) = admin_result else {
        return;
    };
    let admin_flag = sqlx::query_scalar::<_, String>(
        "SELECT COALESCE(current_setting('app.instance_admin', true), '')",
    )
    .fetch_one(admin.conn())
    .await;
    assert!(admin_flag.is_ok(), "admin transaction should read its flag");
    if let Ok(flag) = admin_flag {
        assert_eq!(flag, "on");
    }
    let _ = admin.commit().await;
    probe.cleanup().await;
}

#[tokio::test]
async fn drop_rolls_back() {
    let Some(probe) = setup_probe().await else {
        return;
    };
    let id = 31;
    let tx_result = Tx::begin(&probe.db, account(1)).await;
    assert!(tx_result.is_ok(), "transaction should begin");
    let Ok(mut tx) = tx_result else {
        return;
    };
    let sql = format!(
        "INSERT INTO public.{} (id, account_id) VALUES ($1, $2)",
        probe.table
    );
    let insert = sqlx::query(AssertSqlSafe(sql.as_str()))
        .bind(sqlx::types::Uuid::from_u128(id))
        .bind(*account(1).as_uuid())
        .execute(tx.conn())
        .await;
    assert!(insert.is_ok(), "transaction should insert its own row");
    drop(tx);

    let sql = format!("SELECT count(*) FROM public.{} WHERE id = $1", probe.table);
    let count = sqlx::query_scalar::<_, i64>(AssertSqlSafe(sql.as_str()))
        .bind(sqlx::types::Uuid::from_u128(id))
        .fetch_one(&probe.admin_pool)
        .await;
    assert!(count.is_ok(), "admin should read the probe row count");
    if let Ok(count) = count {
        assert_eq!(count, 0);
    }
    probe.cleanup().await;
}

#[tokio::test]
async fn sec_06_verify_isolation_lists_all_findings() {
    let Some(probe) = setup_probe().await else {
        return;
    };
    let suffix = NEXT_PROBE.fetch_add(1, Ordering::Relaxed);
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
        let result = sqlx::query(AssertSqlSafe(statement.as_str()))
            .execute(&probe.admin_pool)
            .await;
        assert!(result.is_ok(), "invalid probe tables should be prepared");
    }
    let Some(pool) = plain_app_pool(&probe).await else {
        return;
    };
    let result = verify_isolation(&pool).await;
    assert!(
        matches!(result, Err(IsolationError::Findings(_))),
        "both invalid tables should fail isolation verification: {result:?}"
    );
    if let Err(error) = result {
        let findings = format!("{error:?}");
        assert!(findings.contains(&no_force));
        assert!(findings.contains(&no_policy));
    }
    pool.close().await;
    for table in [&no_force, &no_policy] {
        let statement = format!("DROP TABLE IF EXISTS public.{table}");
        let result = sqlx::query(AssertSqlSafe(statement.as_str()))
            .execute(&probe.admin_pool)
            .await;
        assert!(result.is_ok(), "invalid probe table should be removed");
    }
    probe.cleanup().await;
}

#[tokio::test]
async fn sec_06_verify_isolation_ok() {
    let Some(probe) = setup_probe().await else {
        return;
    };
    let Some(pool) = plain_app_pool(&probe).await else {
        return;
    };
    let result = verify_isolation(&pool).await;
    assert!(
        result.is_ok(),
        "properly configured probe table should pass: {result:?}"
    );
    pool.close().await;
    probe.cleanup().await;
}

#[tokio::test]
async fn adr_0011_system_tx_has_no_account_context() {
    let Some(probe) = setup_probe().await else {
        return;
    };
    let tx_result = SystemTx::begin(&probe.db).await;
    assert!(tx_result.is_ok(), "system transaction should begin");
    let Ok(mut tx) = tx_result else {
        return;
    };
    let setting =
        sqlx::query_scalar::<_, Option<String>>("SELECT current_setting('app.account_id', true)")
            .fetch_one(tx.conn())
            .await;
    assert!(
        setting.is_ok(),
        "system transaction should read its context"
    );
    if let Ok(setting) = setting {
        assert!(setting.is_none() || setting.as_deref() == Some(""));
    }
    let _ = tx.commit().await;
    probe.cleanup().await;
}
