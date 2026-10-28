//! System jobs for identity retention.

use std::sync::Arc;

use async_trait::async_trait;
use kinevo_db::{
    SystemTx,
    jobs::{ClaimedJob, JobContext, JobHandler, JobKind},
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    time::Instant,
};

use crate::infra::{retention, sessions};

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

/// Returns the identity module's system retention handlers.
#[must_use]
pub fn handlers() -> Vec<Arc<dyn JobHandler>> {
    vec![Arc::new(SessionsPrune), Arc::new(ResetsPrune)]
}
