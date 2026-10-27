use std::{
    sync::{
        Arc,
        atomic::{AtomicU64, Ordering},
    },
    time::Duration,
};

use kinevo_kernel::{
    id::{AccountId, IdGen, SeqIdGen, SeqTokenGen},
    secret::Secret,
    time::{FixedClock, Instant},
};
use sqlx::{
    AssertSqlSafe, ConnectOptions, Connection, PgConnection, PgPool,
    postgres::{PgConnectOptions, PgPoolOptions},
};
use testcontainers::{
    ContainerAsync, GenericImage, ImageExt,
    core::{ContainerPort, WaitFor},
    runners::AsyncRunner,
};
use thiserror::Error;
use tokio::sync::OnceCell;

use crate::{
    Db, DbConfig, TokioSleeper,
    bootstrap::{RoleCredentials, bootstrap_roles},
    migrate::{self, Applied, MigrationSource},
};

pub mod isolation;

const TEST_ADMIN_PASSWORD: &str = "postgres-test-secret";
const TEST_MIGRATOR_PASSWORD: &str = "migrator-test-secret";
const TEST_APP_PASSWORD: &str = "app-test-secret";
const TEST_SYSTEM_PASSWORD: &str = "system-test-secret";
const BASE_POOL_MAX: u32 = 4;

static CLUSTER: OnceCell<PgCluster> = OnceCell::const_new();
static NEXT_DATABASE: AtomicU64 = AtomicU64::new(0);

/// Generic setup failure exposed only to test builders.
#[derive(Clone, Copy, Debug, Error, Eq, PartialEq)]
#[error("PostgreSQL test database is unavailable")]
pub struct Unavailable;

/// Shared PostgreSQL server for one test binary.
#[derive(Clone)]
pub struct PgCluster(Arc<PgClusterInner>);

struct PgClusterInner {
    admin_url: Secret<String>,
    admin_pool: PgPool,
    bootstrap_database: String,
    external: bool,
    _container: Option<ContainerAsync<GenericImage>>,
}

impl PgCluster {
    /// Starts or attaches to the test server once per test binary.
    pub async fn get() -> Result<Self, Unavailable> {
        let cluster = CLUSTER.get_or_try_init(Self::start).await?;
        Ok(cluster.clone())
    }

    async fn start() -> Result<Self, Unavailable> {
        let (admin_url, container, external) = match std::env::var("KINEVO_TEST_DATABASE_URL") {
            Ok(url) => (url, None, true),
            Err(std::env::VarError::NotPresent) => {
                let version =
                    std::env::var("KINEVO_TEST_PG_VERSION").unwrap_or_else(|_| "17".to_owned());
                if version.is_empty()
                    || !version
                        .chars()
                        .all(|character| character.is_ascii_digit() || character == '.')
                {
                    return Err(Unavailable);
                }
                let tag = format!("{version}-alpine");
                let image = GenericImage::new("postgres", tag.as_str())
                    .with_exposed_port(ContainerPort::Tcp(5432))
                    .with_wait_for(WaitFor::message_on_either_std(
                        "database system is ready to accept connections",
                    ))
                    .with_env_var("POSTGRES_USER", "postgres")
                    .with_env_var("POSTGRES_PASSWORD", TEST_ADMIN_PASSWORD)
                    .with_env_var("POSTGRES_DB", "postgres");
                let container = image.start().await.map_err(|_| Unavailable)?;
                let host = container.get_host().await.map_err(|_| Unavailable)?;
                let port = container
                    .get_host_port_ipv4(5432)
                    .await
                    .map_err(|_| Unavailable)?;
                (
                    format!("postgres://postgres:{TEST_ADMIN_PASSWORD}@{host}:{port}/postgres"),
                    Some(container),
                    false,
                )
            }
            Err(std::env::VarError::NotUnicode(_)) => return Err(Unavailable),
        };

        let admin_url = Secret::new(admin_url);
        let options = admin_url
            .expose()
            .parse::<PgConnectOptions>()
            .map_err(|_| Unavailable)?
            .disable_statement_logging();
        let admin_pool = PgPoolOptions::new()
            .max_connections(BASE_POOL_MAX)
            .acquire_timeout(Duration::from_secs(2))
            .connect_with(options)
            .await
            .map_err(|_| Unavailable)?;
        let is_superuser: bool =
            sqlx::query_scalar("SELECT rolsuper FROM pg_roles WHERE rolname = current_user")
                .fetch_one(&admin_pool)
                .await
                .map_err(|_| Unavailable)?;
        if !is_superuser {
            return Err(Unavailable);
        }

        let parsed_admin_url = admin_url
            .expose()
            .parse::<PgConnectOptions>()
            .map_err(|_| Unavailable)?;
        let bootstrap_database = parsed_admin_url
            .get_database()
            .map(str::to_owned)
            .ok_or(Unavailable)?;
        let cluster = Self(Arc::new(PgClusterInner {
            admin_url,
            admin_pool,
            bootstrap_database,
            external,
            _container: container,
        }));
        cluster.bootstrap_roles_locked().await?;
        Ok(cluster)
    }

