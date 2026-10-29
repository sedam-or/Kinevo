#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "database acceptance checks use synthetic isolated fixtures"
)]

#[path = "support/fixtures.rs"]
mod fixtures;

use fixtures::{ISOLATION_FIXTURES, insert_account};
use kinevo_db::{
    SystemTx, Tx,
    isolation::verify_isolation,
    migrate::MigrationSource,
    require_db,
    testing::{TestApp, isolation::run_isolation},
};
use kinevo_kernel::id::{AccountId, IdGen};
use kinevo_mod_admin::migrations;
use kinevo_mod_identity::migrations as identity_migrations;
use sqlx::error::DatabaseError;

type ColumnSpec = (
    &'static str,
    &'static str,
    &'static str,
    Option<&'static str>,
);
type TableColumns = (&'static str, &'static [ColumnSpec]);
type RoleGrant = (&'static str, &'static str, bool, bool, bool, bool);

const EXPECTED_COLUMNS: &[TableColumns] = &[
    (
        "instance_settings",
        &[
            ("id", "int2", "NO", None),
            ("setup_completed", "bool", "NO", Some("false")),
            ("setup_token_hash", "bytea", "YES", None),
            ("base_url", "text", "YES", None),
            ("default_locale", "text", "NO", Some("'en'::text")),
            ("template_id", "text", "YES", None),
            ("created_at", "timestamptz", "NO", Some("now()")),
            ("updated_at", "timestamptz", "NO", Some("now()")),
            ("version", "int8", "NO", Some("1")),
        ],
    ),
    (
        "instance_modules",
        &[
            ("module_key", "text", "NO", None),
            ("enabled", "bool", "NO", None),
            ("updated_at", "timestamptz", "NO", Some("now()")),
            ("updated_by", "uuid", "YES", None),
            ("version", "int8", "NO", Some("1")),
        ],
    ),
    (
        "policy_versions",
        &[
            ("id", "uuid", "NO", None),
            ("scope", "text", "NO", None),
            ("account_id", "uuid", "YES", None),
            ("key", "text", "NO", None),
            ("value", "jsonb", "NO", None),
            ("effective_from", "timestamptz", "NO", None),
            ("created_by", "uuid", "YES", None),
            ("created_at", "timestamptz", "NO", Some("now()")),
        ],
    ),
    (
        "secrets",
        &[
            ("id", "uuid", "NO", None),
            ("account_id", "uuid", "YES", None),
            ("name", "text", "NO", None),
            ("ciphertext", "bytea", "NO", None),
            ("nonce", "bytea", "NO", None),
            ("key_id", "int2", "NO", None),
            ("last4", "text", "YES", None),
            ("created_at", "timestamptz", "NO", Some("now()")),
            ("updated_at", "timestamptz", "NO", Some("now()")),
        ],
    ),
];

fn migration_sources() -> [MigrationSource; 3] {
    [
        kinevo_db::migrations_source(),
        identity_migrations(),
        migrations(),
    ]
}

fn is_sqlstate(error: &sqlx::Error, expected: &str) -> bool {
    error.as_database_error().is_some_and(|database| {
        DatabaseError::code(database).is_some_and(|code| code.as_ref() == expected)
    })
}

async fn seed_account(app: &TestApp) -> AccountId {
    let account = app.new_account();
    let mut tx = Tx::begin(&app.db, account)
        .await
        .expect("account transaction should begin");
    insert_account(
        &mut tx,
        *account.as_uuid(),
        &format!("{account}@example.test"),
    )
    .await
    .expect("synthetic account should insert");
    tx.commit()
        .await
        .expect("account transaction should commit");
    account
}

async fn insert_policy(
    app: &TestApp,
    id: sqlx::types::Uuid,
    scope: &str,
    account_id: Option<sqlx::types::Uuid>,
    value: &str,
) {
    sqlx::query(
        "INSERT INTO public.policy_versions \
         (id, scope, account_id, key, value, effective_from) \
         VALUES ($1, $2, $3, 'auth.access_token.ttl', $4::jsonb, '2026-11-02T00:00:00Z'::timestamptz)",
    )
    .bind(id)
    .bind(scope)
    .bind(account_id)
    .bind(value)
    .execute(app.admin_pool())
    .await
    .expect("policy fixture should insert");
}

