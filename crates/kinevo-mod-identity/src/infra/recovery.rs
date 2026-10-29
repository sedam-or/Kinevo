//! Account-scoped persistence for one-time recovery-code hashes.

use crate::domain::totp::{RecoveryCode, hash};
use kinevo_db::Tx;
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::{AccountId, IdGen},
    time::Instant,
};

/// Replaces the account's pending recovery codes inside the caller's transaction.
pub async fn replace(
    tx: &mut Tx,
    ids: &dyn IdGen,
    account: AccountId,
    codes: &[RecoveryCode],
    now: Instant,
) -> Result<(), AppError> {
    sqlx::query("DELETE FROM public.recovery_codes WHERE account_id = $1")
        .bind(*account.as_uuid())
        .execute(tx.conn())
        .await
        .map_err(database_error)?;

    for code in codes {
        sqlx::query(
            "INSERT INTO public.recovery_codes (id, account_id, code_hash, created_at) \
             VALUES ($1, $2, $3, $4::timestamptz)",
        )
        .bind(ids.new_uuid_v7())
        .bind(*account.as_uuid())
        .bind(hash(code).as_slice())
        .bind(now.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(database_error)?;
    }
    Ok(())
}

/// Atomically marks one unused recovery code as consumed.
pub async fn consume(
    tx: &mut Tx,
    account: AccountId,
    code_hash: &[u8; 32],
    now: Instant,
) -> Result<bool, AppError> {
    let row = sqlx::query(
        "UPDATE public.recovery_codes SET used_at = $3::timestamptz \
         WHERE account_id = $1 AND code_hash = $2 AND used_at IS NULL \
         RETURNING id",
    )
    .bind(*account.as_uuid())
    .bind(code_hash.as_slice())
    .bind(now.to_rfc3339())
    .fetch_optional(tx.conn())
    .await
    .map_err(database_error)?;
    Ok(row.is_some())
}

/// Deletes every recovery code for the account inside the caller's transaction.
pub async fn delete_all(tx: &mut Tx, account: AccountId) -> Result<(), AppError> {
    sqlx::query("DELETE FROM public.recovery_codes WHERE account_id = $1")
        .bind(*account.as_uuid())
        .execute(tx.conn())
        .await
        .map_err(database_error)?;
    Ok(())
}

fn database_error(_error: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
