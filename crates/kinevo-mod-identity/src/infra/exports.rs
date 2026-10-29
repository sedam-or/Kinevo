//! Account profile and export archive persistence within the caller's tenant transaction.

use kinevo_db::Tx;
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
    time::Instant,
};
use sqlx::{Row, types::Uuid};

/// Profile fields read by the identity account exporter.
pub struct AccountProfile {
    pub email: String,
    pub display_name: String,
    pub locale: String,
    pub timezone: String,
    pub week_start: i16,
    pub totp_enabled: bool,
    pub created_at: String,
}

/// Persisted export data scoped by the caller's `Tx`.
pub struct ExportRow {
    pub state: String,
    pub size_bytes: Option<i64>,
    pub expires_at: Instant,
    pub bytes: Option<Vec<u8>>,
}

/// Reads only the authenticated account's profile fields allowed in `export_v1`.
pub async fn account_profile(tx: &mut Tx, account: AccountId) -> Result<AccountProfile, AppError> {
    let row = sqlx::query(
        "SELECT email::text AS email, display_name, locale, timezone, week_start, \
                totp_enabled, to_char(created_at AT TIME ZONE 'UTC', \
                'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS created_at \
         FROM public.accounts WHERE id=$1",
    )
    .bind(*account.as_uuid())
    .fetch_optional(tx.conn())
    .await
    .map_err(database_error)?
    .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
    Ok(AccountProfile {
        email: row.try_get("email").map_err(database_error)?,
        display_name: row.try_get("display_name").map_err(database_error)?,
        locale: row.try_get("locale").map_err(database_error)?,
        timezone: row.try_get("timezone").map_err(database_error)?,
        week_start: row.try_get("week_start").map_err(database_error)?,
        totp_enabled: row.try_get("totp_enabled").map_err(database_error)?,
        created_at: row.try_get("created_at").map_err(database_error)?,
    })
}

/// Locks the account row while a new export is queued.
pub async fn lock_account(tx: &mut Tx, account: AccountId) -> Result<(), AppError> {
    sqlx::query("SELECT id FROM public.accounts WHERE id=$1 FOR UPDATE")
        .bind(*account.as_uuid())
        .fetch_optional(tx.conn())
        .await
        .map_err(database_error)?
        .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
    Ok(())
}

/// Finds the account's queued or running export while its account row is locked.
pub async fn active_export_id(tx: &mut Tx, account: AccountId) -> Result<Option<Uuid>, AppError> {
    sqlx::query_scalar::<_, Uuid>(
        "SELECT id FROM public.account_exports WHERE account_id=$1 \
         AND state IN ('queued','running') ORDER BY created_at DESC LIMIT 1 FOR UPDATE",
    )
    .bind(*account.as_uuid())
    .fetch_optional(tx.conn())
    .await
    .map_err(database_error)
}

/// Inserts a queued export row in the request's transaction.
pub async fn create_queued_export(
    tx: &mut Tx,
    id: Uuid,
    account: AccountId,
    created_at: Instant,
    expires_at: Instant,
) -> Result<(), AppError> {
    sqlx::query(
        "INSERT INTO public.account_exports \
         (id, account_id, state, bytes, size_bytes, created_at, expires_at) \
         VALUES ($1,$2,'queued',NULL,NULL,$3::timestamptz,$4::timestamptz)",
    )
    .bind(id)
    .bind(*account.as_uuid())
    .bind(created_at.to_rfc3339())
    .bind(expires_at.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(database_error)?;
    Ok(())
}

/// Loads an export through the caller's row-level-security context.
pub async fn find(tx: &mut Tx, id: Uuid) -> Result<ExportRow, AppError> {
    let row = sqlx::query(
        "SELECT state, size_bytes, bytes, \
                to_char(expires_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS expires_at \
         FROM public.account_exports WHERE id=$1",
    )
    .bind(id)
    .fetch_optional(tx.conn())
    .await
    .map_err(database_error)?
    .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
    let expires_at = row
        .try_get::<String, _>("expires_at")
        .map_err(database_error)?;
    Ok(ExportRow {
        state: row.try_get("state").map_err(database_error)?,
        size_bytes: row.try_get("size_bytes").map_err(database_error)?,
        bytes: row.try_get("bytes").map_err(database_error)?,
        expires_at: Instant::parse_rfc3339(&expires_at).map_err(|_| internal_error())?,
    })
}

/// Locks one export while its worker validates the current state.
pub async fn lock_state(tx: &mut Tx, account: AccountId, id: Uuid) -> Result<String, AppError> {
    let row = sqlx::query(
        "SELECT state FROM public.account_exports WHERE id=$1 AND account_id=$2 FOR UPDATE",
    )
    .bind(id)
    .bind(*account.as_uuid())
    .fetch_optional(tx.conn())
    .await
    .map_err(database_error)?
    .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
    row.try_get("state").map_err(database_error)
}

/// Marks a queued or lease-reclaimed export as running.
pub async fn mark_running(tx: &mut Tx, id: Uuid) -> Result<(), AppError> {
    sqlx::query("UPDATE public.account_exports SET state='running' WHERE id=$1")
        .bind(id)
        .execute(tx.conn())
        .await
        .map_err(database_error)?;
    Ok(())
}

/// Stores completed archive bytes and their canonical size and expiry.
pub async fn mark_ready(
    tx: &mut Tx,
    id: Uuid,
    bytes: &[u8],
    size_bytes: i64,
    expires_at: Instant,
) -> Result<(), AppError> {
    sqlx::query(
        "UPDATE public.account_exports SET state='ready', bytes=$2, size_bytes=$3, \
         expires_at=$4::timestamptz WHERE id=$1",
    )
    .bind(id)
    .bind(bytes)
    .bind(size_bytes)
    .bind(expires_at.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(database_error)?;
    Ok(())
}

/// Marks a failed build after its archive transaction has rolled back.
pub async fn mark_failed(tx: &mut Tx, account: AccountId, id: Uuid) -> Result<(), AppError> {
    sqlx::query(
        "UPDATE public.account_exports SET state='failed' \
         WHERE id=$1 AND account_id=$2 AND state='running'",
    )
    .bind(id)
    .bind(*account.as_uuid())
    .execute(tx.conn())
    .await
    .map_err(database_error)?;
    Ok(())
}

/// Expires a ready export and clears its protected archive bytes.
pub async fn expire(tx: &mut Tx, id: Uuid) -> Result<(), AppError> {
    sqlx::query(
        "UPDATE public.account_exports SET state='expired', bytes=NULL \
         WHERE id=$1 AND state='ready'",
    )
    .bind(id)
    .execute(tx.conn())
    .await
    .map_err(database_error)?;
    Ok(())
}

fn database_error(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}

fn internal_error() -> AppError {
    AppError::new(ErrorCode::InternalError)
}