async fn assert_columns_match(app: &TestApp) {
    for &(table, expected) in EXPECTED_COLUMNS {
        let actual = sqlx::query_as::<_, (String, String, String, Option<String>)>(
            "SELECT column_name::text, udt_name::text, is_nullable::text, column_default::text \
             FROM information_schema.columns \
             WHERE table_schema = 'public' AND table_name = $1 ORDER BY ordinal_position",
        )
        .bind(table)
        .fetch_all(app.admin_pool())
        .await
        .expect("admin table metadata should be queryable");
        assert_eq!(
            actual,
            expected
                .iter()
                .map(|(name, kind, nullable, default)| {
                    (
                        (*name).to_owned(),
                        (*kind).to_owned(),
                        (*nullable).to_owned(),
                        default.map(str::to_owned),
                    )
                })
                .collect::<Vec<_>>(),
            "column metadata for {table}"
        );
    }
}

async fn assert_constraints_and_no_rls_match(app: &TestApp) {
    let constraints = sqlx::query_as::<_, (String, String)>(
        "SELECT conname::text, contype::text FROM pg_constraint \
         WHERE conrelid IN ('public.instance_settings'::regclass, 'public.instance_modules'::regclass, \
                            'public.policy_versions'::regclass, 'public.secrets'::regclass) \
         ORDER BY conname",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("admin constraints should be queryable");
    assert_eq!(
        constraints,
        [
            ("instance_locale_ck".to_owned(), "c".to_owned()),
            (
                "instance_modules_module_key_check".to_owned(),
                "c".to_owned()
            ),
            ("instance_modules_pkey".to_owned(), "p".to_owned()),
            (
                "instance_modules_updated_by_fkey".to_owned(),
                "f".to_owned()
            ),
            ("instance_settings_id_check".to_owned(), "c".to_owned()),
            ("instance_settings_pkey".to_owned(), "p".to_owned()),
            ("policy_scope_account_ck".to_owned(), "c".to_owned()),
            ("policy_scope_ck".to_owned(), "c".to_owned()),
            ("policy_versions_account_id_fkey".to_owned(), "f".to_owned()),
            ("policy_versions_created_by_fkey".to_owned(), "f".to_owned()),
            ("policy_versions_key_check".to_owned(), "c".to_owned()),
            ("policy_versions_pkey".to_owned(), "p".to_owned()),
            ("secrets_account_id_fkey".to_owned(), "f".to_owned()),
            ("secrets_account_id_name_key".to_owned(), "u".to_owned()),
            ("secrets_pkey".to_owned(), "p".to_owned()),
        ]
    );

    let foreign_key_actions = sqlx::query_as::<_, (String, String)>(
        "SELECT conname::text, confdeltype::text FROM pg_constraint \
         WHERE conname IN ('instance_modules_updated_by_fkey', 'policy_versions_account_id_fkey', \
                           'policy_versions_created_by_fkey', 'secrets_account_id_fkey') \
         ORDER BY conname",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("admin foreign key actions should be queryable");
    assert_eq!(
        foreign_key_actions,
        [
            (
                "instance_modules_updated_by_fkey".to_owned(),
                "n".to_owned()
            ),
            ("policy_versions_account_id_fkey".to_owned(), "c".to_owned()),
            ("policy_versions_created_by_fkey".to_owned(), "n".to_owned()),
            ("secrets_account_id_fkey".to_owned(), "c".to_owned()),
        ]
    );

    let no_rls_tables = sqlx::query_as::<_, (String, bool, bool)>(
        "SELECT relname::text, relrowsecurity, relforcerowsecurity FROM pg_class \
         WHERE oid IN ('public.instance_settings'::regclass, 'public.instance_modules'::regclass) \
         ORDER BY relname",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("instance-level tables should be queryable");
    assert_eq!(
        no_rls_tables,
        [
            ("instance_modules".to_owned(), false, false),
            ("instance_settings".to_owned(), false, false),
        ]
    );
}

async fn assert_index_and_seed_match(app: &TestApp) {
    let lookup_index: String = sqlx::query_scalar(
        "SELECT indexdef::text FROM pg_indexes WHERE schemaname = 'public' \
         AND indexname = 'policy_versions_lookup'",
    )
    .fetch_one(app.admin_pool())
    .await
    .expect("policy lookup index should exist");
    assert!(lookup_index.contains("(key, scope, account_id, effective_from DESC, id DESC)"));

    let nulls_not_distinct: bool = sqlx::query_scalar(
        "SELECT i.indnullsnotdistinct FROM pg_index i \
         JOIN pg_class c ON c.oid = i.indexrelid \
         WHERE c.relname = 'secrets_account_id_name_key'",
    )
    .fetch_one(app.admin_pool())
    .await
    .expect("secrets unique constraint should exist");
    assert!(nulls_not_distinct);

    let singleton_count: i64 = sqlx::query_scalar(
        "SELECT count(*) FROM public.instance_settings WHERE id = 1 AND setup_completed = false",
    )
    .fetch_one(app.admin_pool())
    .await
    .expect("singleton row should be queryable");
    assert_eq!(singleton_count, 1);
}

async fn assert_grants_match(app: &TestApp) {
    let expected: &[RoleGrant] = &[
        ("kinevo_app", "instance_settings", true, true, true, false),
        (
            "kinevo_system",
            "instance_settings",
            true,
            false,
            false,
            false,
        ),
        ("kinevo_app", "instance_modules", true, true, true, false),
        (
            "kinevo_system",
            "instance_modules",
            true,
            false,
            false,
            false,
        ),
        ("kinevo_app", "policy_versions", true, true, false, false),
        (
            "kinevo_system",
            "policy_versions",
            true,
            false,
            false,
            false,
        ),
        ("kinevo_app", "secrets", true, true, true, true),
        ("kinevo_system", "secrets", true, false, false, false),
    ];

    for &(role, table, select, insert, update, delete) in expected {
        let actual = sqlx::query_as::<_, (bool, bool, bool, bool)>(
            "SELECT has_table_privilege($1, $2, 'SELECT'), \
                    has_table_privilege($1, $2, 'INSERT'), \
                    has_table_privilege($1, $2, 'UPDATE'), \
                    has_table_privilege($1, $2, 'DELETE')",
        )
        .bind(role)
        .bind(format!("public.{table}"))
        .fetch_one(app.admin_pool())
        .await
        .expect("admin table grants should be queryable");
        assert_eq!(
            actual,
            (select, insert, update, delete),
            "{role} on {table}"
        );
    }
}

#[tokio::test]
async fn dom_schema_matches_spec_ddl() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    assert_columns_match(&app).await;
    assert_constraints_and_no_rls_match(&app).await;
    assert_index_and_seed_match(&app).await;
    assert_grants_match(&app).await;
}

#[tokio::test]
async fn sec_ac_01_admin_tables_isolated() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    run_isolation(&app, ISOLATION_FIXTURES).await.assert_clean();
}

#[tokio::test]
async fn adr_0011_instance_policy_needs_admin_flag() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account = seed_account(&app).await;
    let id = app.ids.new_uuid_v7();

    let mut tx = Tx::begin(&app.db, account)
        .await
        .expect("account transaction should begin");
    let ordinary = sqlx::query(
        "INSERT INTO public.policy_versions (id, scope, account_id, key, value, effective_from) \
         VALUES ($1, 'instance', NULL, 'auth.access_token.ttl', '600'::jsonb, '2026-11-02T00:00:00Z'::timestamptz)",
    )
    .bind(id)
    .execute(tx.conn())
    .await
    .expect_err("ordinary account context must not write instance policy");
    assert!(is_sqlstate(&ordinary, "42501"));
    drop(tx);

    let mut tx = Tx::begin_admin(&app.db, account)
        .await
        .expect("admin transaction should begin");
    sqlx::query(
        "INSERT INTO public.policy_versions (id, scope, account_id, key, value, effective_from) \
         VALUES ($1, 'instance', NULL, 'auth.access_token.ttl', '600'::jsonb, '2026-11-02T00:00:00Z'::timestamptz)",
    )
    .bind(id)
    .execute(tx.conn())
    .await
    .expect("admin context should write instance policy");
    tx.commit().await.expect("admin transaction should commit");
}

