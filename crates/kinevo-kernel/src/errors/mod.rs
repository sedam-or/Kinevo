//! Typed application, field and process errors owned by the kernel.

mod codes;

pub use codes::{ErrorCode, FieldCode, ProcessCode};
use serde::Serialize;
use std::collections::BTreeMap;

/// The class of action a client should take before retrying an operation.
#[derive(Clone, Copy, Debug, Eq, Hash, PartialEq, Serialize, serde::Deserialize)]
#[serde(rename_all = "snake_case")]
pub enum RetryClass {
    Never,
    AfterChange,
    Backoff,
    Reauth,
}

// SPEC: ERR-03
/// A registry-approved scalar or enum list safe to include in error details.
#[derive(Clone, Debug, PartialEq, Serialize)]
#[serde(untagged)]
pub enum DetailValue {
    Int(i64),
    Str(&'static str),
    Id(uuid::Uuid),
    StrList(Vec<String>),
    Bool(bool),
}

/// A typed map of registry-approved detail values.
#[derive(Clone, Debug, Default, PartialEq, Serialize)]
pub struct Details(BTreeMap<&'static str, DetailValue>);

// ERR-03: validation errors are top-level; every other key is a registry detail key.
const REGISTRY_DETAIL_KEYS: &[&str] = &[
    "accepted",
    "allowed",
    "bounds",
    "count",
    "counts",
    "current",
    "current_version",
    "dependents",
    "entity_id",
    "export_id",
    "from",
    "header",
    "index",
    "limit",
    "max",
    "max_bytes",
    "max_chars",
    "max_days",
    "methods",
    "min",
    "missing",
    "module",
    "name",
    "op_type",
    "path",
    "reasons",
    "retry_after_seconds",
    "session_id",
    "status",
    "supported",
    "task_id",
    "to",
    "violations",
    "window",
];

impl Details {
    #[must_use]
    pub fn new() -> Self {
        Self::default()
    }

    #[must_use]
    pub fn with_detail(mut self, key: &'static str, value: DetailValue) -> Self {
        if REGISTRY_DETAIL_KEYS.contains(&key) {
            self.0.insert(key, value);
        }
        self
    }

    #[must_use]
    pub fn as_map(&self) -> &BTreeMap<&'static str, DetailValue> {
        &self.0
    }
}

// SPEC: API-21, VAL-01
/// A field validation failure with its request path and registry code.
#[derive(Clone, Debug, PartialEq, Serialize, thiserror::Error)]
#[error("field validation error")]
pub struct FieldError {
    pub path: String,
    pub code: FieldCode,
    pub details: Details,
}

// SPEC: API-20, API-21, CQ-09, VAL-01
/// A client-facing application error with stable registry metadata.
#[derive(Clone, Debug, PartialEq, Serialize, thiserror::Error)]
#[error("{code}")]
pub struct AppError {
    pub code: ErrorCode,
    pub details: Details,
    pub errors: Vec<FieldError>,
}

impl AppError {
    #[must_use]
    pub fn new(code: ErrorCode) -> Self {
        Self {
            code,
            details: Details::new(),
            errors: Vec::new(),
        }
    }

    #[must_use]
    pub fn with_detail(mut self, key: &'static str, value: DetailValue) -> Self {
        self.details = self.details.with_detail(key, value);
        self
    }

    #[must_use]
    pub fn validation(errors: Vec<FieldError>) -> Self {
        Self {
            code: ErrorCode::ValidationFailed,
            details: Details::new(),
            errors,
        }
    }
}

#[cfg(test)]
mod tests {
    use super::{AppError, DetailValue, Details, FieldError};
    use crate::errors::{ErrorCode, FieldCode, RetryClass};

    #[test]
    fn api_21_validation_keeps_all_errors() {
        let errors = vec![
            FieldError {
                path: "title".to_owned(),
                code: FieldCode::Required,
                details: Details::default(),
            },
            FieldError {
                path: "description".to_owned(),
                code: FieldCode::TooLong,
                details: Details::default(),
            },
        ];
        let error = AppError::validation(errors.clone());

        assert_eq!(error.code, ErrorCode::ValidationFailed);
        assert_eq!(error.code.http_status(), 422);
        assert_eq!(error.errors, errors);
    }

    #[test]
    fn version_conflict_metadata() {
        let code = ErrorCode::VersionConflict;

        assert_eq!(code.as_str(), "version_conflict");
        assert_eq!(code.http_status(), 409);
        assert_eq!(code.retry(), RetryClass::AfterChange);
    }

    #[test]
    fn err_03_details_only_emit_registry_keys() {
        let details = Details::new()
            .with_detail("max", DetailValue::Int(20))
            .with_detail("unregistered_key", DetailValue::Int(1));

        assert!(details.as_map().contains_key("max"));
        assert!(!details.as_map().contains_key("unregistered_key"));
    }
}
