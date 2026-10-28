//! RFC 9457 error response rendering.

use crate::layers::{current_catalog, current_request_id, current_request_locale};
use axum::http::{HeaderValue, StatusCode, header};
use axum::{
    Json,
    response::{IntoResponse, Response},
};
use kinevo_kernel::errors::{AppError, DetailValue, ErrorCode};
use serde_json::{Map, Value, json};

/// Converts a kernel error into the stable, localized problem response.
#[derive(Clone, Debug)]
pub struct ApiError(pub AppError);

/// Result type for HTTP handlers that return a kernel error.
pub type ApiResult<T> = Result<T, ApiError>;

impl From<AppError> for ApiError {
    fn from(error: AppError) -> Self {
        Self(error)
    }
}

impl IntoResponse for ApiError {
    fn into_response(self) -> Response {
        let error = self.0;
        let code = error.code;
        let status =
            StatusCode::from_u16(code.http_status()).unwrap_or(StatusCode::INTERNAL_SERVER_ERROR);
        let locale = current_request_locale();
        let request_id = current_request_id();
        let catalog = current_catalog();
        let mut body = Map::new();
        body.insert(
            "type".to_owned(),
            json!(format!("urn:kinevo:error:{}", code.as_str())),
        );
        body.insert(
            "title".to_owned(),
            json!(
                catalog
                    .title(code.as_str(), locale)
                    .unwrap_or(code.as_str())
            ),
        );
        body.insert("status".to_owned(), json!(status.as_u16()));
        body.insert(
            "detail".to_owned(),
            json!(
                catalog
                    .detail(code.as_str(), locale)
                    .unwrap_or("The request could not be processed.")
            ),
        );
        body.insert("code".to_owned(), json!(code.as_str()));
        body.insert("request_id".to_owned(), json!(request_id));

        if code != ErrorCode::InternalError {
            let field_errors = error
                .errors
                .iter()
                .map(|field| {
                    let mut value = Map::new();
                    value.insert("path".to_owned(), json!(field.path));
                    value.insert("code".to_owned(), json!(field.code.as_str()));
                    let details = serde_json::to_value(&field.details).unwrap_or(Value::Null);
                    if details
                        .as_object()
                        .is_some_and(|details| !details.is_empty())
                    {
                        value.insert("details".to_owned(), details);
                    }
                    Value::Object(value)
                })
                .collect::<Vec<_>>();
            if !field_errors.is_empty() {
                body.insert("errors".to_owned(), json!(field_errors));
            }
            let details = serde_json::to_value(&error.details).unwrap_or(Value::Null);
            if details
                .as_object()
                .is_some_and(|details| !details.is_empty())
            {
                body.insert("details".to_owned(), details);
            }
        }

        let mut response = (status, Json(Value::Object(body))).into_response();
        response.headers_mut().insert(
            header::CONTENT_TYPE,
            HeaderValue::from_static("application/problem+json"),
        );
        if status == StatusCode::UNAUTHORIZED {
            response
                .headers_mut()
                .insert(header::WWW_AUTHENTICATE, HeaderValue::from_static("Bearer"));
        }
        if let Some(retry_after) = retry_after(&error)
            && let Ok(value) = HeaderValue::from_str(&retry_after)
        {
            response.headers_mut().insert(header::RETRY_AFTER, value);
        }
        response.extensions_mut().insert(code);
        response
    }
}

fn retry_after(error: &AppError) -> Option<String> {
    match error.code {
        ErrorCode::IdempotencyInProgress => Some("1".to_owned()),
        ErrorCode::RateLimited | ErrorCode::AccountLocked => {
            match error.details.as_map().get("retry_after_seconds") {
                Some(DetailValue::Int(seconds)) if *seconds > 0 => Some(seconds.to_string()),
                _ => None,
            }
        }
        _ => None,
    }
}

/// Opaque 500 used when a handler panics; panic payloads can contain request data.
pub(crate) fn panic_response() -> Response {
    tracing::error!(
        request_id = %current_request_id(),
        error_code = ErrorCode::InternalError.as_str(),
        event = "panic"
    );
    ApiError(AppError::new(ErrorCode::InternalError)).into_response()
}
