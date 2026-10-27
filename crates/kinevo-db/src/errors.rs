use kinevo_kernel::errors::{AppError, ErrorCode};
use sqlx::error::ErrorKind;
use std::collections::BTreeMap;

/// A module's static database constraint-to-error mapping.
#[derive(Clone, Copy, Debug)]
pub struct ConstraintMap(pub &'static [(&'static str, ErrorCode)]);

/// The application's merged constraint registry.
#[derive(Debug, Default)]
pub struct ConstraintRegistry {
    constraints: BTreeMap<&'static str, ErrorCode>,
}

impl ConstraintRegistry {
    /// Builds the registry from module-owned mappings.
    #[must_use]
    pub fn new(maps: impl IntoIterator<Item = ConstraintMap>) -> Self {
        let mut constraints = BTreeMap::new();
        for map in maps {
            for (constraint, code) in map.0 {
                constraints.insert(*constraint, *code);
            }
        }
        Self { constraints }
    }

    fn get(&self, constraint: &str) -> Option<ErrorCode> {
        self.constraints.get(constraint).copied()
    }
}

/// Maps `SQLx` failures to stable application error codes without exposing SQL details.
pub fn map_error(registry: &ConstraintRegistry, error: sqlx::Error) -> AppError {
    match error {
        sqlx::Error::RowNotFound => AppError::new(ErrorCode::NotFound),
        sqlx::Error::PoolTimedOut | sqlx::Error::PoolClosed | sqlx::Error::Io(_) => {
            AppError::new(ErrorCode::ServiceUnavailable)
        }
        sqlx::Error::Database(database) => {
            let code = database.code();
            let state = code.as_deref();
            if matches!(state, Some("40001" | "40P01" | "57P03")) {
                return AppError::new(ErrorCode::ServiceUnavailable);
            }

            let kind = database.kind();
            let is_constraint = matches!(state, Some("23505" | "23503" | "23514"))
                || matches!(
                    kind,
                    ErrorKind::UniqueViolation
                        | ErrorKind::ForeignKeyViolation
                        | ErrorKind::CheckViolation
                );
            if !is_constraint {
                return AppError::new(ErrorCode::InternalError);
            }

            let constraint = database.constraint();
            if let Some(mapped) = constraint.and_then(|name| registry.get(name)) {
                return AppError::new(mapped);
            }

            tracing::error!(
                event = "unmapped_constraint",
                constraint_name = constraint.unwrap_or("unknown")
            );
            AppError::new(ErrorCode::InternalError)
        }
        _ => AppError::new(ErrorCode::InternalError),
    }
}

#[cfg(test)]
mod tests {
    use super::{ConstraintMap, ConstraintRegistry, map_error};
    use kinevo_kernel::errors::ErrorCode;
    use sqlx::error::{DatabaseError, ErrorKind};
    use std::{
        borrow::Cow,
        collections::BTreeMap,
        error::Error,
        fmt,
        sync::{Arc, Mutex},
    };
    use tracing::{
        Event, Subscriber,
        field::{Field, Visit},
        span,
    };

    #[derive(Debug)]
    struct ConstraintError {
        code: &'static str,
        constraint: &'static str,
    }

    impl fmt::Display for ConstraintError {
        fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
            formatter.write_str("synthetic database error")
        }
    }

    impl Error for ConstraintError {}

    impl DatabaseError for ConstraintError {
        fn message(&self) -> &'static str {
            "synthetic database error"
        }

        fn code(&self) -> Option<Cow<'_, str>> {
            Some(Cow::Borrowed(self.code))
        }

        fn as_error(&self) -> &(dyn Error + Send + Sync + 'static) {
            self
        }

        fn as_error_mut(&mut self) -> &mut (dyn Error + Send + Sync + 'static) {
            self
        }

        fn into_error(self: Box<Self>) -> Box<dyn Error + Send + Sync + 'static> {
            self
        }

        fn constraint(&self) -> Option<&str> {
            Some(self.constraint)
        }

        fn kind(&self) -> ErrorKind {
            ErrorKind::UniqueViolation
        }
    }

    struct CapturingSubscriber(Arc<Mutex<Vec<BTreeMap<String, String>>>>);

    impl Subscriber for CapturingSubscriber {
        fn enabled(&self, _metadata: &tracing::Metadata<'_>) -> bool {
            true
        }

        fn new_span(&self, _span: &span::Attributes<'_>) -> span::Id {
            span::Id::from_u64(1)
        }

        fn record(&self, _span: &span::Id, _values: &span::Record<'_>) {}

        fn record_follows_from(&self, _span: &span::Id, _follows: &span::Id) {}

        fn event(&self, event: &Event<'_>) {
            let mut values = BTreeMap::new();
            event.record(&mut CapturedFields(&mut values));
            let mut events = self
                .0
                .lock()
                .unwrap_or_else(std::sync::PoisonError::into_inner);
            events.push(values);
        }

        fn enter(&self, _span: &span::Id) {}

        fn exit(&self, _span: &span::Id) {}
    }

    struct CapturedFields<'a>(&'a mut BTreeMap<String, String>);

    impl Visit for CapturedFields<'_> {
        fn record_debug(&mut self, field: &Field, value: &dyn fmt::Debug) {
            self.0.insert(field.name().to_owned(), format!("{value:?}"));
        }

        fn record_str(&mut self, field: &Field, value: &str) {
            self.0.insert(field.name().to_owned(), value.to_owned());
        }
    }

    #[test]
    fn val_11_constraint_mapping() {
        let registry =
            ConstraintRegistry::new([ConstraintMap(&[("users_email_key", ErrorCode::EmailTaken)])]);
        let mapped = map_error(
            &registry,
            sqlx::Error::Database(Box::new(ConstraintError {
                code: "23505",
                constraint: "users_email_key",
            })),
        );
        assert_eq!(mapped.code, ErrorCode::EmailTaken);

        let events = Arc::new(Mutex::new(Vec::new()));
        let subscriber = CapturingSubscriber(Arc::clone(&events));
        let unmapped = tracing::subscriber::with_default(subscriber, || {
            map_error(
                &registry,
                sqlx::Error::Database(Box::new(ConstraintError {
                    code: "23505",
                    constraint: "unknown_unique_key",
                })),
            )
        });
        assert_eq!(unmapped.code, ErrorCode::InternalError);
        assert!(unmapped.details.as_map().is_empty());
        let captured = events
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner);
        assert_eq!(captured.len(), 1);
        let fields = captured.first().cloned().unwrap_or_default();
        assert_eq!(fields.len(), 2);
        assert_eq!(
            fields.get("event").map(String::as_str),
            Some("unmapped_constraint")
        );
        assert_eq!(
            fields.get("constraint_name").map(String::as_str),
            Some("unknown_unique_key")
        );
    }
}
