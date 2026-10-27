//! Request context, bounded bodies, safe tracing and server security headers.

use crate::{i18n::Catalog, problem::ApiError, state::HttpState};
use axum::{
    body::{Body, Bytes, to_bytes},
    extract::Request,
    http::{HeaderValue, Method, header},
    middleware::Next,
    response::{IntoResponse, Response},
};
use kinevo_kernel::{
    errors::{AppError, DetailValue, ErrorCode},
    validate::tz::Locale,
};
use std::cell::Cell;
use uuid::Uuid;

tokio::task_local! {
    static REQUEST_CONTEXT: RequestContext;
}

struct RequestContext {
    id_text: String,
    locale: Cell<Locale>,
    catalog: std::sync::Arc<Catalog>,
}

/// Request id extension added to every routed request.
#[derive(Clone, Debug)]
pub struct RequestId(pub Uuid);

/// Body limit passed to extractors after the request body has been checked.
#[derive(Clone, Copy, Debug)]
pub struct BodyLimit(pub usize);

/// Runs request context before routes and applies response-wide safety headers.
pub async fn request_context(
    axum::extract::State(state): axum::extract::State<HttpState>,
    mut request: Request,
    next: Next,
) -> Response {
    let supplied_text = request
        .headers()
        .get("X-Request-Id")
        .and_then(|value| value.to_str().ok());
    let supplied_id = supplied_text.and_then(|value| Uuid::parse_str(value).ok());
    let (id, id_text) = if let Some(id) = supplied_id {
        (id, supplied_text.unwrap_or_default().to_owned())
    } else {
        let id = state.ids.new_uuid_v7();
        (id, id.to_string())
    };
    request.extensions_mut().insert(RequestId(id));

    let fallback = state
        .instance
        .get()
        .await
        .map_or(Locale::En, |info| info.default_locale);
    let accept_language = request
        .headers()
        .get(header::ACCEPT_LANGUAGE)
        .and_then(|value| value.to_str().ok());
    let context = RequestContext {
        id_text,
        locale: Cell::new(crate::i18n::negotiate(accept_language, fallback)),
        catalog: state.i18n.clone(),
    };

    REQUEST_CONTEXT
        .scope(context, async move {
            let method = request.method().clone();
            let route = request
                .extensions()
                .get::<axum::extract::MatchedPath>()
                .map_or_else(|| "unmatched".to_owned(), |path| path.as_str().to_owned());
            let request_path = request.uri().path().to_owned();
            let mut response = match read_bounded_body(&mut request, state.max_body_bytes).await {
                Ok(bytes) => {
                    if !bytes.is_empty() && !has_json_content_type(request.headers()) {
                        ApiError(AppError::new(ErrorCode::UnsupportedMediaType).with_detail(
                            "accepted",
                            DetailValue::StrList(vec!["application/json".to_owned()]),
                        ))
                        .into_response()
                    } else {
                        request
                            .extensions_mut()
                            .insert(BodyLimit(state.max_body_bytes));
                        *request.body_mut() = Body::from(bytes);
                        next.run(request).await
                    }
                }
                Err(()) => ApiError(AppError::new(ErrorCode::PayloadTooLarge).with_detail(
                    "max_bytes",
                    DetailValue::Int(i64::try_from(state.max_body_bytes).unwrap_or(i64::MAX)),
                ))
                .into_response(),
            };
            apply_security_headers(&mut response, &state, &method, &request_path);
            let request_id = REQUEST_CONTEXT
                .try_with(|context| context.id_text.clone())
                .unwrap_or_else(|_| id.to_string());
            log_response(&request_id, &route, &method, &response);
            response
        })
        .await
}

async fn read_bounded_body(request: &mut Request, max_bytes: usize) -> Result<Bytes, ()> {
    let body = std::mem::replace(request.body_mut(), Body::empty());
    to_bytes(body, max_bytes.saturating_add(1))
        .await
        .map_err(|_| ())
        .and_then(|bytes| {
            if bytes.len() > max_bytes {
                Err(())
            } else {
                Ok(bytes)
            }
        })
}

fn has_json_content_type(headers: &axum::http::HeaderMap) -> bool {
    let Some(value) = headers
        .get(header::CONTENT_TYPE)
        .and_then(|value| value.to_str().ok())
    else {
        return false;
    };
    let mut parts = value.split(';');
    let media_type = parts.next().unwrap_or_default().trim();
    if !media_type.eq_ignore_ascii_case("application/json") {
        return false;
    }
    parts.all(|part| {
        let Some((name, value)) = part.trim().split_once('=') else {
            return false;
        };
        name.trim().eq_ignore_ascii_case("charset")
            && value.trim().trim_matches('"').eq_ignore_ascii_case("utf-8")
    })
}

fn apply_security_headers(response: &mut Response, state: &HttpState, method: &Method, path: &str) {
    let headers = response.headers_mut();
    headers.insert(
        "Content-Security-Policy",
        HeaderValue::from_static("default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'"),
    );
    headers.insert("Referrer-Policy", HeaderValue::from_static("no-referrer"));
    headers.insert(
        "X-Content-Type-Options",
        HeaderValue::from_static("nosniff"),
    );
    headers.insert("Permissions-Policy", HeaderValue::from_static(""));
    if let Ok(request_id) = REQUEST_CONTEXT.try_with(|context| context.id_text.clone())
        && let Ok(value) = HeaderValue::from_str(&request_id)
    {
        headers.insert("X-Request-Id", value);
    }
    if state
        .public_url
        .as_ref()
        .is_some_and(|url| url.as_str().starts_with("https://"))
    {
        headers.insert(
            "Strict-Transport-Security",
            HeaderValue::from_static("max-age=31536000; includeSubDomains"),
        );
    }
    if method == Method::GET && path.starts_with("/api/v1/") {
        let cache = if matches!(path, "/api/v1/openapi.json" | "/api/v1/instance") {
            "public, max-age=60"
        } else {
            "no-store"
        };
        headers.insert(header::CACHE_CONTROL, HeaderValue::from_static(cache));
    } else if matches!(path, "/livez" | "/readyz") {
        headers.insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    }
}

fn log_response(request_id: &str, route: &str, method: &Method, response: &Response) {
    let status = response.status().as_u16();
    let error_code = response
        .extensions()
        .get::<ErrorCode>()
        .map_or("", |code| code.as_str());
    if response.status().is_server_error() {
        tracing::error!(request_id, route, method = %method, status, error_code, event = "response");
    } else if response.status().is_client_error() {
        tracing::info!(request_id, route, method = %method, status, error_code, event = "response");
    } else {
        tracing::info!(request_id, route, method = %method, status, event = "response");
    }
}

/// Current request id for `ApiError` rendering.
#[must_use]
pub fn current_request_id() -> String {
    REQUEST_CONTEXT
        .try_with(|context| context.id_text.clone())
        .unwrap_or_else(|_| Uuid::nil().to_string())
}

/// Current negotiated locale for `ApiError` rendering.
#[must_use]
pub fn current_request_locale() -> Locale {
    REQUEST_CONTEXT
        .try_with(|context| context.locale.get())
        .unwrap_or(Locale::En)
}

/// Current request catalogue for error response rendering.
#[must_use]
pub fn current_catalog() -> std::sync::Arc<Catalog> {
    REQUEST_CONTEXT
        .try_with(|context| context.catalog.clone())
        .unwrap_or_else(|_| std::sync::Arc::new(Catalog::load()))
}

/// Overrides locale after authentication has supplied an account preference.
pub fn set_request_locale(locale: Locale) {
    let _ = REQUEST_CONTEXT.try_with(|context| context.locale.set(locale));
}
