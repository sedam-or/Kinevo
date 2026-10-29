use std::collections::{BTreeMap, HashSet};

use futures::future::BoxFuture;
use kinevo_kernel::id::{AccountId, IdGen};
use sqlx::{AssertSqlSafe, PgPool};

use crate::{
    Tx,
    testing::{TestApp, TestIds},
};

const EXCLUDED_TABLES: &[(&str, &str)] = &[
    (
        "instance_settings",
        "single-instance settings have no tenant rows",
    ),
    ("instance_modules", "module switches are instance-scoped"),
    ("invites", "invite tokens are instance-scoped"),
    ("_sqlx_migrations", "SQLx owns its migration metadata table"),
];

/// Insert callback used by the generated tenant-isolation checks.
pub type InsertFn = for<'a> fn(&'a mut Tx, &'a TestIds) -> BoxFuture<'a, Result<(), sqlx::Error>>;

/// Deterministic fixture for a tenant table.
#[derive(Clone, Copy)]
pub struct IsolationFixture {
    pub table: &'static str,
    pub insert: InsertFn,
    /// SQL fragment beginning with `SET`, supplied by the table's fixture.
    pub touch_sql: &'static str,
    /// Whether the app role is expected to update a row in this table.
    pub update_allowed: bool,
    /// Whether the app role is expected to delete a row in this table.
    pub delete_allowed: bool,
}

/// Findings collected while checking every tenant table in the database.
#[derive(Debug, Default)]
pub struct IsolationReport {
    pub findings: Vec<String>,
}

impl IsolationReport {
    /// Panics with every finding if the generated isolation checks fail.
    ///
    /// # Panics
    ///
    /// Panics when `findings` is not empty.
    pub fn assert_clean(&self) {
        assert!(
            self.findings.is_empty(),
            "tenant isolation findings: {}",
            self.findings.join(", ")
        );
    }
}

/// Runs the registered fixtures against every tenant table in `public`.
#[allow(
    clippy::too_many_lines,
    reason = "the verifier keeps discovery, fixture execution, and findings in one ordered pass"
)]
pub async fn run_isolation(app: &TestApp, fixtures: &[IsolationFixture]) -> IsolationReport {
    let mut report = IsolationReport::default();
    let discovered = sqlx::query_scalar::<_, String>(
        "SELECT c.relname::text FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p') AND (c.relname = 'accounts' OR EXISTS (SELECT 1 FROM pg_attribute a WHERE a.attrelid = c.oid AND a.attname = 'account_id' AND NOT a.attisdropped)) ORDER BY c.relname::text",
    )
    .fetch_all(app.admin_pool())
    .await;
    let Ok(discovered) = discovered else {
        return IsolationReport {
            findings: vec!["public: tenant table discovery failed".to_owned()],
        };
    };

    let tenant_tables: Vec<String> = discovered
        .into_iter()
        .filter(|table| {
            !EXCLUDED_TABLES
                .iter()
                .any(|(excluded, _)| *excluded == table)
                && crate::isolation::no_rls_allowlist_reason(table).is_none()
        })
        .collect();
    let tenant_names: HashSet<&str> = tenant_tables.iter().map(String::as_str).collect();
    let mut fixtures_by_table: BTreeMap<&str, Vec<&IsolationFixture>> = BTreeMap::new();
    for fixture in fixtures {
        fixtures_by_table
            .entry(fixture.table)
            .or_default()
            .push(fixture);
        if !tenant_names.contains(fixture.table) {
            report.findings.push(format!(
                "{}: fixture names a non-tenant table",
                fixture.table
            ));
        }
    }

    for (table, matching) in &fixtures_by_table {
        if matching.len() > 1 {
            report.findings.push(format!("{table}: duplicate fixtures"));
        }
    }
    for table in &tenant_tables {
        if !fixtures_by_table.contains_key(table.as_str()) {
            report.findings.push(format!("{table}: missing fixture"));
        }
        check_rls(app.admin_pool(), table, &mut report.findings).await;
    }

    let account_a = app.new_account();
    let account_b = app.new_account();
    let accounts_seeded = if tenant_names.contains("accounts") {
        seed_accounts(
            app,
            &fixtures_by_table,
            account_a,
            account_b,
            &mut report.findings,
        )
        .await
    } else {
        false
    };

    for table in &tenant_tables {
        let Some(matching) = fixtures_by_table.get(table.as_str()) else {
            continue;
        };
        let [fixture] = matching.as_slice() else {
            continue;
        };
        if table == "accounts" {
            if !accounts_seeded {
                continue;
            }
        } else {
            let ids_a = TestIds {
                account_id: account_a,
                row_id: app.ids.new_uuid_v7(),
            };
            let ids_b = TestIds {
                account_id: account_b,
                row_id: app.ids.new_uuid_v7(),
            };
            let inserted_a = insert_fixture(app, fixture, ids_a).await;
            let inserted_b = insert_fixture(app, fixture, ids_b).await;
            if inserted_a.is_err() || inserted_b.is_err() {
                report
                    .findings
                    .push(format!("{table}: fixture insert failed"));
                continue;
            }
        }

        check_visible_rows(app, fixture, account_a, &mut report.findings).await;
        if fixture.update_allowed {
            check_update(app, fixture, account_a, &mut report.findings).await;
        }
        check_other_account_row(app, table, account_b, &mut report.findings).await;
        if fixture.delete_allowed {
            check_delete(app, fixture, account_a, &mut report.findings).await;
        }
        check_other_account_row(app, table, account_b, &mut report.findings).await;
        check_foreign_insert(app, fixture, account_a, account_b, &mut report.findings).await;
    }

    report.findings.sort();
    report
}

