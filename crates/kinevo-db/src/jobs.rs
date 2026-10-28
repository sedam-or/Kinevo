//! PostgreSQL-backed job queue operations.

use async_trait::async_trait;
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::{AccountId, IdGen, JobId},
    time::{Clock, Instant},
};
use serde_json::Value;
use sqlx::{Row, types::Uuid};
use std::{fmt, sync::Arc};

use crate::{Db, SystemTx, audit::TxConn};

const MAX_PAYLOAD_BYTES: usize = 16 * 1024;
const MAX_PAYLOAD_STRING_CHARS: usize = 128;

/// A registered job kind. Only static, validated names can be constructed.
#[derive(Clone, Copy, Debug, Eq, Hash, PartialEq)]
pub struct JobKind(&'static str);

impl JobKind {
    pub fn new(value: &'static str) -> Result<Self, AppError> {
        let bytes = value.as_bytes();
        let valid = (2..=64).contains(&bytes.len())
            && bytes.first().is_some_and(u8::is_ascii_lowercase)
            && bytes.iter().skip(1).all(|byte| {
                byte.is_ascii_lowercase() || byte.is_ascii_digit() || matches!(byte, b'_' | b'.')
            });
        if valid {
            Ok(Self(value))
        } else {
            Err(AppError::new(ErrorCode::ValidationFailed))
        }
    }

    #[must_use]
    pub const fn as_str(self) -> &'static str {
        self.0
    }
}

impl fmt::Display for JobKind {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str(self.0)
    }
}

/// Opaque process-local worker identity stored on a claimed row.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct WorkerId(String);

impl WorkerId {
    #[must_use]
    pub fn new(value: impl Into<String>) -> Self {
        Self(value.into())
    }

    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

/// A new queue entry. `max_attempts` is resolved by the caller from policy.
pub struct NewJob {
    pub account_id: Option<AccountId>,
    pub kind: JobKind,
    pub unique_key: Option<String>,
    pub payload: Value,
    pub run_at: Instant,
    pub max_attempts: u8,
}

/// Outcome of an idempotent enqueue.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum EnqueueOutcome {
    Inserted(JobId),
    Duplicate,
}

/// A job atomically leased to a worker.
#[derive(Clone, Debug, PartialEq)]
pub struct ClaimedJob {
    pub id: JobId,
    pub account_id: Option<AccountId>,
    pub kind: String,
    pub payload: Value,
    pub attempts: i32,
    pub max_attempts: i32,
}

/// Retry delay settings resolved by the caller from the policy registry.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct RetryPolicy {
    pub base_seconds: i64,
    pub cap_seconds: i64,
}

/// A recurring schedule slot supplied by a module.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct ScheduleSlot {
    pub kind: JobKind,
    pub account_id: Option<AccountId>,
    pub scheduled_for: Instant,
}

/// Counts for one kind and queue state.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct JobCount {
    pub kind: String,
    pub state: String,
    pub count: i64,
}

/// Dependencies made available to an idempotent job handler.
#[derive(Clone)]
pub struct JobContext {
    pub db: Arc<Db>,
    pub ids: Arc<dyn IdGen>,
    pub clock: Arc<dyn Clock>,
}

impl JobContext {
    #[must_use]
    pub fn new(db: Arc<Db>, ids: Arc<dyn IdGen>, clock: Arc<dyn Clock>) -> Self {
        Self { db, ids, clock }
    }
}

/// A module-provided asynchronous job handler.
#[async_trait]
pub trait JobHandler: Send + Sync {
    fn kind(&self) -> JobKind;

    async fn run(&self, ctx: JobContext, job: ClaimedJob) -> Result<(), AppError>;
}

/// Returns capped exponential backoff for a one-based attempt count.
#[must_use]
pub fn backoff_seconds(attempts: u32, policy: RetryPolicy) -> i64 {
    let base = policy.base_seconds.max(0);
    let cap = policy.cap_seconds.max(0);
    if base == 0 || cap == 0 {
        return 0;
    }

    let shift = attempts.saturating_sub(1);
    let multiplier = 1_i64.checked_shl(shift).unwrap_or(i64::MAX);
    base.saturating_mul(multiplier).min(cap)
}

