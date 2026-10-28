//! Optimistic-concurrency HTTP contracts.

use axum::{
    extract::FromRequestParts,
    http::{header, request::Parts},
};
use kinevo_kernel::errors::{AppError, DetailValue, ErrorCode, FieldCode, FieldError};
use std::future::{Future, ready};

use crate::problem::ApiError;

/// Positive resource version supplied by an `If-Match` header.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct Version(pub i64);

/// Extracted optimistic-concurrency precondition.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct IfMatch(pub Version);

impl<S> FromRequestParts<S> for IfMatch
where
    S: Send + Sync,
{
    type Rejection = ApiError;

    fn from_request_parts(
        parts: &mut Parts,
        _state: &S,
    ) -> impl Future<Output = Result<Self, Self::Rejection>> + Send {
        ready(parse_header(parts.headers.get(header::IF_MATCH)))
    }
}

fn parse_header(value: Option<&axum::http::HeaderValue>) -> Result<IfMatch, ApiError> {
    let Some(value) = value else {
        return Err(ApiError(
            AppError::new(ErrorCode::PreconditionRequired)
                .with_detail("header", DetailValue::Str("If-Match")),
        ));
    };
    let Ok(value) = value.to_str() else {
        return Err(invalid_if_match());
    };
    let Some(version) = value
        .strip_prefix('"')
        .and_then(|value| value.strip_suffix('"'))
    else {
        return Err(invalid_if_match());
    };
    if version.is_empty() || !version.bytes().all(|byte| byte.is_ascii_digit()) {
        return Err(invalid_if_match());
    }
    let Ok(version) = version.parse::<i64>() else {
        return Err(invalid_if_match());
    };
    if version <= 0 {
        return Err(invalid_if_match());
    }
    Ok(IfMatch(Version(version)))
}

fn invalid_if_match() -> ApiError {
    ApiError(AppError::validation(vec![FieldError {
        path: "If-Match".to_owned(),
        code: FieldCode::IntInvalid,
        details: kinevo_kernel::errors::Details::default(),
    }]))
}

/// Builds the standard 409 response with the current safe resource snapshot.
pub fn version_conflict<T: serde::Serialize>(current_version: i64, current: &T) -> AppError {
    kinevo_kernel::errors::version_conflict(current_version, current)
}
