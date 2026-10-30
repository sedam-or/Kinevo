use clap::Args;
use kinevo_kernel::secret::Secret;
use sqlx::{
    ConnectOptions,
    postgres::{PgConnectOptions, PgPoolOptions},
};
use std::path::Path;

#[derive(Args, Debug)]
pub struct MigrateArgs {
    /// Return a failure code when migrations are pending without applying them.
    #[arg(long)]
    pub check: bool,
    /// Create the restricted roles from the configured role URLs before migrating.
    #[arg(long)]
    pub bootstrap: bool,
}

pub async fn run(args: MigrateArgs) -> Result<(), &'static str> {
    let sources = crate::serve::migration_sources();
    let migrator = read_secret_setting("KINEVO_MIGRATOR_DATABASE_URL")?;

    if args.bootstrap {
        let admin = read_secret_setting("KINEVO_BOOTSTRAP_DATABASE_URL")?;
        let app = read_secret_setting("KINEVO_DATABASE_URL")?;
        let system = read_secret_setting("KINEVO_SYSTEM_DATABASE_URL")?;
        let (credentials, database_name) =
            kinevo_db::bootstrap::RoleCredentials::from_urls(&migrator, &app, &system)
                .map_err(|_| "database_bootstrap_invalid")?;
        kinevo_db::bootstrap::bootstrap_roles(&admin, &credentials, &database_name)
            .await
            .map_err(|_| "database_bootstrap_failed")?;
    }

    if args.check {
        let options = migrator
            .expose()
            .parse::<PgConnectOptions>()
            .map_err(|_| "migration_status_failed")?
            .disable_statement_logging();
        let pool = PgPoolOptions::new()
            .max_connections(1)
            .connect_with(options)
            .await
            .map_err(|_| "migration_status_failed")?;
        let status = kinevo_db::migrate::status(&pool, &sources)
            .await
            .map_err(|_| "migration_status_failed");
        pool.close().await;
        if status?.up_to_date {
            println!("migrations_current");
            return Ok(());
        }
        return Err("migrations_pending");
    }

    kinevo_db::migrate::run(&migrator, &sources)
        .await
        .map_err(|_| "migration_failed")?;
    println!("migrations_applied");
    Ok(())
}

fn read_secret_setting(name: &'static str) -> Result<Secret<String>, &'static str> {
    let file_name = format!("{name}_FILE");
    let value = if let Ok(value) = std::env::var(name) {
        value
    } else {
        let file = std::env::var_os(&file_name).ok_or("migration_config_missing")?;
        let content =
            std::fs::read_to_string(Path::new(&file)).map_err(|_| "migration_config_unreadable")?;
        content.trim_end_matches(['\r', '\n']).to_owned()
    };
    if value.is_empty() {
        return Err("migration_config_missing");
    }
    Ok(Secret::new(value))
}