async fn seed_accounts(
    app: &TestApp,
    fixtures: &BTreeMap<&str, Vec<&IsolationFixture>>,
    account_a: AccountId,
    account_b: AccountId,
    findings: &mut Vec<String>,
) -> bool {
    let Some([fixture]) = fixtures.get("accounts").map(Vec::as_slice) else {
        return false;
    };
    let ids_a = TestIds {
        account_id: account_a,
        row_id: *account_a.as_uuid(),
    };
    let ids_b = TestIds {
        account_id: account_b,
        row_id: *account_b.as_uuid(),
    };
    let inserted_a = insert_fixture(app, fixture, ids_a).await;
    let inserted_b = insert_fixture(app, fixture, ids_b).await;
    let inserted = inserted_a.is_ok() && inserted_b.is_ok();
    if !inserted {
        findings.push("accounts: fixture insert failed".to_owned());
    }
    inserted
}

async fn insert_fixture(app: &TestApp, fixture: &IsolationFixture, ids: TestIds) -> Result<(), ()> {
    let mut tx = Tx::begin(&app.db, ids.account_id).await.map_err(|_| ())?;
    (fixture.insert)(&mut tx, &ids).await.map_err(|_| ())?;
    tx.commit().await.map_err(|_| ())
}

async fn check_rls(pool: &PgPool, table: &str, findings: &mut Vec<String>) {
    let flags = sqlx::query_as::<_, (bool, bool)>(
        "SELECT c.relrowsecurity, c.relforcerowsecurity FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relname = $1",
    )
    .bind(table)
    .fetch_optional(pool)
    .await;
    match flags {
        Ok(Some((enabled, forced))) => {
            if !enabled {
                findings.push(format!("{table}: RLS is not enabled"));
            }
            if !forced {
                findings.push(format!("{table}: FORCE ROW LEVEL SECURITY is not enabled"));
            }
        }
        Ok(None) | Err(_) => findings.push(format!("{table}: RLS catalog check failed")),
    }
}

async fn check_visible_rows(
    app: &TestApp,
    fixture: &IsolationFixture,
    account: kinevo_kernel::id::AccountId,
    findings: &mut Vec<String>,
) {
    let table = quote_identifier(fixture.table);
    let statement = format!("SELECT count(*) FROM public.{table}");
    let Ok(mut tx) = Tx::begin(&app.db, account).await else {
        findings.push(format!("{}: account transaction failed", fixture.table));
        return;
    };
    let count = sqlx::query_scalar::<_, i64>(AssertSqlSafe(statement.as_str()))
        .fetch_one(tx.conn())
        .await;
    match count {
        Ok(1) => {}
        Ok(_) => findings.push(format!(
            "{}: unfiltered SELECT did not return one row",
            fixture.table
        )),
        Err(_) => findings.push(format!("{}: unfiltered SELECT failed", fixture.table)),
    }
    if tx.commit().await.is_err() {
        findings.push(format!(
            "{}: account transaction commit failed",
            fixture.table
        ));
    }
}

