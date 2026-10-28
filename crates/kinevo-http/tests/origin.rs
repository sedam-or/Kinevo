#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "acceptance tests use explicit synthetic requests"
)]

#[path = "support/mod.rs"]
mod support;

use async_trait::async_trait;
use axum::{
    body::Body,
    http::{Method, Request, StatusCode, header},
    response::IntoResponse,
    routing::post,
};
use kinevo_http::{
    origin::{OriginAuditHook, OriginConfig},
    route::{Access, IdemMode},
};
use kinevo_kernel::id::AccountId;
use std::sync::{
    Arc,
    atomic::{AtomicUsize, Ordering},
};
use support::{fixture, request, route_spec, router, send};
use uuid::Uuid;

#[derive(Default)]
struct AuditCounter(AtomicUsize);

#[async_trait]
impl OriginAuditHook for AuditCounter {
    async fn on_csrf_failure(&self, _account: Option<AccountId>, _request_id: Option<Uuid>) {
        self.0.fetch_add(1, Ordering::SeqCst);
    }
}

#[tokio::test]
async fn sec_09_csrf_matrix() {
    let fixture = fixture().expect("fixture builds");
    let audit = Arc::new(AuditCounter::default());
    let state = fixture
        .state
        .clone()
        .with_origin_config(OriginConfig {
            public_origin: Some("https://api.example.test".to_owned()),
            android_origin_enabled: true,
        })
        .with_origin_audit_hook(audit.clone());
    let app = router(
        state,
        route_spec(
            Method::POST,
            "/auth/refresh",
            Access::Public,
            IdemMode::Off,
            false,
            true,
        ),
        post(|| async { StatusCode::NO_CONTENT.into_response() }),
    )
    .expect("cookie route validates");
    let cases = [
        (None, None),
        (Some("unknown"), Some("https://api.example.test")),
        (Some("web"), Some("https://evil.example")),
        (Some("web"), Some("https://api.example.test:444")),
        (Some("web"), Some("null")),
    ];
    for (client, origin) in cases {
        let mut req = request(Method::POST, "/api/v1/auth/refresh", None, b"");
        if let Some(client) = client {
            req.headers_mut()
                .insert("x-kinevo-client", client.parse().unwrap());
        }
        if let Some(origin) = origin {
            req.headers_mut()
                .insert(header::ORIGIN, origin.parse().unwrap());
        }
        let response = send(app.clone(), req).await.expect("CSRF response");
        assert_eq!(response.status, StatusCode::FORBIDDEN);
        let body: serde_json::Value = serde_json::from_slice(&response.body).expect("problem JSON");
        assert_eq!(body["code"], "csrf_check_failed");
    }
    let mut valid = request(Method::POST, "/api/v1/auth/refresh", None, b"");
    valid
        .headers_mut()
        .insert("x-kinevo-client", "web".parse().unwrap());
    valid
        .headers_mut()
        .insert(header::ORIGIN, "https://api.example.test".parse().unwrap());
    assert_eq!(
        send(app.clone(), valid).await.expect("web success").status,
        StatusCode::NO_CONTENT
    );
    let mut android = request(Method::POST, "/api/v1/auth/refresh", None, b"");
    android
        .headers_mut()
        .insert("x-kinevo-client", "android".parse().unwrap());
    assert_eq!(
        send(app.clone(), android)
            .await
            .expect("android success")
            .status,
        StatusCode::NO_CONTENT
    );
    let mut android_cookie = request(Method::POST, "/api/v1/auth/refresh", None, b"");
    android_cookie
        .headers_mut()
        .insert("x-kinevo-client", "android".parse().unwrap());
    android_cookie
        .headers_mut()
        .insert(header::COOKIE, "refresh=secret".parse().unwrap());
    assert_eq!(
        send(app, android_cookie)
            .await
            .expect("android cookie denied")
            .status,
        StatusCode::FORBIDDEN
    );
    assert_eq!(audit.0.load(Ordering::SeqCst), 6);
}

#[tokio::test]
async fn d_37_cors_android_only() {
    let fixture = fixture().expect("fixture builds");
    let state = fixture.state.clone().with_origin_config(OriginConfig {
        public_origin: Some("https://api.example.test".to_owned()),
        android_origin_enabled: true,
    });
    let app = router(
        state,
        route_spec(
            Method::POST,
            "/cors",
            Access::Public,
            IdemMode::Off,
            true,
            false,
        ),
        post(|| async { StatusCode::NO_CONTENT.into_response() }),
    )
    .expect("route validates");
    for (origin, allowed) in [
        ("https://app.kinevo.localhost", true),
        ("https://evil.example", false),
    ] {
        let req = Request::builder()
            .method(Method::OPTIONS)
            .uri("/api/v1/cors")
            .header(header::ORIGIN, origin)
            .header("access-control-request-method", "POST")
            .header("access-control-request-headers", "content-type")
            .body(Body::empty())
            .expect("preflight request is valid");
        let response = send(app.clone(), req).await.expect("preflight response");
        assert_eq!(
            response.headers.contains_key("access-control-allow-origin"),
            allowed
        );
        assert!(
            !response
                .headers
                .contains_key("access-control-allow-credentials")
        );
        if allowed {
            assert_eq!(response.status, StatusCode::NO_CONTENT);
            assert_eq!(response.headers["access-control-allow-origin"], origin);
        }
    }
}
