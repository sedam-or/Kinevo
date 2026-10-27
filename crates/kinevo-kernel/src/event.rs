//! Versioned domain events with payloads restricted to ids, enums, and integers.

// SPEC: CA-11, D-43

use crate::id::{AccountId, EventId, Id};
use crate::time::Instant;
use serde::Serialize;
use std::collections::BTreeMap;

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
}

/// Closed-at-call-site enum value wrapper; free-form strings are not event values.
#[derive(Clone, Copy, Debug, Eq, PartialEq, Serialize)]
#[serde(transparent)]
pub struct EventEnumValue(&'static str);

impl EventEnumValue {
    /// Wraps a static enum literal.
    #[must_use]
    pub const fn new(value: &'static str) -> Self {
        Self(value)
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
pub struct EventPayload(BTreeMap<&'static str, EventValue>);

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
            .insert(key, EventValue::Id(EventIdValue::from_id(id)));
        self
    }

    /// Adds an enum scalar under a stable field name.
    #[must_use]
    pub fn with_enum(mut self, key: &'static str, value: EventEnumValue) -> Self {
        self.0.insert(key, EventValue::Enum(value));
        self
    }

    /// Adds an integer scalar under a stable field name.
    #[must_use]
    pub fn with_int(mut self, key: &'static str, value: i64) -> Self {
        self.0.insert(key, EventValue::Int(value));
        self
    }

    /// Borrows the typed payload values.
    #[must_use]
    pub fn values(&self) -> &BTreeMap<&'static str, EventValue> {
        &self.0
    }
}

#[derive(Clone, Debug, PartialEq, Serialize)]
// SPEC: CA-11
/// Versioned event envelope shared with the database and module crates.
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