/// Inserts a job in the caller's transaction.
pub async fn enqueue(
    conn: &mut impl TxConn,
    ids: &dyn IdGen,
    now: Instant,
    job: NewJob,
) -> Result<EnqueueOutcome, AppError> {
    if job.max_attempts == 0 {
        return Err(AppError::new(ErrorCode::ValidationFailed));
    }
    let payload = validate_payload(&job.payload)?;
    let id = ids.new_uuid_v7();
    let inserted = sqlx::query_scalar::<_, Uuid>(
        "INSERT INTO public.jobs \
         (id, account_id, kind, unique_key, payload, state, run_at, attempts, \
          max_attempts, locked_by, locked_until, last_error_code, created_at, finished_at) \
         VALUES ($1, $2, $3, $4, $5::jsonb, 'queued', $6::timestamptz, 0, \
                 $7, NULL, NULL, NULL, $8::timestamptz, NULL) \
         ON CONFLICT (kind, unique_key) DO NOTHING \
         RETURNING id",
    )
    .bind(id)
    .bind(job.account_id.map(|account| *account.as_uuid()))
    .bind(job.kind.as_str())
    .bind(job.unique_key)
    .bind(payload)
    .bind(job.run_at.to_rfc3339())
    .bind(i32::from(job.max_attempts))
    .bind(now.to_rfc3339())
    .fetch_optional(conn.conn())
    .await
    .map_err(database_error)?;

    Ok(match inserted {
        Some(id) => EnqueueOutcome::Inserted(JobId::from_uuid(id)),
        None => EnqueueOutcome::Duplicate,
    })
}

/// Claims due jobs and expired leases in one `SKIP LOCKED` update.
#[allow(
    clippy::too_many_lines,
    reason = "selection and state transition must stay together as one atomic claim statement"
)]
pub async fn claim(
    tx: &mut SystemTx,
    worker: &WorkerId,
    now: Instant,
    lease_seconds: i64,
    limit: u16,
    enabled: &[JobKind],
) -> Result<Vec<ClaimedJob>, AppError> {
    if lease_seconds <= 0 {
        return Err(AppError::new(ErrorCode::ValidationFailed));
    }
    if limit == 0 || enabled.is_empty() {
        return Ok(Vec::new());
    }
    let kinds = enabled
        .iter()
        .map(|kind| kind.as_str().to_owned())
        .collect::<Vec<_>>();
    let rows = sqlx::query(
        "WITH candidates AS ( \
             SELECT id, attempts, max_attempts \
             FROM public.jobs \
             WHERE kind = ANY($1::text[]) \
               AND ((state = 'queued' AND run_at <= $2::timestamptz) \
                    OR (state = 'running' AND locked_until < $2::timestamptz)) \
             ORDER BY run_at, id \
             FOR UPDATE SKIP LOCKED \
             LIMIT $3 \
         ) \
         UPDATE public.jobs AS job \
         SET state = CASE WHEN candidates.attempts >= candidates.max_attempts \
                          THEN 'dead' ELSE 'running' END, \
             locked_by = CASE WHEN candidates.attempts >= candidates.max_attempts \
                              THEN NULL ELSE $4 END, \
             locked_until = CASE WHEN candidates.attempts >= candidates.max_attempts \
                                 THEN NULL \
                                 ELSE $2::timestamptz + ($5::bigint * interval '1 second') END, \
             attempts = CASE WHEN candidates.attempts >= candidates.max_attempts \
                             THEN job.attempts ELSE job.attempts + 1 END, \
             finished_at = CASE WHEN candidates.attempts >= candidates.max_attempts \
                                THEN $2::timestamptz ELSE NULL END \
         FROM candidates \
         WHERE job.id = candidates.id \
         RETURNING job.id, job.account_id, job.kind, job.payload, job.attempts, \
                   job.max_attempts, job.state",
    )
    .bind(kinds)
    .bind(now.to_rfc3339())
    .bind(i64::from(limit))
    .bind(worker.as_str())
    .bind(lease_seconds)
    .fetch_all(tx.conn())
    .await
    .map_err(database_error)?;

    let mut claimed = Vec::with_capacity(rows.len());
    for row in rows {
        let state: String = row.try_get("state").map_err(database_error)?;
        if state != "running" {
            continue;
        }
        let id: Uuid = row.try_get("id").map_err(database_error)?;
        let account_id: Option<Uuid> = row.try_get("account_id").map_err(database_error)?;
        let kind: String = row.try_get("kind").map_err(database_error)?;
        let payload: Value = row.try_get("payload").map_err(database_error)?;
        let attempts: i32 = row.try_get("attempts").map_err(database_error)?;
        let max_attempts: i32 = row.try_get("max_attempts").map_err(database_error)?;
        claimed.push(ClaimedJob {
            id: JobId::from_uuid(id),
            account_id: account_id.map(AccountId::from_uuid),
            kind,
            payload,
            attempts,
            max_attempts,
        });
    }
    Ok(claimed)
}

