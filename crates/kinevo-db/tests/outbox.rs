#![cfg(feature = "testing")]
#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "outbox acceptance tests use isolated synthetic PostgreSQL fixtures"
)]

#[allow(
    dead_code,
    reason = "shared support fixtures are also used by sibling database suites"
)]
mod support;

use async_trait::async_trait;
use kinevo_db::{
    SystemTx, Tx,
    outbox::{
        DeadLetterHook, DispatchStats, RetryPolicy, Subscriber, SubscriberName, SubscriberRegistry,
        SubscriberSet, dispatch_once, emit, retry_dead,
    },
    require_db,
    testing::TestApp,
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    event::{EventEnumValue, EventEnvelope, EventKind, EventPayload},
    id::{AccountId, EventId, IdGen},
    time::Instant,
};
use sqlx::{Executor, Row};
use std::sync::{
    Arc, Mutex,
    atomic::{AtomicUsize, Ordering},
};

macro_rules! test_app {
    () => {{
        let sources = support::migration_sources();
        require_db!(app = TestApp::builder().migrations(&sources).build().await);
        app
    }};
}

fn instant(value: &str) -> Instant {
    Instant::parse_rfc3339(value).expect("fixture instant should be valid")
}

async fn seed_account(app: &TestApp, account_id: AccountId) {
    let mut tx = Tx::begin(&app.db, account_id)
        .await
        .expect("account transaction should begin");
    support::fixtures::insert_account(&mut tx, account_id, &format!("{account_id}@example.test"))
        .await
        .expect("synthetic account should insert");
    tx.commit()
        .await
        .expect("account fixture transaction should commit");
}

fn envelope(app: &TestApp, account_id: Option<AccountId>, kind: EventKind) -> EventEnvelope {
    EventEnvelope {
        id: EventId::from_uuid(app.ids.new_uuid_v7()),
        kind,
        v: 1,
        account_id,
        occurred_at: instant("2026-11-02T00:00:00.123Z"),
        payload: EventPayload::new()
            .with_id(
                "account_id",
                &account_id.unwrap_or_else(|| app.new_account()),
            )
            .with_enum("status", EventEnumValue::new("active"))
            .with_int("version", 1),
    }
}

fn registered(registrations: &[(&'static str, &'static str, u16)]) -> SubscriberRegistry {
    let mut registry = SubscriberRegistry::default();
    for &(name, kind, version) in registrations {
        registry.register(SubscriberName::new(name), kind, version);
    }
    registry
}

async fn emit_event(
    app: &TestApp,
    account_id: AccountId,
    kind: EventKind,
    registry: &SubscriberRegistry,
    now: Instant,
) -> EventId {
    let mut tx = Tx::begin(&app.db, account_id)
        .await
        .expect("event transaction should begin");
    let id = emit(
        &mut tx,
        app.ids.as_ref(),
        now,
        envelope(app, Some(account_id), kind),
        registry,
    )
    .await
    .expect("event should emit");
    tx.commit().await.expect("event transaction should commit");
    id
}

struct RecordingSubscriber {
    name: SubscriberName,
    seen: Arc<Mutex<Vec<EventId>>>,
    payloads: Arc<Mutex<Vec<EventPayload>>>,
    occurred_at: Arc<Mutex<Vec<Instant>>>,
    failures_left: AtomicUsize,
    always_fail: bool,
}

impl RecordingSubscriber {
    fn new(name: &'static str) -> Self {
        Self {
            name: SubscriberName::new(name),
            seen: Arc::new(Mutex::new(Vec::new())),
            payloads: Arc::new(Mutex::new(Vec::new())),
            occurred_at: Arc::new(Mutex::new(Vec::new())),
            failures_left: AtomicUsize::new(0),
            always_fail: false,
        }
    }

    fn failing(name: &'static str, failures: usize, always_fail: bool) -> Self {
        Self {
            name: SubscriberName::new(name),
            seen: Arc::new(Mutex::new(Vec::new())),
            payloads: Arc::new(Mutex::new(Vec::new())),
            occurred_at: Arc::new(Mutex::new(Vec::new())),
            failures_left: AtomicUsize::new(failures),
            always_fail,
        }
    }

    fn calls(&self) -> Vec<EventId> {
        self.seen
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
            .clone()
    }

