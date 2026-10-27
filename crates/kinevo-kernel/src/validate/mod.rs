pub mod net;
pub mod text;
pub mod tz;

use crate::errors::{AppError, Details, FieldCode, FieldError};

// SPEC: VAL-01
/// Collects field failures in validation order without retaining invalid input.
#[derive(Debug, Default)]
pub struct Collector {
    errors: Vec<FieldError>,
}

impl Collector {
    /// Starts an empty validation pass.
    #[must_use]
    pub fn new() -> Self {
        Self::default()
    }

    /// Adds a field error and returns successful values for continued checks.
    pub fn check<T>(&mut self, path: &str, result: Result<T, FieldCode>) -> Option<T> {
        match result {
            Ok(value) => Some(value),
            Err(code) => {
                self.errors.push(FieldError {
                    path: path.to_owned(),
                    code,
                    details: Details::new(),
                });
                None
            }
        }
    }

    /// Returns all collected errors as one validation failure.
    pub fn finish(self) -> Result<(), AppError> {
        if self.errors.is_empty() {
            Ok(())
        } else {
            Err(AppError::validation(self.errors))
        }
    }
}

// SPEC: VAL-05
/// Accepts an integer only when it is within the field bounds and safe in JavaScript.
pub fn bounded_int(value: i64, min: i64, max: i64) -> Result<i64, FieldCode> {
    const MAX_SAFE_INTEGER: i64 = 9_007_199_254_740_991;

    if value < min || value > max || !(-MAX_SAFE_INTEGER..=MAX_SAFE_INTEGER).contains(&value) {
        Err(FieldCode::OutOfRange)
    } else {
        Ok(value)
    }
}

// SPEC: VAL-06
/// Returns a value only when it appears in the closed set, including the set on failure.
pub fn enum_check<'a>(
    value: &'a str,
    allowed: &[&str],
) -> Result<&'a str, (FieldCode, Vec<String>)> {
    if allowed.contains(&value) {
        Ok(value)
    } else {
        Err((
            FieldCode::EnumInvalid,
            allowed.iter().map(|item| (*item).to_owned()).collect(),
        ))
    }
}

#[cfg(test)]
mod tests {
    use super::{Collector, bounded_int};
    use crate::errors::{ErrorCode, FieldCode};

    #[test]
    fn val_01_collects_all_errors() {
        let mut collector = Collector::new();
        let _: Option<()> = collector.check("email", Err(FieldCode::EmailInvalid));
        let _: Option<()> = collector.check("name", Err(FieldCode::Required));
        let _: Option<()> = collector.check("timezone", Err(FieldCode::TimezoneInvalid));

        let error = collector
            .finish()
            .expect_err("three invalid fields should fail");
        assert_eq!(error.code, ErrorCode::ValidationFailed);
        assert_eq!(error.errors.len(), 3);
        assert_eq!(error.errors[0].path, "email");
        assert_eq!(error.errors[1].path, "name");
        assert_eq!(error.errors[2].path, "timezone");
    }

    #[test]
    fn val_05_safe_integer_limit() {
        let error = bounded_int(1_i64 << 53, i64::MIN, i64::MAX).err();
        assert_eq!(error, Some(FieldCode::OutOfRange));
        assert_eq!(
            bounded_int((1_i64 << 53) - 1, 0, i64::MAX),
            Ok((1_i64 << 53) - 1)
        );
    }
}