/// Marks a running job as succeeded and clears its lease.
pub async fn complete(tx: &mut SystemTx, id: JobId, now: Instant) -> Result<(), AppError> {
    let result = sqlx::query(
        "UPDATE public.jobs SET state = 'succeeded', finished_at = $2::timestamptz, \
         locked_by = NULL, locked_until = NULL WHERE id = $1 AND state = 'running'",
    )
    .bind(*id.as_uuid())
    .bind(now.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(database_error)?;
    require_running_row(result.rows_affected())
}

/// Records a failed attempt and either schedules a retry or marks the job dead.
pub async fn fail(
    tx: &mut SystemTx,
    id: JobId,
    now: Instant,
    code: ErrorCode,
    retry: RetryPolicy,
) -> Result<(), AppError> {
    let row = sqlx::query(
        "SELECT attempts, max_attempts FROM public.jobs \
         WHERE id = $1 AND state = 'running' FOR UPDATE",
    )
    .bind(*id.as_uuid())
    .fetch_optional(tx.conn())
    .await
    .map_err(database_error)?
    .ok_or_else(invalid_transition)?;
    let attempts: i32 = row.try_get("attempts").map_err(database_error)?;
    let max_attempts: i32 = row.try_get("max_attempts").map_err(database_error)?;
    let dead = attempts >= max_attempts;
    let next_run_at = if dead {
        None
    } else {
        let attempts =
            u32::try_from(attempts).map_err(|_| AppError::new(ErrorCode::InternalError))?;
        let delay = backoff_seconds(attempts, retry);
        Some(
            now.checked_add_seconds(delay)
                .ok_or_else(|| AppError::new(ErrorCode::ValidationFailed))?
                .to_rfc3339(),
        )
    };
    let state = if dead { "dead" } else { "queued" };
    let result = sqlx::query(
        "UPDATE public.jobs SET state = $2, \
         run_at = COALESCE($3::timestamptz, run_at), last_error_code = $4, \
         locked_by = NULL, locked_until = NULL, \
         finished_at = CASE WHEN $5 THEN $6::timestamptz ELSE NULL END \
         WHERE id = $1 AND state = 'running'",
    )
    .bind(*id.as_uuid())
    .bind(state)
    .bind(next_run_at)
    .bind(code.as_str())
    .bind(dead)
    .bind(now.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(database_error)?;
    require_running_row(result.rows_affected())
}

/// Extends a running job lease from the caller's injected instant.
pub async fn extend_lease(
    tx: &mut SystemTx,
    id: JobId,
    now: Instant,
    lease_seconds: i64,
) -> Result<(), AppError> {
    if lease_seconds <= 0 {
        return Err(AppError::new(ErrorCode::ValidationFailed));
    }
    let until = now
        .checked_add_seconds(lease_seconds)
        .ok_or_else(|| AppError::new(ErrorCode::ValidationFailed))?;
    let result = sqlx::query(
        "UPDATE public.jobs SET locked_until = $2::timestamptz \
         WHERE id = $1 AND state = 'running'",
    )
    .bind(*id.as_uuid())
    .bind(until.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(database_error)?;
    require_running_row(result.rows_affected())
}

/// Inserts schedule slots idempotently and returns the number of new rows.
pub async fn materialise(
    tx: &mut SystemTx,
    ids: &dyn IdGen,
    now: Instant,
    max_attempts: u8,
    schedules: &[ScheduleSlot],
) -> Result<u64, AppError> {
    if max_attempts == 0 {
        return Err(AppError::new(ErrorCode::ValidationFailed));
    }
    let mut inserted = 0_u64;
    for slot in schedules {
        let account_key = slot
            .account_id
            .map_or_else(|| "instance".to_owned(), |account| account.to_string());
        let unique_key = format!("{account_key}:{}", slot.scheduled_for.to_rfc3339());
        let outcome = enqueue(
            tx,
            ids,
            now,
            NewJob {
                account_id: slot.account_id,
                kind: slot.kind,
                unique_key: Some(unique_key),
                payload: Value::Object(serde_json::Map::new()),
                run_at: slot.scheduled_for,
                max_attempts,
            },
        )
        .await?;
        if matches!(outcome, EnqueueOutcome::Inserted(_)) {
            inserted = inserted.saturating_add(1);
        }
    }
    Ok(inserted)
}

/// Returns job counts grouped by kind and state.
pub async fn summary(conn: &mut impl TxConn) -> Result<Vec<JobCount>, AppError> {
    let rows = sqlx::query(
        "SELECT kind, state, count(*)::bigint AS count \
         FROM public.jobs GROUP BY kind, state ORDER BY kind, state",
    )
    .fetch_all(conn.conn())
    .await
    .map_err(database_error)?;
    rows.into_iter()
        .map(|row| {
            Ok(JobCount {
                kind: row.try_get("kind").map_err(database_error)?,
                state: row.try_get("state").map_err(database_error)?,
                count: row.try_get("count").map_err(database_error)?,
            })
        })
        .collect()
}

/// Returns the oldest queued row's age in seconds at the injected instant.
pub async fn oldest_queued_age_seconds(
    conn: &mut impl TxConn,
    now: Instant,
) -> Result<Option<i64>, AppError> {
    sqlx::query_scalar::<_, Option<i64>>(
        "SELECT floor(extract(epoch FROM ($1::timestamptz - min(created_at))))::bigint \
         FROM public.jobs WHERE state = 'queued'",
    )
    .bind(now.to_rfc3339())
    .fetch_one(conn.conn())
    .await
    .map(|age| age.map(|seconds: i64| seconds.max(0)))
    .map_err(database_error)
}

fn validate_payload(payload: &Value) -> Result<String, AppError> {
    let bytes = serde_json::to_vec(payload).map_err(|_| AppError::new(ErrorCode::InternalError))?;
    if bytes.len() > MAX_PAYLOAD_BYTES {
        return Err(AppError::new(ErrorCode::PayloadTooLarge));
    }
    if !payload_strings_are_bounded(payload) {
        return Err(AppError::new(ErrorCode::ValidationFailed));
    }
    String::from_utf8(bytes).map_err(|_| AppError::new(ErrorCode::InternalError))
}

fn payload_strings_are_bounded(payload: &Value) -> bool {
    match payload {
        Value::String(value) => value.chars().count() <= MAX_PAYLOAD_STRING_CHARS,
        Value::Array(values) => values.iter().all(payload_strings_are_bounded),
        Value::Object(values) => values.iter().all(|(key, value)| {
            key.chars().count() <= MAX_PAYLOAD_STRING_CHARS && payload_strings_are_bounded(value)
        }),
        Value::Null | Value::Bool(_) | Value::Number(_) => true,
    }
}

fn require_running_row(rows_affected: u64) -> Result<(), AppError> {
    if rows_affected == 1 {
        Ok(())
    } else {
        Err(invalid_transition())
    }
}

fn invalid_transition() -> AppError {
    AppError::new(ErrorCode::InvalidTransition)
}

fn database_error(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
