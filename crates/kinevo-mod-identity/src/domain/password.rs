//! Password policy value types.
//!
//! `PlainPassword` deliberately does not implement `Clone`:
//!
//! ```compile_fail
//! fn requires_clone<T: Clone>() {}
//! requires_clone::<kinevo_mod_identity::domain::password::PlainPassword>();
//! ```
//!
//! It also has no `Display` implementation:
//!
//! ```compile_fail
//! fn requires_display<T: std::fmt::Display>() {}
//! requires_display::<kinevo_mod_identity::domain::password::PlainPassword>();
//! ```
//!
//! It cannot be serialized:
//!
//! ```compile_fail
//! fn requires_serialize<T: serde::Serialize>() {}
//! requires_serialize::<kinevo_mod_identity::domain::password::PlainPassword>();
//! ```

use std::{collections::HashSet, fmt, sync::OnceLock};

use kinevo_kernel::{errors::FieldCode, secret::Secret};
use unicode_normalization::UnicodeNormalization;
use zeroize::Zeroizing;

static EMBEDDED_COMMON: OnceLock<HashSet<&'static str>> = OnceLock::new();

/// Policy for validating passwords at account creation or password change.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct PasswordPolicy {
    min_length: usize,
}

impl PasswordPolicy {
    /// Creates a policy using the configured minimum scalar-value length.
    #[must_use]
    pub const fn new(min_length: usize) -> Self {
        Self { min_length }
    }
}

/// A lowercase, embedded set of common passwords.
pub struct CommonPasswords {
    entries: &'static HashSet<&'static str>,
}

impl CommonPasswords {
    /// Loads the fixed common-password list once for this process.
    #[must_use]
    pub fn embedded() -> Self {
        Self {
            entries: EMBEDDED_COMMON.get_or_init(|| {
                include_str!("../../data/common-passwords.txt")
                    .lines()
                    .collect()
            }),
        }
    }

    fn contains(&self, password: &str) -> bool {
        self.entries.contains(password)
    }
}

/// A password which has passed policy checks and is NFKC-normalized.
pub struct PlainPassword(Secret<String>);

impl PlainPassword {
    /// Normalizes and validates a secret password.
    #[allow(
        clippy::trivially_copy_pass_by_ref,
        reason = "the task interface passes the immutable password policy by reference"
    )]
    pub fn parse(
        raw: Secret<String>,
        policy: &PasswordPolicy,
        common: &CommonPasswords,
    ) -> Result<Self, FieldCode> {
        let mut normalized = Zeroizing::new(raw.expose().nfkc().collect::<String>());
        drop(raw);
        let length = normalized.chars().count();
        if length > 128 {
            return Err(FieldCode::TooLong);
        }
        if length < policy.min_length {
            return Err(FieldCode::PasswordTooShort);
        }

        let lowercase = Zeroizing::new(normalized.to_lowercase());
        if common.contains(&lowercase) {
            return Err(FieldCode::PasswordTooCommon);
        }

        Ok(Self(Secret::new(std::mem::take(&mut *normalized))))
    }

    /// Returns the normalized password for hashing.
    #[must_use]
    pub fn expose(&self) -> &str {
        self.0.expose()
    }
}

impl fmt::Debug for PlainPassword {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("PlainPassword(<redacted>)")
    }
}

#[cfg(test)]
mod tests {
    #![allow(
        clippy::expect_used,
        clippy::unwrap_used,
        clippy::indexing_slicing,
        reason = "password acceptance tests use fixed synthetic inputs"
    )]

    use super::{CommonPasswords, PasswordPolicy, PlainPassword};
    use kinevo_kernel::{crypto::hash_token, errors::FieldCode, secret::Secret};
    use std::{collections::BTreeSet, fmt::Write as _};

    #[allow(
        clippy::trivially_copy_pass_by_ref,
        reason = "keeps acceptance helper aligned with PlainPassword::parse"
    )]
    fn parse(raw: &str, policy: &PasswordPolicy) -> Result<PlainPassword, FieldCode> {
        PlainPassword::parse(
            Secret::new(raw.to_owned()),
            policy,
            &CommonPasswords::embedded(),
        )
    }

    #[test]
    fn id_fr_02_length_bounds() {
        let policy = PasswordPolicy::new(12);
        assert_eq!(
            parse("ABCD1234WXY", &policy).err(),
            Some(FieldCode::PasswordTooShort)
        );
        assert!(parse("ABCD1234WXYZ", &policy).is_ok());
        assert!(parse(&"z".repeat(128), &policy).is_ok());
        assert_eq!(
            parse(&"z".repeat(129), &policy).err(),
            Some(FieldCode::TooLong)
        );
    }

    #[test]
    fn id_fr_02_common_list_after_nfkc() {
        let policy = PasswordPolicy::new(12);
        for password in ["password1234", "PASSWORD1234", "ｐａｓｓｗｏｒｄ１２３４"] {
            assert_eq!(
                parse(password, &policy).err(),
                Some(FieldCode::PasswordTooCommon)
            );
        }
    }

    #[test]
    fn id_fr_02_unicode_and_spaces() {
        let policy = PasswordPolicy::new(12);
        let emoji = "🧭".repeat(12);
        assert!(parse(&emoji, &policy).is_ok());

        let spaced = "  keep spaces here  ";
        let parsed = parse(spaced, &policy).expect("spaces are valid password content");
        assert_eq!(parsed.expose(), spaced);
    }

    #[test]
    fn cq_10_plain_password_is_redacted() {
        let policy = PasswordPolicy::new(12);
        let password = parse("not a real credential", &policy).expect("synthetic password parses");
        let debug = format!("{password:?}");

        assert!(!debug.contains("not a real credential"));
    }

    #[test]
    fn common_list_matches_provenance() {
        let words = include_str!("../../data/common-passwords.txt");
        let provenance = include_str!("../../data/PROVENANCE.md");
        let entries: Vec<&str> = words.lines().collect();
        let unique: BTreeSet<&str> = entries.iter().copied().collect();
        let digest = hash_token(words.as_bytes());
        let mut digest_hex = String::with_capacity(digest.len() * 2);
        for byte in digest {
            let _ = write!(digest_hex, "{byte:02x}");
        }

        assert_eq!(entries.len(), 10_000);
        assert_eq!(unique.len(), 10_000);
        assert!(
            entries
                .iter()
                .all(|entry| !entry.is_empty() && *entry == entry.to_lowercase())
        );
        assert!(provenance.contains(&digest_hex));
    }
}
