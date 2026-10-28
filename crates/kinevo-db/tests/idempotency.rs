#![cfg(feature = "testing")]
#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    clippy::too_many_lines,
    reason = "idempotency acceptance tests use isolated synthetic PostgreSQL fixtures"
)]

#[allow(
    dead_code,
    reason = "shared support fixtures are also used by sibling suites"
)]
mod support;

use futures::future::join_all;
use kinevo_db::{
    Tx,
    idempotency::{Begin, IdemKey, begin, finish},
    require_db,
    testing::TestApp,
};
use kinevo_kernel::{id::AccountId, time::Instant};
use sqlx::Row;

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

fn key(value: &str) -> IdemKey {
    IdemKey::parse(value).expect("fixture idempotency key should be valid")
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

async fn begin_committed(
    app: &TestApp,
    account_id: AccountId,
    idempotency_key: &IdemKey,
    method: &str,
    path: &str,
    request_hash: [u8; 32],
    now: Instant,
) -> Begin {
    let mut tx = Tx::begin(&app.db, account_id)
        .await
        .expect("idempotency transaction should begin");
    let result = begin(
        &mut tx,
        idempotency_key,
        method,
        path,
        request_hash,
        now,
        3_600,
    )
    .await
    .expect("idempotency begin should succeed");
    tx.commit()
        .await
        .expect("idempotency reservation should commit");
    result
}

async fn finish_committed(
    app: &TestApp,
    account_id: AccountId,
    idempotency_key: &IdemKey,
    status: u16,
    body: &[u8],
    store_body: bool,
) {
    let mut tx = Tx::begin(&app.db, account_id)
        .await
        .expect("idempotency transaction should begin");
    finish(&mut tx, idempotency_key, status, body, store_body)
        .await
        .expect("idempotency response should finish");
    tx.commit()
        .await
        .expect("idempotency response should commit");
}

async fn stored_response_columns(
    app: &TestApp,
    account_id: AccountId,
    idempotency_key: &IdemKey,
) -> (Option<i16>, Option<Vec<u8>>) {
    let row = sqlx::query(
        "SELECT status_code, response_body FROM public.idempotency_keys \
         WHERE account_id = $1 AND key = $2",
    )
    .bind(*account_id.as_uuid())
    .bind(idempotency_key.as_str())
    .fetch_one(app.admin_pool())
    .await
    .expect("stored response should be readable by the test administrator");
    (row.get("status_code"), row.get("response_body"))
}

#[tokio::test]
async fn api_71_replay_same_request() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let idempotency_key = key("request-001");
    let request_hash = [0x2a; 32];
    let now = instant("2026-11-02T00:00:00Z");

    assert!(matches!(
        begin_committed(
            &app,
            account_id,
            &idempotency_key,
            "post",
            "/api/v1/tasks",
            request_hash,
            now,
        )
        .await,
        Begin::Fresh
    ));
    finish_committed(&app, account_id, &idempotency_key, 201, b"created", true).await;

    let replay = begin_committed(
        &app,
        account_id,
        &idempotency_key,
        "POST",
        "/api/v1/tasks",
        request_hash,
        now,
    )
    .await;
    assert!(matches!(replay, Begin::Replay(_)));
    if let Begin::Replay(response) = replay {
        assert_eq!(response.status, 201);
        assert_eq!(response.body, b"created");
    }
}

#[tokio::test]
async fn api_71_in_flight_duplicate() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let idempotency_key = key("request-002");
    let now = instant("2026-11-02T00:00:00Z");

    assert!(matches!(
        begin_committed(
            &app,
            account_id,
            &idempotency_key,
            "POST",
            "/api/v1/tasks",
            [0x2a; 32],
            now,
        )
        .await,
        Begin::Fresh
    ));
    assert!(matches!(
        begin_committed(
            &app,
            account_id,
            &idempotency_key,
            "POST",
            "/api/v1/tasks",
            [0x2a; 32],
            now,
        )
        .await,
        Begin::InProgress
    ));
}

#[tokio::test]
async fn api_71_reuse_with_different_request() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let idempotency_key = key("request-003");
    let now = instant("2026-11-02T00:00:00Z");
    assert!(matches!(
        begin_committed(
            &app,
            account_id,
            &idempotency_key,
            "POST",
            "/api/v1/tasks",
            [0x2a; 32],
            now,
        )
        .await,
        Begin::Fresh
    ));
    finish_committed(&app, account_id, &idempotency_key, 201, b"created", true).await;

    for (method, path, hash) in [
        ("POST", "/api/v1/tasks", [0x2b; 32]),
        ("PUT", "/api/v1/tasks", [0x2a; 32]),
        ("POST", "/api/v1/tasks/other", [0x2a; 32]),
    ] {
        assert!(matches!(
            begin_committed(&app, account_id, &idempotency_key, method, path, hash, now,).await,
            Begin::Mismatch
        ));
    }
}

#[tokio::test]
async fn api_72_keys_are_per_account() {
    let app = test_app!();
    let first_account = app.new_account();
    let second_account = app.new_account();
    seed_account(&app, first_account).await;
    seed_account(&app, second_account).await;
    let idempotency_key = key("request-004");
    let now = instant("2026-11-02T00:00:00Z");

    for account_id in [first_account, second_account] {
        assert!(matches!(
            begin_committed(
                &app,
                account_id,
                &idempotency_key,
                "POST",
                "/api/v1/tasks",
                [0x2a; 32],
                now,
            )
            .await,
            Begin::Fresh
        ));
    }
    finish_committed(
        &app,
        first_account,
        &idempotency_key,
        201,
        b"first account",
        true,
    )
    .await;

    assert!(matches!(
        begin_committed(
            &app,
            second_account,
            &idempotency_key,
            "POST",
            "/api/v1/tasks",
            [0x2a; 32],
            now,
        )
        .await,
        Begin::InProgress
    ));
}

