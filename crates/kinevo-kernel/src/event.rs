//! Versioned domain events with payloads restricted to ids, enums, and integers.

// SPEC: CA-11, D-43

use crate::id::{AccountId, EventId, Id};
use crate::time::Instant;
use serde::Serialize;
use std::collections::BTreeMap;
use thiserror::Error;
use uuid::Uuid;

#[derive(Clone, Copy, Debug, Eq, Hash, PartialEq, Serialize)]
// SPEC: CA-11
/// Closed set of M1 domain event kinds; names are stable past-tense facts.
pub enum EventKind {
    AccountRegistered,
    AccountLoggedIn,
    AccountDisabled,
    AccountDeletionRequested,
    PasswordChanged,
    InstanceSetupCompleted,
    ModuleSwitched,
    PolicyChanged,
    SecretChanged,
}

impl EventKind {
    /// Returns the stable discriminator stored in `outbox_events.kind`.
    #[must_use]
    pub const fn as_str(self) -> &'static str {
        match self {
            Self::AccountRegistered => "AccountRegistered",
            Self::AccountLoggedIn => "AccountLoggedIn",
            Self::AccountDisabled => "AccountDisabled",
            Self::AccountDeletionRequested => "AccountDeletionRequested",
            Self::PasswordChanged => "PasswordChanged",
            Self::InstanceSetupCompleted => "InstanceSetupCompleted",
            Self::ModuleSwitched => "ModuleSwitched",
            Self::PolicyChanged => "PolicyChanged",
            Self::SecretChanged => "SecretChanged",
        }
    }

    /// Parses a stable event discriminator read from the outbox.
    #[must_use]
    pub fn parse(value: &str) -> Option<Self> {
        Some(match value {
            "AccountRegistered" => Self::AccountRegistered,
            "AccountLoggedIn" => Self::AccountLoggedIn,
            "AccountDisabled" => Self::AccountDisabled,
            "AccountDeletionRequested" => Self::AccountDeletionRequested,
            "PasswordChanged" => Self::PasswordChanged,
            "InstanceSetupCompleted" => Self::InstanceSetupCompleted,
            "ModuleSwitched" => Self::ModuleSwitched,
            "PolicyChanged" => Self::PolicyChanged,
            "SecretChanged" => Self::SecretChanged,
            _ => return None,
        })
    }
}

/// Error raised when stored event payload JSON is not a typed value map.
#[derive(Clone, Copy, Debug, Eq, Error, PartialEq)]
#[error("invalid event payload")]
pub struct EventPayloadDecodeError;

fn valid_event_token(value: &str) -> bool {
    (1..=64).contains(&value.len())
        && value
            .as_bytes()
            .first()
            .is_some_and(u8::is_ascii_alphabetic)
        && value
            .bytes()
            .all(|byte| byte.is_ascii_alphanumeric() || matches!(byte, b'_' | b'-'))
}

/// Canonical string form of a typed identifier for use in an event payload.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
#[serde(transparent)]
pub struct EventIdValue(String);

impl EventIdValue {
    /// Converts a typed kernel identifier to its canonical UUID string.
    #[must_use]
    pub fn from_id<T>(id: &Id<T>) -> Self {
        Self(id.to_string())
    }

    fn parse(value: &str) -> Option<Self> {
        let id = Uuid::parse_str(value).ok()?;
        (id.hyphenated().to_string() == value).then(|| Self(value.to_owned()))
    }
}

/// A code-like enum literal; free-form text is not accepted by the stored form.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
#[serde(transparent)]
pub struct EventEnumValue(String);

impl EventEnumValue {
    /// Wraps an enum literal supplied as static application data.
    #[must_use]
    pub fn new(value: &'static str) -> Self {
        Self(value.to_owned())
    }
}

#[derive(Clone, Debug, PartialEq, Serialize)]
#[serde(untagged)]
// SPEC: CA-11, ERR-03
/// Payload scalar types allowed by CA-11.
pub enum EventValue {
    Id(EventIdValue),
    Enum(EventEnumValue),
    Int(i64),
}

#[derive(Clone, Debug, Default, PartialEq, Serialize)]
// SPEC: CA-11
/// Deterministically ordered event data limited to ids, enums, and integers.
pub struct EventPayload(BTreeMap<String, EventValue>);

impl EventPayload {
    /// Starts an empty event payload.
    #[must_use]
    pub fn new() -> Self {
        Self::default()
    }

