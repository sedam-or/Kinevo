//! Strict JSON/query extractors and common pagination parameters.

use crate::{layers::BodyLimit, problem::ApiError};
use axum::{
    body::to_bytes,
    extract::{FromRequest, FromRequestParts, Query, Request},
    http::{self, request::Parts},
};
use kinevo_kernel::{
    errors::{AppError, DetailValue, Details, ErrorCode, FieldCode, FieldError},
    page::{CursorCodec, CursorPosition, PageRequest},
    validate::Collector,
};
use serde::de::DeserializeOwned;

/// Validation pass run after a request body has deserialized successfully.
pub trait Validate {
    /// Collects every field-level validation failure for the deserialized value.
    fn validate(&self) -> Collector;
}

/// JSON request body after strict deserialization and domain validation.
#[derive(Debug)]
pub struct ValidatedJson<T>(pub T);

impl<S, T> FromRequest<S> for ValidatedJson<T>
where
    S: Send + Sync,
    T: DeserializeOwned + Validate + Send,
{
    type Rejection = ApiError;

    async fn from_request(request: Request, _state: &S) -> Result<Self, Self::Rejection> {
        if !is_json_content_type(request.headers()) {
            return Err(unsupported_media_type());
        }
        let max_bytes = request
            .extensions()
            .get::<BodyLimit>()
            .map_or(usize::MAX, |limit| limit.0);
        let body = request.into_body();
        let bytes = to_bytes(body, max_bytes)
            .await
            .map_err(|_| payload_too_large(max_bytes))?;
        let mut deserializer = serde_json::Deserializer::from_slice(&bytes);
        let value: T = serde_path_to_error::deserialize(&mut deserializer).map_err(|error| {
            let message = error.inner().to_string();
            let unknown = unknown_field_name(&message);
            let serde_path = error.path().to_string();
            let path = unknown
                .clone()
                .unwrap_or_else(|| non_empty_path(&serde_path));
            let code = if unknown.is_some() {
                FieldCode::UnknownField
            } else {
                FieldCode::SchemaInvalid
            };
            ApiError(AppError::validation(vec![FieldError {
                path,
                code,
                details: Details::new(),
            }]))
        })?;
        if deserializer.end().is_err() {
            return Err(ApiError(AppError::validation(vec![FieldError {
                path: "body".to_owned(),
                code: FieldCode::SchemaInvalid,
                details: Details::new(),
            }])));
        }
        value.validate().finish().map_err(ApiError::from)?;
        Ok(Self(value))
    }
}

/// Query parameters deserialized with `deny_unknown_fields` on the target type.
#[derive(Debug)]
pub struct StrictQuery<T>(pub T);

impl<S, T> FromRequestParts<S> for StrictQuery<T>
where
    S: Send + Sync,
    T: DeserializeOwned,
{
    type Rejection = ApiError;

    #[allow(clippy::unused_async_trait_impl)]
    async fn from_request_parts(parts: &mut Parts, _state: &S) -> Result<Self, Self::Rejection> {
        match Query::<T>::try_from_uri(&parts.uri) {
            Ok(Query(value)) => Ok(Self(value)),
            Err(error) => {
                let message = error.to_string();
                if let Some(name) = unknown_field_name(&message) {
                    Err(ApiError(
                        AppError::new(ErrorCode::QueryParamUnknown)
                            .with_detail("name", DetailValue::OwnedStr(name)),
                    ))
                } else {
                    let path = message
                        .split_once(':')
                        .map_or_else(|| "query".to_owned(), |(path, _)| non_empty_path(path));
                    Err(ApiError(AppError::validation(vec![FieldError {
                        path,
                        code: FieldCode::SchemaInvalid,
                        details: Details::new(),
                    }])))
                }
            }
        }
    }
}

/// API-30 page limit and verified cursor values.
#[derive(Debug, Eq, PartialEq)]
pub struct PageParams {
    /// Requested page size.
    pub limit: u16,
    /// Verified position from the supplied cursor, if present.
    pub cursor: Option<CursorPosition>,
}

