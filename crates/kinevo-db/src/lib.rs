//! PostgreSQL infrastructure for Nivaras.

#![forbid(unsafe_code)]

pub mod bootstrap;
pub mod errors;
pub mod isolation;
pub mod migrate;
pub mod pool;
pub mod tx;

#[cfg(feature = "testing")]
pub mod testing;

pub use pool::{ConnectError, Db, DbConfig, Sleeper, TokioSleeper};
pub use tx::{SystemTx, Tx};

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