async fn check_update(
    app: &TestApp,
    fixture: &IsolationFixture,
    account: kinevo_kernel::id::AccountId,
    findings: &mut Vec<String>,
) {
    let table = quote_identifier(fixture.table);
    let statement = format!("UPDATE public.{table} {}", fixture.touch_sql);
    let Ok(mut tx) = Tx::begin(&app.db, account).await else {
        findings.push(format!("{}: account transaction failed", fixture.table));
        return;
    };
    let result = sqlx::query(AssertSqlSafe(statement.as_str()))
        .execute(tx.conn())
        .await;
    match result {
        Ok(result) if result.rows_affected() == 1 => {}
        Ok(_) => findings.push(format!(
            "{}: unfiltered UPDATE did not affect one row",
            fixture.table
        )),
        Err(_) => findings.push(format!("{}: unfiltered UPDATE failed", fixture.table)),
    }
    if tx.commit().await.is_err() {
        findings.push(format!(
            "{}: account transaction commit failed",
            fixture.table
        ));
    }
}

async fn check_delete(
    app: &TestApp,
    fixture: &IsolationFixture,
    account: kinevo_kernel::id::AccountId,
    findings: &mut Vec<String>,
) {
    let table = quote_identifier(fixture.table);
    let statement = format!("DELETE FROM public.{table}");
    let Ok(mut tx) = Tx::begin(&app.db, account).await else {
        findings.push(format!("{}: account transaction failed", fixture.table));
        return;
    };
    let result = sqlx::query(AssertSqlSafe(statement.as_str()))
        .execute(tx.conn())
        .await;
    match result {
        Ok(result) if result.rows_affected() == 1 => {}
        Ok(_) => findings.push(format!(
            "{}: unfiltered DELETE did not affect one row",
            fixture.table
        )),
        Err(_) => findings.push(format!("{}: unfiltered DELETE failed", fixture.table)),
    }
    if tx.rollback().await.is_err() {
        findings.push(format!(
            "{}: account delete probe rollback failed",
            fixture.table
        ));
    }
}

async fn check_other_account_row(
    app: &TestApp,
    table: &str,
    account_b: kinevo_kernel::id::AccountId,
    findings: &mut Vec<String>,
) {
    let column = if table == "accounts" {
        "id"
    } else {
        "account_id"
    };
    let quoted_table = quote_identifier(table);
    let statement = format!("SELECT count(*) FROM public.{quoted_table} WHERE {column} = $1");
    let count = sqlx::query_scalar::<_, i64>(AssertSqlSafe(statement.as_str()))
        .bind(*account_b.as_uuid())
        .fetch_one(app.admin_pool())
        .await;
    match count {
        Ok(1) => {}
        Ok(_) => findings.push(format!("{table}: account B row was changed")),
        Err(_) => findings.push(format!("{table}: account B row could not be checked")),
    }
}

async fn check_foreign_insert(
    app: &TestApp,
    fixture: &IsolationFixture,
    account_a: kinevo_kernel::id::AccountId,
    account_b: kinevo_kernel::id::AccountId,
    findings: &mut Vec<String>,
) {
    let Ok(mut tx) = Tx::begin(&app.db, account_a).await else {
        findings.push(format!("{}: account transaction failed", fixture.table));
        return;
    };
    let ids = TestIds {
        account_id: account_b,
        row_id: app.ids.new_uuid_v7(),
    };
    match (fixture.insert)(&mut tx, &ids).await {
        Err(error)
            if matches!(
                error.as_database_error().and_then(sqlx::error::DatabaseError::code),
                Some(code) if code.as_ref() == "42501"
            ) => {}
        Err(_) => findings.push(format!(
            "{}: foreign INSERT failed for another reason",
            fixture.table
        )),
        Ok(()) => findings.push(format!("{}: foreign INSERT was not blocked", fixture.table)),
    }
    drop(tx);
}

fn quote_identifier(identifier: &str) -> String {
    format!("\"{}\"", identifier.replace('"', "\"\""))
}
