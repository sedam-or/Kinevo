use std::sync::Arc;

use kinevo_kernel::{
    errors::{AppError, DetailValue, Details, ErrorCode},
    id::{AccountId, IdGen, RequestId},
    time::Instant,
};
use sqlx::{PgConnection, types::Uuid};

use crate::{SystemTx, Tx};

const MAX_DETAILS: usize = 16;

/// A registered audit action with its stable wire and storage name.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum AuditAction {
    SetupCompleted,
    SetupTokenFailed,
    ModuleSwitched,
    PolicyChanged,
    SecretSet,
    SecretDeleted,
    AuthLogin,
    AuthLoginFailed,
    AuthRefreshReuse,
    AuthPasswordChanged,
    AccountStatusChanged,
    AccountDeleted,
    AccountExported,
    OutboxRetried,
    OutboxDead,
    Startup,
}

impl AuditAction {
    #[must_use]
    pub fn as_str(self) -> &'static str {
        match self {
            Self::SetupCompleted => "setup.completed",
            Self::SetupTokenFailed => "setup.token_failed",
            Self::ModuleSwitched => "module.switched",
            Self::PolicyChanged => "policy.changed",
            Self::SecretSet => "secret.set",
            Self::SecretDeleted => "secret.deleted",
            Self::AuthLogin => "auth.login",
            Self::AuthLoginFailed => "auth.login_failed",
            Self::AuthRefreshReuse => "auth.refresh_reuse",
            Self::AuthPasswordChanged => "auth.password_changed",
            Self::AccountStatusChanged => "account.status_changed",
            Self::AccountDeleted => "account.deleted",
            Self::AccountExported => "account.exported",
            Self::OutboxRetried => "outbox.retried",
            Self::OutboxDead => "outbox.dead",
            Self::Startup => "startup",
        }
    }
}

/// A statically named audit target; request data cannot supply a target type.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct AuditTarget(&'static str);

impl AuditTarget {
    pub const ACCOUNT: Self = Self("account");

    #[must_use]
    pub const fn from_static(value: &'static str) -> Self {
        Self(value)
    }

    #[must_use]
    pub const fn as_str(self) -> &'static str {
        self.0
    }
}

/// One structured audit event. `occurred_at` comes from the caller's injected clock.
pub struct AuditEntry {
    pub account_id: Option<AccountId>,
    pub actor_id: Option<Uuid>,
    pub action: AuditAction,
    pub target_type: Option<AuditTarget>,
    pub target_id: Option<String>,
    pub details: Details,
    pub request_id: Option<RequestId>,
    pub occurred_at: Instant,
}

mod private {
    pub trait Sealed {}

    impl Sealed for crate::Tx {}
    impl Sealed for crate::SystemTx {}
}

/// A database transaction accepted by the audit writer.
pub trait TxConn: private::Sealed {
    fn conn(&mut self) -> &mut PgConnection;
}

impl TxConn for Tx {
    fn conn(&mut self) -> &mut PgConnection {
        Tx::conn(self)
    }
}

impl TxConn for SystemTx {
    fn conn(&mut self) -> &mut PgConnection {
        SystemTx::conn(self)
    }
}

/// Appends audit entries using injected identifiers and the caller's transaction.
pub struct AuditWriter {
    ids: Arc<dyn IdGen>,
}

impl AuditWriter {
    #[must_use]
    pub fn new(ids: Arc<dyn IdGen>) -> Self {
        Self { ids }
    }

    pub async fn record(&self, tx: &mut impl TxConn, entry: AuditEntry) -> Result<(), AppError> {
        let details = serialise_details(&entry.details)?;
        sqlx::query(
            "INSERT INTO public.audit_log \
             (id, account_id, actor_id, action, target_type, target_id, details, request_id, occurred_at) \
             VALUES ($1, $2, $3, $4, $5, $6, $7::jsonb, $8, $9::timestamptz)",
        )
        .bind(self.ids.new_uuid_v7())
        .bind(entry.account_id.map(|account| *account.as_uuid()))
        .bind(entry.actor_id)
        .bind(entry.action.as_str())
        .bind(entry.target_type.map(AuditTarget::as_str))
        .bind(entry.target_id)
        .bind(details)
        .bind(entry.request_id.map(|request| request.to_string()))
        .bind(entry.occurred_at.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        Ok(())
    }
}

fn serialise_details(details: &Details) -> Result<String, AppError> {
    let map = details.as_map();
    if map.len() > MAX_DETAILS {
        debug_assert!(
            map.len() <= MAX_DETAILS,
            "audit details exceed the key limit"
        );
        return Err(AppError::new(ErrorCode::InternalError));
    }

    let mut values = serde_json::Map::new();
    for (key, value) in map {
        let value = match value {
            DetailValue::Int(value) => serde_json::Value::from(*value),
            DetailValue::Str(value) => serde_json::Value::from(*value),
            DetailValue::Id(value) => serde_json::Value::from(value.hyphenated().to_string()),
            DetailValue::Bool(value) => serde_json::Value::from(*value),
            DetailValue::OwnedStr(_) | DetailValue::StrList(_) | DetailValue::Resource(_) => {
                return Err(AppError::new(ErrorCode::InternalError));
            }
        };
        values.insert((*key).to_owned(), value);
    }
    serde_json::to_string(&serde_json::Value::Object(values))
        .map_err(|_| AppError::new(ErrorCode::InternalError))
}

#[cfg(test)]
mod tests {
    use super::serialise_details;
    use kinevo_kernel::errors::{DetailValue, Details, ErrorCode};
    use sqlx::types::Uuid;

    #[test]
    fn details_serialise_canonically() {
        let details = Details::new()
            .with_detail("status", DetailValue::Str("active"))
            .with_detail("count", DetailValue::Int(7))
            .with_detail("entity_id", DetailValue::Id(Uuid::from_u128(1)));

        let serialised = serialise_details(&details);
        assert!(serialised.is_ok(), "registered details should serialise");
        assert_eq!(
            serialised.unwrap_or_default(),
            r#"{"count":7,"entity_id":"00000000-0000-0000-0000-000000000001","status":"active"}"#
        );
    }

    #[test]
    fn sec_04_rejects_dynamic_audit_detail_values() {
        for value in [
            DetailValue::OwnedStr("free text".to_owned()),
            DetailValue::StrList(vec!["free text".to_owned()]),
        ] {
            let details = Details::new().with_detail("status", value);
            assert!(
                matches!(
                    serialise_details(&details).err(),
                    Some(error) if error.code == ErrorCode::InternalError
                ),
                "dynamic detail values must not enter audit rows"
            );
        }
    }

    #[test]
    fn audit_details_enforce_sixteen_key_limit() {
        use std::panic::catch_unwind;

        const KEYS: &[&str] = &[
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
        ];
        let details = KEYS.iter().fold(Details::new(), |details, key| {
            details.with_detail(key, DetailValue::Int(1))
        });

        assert!(catch_unwind(|| serialise_details(&details)).is_err());
    }
}
