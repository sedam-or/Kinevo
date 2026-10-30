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
use thiserror::Error;
use tokio::sync::OnceCell;

use crate::{
    Db, DbConfig, TokioSleeper,
    bootstrap::{RoleCredentials, bootstrap_roles},
    migrate::{self, Applied, MigrationSource},
};

pub mod isolation;

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

/// Shared PostgreSQL server configured through `KINEVO_TEST_DATABASE_URL`.
#[derive(Clone)]
pub struct PgCluster(Arc<PgClusterInner>);

struct PgClusterInner {
    admin_url: Secret<String>,
    admin_pool: PgPool,
    bootstrap_database: String,
}

impl PgCluster {
    /// Attaches to the shared test server once per test binary.
    pub async fn get() -> Result<Self, Unavailable> {
        let cluster = CLUSTER.get_or_try_init(Self::start).await?;
        Ok(cluster.clone())
    }

    async fn start() -> Result<Self, Unavailable> {
        let admin_url =
            Secret::new(std::env::var("KINEVO_TEST_DATABASE_URL").map_err(|_| Unavailable)?);
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
    app_url: Secret<String>,
    system_url: Secret<String>,
    database_name: String,
    cluster: PgCluster,
    lease: DatabaseLease,
}

impl TestDb {
    /// Creates a disposable database, grants the three roles, and applies migrations.
    #[allow(
        clippy::too_many_lines,
        reason = "database setup is intentionally sequential so each privilege grant precedes connection"
    )]
    pub async fn create(sources: &[MigrationSource]) -> Result<Self, Unavailable> {
        let cluster = PgCluster::get().await?;
        let sequence = NEXT_DATABASE.fetch_add(1, Ordering::Relaxed) + 1;
        let database_name = format!("kinevo_t_{}_{}", std::process::id(), sequence);
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
            admin_url: Secret::new(cluster.0.admin_url.expose().to_owned()),
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
        let grant_migrator_create: String = sqlx::query_scalar(
            "SELECT format('GRANT CREATE ON DATABASE %I TO kinevo_migrator', $1)",
        )
        .bind(&database_name)
        .fetch_one(&cluster.0.admin_pool)
        .await
        .map_err(|_| Unavailable)?;
        sqlx::query(AssertSqlSafe(grant_migrator_create))
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
        let app_url = Secret::new(db_config.app_url.expose().to_owned());
        let system_url = Secret::new(db_config.system_url.expose().to_owned());
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
            app_url,
            system_url,
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

    /// Returns the synthetic app-role URL for tests that exercise the application composition root.
    #[must_use]
    pub fn app_database_url(&self) -> &str {
        self.app_url.expose()
    }

    /// Returns the synthetic system-role URL for tests that exercise the application composition root.
    #[must_use]
    pub fn system_database_url(&self) -> &str {
        self.system_url.expose()
    }

    /// Returns the synthetic migrator-role URL for tests that exercise startup migrations.
    #[must_use]
    pub fn migrator_database_url(&self) -> &str {
        self.migrator_url.expose()
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
            app_url: self.app_url,
            system_url: self.system_url,
            migrator_url: self.migrator_url,
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
    app_url: Secret<String>,
    system_url: Secret<String>,
    migrator_url: Secret<String>,
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

    /// Returns the synthetic app-role URL for tests that exercise the application composition root.
    #[must_use]
    pub fn app_database_url(&self) -> &str {
        self.app_url.expose()
    }

    /// Returns the synthetic system-role URL for tests that exercise the application composition root.
    #[must_use]
    pub fn system_database_url(&self) -> &str {
        self.system_url.expose()
    }

    /// Returns the synthetic migrator-role URL for tests that exercise startup migrations.
    #[must_use]
    pub fn migrator_database_url(&self) -> &str {
        self.migrator_url.expose()
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
    admin_url: Secret<String>,
    database_name: String,
}

impl Drop for DatabaseLease {
    #[allow(
        clippy::panic,
        reason = "a test must fail when its disposable database cannot be removed"
    )]
    fn drop(&mut self) {
        let admin_url = Secret::new(self.admin_url.expose().to_owned());
        let database_name = self.database_name.clone();
        let cleanup = std::thread::Builder::new()
            .name("kinevo-test-db-cleanup".to_owned())
            .spawn(move || {
                let Ok(runtime) = tokio::runtime::Builder::new_current_thread()
                    .enable_all()
                    .build()
                else {
                    return false;
                };
                runtime.block_on(async move {
                    let Ok(options) = admin_url
                        .expose()
                        .parse::<PgConnectOptions>()
                        .map(PgConnectOptions::disable_statement_logging)
                    else {
                        return false;
                    };
                    let Ok(pool) = PgPoolOptions::new()
                        .max_connections(1)
                        .connect_with(options)
                        .await
                    else {
                        return false;
                    };
                    let result = async {
                        let statement = sqlx::query_scalar::<_, String>(
                            "SELECT format('DROP DATABASE IF EXISTS %I WITH (FORCE)', $1)",
                        )
                        .bind(database_name)
                        .fetch_one(&pool)
                        .await?;
                        sqlx::query(AssertSqlSafe(statement)).execute(&pool).await?;
                        Ok::<(), sqlx::Error>(())
                    }
                    .await;
                    pool.close().await;
                    result.is_ok()
                })
            });
        let cleanup_succeeded = cleanup.is_ok_and(|thread| thread.join().unwrap_or(false));
        if !cleanup_succeeded {
            if std::thread::panicking() {
                eprintln!("test database cleanup failed while unwinding");
            } else {
                panic!("test database cleanup failed");
            }
        }
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
