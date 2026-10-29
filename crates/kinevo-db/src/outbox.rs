//! PostgreSQL-backed transactional outbox and subscriber dispatcher.

use async_trait::async_trait;
use kinevo_kernel::{
    errors::{AppError, DetailValue, Details, ErrorCode},
    event::{EventEnvelope, EventKind, EventPayload},
    id::{AccountId, EventId, IdGen},
    time::Instant,
};
use sqlx::{Row, types::Uuid};
use std::{collections::BTreeMap, sync::Arc};

use crate::{
    Db, SystemTx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter, TxConn},
    jobs::backoff_seconds,
};

pub use crate::jobs::RetryPolicy;

/// Stable name of a module subscriber, such as `progress.rebuild`.
#[derive(Clone, Copy, Debug, Eq, Hash, Ord, PartialEq, PartialOrd)]
pub struct SubscriberName(&'static str);

impl SubscriberName {
    #[must_use]
    pub const fn new(value: &'static str) -> Self {
        Self(value)
    }

    #[must_use]
    pub const fn as_str(self) -> &'static str {
        self.0
    }
}

/// A module subscriber. Handlers must be idempotent by event id.
#[async_trait]
pub trait Subscriber: Send + Sync {
    fn name(&self) -> SubscriberName;

    async fn handle(&self, env: &EventEnvelope) -> Result<(), AppError>;
}

/// Optional audit hook called in the same system transaction as the dead transition.
#[async_trait]
pub trait DeadLetterHook: Send + Sync {
    async fn on_dead(
        &self,
        tx: &mut SystemTx,
        event_id: EventId,
        subscriber: SubscriberName,
        now: Instant,
    ) -> Result<(), AppError>;
}

/// Audit writer adapter for `outbox.dead` entries.
pub struct AuditDeadLetterHook {
    writer: AuditWriter,
}

impl AuditDeadLetterHook {
    #[must_use]
    pub fn new(ids: Arc<dyn IdGen>) -> Self {
        Self {
            writer: AuditWriter::new(ids),
        }
    }
}

#[async_trait]
impl DeadLetterHook for AuditDeadLetterHook {
    async fn on_dead(
        &self,
        tx: &mut SystemTx,
        event_id: EventId,
        subscriber: SubscriberName,
        now: Instant,
    ) -> Result<(), AppError> {
        self.writer
            .record(
                tx,
                AuditEntry {
                    account_id: None,
                    actor_id: None,
                    action: AuditAction::OutboxDead,
                    target_type: Some(AuditTarget::from_static("outbox_delivery")),
                    target_id: Some(format!("{}:{}", event_id, subscriber.as_str())),
                    details: Details::new()
                        .with_detail("entity_id", DetailValue::Id(*event_id.as_uuid()))
                        .with_detail("status", DetailValue::Str("dead")),
                    request_id: None,
                    occurred_at: now,
                },
            )
            .await
    }
}

/// Maps `(kind, version)` event contracts to enabled subscriber names.
#[derive(Default)]
pub struct SubscriberRegistry {
    entries: BTreeMap<(&'static str, u16), Vec<SubscriberName>>,
}

impl SubscriberRegistry {
    /// Registers a subscriber for one event contract. Duplicate registrations are ignored.
    pub fn register(&mut self, subscriber: SubscriberName, kind: &'static str, v: u16) {
        let names = self.entries.entry((kind, v)).or_default();
        if !names.contains(&subscriber) {
            names.push(subscriber);
            names.sort_unstable();
        }
    }

    /// Returns registered subscribers for the exact event contract.
    #[must_use]
    pub fn for_event(&self, kind: &str, v: u16) -> &[SubscriberName] {
        self.entries
            .iter()
            .find_map(|((registered_kind, registered_v), names)| {
                (*registered_kind == kind && *registered_v == v).then_some(names.as_slice())
            })
            .unwrap_or(&[])
    }
}

/// Runtime subscriber instances and their optional dead-letter audit hook.
#[derive(Default)]
pub struct SubscriberSet {
    handlers: BTreeMap<SubscriberName, Arc<dyn Subscriber>>,
    on_dead: Option<Arc<dyn DeadLetterHook>>,
}

impl SubscriberSet {
    #[must_use]
    pub fn new(handlers: Vec<Arc<dyn Subscriber>>) -> Self {
        Self {
            handlers: handlers
                .into_iter()
                .map(|handler| (handler.name(), handler))
                .collect(),
            on_dead: None,
        }
    }

