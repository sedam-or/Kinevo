//! Atomic invite consumption for registration.

use kinevo_db::Tx;
use kinevo_kernel::{
    crypto::{encode_token, hash_token},
    errors::{AppError, ErrorCode},
    id::{AccountId, IdGen, TokenGen},
    secret::Secret,
    time::Instant,
    validate::net::Email,
};

/// Creates an invite row with a hash-only token and returns its one-time URL.
#[allow(
    clippy::too_many_arguments,
    reason = "the invite persistence operation receives explicit transaction and token inputs"
)]
pub async fn create(
    tx: &mut Tx,
    ids: &dyn IdGen,
    tokens: &dyn TokenGen,
    email: Option<&Email>,
    created_by: AccountId,
    base_url: &str,
    now: Instant,
    ttl_seconds: i64,
) -> Result<Secret<String>, AppError> {
    let raw = tokens.token_256();
    let token = encode_token(&raw);
    let token_hash = hash_token(&raw);
    let expires_at = now
        .checked_add_seconds(ttl_seconds)
        .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
    sqlx::query(
        "INSERT INTO public.invites (id,token_hash,email,created_by,expires_at,created_at) \
         VALUES ($1,$2,$3,$4,$5::timestamptz,$6::timestamptz)",
    )
    .bind(ids.new_uuid_v7())
    .bind(token_hash.as_slice())
    .bind(email.map(Email::as_str))
    .bind(*created_by.as_uuid())
    .bind(expires_at.to_rfc3339())
    .bind(now.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
    Ok(Secret::new(format!("{base_url}/register?invite={token}")))
}

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
