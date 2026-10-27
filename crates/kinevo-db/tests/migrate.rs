use kinevo_db::{
    bootstrap::{RoleCredentials, bootstrap_roles},
    migrate,
};
use kinevo_kernel::secret::Secret;
use sqlx::migrate::{Migration, MigrationType, Migrator};
use sqlx::postgres::PgPoolOptions;
use sqlx::{Connection, PgConnection, PgPool, Row, SqlSafeStr};

fn database_url() -> Option<String> {
    if let Ok(url) = std::env::var("KINEVO_TEST_ADMIN_DATABASE_URL") {
        Some(url)
    } else {
        eprintln!("skipped: no database");
        assert!(
            !std::env::var("CI").is_ok_and(|value| value == "true"),
            "KINEVO_TEST_ADMIN_DATABASE_URL is required in CI"
        );
        None
    }
}

async fn lock_database_tests(admin_url: &str) -> Result<PgConnection, sqlx::Error> {
    let mut connection = PgConnection::connect(admin_url).await?;
    sqlx::query("SELECT pg_advisory_lock(hashtext('kinevo-db'), hashtext('migration-tests'))")
        .execute(&mut connection)
        .await?;
    Ok(connection)
}

fn role_url(admin_url: &str, username: &str, password: &str) -> String {
    let parsed = admin_url.split_once("://");
    assert!(parsed.is_some(), "test database URL must use a URL scheme");
    let Some((scheme, remainder)) = parsed else {
        return String::new();
    };
    let parsed = remainder.rsplit_once('@');
    assert!(parsed.is_some(), "test database URL must include userinfo");
    let Some((_, host_and_database)) = parsed else {
        return String::new();
    };
    format!("{scheme}://{username}:{password}@{host_and_database}")
}

async fn admin_pool(admin_url: &str) -> Result<PgPool, sqlx::Error> {
    PgPoolOptions::new()
        .max_connections(1)
        .connect(admin_url)
        .await
}

async fn reset_migration_table(pool: &PgPool) -> Result<(), sqlx::Error> {
    sqlx::query("DROP TABLE IF EXISTS _sqlx_migrations")
        .execute(pool)
        .await
        .map(|_| ())
}

fn role_credentials(admin_url: &str) -> Option<(RoleCredentials, String, String)> {
    let migrator_url = role_url(admin_url, "kinevo_migrator", "migrator-test-secret");
    let app_url = role_url(admin_url, "kinevo_app", "app-test-secret");
    let system_url = role_url(admin_url, "kinevo_system", "system-test-secret");
    let app_url_secret = Secret::new(app_url.clone());
    let migrator_url_secret = Secret::new(migrator_url.clone());
    let system_url_secret = Secret::new(system_url);
    let parsed =
        RoleCredentials::from_urls(&migrator_url_secret, &app_url_secret, &system_url_secret);
    assert!(parsed.is_ok(), "test role URLs should parse");
    match parsed {
        Ok((credentials, database_name)) => Some((credentials, database_name, migrator_url)),
        Err(_) => None,
    }
}

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
    let Some(admin_url) = database_url() else {
        return;
    };
    let lock = lock_database_tests(&admin_url).await;
    assert!(lock.is_ok(), "test database lock should be acquired");
    let Ok(_lock) = lock else {
        return;
    };
    let pool = admin_pool(&admin_url).await;
    assert!(pool.is_ok(), "test admin database must accept connections");
    let Ok(pool) = pool else {
        return;
    };
    let reset = reset_migration_table(&pool).await;
    assert!(
        reset.is_ok(),
        "migration history should be reset for this test"
    );
    let Some((credentials, database_name, migrator_url)) = role_credentials(&admin_url) else {
        return;
    };
    let bootstrap = bootstrap_roles(&Secret::new(admin_url), &credentials, &database_name).await;
    assert!(bootstrap.is_ok(), "role bootstrap should succeed");

    let migrator_url = Secret::new(migrator_url);
    let source = [kinevo_db::migrations_source()];
    let pending = migrate::status(&pool, &source).await;
    assert!(
        pending.is_ok(),
        "migration status should read the empty database"
    );
    let Ok(pending) = pending else {
        return;
    };
    assert_eq!(pending.applied, 0);
    assert_eq!(pending.pending, 1);
    assert!(!pending.up_to_date);

    let first = migrate::run(&migrator_url, &source).await;
    assert!(first.is_ok(), "the platform migration should apply");
    let Ok(first) = first else {
        return;
    };
    assert_eq!(first.count, 1);
    assert_eq!(first.last_version, Some(20_261_101_000_000));
    let current = migrate::status(&pool, &source).await;
    assert!(
        current.is_ok(),
        "migration status should read applied history"
    );
    let Ok(current) = current else {
        return;
    };
    assert_eq!(current.applied, 1);
    assert_eq!(current.pending, 0);
    assert!(current.up_to_date);

    let second = migrate::run(&migrator_url, &source).await;
    assert!(second.is_ok(), "a second migration run should be a no-op");
    let Ok(second) = second else {
        return;
    };
    assert_eq!(second.count, 0);
}

