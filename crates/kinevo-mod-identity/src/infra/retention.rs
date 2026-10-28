//! System-context deletion for expired identity records.

use kinevo_db::SystemTx;
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    time::Instant,
};

/// Deletes revoked or expired refresh families older than the retention cutoff.
pub async fn prune_sessions(tx: &mut SystemTx, cutoff: Instant) -> Result<u64, AppError> {
    let result = sqlx::query(
        "DELETE FROM public.refresh_token_families AS family \
         WHERE family.revoked_at < $1::timestamptz \
            OR family.absolute_expires_at < $1::timestamptz \
            OR EXISTS (SELECT 1 FROM public.refresh_tokens AS token \
                       WHERE token.family_id = family.id AND token.used_at IS NULL \
                         AND token.idle_expires_at < $1::timestamptz)",
    )
    .bind(cutoff.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?;
    Ok(result.rows_affected())
}

/// Deletes used or expired password-reset rows older than the retention cutoff.
pub async fn prune_resets(tx: &mut SystemTx, cutoff: Instant) -> Result<u64, AppError> {
    let result = sqlx::query(
        "DELETE FROM public.password_resets \
         WHERE expires_at < $1::timestamptz OR used_at < $1::timestamptz",
    )
    .bind(cutoff.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?;
    Ok(result.rows_affected())
}

fn service_unavailable(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
