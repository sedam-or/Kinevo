use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
};
use sqlx::{PgConnection, Postgres, Transaction};

/// A tenant-scoped application transaction.
pub struct Tx {
    account: AccountId,
    inner: Transaction<'static, Postgres>,
}

impl Tx {
    /// Begins a transaction and binds its account RLS context before returning it.
    pub async fn begin(db: &super::pool::Db, account: AccountId) -> Result<Self, AppError> {
        let mut inner = db.app_pool().begin().await.map_err(service_unavailable)?;
        sqlx::query("SELECT set_config($1, $2, true)")
            .bind("app.account_id")
            .bind(account.to_string())
            .execute(&mut *inner)
            .await
            .map_err(service_unavailable)?;
        Ok(Self { account, inner })
    }

    /// Begins an administrator transaction after the caller verifies permission.
    ///
    /// The caller must load and verify `is_instance_admin` before invoking this method.
    pub async fn begin_admin(db: &super::pool::Db, account: AccountId) -> Result<Self, AppError> {
        let mut inner = db.app_pool().begin().await.map_err(service_unavailable)?;
        sqlx::query("SELECT set_config($1, $2, true), set_config($3, $4, true)")
            .bind("app.account_id")
            .bind(account.to_string())
            .bind("app.instance_admin")
            .bind("on")
            .execute(&mut *inner)
            .await
            .map_err(service_unavailable)?;
        Ok(Self { account, inner })
    }

    /// Returns the transaction's bound account.
    #[must_use]
    pub fn account_id(&self) -> AccountId {
        self.account
    }

    /// Provides the scoped connection to repositories.
    pub fn conn(&mut self) -> &mut PgConnection {
        &mut self.inner
    }

    /// Commits this transaction.
    pub async fn commit(self) -> Result<(), AppError> {
        self.inner.commit().await.map_err(service_unavailable)
    }

    /// Rolls back this transaction.
    pub async fn rollback(self) -> Result<(), AppError> {
        self.inner.rollback().await.map_err(service_unavailable)
    }
}

/// A restricted system transaction that never sets tenant context.
pub struct SystemTx {
    inner: Transaction<'static, Postgres>,
}

impl SystemTx {
    /// Begins a transaction using the system role.
    pub async fn begin(db: &super::pool::Db) -> Result<Self, AppError> {
        let inner = db
            .system_pool()
            .begin()
            .await
            .map_err(service_unavailable)?;
        Ok(Self { inner })
    }

    /// Provides the restricted connection to system repositories.
    pub fn conn(&mut self) -> &mut PgConnection {
        &mut self.inner
    }

    /// Commits this transaction.
    pub async fn commit(self) -> Result<(), AppError> {
        self.inner.commit().await.map_err(service_unavailable)
    }

    /// Rolls back this transaction.
    pub async fn rollback(self) -> Result<(), AppError> {
        self.inner.rollback().await.map_err(service_unavailable)
    }
}

fn service_unavailable(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
