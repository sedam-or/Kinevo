//! Password-reset persistence in restricted system transactions.

use kinevo_db::SystemTx;
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
    time::Instant,
};
use sqlx::{Row, types::Uuid};

/// Small row returned while locking a reset token and its account.
pub struct ResetRow {
    pub id: Uuid,
    pub account_id: AccountId,
    pub status: String,
}

/// Locks a reset token and its owning account for one confirmation attempt.
pub async fn lock_by_hash(
    tx: &mut SystemTx,
    token_hash: &[u8; 32],
) -> Result<Option<ResetRow>, AppError> {
    let row = sqlx::query(
        "SELECT r.id, r.account_id, a.status \
         FROM public.password_resets r JOIN public.accounts a ON a.id=r.account_id \
         WHERE r.token_hash=$1 FOR UPDATE OF r,a",
    )
    .bind(token_hash.as_slice())
    .fetch_optional(tx.conn())
    .await
    .map_err(service_unavailable)?;
    row.map(|row| {
        Ok(ResetRow {
            id: row.try_get("id").map_err(service_unavailable)?,
            account_id: AccountId::from_uuid(
                row.try_get("account_id").map_err(service_unavailable)?,
            ),
            status: row.try_get("status").map_err(service_unavailable)?,
        })
    })
    .transpose()
}

/// Consumes a reset token only once and only before its expiry instant.
pub async fn consume(tx: &mut SystemTx, id: Uuid, now: Instant) -> Result<(), AppError> {
    let changed = sqlx::query(
        "UPDATE public.password_resets SET used_at=$2::timestamptz \
         WHERE id=$1 AND used_at IS NULL AND expires_at>$2::timestamptz",
    )
    .bind(id)
    .bind(now.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?
    .rows_affected();
    if changed != 1 {
        return Err(AppError::new(ErrorCode::ResetTokenInvalid));
    }
    Ok(())
}

fn service_unavailable(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
