#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "database acceptance checks use isolated synthetic fixtures"
)]

#[path = "support/fixtures.rs"]
mod fixtures;

use fixtures::{ISOLATION_FIXTURES, insert_account, seed_account_with_auth_rows};
use kinevo_db::{
    SystemTx, Tx,
    isolation::verify_isolation,
    migrate::MigrationSource,
    require_db,
    testing::{TestApp, isolation::run_isolation},
};
use kinevo_kernel::errors::ErrorCode;
use kinevo_mod_identity::{infra::constraints::CONSTRAINTS, migrations};
use sqlx::{AssertSqlSafe, error::DatabaseError};

const EXPECTED_COLUMNS: &[(&str, &[&str])] = &[
    (
        "accounts",
        &[
            "id",
            "email",
            "email_verified_at",
            "display_name",
            "password_hash",
            "locale",
            "timezone",
            "week_start",
            "is_instance_admin",
            "status",
            "totp_enabled",
            "last_login_at",
            "failed_login_count",
            "failed_window_start",
            "locked_until",
            "created_at",
            "updated_at",
            "version",
        ],
    ),
    (
        "refresh_token_families",
        &[
            "id",
            "account_id",
            "device_label",
            "mfa",
            "created_at",
            "last_used_at",
            "absolute_expires_at",
            "revoked_at",
            "revoked_reason",
        ],
    ),
    (
        "refresh_tokens",
        &[
            "id",
            "family_id",
            "account_id",
            "token_hash",
            "issued_at",
            "idle_expires_at",
            "used_at",
            "replaced_by",
        ],
    ),
    (
        "recovery_codes",
        &["id", "account_id", "code_hash", "used_at", "created_at"],
    ),
    (
        "password_resets",
        &[
            "id",
            "account_id",
            "token_hash",
            "expires_at",
            "used_at",
            "created_by",
            "created_at",
        ],
    ),
    (
        "invites",
        &[
            "id",
            "token_hash",
            "email",
            "created_by",
            "expires_at",
            "used_at",
            "created_at",
        ],
    ),
    (
        "account_exports",
        &[
            "id",
            "account_id",
            "state",
            "bytes",
            "size_bytes",
            "created_at",
            "expires_at",
        ],
    ),
];

fn migration_sources() -> [MigrationSource; 2] {
    [kinevo_db::migrations_source(), migrations()]
}

#[tokio::test]
async fn dom_schema_matches_spec_ddl() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);

    for &(table, expected) in EXPECTED_COLUMNS {
        let actual = sqlx::query_scalar::<_, String>(
            "SELECT column_name::text FROM information_schema.columns \
             WHERE table_schema = 'public' AND table_name = $1 ORDER BY ordinal_position",
        )
        .bind(table)
        .fetch_all(app.admin_pool())
        .await
        .expect("table columns should be queryable");
        assert_eq!(
            actual.iter().map(String::as_str).collect::<Vec<_>>(),
            expected
        );
    }

    let email_type: String = sqlx::query_scalar(
        "SELECT udt_name::text FROM information_schema.columns \
         WHERE table_schema = 'public' AND table_name = 'accounts' AND column_name = 'email'",
    )
    .fetch_one(app.admin_pool())
    .await
    .expect("email column type should be queryable");
    assert_eq!(email_type, "citext");

    let named_constraints = sqlx::query_as::<_, (String, String)>(
        "SELECT conname::text, contype::text FROM pg_constraint \
         WHERE connamespace = 'public'::regnamespace \
         AND conname IN ('accounts_email_key', 'account_status_ck', 'account_locale_ck', \
                         'accounts_display_name_check', 'accounts_week_start_check', \
                         'refresh_tokens_token_hash_key', 'password_resets_token_hash_key', \
                         'invites_token_hash_key', 'rtf_reason_ck', 'export_state_ck') \
         ORDER BY conname",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("named identity constraints should be queryable");
    assert_eq!(
        named_constraints,
        [
            ("account_locale_ck".to_owned(), "c".to_owned()),
            ("account_status_ck".to_owned(), "c".to_owned()),
            ("accounts_display_name_check".to_owned(), "c".to_owned()),
            ("accounts_email_key".to_owned(), "u".to_owned()),
            ("accounts_week_start_check".to_owned(), "c".to_owned()),
            ("export_state_ck".to_owned(), "c".to_owned()),
            ("invites_token_hash_key".to_owned(), "u".to_owned()),
            ("password_resets_token_hash_key".to_owned(), "u".to_owned()),
            ("refresh_tokens_token_hash_key".to_owned(), "u".to_owned()),
            ("rtf_reason_ck".to_owned(), "c".to_owned()),
        ]
    );

    let identity_indexes = sqlx::query_scalar::<_, String>(
        "SELECT indexname::text FROM pg_indexes WHERE schemaname = 'public' \
         AND tablename IN ('accounts', 'refresh_token_families', 'refresh_tokens', \
                           'recovery_codes', 'password_resets', 'invites', 'account_exports') \
         ORDER BY indexname",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("identity indexes should be queryable");
    assert_eq!(
        identity_indexes,
        [
            "account_exports_pkey",
            "accounts_email_key",
            "accounts_pkey",
            "invites_pkey",
            "invites_token_hash_key",
            "password_resets_pkey",
            "password_resets_token_hash_key",
            "recovery_codes_pkey",
            "refresh_token_families_pkey",
            "refresh_tokens_family",
            "refresh_tokens_pkey",
            "refresh_tokens_token_hash_key",
        ]
    );

    let invites_rls: bool = sqlx::query_scalar(
        "SELECT relrowsecurity FROM pg_class WHERE oid = 'public.invites'::regclass",
    )
    .fetch_one(app.admin_pool())
    .await
    .expect("invite RLS flag should be queryable");
    assert!(!invites_rls);
}

#[tokio::test]
async fn sec_ac_01_identity_tables_isolated() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    run_isolation(&app, ISOLATION_FIXTURES).await.assert_clean();
}