#[tokio::test]
async fn ca_18_checksum_change_detected() {
    let Some(admin_url) = database_url() else {
        return;
    };
    let lock = lock_database_tests(&admin_url).await;
    assert!(lock.is_ok(), "test database lock should be acquired");
    let Ok(_lock) = lock else {
        return;
    };
    let pool = admin_pool(&admin_url).await;
    assert!(pool.is_ok(), "test admin database must accept connections");
    let Ok(pool) = pool else {
        return;
    };
    let reset = reset_migration_table(&pool).await;
    assert!(
        reset.is_ok(),
        "migration history should be reset for this test"
    );

    let url = Secret::new(admin_url);
    let original = [custom_source("SELECT 1")];
    let applied = migrate::run(&url, &original).await;
    assert!(applied.is_ok(), "original migration should apply");

    let changed = [custom_source("SELECT 2")];
    let result = migrate::run(&url, &changed).await;
    let displayed_error = result.as_ref().err().map(ToString::to_string);
    assert!(matches!(
        result,
        Err(migrate::MigrateError::ChecksumMismatch {
            version: 20_261_101_000_000
        })
    ));
    assert!(displayed_error.is_some());
    let Some(displayed_error) = displayed_error else {
        return;
    };
    assert!(!displayed_error.contains(url.expose()));
}

#[tokio::test]
async fn arch_21_roles_are_unprivileged_and_idempotent() {
    let Some(admin_url) = database_url() else {
        return;
    };
    let lock = lock_database_tests(&admin_url).await;
    assert!(lock.is_ok(), "test database lock should be acquired");
    let Ok(_lock) = lock else {
        return;
    };
    let pool = admin_pool(&admin_url).await;
    assert!(pool.is_ok(), "test admin database must accept connections");
    let Ok(pool) = pool else {
        return;
    };
    let Some((credentials, database_name, _)) = role_credentials(&admin_url) else {
        return;
    };
    let admin_secret = Secret::new(admin_url.clone());
    let first = bootstrap_roles(&admin_secret, &credentials, &database_name).await;
    assert!(first.is_ok(), "role bootstrap should succeed");
    let second = bootstrap_roles(&admin_secret, &credentials, &database_name).await;
    assert!(second.is_ok(), "role bootstrap should be idempotent");

    let roles = ["kinevo_migrator", "kinevo_app", "kinevo_system"];
    for role in roles {
        let row = sqlx::query(
            "SELECT rolsuper, rolbypassrls, rolcreatedb, rolcreaterole FROM pg_roles WHERE rolname = $1",
        )
        .bind(role)
        .fetch_one(&pool)
        .await;
        assert!(row.is_ok(), "bootstrapped role should exist");
        let Ok(row) = row else {
            return;
        };
        let flags = (
            row.try_get::<bool, _>("rolsuper"),
            row.try_get::<bool, _>("rolbypassrls"),
            row.try_get::<bool, _>("rolcreatedb"),
            row.try_get::<bool, _>("rolcreaterole"),
        );
        assert!(
            flags.0.is_ok() && flags.1.is_ok() && flags.2.is_ok() && flags.3.is_ok(),
            "role flags should be readable"
        );
        let (Ok(superuser), Ok(bypass_rls), Ok(createdb), Ok(createrole)) = flags else {
            return;
        };
        assert!(!superuser && !bypass_rls && !createdb && !createrole);
    }
}