    fn should_fail(&self) -> bool {
        if self.always_fail {
            return true;
        }
        loop {
            let remaining = self.failures_left.load(Ordering::SeqCst);
            if remaining == 0 {
                return false;
            }
            if self
                .failures_left
                .compare_exchange(remaining, remaining - 1, Ordering::SeqCst, Ordering::SeqCst)
                .is_ok()
            {
                return true;
            }
        }
    }
}

#[async_trait]
impl Subscriber for RecordingSubscriber {
    fn name(&self) -> SubscriberName {
        self.name
    }

    async fn handle(&self, env: &EventEnvelope) -> Result<(), AppError> {
        self.seen
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
            .push(env.id);
        self.payloads
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
            .push(env.payload.clone());
        self.occurred_at
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
            .push(env.occurred_at);
        if self.should_fail() {
            return Err(AppError::new(ErrorCode::ServiceUnavailable));
        }
        Ok(())
    }
}

struct RecordingDeadLetterHook(Arc<Mutex<Vec<(EventId, SubscriberName)>>>);

#[async_trait]
impl DeadLetterHook for RecordingDeadLetterHook {
    async fn on_dead(
        &self,
        _tx: &mut SystemTx,
        event_id: EventId,
        subscriber: SubscriberName,
        _now: Instant,
    ) -> Result<(), AppError> {
        self.0
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
            .push((event_id, subscriber));
        Ok(())
    }
}

fn set(subscribers: Vec<Arc<dyn Subscriber>>) -> SubscriberSet {
    SubscriberSet::new(subscribers)
}

fn retry_policy() -> RetryPolicy {
    RetryPolicy {
        base_seconds: 5,
        cap_seconds: 60,
    }
}

async fn delivery(
    app: &TestApp,
    event_id: EventId,
    subscriber: SubscriberName,
) -> (String, i32, String) {
    let row = sqlx::query(
        "SELECT state, attempts, next_attempt_at::text AS next_attempt_at \
         FROM public.outbox_deliveries WHERE event_id = $1 AND subscriber = $2",
    )
    .bind(*event_id.as_uuid())
    .bind(subscriber.as_str())
    .fetch_one(app.admin_pool())
    .await
    .expect("delivery state should be readable");
    (
        row.get("state"),
        row.get("attempts"),
        row.get("next_attempt_at"),
    )
}

async fn dispatch(
    app: &TestApp,
    subscribers: &SubscriberSet,
    at: Instant,
    max_attempts: u32,
) -> DispatchStats {
    dispatch_once(&app.db, subscribers, at, retry_policy(), max_attempts, 32)
        .await
        .expect("dispatch should complete")
}

#[tokio::test]
async fn arch_10_emit_is_transactional() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let now = instant("2026-11-02T00:00:00Z");
    let registry = registered(&[("progress.rebuild", "AccountRegistered", 1)]);
    let mut tx = Tx::begin(&app.db, account_id)
        .await
        .expect("event transaction should begin");
    let event_id = emit(
        &mut tx,
        app.ids.as_ref(),
        now,
        envelope(&app, Some(account_id), EventKind::AccountRegistered),
        &registry,
    )
    .await
    .expect("event should emit inside caller transaction");
    tx.rollback()
        .await
        .expect("caller transaction should roll back");

    let events: i64 = sqlx::query_scalar("SELECT count(*) FROM public.outbox_events WHERE id = $1")
        .bind(*event_id.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("event rows should be readable");
    let deliveries: i64 =
        sqlx::query_scalar("SELECT count(*) FROM public.outbox_deliveries WHERE event_id = $1")
            .bind(*event_id.as_uuid())
            .fetch_one(app.admin_pool())
            .await
            .expect("delivery rows should be readable");
    assert_eq!((events, deliveries), (0, 0));
}

