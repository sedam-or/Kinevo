//! Refresh-cookie construction and parsing without serialising secret types.

use axum::http::{HeaderMap, HeaderValue, header};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    secret::Secret,
};

/// HTTPS refresh-cookie name required by the authentication standard.
pub const REFRESH_COOKIE_SECURE: &str = "__Secure-kinevo_rt";
/// Plain HTTP development cookie name; it is never used for an HTTPS origin.
pub const REFRESH_COOKIE_PLAIN: &str = "kinevo_rt";

/// Selects whether the configured public origin uses the secure cookie form.
/// An absent or unrecognised origin defaults to the secure form.
#[must_use]
pub fn is_secure_origin(public_origin: Option<&str>) -> bool {
    !public_origin.is_some_and(|origin| {
        origin
            .get(..7)
            .is_some_and(|scheme| scheme.eq_ignore_ascii_case("http://"))
    })
}

/// Builds the exact refresh-cookie attributes for the configured transport.
pub fn build_refresh_cookie(
    token: &str,
    remaining_seconds: u32,
    secure: bool,
) -> Result<HeaderValue, AppError> {
    if token.is_empty() || !safe_cookie_value(token) {
        return Err(internal_error());
    }
    cookie_header(token, remaining_seconds, secure)
}

/// Builds an expired cookie with the same name and attributes as the active cookie.
pub fn clear_refresh_cookie(secure: bool) -> Result<HeaderValue, AppError> {
    cookie_header("", 0, secure)
}

/// Reads exactly one refresh cookie and wraps its value immediately as a secret.
#[must_use]
pub fn read_refresh_cookie(headers: &HeaderMap, secure: bool) -> Option<Secret<String>> {
    let name = cookie_name(secure);
    let mut found = None;
    for header_value in &headers.get_all(header::COOKIE) {
        let Ok(header_value) = header_value.to_str() else {
            continue;
        };
        for segment in header_value.split(';') {
            let Some((candidate, value)) = segment.trim().split_once('=') else {
                continue;
            };
            if candidate.trim() != name {
                continue;
            }
            if found.is_some() || !safe_cookie_value(value.trim()) {
                return None;
            }
            found = Some(value.trim().to_owned());
        }
    }
    found.map(Secret::new)
}

fn cookie_header(
    token: &str,
    remaining_seconds: u32,
    secure: bool,
) -> Result<HeaderValue, AppError> {
    let name = cookie_name(secure);
    let secure_attribute = if secure { "; Secure" } else { "" };
    let cookie = format!(
        "{name}={token}; HttpOnly{secure_attribute}; SameSite=Strict; Path=/api/v1/auth; Max-Age={remaining_seconds}"
    );
    HeaderValue::from_str(&cookie).map_err(|_| internal_error())
}

const fn cookie_name(secure: bool) -> &'static str {
    if secure {
        REFRESH_COOKIE_SECURE
    } else {
        REFRESH_COOKIE_PLAIN
    }
}

fn safe_cookie_value(value: &str) -> bool {
    value
        .bytes()
        .all(|byte| byte.is_ascii_alphanumeric() || matches!(byte, b'-' | b'_'))
}

fn internal_error() -> AppError {
    AppError::new(ErrorCode::InternalError)
}
