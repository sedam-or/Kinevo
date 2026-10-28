use kinevo_db::{SystemTx, audit::TxConn};
use kinevo_kernel::{
    crypto::{encode_token, hash_token},
    errors::{AppError, ErrorCode},
    id::{AccountId, FamilyId, IdGen, TokenGen},
    secret::Secret,
    time::Instant,
};
use sqlx::{Row, types::Uuid};

use crate::domain::session::{FamilyRow, RevocationReason, SessionPolicy, TokenRow, idle_expiry};

/// First refresh token returned when a family is created.
pub struct NewSession {
    pub family_id: FamilyId,
    pub refresh_token: Secret<String>,
}

/// Safe metadata for one account session.
pub struct FamilySummary {
    pub family_id: FamilyId,
    pub account_id: AccountId,
    pub device_label: Option<String>,
    pub mfa: bool,
    pub created_at: Instant,
    pub last_used_at: Instant,
    pub absolute_expires_at: Instant,
    pub revoked_at: Option<Instant>,
    pub revoked_reason: Option<RevocationReason>,
}

/// Creates a family and its first hash-only refresh-token row in the caller's transaction.
#[allow(
    clippy::too_many_arguments,
    reason = "this signature matches the declared T-022 session-creation interface"
)]
pub async fn create_family_with_token(
    conn: &mut impl TxConn,
    ids: &dyn IdGen,
    tokens: &dyn TokenGen,
    now: Instant,
    account_id: AccountId,
    device_label: Option<&str>,
    mfa: bool,
    policy: SessionPolicy,
) -> Result<NewSession, AppError> {
    let family_id = FamilyId::from_uuid(ids.new_uuid_v7());
    let token_id = ids.new_uuid_v7();
    let raw_token = Secret::new(tokens.token_256());
    let refresh_token = Secret::new(encode_token(raw_token.expose()));
    let token_hash = hash_token(raw_token.expose());
    let absolute_expires_at = now
        .checked_add_seconds(i64::from(policy.absolute_ttl))
        .ok_or_else(internal_error)?;
    let idle_expires_at = idle_expiry(now, policy, absolute_expires_at);

    sqlx::query(
        "INSERT INTO public.refresh_token_families \
         (id, account_id, device_label, mfa, created_at, last_used_at, absolute_expires_at) \
         VALUES ($1, $2, $3, $4, $5::timestamptz, $5::timestamptz, $6::timestamptz)",
    )
    .bind(*family_id.as_uuid())
    .bind(*account_id.as_uuid())
    .bind(device_label)
    .bind(mfa)
    .bind(now.to_rfc3339())
    .bind(absolute_expires_at.to_rfc3339())
    .execute(conn.conn())
    .await
    .map_err(service_unavailable)?;

    sqlx::query(
        "INSERT INTO public.refresh_tokens \
         (id, family_id, account_id, token_hash, issued_at, idle_expires_at) \
         VALUES ($1, $2, $3, $4, $5::timestamptz, $6::timestamptz)",
    )
    .bind(token_id)
    .bind(*family_id.as_uuid())
    .bind(*account_id.as_uuid())
    .bind(token_hash.as_slice())
    .bind(now.to_rfc3339())
    .bind(idle_expires_at.to_rfc3339())
    .execute(conn.conn())
    .await
    .map_err(service_unavailable)?;

    Ok(NewSession {
        family_id,
        refresh_token,
    })
}

