#![cfg(feature = "testing")]
#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "queue acceptance tests use isolated synthetic PostgreSQL fixtures"
)]

#[allow(
    dead_code,
    reason = "the shared support module also contains fixtures used by sibling database suites"
)]
mod support;

use kinevo_db::{
    Db, SystemTx, Tx,
    jobs::{
        ClaimedJob, EnqueueOutcome, JobKind, NewJob, RetryPolicy, ScheduleSlot, WorkerId,
        backoff_seconds, claim, complete, enqueue, extend_lease, fail, materialise,
        oldest_queued_age_seconds, summary,
    },
    require_db,
    testing::TestApp,
};
use kinevo_kernel::{
    errors::ErrorCode,
    id::{AccountId, JobId},
    time::Instant,
};
use serde_json::{Value, json};
use sqlx::{Row, types::Uuid};
use std::collections::HashSet;

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

fn kind(value: &'static str) -> JobKind {
    JobKind::new(value).expect("fixture job kind should be valid")
}

fn new_job(kind: JobKind, unique_key: Option<&str>, now: Instant) -> NewJob {
    NewJob {
        account_id: None,
        kind,
        unique_key: unique_key.map(str::to_owned),
        payload: json!({"record_id": "00000000-0000-0000-0000-000000000001"}),
        run_at: now,
        max_attempts: 3,
    }
}

async fn seed_account(app: &TestApp, account_id: AccountId) {
    let mut tx = Tx::begin(&app.db, account_id)
        .await
        .expect("account transaction should begin");
    support::fixtures::insert_account(&mut tx, account_id, &format!("{account_id}@example.test"))
        .await
        .expect("synthetic account fixture should insert");
    tx.commit()
        .await
        .expect("account fixture transaction should commit");
}

async fn insert_job(app: &TestApp, job: NewJob, now: Instant) -> Option<JobId> {
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    let outcome = enqueue(&mut tx, app.ids.as_ref(), now, job)
        .await
        .expect("job should enqueue");
    tx.commit()
        .await
        .expect("fixture transaction should commit");
    match outcome {
        EnqueueOutcome::Inserted(id) => Some(id),
        EnqueueOutcome::Duplicate => None,
    }
}

async fn claim_batch(
    db: &Db,
    worker: &'static str,
    now: Instant,
    limit: u16,
    enabled: &[JobKind],
) -> Vec<ClaimedJob> {
    let mut tx = SystemTx::begin(db)
        .await
        .expect("claim transaction should begin");
    let claimed = claim(&mut tx, &WorkerId::new(worker), now, 120, limit, enabled)
        .await
        .expect("jobs should be claimed");
    tx.commit().await.expect("claim transaction should commit");
    claimed
}

async fn job_state(app: &TestApp, id: JobId) -> (String, i32, i32, i64, Option<String>) {
    let row = sqlx::query(
        "SELECT state, attempts, max_attempts, \
         (extract(epoch FROM run_at) * 1000)::bigint AS run_at_ms, last_error_code \
         FROM public.jobs WHERE id = $1",
    )
    .bind(*id.as_uuid())
    .fetch_one(app.admin_pool())
    .await
    .expect("job state should be readable");
    (
        row.get("state"),
        row.get("attempts"),
        row.get("max_attempts"),
        row.get("run_at_ms"),
        row.get("last_error_code"),
    )
}

mod tests {
    use super::{RetryPolicy, backoff_seconds};

    #[test]
    fn pol_backoff_doubles_and_caps() {
        let policy = RetryPolicy {
            base_seconds: 5,
            cap_seconds: 3600,
        };
        assert_eq!(backoff_seconds(1, policy), 5);
        assert_eq!(backoff_seconds(2, policy), 10);
        assert_eq!(backoff_seconds(3, policy), 20);
        assert_eq!(backoff_seconds(10, policy), 2560);
        assert_eq!(backoff_seconds(11, policy), 3600);
        assert_eq!(backoff_seconds(200, policy), 3600);
    }
}

