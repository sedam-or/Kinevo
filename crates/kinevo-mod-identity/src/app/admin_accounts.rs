//! System-level account administration use cases.

use std::sync::Arc;

use kinevo_db::{
    SystemTx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    outbox::{self, SubscriberRegistry},
};
use kinevo_kernel::{
    errors::{AppError, DetailValue, Details, ErrorCode},
    event::{EventEnvelope, EventKind, EventPayload},
    id::{AccountId, EventId, IdGen},
    page::{CursorCodec, CursorPart, CursorPosition, Page, PageRequest},
    time::{Clock, Instant},
};
use kinevo_ports::platform::AccountStatus;
use serde::Serialize;
use sqlx::Row;
use utoipa::ToSchema;

use crate::{
    domain::accounts::can_change_status,
    infra::{admin_accounts::parse_status, sessions},
};

/// Account metadata exposed to instance administrators.
#[derive(Clone, Debug, Eq, PartialEq, Serialize, ToSchema)]
pub struct AdminAccountRow {
    #[schema(value_type = String)]
    pub id: AccountId,
    pub email: String,
    pub display_name: String,
    pub status: AccountStatus,
    #[schema(value_type = String)]
    pub created_at: Instant,
    #[schema(value_type = Option<String>)]
    pub last_login_at: Option<Instant>,
}

/// Coordinates administrator account listing and status changes.
pub struct AdminAccountsService {
    db: kinevo_db::Db,
    ids: Arc<dyn IdGen>,
    clock: Arc<dyn Clock>,
    subscribers: SubscriberRegistry,
    cursor: CursorCodec,
}

impl AdminAccountsService {
    #[must_use]
    pub fn new(
        db: kinevo_db::Db,
        ids: Arc<dyn IdGen>,
        clock: Arc<dyn Clock>,
        subscribers: SubscriberRegistry,
        cursor: CursorCodec,
    ) -> Self {
        Self {
            db,
            ids,
            clock,
            subscribers,
            cursor,
        }
    }

    /// Lists only the six documented account metadata fields in a stable keyset order.
    pub async fn list(&self, request: PageRequest) -> Result<Page<AdminAccountRow>, AppError> {
        let (created_before, id_before) = match request.cursor {
            None => (None, None),
            Some(CursorPosition(parts)) => match parts.as_slice() {
                [CursorPart::Instant(created), CursorPart::Id(id)] => {
                    (Some(created.to_rfc3339()), Some(*id))
                }
                _ => return Err(AppError::new(ErrorCode::CursorInvalid)),
            },
        };
        let mut tx = SystemTx::begin(&self.db).await?;
        let limit = i64::from(request.limit).saturating_add(1);
        let rows = sqlx::query(
            "SELECT id, email::text AS email, display_name, status, \
                    to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS created_at, \
                    to_char(last_login_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS last_login_at \
             FROM public.accounts \
             WHERE ($1::timestamptz IS NULL OR (created_at, id) < ($1::timestamptz, $2::uuid)) \
             ORDER BY created_at DESC, id DESC LIMIT $3",
        )
        .bind(created_before)
        .bind(id_before)
        .bind(limit)
        .fetch_all(tx.conn())
        .await
        .map_err(service_unavailable)?;
        tx.commit().await?;

        let rows = rows
            .iter()
            .map(parse_admin_row)
            .collect::<Result<Vec<_>, _>>()?;
        Ok(Page::from_rows(rows, request.limit, |row| {
            self.cursor.encode(
                "/api/v1/admin/accounts",
                "created_at_desc,id_desc",
                &CursorPosition(vec![
                    CursorPart::Instant(row.created_at),
                    CursorPart::Id(*row.id.as_uuid()),
                ]),
            )
        }))
    }