/// Locks the token row by hash and then its family row, in the required order.
#[allow(
    clippy::too_many_lines,
    reason = "the required token-then-family lock and typed row mapping are one operation"
)]
pub async fn lock_by_hash(
    tx: &mut SystemTx,
    hash: &[u8; 32],
) -> Result<Option<(TokenRow, FamilyRow)>, AppError> {
    let token = sqlx::query(
        "SELECT id, family_id, account_id, token_hash, \
                to_char(issued_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS issued_at, \
                to_char(idle_expires_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS idle_expires_at, \
                to_char(used_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS used_at, \
                replaced_by \
         FROM public.refresh_tokens WHERE token_hash = $1 FOR UPDATE",
    )
    .bind(hash.as_slice())
    .fetch_optional(tx.conn())
    .await
    .map_err(service_unavailable)?;
    let Some(token) = token else {
        return Ok(None);
    };

    let token_id: Uuid = token.try_get("id").map_err(service_unavailable)?;
    let family_id = FamilyId::from_uuid(token.try_get("family_id").map_err(service_unavailable)?);
    let account_id =
        AccountId::from_uuid(token.try_get("account_id").map_err(service_unavailable)?);
    let stored_hash = token
        .try_get::<Vec<u8>, _>("token_hash")
        .map_err(service_unavailable)?
        .try_into()
        .map_err(|_| internal_error())?;
    let issued_at = parse_instant(
        &token
            .try_get::<String, _>("issued_at")
            .map_err(service_unavailable)?,
    )?;
    let idle_expires_at = parse_instant(
        &token
            .try_get::<String, _>("idle_expires_at")
            .map_err(service_unavailable)?,
    )?;
    let used_at = token
        .try_get::<Option<String>, _>("used_at")
        .map_err(service_unavailable)?
        .as_deref()
        .map(parse_instant)
        .transpose()?;
    let replaced_by = token.try_get("replaced_by").map_err(service_unavailable)?;
    let token_row = TokenRow {
        id: token_id,
        family_id,
        account_id,
        token_hash: stored_hash,
        issued_at,
        idle_expires_at,
        used_at,
        replaced_by,
    };

    let family = sqlx::query(
        "SELECT id, account_id, device_label, mfa, \
                to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS created_at, \
                to_char(last_used_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS last_used_at, \
                to_char(absolute_expires_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS absolute_expires_at, \
                to_char(revoked_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS revoked_at, \
                revoked_reason \
         FROM public.refresh_token_families WHERE id = $1 FOR UPDATE",
    )
    .bind(*family_id.as_uuid())
    .fetch_one(tx.conn())
    .await
    .map_err(service_unavailable)?;
    let revoked_reason = family
        .try_get::<Option<String>, _>("revoked_reason")
        .map_err(service_unavailable)?
        .map(|value| RevocationReason::parse(&value).ok_or_else(internal_error))
        .transpose()?;
    let family_row = FamilyRow {
        id: FamilyId::from_uuid(family.try_get("id").map_err(service_unavailable)?),
        account_id: AccountId::from_uuid(
            family.try_get("account_id").map_err(service_unavailable)?,
        ),
        device_label: family
            .try_get("device_label")
            .map_err(service_unavailable)?,
        mfa: family.try_get("mfa").map_err(service_unavailable)?,
        created_at: parse_instant(
            &family
                .try_get::<String, _>("created_at")
                .map_err(service_unavailable)?,
        )?,
        last_used_at: parse_instant(
            &family
                .try_get::<String, _>("last_used_at")
                .map_err(service_unavailable)?,
        )?,
        absolute_expires_at: parse_instant(
            &family
                .try_get::<String, _>("absolute_expires_at")
                .map_err(service_unavailable)?,
        )?,
        revoked_at: family
            .try_get::<Option<String>, _>("revoked_at")
            .map_err(service_unavailable)?
            .as_deref()
            .map(parse_instant)
            .transpose()?,
        revoked_reason,
    };
    Ok(Some((token_row, family_row)))
}