    async fn bootstrap_roles_locked(&self) -> Result<(), Unavailable> {
        let mut lock = PgConnection::connect(self.0.admin_url.expose())
            .await
            .map_err(|_| Unavailable)?;
        sqlx::query(
            "SELECT pg_advisory_lock(hashtext('kinevo-testkit'), hashtext('role-bootstrap'))",
        )
        .execute(&mut lock)
        .await
        .map_err(|_| Unavailable)?;

        let result = self.bootstrap_roles().await;
        let _ = sqlx::query(
            "SELECT pg_advisory_unlock(hashtext('kinevo-testkit'), hashtext('role-bootstrap'))",
        )
        .execute(&mut lock)
        .await;
        result
    }

    async fn bootstrap_roles(&self) -> Result<(), Unavailable> {
        let migrator_url = self.role_url(
            "kinevo_migrator",
            TEST_MIGRATOR_PASSWORD,
            &self.0.bootstrap_database,
        )?;
        let app_url = self.role_url("kinevo_app", TEST_APP_PASSWORD, &self.0.bootstrap_database)?;
        let system_url = self.role_url(
            "kinevo_system",
            TEST_SYSTEM_PASSWORD,
            &self.0.bootstrap_database,
        )?;
        let (credentials, database) =
            RoleCredentials::from_urls(&migrator_url, &app_url, &system_url)
                .map_err(|_| Unavailable)?;
        bootstrap_roles(&self.0.admin_url, &credentials, &database)
            .await
            .map_err(|_| Unavailable)
    }

    fn role_url(
        &self,
        role: &str,
        password: &str,
        database: &str,
    ) -> Result<Secret<String>, Unavailable> {
        let options = self
            .0
            .admin_url
            .expose()
            .parse::<PgConnectOptions>()
            .map_err(|_| Unavailable)?
            .username(role)
            .password(password)
            .database(database);
        Ok(Secret::new(options.to_url_lossy().to_string()))
    }

    /// Reapplies the fixed test roles to verify bootstrap idempotency.
    pub async fn rebootstrap_roles(&self) -> Result<(), Unavailable> {
        self.bootstrap_roles_locked().await
    }
}

/// One fresh database and its role-specific connections.
pub struct TestDb {
    db: Db,
    app_pool: PgPool,
    migrator_pool: PgPool,
    admin_pool: PgPool,
    migrator_url: Secret<String>,
    database_name: String,
    cluster: PgCluster,
    lease: DatabaseLease,
}