#[tokio::test]
async fn pol_scope_visibility() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account_a = seed_account(&app).await;
    let account_b = seed_account(&app).await;
    insert_policy(&app, app.ids.new_uuid_v7(), "instance", None, "600").await;
    insert_policy(
        &app,
        app.ids.new_uuid_v7(),
        "account",
        Some(*account_a.as_uuid()),
        "300",
    )
    .await;
    insert_policy(
        &app,
        app.ids.new_uuid_v7(),
        "account",
        Some(*account_b.as_uuid()),
        "120",
    )
    .await;

    let mut tx = Tx::begin(&app.db, account_a)
        .await
        .expect("account transaction should begin");
    let values = sqlx::query_scalar::<_, String>(
        "SELECT value::text FROM public.policy_versions \
         WHERE key = 'auth.access_token.ttl' ORDER BY value::text",
    )
    .fetch_all(tx.conn())
    .await
    .expect("scoped policy values should be queryable");
    assert_eq!(values, ["300", "600"]);
    tx.commit()
        .await
        .expect("account transaction should commit");
}

#[tokio::test]
async fn dom_26_policy_versions_append_only() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account = seed_account(&app).await;
    let id = app.ids.new_uuid_v7();
    insert_policy(&app, id, "account", Some(*account.as_uuid()), "600").await;

    let mut tx = Tx::begin(&app.db, account)
        .await
        .expect("account transaction should begin");
    let update =
        sqlx::query("UPDATE public.policy_versions SET value = '601'::jsonb WHERE id = $1")
            .bind(id)
            .execute(tx.conn())
            .await
            .expect_err("app role must not update policy history");
    assert!(is_sqlstate(&update, "42501"));
    drop(tx);

    let mut tx = Tx::begin(&app.db, account)
        .await
        .expect("second account transaction should begin");
    let delete = sqlx::query("DELETE FROM public.policy_versions WHERE id = $1")
        .bind(id)
        .execute(tx.conn())
        .await
        .expect_err("app role must not delete policy history");
    assert!(is_sqlstate(&delete, "42501"));
}

