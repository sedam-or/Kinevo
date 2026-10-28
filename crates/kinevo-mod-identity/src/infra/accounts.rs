use kinevo_db::{SystemTx, Tx};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
    time::Instant,
    validate::net::Email,
};
use kinevo_ports::platform::{AccountProfile, AccountStatus, NewAccount};
use sqlx::Row;

use crate::domain::{lockout::LockoutState, profile::Profile};

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

/// Inserts an account row in its newly generated tenant context.
pub async fn create(
    tx: &mut Tx,
    input: &NewAccount,
    now: Instant,
) -> Result<AccountProfile, AppError> {
    let row = sqlx::query(
        "INSERT INTO public.accounts \
         (id, email, display_name, password_hash, locale, timezone, is_instance_admin, created_at, updated_at) \
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8::timestamptz, $8::timestamptz) \
         RETURNING id::text AS id, email::text AS email, display_name, locale, timezone, \
                   week_start, totp_enabled, is_instance_admin, version",
    )
    .bind(*input.id.as_uuid())
    .bind(input.email.as_str())
    .bind(&input.display_name)
    .bind(&input.password_hash)
    .bind(input.locale.as_str())
    .bind(input.timezone.as_str())
    .bind(input.is_instance_admin)
    .bind(now.to_rfc3339())
    .fetch_one(tx.conn())
    .await
    .map_err(map_create_error)?;

    Ok(AccountProfile {
        id: row.try_get("id").map_err(service_unavailable)?,
        email: row.try_get("email").map_err(service_unavailable)?,
        display_name: row.try_get("display_name").map_err(service_unavailable)?,
        locale: row.try_get("locale").map_err(service_unavailable)?,
        timezone: row.try_get("timezone").map_err(service_unavailable)?,
        week_start: row.try_get("week_start").map_err(service_unavailable)?,
        totp_enabled: row.try_get("totp_enabled").map_err(service_unavailable)?,
        is_instance_admin: row
            .try_get("is_instance_admin")
            .map_err(service_unavailable)?,
        version: row.try_get("version").map_err(service_unavailable)?,
    })
}

/// Loads only the safe profile fields visible to the account owner.
pub async fn get_profile(tx: &mut Tx, id: AccountId) -> Result<Profile, AppError> {
    let row = sqlx::query(
        "SELECT id, email::text AS email, display_name, locale, timezone, week_start, \
                totp_enabled, is_instance_admin, version \
         FROM public.accounts WHERE id = $1",
    )
    .bind(*id.as_uuid())
    .fetch_optional(tx.conn())
    .await
    .map_err(service_unavailable)?
    .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
    parse_profile(&row)
}

/// Applies one optimistic profile update and returns `None` when the version changed.
pub async fn update_profile(
    tx: &mut Tx,
    id: AccountId,
    expected_version: i64,
    profile: &Profile,
    now: Instant,
) -> Result<Option<Profile>, AppError> {
    let week_start = i16::from(profile.week_start);
    let row = sqlx::query(
        "UPDATE public.accounts \
         SET display_name = $3, locale = $4, timezone = $5, week_start = $6, \
             version = version + 1, updated_at = $7::timestamptz \
         WHERE id = $1 AND version = $2 \
         RETURNING id, email::text AS email, display_name, locale, timezone, week_start, \
                   totp_enabled, is_instance_admin, version",
    )
    .bind(*id.as_uuid())
    .bind(expected_version)
    .bind(&profile.display_name)
    .bind(profile.locale.as_str())
    .bind(profile.timezone.as_str())
    .bind(week_start)
    .bind(now.to_rfc3339())
    .fetch_optional(tx.conn())
    .await
    .map_err(service_unavailable)?;
    row.as_ref().map(parse_profile).transpose()
}

/// Reads the current password hash inside the account transaction.
pub async fn password_hash(tx: &mut Tx, id: AccountId) -> Result<String, AppError> {
    sqlx::query_scalar("SELECT password_hash FROM public.accounts WHERE id = $1 FOR UPDATE")
        .bind(*id.as_uuid())
        .fetch_optional(tx.conn())
        .await
        .map_err(service_unavailable)?
        .ok_or_else(|| AppError::new(ErrorCode::NotFound))
}

/// Updates the credential and profile version in the caller's transaction.
pub async fn update_password(
    tx: &mut Tx,
    id: AccountId,
    password_hash: &str,
    now: Instant,
) -> Result<(), AppError> {
    let changed = sqlx::query(
        "UPDATE public.accounts SET password_hash = $2, version = version + 1, \
             updated_at = $3::timestamptz WHERE id = $1",
    )
    .bind(*id.as_uuid())
    .bind(password_hash)
    .bind(now.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?
    .rows_affected();
    if changed != 1 {
        return Err(AppError::new(ErrorCode::NotFound));
    }
    Ok(())
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

fn parse_profile(row: &sqlx::postgres::PgRow) -> Result<Profile, AppError> {
    let locale = row
        .try_get::<String, _>("locale")
        .map_err(service_unavailable)?;
    let timezone = row
        .try_get::<String, _>("timezone")
        .map_err(service_unavailable)?;
    let week_start = row
        .try_get::<i16, _>("week_start")
        .map_err(service_unavailable)?;
    Ok(Profile {
        id: AccountId::from_uuid(row.try_get("id").map_err(service_unavailable)?),
        email: row.try_get("email").map_err(service_unavailable)?,
        display_name: row.try_get("display_name").map_err(service_unavailable)?,
        locale: kinevo_kernel::validate::tz::Locale::parse(&locale)
            .map_err(|_| AppError::new(ErrorCode::InternalError))?,
        timezone: kinevo_kernel::validate::tz::IanaTz::parse(&timezone)
            .map_err(|_| AppError::new(ErrorCode::InternalError))?,
        week_start: u8::try_from(week_start)
            .map_err(|_| AppError::new(ErrorCode::InternalError))?,
        totp_enabled: row.try_get("totp_enabled").map_err(service_unavailable)?,
        is_instance_admin: row
            .try_get("is_instance_admin")
            .map_err(service_unavailable)?,
        version: row.try_get("version").map_err(service_unavailable)?,
    })
}

fn parse_instant(value: &str) -> Result<Instant, AppError> {
    Instant::parse_rfc3339(value).map_err(|_| AppError::new(ErrorCode::InternalError))
}

fn service_unavailable(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}

fn map_create_error(error: sqlx::Error) -> AppError {
    if error
        .as_database_error()
        .and_then(|database| database.constraint())
        == Some("accounts_email_key")
    {
        AppError::new(ErrorCode::EmailTaken)
    } else {
        service_unavailable(error)
    }
}