#[tokio::test]
async fn api_71_5xx_and_429_not_stored() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let now = instant("2026-11-02T00:00:00Z");

    for (raw_key, status) in [("request-005", 500), ("request-006", 429)] {
        let idempotency_key = key(raw_key);
        assert!(matches!(
            begin_committed(
                &app,
                account_id,
                &idempotency_key,
                "POST",
                "/api/v1/tasks",
                [0x2a; 32],
                now,
            )
            .await,
            Begin::Fresh
        ));
        finish_committed(
            &app,
            account_id,
            &idempotency_key,
            status,
            b"not stored",
            true,
        )
        .await;
        assert!(matches!(
            begin_committed(
                &app,
                account_id,
                &idempotency_key,
                "POST",
                "/api/v1/tasks",
                [0x2a; 32],
                now.checked_add_seconds(1)
                    .expect("fixture time should advance"),
            )
            .await,
            Begin::Fresh
        ));
    }
}

#[tokio::test]
async fn response_body_limit_releases_key() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let idempotency_key = key("request-010");
    let now = instant("2026-11-02T00:00:00Z");
    assert!(matches!(
        begin_committed(
            &app,
            account_id,
            &idempotency_key,
            "POST",
            "/api/v1/tasks",
            [0x2a; 32],
            now,
        )
        .await,
        Begin::Fresh
    ));

    let oversized_body = vec![b'x'; kinevo_db::idempotency::MAX_STORED_BODY_BYTES + 1];
    finish_committed(
        &app,
        account_id,
        &idempotency_key,
        201,
        &oversized_body,
        true,
    )
    .await;
    assert!(matches!(
        begin_committed(
            &app,
            account_id,
            &idempotency_key,
            "POST",
            "/api/v1/tasks",
            [0x2a; 32],
            now.checked_add_seconds(1)
                .expect("fixture time should advance"),
        )
        .await,
        Begin::Fresh
    ));
}

#[tokio::test]
async fn ttl_expiry_allows_reuse() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let idempotency_key = key("request-007");
    let now = instant("2026-11-02T00:00:00Z");
    assert!(matches!(
        begin_committed(
            &app,
            account_id,
            &idempotency_key,
            "POST",
            "/api/v1/tasks",
            [0x2a; 32],
            now,
        )
        .await,
        Begin::Fresh
    ));
    finish_committed(&app, account_id, &idempotency_key, 201, b"created", true).await;

    assert!(matches!(
        begin_committed(
            &app,
            account_id,
            &idempotency_key,
            "POST",
            "/api/v1/tasks",
            [0x2a; 32],
            now.checked_add_seconds(3_601)
                .expect("fixture time should advance"),
        )
        .await,
        Begin::Fresh
    ));
}

#[tokio::test]
async fn secret_routes_store_status_only() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let idempotency_key = key("request-008");
    let request_hash = [0x2a; 32];
    let now = instant("2026-11-02T00:00:00Z");
    assert!(matches!(
        begin_committed(
            &app,
            account_id,
            &idempotency_key,
            "POST",
            "/api/v1/auth/login",
            request_hash,
            now,
        )
        .await,
        Begin::Fresh
    ));
    finish_committed(
        &app,
        account_id,
        &idempotency_key,
        200,
        b"secret response body",
        false,
    )
    .await;

    assert_eq!(
        stored_response_columns(&app, account_id, &idempotency_key).await,
        (Some(200), None)
    );
    let replay = begin_committed(
        &app,
        account_id,
        &idempotency_key,
        "POST",
        "/api/v1/auth/login",
        request_hash,
        now,
    )
    .await;
    assert!(matches!(replay, Begin::Replay(_)));
    if let Begin::Replay(response) = replay {
        assert_eq!(response.status, 200);
        assert_eq!(response.body.len(), 0);
    }
}

#[tokio::test]
async fn tst_20_concurrent_begin_one_winner() {
    let app = test_app!();
    let account_id = app.new_account();
    seed_account(&app, account_id).await;
    let now = instant("2026-11-02T00:00:00Z");
    let idempotency_key = key("request-009");
    let app_ref = &app;
    let results = join_all((0..8).map(|_| {
        let idempotency_key = idempotency_key.clone();
        async move {
            begin_committed(
                app_ref,
                account_id,
                &idempotency_key,
                "POST",
                "/api/v1/tasks",
                [0x2a; 32],
                now,
            )
            .await
        }
    }))
    .await;

    assert_eq!(
        results
            .iter()
            .filter(|result| matches!(result, Begin::Fresh))
            .count(),
        1
    );
    assert_eq!(
        results
            .iter()
            .filter(|result| matches!(result, Begin::InProgress))
            .count(),
        7
    );
}

mod tests {
    use super::IdemKey;

    #[test]
    fn key_validation() {
        assert!(IdemKey::parse("request-01").is_ok());
        assert_eq!(
            IdemKey::parse(" REQUEST-01 ")
                .expect("key should normalize")
                .as_str(),
            "request-01"
        );
        assert!(IdemKey::parse("abc").is_err());
        assert!(IdemKey::parse(&"a".repeat(129)).is_err());
        assert!(IdemKey::parse("has space").is_err());
    }
}