    /// Applies one optimistic status change and its revocation, audit, and event atomically.
    #[allow(
        clippy::too_many_lines,
        reason = "the status change must commit with revocation, audit and outbox effects"
    )]
    pub async fn set_status(
        &self,
        actor: AccountId,
        target: AccountId,
        new_status: AccountStatus,
        expected_version: i64,
    ) -> Result<(), AppError> {
        if matches!(new_status, AccountStatus::Deleting) {
            return Err(AppError::new(ErrorCode::InvalidTransition));
        }
        let now = self.clock.now();
        let mut tx = SystemTx::begin(&self.db).await?;
        let active_admins = sqlx::query_scalar::<_, sqlx::types::Uuid>(
            "SELECT id FROM public.accounts WHERE is_instance_admin AND status='active' ORDER BY id FOR UPDATE",
        )
        .fetch_all(tx.conn())
        .await
        .map_err(service_unavailable)?;
        let target_row = sqlx::query(
            "SELECT status, is_instance_admin, version FROM public.accounts WHERE id=$1 FOR UPDATE",
        )
        .bind(*target.as_uuid())
        .fetch_optional(tx.conn())
        .await
        .map_err(service_unavailable)?
        .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
        let current_status = parse_status(
            &target_row
                .try_get::<String, _>("status")
                .map_err(service_unavailable)?,
        )?;
        let target_is_admin: bool = target_row
            .try_get("is_instance_admin")
            .map_err(service_unavailable)?;
        let version: i64 = target_row.try_get("version").map_err(service_unavailable)?;
        if new_status != current_status
            && target_is_admin
            && current_status == AccountStatus::Active
        {
            can_change_status(
                actor,
                target,
                i64::try_from(active_admins.len()).unwrap_or(i64::MAX),
                new_status,
            )?;
        }
        if version != expected_version {
            return Err(AppError::new(ErrorCode::VersionConflict));
        }
        if current_status == AccountStatus::Deleting {
            return Err(AppError::new(ErrorCode::InvalidTransition));
        }
        let changed = sqlx::query(
            "UPDATE public.accounts SET status=$2, version=version+1, updated_at=$3::timestamptz \
             WHERE id=$1 AND version=$4",
        )
        .bind(*target.as_uuid())
        .bind(new_status.as_str())
        .bind(now.to_rfc3339())
        .bind(expected_version)
        .execute(tx.conn())
        .await
        .map_err(service_unavailable)?
        .rows_affected();
        if changed != 1 {
            return Err(AppError::new(ErrorCode::VersionConflict));
        }
        if new_status == AccountStatus::Disabled {
            sessions::revoke_all_for_account(
                &mut tx,
                target,
                crate::domain::session::RevocationReason::Admin,
                now,
                None,
            )
            .await?;
        }
        AuditWriter::new(Arc::clone(&self.ids))
            .record(
                &mut tx,
                AuditEntry {
                    account_id: Some(target),
                    actor_id: Some(*actor.as_uuid()),
                    action: AuditAction::AccountStatusChanged,
                    target_type: Some(AuditTarget::ACCOUNT),
                    target_id: Some(target.to_string()),
                    details: Details::new()
                        .with_detail("from", DetailValue::Str(current_status.as_str()))
                        .with_detail("to", DetailValue::Str(new_status.as_str())),
                    request_id: None,
                    occurred_at: now,
                },
            )
            .await?;
        if new_status == AccountStatus::Disabled {
            outbox::emit(
                &mut tx,
                self.ids.as_ref(),
                now,
                EventEnvelope {
                    id: EventId::from_uuid(self.ids.new_uuid_v7()),
                    kind: EventKind::AccountDisabled,
                    v: 1,
                    account_id: Some(target),
                    occurred_at: now,
                    payload: EventPayload::new().with_id("account_id", &target),
                },
                &self.subscribers,
            )
            .await?;
        }
        tx.commit().await
    }
}

fn parse_admin_row(row: &sqlx::postgres::PgRow) -> Result<AdminAccountRow, AppError> {
    let created_at = row
        .try_get::<String, _>("created_at")
        .map_err(service_unavailable)?;
    let last_login_at = row
        .try_get::<Option<String>, _>("last_login_at")
        .map_err(service_unavailable)?;
    Ok(AdminAccountRow {
        id: AccountId::from_uuid(row.try_get("id").map_err(service_unavailable)?),
        email: row.try_get("email").map_err(service_unavailable)?,
        display_name: row.try_get("display_name").map_err(service_unavailable)?,
        status: parse_status(
            &row.try_get::<String, _>("status")
                .map_err(service_unavailable)?,
        )?,
        created_at: Instant::parse_rfc3339(&created_at)
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?,
        last_login_at: last_login_at
            .as_deref()
            .map(Instant::parse_rfc3339)
            .transpose()
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?,
    })
}

fn service_unavailable(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