#[tokio::test]
async fn enqueue_is_idempotent_by_unique_key() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let kind = kind("test.idempotent");
    let mut first_job = new_job(kind, Some("same-key"), now);
    first_job.account_id = Some(account_id);
    let mut duplicate_job = new_job(kind, Some("same-key"), now);
    duplicate_job.account_id = Some(account_id);

    let mut tx = Tx::begin(&app.db, account_id)
        .await
        .expect("account transaction should begin");
    let first = enqueue(&mut tx, app.ids.as_ref(), now, first_job)
        .await
        .expect("first enqueue should succeed");
    let duplicate = enqueue(&mut tx, app.ids.as_ref(), now, duplicate_job)
        .await
        .expect("duplicate enqueue should be idempotent");
    tx.commit()
        .await
        .expect("enqueue transaction should commit");

    assert!(matches!(first, EnqueueOutcome::Inserted(_)));
    assert_eq!(duplicate, EnqueueOutcome::Duplicate);
    let count: i64 =
        sqlx::query_scalar("SELECT count(*) FROM public.jobs WHERE kind = $1 AND unique_key = $2")
            .bind(kind.as_str())
            .bind("same-key")
            .fetch_one(app.admin_pool())
            .await
            .expect("job count should be readable");
    assert_eq!(count, 1);
}

#[tokio::test]
async fn enqueue_rolls_back_with_tx() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let mut job = new_job(kind("test.rollback"), Some("rolled-back"), now);
    job.account_id = Some(account_id);
    let mut tx = Tx::begin(&app.db, account_id)
        .await
        .expect("account transaction should begin");
    enqueue(&mut tx, app.ids.as_ref(), now, job)
        .await
        .expect("job should enqueue before rollback");
    tx.rollback()
        .await
        .expect("enqueue transaction should roll back");

    let count: i64 =
        sqlx::query_scalar("SELECT count(*) FROM public.jobs WHERE kind = $1 AND unique_key = $2")
            .bind("test.rollback")
            .bind("rolled-back")
            .fetch_one(app.admin_pool())
            .await
            .expect("job count should be readable");
    assert_eq!(count, 0);
}

#[tokio::test]
async fn tst_20_concurrent_claims_are_disjoint() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let kind = kind("test.concurrent");
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("fixture transaction should begin");
    for index in 0..20 {
        let outcome = enqueue(
            &mut tx,
            app.ids.as_ref(),
            now,
            new_job(kind, Some(&format!("job-{index}")), now),
        )
        .await
        .expect("concurrent fixture should enqueue");
        assert!(matches!(outcome, EnqueueOutcome::Inserted(_)));
    }
    tx.commit()
        .await
        .expect("fixture transaction should commit");

    let enabled = [kind];
    let (first, second, third, fourth) = tokio::join!(
        claim_batch(&app.db, "worker-a", now, 5, &enabled),
        claim_batch(&app.db, "worker-b", now, 5, &enabled),
        claim_batch(&app.db, "worker-c", now, 5, &enabled),
        claim_batch(&app.db, "worker-d", now, 5, &enabled),
    );
    let claimed = [first, second, third, fourth].concat();
    let ids = claimed
        .iter()
        .map(|job| *job.id.as_uuid())
        .collect::<HashSet<Uuid>>();
    assert_eq!(claimed.len(), 20);
    assert_eq!(ids.len(), 20);
}

#[tokio::test]
async fn lease_expiry_reclaims_and_caps_attempts() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let expired = now.checked_add_seconds(-1).expect("valid earlier time");
    let kind = kind("test.lease");
    let mut retried = new_job(kind, Some("retry"), now);
    retried.max_attempts = 3;
    let retried_id = insert_job(&app, retried, now)
        .await
        .expect("retry fixture should be new");
    let mut exhausted = new_job(kind, Some("exhausted"), now);
    exhausted.max_attempts = 2;
    let exhausted_id = insert_job(&app, exhausted, now)
        .await
        .expect("exhausted fixture should be new");
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("setup transaction should begin");
    for (id, attempts) in [(retried_id, 1_i32), (exhausted_id, 2_i32)] {
        sqlx::query(
            "UPDATE public.jobs SET state = 'running', attempts = $2, \
             locked_by = 'old-worker', locked_until = $3::timestamptz WHERE id = $1",
        )
        .bind(*id.as_uuid())
        .bind(attempts)
        .bind(expired.to_rfc3339())
        .execute(tx.conn())
        .await
        .expect("expired running fixture should update");
    }
    tx.commit().await.expect("setup transaction should commit");

    let claimed = claim_batch(&app.db, "new-worker", now, 5, &[kind]).await;
    assert_eq!(claimed.len(), 1);
    assert_eq!(claimed[0].id, retried_id);
    assert_eq!(claimed[0].attempts, 2);
    let (state, attempts, _, _, _) = job_state(&app, exhausted_id).await;
    assert_eq!(state, "dead");
    assert_eq!(attempts, 2);
}