#[tokio::test]
async fn adr_0011_system_auth_scope() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account_a = app.new_account();
    let account_b = app.new_account();
    seed_account_with_auth_rows(&app, account_a, 1).await;
    seed_account_with_auth_rows(&app, account_b, 2).await;

    let mut system = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    for table in [
        "accounts",
        "refresh_token_families",
        "refresh_tokens",
        "password_resets",
    ] {
        let query = format!("SELECT count(*) FROM public.{table}");
        let count: i64 = sqlx::query_scalar(AssertSqlSafe(query.as_str()))
            .fetch_one(system.conn())
            .await
            .expect("system role should read auth rows without account context");
        assert_eq!(count, 2);
    }
    system
        .commit()
        .await
        .expect("system transaction should commit");

    for table in ["recovery_codes", "account_exports"] {
        let mut system = SystemTx::begin(&app.db)
            .await
            .expect("system transaction should begin");
        let query = format!("SELECT count(*) FROM public.{table}");
        let result = sqlx::query_scalar::<_, i64>(AssertSqlSafe(query.as_str()))
            .fetch_one(system.conn())
            .await;
        assert!(result.is_err_and(|error| {
            error
                .as_database_error()
                .and_then(DatabaseError::code)
                .is_some_and(|code| code.as_ref() == "42501")
        }));
    }
}

#[tokio::test]
async fn sec_05_accounts_policy_uses_id() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account_a = app.new_account();
    let account_b = app.new_account();
    for (account, email) in [(account_a, "a@example.test"), (account_b, "b@example.test")] {
        let mut tx = Tx::begin(&app.db, account)
            .await
            .expect("account transaction should begin");
        insert_account(&mut tx, account, email)
            .await
            .expect("account row should insert in its own context");
        tx.commit()
            .await
            .expect("account transaction should commit");
    }

    let without_context: i64 = sqlx::query_scalar("SELECT count(*) FROM public.accounts")
        .fetch_one(app.app_pool_for_test())
        .await
        .expect("unscoped app-role read should succeed");
    assert_eq!(without_context, 0);
    let mut tx = Tx::begin(&app.db, account_a)
        .await
        .expect("account transaction should begin");
    let own_rows: i64 = sqlx::query_scalar("SELECT count(*) FROM public.accounts")
        .fetch_one(tx.conn())
        .await
        .expect("scoped app-role read should succeed");
    assert_eq!(own_rows, 1);
    tx.commit()
        .await
        .expect("account transaction should commit");
}

#[tokio::test]
async fn adr_0011_account_insert_only_for_own_id() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account_a = app.new_account();
    let account_b = app.new_account();
    let mut tx = Tx::begin(&app.db, account_a)
        .await
        .expect("account transaction should begin");
    let foreign = insert_account(&mut tx, account_b, "foreign@example.test").await;
    assert!(foreign.is_err_and(|error| {
        error
            .as_database_error()
            .and_then(DatabaseError::code)
            .is_some_and(|code| code.as_ref() == "42501")
    }));
    drop(tx);

    let mut tx = Tx::begin(&app.db, account_a)
        .await
        .expect("account transaction should begin");
    insert_account(&mut tx, account_a, "own@example.test")
        .await
        .expect("own account id should pass RLS check");
    tx.commit()
        .await
        .expect("account transaction should commit");
}

#[tokio::test]
async fn dom_07_email_unique_case_insensitive() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account_a = app.new_account();
    let account_b = app.new_account();
    let mut tx = Tx::begin(&app.db, account_a)
        .await
        .expect("first account transaction should begin");
    insert_account(&mut tx, account_a, "A@Example.test")
        .await
        .expect("first email should insert");
    tx.commit()
        .await
        .expect("first account transaction should commit");

    let mut tx = Tx::begin(&app.db, account_b)
        .await
        .expect("second account transaction should begin");
    let duplicate = insert_account(&mut tx, account_b, "a@example.TEST").await;
    drop(tx);
    let error = duplicate.expect_err("citext should reject case-insensitive duplicate email");
    assert_eq!(
        error
            .as_database_error()
            .and_then(DatabaseError::constraint),
        Some("accounts_email_key")
    );
    let registry = kinevo_db::errors::ConstraintRegistry::new([CONSTRAINTS]);
    assert_eq!(
        kinevo_db::errors::map_error(&registry, error).code,
        ErrorCode::EmailTaken
    );
}

#[tokio::test]
async fn sec_06_verify_isolation_accepts_identity() {
    let sources = migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    verify_isolation(app.app_pool_for_test())
        .await
        .expect("identity schema should satisfy the exact isolation policy set");
}
