//! Typed UUIDs and injected identifier and token sources.

use serde::{Deserialize, Deserializer, Serialize, Serializer, de};
use std::{fmt, marker::PhantomData};
use uuid::Uuid;

pub struct Id<T> {
    value: Uuid,
    marker: PhantomData<fn() -> T>,
}

impl<T> Id<T> {
    pub fn parse(value: &str) -> Result<Self, IdError> {
        let parsed = Uuid::parse_str(value).map_err(|_| IdError)?;
        if parsed.hyphenated().to_string() != value {
            return Err(IdError);
        }
        Ok(Self::from_uuid(parsed))
    }

    #[must_use]
    pub fn as_uuid(&self) -> &Uuid {
        &self.value
    }

    #[must_use]
    pub fn from_uuid(value: Uuid) -> Self {
        Self {
            value,
            marker: PhantomData,
        }
    }
}

impl<T> Clone for Id<T> {
    fn clone(&self) -> Self {
        *self
    }
}

impl<T> Copy for Id<T> {}

impl<T> fmt::Debug for Id<T> {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.debug_tuple("Id").field(&self.value).finish()
    }
}

impl<T> fmt::Display for Id<T> {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        self.value.hyphenated().fmt(formatter)
    }
}

impl<T> PartialEq for Id<T> {
    fn eq(&self, other: &Self) -> bool {
        self.value == other.value
    }
}

impl<T> Eq for Id<T> {}

impl<T> PartialOrd for Id<T> {
    fn partial_cmp(&self, other: &Self) -> Option<std::cmp::Ordering> {
        Some(self.cmp(other))
    }
}

impl<T> Ord for Id<T> {
    fn cmp(&self, other: &Self) -> std::cmp::Ordering {
        self.value.cmp(&other.value)
    }
}

impl<T> std::hash::Hash for Id<T> {
    fn hash<H: std::hash::Hasher>(&self, state: &mut H) {
        self.value.hash(state);
    }
}

impl<T> Serialize for Id<T> {
    fn serialize<S>(&self, serializer: S) -> Result<S::Ok, S::Error>
    where
        S: Serializer,
    {
        serializer.serialize_str(&self.to_string())
    }
}

impl<'de, T> Deserialize<'de> for Id<T> {
    fn deserialize<D>(deserializer: D) -> Result<Self, D::Error>
    where
        D: Deserializer<'de>,
    {
        let value = String::deserialize(deserializer)?;
        Self::parse(&value).map_err(de::Error::custom)
    }
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct IdError;

impl fmt::Display for IdError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("invalid canonical UUID")
    }
}

impl std::error::Error for IdError {}

/// Define a marker type and its typed identifier alias.
#[macro_export]
macro_rules! define_id {
    ($marker:ident, $alias:ident) => {
        #[derive(Clone, Copy, Debug, Eq, Hash, Ord, PartialEq, PartialOrd)]
        pub struct $marker;
        pub type $alias = $crate::id::Id<$marker>;
    };
}

crate::define_id!(Account, AccountId);
crate::define_id!(Request, RequestId);
crate::define_id!(Event, EventId);
crate::define_id!(Job, JobId);
crate::define_id!(Family, FamilyId);

pub trait IdGen: Send + Sync {
    #[must_use]
    fn new_uuid_v7(&self) -> Uuid;
}

#[must_use]
pub fn new_id<T>(generator: &dyn IdGen) -> Id<T> {
    Id::from_uuid(generator.new_uuid_v7())
}

pub trait TokenGen: Send + Sync {
    fn fill(&self, buffer: &mut [u8]);

    #[must_use]
    fn token_256(&self) -> [u8; 32] {
        let mut token = [0; 32];
        self.fill(&mut token);
        token
    }

    #[must_use]
    fn token_128(&self) -> [u8; 16] {
        let mut token = [0; 16];
        self.fill(&mut token);
        token
    }
}

#[cfg(any(test, feature = "testing"))]
pub struct SeqIdGen {
    timestamp_millis: i64,
    counter: std::sync::atomic::AtomicU64,
}

#[cfg(any(test, feature = "testing"))]
impl SeqIdGen {
    #[must_use]
    pub fn new(timestamp_millis: i64) -> Self {
        Self {
            timestamp_millis,
            counter: std::sync::atomic::AtomicU64::new(0),
        }
    }
}

#[cfg(any(test, feature = "testing"))]
impl IdGen for SeqIdGen {
    fn new_uuid_v7(&self) -> Uuid {
        let timestamp = self.timestamp_millis.to_be_bytes();
        let [_, _, time0, time1, time2, time3, time4, time5] = timestamp;
        let sequence = self
            .counter
            .fetch_add(1, std::sync::atomic::Ordering::SeqCst)
            & 0x3fff_ffff_ffff_ffff;
        let [
            sequence0,
            sequence1,
            sequence2,
            sequence3,
            sequence4,
            sequence5,
            sequence6,
            sequence7,
        ] = sequence.to_be_bytes();
        Uuid::from_bytes([
            time0,
            time1,
            time2,
            time3,
            time4,
            time5,
            0x70,
            0,
            0x80 | sequence0,
            sequence1,
            sequence2,
            sequence3,
            sequence4,
            sequence5,
            sequence6,
            sequence7,
        ])
    }
}

#[cfg(any(test, feature = "testing"))]
pub struct SeqTokenGen {
    counter: std::sync::atomic::AtomicU64,
}

#[cfg(any(test, feature = "testing"))]
impl SeqTokenGen {
    #[must_use]
    pub fn new(seed: u64) -> Self {
        Self {
            counter: std::sync::atomic::AtomicU64::new(seed),
        }
    }
}

#[cfg(any(test, feature = "testing"))]
impl TokenGen for SeqTokenGen {
    fn fill(&self, buffer: &mut [u8]) {
        for chunk in buffer.chunks_mut(8) {
            let value = self
                .counter
                .fetch_add(1, std::sync::atomic::Ordering::SeqCst)
                .to_be_bytes();
            for (destination, byte) in chunk.iter_mut().zip(value) {
                *destination = byte;
            }
        }
    }
}

#[cfg(test)]
mod tests {
    use super::{AccountId, IdGen, SeqIdGen};

    #[test]
    fn seq_id_gen_is_v7_and_ordered() {
        let generator = SeqIdGen::new(1_700_000_000_000);
        let first = generator.new_uuid_v7();
        let second = generator.new_uuid_v7();
        let third = generator.new_uuid_v7();

        assert_eq!(first.get_version_num(), 7);
        assert_eq!(second.get_version_num(), 7);
        assert_eq!(third.get_version_num(), 7);
        assert!(first < second && second < third);
    }

    #[test]
    fn id_serde_round_trip() {
        let id = AccountId::parse("550e8400-e29b-41d4-a716-446655440000")
            .expect("test id is a valid UUID");
        let json = serde_json::to_string(&id).expect("id serializes");

        assert_eq!(json, "\"550e8400-e29b-41d4-a716-446655440000\"");
        let decoded: AccountId = serde_json::from_str(&json).expect("id deserializes");
        assert_eq!(decoded.to_string(), id.to_string());
    }
}