#[tokio::test]
async fn arch_11_events_without_subscribers_are_kept() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let now = instant("2026-11-02T00:00:00Z");
    let registry = registered(&[
        ("progress.rebuild", "AccountRegistered", 1),
        ("scheduler.account_registered", "AccountRegistered", 1),
    ]);
    let registered_id = emit_event(
        &app,
        account_id,
        EventKind::AccountRegistered,
        &registry,
        now,
    )
    .await;
    let no_subscriber_id =
        emit_event(&app, account_id, EventKind::SecretChanged, &registry, now).await;

    let rows = sqlx::query(
        "SELECT e.id, count(d.event_id)::bigint AS deliveries, \
                count(d.event_id) FILTER (WHERE d.state = 'pending')::bigint AS pending \
         FROM public.outbox_events e LEFT JOIN public.outbox_deliveries d ON d.event_id = e.id \
         WHERE e.id = ANY($1::uuid[]) GROUP BY e.id",
    )
    .bind(vec![*registered_id.as_uuid(), *no_subscriber_id.as_uuid()])
    .fetch_all(app.admin_pool())
    .await
    .expect("outbox rows should be readable");
    assert_eq!(rows.len(), 2);
    for row in rows {
        let id: sqlx::types::Uuid = row.get("id");
        let deliveries: i64 = row.get("deliveries");
        let pending: i64 = row.get("pending");
        if id == *registered_id.as_uuid() {
            assert_eq!(deliveries, 2);
            assert_eq!(pending, 2);
        } else {
            assert_eq!(id, *no_subscriber_id.as_uuid());
            assert_eq!(deliveries, 0);
            assert_eq!(pending, 0);
        }
    }
}

#[tokio::test]
async fn dispatch_preserves_per_account_order() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let now = instant("2026-11-02T00:00:00Z");
    let registry = registered(&[("progress.rebuild", "AccountRegistered", 1)]);
    let ids = [
        emit_event(
            &app,
            account_id,
            EventKind::AccountRegistered,
            &registry,
            now,
        )
        .await,
        emit_event(
            &app,
            account_id,
            EventKind::AccountRegistered,
            &registry,
            now,
        )
        .await,
        emit_event(
            &app,
            account_id,
            EventKind::AccountRegistered,
            &registry,
            now,
        )
        .await,
    ];
    let subscriber = Arc::new(RecordingSubscriber::new("progress.rebuild"));
    let subscribers = set(vec![subscriber.clone()]);

    for _ in 0..ids.len() {
        let stats = dispatch(&app, &subscribers, now, 3).await;
        assert_eq!(stats.delivered, 1);
    }
    assert_eq!(subscriber.calls(), ids);
    assert_eq!(
        subscriber
            .occurred_at
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
            .first(),
        Some(&instant("2026-11-02T00:00:00.123Z"))
    );
    assert_eq!(
        subscriber
            .payloads
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
            .first(),
        Some(
            &EventPayload::new()
                .with_id("account_id", &account_id)
                .with_enum("status", EventEnumValue::new("active"))
                .with_int("version", 1)
        )
    );
}

#[tokio::test]
async fn failing_delivery_blocks_only_its_stream_and_retries() {
    let app = test_app!();
    let account_a = app.new_account();
    let account_b = app.new_account();
    seed_account(&app, account_a).await;
    seed_account(&app, account_b).await;
    let t0 = instant("2026-11-02T00:00:00Z");
    let t5 = instant("2026-11-02T00:00:05Z");
    let t15 = instant("2026-11-02T00:00:15Z");
    let registry = registered(&[
        ("progress.retrying", "AccountRegistered", 1),
        ("progress.other", "AccountRegistered", 1),
    ]);
    let a1 = emit_event(&app, account_a, EventKind::AccountRegistered, &registry, t0).await;
    let a2 = emit_event(&app, account_a, EventKind::AccountRegistered, &registry, t0).await;
    let b1 = emit_event(&app, account_b, EventKind::AccountRegistered, &registry, t0).await;
    let retrying = Arc::new(RecordingSubscriber::failing("progress.retrying", 4, false));
    let other = Arc::new(RecordingSubscriber::new("progress.other"));
    let subscribers = set(vec![retrying.clone(), other.clone()]);

    let first = dispatch(&app, &subscribers, t0, 3).await;
    assert_eq!((first.delivered, first.retried), (2, 2));
    let first_state = delivery(&app, a1, retrying.name).await;
    assert_eq!(first_state.0, "pending");
    assert_eq!(first_state.1, 1);
    assert_eq!(first_state.2, "2026-11-02 00:00:05+00");
    let first_b_state = delivery(&app, b1, retrying.name).await;
    assert_eq!((first_b_state.0.as_str(), first_b_state.1), ("pending", 1));

    let not_due = dispatch(&app, &subscribers, t0, 3).await;
    assert_eq!((not_due.delivered, not_due.retried), (1, 0));
    assert_eq!(other.calls(), vec![a1, b1, a2]);

    let second = dispatch(&app, &subscribers, t5, 3).await;
    assert_eq!(second.retried, 2);
    let third = dispatch(&app, &subscribers, t15, 3).await;
    assert_eq!(third.delivered, 2);
    let fourth = dispatch(&app, &subscribers, t15, 3).await;
    assert_eq!(fourth.delivered, 1);
    assert_eq!(retrying.calls(), vec![a1, b1, a1, b1, a1, b1, a2]);
}

