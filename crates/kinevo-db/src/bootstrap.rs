use kinevo_kernel::secret::Secret;
use sqlx::postgres::{PgConnectOptions, PgPool, PgPoolOptions};
use sqlx::{AssertSqlSafe, ConnectOptions};
use thiserror::Error;

/// Redacted role passwords collected from the three configured database URLs.
#[derive(Debug)]
pub struct RoleCredentials {
    migrator: Secret<String>,
    app: Secret<String>,
    system: Secret<String>,
}

impl RoleCredentials {
    /// Reads role passwords from their URLs and returns the application database name.
    pub fn from_urls(
        migrator_url: &Secret<String>,
        app_url: &Secret<String>,
        system_url: &Secret<String>,
    ) -> Result<(Self, String), BootstrapError> {
        let (migrator_password, _) = parse_role_url(migrator_url.expose(), "kinevo_migrator")?;
        let (app_password, database_name) = parse_role_url(app_url.expose(), "kinevo_app")?;
        let (system_password, _) = parse_role_url(system_url.expose(), "kinevo_system")?;
        let database_name = database_name.ok_or(BootstrapError::MissingDatabase)?;

        Ok((
            Self {
                migrator: migrator_password,
                app: app_password,
                system: system_password,
            },
            database_name,
        ))
    }
}

/// Errors raised while bootstrapping database roles.
#[derive(Debug, Error)]
pub enum BootstrapError {
    #[error("role database URL is invalid")]
    InvalidRoleUrl,
    #[error("role database URL does not use the expected role")]
    WrongRole,
    #[error("role database URL is missing a password")]
    MissingPassword,
    #[error("application database URL is missing a database name")]
    MissingDatabase,
    #[error("database role bootstrap failed")]
    Db(#[source] sqlx::Error),
}

/// Creates or updates the three restricted roles and their minimum database grants.
pub async fn bootstrap_roles(
    admin_url: &Secret<String>,
    credentials: &RoleCredentials,
    database_name: &str,
) -> Result<(), BootstrapError> {
    let admin_options = admin_url
        .expose()
        .parse::<PgConnectOptions>()
        .map_err(|_| BootstrapError::InvalidRoleUrl)?
        .disable_statement_logging();
    let admin_pool = PgPoolOptions::new()
        .max_connections(1)
        .connect_with(admin_options.clone())
        .await
        .map_err(BootstrapError::Db)?;

    for (role, password) in [
        ("kinevo_migrator", &credentials.migrator),
        ("kinevo_app", &credentials.app),
        ("kinevo_system", &credentials.system),
    ] {
        upsert_role(&admin_pool, role, password).await?;
        grant_connect(&admin_pool, database_name, role).await?;
    }
    grant_database_create(&admin_pool, database_name).await?;

    let target_admin = PgPoolOptions::new()
        .max_connections(1)
        .connect_with(admin_options.clone().database(database_name))
        .await
        .map_err(BootstrapError::Db)?;
    sqlx::query("GRANT CREATE ON SCHEMA public TO kinevo_migrator")
        .execute(&target_admin)
        .await
        .map_err(BootstrapError::Db)?;
    target_admin.close().await;

    let migrator_options = admin_options
        .username("kinevo_migrator")
        .password(credentials.migrator.expose())
        .database(database_name);
    let migrator_pool = PgPoolOptions::new()
        .max_connections(1)
        .connect_with(migrator_options)
        .await
        .map_err(BootstrapError::Db)?;
    sqlx::query("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM PUBLIC")
        .execute(&migrator_pool)
        .await
        .map_err(BootstrapError::Db)?;
    migrator_pool.close().await;
    admin_pool.close().await;
    Ok(())
}

async fn upsert_role(
    pool: &PgPool,
    role: &str,
    password: &Secret<String>,
) -> Result<(), BootstrapError> {
    let exists: bool =
        sqlx::query_scalar("SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = $1)")
            .bind(role)
            .fetch_one(pool)
            .await
            .map_err(BootstrapError::Db)?;
    let format_query = if exists {
        "SELECT format('ALTER ROLE %I WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %L', $1, $2)"
    } else {
        "SELECT format('CREATE ROLE %I WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %L', $1, $2)"
    };
    let statement: String = sqlx::query_scalar(format_query)
        .bind(role)
        .bind(password.expose())
        .fetch_one(pool)
        .await
        .map_err(BootstrapError::Db)?;
    sqlx::query(AssertSqlSafe(statement))
        .execute(pool)
        .await
        .map_err(BootstrapError::Db)?;
    Ok(())
}

async fn grant_connect(
    pool: &PgPool,
    database_name: &str,
    role: &str,
) -> Result<(), BootstrapError> {
    let statement: String =
        sqlx::query_scalar("SELECT format('GRANT CONNECT ON DATABASE %I TO %I', $1, $2)")
            .bind(database_name)
            .bind(role)
            .fetch_one(pool)
            .await
            .map_err(BootstrapError::Db)?;
    sqlx::query(AssertSqlSafe(statement))
        .execute(pool)
        .await
        .map_err(BootstrapError::Db)?;
    Ok(())
}

async fn grant_database_create(pool: &PgPool, database_name: &str) -> Result<(), BootstrapError> {
    let statement: String =
        sqlx::query_scalar("SELECT format('GRANT CREATE ON DATABASE %I TO %I', $1, $2)")
            .bind(database_name)
            .bind("kinevo_migrator")
            .fetch_one(pool)
            .await
            .map_err(BootstrapError::Db)?;
    sqlx::query(AssertSqlSafe(statement))
        .execute(pool)
        .await
        .map_err(BootstrapError::Db)?;
    Ok(())
}

fn parse_role_url(
    raw_url: &str,
    expected_role: &'static str,
) -> Result<(Secret<String>, Option<String>), BootstrapError> {
    raw_url
        .parse::<PgConnectOptions>()
        .map_err(|_| BootstrapError::InvalidRoleUrl)?;
    let Some((scheme, remainder)) = raw_url.split_once("://") else {
        return Err(BootstrapError::InvalidRoleUrl);
    };
    if scheme != "postgres" && scheme != "postgresql" {
        return Err(BootstrapError::InvalidRoleUrl);
    }
    let mut url_parts = remainder.splitn(2, '/');
    let Some(authority_with_query) = url_parts.next() else {
        return Err(BootstrapError::InvalidRoleUrl);
    };
    let Some(authority) = authority_with_query.split(['?', '#']).next() else {
        return Err(BootstrapError::InvalidRoleUrl);
    };
    let Some((userinfo, _host)) = authority.rsplit_once('@') else {
        return Err(BootstrapError::InvalidRoleUrl);
    };
    let Some((username, encoded_password)) = userinfo.split_once(':') else {
        return Err(BootstrapError::MissingPassword);
    };
    let username = percent_decode(username)?;
    if username != expected_role {
        return Err(BootstrapError::WrongRole);
    }
    let password = percent_decode(encoded_password)?;
    let database_name = url_parts
        .next()
        .map(|path| path.split(['?', '#']).next().unwrap_or_default())
        .filter(|path| !path.is_empty())
        .map(percent_decode)
        .transpose()?;
    Ok((Secret::new(password), database_name))
}

fn percent_decode(value: &str) -> Result<String, BootstrapError> {
    let mut bytes = Vec::with_capacity(value.len());
    let mut encoded = value.bytes();
    while let Some(byte) = encoded.next() {
        if byte == b'%' {
            let Some(high) = encoded.next().and_then(hex_value) else {
                return Err(BootstrapError::InvalidRoleUrl);
            };
            let Some(low) = encoded.next().and_then(hex_value) else {
                return Err(BootstrapError::InvalidRoleUrl);
            };
            bytes.push((high << 4) | low);
        } else {
            bytes.push(byte);
        }
    }
    String::from_utf8(bytes).map_err(|_| BootstrapError::InvalidRoleUrl)
}

fn hex_value(byte: u8) -> Option<u8> {
    match byte {
        b'0'..=b'9' => Some(byte - b'0'),
        b'a'..=b'f' => Some(byte - b'a' + 10),
        b'A'..=b'F' => Some(byte - b'A' + 10),
        _ => None,
    }
}

#[cfg(test)]
mod tests {
    use super::RoleCredentials;
    use kinevo_kernel::secret::Secret;