impl TestDb {
    /// Creates a disposable database, grants the three roles, and applies migrations.
    pub async fn create(sources: &[MigrationSource]) -> Result<Self, Unavailable> {
        let cluster = PgCluster::get().await?;
        let sequence = NEXT_DATABASE.fetch_add(1, Ordering::Relaxed) + 1;
        let suffix = if cluster.0.external {
            format!("{}_{}", std::process::id(), sequence)
        } else {
            sequence.to_string()
        };
        let database_name = format!("kinevo_t_{suffix}");
        let create_sql: String = sqlx::query_scalar("SELECT format('CREATE DATABASE %I', $1)")
            .bind(&database_name)
            .fetch_one(&cluster.0.admin_pool)
            .await
            .map_err(|_| Unavailable)?;
        sqlx::query(AssertSqlSafe(create_sql))
            .execute(&cluster.0.admin_pool)
            .await
            .map_err(|_| Unavailable)?;
        let lease = DatabaseLease {
            cluster: cluster.clone(),
            database_name: database_name.clone(),
        };

        let grant_sql: String = sqlx::query_scalar(
            "SELECT format('GRANT CONNECT ON DATABASE %I TO kinevo_migrator, kinevo_app, kinevo_system', $1)",
        )
        .bind(&database_name)
        .fetch_one(&cluster.0.admin_pool)
        .await
            .map_err(|_| Unavailable)?;
        sqlx::query(AssertSqlSafe(grant_sql))
            .execute(&cluster.0.admin_pool)
            .await
            .map_err(|_| Unavailable)?;

        let admin_options = cluster
            .0
            .admin_url
            .expose()
            .parse::<PgConnectOptions>()
            .map_err(|_| Unavailable)?
            .database(&database_name)
            .disable_statement_logging();
        let admin_pool = PgPoolOptions::new()
            .max_connections(4)
            .connect_with(admin_options)
            .await
            .map_err(|_| Unavailable)?;
        sqlx::query("GRANT CREATE ON SCHEMA public TO kinevo_migrator")
            .execute(&admin_pool)
            .await
            .map_err(|_| Unavailable)?;
        sqlx::query("GRANT USAGE ON SCHEMA public TO kinevo_app, kinevo_system")
            .execute(&admin_pool)
            .await
            .map_err(|_| Unavailable)?;

        let migrator_url =
            cluster.role_url("kinevo_migrator", TEST_MIGRATOR_PASSWORD, &database_name)?;
        let app_url = cluster.role_url("kinevo_app", TEST_APP_PASSWORD, &database_name)?;
        let system_url = cluster.role_url("kinevo_system", TEST_SYSTEM_PASSWORD, &database_name)?;
        let credentials = RoleCredentials::from_urls(&migrator_url, &app_url, &system_url)
            .map_err(|_| Unavailable)?;
        if credentials.1 != database_name {
            return Err(Unavailable);
        }
        let db_config = DbConfig {
            app_url,
            system_url,
            pool_max: 2,
            retries: 0,
            backoff: Duration::from_millis(1),
        };
        let db = Db::connect(&db_config, &TokioSleeper)
            .await
            .map_err(|_| Unavailable)?;
        let app_pool = db.app_pool_for_health().clone();
        let migrator_options = migrator_url
            .expose()
            .parse::<PgConnectOptions>()
            .map_err(|_| Unavailable)?
            .disable_statement_logging();
        let migrator_pool = PgPoolOptions::new()
            .max_connections(2)
            .connect_with(migrator_options)
            .await
            .map_err(|_| Unavailable)?;

        migrate::run(&migrator_url, sources)
            .await
            .map_err(|_| Unavailable)?;

        Ok(Self {
            db,
            app_pool,
            migrator_pool,
            admin_pool,
            migrator_url,
            database_name,
            cluster,
            lease,
        })
    }

    #[must_use]
    pub fn db(&self) -> &Db {
        &self.db
    }

    #[must_use]
    pub fn admin_pool(&self) -> &PgPool {
        &self.admin_pool
    }

    #[must_use]
    pub fn migrator_pool(&self) -> &PgPool {
        &self.migrator_pool
    }

    #[must_use]
    pub fn database_name(&self) -> &str {
        &self.database_name
    }

    pub async fn run_migrations(
        &self,
        sources: &[MigrationSource],
    ) -> Result<Applied, migrate::MigrateError> {
        migrate::run(&self.migrator_url, sources).await
    }

    pub async fn rebootstrap_roles(&self) -> Result<(), Unavailable> {
        self.cluster.rebootstrap_roles().await
    }