    #[must_use]
    pub fn with_dead_letter_hook(mut self, hook: Arc<dyn DeadLetterHook>) -> Self {
        self.on_dead = Some(hook);
        self
    }

    fn get(&self, name: &str) -> Option<&Arc<dyn Subscriber>> {
        self.handlers
            .iter()
            .find_map(|(registered, handler)| (registered.as_str() == name).then_some(handler))
    }
}

/// Counts returned after one dispatch batch.
#[derive(Clone, Copy, Debug, Default, Eq, PartialEq)]
pub struct DispatchStats {
    pub delivered: u32,
    pub retried: u32,
    pub dead: u32,
}

/// Current outbox delivery counts for the admin summary and metrics.
#[derive(Clone, Copy, Debug, Default, Eq, PartialEq)]
pub struct OutboxCounts {
    pub pending: i64,
    pub dead: i64,
}

struct Delivery {
    event_id: EventId,
    subscriber: String,
    attempts: i32,
    next_attempt_at: String,
    handler_name: SubscriberName,
    envelope: EventEnvelope,
}

/// Inserts an event and its current subscribers in the caller's transaction.
#[allow(
    clippy::too_many_arguments,
    reason = "event row and subscriber deliveries must share the caller transaction"
)]
pub async fn emit(
    conn: &mut impl TxConn,
    _ids: &dyn IdGen,
    now: Instant,
    env: EventEnvelope,
    subscribers: &SubscriberRegistry,
) -> Result<EventId, AppError> {
    let version = i16::try_from(env.v).map_err(|_| AppError::new(ErrorCode::ValidationFailed))?;
    if version <= 0 || env.payload.validate().is_err() {
        return Err(AppError::new(ErrorCode::ValidationFailed));
    }
    let payload = env.payload.to_storage_json();
    sqlx::query(
        "INSERT INTO public.outbox_events (id, account_id, kind, payload, created_at, v) \
         VALUES ($1, $2, $3, $4, $5::timestamptz, $6)",
    )
    .bind(*env.id.as_uuid())
    .bind(env.account_id.map(|account| *account.as_uuid()))
    .bind(env.kind.as_str())
    .bind(payload)
    .bind(env.occurred_at.to_rfc3339())
    .bind(version)
    .execute(conn.conn())
    .await
    .map_err(database_error)?;

    for subscriber in subscribers.for_event(env.kind.as_str(), env.v) {
        sqlx::query(
            "INSERT INTO public.outbox_deliveries \
             (event_id, subscriber, state, attempts, next_attempt_at, last_error_code, delivered_at) \
             VALUES ($1, $2, 'pending', 0, $3::timestamptz, NULL, NULL)",
        )
        .bind(*env.id.as_uuid())
        .bind(subscriber.as_str())
        .bind(now.to_rfc3339())
        .execute(conn.conn())
        .await
        .map_err(database_error)?;
    }
    Ok(env.id)
}

