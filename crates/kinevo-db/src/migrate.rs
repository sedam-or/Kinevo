use std::collections::HashSet;

use kinevo_kernel::secret::Secret;
use sqlx::ConnectOptions;
use sqlx::PgPool;
use sqlx::migrate::Migrator;
use sqlx::postgres::{PgConnectOptions, PgPoolOptions};
use thiserror::Error;

/// One crate's embedded migration set and its declared module name.
#[derive(Clone, Copy, Debug)]
pub struct MigrationSource {
    pub crate_name: &'static str,
    pub module: &'static str,
    pub migrator: &'static Migrator,
}

/// Errors raised while combining or applying crate migrations.
#[derive(Debug, Error)]
pub enum MigrateError {
    #[error("migration version {version} occurs in more than one source")]
    Duplicate { version: i64 },
    #[error("migration file {file} does not match module {expected}")]
    WrongModule { file: String, expected: String },
    #[error("migration file name is invalid: {file}")]
    InvalidFileName { file: String },
    #[error("migration file requires a no-transaction marker: {file}")]
    InvalidNoTransaction { file: String },
    #[error("migration version {version} is older than an applied migration")]
    OutOfOrder { version: i64 },
    #[error("applied migration {version} has a changed checksum")]
    ChecksumMismatch { version: i64 },
    #[error("database migration operation failed")]
    Db(#[source] sqlx::Error),
}

/// Summary of newly applied migrations.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct Applied {
    pub count: usize,
    pub last_version: Option<i64>,
}

/// Counts used by readiness checks.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct MigrationStatus {
    pub applied: usize,
    pub pending: usize,
    pub up_to_date: bool,
}

/// Combines source migrations into one timestamp-ordered `SQLx` migrator.
pub fn merge(sources: &[MigrationSource]) -> Result<Migrator, MigrateError> {
    let mut versions = HashSet::new();
    let mut migrations = Vec::new();

    for source in sources {
        for migration in source.migrator.iter() {
            let description = migration.description.replace(' ', "_");
            let file = migration_file(source, migration.version, &description);
            validate_filename(source.module, migration.version, &description, &file)?;
            validate_transaction_mode(
                migration.no_tx,
                migration.sql.as_str(),
                &description,
                &file,
            )?;
            if !versions.insert(migration.version) {
                return Err(MigrateError::Duplicate {
                    version: migration.version,
                });
            }
            migrations.push(migration.clone());
        }
    }

    migrations.sort_by_key(|migration| migration.version);
    Ok(Migrator::with_migrations(migrations))
}

/// Applies all pending migrations using the migrator role URL.
pub async fn run(
    migrator_url: &Secret<String>,
    sources: &[MigrationSource],
) -> Result<Applied, MigrateError> {
    let options = migrator_url
        .expose()
        .parse::<PgConnectOptions>()
        .map_err(MigrateError::Db)?
        .disable_statement_logging();
    let pool = PgPoolOptions::new()
        .connect_with(options)
        .await
        .map_err(MigrateError::Db)?;
    let migrator = merge(sources)?;
    let before = applied_versions(&pool).await?;
    validate_out_of_order(&migrator, &before)?;

    if let Err(error) = migrator.run(&pool).await {
        return Err(map_sqlx_migrate_error(error));
    }

    let after = applied_versions(&pool).await?;
    Ok(Applied {
        count: after.len().saturating_sub(before.len()),
        last_version: after.iter().max().copied(),
    })
}

/// Reports applied and pending migrations without changing the database.
pub async fn status(
    pool: &PgPool,
    sources: &[MigrationSource],
) -> Result<MigrationStatus, MigrateError> {
    let migrator = merge(sources)?;
    let applied_versions = applied_versions(pool).await?;
    let applied: HashSet<_> = applied_versions.iter().copied().collect();
    let local: HashSet<_> = migrator.iter().map(|migration| migration.version).collect();
    if let Some(version) = applied.iter().find(|version| !local.contains(version)) {
        return Err(MigrateError::Db(sqlx::Error::Migrate(Box::new(
            sqlx::migrate::MigrateError::VersionMissing(*version),
        ))));
    }
    let pending = local.difference(&applied).count();
    Ok(MigrationStatus {
        applied: applied.len(),
        pending,
        up_to_date: pending == 0,
    })
}

fn validate_out_of_order(
    migrator: &Migrator,
    applied_versions: &[i64],
) -> Result<(), MigrateError> {
    let Some(highest_applied) = applied_versions.iter().max() else {
        return Ok(());
    };
    let applied: HashSet<_> = applied_versions.iter().copied().collect();
    if let Some(migration) = migrator.iter().find(|migration| {
        migration.version < *highest_applied && !applied.contains(&migration.version)
    }) {
        return Err(MigrateError::OutOfOrder {
            version: migration.version,
        });
    }
    Ok(())
}

async fn applied_versions(pool: &PgPool) -> Result<Vec<i64>, MigrateError> {
    let table_exists: bool =
        sqlx::query_scalar("SELECT to_regclass('_sqlx_migrations') IS NOT NULL")
            .fetch_one(pool)
            .await
            .map_err(MigrateError::Db)?;
    if !table_exists {
        return Ok(Vec::new());
    }
    sqlx::query_scalar("SELECT version FROM _sqlx_migrations WHERE success ORDER BY version")
        .fetch_all(pool)
        .await
        .map_err(MigrateError::Db)
}