    #[test]
    fn d_50_credentials_from_urls() {
        let migrator =
            Secret::new("postgres://kinevo_migrator:p%40ss%3Aword@localhost/migrate_db".to_owned());
        let app = Secret::new("postgres://kinevo_app:app-secret@localhost/kinevo_db".to_owned());
        let system =
            Secret::new("postgres://kinevo_system:system-secret@localhost/kinevo_db".to_owned());

        let result = RoleCredentials::from_urls(&migrator, &app, &system);
        assert!(result.is_ok(), "valid role URLs should be accepted");
        let Ok((credentials, database_name)) = result else {
            return;
        };
        assert_eq!(credentials.migrator.expose(), "p@ss:word");
        assert_eq!(credentials.app.expose(), "app-secret");
        assert_eq!(credentials.system.expose(), "system-secret");
        assert_eq!(database_name, "kinevo_db");

        let wrong_user = Secret::new("postgres://wrong:secret@localhost/kinevo_db".to_owned());
        let wrong = RoleCredentials::from_urls(&wrong_user, &app, &system);
        assert!(wrong.is_err());
        let wrong_error = wrong.err();
        assert!(wrong_error.is_some());
        let Some(wrong_error) = wrong_error else {
            return;
        };
        assert!(!wrong_error.to_string().contains("wrong:secret@localhost"));

        let missing_password =
            Secret::new("postgres://kinevo_migrator@localhost/kinevo_db".to_owned());
        let missing = RoleCredentials::from_urls(&missing_password, &app, &system);
        assert!(missing.is_err());
        let missing_error = missing.err();
        assert!(missing_error.is_some());
        let Some(missing_error) = missing_error else {
            return;
        };
        assert!(
            !missing_error
                .to_string()
                .contains("kinevo_migrator@localhost")
        );
    }
}