/// Delivers at most the first due event for each `(account, subscriber)` stream.
///
/// The claim transaction commits before calling a handler. This deliberately allows a
/// handler to run without a database lock; a crash before its result is recorded causes
/// at-least-once redelivery.
#[allow(
    clippy::too_many_lines,
    reason = "claim, handler invocation, and result persistence form one dispatch contract"
)]
pub async fn dispatch_once(
    db: &Db,
    subscribers: &SubscriberSet,
    now: Instant,
    retry: RetryPolicy,
    max_attempts: u32,
    batch: u16,
) -> Result<DispatchStats, AppError> {
    if max_attempts == 0 {
        return Err(AppError::new(ErrorCode::ValidationFailed));
    }
    if batch == 0 {
        return Ok(DispatchStats::default());
    }

    let mut claim = SystemTx::begin(db).await?;
    let rows = sqlx::query(
        "SELECT e.seq, e.id, e.account_id, e.kind, e.v, e.payload, \
                to_char(e.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"') AS created_at, \
                d.subscriber, d.attempts, d.next_attempt_at::text AS next_attempt_at \
         FROM public.outbox_deliveries AS d \
         JOIN public.outbox_events AS e ON e.id = d.event_id \
         WHERE d.state = 'pending' AND d.next_attempt_at <= $1::timestamptz \
           AND NOT EXISTS ( \
               SELECT 1 FROM public.outbox_deliveries AS earlier_d \
               JOIN public.outbox_events AS earlier_e ON earlier_e.id = earlier_d.event_id \
               WHERE earlier_e.account_id IS NOT DISTINCT FROM e.account_id \
                 AND earlier_d.subscriber = d.subscriber \
                 AND earlier_e.seq < e.seq \
                 AND earlier_d.state NOT IN ('delivered', 'dead') \
           ) \
         ORDER BY e.account_id NULLS FIRST, d.subscriber, e.seq \
         FOR UPDATE OF d SKIP LOCKED LIMIT $2",
    )
    .bind(now.to_rfc3339())
    .bind(i64::from(batch))
    .fetch_all(claim.conn())
    .await
    .map_err(database_error)?;

    let mut deliveries = Vec::with_capacity(rows.len());
    for row in rows {
        let subscriber: String = row.try_get("subscriber").map_err(database_error)?;
        let Some(handler) = subscribers.get(&subscriber) else {
            return Err(AppError::new(ErrorCode::ServiceUnavailable));
        };
        let id: Uuid = row.try_get("id").map_err(database_error)?;
        let account_id: Option<Uuid> = row.try_get("account_id").map_err(database_error)?;
        let kind: String = row.try_get("kind").map_err(database_error)?;
        let version: i16 = row.try_get("v").map_err(database_error)?;
        let payload: serde_json::Value = row.try_get("payload").map_err(database_error)?;
        let created_at: String = row.try_get("created_at").map_err(database_error)?;
        let attempts: i32 = row.try_get("attempts").map_err(database_error)?;
        let next_attempt_at: String = row.try_get("next_attempt_at").map_err(database_error)?;
        let kind =
            EventKind::parse(&kind).ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        let v = u16::try_from(version).map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let occurred_at = Instant::parse_rfc3339(&created_at)
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let payload = EventPayload::from_storage_json(&payload)
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        deliveries.push(Delivery {
            event_id: EventId::from_uuid(id),
            subscriber,
            attempts,
            next_attempt_at,
            handler_name: handler.name(),
            envelope: EventEnvelope {
                id: EventId::from_uuid(id),
                kind,
                v,
                account_id: account_id.map(AccountId::from_uuid),
                occurred_at,
                payload,
            },
        });
    }
    claim.commit().await?;

    let mut stats = DispatchStats::default();
    for delivery in deliveries {
        let result = match subscribers.get(&delivery.subscriber) {
            Some(handler) => handler.handle(&delivery.envelope).await,
            None => Err(AppError::new(ErrorCode::ServiceUnavailable)),
        };
        let mut tx = SystemTx::begin(db).await?;
        if result.is_ok() {
            let changed = sqlx::query(
                "UPDATE public.outbox_deliveries \
                 SET state = 'delivered', delivered_at = $4::timestamptz, last_error_code = NULL \
                 WHERE event_id = $1 AND subscriber = $2 AND state = 'pending' \
                   AND attempts = $3 AND next_attempt_at = $5::timestamptz",
            )
            .bind(*delivery.event_id.as_uuid())
            .bind(&delivery.subscriber)
            .bind(delivery.attempts)
            .bind(now.to_rfc3339())
            .bind(&delivery.next_attempt_at)
            .execute(tx.conn())
            .await
            .map_err(database_error)?
            .rows_affected();
            if changed > 0 {
                stats.delivered = stats.delivered.saturating_add(1);
            }
        } else if let Err(error) = result {
            let attempts = delivery.attempts.saturating_add(1);
            if u32::try_from(attempts).unwrap_or(u32::MAX) >= max_attempts {
                let changed = sqlx::query(
                    "UPDATE public.outbox_deliveries \
                     SET state = 'dead', attempts = $3, last_error_code = $4, delivered_at = NULL \
                     WHERE event_id = $1 AND subscriber = $2 AND state = 'pending' \
                       AND attempts = $5 AND next_attempt_at = $6::timestamptz",
                )
                .bind(*delivery.event_id.as_uuid())
                .bind(&delivery.subscriber)
                .bind(attempts)
                .bind(error.code.as_str())
                .bind(delivery.attempts)
                .bind(&delivery.next_attempt_at)
                .execute(tx.conn())
                .await
                .map_err(database_error)?
                .rows_affected();
                if changed > 0 {
                    if let Some(hook) = &subscribers.on_dead {
                        hook.on_dead(&mut tx, delivery.event_id, delivery.handler_name, now)
                            .await?;
                    }
                    stats.dead = stats.dead.saturating_add(1);
                }
            } else {
                let delay = backoff_seconds(u32::try_from(attempts).unwrap_or(u32::MAX), retry);
                let next_attempt_at = now
                    .checked_add_seconds(delay)
                    .ok_or_else(|| AppError::new(ErrorCode::ValidationFailed))?;
                let changed = sqlx::query(
                    "UPDATE public.outbox_deliveries \
                     SET attempts = $3, next_attempt_at = $4::timestamptz, last_error_code = $5 \
                     WHERE event_id = $1 AND subscriber = $2 AND state = 'pending' \
                       AND attempts = $6 AND next_attempt_at = $7::timestamptz",
                )
                .bind(*delivery.event_id.as_uuid())
                .bind(&delivery.subscriber)
                .bind(attempts)
                .bind(next_attempt_at.to_rfc3339())
                .bind(error.code.as_str())
                .bind(delivery.attempts)
                .bind(&delivery.next_attempt_at)
                .execute(tx.conn())
                .await
                .map_err(database_error)?
                .rows_affected();
                if changed > 0 {
                    stats.retried = stats.retried.saturating_add(1);
                }
            }
        }
        tx.commit().await?;
    }
    Ok(stats)
}