#[tokio::test]
async fn fail_schedules_backoff_from_injected_clock() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let kind = kind("test.retry");
    let id = insert_job(&app, new_job(kind, Some("backoff"), now), now)
        .await
        .expect("backoff fixture should be new");
    let claimed = claim_batch(&app.db, "retry-worker", now, 1, &[kind]).await;
    assert_eq!(claimed.len(), 1);
    assert_eq!(claimed[0].attempts, 1);
    let retry = RetryPolicy {
        base_seconds: 5,
        cap_seconds: 3600,
    };

    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("failure transaction should begin");
    fail(&mut tx, id, now, ErrorCode::ServiceUnavailable, retry)
        .await
        .expect("first failure should schedule retry");
    tx.commit()
        .await
        .expect("failure transaction should commit");
    let first_due = now.checked_add_seconds(5).expect("due time should fit");
    assert_eq!(job_state(&app, id).await.3, first_due.unix_millis());

    let second_now = first_due;
    let claimed = claim_batch(&app.db, "retry-worker", second_now, 1, &[kind]).await;
    assert_eq!(claimed.len(), 1);
    assert_eq!(claimed[0].attempts, 2);
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("second failure transaction should begin");
    fail(
        &mut tx,
        id,
        second_now,
        ErrorCode::ServiceUnavailable,
        retry,
    )
    .await
    .expect("second failure should schedule retry");
    tx.commit()
        .await
        .expect("second failure transaction should commit");
    assert_eq!(
        job_state(&app, id).await.3,
        second_now
            .checked_add_seconds(10)
            .expect("second due time should fit")
            .unix_millis()
    );
    assert_eq!(
        job_state(&app, id).await.4.as_deref(),
        Some("service_unavailable")
    );
}

#[tokio::test]
async fn mod_05_disabled_module_jobs_not_claimed() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let enabled_kind = kind("test.enabled");
    let disabled_kind = kind("test.disabled");
    let enabled = insert_job(&app, new_job(enabled_kind, Some("enabled"), now), now)
        .await
        .expect("enabled fixture should be new");
    let disabled = insert_job(&app, new_job(disabled_kind, Some("disabled"), now), now)
        .await
        .expect("disabled fixture should be new");

    let claimed = claim_batch(&app.db, "module-worker", now, 5, &[enabled_kind]).await;
    assert_eq!(claimed.len(), 1);
    assert_eq!(claimed[0].id, enabled);
    let (state, _, _, _, _) = job_state(&app, disabled).await;
    assert_eq!(state, "queued");
}

#[tokio::test]
async fn payload_guard_rejects_content_like_strings() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let mut job = new_job(kind("test.payload"), Some("oversized-string"), now);
    job.payload = json!({"enum": "x".repeat(200)});
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("enqueue transaction should begin");
    let error = enqueue(&mut tx, app.ids.as_ref(), now, job)
        .await
        .expect_err("long string should be rejected");
    assert_eq!(error.code, ErrorCode::ValidationFailed);
    tx.commit()
        .await
        .expect("empty enqueue transaction should commit");
    let count: i64 =
        sqlx::query_scalar("SELECT count(*) FROM public.jobs WHERE kind = $1 AND unique_key = $2")
            .bind("test.payload")
            .bind("oversized-string")
            .fetch_one(app.admin_pool())
            .await
            .expect("job count should be readable");
    assert_eq!(count, 0);
}

#[tokio::test]
async fn materialise_is_idempotent_per_slot() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let scheduled_for = instant("2026-11-03T00:00:00Z");
    let first_account = app.new_account();
    let second_account = app.new_account();
    seed_account(&app, first_account).await;
    seed_account(&app, second_account).await;
    let kind = kind("test.recurring");
    let slot = ScheduleSlot {
        kind,
        account_id: Some(first_account),
        scheduled_for,
    };
    let duplicate_slot = ScheduleSlot { ..slot };
    let second_account_slot = ScheduleSlot {
        account_id: Some(second_account),
        ..slot
    };
    let instance_slot = ScheduleSlot {
        account_id: None,
        ..slot
    };
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("materialise transaction should begin");
    materialise(
        &mut tx,
        app.ids.as_ref(),
        now,
        3,
        &[slot, duplicate_slot, second_account_slot, instance_slot],
    )
    .await
    .expect("schedule slots should materialise");
    tx.commit()
        .await
        .expect("materialise transaction should commit");

    let count: i64 = sqlx::query_scalar(
        "SELECT count(*) FROM public.jobs WHERE kind = $1 AND run_at = $2::timestamptz",
    )
    .bind(kind.as_str())
    .bind(scheduled_for.to_rfc3339())
    .fetch_one(app.admin_pool())
    .await
    .expect("schedule count should be readable");
    assert_eq!(count, 3);
}

