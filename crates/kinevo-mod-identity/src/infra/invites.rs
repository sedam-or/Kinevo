//! Atomic invite consumption for registration.

use kinevo_db::Tx;
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    time::Instant,
    validate::net::Email,
};

/// Marks one eligible invite as used within the account-creation transaction.
pub async fn consume(
    tx: &mut Tx,
    token_hash: &[u8; 32],
    email: &Email,
    now: Instant,
) -> Result<(), AppError> {
    let invite = sqlx::query(
        "UPDATE public.invites \
         SET used_at = $3::timestamptz \
         WHERE token_hash = $1 AND used_at IS NULL AND expires_at > $3::timestamptz \
           AND (email IS NULL OR email = $2::citext) \
         RETURNING id",
    )
    .bind(token_hash.as_slice())
    .bind(email.as_str())
    .bind(now.to_rfc3339())
    .fetch_optional(tx.conn())
    .await
    .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
    invite.ok_or_else(|| AppError::new(ErrorCode::InviteInvalid))?;
    Ok(())
}