    /// Adds a typed kernel identifier under a stable field name.
    #[must_use]
    pub fn with_id<T>(mut self, key: &'static str, id: &Id<T>) -> Self {
        self.0
            .insert(key.to_owned(), EventValue::Id(EventIdValue::from_id(id)));
        self
    }

    /// Adds an enum scalar under a stable field name.
    #[must_use]
    pub fn with_enum(mut self, key: &'static str, value: EventEnumValue) -> Self {
        self.0.insert(key.to_owned(), EventValue::Enum(value));
        self
    }

    /// Adds an integer scalar under a stable field name.
    #[must_use]
    pub fn with_int(mut self, key: &'static str, value: i64) -> Self {
        self.0.insert(key.to_owned(), EventValue::Int(value));
        self
    }

    /// Borrows the typed payload values.
    #[must_use]
    pub fn values(&self) -> &BTreeMap<String, EventValue> {
        &self.0
    }

    /// Ensures every field name and enum literal is a code-like value, not content.
    pub fn validate(&self) -> Result<(), EventPayloadDecodeError> {
        for (key, value) in &self.0 {
            if !valid_event_token(key)
                || matches!(value, EventValue::Enum(literal) if !valid_event_token(&literal.0))
            {
                return Err(EventPayloadDecodeError);
            }
        }
        Ok(())
    }

    /// Encodes field types explicitly for lossless database storage.
    #[must_use]
    pub fn to_storage_json(&self) -> serde_json::Value {
        let mut values = serde_json::Map::new();
        for (key, value) in &self.0 {
            let tagged = match value {
                EventValue::Id(value) => serde_json::json!({ "id": value.0 }),
                EventValue::Enum(value) => serde_json::json!({ "enum": value.0 }),
                EventValue::Int(value) => serde_json::json!({ "int": value }),
            };
            values.insert(key.clone(), tagged);
        }
        serde_json::Value::Object(values)
    }

    /// Decodes and validates the typed database representation.
    pub fn from_storage_json(value: &serde_json::Value) -> Result<Self, EventPayloadDecodeError> {
        let Some(fields) = value.as_object() else {
            return Err(EventPayloadDecodeError);
        };
        let mut values = BTreeMap::new();
        for (key, tagged) in fields {
            if !valid_event_token(key) {
                return Err(EventPayloadDecodeError);
            }
            let Some(tag) = tagged.as_object() else {
                return Err(EventPayloadDecodeError);
            };
            if tag.len() != 1 {
                return Err(EventPayloadDecodeError);
            }
            let Some((kind, value)) = tag.iter().next() else {
                return Err(EventPayloadDecodeError);
            };
            let value = match kind.as_str() {
                "id" => EventValue::Id(
                    EventIdValue::parse(value.as_str().ok_or(EventPayloadDecodeError)?)
                        .ok_or(EventPayloadDecodeError)?,
                ),
                "enum" => {
                    let literal = value.as_str().ok_or(EventPayloadDecodeError)?;
                    if !valid_event_token(literal) {
                        return Err(EventPayloadDecodeError);
                    }
                    EventValue::Enum(EventEnumValue(literal.to_owned()))
                }
                "int" => EventValue::Int(value.as_i64().ok_or(EventPayloadDecodeError)?),
                _ => return Err(EventPayloadDecodeError),
            };
            values.insert(key.clone(), value);
        }
        Ok(Self(values))
    }
}

#[cfg(test)]
mod tests {
    use super::{EventEnumValue, EventPayload};

    #[test]
    fn ca_11_event_payload_storage_round_trip_preserves_scalar_types() {
        let payload = EventPayload::new()
            .with_enum("status", EventEnumValue::new("active"))
            .with_int("version", 3);
        let stored = payload.to_storage_json();
        let restored = EventPayload::from_storage_json(&stored);
        assert_eq!(restored.ok(), Some(payload));
    }

    #[test]
    fn ca_11_event_payload_rejects_content_like_enum_values() {
        let payload = EventPayload::new().with_enum("status", EventEnumValue::new("free text"));
        assert!(payload.validate().is_err());
    }
}

#[derive(Clone, Debug, PartialEq, Serialize)]
// SPEC: CA-11
/// Versioned event envelope shared with the database, application, and module crates.
pub struct EventEnvelope {
    /// Event identifier.
    pub id: EventId,
    /// Stable event kind.
    pub kind: EventKind,
    /// Payload schema version.
    pub v: u16,
    /// Owning account, or none for an instance-wide event.
    pub account_id: Option<AccountId>,
    /// Injected UTC instant when the fact occurred.
    pub occurred_at: Instant,
    /// Typed, content-free event fields.
    pub payload: EventPayload,
}