    fn into_test_app(
        self,
        clock: Arc<FixedClock>,
        ids: Arc<SeqIdGen>,
        tokens: Arc<SeqTokenGen>,
    ) -> TestApp {
        let lease = self.lease;
        TestApp {
            db: self.db,
            app_pool: self.app_pool,
            admin_pool: self.admin_pool,
            migrator_pool: self.migrator_pool,
            database_name: self.database_name,
            clock,
            ids,
            tokens,
            cluster: self.cluster,
            _lease: lease,
        }
    }
}

/// Application test context with real database roles and deterministic generators.
pub struct TestApp {
    pub db: Db,
    pub clock: Arc<FixedClock>,
    pub ids: Arc<SeqIdGen>,
    pub tokens: Arc<SeqTokenGen>,
    pub cluster: PgCluster,
    app_pool: PgPool,
    admin_pool: PgPool,
    migrator_pool: PgPool,
    database_name: String,
    _lease: DatabaseLease,
}

impl TestApp {
    #[must_use]
    pub fn builder() -> TestAppBuilder {
        TestAppBuilder::default()
    }

    /// Allocates a deterministic account id; schema tasks add account creation.
    #[must_use]
    pub fn new_account(&self) -> AccountId {
        AccountId::from_uuid(self.ids.new_uuid_v7())
    }

    #[must_use]
    pub fn admin_pool(&self) -> &PgPool {
        &self.admin_pool
    }

    #[must_use]
    pub fn migrator_pool(&self) -> &PgPool {
        &self.migrator_pool
    }

    /// App-role pool for test assertions that need an unset RLS context.
    #[must_use]
    pub fn app_pool_for_test(&self) -> &PgPool {
        &self.app_pool
    }

    #[must_use]
    pub fn database_name(&self) -> &str {
        &self.database_name
    }
}

/// Builder for an isolated app database and injected test dependencies.
#[derive(Default)]
pub struct TestAppBuilder {
    migrations: Vec<MigrationSource>,
    clock: Option<Instant>,
}

impl TestAppBuilder {
    #[must_use]
    pub fn migrations(mut self, sources: &[MigrationSource]) -> Self {
        self.migrations = sources.to_vec();
        self
    }

    #[must_use]
    pub fn clock_at(mut self, instant: Instant) -> Self {
        self.clock = Some(instant);
        self
    }

    pub async fn build(self) -> Result<TestApp, Unavailable> {
        let initial = match self.clock {
            Some(instant) => instant,
            None => Instant::parse_rfc3339("2026-11-02T00:00:00Z").map_err(|_| Unavailable)?,
        };
        let test_db = TestDb::create(&self.migrations).await?;
        let clock = Arc::new(FixedClock::new(initial));
        let ids = Arc::new(SeqIdGen::new(initial.unix_millis()));
        let tokens = Arc::new(SeqTokenGen::new(0));
        Ok(test_db.into_test_app(clock, ids, tokens))
    }
}

/// Deterministic account and row identifiers for one isolation-fixture insert.
#[derive(Clone, Copy, Debug)]
pub struct TestIds {
    pub account_id: AccountId,
    pub row_id: sqlx::types::Uuid,
}

struct DatabaseLease {
    cluster: PgCluster,
    database_name: String,
}

impl Drop for DatabaseLease {
    fn drop(&mut self) {
        let Ok(runtime) = tokio::runtime::Handle::try_current() else {
            return;
        };
        let pool = self.cluster.0.admin_pool.clone();
        let database_name = self.database_name.clone();
        runtime.spawn(async move {
            let statement = sqlx::query_scalar::<_, String>(
                "SELECT format('DROP DATABASE IF EXISTS %I WITH (FORCE)', $1)",
            )
            .bind(database_name)
            .fetch_one(&pool)
            .await;
            if let Ok(statement) = statement {
                let _ = sqlx::query(AssertSqlSafe(statement)).execute(&pool).await;
            }
        });
    }
}

/// Returns from a test locally when PostgreSQL is unavailable and fails in CI.
#[macro_export]
macro_rules! require_db {
    ($app:ident = $builder:expr) => {
        let $app = match $builder {
            Ok(app) => app,
            Err(error) => {
                if std::env::var("CI").is_ok_and(|value| value == "true") {
                    panic!("database is required in CI: {error}");
                }
                eprintln!("skipped: no database");
                return;
            }
        };
    };
}
