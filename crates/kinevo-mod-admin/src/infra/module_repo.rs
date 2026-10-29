//! Restricted reads and transactional writes for instance module state.

use std::collections::BTreeMap;

use kinevo_db::{Db, SystemTx, Tx};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    time::Instant,
};
use sqlx::Row;

/// The stored switch state and optimistic-lock version for one module.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub(crate) struct ModuleRow {
    pub enabled: bool,
    pub version: i64,
}

/// Instance settings fields exposed by the authenticated summary endpoint.
#[derive(Clone, Debug, Eq, PartialEq)]
pub(crate) struct InstanceRow {
    pub version: i64,
    pub setup_completed: bool,
    pub base_url: Option<String>,
    pub default_locale: String,
    pub template_id: Option<String>,
}

/// Versioned switch data persisted as one module row.
pub(crate) struct ModuleSwitchUpdate {
    pub key: &'static str,
    pub enabled: bool,
    pub expected_version: i64,
    pub next_version: i64,
    pub actor: kinevo_kernel::id::AccountId,
    pub now: Instant,
}

/// PostgreSQL repository for instance module switches and settings.
pub struct ModuleRepository {
    db: Db,
}

impl ModuleRepository {
    /// Uses the application's restricted database pools.
    #[must_use]
    pub fn new(db: Db) -> Self {
        Self { db }
    }

    /// Loads the instance-wide module switch rows using the restricted system role.
    pub(crate) async fn load_rows(&self) -> Result<BTreeMap<String, ModuleRow>, AppError> {
        let mut tx = SystemTx::begin(&self.db).await?;
        let rows = Self::select_rows(tx.conn()).await?;
        tx.commit().await?;
        Ok(rows)
    }

    /// Locks the singleton instance settings row to serialize switch plans.
    pub(crate) async fn lock_instance(&self, tx: &mut Tx) -> Result<(), AppError> {
        sqlx::query("SELECT id FROM public.instance_settings WHERE id=1 FOR UPDATE")
            .fetch_one(tx.conn())
            .await
            .map_err(database_error)?;
        Ok(())
    }

    /// Reads the current switch rows inside the caller's locked write transaction.
    pub(crate) async fn load_rows_tx(
        &self,
        tx: &mut Tx,
    ) -> Result<BTreeMap<String, ModuleRow>, AppError> {
        Self::select_rows(tx.conn()).await
    }

    /// Inserts the first row or advances an existing row version.
    pub(crate) async fn save(
        &self,
        tx: &mut Tx,
        update: ModuleSwitchUpdate,
    ) -> Result<(), AppError> {
        let result = if update.expected_version == 0 {
            sqlx::query(
                "INSERT INTO public.instance_modules \
                 (module_key, enabled, updated_at, updated_by, version) \
                 VALUES ($1, $2, $3::timestamptz, $4, $5) ON CONFLICT (module_key) DO NOTHING",
            )
            .bind(update.key)
            .bind(update.enabled)
            .bind(update.now.to_rfc3339())
            .bind(*update.actor.as_uuid())
            .bind(update.next_version)
            .execute(tx.conn())
            .await
            .map_err(database_error)?
        } else {
            sqlx::query(
                "UPDATE public.instance_modules \
                 SET enabled=$1, updated_at=$2::timestamptz, updated_by=$3, version=$4 \
                 WHERE module_key=$5 AND version=$6",
            )
            .bind(update.enabled)
            .bind(update.now.to_rfc3339())
            .bind(*update.actor.as_uuid())
            .bind(update.next_version)
            .bind(update.key)
            .bind(update.expected_version)
            .execute(tx.conn())
            .await
            .map_err(database_error)?
        };
        if result.rows_affected() != 1 {
            return Err(AppError::new(ErrorCode::VersionConflict));
        }
        Ok(())
    }

    /// Reads the singleton instance settings row through the restricted system role.
    pub(crate) async fn load_instance(&self) -> Result<InstanceRow, AppError> {
        let mut tx = SystemTx::begin(&self.db).await?;
        let row = sqlx::query(
            "SELECT version, setup_completed, base_url, default_locale, template_id \
             FROM public.instance_settings WHERE id=1",
        )
        .fetch_one(tx.conn())
        .await
        .map_err(database_error)?;
        let instance = InstanceRow {
            version: row.try_get("version").map_err(database_error)?,
            setup_completed: row.try_get("setup_completed").map_err(database_error)?,
            base_url: row.try_get("base_url").map_err(database_error)?,
            default_locale: row.try_get("default_locale").map_err(database_error)?,
            template_id: row.try_get("template_id").map_err(database_error)?,
        };
        tx.commit().await?;
        Ok(instance)
    }

    async fn select_rows(
        conn: &mut sqlx::PgConnection,
    ) -> Result<BTreeMap<String, ModuleRow>, AppError> {
        let rows = sqlx::query(
            "SELECT module_key, enabled, version FROM public.instance_modules ORDER BY module_key",
        )
        .fetch_all(conn)
        .await
        .map_err(database_error)?;
        let mut result = BTreeMap::new();
        for row in rows {
            result.insert(
                row.try_get("module_key").map_err(database_error)?,
                ModuleRow {
                    enabled: row.try_get("enabled").map_err(database_error)?,
                    version: row.try_get("version").map_err(database_error)?,
                },
            );
        }
        Ok(result)
    }
}

fn database_error(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