#[tokio::test]
async fn arch_10_dead_after_max_attempts() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let t0 = instant("2026-11-02T00:00:00Z");
    let t5 = instant("2026-11-02T00:00:05Z");
    let t15 = instant("2026-11-02T00:00:15Z");
    let registry = registered(&[("progress.permanent", "AccountRegistered", 1)]);
    let first = emit_event(
        &app,
        account_id,
        EventKind::AccountRegistered,
        &registry,
        t0,
    )
    .await;
    let later = emit_event(
        &app,
        account_id,
        EventKind::AccountRegistered,
        &registry,
        t0,
    )
    .await;
    let subscriber = Arc::new(RecordingSubscriber::failing("progress.permanent", 0, true));
    let dead_events = Arc::new(Mutex::new(Vec::new()));
    let subscribers = set(vec![subscriber.clone()])
        .with_dead_letter_hook(Arc::new(RecordingDeadLetterHook(Arc::clone(&dead_events))));

    assert_eq!(dispatch(&app, &subscribers, t0, 3).await.retried, 1);
    assert_eq!(dispatch(&app, &subscribers, t5, 3).await.retried, 1);
    assert_eq!(dispatch(&app, &subscribers, t15, 3).await.dead, 1);
    let state = delivery(&app, first, subscriber.name).await;
    assert_eq!((state.0.as_str(), state.1), ("dead", 3));
    assert_eq!(
        *dead_events
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner),
        vec![(first, subscriber.name)]
    );
    assert_eq!(dispatch(&app, &subscribers, t15, 3).await.retried, 1);
    assert_eq!(subscriber.calls().last(), Some(&later));
}

#[tokio::test]
async fn retry_dead_rules() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let now = instant("2026-11-02T00:00:00Z");
    let registry = registered(&[("progress.retry", "AccountRegistered", 1)]);
    let event_id = emit_event(
        &app,
        account_id,
        EventKind::AccountRegistered,
        &registry,
        now,
    )
    .await;
    let subscriber = SubscriberName::new("progress.retry");

    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    let not_dead = retry_dead(&mut tx, event_id, subscriber, now)
        .await
        .expect_err("pending delivery must not retry");
    assert_eq!(not_dead.code, ErrorCode::NotDead);
    tx.rollback().await.expect("failed retry should roll back");

    sqlx::query(
        "UPDATE public.outbox_deliveries SET state = 'dead', attempts = 3, \
         last_error_code = 'service_unavailable' WHERE event_id = $1 AND subscriber = $2",
    )
    .bind(*event_id.as_uuid())
    .bind(subscriber.as_str())
    .execute(app.admin_pool())
    .await
    .expect("dead fixture should be set");
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    retry_dead(&mut tx, event_id, subscriber, now)
        .await
        .expect("dead delivery should reset");
    tx.commit().await.expect("retry should commit");
    let state = delivery(&app, event_id, subscriber).await;
    assert_eq!(state.0, "pending");
    assert_eq!(state.1, 0);
    assert_eq!(state.2, "2026-11-02 00:00:00+00");

    let missing = EventId::from_uuid(app.ids.new_uuid_v7());
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    let not_found = retry_dead(&mut tx, missing, subscriber, now)
        .await
        .expect_err("unknown delivery should not be retried");
    assert_eq!(not_found.code, ErrorCode::NotFound);
    tx.rollback().await.expect("missing retry should roll back");

    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    let unknown_pair = retry_dead(
        &mut tx,
        event_id,
        SubscriberName::new("progress.unknown"),
        now,
    )
    .await
    .expect_err("unknown event subscriber pair should not be retried");
    assert_eq!(unknown_pair.code, ErrorCode::NotFound);
    tx.rollback()
        .await
        .expect("unknown pair retry should roll back");
}

