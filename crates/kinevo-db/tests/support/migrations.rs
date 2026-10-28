//! Combined identity and platform migrations for database integration tests.

use kinevo_db::migrate::MigrationSource;
use sqlx::migrate::Migrator;

static IDENTITY_MIGRATOR: Migrator = sqlx::migrate!("../kinevo-mod-identity/migrations");
static IDENTITY_SOURCE: MigrationSource = MigrationSource {
    crate_name: "kinevo-mod-identity",
    module: "identity",
    migrator: &IDENTITY_MIGRATOR,
};

pub fn migration_sources() -> [MigrationSource; 2] {
    [IDENTITY_SOURCE, kinevo_db::migrations_source()]
}
