//! Cookie-endpoint CSRF protection and closed CORS handling.

use crate::{
    auth::AuthCtx,
    layers::RequestId,
    route::{RouteSpec, route_path},
    state::HttpState,
};
use async_trait::async_trait;
use axum::{
    extract::{MatchedPath, Request, State},
    http::{HeaderMap, HeaderValue, Method, StatusCode, Uri, header},
    middleware::Next,
    response::{IntoResponse, Response},
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
};
use std::sync::Arc;
use uuid::Uuid;

/// Android `WebView` origin allowed by D-37 when enabled by operator policy.
pub const ANDROID_SHELL_ORIGIN: &str = "https://app.kinevo.localhost";

/// Operator-provided origin policy for cookie endpoints and Android CORS.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct OriginConfig {
    /// Public web origin, compared by scheme, host and effective port.
    pub public_origin: Option<String>,
    /// Whether the Android shell origin receives CORS headers.
    pub android_origin_enabled: bool,
}

impl Default for OriginConfig {
    fn default() -> Self {
        Self {
            public_origin: None,
            android_origin_enabled: true,
        }
    }
}

/// Receives a security event without request content or credentials.
#[async_trait]
pub trait OriginAuditHook: Send + Sync {
    /// Records a failed cookie-endpoint CSRF/origin check.
    async fn on_csrf_failure(&self, account: Option<AccountId>, request_id: Option<Uuid>);
}

/// Safe fallback logger used until the identity audit writer is wired at composition time.
pub struct TracingOriginAuditHook;

#[async_trait]
impl OriginAuditHook for TracingOriginAuditHook {
    async fn on_csrf_failure(&self, _account: Option<AccountId>, request_id: Option<Uuid>) {
        tracing::info!(
            error_code = ErrorCode::CsrfCheckFailed.as_str(),
            request_id = ?request_id,
            event = "csrf_check_failed"
        );
    }
}

#[derive(Clone)]
pub(crate) struct OriginRequestState {
    pub state: HttpState,
    pub specs: Arc<Vec<RouteSpec>>,
}

/// Rejects cookie endpoints unless their client marker and origin match SEC-09.
pub(crate) async fn origin_guard(
    State(context): State<OriginRequestState>,
    request: Request,
    next: Next,
) -> Response {
    let Some(spec) = matched_spec(&context.specs, &request) else {
        return next.run(request).await;
    };
    if !spec.cookie_endpoint {
        return next.run(request).await;
    }

    let headers = request.headers();
    let client = headers
        .get("X-Kinevo-Client")
        .and_then(|value| value.to_str().ok());
    let allowed = match client {
        Some("web") => headers
            .get(header::ORIGIN)
            .and_then(|value| value.to_str().ok())
            .zip(context.state.origin_config.public_origin.as_deref())
            .is_some_and(|(origin, public)| same_origin(origin, public)),
        Some("android") => !headers.contains_key(header::COOKIE),
        _ => false,
    };
    if allowed {
        return next.run(request).await;
    }

    let account = request
        .extensions()
        .get::<AuthCtx>()
        .map(AuthCtx::account_id);
    let request_id = request.extensions().get::<RequestId>().map(|id| id.0);
    context
        .state
        .origin_audit_hook
        .on_csrf_failure(account, request_id)
        .await;
    crate::problem::ApiError(AppError::new(ErrorCode::CsrfCheckFailed)).into_response()
}

/// Answers Android preflight requests and adds no credentialed CORS headers.
pub(crate) async fn cors(State(state): State<HttpState>, request: Request, next: Next) -> Response {
    let origin = request
        .headers()
        .get(header::ORIGIN)
        .and_then(|value| value.to_str().ok())
        .map(str::to_owned);
    let is_preflight = request.method() == Method::OPTIONS
        && request
            .headers()
            .contains_key("access-control-request-method");
    if is_preflight {
        let Some(origin) = origin.filter(|origin| allowed_android_origin(&state, origin)) else {
            return StatusCode::FORBIDDEN.into_response();
        };
        let mut response = StatusCode::NO_CONTENT.into_response();
        apply_cors_headers(response.headers_mut(), &origin, true);
        return response;
    }

    let mut response = next.run(request).await;
    response
        .headers_mut()
        .remove("access-control-allow-credentials");
    if let Some(origin) = origin.filter(|origin| allowed_android_origin(&state, origin)) {
        apply_cors_headers(response.headers_mut(), &origin, false);
    } else {
        response.headers_mut().remove("access-control-allow-origin");
    }
    response
}

fn apply_cors_headers(headers: &mut HeaderMap, origin: &str, preflight: bool) {
    if let Ok(value) = HeaderValue::from_str(origin) {
        headers.insert("access-control-allow-origin", value);
    }
    headers.insert(header::VARY, HeaderValue::from_static("Origin"));
    if preflight {
        headers.insert(
            "access-control-allow-methods",
            HeaderValue::from_static("GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS"),
        );
        headers.insert(
            "access-control-allow-headers",
            HeaderValue::from_static(
                "authorization, content-type, idempotency-key, if-match, x-kinevo-client, x-request-id",
            ),
        );
    }
}

fn allowed_android_origin(state: &HttpState, origin: &str) -> bool {
    state.origin_config.android_origin_enabled && same_origin(origin, ANDROID_SHELL_ORIGIN)
}

#[derive(Clone, Debug, Eq, PartialEq)]
struct OriginParts {
    scheme: String,
    host: String,
    port: u16,
}

fn same_origin(left: &str, right: &str) -> bool {
    match (OriginParts::parse(left), OriginParts::parse(right)) {
        (Some(left), Some(right)) => {
            left.scheme.eq_ignore_ascii_case(&right.scheme)
                && left.host.eq_ignore_ascii_case(&right.host)
                && left.port == right.port
        }
        _ => false,
    }
}

impl OriginParts {
    fn parse(value: &str) -> Option<Self> {
        if value == "null" {
            return None;
        }
        let uri: Uri = value.parse().ok()?;
        let scheme = uri.scheme_str()?.to_ascii_lowercase();
        let authority = uri.authority()?;
        if authority.as_str().contains('@')
            || uri
                .path_and_query()
                .is_some_and(|path| path.as_str() != "/")
        {
            return None;
        }
        let port = authority.port_u16().or(match scheme.as_str() {
            "https" => Some(443),
            "http" => Some(80),
            _ => None,
        })?;
        Some(Self {
            scheme,
            host: authority.host().to_ascii_lowercase(),
            port,
        })
    }
}

fn matched_spec<'a>(specs: &'a [RouteSpec], request: &Request) -> Option<&'a RouteSpec> {
    let matched_path = request.extensions().get::<MatchedPath>()?.as_str();
    let method = if request.method() == Method::HEAD {
        &Method::GET
    } else {
        request.method()
    };
    specs
        .iter()
        .find(|spec| spec.method == *method && route_path(spec.path) == matched_path)
}