fn map_sqlx_migrate_error(error: sqlx::migrate::MigrateError) -> MigrateError {
    match error {
        sqlx::migrate::MigrateError::VersionMismatch(version) => {
            MigrateError::ChecksumMismatch { version }
        }
        sqlx::migrate::MigrateError::VersionTooOld(version, _) => {
            MigrateError::OutOfOrder { version }
        }
        other => MigrateError::Db(sqlx::Error::Migrate(Box::new(other))),
    }
}

fn migration_file(source: &MigrationSource, version: i64, description: &str) -> String {
    format!(
        "{}/migrations/{version:014}_{description}.sql",
        source.crate_name
    )
}

fn validate_filename(
    module: &str,
    version: i64,
    description: &str,
    file: &str,
) -> Result<(), MigrateError> {
    let version_text = version.to_string();
    let module_prefix = format!("{module}_");
    let Some(suffix) = description.strip_prefix(&module_prefix) else {
        return Err(MigrateError::WrongModule {
            file: file.to_owned(),
            expected: module.to_owned(),
        });
    };
    let valid_suffix = !suffix.is_empty()
        && suffix
            .bytes()
            .all(|byte| byte.is_ascii_lowercase() || byte.is_ascii_digit() || byte == b'_');
    if version_text.len() != 14 || !valid_suffix {
        return Err(MigrateError::InvalidFileName {
            file: file.to_owned(),
        });
    }
    Ok(())
}

fn validate_transaction_mode(
    no_tx: bool,
    sql: &str,
    description: &str,
    file: &str,
) -> Result<(), MigrateError> {
    let contains_concurrent_index = sql
        .to_ascii_uppercase()
        .contains("CREATE INDEX CONCURRENTLY");
    if contains_concurrent_index && (!no_tx || !description.ends_with("_nontx")) {
        return Err(MigrateError::InvalidNoTransaction {
            file: file.to_owned(),
        });
    }
    Ok(())
}

#[cfg(test)]
mod tests {
    use super::{MigrateError, MigrationSource, merge};
    use sqlx::SqlSafeStr;
    use sqlx::migrate::{Migration, MigrationType, Migrator};

    fn source(
        crate_name: &'static str,
        module: &'static str,
        entries: &[(i64, &'static str)],
    ) -> MigrationSource {
        let migrations = entries
            .iter()
            .map(|(version, description)| {
                Migration::new(
                    *version,
                    (*description).into(),
                    MigrationType::Simple,
                    "SELECT 1".into_sql_str(),
                    false,
                )
            })
            .collect();
        let migrator = Box::leak(Box::new(Migrator::with_migrations(migrations)));
        MigrationSource {
            crate_name,
            module,
            migrator,
        }
    }

    #[test]
    fn ca_18_global_order_by_timestamp() {
        let identity = source(
            "kinevo-mod-identity",
            "identity",
            &[
                (20_261_101_000_100, "identity_accounts"),
                (20_261_101_000_300, "identity_tokens"),
            ],
        );
        let admin = source(
            "kinevo-mod-admin",
            "admin",
            &[(20_261_101_000_200, "admin_settings")],
        );

        let merged = merge(&[identity, admin]);
        assert!(merged.is_ok(), "interleaved migrations should merge");
        let Ok(merged) = merged else {
            return;
        };
        let versions: Vec<_> = merged.iter().map(|migration| migration.version).collect();
        assert_eq!(
            versions,
            [20_261_101_000_100, 20_261_101_000_200, 20_261_101_000_300]
        );
    }

    #[test]
    fn duplicate_versions_rejected() {
        let identity = source(
            "kinevo-mod-identity",
            "identity",
            &[(20_261_101_000_100, "identity_accounts")],
        );
        let admin = source(
            "kinevo-mod-admin",
            "admin",
            &[(20_261_101_000_100, "admin_settings")],
        );

        let result = merge(&[identity, admin]);
        assert!(matches!(
            result,
            Err(MigrateError::Duplicate {
                version: 20_261_101_000_100
            })
        ));
    }

    #[test]
    fn d_40_file_module_matches_source() {
        let admin = source(
            "kinevo-mod-admin",
            "admin",
            &[(20_261_101_000_100, "identity_accounts")],
        );

        let result = merge(&[admin]);
        assert!(matches!(
            result,
            Err(MigrateError::WrongModule { expected, .. }) if expected == "admin"
        ));
    }

    #[test]
    fn out_of_order_rejected() {
        let late_source = source(
            "kinevo-mod-identity",
            "identity",
            &[(20_261_101_000_200, "identity_late")],
        );
        let merged = merge(&[late_source]);
        assert!(merged.is_ok(), "a valid source should merge");
        let Ok(merged) = merged else {
            return;
        };

        let result = super::validate_out_of_order(&merged, &[20_261_101_000_300]);
        assert!(matches!(
            result,
            Err(MigrateError::OutOfOrder {
                version: 20_261_101_000_200
            })
        ));
    }

    #[test]
    fn migration_error_display_redacts_database_url() {
        let source = std::io::Error::other("postgres://test-user:test-password@localhost/test-db");
        let error = MigrateError::Db(sqlx::Error::Configuration(Box::new(source)));

        assert_eq!(error.to_string(), "database migration operation failed");
        assert!(!error.to_string().contains("postgres://"));
    }
}
