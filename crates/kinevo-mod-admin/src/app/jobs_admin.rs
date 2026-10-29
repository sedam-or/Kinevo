//! Admin job summary and dead-outbox retry use cases.

use std::sync::Arc;

use kinevo_db::{
    Db, Tx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    jobs::{self, EnqueueOutcome, JobKind, NewJob},
    outbox,
};
use kinevo_kernel::{
    errors::{AppError, DetailValue, Details, ErrorCode},
    id::{AccountId, EventId, IdGen},
    policy::PolicyKey,
    time::Clock,
};
use kinevo_ports::platform::PolicyReader;
use serde::Serialize;
use serde_json::json;
const RETRY_KIND: JobKind = JobKind::from_valid_static("outbox.retry");

/// Counts and safe queue-age information returned to an instance administrator.
#[derive(Clone, Debug, Serialize)]
pub struct JobsSummary {
    pub jobs: Vec<JobCountSummary>,
    pub oldest_queued_age_seconds: Option<i64>,
    pub dead_deliveries: i64,
}

/// One payload-free count by registered job kind and queue state.
#[derive(Clone, Debug, Serialize)]
pub struct JobCountSummary {
    pub kind: String,
    pub state: String,
    pub count: i64,
}

/// Coordinates admin reads and retry-job creation using injected platform ports.
pub struct JobsAdminService {
    db: Db,
    ids: Arc<dyn IdGen>,
    clock: Arc<dyn Clock>,
    policies: Arc<dyn PolicyReader>,
    audit: AuditWriter,
}

impl JobsAdminService {
    /// Creates a service with the platform policy reader and injected time/id sources.
    #[must_use]
    pub fn new(
        db: Db,
        ids: Arc<dyn IdGen>,
        clock: Arc<dyn Clock>,
        policies: Arc<dyn PolicyReader>,
    ) -> Self {
        Self {
            db,
            audit: AuditWriter::new(Arc::clone(&ids)),
            ids,
            clock,
            policies,
        }
    }

    /// Aggregates queue metadata without returning job payloads.
    pub async fn summary(&self, actor: AccountId) -> Result<JobsSummary, AppError> {
        let now = self.clock.now();
        let mut tx = Tx::begin_admin(&self.db, actor).await?;
        let jobs = jobs::summary(&mut tx)
            .await?
            .into_iter()
            .map(|row| JobCountSummary {
                kind: row.kind,
                state: row.state,
                count: row.count,
            })
            .collect();
        let oldest_queued_age_seconds = jobs::oldest_queued_age_seconds(&mut tx, now).await?;
        let dead_deliveries = outbox::counts(&mut tx).await?.dead;
        tx.commit().await?;
        Ok(JobsSummary {
            jobs,
            oldest_queued_age_seconds,
            dead_deliveries,
        })
    }

    /// Enqueues one retry job for an existing dead delivery and audits the request.
    pub async fn retry_dead(
        &self,
        actor: AccountId,
        event_id: EventId,
        subscriber: &str,
    ) -> Result<(), AppError> {
        if !valid_subscriber(subscriber) {
            return Err(AppError::new(ErrorCode::NotFound));
        }
        let now = self.clock.now();
        let values = self.policies.snapshot_values(None, now).await?;
        let max_attempts = policy_value(
            &values,
            &kinevo_kernel::policy::registry::platform::JOB_RETRY_MAX_ATTEMPTS,
        )?
        .try_into()
        .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        let mut tx = Tx::begin_admin(&self.db, actor).await?;
        match outbox::delivery_state(&mut tx, event_id, subscriber).await? {
            Some(state) if state == "dead" => {}
            Some(_) => return Err(AppError::new(ErrorCode::NotDead)),
            None => return Err(AppError::new(ErrorCode::NotFound)),
        }
        let outcome = jobs::enqueue(
            &mut tx,
            self.ids.as_ref(),
            now,
            NewJob {
                account_id: None,
                kind: RETRY_KIND,
                unique_key: Some(self.ids.new_uuid_v7().to_string()),
                payload: json!({
                    "event_id": event_id.to_string(),
                    "subscriber": subscriber,
                }),
                run_at: now,
                max_attempts,
            },
        )
        .await?;
        if matches!(outcome, EnqueueOutcome::Inserted(_)) {
            self.audit
                .record(
                    &mut tx,
                    AuditEntry {
                        account_id: None,
                        actor_id: Some(*actor.as_uuid()),
                        action: AuditAction::OutboxRetried,
                        target_type: Some(AuditTarget::from_static("outbox_delivery")),
                        target_id: Some(format!("{event_id}:{subscriber}")),
                        details: Details::new()
                            .with_detail("entity_id", DetailValue::Id(*event_id.as_uuid()))
                            .with_detail("status", DetailValue::Str("queued")),
                        request_id: None,
                        occurred_at: now,
                    },
                )
                .await?;
        }
        tx.commit().await
    }
}

/// A policy lookup which fails closed if the registry and resolved snapshot diverge.
pub(crate) fn policy_value<T: kinevo_kernel::policy::PolicyValueType>(
    policies: &kinevo_ports::platform::ResolvedPolicies,
    key: &PolicyKey<T>,
) -> Result<T, AppError> {
    policies
        .resolved(key.name)
        .and_then(|resolved| key.get(resolved))
        .ok_or_else(|| AppError::new(ErrorCode::InternalError))
}

fn valid_subscriber(value: &str) -> bool {
    !value.is_empty()
        && value.len() <= 64
        && value.bytes().all(|byte| {
            byte.is_ascii_lowercase() || byte.is_ascii_digit() || matches!(byte, b'.' | b'_' | b'-')
        })
}
