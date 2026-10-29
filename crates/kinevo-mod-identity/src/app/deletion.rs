//! Account deletion request use case.

use std::sync::Arc;

use kinevo_db::{
    Db, SystemTx, Tx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    jobs::{self, JobKind, NewJob},
    outbox::{self, SubscriberRegistry},
};
use kinevo_kernel::{
    errors::{AppError, DetailValue, Details, ErrorCode, FieldCode, FieldError},
    event::{EventEnvelope, EventKind, EventPayload},
    id::{AccountId, EventId, IdGen},
    policy::{
        PolicyDuration,
        registry::{identity, platform},
    },
    secret::Secret,
    time::Clock,
};
use kinevo_ports::platform::PolicyReader;
use sqlx::Row;

use crate::{
    app::login::PasswordWork,
    domain::{accounts::can_change_status, session::RevocationReason},
    infra::{hasher::VerifyOutcome, sessions},
};

/// Coordinates password-confirmed account deletion.
pub struct DeletionService {
    db: Db,
    ids: Arc<dyn IdGen>,
    passwords: Arc<dyn PasswordWork>,
    policies: Arc<dyn PolicyReader>,
    clock: Arc<dyn Clock>,
    subscribers: SubscriberRegistry,
}

impl DeletionService {
    #[allow(
        clippy::too_many_arguments,
        reason = "deletion uses explicit platform ports"
    )]
    #[must_use]
    pub fn new(
        db: Db,
        ids: Arc<dyn IdGen>,
        passwords: Arc<dyn PasswordWork>,
        policies: Arc<dyn PolicyReader>,
        clock: Arc<dyn Clock>,
        subscribers: SubscriberRegistry,
    ) -> Self {
        Self {
            db,
            ids,
            passwords,
            policies,
            clock,
            subscribers,
        }
    }

    /// Marks the authenticated account deleting and queues its purge atomically.
    #[allow(
        clippy::too_many_lines,
        reason = "status, session revocation, job, event and audit share one transaction"
    )]
    pub async fn request_deletion(
        &self,
        account: AccountId,
        password: Secret<String>,
        confirm: &str,
    ) -> Result<(), AppError> {
        if confirm != "delete my account" {
            return Err(AppError::validation(vec![FieldError {
                path: "confirm".to_owned(),
                code: FieldCode::EnumInvalid,
                details: Details::new(),
            }]));
        }

        let now = self.clock.now();
        let policies = self.policies.snapshot_values(Some(account), now).await?;
        let delay = policies
            .get::<PolicyDuration>(&identity::PURGE_DELAY)
            .map(|value| value.0)
            .ok_or_else(service_unavailable)?;
        let max_attempts = policies
            .get(&platform::JOB_RETRY_MAX_ATTEMPTS)
            .and_then(|value| u8::try_from(value).ok())
            .filter(|value| *value > 0)
            .ok_or_else(service_unavailable)?;
        let run_at = now
            .checked_add_seconds(delay)
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;

        let mut lookup = SystemTx::begin(&self.db).await?;
        let is_admin = sqlx::query_scalar::<_, bool>(
            "SELECT is_instance_admin FROM public.accounts WHERE id=$1",
        )
        .bind(*account.as_uuid())
        .fetch_optional(lookup.conn())
        .await
        .map_err(database_error)?
        .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
        lookup.commit().await?;

        let mut tx = if is_admin {
            Tx::begin_admin(&self.db, account).await?
        } else {
            Tx::begin(&self.db, account).await?
        };
        let row = sqlx::query(
            "SELECT password_hash, status, is_instance_admin FROM public.accounts \
             WHERE id=$1 FOR UPDATE",
        )
        .bind(*account.as_uuid())
        .fetch_optional(tx.conn())
        .await
        .map_err(database_error)?
        .ok_or_else(|| AppError::new(ErrorCode::NotFound))?;
        let status = row.try_get::<String, _>("status").map_err(database_error)?;
        if status != "active" {
            return Err(AppError::new(ErrorCode::AccountDisabled));
        }
        let stored_hash = row
            .try_get::<String, _>("password_hash")
            .map_err(database_error)?;
        if matches!(
            self.passwords.verify(&password, &stored_hash).await,
            VerifyOutcome::Wrong
        ) {
            return Err(AppError::new(ErrorCode::CurrentPasswordIncorrect));
        }

        let target_is_admin = row
            .try_get::<bool, _>("is_instance_admin")
            .map_err(database_error)?;
        if target_is_admin {
            if !is_admin {
                return Err(service_unavailable());
            }
            let active_admins = sqlx::query_scalar::<_, sqlx::types::Uuid>(
                "SELECT id FROM public.accounts WHERE is_instance_admin AND status='active' \
                 ORDER BY id FOR UPDATE",
            )
            .fetch_all(tx.conn())
            .await
            .map_err(database_error)?;
            can_change_status(
                account,
                account,
                i64::try_from(active_admins.len()).unwrap_or(i64::MAX),
                kinevo_ports::platform::AccountStatus::Disabled,
            )?;
        }

        let changed = sqlx::query(
            "UPDATE public.accounts SET status='deleting', version=version+1, updated_at=$2::timestamptz \
             WHERE id=$1 AND status='active'",
        )
        .bind(*account.as_uuid())
        .bind(now.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(database_error)?
        .rows_affected();
        if changed != 1 {
            return Err(AppError::new(ErrorCode::AccountDisabled));
        }

        sessions::revoke_all_for_account(&mut tx, account, RevocationReason::Logout, now, None)
            .await?;
        jobs::enqueue(
            &mut tx,
            self.ids.as_ref(),
            now,
            NewJob {
                account_id: Some(account),
                kind: JobKind::from_valid_static("account.purge"),
                unique_key: Some(account.to_string()),
                payload: serde_json::json!({ "account_id": account.to_string() }),
                run_at,
                max_attempts,
            },
        )
        .await?;
        outbox::emit(
            &mut tx,
            self.ids.as_ref(),
            now,
            EventEnvelope {
                id: EventId::from_uuid(self.ids.new_uuid_v7()),
                kind: EventKind::AccountDeletionRequested,
                v: 1,
                account_id: Some(account),
                occurred_at: now,
                payload: EventPayload::new().with_id("account_id", &account),
            },
            &self.subscribers,
        )
        .await?;
        AuditWriter::new(Arc::clone(&self.ids))
            .record(
                &mut tx,
                AuditEntry {
                    account_id: Some(account),
                    actor_id: Some(*account.as_uuid()),
                    action: AuditAction::AccountStatusChanged,
                    target_type: Some(AuditTarget::ACCOUNT),
                    target_id: Some(account.to_string()),
                    details: Details::new()
                        .with_detail("from", DetailValue::Str("active"))
                        .with_detail("to", DetailValue::Str("deleting")),
                    request_id: None,
                    occurred_at: now,
                },
            )
            .await?;
        tx.commit().await
    }
}

fn service_unavailable() -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}

fn database_error(_: sqlx::Error) -> AppError {
    service_unavailable()
}