#[tokio::test]
async fn at_least_once_redelivery_after_crash() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let now = instant("2026-11-02T00:00:00Z");
    let registry = registered(&[("progress.crash", "AccountRegistered", 1)]);
    let event_id = emit_event(
        &app,
        account_id,
        EventKind::AccountRegistered,
        &registry,
        now,
    )
    .await;
    let subscriber = Arc::new(RecordingSubscriber::new("progress.crash"));
    let subscribers = set(vec![subscriber.clone()]);

    app.admin_pool()
        .execute(sqlx::raw_sql(
            "CREATE FUNCTION public.outbox_test_fail_mark_delivered() RETURNS trigger \
             LANGUAGE plpgsql AS $$ BEGIN IF NEW.state = 'delivered' THEN \
             RAISE EXCEPTION 'simulated process crash before delivery mark'; END IF; RETURN NEW; END $$; \
             CREATE TRIGGER outbox_test_fail_mark_delivered BEFORE UPDATE ON public.outbox_deliveries \
             FOR EACH ROW EXECUTE FUNCTION public.outbox_test_fail_mark_delivered()",
        ))
        .await
        .expect("crash simulation trigger should install");
    let first = dispatch_once(&app.db, &subscribers, now, retry_policy(), 3, 1).await;
    assert!(
        first.is_err(),
        "delivery mark failure should abort its transaction"
    );
    assert_eq!(subscriber.calls(), vec![event_id]);

    app.admin_pool()
        .execute(sqlx::raw_sql(
            "DROP TRIGGER outbox_test_fail_mark_delivered ON public.outbox_deliveries; \
             DROP FUNCTION public.outbox_test_fail_mark_delivered()",
        ))
        .await
        .expect("crash simulation trigger should be removed");
    assert_eq!(dispatch(&app, &subscribers, now, 3).await.delivered, 1);
    assert_eq!(subscriber.calls(), vec![event_id, event_id]);
}

async fn dispatch_until_idle(app: &TestApp, subscribers: &SubscriberSet, now: Instant) -> u32 {
    let mut delivered = 0;
    for _ in 0..64 {
        let stats = dispatch_once(&app.db, subscribers, now, retry_policy(), 3, 8)
            .await
            .expect("concurrent dispatch should complete");
        delivered += stats.delivered;
        if stats == DispatchStats::default() {
            break;
        }
    }
    delivered
}

#[tokio::test]
async fn tst_20_concurrent_dispatchers() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let now = instant("2026-11-02T00:00:00Z");
    let registry = registered(&[("progress.concurrent", "AccountRegistered", 1)]);
    let mut expected = Vec::new();
    for _ in 0..30 {
        expected.push(
            emit_event(
                &app,
                account_id,
                EventKind::AccountRegistered,
                &registry,
                now,
            )
            .await,
        );
    }
    let subscriber = Arc::new(RecordingSubscriber::new("progress.concurrent"));
    let subscribers = set(vec![subscriber.clone()]);

    let (left, right) = tokio::join!(
        dispatch_until_idle(&app, &subscribers, now),
        dispatch_until_idle(&app, &subscribers, now),
    );
    assert_eq!(left + right, 30);
    let calls = subscriber.calls();
    assert!(calls.len() >= expected.len());
    let mut unique = calls.clone();
    unique.sort_unstable();
    unique.dedup();
    assert_eq!(unique, expected);

    let states = sqlx::query(
        "SELECT e.id, d.state, e.seq FROM public.outbox_events e \
         JOIN public.outbox_deliveries d ON d.event_id = e.id \
         WHERE d.subscriber = 'progress.concurrent' ORDER BY e.seq",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("concurrent delivery states should be readable");
    assert_eq!(states.len(), 30);
    assert!(
        states
            .iter()
            .all(|row| row.get::<String, _>("state") == "delivered")
    );
    let ordered = states
        .iter()
        .map(|row| EventId::from_uuid(row.get("id")))
        .collect::<Vec<_>>();
    assert_eq!(ordered, expected);
    assert!(calls.windows(2).all(|pair| {
        let left = expected.iter().position(|id| id == &pair[0]).unwrap_or(0);
        let right = expected.iter().position(|id| id == &pair[1]).unwrap_or(0);
        left <= right
    }));
}
