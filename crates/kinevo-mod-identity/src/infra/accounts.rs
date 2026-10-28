use kinevo_db::SystemTx;
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
    time::Instant,
    validate::net::Email,
};
use kinevo_ports::platform::AccountStatus;
use sqlx::Row;

use crate::domain::lockout::LockoutState;

/// System-context account fields needed by authentication.
pub struct AccountRow {
    pub id: AccountId,
    pub email: String,
    pub display_name: String,
    pub locale: String,
    pub timezone: String,
    pub week_start: i16,
    pub is_instance_admin: bool,
    pub status: AccountStatus,
    pub totp_enabled: bool,
    pub password_hash: String,
    pub lockout: LockoutState,
    pub version: i64,
}

/// Finds an account by its normalized email address in the restricted system context.
pub async fn find_by_email(
    tx: &mut SystemTx,
    email: &Email,
) -> Result<Option<AccountRow>, AppError> {
    let row = sqlx::query(FIND_BY_EMAIL)
        .bind(email.as_str())
        .fetch_optional(tx.conn())
        .await
        .map_err(service_unavailable)?;
    row.as_ref().map(parse_account).transpose()
}

/// Locks and reloads an account before changing authentication state.
pub async fn lock_by_id(tx: &mut SystemTx, id: AccountId) -> Result<AccountRow, AppError> {
    let row = sqlx::query(LOCK_BY_ID)
        .bind(*id.as_uuid())
        .fetch_optional(tx.conn())
        .await
        .map_err(service_unavailable)?
        .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
    parse_account(&row)
}

/// Persists the state-machine result while the account row is locked.
pub async fn record_failure(
    tx: &mut SystemTx,
    id: AccountId,
    state: LockoutState,
    now: Instant,
) -> Result<(), AppError> {
    let count =
        i32::try_from(state.failed_count).map_err(|_| AppError::new(ErrorCode::InternalError))?;
    let changed = sqlx::query(
        "UPDATE public.accounts \
         SET failed_login_count = $2, failed_window_start = $3::timestamptz, \
             locked_until = $4::timestamptz, updated_at = $5::timestamptz \
         WHERE id = $1",
    )
    .bind(*id.as_uuid())
    .bind(count)
    .bind(state.window_start.map(|value| value.to_rfc3339()))
    .bind(state.locked_until.map(|value| value.to_rfc3339()))
    .bind(now.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?
    .rows_affected();
    if changed != 1 {
        return Err(AppError::new(ErrorCode::ServiceUnavailable));
    }
    Ok(())
}

/// Resets failures and records the successful-login timestamp.
pub async fn record_success(
    tx: &mut SystemTx,
    id: AccountId,
    now: Instant,
) -> Result<(), AppError> {
    let changed = sqlx::query(
        "UPDATE public.accounts \
         SET failed_login_count = 0, failed_window_start = NULL, locked_until = NULL, \
             last_login_at = $2::timestamptz, updated_at = $2::timestamptz \
         WHERE id = $1 AND status = 'active'",
    )
    .bind(*id.as_uuid())
    .bind(now.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?
    .rows_affected();
    if changed != 1 {
        return Err(AppError::new(ErrorCode::AccountDisabled));
    }
    Ok(())
}

const FIND_BY_EMAIL: &str = "SELECT id, email::text AS email, display_name, locale, timezone, \
    week_start, is_instance_admin, status, totp_enabled, password_hash, failed_login_count, \
    to_char(failed_window_start AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') \
        AS failed_window_start, \
    to_char(locked_until AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS locked_until, \
    version FROM public.accounts WHERE email = $1";
const LOCK_BY_ID: &str = "SELECT id, email::text AS email, display_name, locale, timezone, \
    week_start, is_instance_admin, status, totp_enabled, password_hash, failed_login_count, \
    to_char(failed_window_start AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') \
        AS failed_window_start, \
    to_char(locked_until AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS locked_until, \
    version FROM public.accounts WHERE id = $1 FOR UPDATE";

fn parse_account(row: &sqlx::postgres::PgRow) -> Result<AccountRow, AppError> {
    let failed_count: i32 = row
        .try_get("failed_login_count")
        .map_err(service_unavailable)?;
    let failed_count =
        u32::try_from(failed_count).map_err(|_| AppError::new(ErrorCode::InternalError))?;
    let window_start = row
        .try_get::<Option<String>, _>("failed_window_start")
        .map_err(service_unavailable)?
        .as_deref()
        .map(parse_instant)
        .transpose()?;
    let locked_until = row
        .try_get::<Option<String>, _>("locked_until")
        .map_err(service_unavailable)?
        .as_deref()
        .map(parse_instant)
        .transpose()?;
    let status = match row
        .try_get::<String, _>("status")
        .map_err(service_unavailable)?
        .as_str()
    {
        "active" => AccountStatus::Active,
        "disabled" => AccountStatus::Disabled,
        "deleting" => AccountStatus::Deleting,
        _ => return Err(AppError::new(ErrorCode::InternalError)),
    };
    Ok(AccountRow {
        id: AccountId::from_uuid(row.try_get("id").map_err(service_unavailable)?),
        email: row.try_get("email").map_err(service_unavailable)?,
        display_name: row.try_get("display_name").map_err(service_unavailable)?,
        locale: row.try_get("locale").map_err(service_unavailable)?,
        timezone: row.try_get("timezone").map_err(service_unavailable)?,
        week_start: row.try_get("week_start").map_err(service_unavailable)?,
        is_instance_admin: row
            .try_get("is_instance_admin")
            .map_err(service_unavailable)?,
        status,
        totp_enabled: row.try_get("totp_enabled").map_err(service_unavailable)?,
        password_hash: row.try_get("password_hash").map_err(service_unavailable)?,
        lockout: LockoutState {
            failed_count,
            window_start,
            locked_until,
        },
        version: row.try_get("version").map_err(service_unavailable)?,
    })
}

fn parse_instant(value: &str) -> Result<Instant, AppError> {
    Instant::parse_rfc3339(value).map_err(|_| AppError::new(ErrorCode::InternalError))
}

fn service_unavailable(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
