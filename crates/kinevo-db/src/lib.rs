//! PostgreSQL infrastructure for Nivaras.

#![forbid(unsafe_code)]

pub mod bootstrap;
pub mod migrate;

static PLATFORM_MIGRATOR: sqlx::migrate::Migrator = sqlx::migrate!("./migrations");

/// Returns this crate's platform migration source.
#[must_use]
pub fn migrations_source() -> migrate::MigrationSource {
    migrate::MigrationSource {
        crate_name: "kinevo-db",
        module: "platform",
        migrator: &PLATFORM_MIGRATOR,
    }
}
