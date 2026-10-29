//! System jobs for identity retention.

use std::sync::Arc;

use async_trait::async_trait;
use kinevo_db::{
    SystemTx, Tx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    jobs::{ClaimedJob, JobContext, JobHandler, JobKind},
};
use kinevo_kernel::{
    errors::{AppError, Details, ErrorCode},
    id::AccountId,
    time::Instant,
};
use sqlx::Row;

use crate::{
    app::export::ExportService,
    infra::{retention, sessions},
};

/// Purges an account only after confirming its persisted status is `deleting`.
pub struct AccountPurge;

#[async_trait]
impl JobHandler for AccountPurge {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("account.purge")
    }

    async fn run(&self, ctx: JobContext, job: ClaimedJob) -> Result<(), AppError> {
        let Some(account) = job.account_id else {
            return Ok(());
        };
        let now = ctx.clock.now();
        let mut tx = Tx::begin(&ctx.db, account).await?;
        let row = sqlx::query("SELECT status FROM public.accounts WHERE id=$1 FOR UPDATE")
            .bind(*account.as_uuid())
            .fetch_optional(tx.conn())
            .await
            .map_err(service_unavailable)?;
        let Some(row) = row else {
            tx.commit().await?;
            return Ok(());
        };
        let status = row
            .try_get::<String, _>("status")
            .map_err(service_unavailable)?;
        if status != "deleting" {
            tx.commit().await?;
            return Ok(());
        }

        AuditWriter::new(ctx.ids.clone())
            .record(
                &mut tx,
                AuditEntry {
                    account_id: Some(account),
                    actor_id: None,
                    action: AuditAction::AccountDeleted,
                    target_type: Some(AuditTarget::ACCOUNT),
                    target_id: Some(account.to_string()),
                    details: Details::new(),
                    request_id: None,
                    occurred_at: now,
                },
            )
            .await?;
        let deleted = sqlx::query("DELETE FROM public.accounts WHERE id=$1 AND status='deleting'")
            .bind(*account.as_uuid())
            .execute(tx.conn())
            .await
            .map_err(service_unavailable)?
            .rows_affected();
        if deleted != 1 {
            return Err(AppError::new(ErrorCode::ServiceUnavailable));
        }
        tx.commit().await
    }
}

/// Builds an account export with the injectible exporter list owned by composition.
pub struct AccountExportBuild {
    service: Arc<ExportService>,
}

impl AccountExportBuild {
    #[must_use]
    pub fn new(service: Arc<ExportService>) -> Self {
        Self { service }
    }
}

#[async_trait]
impl JobHandler for AccountExportBuild {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("account.export.build")
    }

    async fn run(&self, _ctx: JobContext, job: ClaimedJob) -> Result<(), AppError> {
        let account = job
            .account_id
            .or_else(|| parse_account_id(&job.payload))
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let export_id = job
            .payload
            .get("export_id")
            .and_then(serde_json::Value::as_str)
            .and_then(|value| sqlx::types::Uuid::parse_str(value).ok())
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        self.service.build_export(account, export_id).await
    }
}

/// Expires and deletes one account's archive without granting the system role table access.
pub struct ExportsPrune;

#[async_trait]
impl JobHandler for ExportsPrune {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("identity.exports.prune")
    }

    async fn run(&self, ctx: JobContext, job: ClaimedJob) -> Result<(), AppError> {
        let Some(account) = job.account_id.or_else(|| parse_account_id(&job.payload)) else {
            return Ok(());
        };
        let Some(id) = job
            .payload
            .get("export_id")
            .and_then(serde_json::Value::as_str)
            .and_then(|value| sqlx::types::Uuid::parse_str(value).ok())
        else {
            return Err(AppError::new(ErrorCode::InternalError));
        };
        let now = ctx.clock.now();
        let mut tx = Tx::begin(&ctx.db, account).await?;
        sqlx::query(
            "UPDATE public.account_exports SET state='expired', bytes=NULL \
             WHERE id=$1 AND account_id=$2 AND state='ready' AND expires_at <= $3::timestamptz",
        )
        .bind(id)
        .bind(*account.as_uuid())
        .bind(now.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(service_unavailable)?;
        tx.commit().await
    }
}

fn parse_account_id(payload: &serde_json::Value) -> Option<AccountId> {
    payload
        .get("account_id")
        .and_then(serde_json::Value::as_str)
        .and_then(|value| sqlx::types::Uuid::parse_str(value).ok())
        .map(AccountId::from_uuid)
}

const RETENTION_SECONDS: i64 = 7 * 24 * 60 * 60;

/// Removes refresh families and their token rows beyond the retention horizon.
pub struct SessionsPrune;

#[async_trait]
impl JobHandler for SessionsPrune {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("identity.sessions.prune")
    }

    async fn run(&self, ctx: JobContext, _job: ClaimedJob) -> Result<(), AppError> {
        let now = ctx.clock.now();
        let cutoff = retention_cutoff(now)?;
        let mut tx = SystemTx::begin(&ctx.db).await?;
        sessions::prune_expired(&mut tx, now).await?;
        retention::prune_sessions(&mut tx, cutoff).await?;
        tx.commit().await
    }
}

/// Removes expired or used password-reset rows beyond the retention horizon.
pub struct ResetsPrune;

#[async_trait]
impl JobHandler for ResetsPrune {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("identity.resets.prune")
    }

    async fn run(&self, ctx: JobContext, _job: ClaimedJob) -> Result<(), AppError> {
        let cutoff = retention_cutoff(ctx.clock.now())?;
        let mut tx = SystemTx::begin(&ctx.db).await?;
        retention::prune_resets(&mut tx, cutoff).await?;
        tx.commit().await
    }
}

/// Creates the fixed seven-day retention cutoff from the injected clock.
fn retention_cutoff(now: Instant) -> Result<Instant, AppError> {
    now.checked_add_seconds(-RETENTION_SECONDS)
        .ok_or_else(|| AppError::new(ErrorCode::InternalError))
}

fn service_unavailable(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}

/// Returns the identity module's system retention handlers.
#[must_use]
pub fn handlers() -> Vec<Arc<dyn JobHandler>> {
    vec![
        Arc::new(AccountPurge),
        Arc::new(ExportsPrune),
        Arc::new(SessionsPrune),
        Arc::new(ResetsPrune),
    ]
}

/// Returns identity handlers plus the exporter-aware build job.
#[must_use]
pub fn handlers_with_exports(service: Arc<ExportService>) -> Vec<Arc<dyn JobHandler>> {
    let mut handlers = handlers();
    handlers.push(Arc::new(AccountExportBuild::new(service)));
    handlers
}
