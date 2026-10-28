#![cfg(feature = "testing")]
#![allow(clippy::expect_used)] // Integration assertions keep setup failures explicit.

use kinevo_db::{migrate, require_db, testing::TestDb};
use sqlx::migrate::{Migration, MigrationType, Migrator};
use sqlx::{Row, SqlSafeStr};

#[path = "support/migrations.rs"]
mod migrations;

fn custom_source(sql: &'static str) -> migrate::MigrationSource {
    let migration = Migration::new(
        20_261_101_000_000_i64,
        "platform_extensions".into(),
        MigrationType::Simple,
        sql.into_sql_str(),
        false,
    );
    let migrator = Box::leak(Box::new(Migrator::with_migrations(vec![migration])));
    migrate::MigrationSource {
        crate_name: "kinevo-db",
        module: "platform",
        migrator,
    }
}

#[tokio::test]
async fn platform_extensions_apply_once() {
    let source = migrations::migration_sources();
    require_db!(test_db = TestDb::create(&source).await);

    let status = migrate::status(test_db.admin_pool(), &source)
        .await
        .expect("migration status should read the migrated database");
    assert_eq!(status.applied, 3);
    assert_eq!(status.pending, 0);
    assert!(status.up_to_date);

    let second = test_db
        .run_migrations(&source)
        .await
        .expect("a second migration run should be a no-op");
    assert_eq!(second.count, 0);
}

#[tokio::test]
async fn ca_18_checksum_change_detected() {
    let original = [custom_source("SELECT 1")];
    require_db!(test_db = TestDb::create(&original).await);

    let changed = [custom_source("SELECT 2")];
    let error = test_db
        .run_migrations(&changed)
        .await
        .expect_err("changing an applied migration should fail");
    assert!(matches!(
        error,
        migrate::MigrateError::ChecksumMismatch {
            version: 20_261_101_000_000
        }
    ));
    assert!(!error.to_string().contains("migrator-test-secret"));
}

#[tokio::test]
async fn arch_21_roles_are_unprivileged_and_idempotent() {
    require_db!(test_db = TestDb::create(&[]).await);
    test_db
        .rebootstrap_roles()
        .await
        .expect("role bootstrap should be idempotent");

    for role in ["kinevo_migrator", "kinevo_app", "kinevo_system"] {
        let row = sqlx::query(
            "SELECT rolsuper, rolbypassrls, rolcreatedb, rolcreaterole FROM pg_roles WHERE rolname = $1",
        )
        .bind(role)
        .fetch_one(test_db.admin_pool())
        .await
        .expect("bootstrapped role should exist");
        let superuser: bool = row
            .try_get("rolsuper")
            .expect("superuser flag should exist");
        let bypass_rls: bool = row
            .try_get("rolbypassrls")
            .expect("bypass RLS flag should exist");
        let createdb: bool = row
            .try_get("rolcreatedb")
            .expect("createdb flag should exist");
        let createrole: bool = row
            .try_get("rolcreaterole")
            .expect("createrole flag should exist");
        assert!(!superuser && !bypass_rls && !createdb && !createrole);
    }

    let migrator_can_create_in_current_database: bool = sqlx::query_scalar(
        "SELECT has_database_privilege('kinevo_migrator', current_database(), 'CREATE')",
    )
    .fetch_one(test_db.admin_pool())
    .await
    .expect("migrator database privilege should be queryable");
    assert!(migrator_can_create_in_current_database);
}
