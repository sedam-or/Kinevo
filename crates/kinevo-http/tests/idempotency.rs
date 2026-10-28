#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "acceptance tests use explicit synthetic requests"
)]

#[path = "support/mod.rs"]
mod support;

use axum::{
    body::{Body, Bytes},
    http::{Method, Response, StatusCode, header},
    routing::{get, post},
};
use kinevo_http::route::{Access, IdemMode, RouteTable};
use kinevo_ports::fakes::FakeIdempotency;
use serde_json::Value;
use std::sync::{
    Arc,
    atomic::{AtomicUsize, Ordering},
};
use support::{fixture, request, route_spec, router, send};
use tokio::sync::Notify;

fn response(
    status: StatusCode,
    content_type: &'static str,
    body: impl Into<Body>,
) -> Response<Body> {
    Response::builder()
        .status(status)
        .header(header::CONTENT_TYPE, content_type)
        .body(body.into())
        .expect("synthetic response is valid")
}

#[tokio::test]
async fn api_71_replay_identical_response() {
    let fixture = fixture().expect("fixture builds");
    let idempotency = Arc::new(FakeIdempotency::default());
    let state = fixture.state.clone().with_idempotency(idempotency, 86_400);
    let calls = Arc::new(AtomicUsize::new(0));
    let handler_calls = calls.clone();
    let app = router(
        state,
        route_spec(
            Method::POST,
            "/writes",
            Access::Permission("identity.account.read"),
            IdemMode::Optional,
            true,
            false,
        ),
        post(move |body: Bytes| {
            let handler_calls = handler_calls.clone();
            async move {
                handler_calls.fetch_add(1, Ordering::SeqCst);
                response(StatusCode::CREATED, "application/json", Body::from(body))
            }
        }),
    )
    .expect("route validates");
    let token = fixture.token(fixture.account_a);
    let mut first = request(Method::POST, "/api/v1/writes", Some(&token), br#"{"x":1}"#);
    first
        .headers_mut()
        .insert("idempotency-key", "Abcdefgh".parse().unwrap());
    let original = send(app.clone(), first).await.expect("first response");
    let mut second = request(Method::POST, "/api/v1/writes", Some(&token), br#"{"x":1}"#);
    second
        .headers_mut()
        .insert("idempotency-key", "abcdefgh".parse().unwrap());
    let replay = send(app, second).await.expect("replay response");

    assert_eq!(calls.load(Ordering::SeqCst), 1);
    assert_eq!(replay.status, original.status);
    assert_eq!(replay.body, original.body);
    assert_eq!(replay.headers[header::CONTENT_TYPE], "application/json");
    assert_eq!(replay.headers["idempotency-replayed"], "true");
}

#[tokio::test]
async fn api_71_reuse_with_other_body() {
    let fixture = fixture().expect("fixture builds");
    let idempotency = Arc::new(FakeIdempotency::default());
    let state = fixture.state.clone().with_idempotency(idempotency, 86_400);
    let app = router(
        state,
        route_spec(
            Method::POST,
            "/writes",
            Access::Permission("identity.account.read"),
            IdemMode::Optional,
            true,
            false,
        ),
        post(|body: Bytes| async move {
            response(StatusCode::CREATED, "application/json", Body::from(body))
        }),
    )
    .expect("route validates");
    let token = fixture.token(fixture.account_a);
    for body in [br#"{"x":1}"#.as_slice(), br#"{"x":2}"#.as_slice()] {
        let mut request = request(Method::POST, "/api/v1/writes", Some(&token), body);
        request
            .headers_mut()
            .insert("idempotency-key", "same-key-0001".parse().unwrap());
        let last = send(app.clone(), request).await.expect("response");
        if body.ends_with(b"2}") {
            assert_eq!(last.status, StatusCode::UNPROCESSABLE_ENTITY);
            let json: Value = serde_json::from_slice(&last.body).expect("problem JSON");
            assert_eq!(json["code"], "idempotency_key_reuse");
        }
    }
}

#[tokio::test]
async fn tst_20_concurrent_same_key() {
    let fixture = fixture().expect("fixture builds");
    let idempotency = Arc::new(FakeIdempotency::default());
    let state = fixture.state.clone().with_idempotency(idempotency, 86_400);
    let calls = Arc::new(AtomicUsize::new(0));
    let started = Arc::new(Notify::new());
    let release = Arc::new(Notify::new());
    let handler_calls = calls.clone();
    let handler_started = started.clone();
    let handler_release = release.clone();
    let app = router(
        state,
        route_spec(
            Method::POST,
            "/slow",
            Access::Permission("identity.account.read"),
            IdemMode::Optional,
            true,
            false,
        ),
        post(move |body: Bytes| {
            let handler_calls = handler_calls.clone();
            let handler_started = handler_started.clone();
            let handler_release = handler_release.clone();
            async move {
                handler_calls.fetch_add(1, Ordering::SeqCst);
                handler_started.notify_one();
                handler_release.notified().await;
                response(StatusCode::CREATED, "application/json", Body::from(body))
            }
        }),
    )
    .expect("route validates");
    let token = fixture.token(fixture.account_a);
    let mut first = request(Method::POST, "/api/v1/slow", Some(&token), br#"{"x":1}"#);
    first
        .headers_mut()
        .insert("idempotency-key", "same-key-0002".parse().unwrap());
    let app_first = app.clone();
    let first_task =
        tokio::spawn(async move { send(app_first, first).await.expect("first response") });
    started.notified().await;
    let mut second = request(Method::POST, "/api/v1/slow", Some(&token), br#"{"x":1}"#);
    second
        .headers_mut()
        .insert("idempotency-key", "same-key-0002".parse().unwrap());
    let in_progress = send(app, second).await.expect("in-progress response");
    assert_eq!(in_progress.status, StatusCode::CONFLICT);
    assert_eq!(in_progress.headers[header::RETRY_AFTER], "1");
    assert_eq!(calls.load(Ordering::SeqCst), 1);
    release.notify_one();
    let _ = first_task.await.expect("handler task joins");
}

#[tokio::test]
async fn err_06_5xx_not_stored() {
    let fixture = fixture().expect("fixture builds");
    let idempotency = Arc::new(FakeIdempotency::default());
    let state = fixture.state.clone().with_idempotency(idempotency, 86_400);
    let calls = Arc::new(AtomicUsize::new(0));
    let handler_calls = calls.clone();
    let app = router(
        state,
        route_spec(
            Method::POST,
            "/retry",
            Access::Permission("identity.account.read"),
            IdemMode::Optional,
            true,
            false,
        ),
        post(move || {
            let attempt = handler_calls.fetch_add(1, Ordering::SeqCst);
            async move {
                if attempt == 0 {
                    response(
                        StatusCode::SERVICE_UNAVAILABLE,
                        "application/json",
                        Body::empty(),
                    )
                } else {
                    response(StatusCode::CREATED, "application/json", Body::from("{}"))
                }
            }
        }),
    )
    .expect("route validates");
    let token = fixture.token(fixture.account_a);
    let mut statuses = Vec::new();
    for _ in 0..2 {
        let mut request = request(Method::POST, "/api/v1/retry", Some(&token), b"{}");
        request
            .headers_mut()
            .insert("idempotency-key", "retry-key-0001".parse().unwrap());
        statuses.push(send(app.clone(), request).await.expect("response").status);
    }
    assert_eq!(
        statuses,
        [StatusCode::SERVICE_UNAVAILABLE, StatusCode::CREATED]
    );
    assert_eq!(calls.load(Ordering::SeqCst), 2);
}

#[tokio::test]
async fn api_72_per_account_keys() {
    let fixture = fixture().expect("fixture builds");
    let idempotency = Arc::new(FakeIdempotency::default());
    let state = fixture.state.clone().with_idempotency(idempotency, 86_400);
    let calls = Arc::new(AtomicUsize::new(0));
    let handler_calls = calls.clone();
    let app = router(
        state,
        route_spec(
            Method::POST,
            "/account-write",
            Access::Permission("identity.account.read"),
            IdemMode::Optional,
            true,
            false,
        ),
        post(move || {
            let handler_calls = handler_calls.clone();
            async move {
                handler_calls.fetch_add(1, Ordering::SeqCst);
                response(StatusCode::CREATED, "application/json", Body::from("{}"))
            }
        }),
    )
    .expect("route validates");
    for account in [fixture.account_a, fixture.account_b] {
        let token = fixture.token(account);
        let mut request = request(Method::POST, "/api/v1/account-write", Some(&token), b"{}");
        request
            .headers_mut()
            .insert("idempotency-key", "account-key-01".parse().unwrap());
        let response = send(app.clone(), request).await.expect("response");
        assert_eq!(response.status, StatusCode::CREATED);
    }
    assert_eq!(calls.load(Ordering::SeqCst), 2);
}

#[tokio::test]
async fn secret_routes_replay_status_only() {
    let fixture = fixture().expect("fixture builds");
    let idempotency = Arc::new(FakeIdempotency::default());
    let state = fixture.state.clone().with_idempotency(idempotency, 86_400);
    let calls = Arc::new(AtomicUsize::new(0));
    let handler_calls = calls.clone();
    let app = router(
        state,
        route_spec(
            Method::POST,
            "/auth/secret",
            Access::Permission("identity.account.read"),
            IdemMode::Optional,
            false,
            false,
        ),
        post(move || {
            let handler_calls = handler_calls.clone();
            async move {
                handler_calls.fetch_add(1, Ordering::SeqCst);
                response(
                    StatusCode::OK,
                    "application/json",
                    Body::from("secret response"),
                )
                .into_response_with_cookie()
            }
        }),
    )
    .expect("route validates");
    let token = fixture.token(fixture.account_a);
    let mut first = request(Method::POST, "/api/v1/auth/secret", Some(&token), b"{}");
    first
        .headers_mut()
        .insert("idempotency-key", "secret-key-001".parse().unwrap());
    let original = send(app.clone(), first).await.expect("first response");
    assert_eq!(
        original.headers[header::SET_COOKIE],
        "session=one; HttpOnly"
    );
    let mut second = request(Method::POST, "/api/v1/auth/secret", Some(&token), b"{}");
    second
        .headers_mut()
        .insert("idempotency-key", "secret-key-001".parse().unwrap());
    let replay = send(app, second).await.expect("replay response");
    assert_eq!(replay.status, StatusCode::OK);
    assert!(replay.body.is_empty());
    assert!(!replay.headers.contains_key(header::SET_COOKIE));
    assert_eq!(calls.load(Ordering::SeqCst), 1);
}

#[tokio::test]
async fn key_header_validation() {
    let fixture = fixture().expect("fixture builds");
    let idempotency = Arc::new(FakeIdempotency::default());
    let state = fixture.state.clone().with_idempotency(idempotency, 86_400);
    let app = router(
        state,
        route_spec(
            Method::POST,
            "/validate-key",
            Access::Permission("identity.account.read"),
            IdemMode::Optional,
            true,
            false,
        ),
        post(|| async { response(StatusCode::CREATED, "application/json", Body::from("{}")) }),
    )
    .expect("route validates");
    let token = fixture.token(fixture.account_a);
    let too_long = "a".repeat(129);
    for key in ["short", too_long.as_str(), "a b c d e f g h"] {
        let mut request = request(Method::POST, "/api/v1/validate-key", Some(&token), b"{}");
        request
            .headers_mut()
            .insert("idempotency-key", key.parse().unwrap());
        let response = send(app.clone(), request)
            .await
            .expect("validation response");
        assert_eq!(response.status, StatusCode::UNPROCESSABLE_ENTITY);
        let json: Value = serde_json::from_slice(&response.body).expect("problem JSON");
        assert_eq!(json["code"], "validation_failed");
        assert_eq!(json["errors"][0]["path"], "Idempotency-Key");
        assert_eq!(json["errors"][0]["code"], "key_invalid");
    }
}

#[tokio::test]
async fn optional_key_and_read_methods_bypass_idempotency() {
    let fixture = fixture().expect("fixture builds");
    let idempotency = Arc::new(FakeIdempotency::default());
    let state = fixture.state.clone().with_idempotency(idempotency, 86_400);
    let writes = Arc::new(AtomicUsize::new(0));
    let reads = Arc::new(AtomicUsize::new(0));
    let write_calls = writes.clone();
    let read_calls = reads.clone();
    let app = RouteTable::new()
        .route(
            route_spec(
                Method::POST,
                "/optional-write",
                Access::Permission("identity.account.read"),
                IdemMode::Optional,
                true,
                false,
            ),
            post(move || {
                let write_calls = write_calls.clone();
                async move {
                    write_calls.fetch_add(1, Ordering::SeqCst);
                    response(StatusCode::CREATED, "application/json", Body::from("{}"))
                }
            }),
        )
        .route(
            route_spec(
                Method::GET,
                "/read",
                Access::Permission("identity.account.read"),
                IdemMode::Optional,
                true,
                false,
            ),
            get(move || {
                let read_calls = read_calls.clone();
                async move {
                    read_calls.fetch_add(1, Ordering::SeqCst);
                    response(StatusCode::OK, "application/json", Body::from("{}"))
                }
            }),
        )
        .into_router(state)
        .expect("routes validate");
    let token = fixture.token(fixture.account_a);
    for _ in 0..2 {
        let response = send(
            app.clone(),
            request(Method::POST, "/api/v1/optional-write", Some(&token), b""),
        )
        .await
        .expect("optional request without key passes through");
        assert_eq!(response.status, StatusCode::CREATED);
        let mut read = request(Method::GET, "/api/v1/read", Some(&token), b"");
        read.headers_mut()
            .insert("idempotency-key", "read-key-0001".parse().unwrap());
        let response = send(app.clone(), read)
            .await
            .expect("read request passes through");
        assert_eq!(response.status, StatusCode::OK);
    }
    assert_eq!(writes.load(Ordering::SeqCst), 2);
    assert_eq!(reads.load(Ordering::SeqCst), 2);
}

trait CookieResponse {
    fn into_response_with_cookie(self) -> Response<Body>;
}

impl CookieResponse for Response<Body> {
    fn into_response_with_cookie(mut self) -> Response<Body> {
        self.headers_mut()
            .insert(header::SET_COOKIE, "session=one; HttpOnly".parse().unwrap());
        self
    }
}