#[tokio::test]
async fn complete_and_extend_lease_use_injected_time() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let kind = kind("test.lifecycle");
    let id = insert_job(&app, new_job(kind, Some("complete"), now), now)
        .await
        .expect("lifecycle fixture should be new");
    let claimed = claim_batch(&app.db, "lifecycle-worker", now, 1, &[kind]).await;
    assert_eq!(claimed.len(), 1);

    let extended_at = now.checked_add_seconds(60).expect("extension time fits");
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("lease transaction should begin");
    extend_lease(&mut tx, id, extended_at, 300)
        .await
        .expect("running job lease should extend");
    tx.commit().await.expect("lease transaction should commit");
    let locked_until: String =
        sqlx::query_scalar("SELECT locked_until::text FROM public.jobs WHERE id = $1")
            .bind(*id.as_uuid())
            .fetch_one(app.admin_pool())
            .await
            .expect("lease should be readable");
    assert!(locked_until.contains("00:06:00"));

    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("completion transaction should begin");
    complete(&mut tx, id, extended_at)
        .await
        .expect("running job should complete");
    tx.commit()
        .await
        .expect("completion transaction should commit");
    let (state, _, _, _, _) = job_state(&app, id).await;
    assert_eq!(state, "succeeded");
}

#[tokio::test]
async fn summary_counts_states_and_oldest_queue_age() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let kind = kind("test.summary");
    let old = now.checked_add_seconds(-30).expect("valid past instant");
    let mut old_job = new_job(kind, Some("old"), old);
    old_job.run_at = old;
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("summary fixture transaction should begin");
    enqueue(&mut tx, app.ids.as_ref(), old, old_job)
        .await
        .expect("old job should enqueue");
    enqueue(
        &mut tx,
        app.ids.as_ref(),
        now,
        new_job(kind, Some("new"), now),
    )
    .await
    .expect("new job should enqueue");
    let rows = summary(&mut tx).await.expect("job summary should read");
    assert!(
        rows.iter()
            .any(|row| row.kind == kind.as_str() && row.state == "queued" && row.count == 2)
    );
    assert_eq!(
        oldest_queued_age_seconds(&mut tx, now).await.unwrap(),
        Some(30)
    );
    tx.rollback()
        .await
        .expect("read-only summary transaction should roll back");
}

#[tokio::test]
async fn payload_guard_rejects_oversized_json() {
    let app = test_app!();
    let now = instant("2026-11-02T00:00:00Z");
    let payload = Value::Array(vec![Value::from(0); 10_000]);
    let job = NewJob {
        payload,
        ..new_job(kind("test.payload_size"), Some("too-many-values"), now)
    };
    let mut tx = SystemTx::begin(&app.db)
        .await
        .expect("enqueue transaction should begin");
    let error = enqueue(&mut tx, app.ids.as_ref(), now, job)
        .await
        .expect_err("payload beyond 16 KiB should be rejected");
    assert_eq!(error.code, ErrorCode::PayloadTooLarge);
    tx.rollback()
        .await
        .expect("rejected enqueue transaction should roll back");
}

#[tokio::test]
async fn job_handler_contract_is_object_safe() {
    struct Handler;

    #[async_trait::async_trait]
    impl kinevo_db::jobs::JobHandler for Handler {
        fn kind(&self) -> JobKind {
            kind("test.handler")
        }

        async fn run(
            &self,
            _ctx: kinevo_db::jobs::JobContext,
            _job: ClaimedJob,
        ) -> Result<(), kinevo_kernel::errors::AppError> {
            Ok(())
        }
    }

    let handler: &dyn kinevo_db::jobs::JobHandler = &Handler;
    assert_eq!(handler.kind().as_str(), "test.handler");
}
