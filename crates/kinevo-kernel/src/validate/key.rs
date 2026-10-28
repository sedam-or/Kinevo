use crate::errors::FieldCode;

/// A normalized idempotency key accepted by the HTTP and persistence layers.
#[derive(Clone, Eq, PartialEq)]
pub struct IdemKey(String);

impl IdemKey {
    /// Trims and lowercases a key, then validates its ASCII key-like contract.
    pub fn parse(raw: &str) -> Result<Self, FieldCode> {
        let normalized = raw.trim().to_ascii_lowercase();
        if !(8..=128).contains(&normalized.len())
            || !normalized
                .bytes()
                .all(|byte| byte.is_ascii_alphanumeric() || matches!(byte, b'_' | b'-'))
        {
            return Err(FieldCode::KeyInvalid);
        }
        Ok(Self(normalized))
    }

    /// Returns the normalized key for request hashing and database binding.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

#[cfg(test)]
mod tests {
    use super::IdemKey;
    use crate::errors::FieldCode;

    #[test]
    fn val_08_idempotency_key_is_normalized_and_bounded() {
        assert_eq!(
            IdemKey::parse(" AbCdEfGh ").map(|key| key.as_str().to_owned()),
            Ok("abcdefgh".to_owned())
        );
        assert_eq!(IdemKey::parse("short").err(), Some(FieldCode::KeyInvalid));
        assert_eq!(
            IdemKey::parse(&"a".repeat(129)).err(),
            Some(FieldCode::KeyInvalid)
        );
        assert_eq!(
            IdemKey::parse("a b c d e f g h").err(),
            Some(FieldCode::KeyInvalid)
        );
    }
}