/// Inserts the new token and atomically marks the old token and family as used.
pub async fn mark_rotated(
    tx: &mut SystemTx,
    old: &TokenRow,
    new_token_id: Uuid,
    new_hash: &[u8; 32],
    now: Instant,
    idle_expires_at: Instant,
) -> Result<(), AppError> {
    sqlx::query(
        "INSERT INTO public.refresh_tokens \
         (id, family_id, account_id, token_hash, issued_at, idle_expires_at) \
         VALUES ($1, $2, $3, $4, $5::timestamptz, $6::timestamptz)",
    )
    .bind(new_token_id)
    .bind(*old.family_id.as_uuid())
    .bind(*old.account_id.as_uuid())
    .bind(new_hash.as_slice())
    .bind(now.to_rfc3339())
    .bind(idle_expires_at.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?;

    let changed = sqlx::query(
        "UPDATE public.refresh_tokens SET used_at = $2::timestamptz, replaced_by = $3 \
         WHERE id = $1 AND used_at IS NULL",
    )
    .bind(old.id)
    .bind(now.to_rfc3339())
    .bind(new_token_id)
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?
    .rows_affected();
    if changed != 1 {
        return Err(internal_error());
    }

    let changed = sqlx::query(
        "UPDATE public.refresh_token_families SET last_used_at = $2::timestamptz WHERE id = $1",
    )
    .bind(*old.family_id.as_uuid())
    .bind(now.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?
    .rows_affected();
    if changed != 1 {
        return Err(internal_error());
    }
    Ok(())
}

/// Revokes one family if it is not already revoked.
pub async fn revoke_family(
    conn: &mut impl TxConn,
    family_id: FamilyId,
    reason: RevocationReason,
    now: Instant,
) -> Result<u64, AppError> {
    let result = sqlx::query(
        "UPDATE public.refresh_token_families \
         SET revoked_at = $2::timestamptz, revoked_reason = $3 \
         WHERE id = $1 AND revoked_at IS NULL",
    )
    .bind(*family_id.as_uuid())
    .bind(now.to_rfc3339())
    .bind(reason.as_str())
    .execute(conn.conn())
    .await
    .map_err(service_unavailable)?;
    Ok(result.rows_affected())
}

/// Revokes every active family for an account except an optional current family.
pub async fn revoke_all_for_account(
    conn: &mut impl TxConn,
    account_id: AccountId,
    reason: RevocationReason,
    now: Instant,
    except: Option<FamilyId>,
) -> Result<u64, AppError> {
    let result = sqlx::query(
        "UPDATE public.refresh_token_families \
         SET revoked_at = $3::timestamptz, revoked_reason = $4 \
         WHERE account_id = $1 AND revoked_at IS NULL \
           AND ($2::uuid IS NULL OR id <> $2)",
    )
    .bind(*account_id.as_uuid())
    .bind(except.map(|family| *family.as_uuid()))
    .bind(now.to_rfc3339())
    .bind(reason.as_str())
    .execute(conn.conn())
    .await
    .map_err(service_unavailable)?;
    Ok(result.rows_affected())
}

/// Lists session metadata without exposing refresh-token material.
pub async fn list_families(
    conn: &mut impl TxConn,
    account_id: AccountId,
) -> Result<Vec<FamilySummary>, AppError> {
    let rows = sqlx::query(
        "SELECT id, account_id, device_label, mfa, \
                to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS created_at, \
                to_char(last_used_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS last_used_at, \
                to_char(absolute_expires_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS absolute_expires_at, \
                to_char(revoked_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS revoked_at, revoked_reason \
         FROM public.refresh_token_families WHERE account_id = $1 \
         ORDER BY last_used_at DESC, id",
    )
    .bind(*account_id.as_uuid())
    .fetch_all(conn.conn())
    .await
    .map_err(service_unavailable)?;
    rows.into_iter()
        .map(|row| {
            let revoked_reason = row
                .try_get::<Option<String>, _>("revoked_reason")
                .map_err(service_unavailable)?
                .map(|value| RevocationReason::parse(&value).ok_or_else(internal_error))
                .transpose()?;
            Ok(FamilySummary {
                family_id: FamilyId::from_uuid(row.try_get("id").map_err(service_unavailable)?),
                account_id: AccountId::from_uuid(
                    row.try_get("account_id").map_err(service_unavailable)?,
                ),
                device_label: row.try_get("device_label").map_err(service_unavailable)?,
                mfa: row.try_get("mfa").map_err(service_unavailable)?,
                created_at: parse_instant(
                    &row.try_get::<String, _>("created_at")
                        .map_err(service_unavailable)?,
                )?,
                last_used_at: parse_instant(
                    &row.try_get::<String, _>("last_used_at")
                        .map_err(service_unavailable)?,
                )?,
                absolute_expires_at: parse_instant(
                    &row.try_get::<String, _>("absolute_expires_at")
                        .map_err(service_unavailable)?,
                )?,
                revoked_at: row
                    .try_get::<Option<String>, _>("revoked_at")
                    .map_err(service_unavailable)?
                    .as_deref()
                    .map(parse_instant)
                    .transpose()?,
                revoked_reason,
            })
        })
        .collect()
}

/// Marks active families whose current token or fixed absolute lifetime has expired.
pub async fn prune_expired(tx: &mut SystemTx, cutoff: Instant) -> Result<u64, AppError> {
    let result = sqlx::query(
        "UPDATE public.refresh_token_families f \
         SET revoked_at = $1::timestamptz, revoked_reason = 'expired' \
         WHERE f.revoked_at IS NULL AND (f.absolute_expires_at <= $1::timestamptz \
           OR EXISTS (SELECT 1 FROM public.refresh_tokens t \
                      WHERE t.family_id = f.id AND t.used_at IS NULL \
                        AND t.idle_expires_at <= $1::timestamptz))",
    )
    .bind(cutoff.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(service_unavailable)?;
    Ok(result.rows_affected())
}

fn parse_instant(value: &str) -> Result<Instant, AppError> {
    Instant::parse_rfc3339(value).map_err(|_| internal_error())
}

fn service_unavailable(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}

fn internal_error() -> AppError {
    AppError::new(ErrorCode::InternalError)
}
