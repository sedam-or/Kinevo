use kinevo_kernel::secret::Secret;
use sqlx::{PgPool, postgres::PgPoolOptions};
use std::{future::Future, pin::Pin, time::Duration};
use thiserror::Error;

/// A configured delay provider for connection retries.
pub trait Sleeper: Send + Sync {
    fn sleep<'a>(&'a self, delay: Duration) -> Pin<Box<dyn Future<Output = ()> + Send + 'a>>;
}

/// Tokio-backed sleeper for production connection setup.
pub struct TokioSleeper;

impl Sleeper for TokioSleeper {
    fn sleep<'a>(&'a self, delay: Duration) -> Pin<Box<dyn Future<Output = ()> + Send + 'a>> {
        Box::pin(tokio::time::sleep(delay))
    }
}

/// Database connection settings. URLs are held in redacting secret wrappers.
pub struct DbConfig {
    pub app_url: Secret<String>,
    pub system_url: Secret<String>,
    pub pool_max: u32,
    /// Number of retries after the initial connection attempt.
    pub retries: u32,
    /// Initial delay before retrying a failed connection.
    pub backoff: Duration,
}

/// Errors raised while creating database pools.
#[derive(Debug, Error, Eq, PartialEq)]
pub enum ConnectError {
    #[error("database connection failed after {attempts} attempts")]
    AttemptsExhausted { attempts: u32 },
}

/// The application and restricted system connection pools.
pub struct Db {
    app: PgPool,
    system: PgPool,
}

impl Db {
    /// Opens both restricted pools, retrying with injected exponential backoff.
    pub async fn connect(cfg: &DbConfig, sleeper: &dyn Sleeper) -> Result<Self, ConnectError> {
        let mut delay = cfg.backoff;
        let mut attempts = 0;

        for retry in 0..=cfg.retries {
            attempts = retry.saturating_add(1);
            let app = PgPoolOptions::new()
                .max_connections(cfg.pool_max)
                .connect(cfg.app_url.expose())
                .await;
            if let Ok(app) = app {
                let system = PgPoolOptions::new()
                    .max_connections(cfg.pool_max)
                    .connect(cfg.system_url.expose())
                    .await;
                if let Ok(system) = system {
                    return Ok(Self { app, system });
                }
                app.close().await;
            }

            if retry < cfg.retries {
                sleeper.sleep(delay).await;
                delay = delay.saturating_mul(2);
            }
        }

        Err(ConnectError::AttemptsExhausted { attempts })
    }

    /// Pool for `SELECT 1` readiness probes only.
    #[must_use]
    pub fn app_pool_for_health(&self) -> &PgPool {
        &self.app
    }

    pub(crate) fn app_pool(&self) -> &PgPool {
        &self.app
    }

    pub(crate) fn system_pool(&self) -> &PgPool {
        &self.system
    }
}

#[cfg(test)]
mod tests {
    use super::{ConnectError, Db, DbConfig, Sleeper};
    use kinevo_kernel::secret::Secret;
    use std::{
        sync::{Arc, Mutex},
        time::Duration,
    };

    struct RecordingSleeper(Arc<Mutex<Vec<Duration>>>);

    impl Sleeper for RecordingSleeper {
        fn sleep<'a>(
            &'a self,
            delay: Duration,
        ) -> std::pin::Pin<Box<dyn std::future::Future<Output = ()> + Send + 'a>> {
            let delays = Arc::clone(&self.0);
            Box::pin(async move {
                let mut recorded = delays
                    .lock()
                    .unwrap_or_else(std::sync::PoisonError::into_inner);
                recorded.push(delay);
            })
        }
    }

    #[tokio::test]
    async fn connect_retries_with_backoff_and_hides_url() {
        let delays = Arc::new(Mutex::new(Vec::new()));
        let sleeper = RecordingSleeper(Arc::clone(&delays));
        let url = String::from(
            "postgres://synthetic:synthetic-secret@127.0.0.1/not-a-db?port=not-a-port",
        );
        let config = DbConfig {
            app_url: Secret::new(url.clone()),
            system_url: Secret::new(url.clone()),
            pool_max: 1,
            retries: 3,
            backoff: Duration::from_millis(2),
        };

        let result = Db::connect(&config, &sleeper).await;
        assert!(matches!(
            result,
            Err(ConnectError::AttemptsExhausted { attempts: 4 })
        ));
        let message = result.err().map(|error| error.to_string());
        assert!(
            message
                .as_ref()
                .is_some_and(|message| !message.contains(&url))
        );
        assert!(
            message
                .as_ref()
                .is_some_and(|message| !message.contains("synthetic-secret"))
        );
        let actual = delays
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner);
        assert_eq!(
            actual.as_slice(),
            [
                Duration::from_millis(2),
                Duration::from_millis(4),
                Duration::from_millis(8),
            ]
        );
    }
}
