use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    time::Instant,
};

use crate::SystemTx;

/// Deletes audit events strictly older than `cutoff`.
pub async fn prune_audit(tx: &mut SystemTx, cutoff: Instant) -> Result<u64, AppError> {
    prune(
        tx,
        "SELECT public.platform_prune_audit($1::timestamptz)",
        cutoff,
    )
    .await
}

/// Deletes idempotency records whose expiry is strictly older than `cutoff`.
pub async fn prune_idempotency(tx: &mut SystemTx, cutoff: Instant) -> Result<u64, AppError> {
    prune(
        tx,
        "SELECT public.platform_prune_idempotency($1::timestamptz)",
        cutoff,
    )
    .await
}

/// Deletes terminal jobs finished strictly before `cutoff`.
pub async fn prune_jobs(tx: &mut SystemTx, cutoff: Instant) -> Result<u64, AppError> {
    prune(
        tx,
        "SELECT public.platform_prune_jobs($1::timestamptz)",
        cutoff,
    )
    .await
}

/// Deletes eligible outbox deliveries and events older than `cutoff`.
pub async fn prune_outbox(tx: &mut SystemTx, cutoff: Instant) -> Result<u64, AppError> {
    prune(
        tx,
        "SELECT public.platform_prune_outbox($1::timestamptz)",
        cutoff,
    )
    .await
}

async fn prune(
    tx: &mut SystemTx,
    statement: &'static str,
    cutoff: Instant,
) -> Result<u64, AppError> {
    let count = sqlx::query_scalar::<_, i64>(statement)
        .bind(cutoff.to_rfc3339())
        .fetch_one(tx.conn())
        .await
        .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
    u64::try_from(count).map_err(|_| AppError::new(ErrorCode::InternalError))
}