#[tokio::test]
async fn d_46_secrets_policies() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account_a = seed_account(&app).await;
    let account_b = seed_account(&app).await;
    for (account_id, name, cipher_marker, key_id) in [
        (None, "ai.api_key", 0x11_u8, 1_i16),
        (Some(*account_a.as_uuid()), "ai.api_key", 0x22_u8, 2_i16),
        (Some(*account_b.as_uuid()), "ai.api_key", 0x33_u8, 3_i16),
    ] {
        sqlx::query(
            "INSERT INTO public.secrets (id, account_id, name, ciphertext, nonce, key_id, last4) \
             VALUES ($1, $2, $3, $4, $5, $6, 'test')",
        )
        .bind(app.ids.new_uuid_v7())
        .bind(account_id)
        .bind(name)
        .bind([cipher_marker; 32].as_slice())
        .bind([0x4D_u8; 12].as_slice())
        .bind(key_id)
        .execute(app.admin_pool())
        .await
        .expect("secret fixture should insert");
    }

    let mut tx = Tx::begin(&app.db, account_a)
        .await
        .expect("account transaction should begin");
    let visible: i64 =
        sqlx::query_scalar("SELECT count(*) FROM public.secrets WHERE name = 'ai.api_key'")
            .fetch_one(tx.conn())
            .await
            .expect("account should read its secrets and instance secrets");
    assert_eq!(visible, 2);
    let denied = sqlx::query(
        "INSERT INTO public.secrets (id, account_id, name, ciphertext, nonce, key_id) \
         VALUES ($1, NULL, 'smtp.password', $2, $3, 1)",
    )
    .bind(app.ids.new_uuid_v7())
    .bind([0xAA_u8; 32].as_slice())
    .bind([0xBB_u8; 12].as_slice())
    .execute(tx.conn())
    .await
    .expect_err("ordinary account context cannot write instance secrets");
    assert!(is_sqlstate(&denied, "42501"));
    drop(tx);

    let mut tx = Tx::begin_admin(&app.db, account_a)
        .await
        .expect("admin transaction should begin");
    sqlx::query(
        "INSERT INTO public.secrets (id, account_id, name, ciphertext, nonce, key_id) \
         VALUES ($1, NULL, 'smtp.password', $2, $3, 1)",
    )
    .bind(app.ids.new_uuid_v7())
    .bind([0xCC_u8; 32].as_slice())
    .bind([0xDD_u8; 12].as_slice())
    .execute(tx.conn())
    .await
    .expect("admin context should write instance secret");
    tx.commit().await.expect("admin transaction should commit");

    let mut system = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    let visible: i64 = sqlx::query_scalar("SELECT count(*) FROM public.secrets")
        .fetch_one(system.conn())
        .await
        .expect("system role can read instance secrets");
    assert_eq!(visible, 2);
    system
        .commit()
        .await
        .expect("system transaction should commit");
}

#[tokio::test]
async fn instance_settings_singleton() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let error = sqlx::query("INSERT INTO public.instance_settings (id) VALUES (2)")
        .execute(app.admin_pool())
        .await
        .expect_err("instance settings must be a singleton");
    assert!(is_sqlstate(&error, "23514"));
}

#[tokio::test]
async fn sec_06_verify_isolation_accepts_admin() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    verify_isolation(app.app_pool_for_test())
        .await
        .expect("full M1 schema should satisfy the declared isolation rules");
}
