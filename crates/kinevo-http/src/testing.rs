//! Lightweight in-process server helper for module and route tests.

use axum::{
    Router,
    body::{Body, Bytes, to_bytes},
    http::{HeaderMap, Request, StatusCode},
};
use serde_json::Value;
use tower::ServiceExt;

/// An in-process router that executes requests through the same layers as production.
#[derive(Clone)]
pub struct TestServer {
    router: Router,
}

impl TestServer {
    /// Wraps a router for isolated `oneshot` requests.
    #[must_use]
    pub fn new(router: Router) -> Self {
        Self { router }
    }

    /// Sends one request and returns its status, headers, body bytes and optional JSON value.
    pub async fn request(&self, request: Request<Body>) -> Result<TestResponse, axum::Error> {
        let response = match self.router.clone().oneshot(request).await {
            Ok(response) => response,
            Err(never) => match never {},
        };
        let (parts, body) = response.into_parts();
        let body = to_bytes(body, usize::MAX).await?;
        let json = serde_json::from_slice(&body).ok();
        Ok(TestResponse {
            status: parts.status,
            headers: parts.headers,
            body,
            json,
        })
    }
}

/// Captured response fields from a `TestServer` request.
#[derive(Debug)]
pub struct TestResponse {
    /// HTTP response status.
    pub status: StatusCode,
    /// HTTP response headers.
    pub headers: HeaderMap,
    /// Raw response body.
    pub body: Bytes,
    /// Parsed JSON response when the body contains valid JSON.
    pub json: Option<Value>,
}