/// Resets one dead delivery for an administrator retry job.
pub async fn retry_dead(
    tx: &mut SystemTx,
    event_id: EventId,
    subscriber: SubscriberName,
    now: Instant,
) -> Result<(), AppError> {
    retry_dead_named(tx, event_id, subscriber.as_str(), now).await
}

/// Resets one dead delivery for a path-provided registered subscriber name.
pub async fn retry_dead_named(
    tx: &mut SystemTx,
    event_id: EventId,
    subscriber: &str,
    now: Instant,
) -> Result<(), AppError> {
    let changed = sqlx::query(
        "UPDATE public.outbox_deliveries \
         SET state = 'pending', attempts = 0, next_attempt_at = $3::timestamptz, \
             last_error_code = NULL, delivered_at = NULL \
         WHERE event_id = $1 AND subscriber = $2 AND state = 'dead'",
    )
    .bind(*event_id.as_uuid())
    .bind(subscriber)
    .bind(now.to_rfc3339())
    .execute(tx.conn())
    .await
    .map_err(database_error)?
    .rows_affected();
    if changed > 0 {
        return Ok(());
    }
    let exists: bool = sqlx::query_scalar(
        "SELECT EXISTS (SELECT 1 FROM public.outbox_deliveries WHERE event_id = $1 AND subscriber = $2)",
    )
    .bind(*event_id.as_uuid())
    .bind(subscriber)
    .fetch_one(tx.conn())
    .await
    .map_err(database_error)?;
    Err(AppError::new(if exists {
        ErrorCode::NotDead
    } else {
        ErrorCode::NotFound
    }))
}

/// Returns the current state for one delivery without reading its event payload.
pub async fn delivery_state(
    conn: &mut impl TxConn,
    event_id: EventId,
    subscriber: &str,
) -> Result<Option<String>, AppError> {
    sqlx::query_scalar(
        "SELECT state FROM public.outbox_deliveries WHERE event_id = $1 AND subscriber = $2",
    )
    .bind(*event_id.as_uuid())
    .bind(subscriber)
    .fetch_optional(conn.conn())
    .await
    .map_err(database_error)
}

/// Returns pending and dead delivery totals without exposing payloads.
pub async fn counts(conn: &mut impl TxConn) -> Result<OutboxCounts, AppError> {
    let row = sqlx::query(
        "SELECT count(*) FILTER (WHERE state = 'pending')::bigint AS pending, \
                count(*) FILTER (WHERE state = 'dead')::bigint AS dead \
         FROM public.outbox_deliveries",
    )
    .fetch_one(conn.conn())
    .await
    .map_err(database_error)?;
    Ok(OutboxCounts {
        pending: row.try_get("pending").map_err(database_error)?,
        dead: row.try_get("dead").map_err(database_error)?,
    })
}

fn database_error(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
