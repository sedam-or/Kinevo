//! Embedded SPA assets and the non-API route fallback.

use axum::{
    Router,
    body::Body,
    http::{HeaderMap, HeaderValue, Method, StatusCode, Uri, header},
    response::{IntoResponse, Response},
    routing::any,
};
use rust_embed::RustEmbed;
use std::hash::{DefaultHasher, Hash, Hasher};

const CONTENT_SECURITY_POLICY: &str = "default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'";
const DEBUG_PAGE: &str = "Web UI assets are missing. Run `just build-web` to build them.";
const NOT_FOUND_PROBLEM: &str = r#"{"type":"about:blank","title":"Not Found","status":404}"#;

#[derive(RustEmbed)]
#[folder = "../../web/dist/"]
struct WebAssets;

/// Adds the SPA route fallback outside the API router.
pub fn with_fallback(router: Router, https: bool) -> Router {
    router.fallback(any(move |method: Method, uri: Uri, headers: HeaderMap| {
        std::future::ready(fallback(&method, &uri, &headers, https))
    }))
}

fn fallback(method: &Method, uri: &Uri, headers: &HeaderMap, https: bool) -> Response {
    let path = uri.path();
    let response = if is_api_path(path) {
        problem_not_found()
    } else if method != Method::GET && method != Method::HEAD {
        response(
            StatusCode::METHOD_NOT_ALLOWED,
            "",
            "text/plain; charset=utf-8",
        )
    } else if let Some(asset) = asset_for_path(path, headers) {
        asset
    } else if is_asset_path(path) {
        response(StatusCode::NOT_FOUND, "", "text/plain; charset=utf-8")
    } else if let Some(index) = asset_for_key("index.html", headers) {
        index
    } else {
        debug_fallback()
    };

    let mut response = apply_security_headers(response, https);
    if method == Method::HEAD {
        *response.body_mut() = Body::empty();
    }
    response
}

fn asset_for_path(path: &str, request_headers: &HeaderMap) -> Option<Response> {
    let key = path.strip_prefix('/')?;
    if key.is_empty() {
        return asset_for_key("index.html", request_headers);
    }
    if key.split('/').any(|part| part == ".." || part == ".") || key.contains('\\') {
        return None;
    }
    asset_for_key(key, request_headers)
}

fn asset_for_key(key: &str, request_headers: &HeaderMap) -> Option<Response> {
    let file = WebAssets::get(key)?;
    let bytes = file.data.into_owned();
    let mut hasher = DefaultHasher::new();
    bytes.hash(&mut hasher);
    let etag = format!("\"{:016x}\"", hasher.finish());
    let cache_control = if key == "index.html" {
        "no-cache"
    } else if is_hashed_asset(key) {
        "public, max-age=31536000, immutable"
    } else {
        "no-cache"
    };

    let mut response = if request_headers
        .get(header::IF_NONE_MATCH)
        .is_some_and(|value| value.as_bytes() == etag.as_bytes() || value.as_bytes() == b"*")
    {
        StatusCode::NOT_MODIFIED.into_response()
    } else {
        (StatusCode::OK, Body::from(bytes)).into_response()
    };
    response.headers_mut().insert(
        header::CONTENT_TYPE,
        HeaderValue::from_static(content_type(key)),
    );
    response.headers_mut().insert(
        header::CACHE_CONTROL,
        HeaderValue::from_static(cache_control),
    );
    if let Ok(value) = HeaderValue::from_str(&etag) {
        response.headers_mut().insert(header::ETAG, value);
    }
    Some(response)
}

fn is_api_path(path: &str) -> bool {
    path == "/api" || path.starts_with("/api/")
}

fn is_asset_path(path: &str) -> bool {
    path == "/assets" || path.starts_with("/assets/")
}

fn is_hashed_asset(key: &str) -> bool {
    let Some(file) = key.rsplit('/').next() else {
        return false;
    };
    let Some((stem, _extension)) = file.rsplit_once('.') else {
        return false;
    };
    let Some((_name, hash)) = stem.rsplit_once('-') else {
        return false;
    };
    hash.len() >= 8
        && hash
            .bytes()
            .all(|byte| byte.is_ascii_alphanumeric() || b"_-".contains(&byte))
}

fn content_type(key: &str) -> &'static str {
    match key.rsplit_once('.').map(|(_, extension)| extension) {
        Some("html") => "text/html; charset=utf-8",
        Some("css") => "text/css; charset=utf-8",
        Some("js" | "mjs") => "text/javascript; charset=utf-8",
        Some("json") => "application/json",
        Some("svg") => "image/svg+xml",
        Some("png") => "image/png",
        Some("jpg" | "jpeg") => "image/jpeg",
        Some("webp") => "image/webp",
        Some("ico") => "image/vnd.microsoft.icon",
        Some("woff") => "font/woff",
        Some("woff2") => "font/woff2",
        Some("wasm") => "application/wasm",
        _ => "application/octet-stream",
    }
}

fn problem_not_found() -> Response {
    response(
        StatusCode::NOT_FOUND,
        NOT_FOUND_PROBLEM,
        "application/problem+json",
    )
}

fn debug_fallback() -> Response {
    #[cfg(debug_assertions)]
    {
        response(StatusCode::OK, DEBUG_PAGE, "text/plain; charset=utf-8")
    }
    #[cfg(not(debug_assertions))]
    {
        response(
            StatusCode::INTERNAL_SERVER_ERROR,
            DEBUG_PAGE,
            "text/plain; charset=utf-8",
        )
    }
}

fn response(status: StatusCode, body: &str, content_type: &'static str) -> Response {
    let mut response = (status, Body::from(body.to_owned())).into_response();
    response
        .headers_mut()
        .insert(header::CONTENT_TYPE, HeaderValue::from_static(content_type));
    response
}

fn apply_security_headers(mut response: Response, https: bool) -> Response {
    let headers = response.headers_mut();
    headers.insert(
        "content-security-policy",
        HeaderValue::from_static(CONTENT_SECURITY_POLICY),
    );
    headers.insert("referrer-policy", HeaderValue::from_static("no-referrer"));
    headers.insert(
        "x-content-type-options",
        HeaderValue::from_static("nosniff"),
    );
    headers.insert("permissions-policy", HeaderValue::from_static(""));
    if https {
        headers.insert(
            "strict-transport-security",
            HeaderValue::from_static("max-age=31536000; includeSubDomains"),
        );
    }
    response
}
