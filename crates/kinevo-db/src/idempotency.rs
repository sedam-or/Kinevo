//! Tenant-scoped storage for idempotent HTTP responses.

use kinevo_kernel::{
    crypto::ct_eq,
    errors::{AppError, ErrorCode, FieldCode},
    time::Instant,
};
use sqlx::Row;

use crate::Tx;

/// Largest response body retained for replay. Larger responses release the key.
pub const MAX_STORED_BODY_BYTES: usize = 262_144;

/// A validated, normalized idempotency key.
#[derive(Clone, Eq, PartialEq)]
pub struct IdemKey(String);

impl IdemKey {
    /// Trims and lowercases a key, then validates the key-like API contract.
    pub fn parse(raw: &str) -> Result<Self, FieldCode> {
        let normalized = raw.trim().to_ascii_lowercase();
        if !(8..=128).contains(&normalized.len())
            || !normalized
                .bytes()
                .all(|byte| byte.is_ascii_alphanumeric() || matches!(byte, b'_' | b'-'))
        {
            return Err(FieldCode::KeyInvalid);
        }
        Ok(Self(normalized))
    }

    /// Returns the normalized key for database binding.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

/// A response that can be replayed for a duplicate request.
#[derive(Clone, Eq, PartialEq)]
pub struct StoredResponse {
    pub status: u16,
    pub body: Vec<u8>,
}

/// Classification returned when a request reserves or reuses a key.
pub enum Begin {
    /// This call owns a fresh in-flight reservation.
    Fresh,
    /// The original response is ready to replay.
    Replay(StoredResponse),
    /// An equivalent request is still executing.
    InProgress,
    /// The key is already bound to a different request.
    Mismatch,
}

/// Reserves a key or classifies its current request.
///
/// Commit a `Fresh` reservation before running the handler. The handler then
/// runs in a separate transaction, and `finish` stores its response afterward;
/// keeping the reservation transaction short preserves the in-flight signal
/// if the handler fails or rolls back. `method` is stored upper-case. `path` is
/// stored verbatim: T-019 supplies the matched route template with concrete
/// path parameters and excludes the query string for creates.
pub async fn begin(
    tx: &mut Tx,
    key: &IdemKey,
    method: &str,
    path: &str,
    request_hash: [u8; 32],
    now: Instant,
    ttl_seconds: i64,
) -> Result<Begin, AppError> {
    let expires_at = now
        .checked_add_seconds(ttl_seconds)
        .ok_or_else(service_unavailable)?;
    let account_id = *tx.account_id().as_uuid();
    let method = method.to_ascii_uppercase();

    loop {
        let inserted = sqlx::query(
            "INSERT INTO public.idempotency_keys \
             (account_id, key, method, path, request_hash, status_code, response_body, created_at, expires_at) \
             VALUES ($1, $2, $3, $4, $5, NULL, NULL, $6::timestamptz, $7::timestamptz) \
             ON CONFLICT (account_id, key) DO NOTHING",
        )
        .bind(account_id)
        .bind(key.as_str())
        .bind(&method)
        .bind(path)
        .bind(request_hash.as_slice())
        .bind(now.to_rfc3339())
        .bind(expires_at.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(database_error)?;
        if inserted.rows_affected() == 1 {
            return Ok(Begin::Fresh);
        }

        let row = sqlx::query(
            "SELECT method, path, request_hash, status_code, response_body, \
                    expires_at > $3::timestamptz AS active \
             FROM public.idempotency_keys \
             WHERE account_id = $1 AND key = $2 \
             FOR UPDATE",
        )
        .bind(account_id)
        .bind(key.as_str())
        .bind(now.to_rfc3339())
        .fetch_optional(tx.conn())
        .await
        .map_err(database_error)?;

        // The system retention job can delete an expired conflict between the
        // insert and select. Retry insertion in that case.
        let Some(row) = row else {
            continue;
        };
        let active: bool = row.try_get("active").map_err(database_error)?;
        if !active {
            sqlx::query(
                "UPDATE public.idempotency_keys \
                 SET method = $3, path = $4, request_hash = $5, status_code = NULL, \
                     response_body = NULL, created_at = $6::timestamptz, expires_at = $7::timestamptz \
                 WHERE account_id = $1 AND key = $2",
            )
            .bind(account_id)
            .bind(key.as_str())
            .bind(&method)
            .bind(path)
            .bind(request_hash.as_slice())
            .bind(now.to_rfc3339())
            .bind(expires_at.to_rfc3339())
            .execute(tx.conn())
            .await
            .map_err(database_error)?;
            return Ok(Begin::Fresh);
        }

        let previous_method: String = row.try_get("method").map_err(database_error)?;
        let previous_path: String = row.try_get("path").map_err(database_error)?;
        let previous_hash: Vec<u8> = row.try_get("request_hash").map_err(database_error)?;
        if previous_method != method
            || previous_path != path
            || !ct_eq(&previous_hash, &request_hash)
        {
            return Ok(Begin::Mismatch);
        }

        let status_code: Option<i16> = row.try_get("status_code").map_err(database_error)?;
        let Some(status_code) = status_code else {
            return Ok(Begin::InProgress);
        };
        let status = u16::try_from(status_code).map_err(|_| service_unavailable())?;
        let body: Option<Vec<u8>> = row.try_get("response_body").map_err(database_error)?;
        return Ok(Begin::Replay(StoredResponse {
            status,
            body: body.unwrap_or_default(),
        }));
    }
}

/// Stores a deterministic response, or releases the reservation when it must
/// not be replayed. Set `store_body` to false on routes whose response includes
/// secrets; only the status code is retained for those routes.
pub async fn finish(
    tx: &mut Tx,
    key: &IdemKey,
    status: u16,
    body: &[u8],
    store_body: bool,
) -> Result<(), AppError> {
    if status >= 500 || status == 429 || (store_body && body.len() > MAX_STORED_BODY_BYTES) {
        return release(tx, key).await;
    }

    let status = i16::try_from(status).map_err(|_| service_unavailable())?;
    let response_body = store_body.then_some(body);
    let result = sqlx::query(
        "UPDATE public.idempotency_keys \
         SET status_code = $3, response_body = $4 \
         WHERE account_id = $1 AND key = $2 AND status_code IS NULL",
    )
    .bind(*tx.account_id().as_uuid())
    .bind(key.as_str())
    .bind(status)
    .bind(response_body)
    .execute(tx.conn())
    .await
    .map_err(database_error)?;
    if result.rows_affected() != 1 {
        return Err(service_unavailable());
    }
    Ok(())
}

/// Makes a reservation immediately reusable without requiring DELETE privilege.
/// The stored response is cleared so released keys do not retain response data.
pub async fn release(tx: &mut Tx, key: &IdemKey) -> Result<(), AppError> {
    let result = sqlx::query(
        "UPDATE public.idempotency_keys \
         SET status_code = NULL, response_body = NULL, expires_at = created_at \
         WHERE account_id = $1 AND key = $2",
    )
    .bind(*tx.account_id().as_uuid())
    .bind(key.as_str())
    .execute(tx.conn())
    .await
    .map_err(database_error)?;
    if result.rows_affected() != 1 {
        return Err(service_unavailable());
    }
    Ok(())
}

fn database_error(_: sqlx::Error) -> AppError {
    service_unavailable()
}

fn service_unavailable() -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
