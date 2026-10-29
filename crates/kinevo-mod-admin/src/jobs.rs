//! Admin platform retention and outbox retry workers.

use std::sync::Arc;

use async_trait::async_trait;
use kinevo_db::{
    SystemTx,
    jobs::{ClaimedJob, JobContext, JobHandler, JobKind},
    outbox, retention,
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::EventId,
    policy::registry,
    time::Instant,
};
use kinevo_ports::platform::PolicyReader;
use sqlx::types::Uuid;

use crate::app::jobs_admin::policy_value;

/// Retention horizon for completed platform jobs and delivered outbox data.
pub const FINISHED_RETENTION_DAYS: i64 = 7;
const SECONDS_PER_DAY: i64 = 86_400;

/// Prunes append-only audit rows using the instance retention policy.
pub struct AuditPrune {
    policies: Arc<dyn PolicyReader>,
}

impl AuditPrune {
    #[must_use]
    pub fn new(policies: Arc<dyn PolicyReader>) -> Self {
        Self { policies }
    }

    /// Executes one prune and returns the deleted row count to the worker boundary.
    pub async fn run_count(&self, ctx: &JobContext) -> Result<u64, AppError> {
        let now = ctx.clock.now();
        let snapshot = self.policies.snapshot_values(None, now).await?;
        let days = policy_value(&snapshot, &registry::admin::AUDIT_RETENTION_DAYS)?;
        let seconds = days
            .checked_mul(SECONDS_PER_DAY)
            .and_then(i64::checked_neg)
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let cutoff = cutoff(now, seconds)?;
        let mut tx = SystemTx::begin(&ctx.db).await?;
        let count = retention::prune_audit(&mut tx, cutoff).await?;
        tx.commit().await?;
        Ok(count)
    }
}

#[async_trait]
impl JobHandler for AuditPrune {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("admin.audit.prune")
    }

    async fn run(&self, ctx: JobContext, _job: ClaimedJob) -> Result<(), AppError> {
        self.run_count(&ctx).await.map(|_| ())
    }
}

/// Prunes idempotency records using the configured response-retention policy.
pub struct IdempotencyPrune {
    policies: Arc<dyn PolicyReader>,
}

impl IdempotencyPrune {
    #[must_use]
    pub fn new(policies: Arc<dyn PolicyReader>) -> Self {
        Self { policies }
    }

    /// Executes one prune and returns the deleted row count to the worker boundary.
    pub async fn run_count(&self, ctx: &JobContext) -> Result<u64, AppError> {
        let now = ctx.clock.now();
        let snapshot = self.policies.snapshot_values(None, now).await?;
        let ttl = policy_value(&snapshot, &registry::http::IDEMPOTENCY_TTL)?.0;
        let cutoff = cutoff(now, ttl.checked_neg().ok_or_else(internal_error)?)?;
        let mut tx = SystemTx::begin(&ctx.db).await?;
        let count = retention::prune_idempotency(&mut tx, cutoff).await?;
        tx.commit().await?;
        Ok(count)
    }
}

#[async_trait]
impl JobHandler for IdempotencyPrune {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("admin.idempotency.prune")
    }

    async fn run(&self, ctx: JobContext, _job: ClaimedJob) -> Result<(), AppError> {
        self.run_count(&ctx).await.map(|_| ())
    }
}

/// Prunes terminal jobs beyond the task-defined fixed retention horizon.
pub struct JobsPrune;

impl JobsPrune {
    /// Executes one prune and returns the deleted row count to the worker boundary.
    pub async fn run_count(&self, ctx: &JobContext) -> Result<u64, AppError> {
        let cutoff = finished_cutoff(ctx.clock.now())?;
        let mut tx = SystemTx::begin(&ctx.db).await?;
        let count = retention::prune_jobs(&mut tx, cutoff).await?;
        tx.commit().await?;
        Ok(count)
    }
}

#[async_trait]
impl JobHandler for JobsPrune {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("jobs.prune")
    }

    async fn run(&self, ctx: JobContext, _job: ClaimedJob) -> Result<(), AppError> {
        self.run_count(&ctx).await.map(|_| ())
    }
}

/// Prunes delivered outbox events beyond the task-defined fixed retention horizon.
pub struct OutboxPrune;

impl OutboxPrune {
    /// Executes one prune and returns the deleted row count to the worker boundary.
    pub async fn run_count(&self, ctx: &JobContext) -> Result<u64, AppError> {
        let cutoff = finished_cutoff(ctx.clock.now())?;
        let mut tx = SystemTx::begin(&ctx.db).await?;
        let count = retention::prune_outbox(&mut tx, cutoff).await?;
        tx.commit().await?;
        Ok(count)
    }
}

#[async_trait]
impl JobHandler for OutboxPrune {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("outbox.prune")
    }

    async fn run(&self, ctx: JobContext, _job: ClaimedJob) -> Result<(), AppError> {
        self.run_count(&ctx).await.map(|_| ())
    }
}

/// Resets a dead delivery after its admin-authenticated request queued this job.
pub struct OutboxRetry;

#[async_trait]
impl JobHandler for OutboxRetry {
    fn kind(&self) -> JobKind {
        JobKind::from_valid_static("outbox.retry")
    }

    async fn run(&self, ctx: JobContext, job: ClaimedJob) -> Result<(), AppError> {
        let event_id = job
            .payload
            .get("event_id")
            .and_then(serde_json::Value::as_str)
            .and_then(|value| Uuid::parse_str(value).ok())
            .map(EventId::from_uuid)
            .ok_or_else(internal_error)?;
        let subscriber = job
            .payload
            .get("subscriber")
            .and_then(serde_json::Value::as_str)
            .filter(|value| valid_subscriber(value))
            .ok_or_else(internal_error)?;
        let now = ctx.clock.now();
        let mut tx = SystemTx::begin(&ctx.db).await?;
        outbox::retry_dead_named(&mut tx, event_id, subscriber, now).await?;
        tx.commit().await
    }
}

/// Creates the admin module's policy-aware retention and retry handlers.
#[must_use]
pub fn handlers(policies: Arc<dyn PolicyReader>) -> Vec<Arc<dyn JobHandler>> {
    vec![
        Arc::new(AuditPrune::new(Arc::clone(&policies))),
        Arc::new(IdempotencyPrune::new(policies)),
        Arc::new(JobsPrune),
        Arc::new(OutboxPrune),
        Arc::new(OutboxRetry),
    ]
}

fn finished_cutoff(now: Instant) -> Result<Instant, AppError> {
    let seconds = FINISHED_RETENTION_DAYS
        .checked_mul(SECONDS_PER_DAY)
        .and_then(i64::checked_neg)
        .ok_or_else(internal_error)?;
    cutoff(now, seconds)
}

fn cutoff(now: Instant, seconds: i64) -> Result<Instant, AppError> {
    now.checked_add_seconds(seconds).ok_or_else(internal_error)
}

fn valid_subscriber(value: &str) -> bool {
    !value.is_empty()
        && value.len() <= 64
        && value.bytes().all(|byte| {
            byte.is_ascii_lowercase() || byte.is_ascii_digit() || matches!(byte, b'.' | b'_' | b'-')
        })
}

fn internal_error() -> AppError {
    AppError::new(ErrorCode::InternalError)
}