impl PageParams {
    /// Parses a page request and binds its cursor to endpoint and total sort order.
    pub fn parse(
        limit: Option<&str>,
        cursor: Option<&str>,
        codec: &CursorCodec,
        endpoint: &str,
        sort: &SortParam,
    ) -> Result<Self, AppError> {
        let limit = limit
            .map(str::parse::<i64>)
            .transpose()
            .map_err(|_| invalid_page_limit())?;
        let request = PageRequest::parse(limit, cursor, codec, endpoint, &sort.sort_key())?;
        Ok(Self {
            limit: request.limit,
            cursor: request.cursor,
        })
    }
}

/// Parsed allow-listed sort field and direction, with the stable `id` tiebreaker.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct SortParam {
    /// Selected field name.
    pub field: String,
    /// Whether the selected field is descending.
    pub descending: bool,
}

impl SortParam {
    /// Parses one allow-listed field; `id` is always available as the final tiebreaker.
    pub fn parse(raw: &str, allowed: &[&str]) -> Result<Self, AppError> {
        let (descending, field) = raw
            .strip_prefix('-')
            .map_or((false, raw), |field| (true, field));
        let mut all_allowed = allowed
            .iter()
            .map(|field| (*field).to_owned())
            .collect::<Vec<_>>();
        if !all_allowed.iter().any(|field| field == "id") {
            all_allowed.push("id".to_owned());
        }
        if field.is_empty() || !all_allowed.iter().any(|candidate| candidate == field) {
            return Err(AppError::new(ErrorCode::SortInvalid)
                .with_detail("allowed", DetailValue::StrList(all_allowed)));
        }
        Ok(Self {
            field: field.to_owned(),
            descending,
        })
    }

    /// Returns the deterministic sort expression used to bind a cursor.
    #[must_use]
    pub fn sort_key(&self) -> String {
        if self.field == "id" {
            return "id".to_owned();
        }
        let direction = if self.descending { "-" } else { "" };
        format!("{direction}{},id", self.field)
    }
}

fn is_json_content_type(headers: &http::HeaderMap) -> bool {
    let Some(value) = headers
        .get(http::header::CONTENT_TYPE)
        .and_then(|value| value.to_str().ok())
    else {
        return false;
    };
    let mut parts = value.split(';');
    if !parts
        .next()
        .unwrap_or_default()
        .trim()
        .eq_ignore_ascii_case("application/json")
    {
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

fn unsupported_media_type() -> ApiError {
    ApiError(AppError::new(ErrorCode::UnsupportedMediaType).with_detail(
        "accepted",
        DetailValue::StrList(vec!["application/json".to_owned()]),
    ))
}

fn payload_too_large(max_bytes: usize) -> ApiError {
    ApiError(AppError::new(ErrorCode::PayloadTooLarge).with_detail(
        "max_bytes",
        DetailValue::Int(i64::try_from(max_bytes).unwrap_or(i64::MAX)),
    ))
}

fn invalid_page_limit() -> AppError {
    AppError::validation(vec![FieldError {
        path: "limit".to_owned(),
        code: FieldCode::IntInvalid,
        details: Details::new(),
    }])
}

fn unknown_field_name(message: &str) -> Option<String> {
    let marker = "unknown field `";
    let after = message.split_once(marker)?.1;
    let name = after.split_once('`')?.0;
    (!name.is_empty()).then(|| name.to_owned())
}

fn non_empty_path(path: &str) -> String {
    let path = path.trim();
    if path.is_empty() {
        "body".to_owned()
    } else {
        path.to_owned()
    }
}

#[cfg(test)]
mod tests {
    use super::SortParam;
    use kinevo_kernel::errors::AppError;

    #[test]
    fn api_31_sort_appends_id_tiebreaker() -> Result<(), AppError> {
        let sort = SortParam::parse("-title", &["title", "created_at"])?;
        assert_eq!(sort.sort_key(), "-title,id");
        let id_sort = SortParam::parse("id", &["title"])?;
        assert_eq!(id_sort.sort_key(), "id");
        Ok(())
    }
}
