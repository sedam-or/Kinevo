use kinevo_kernel::{
    id::{AccountId, FamilyId},
    time::Instant,
};
use sqlx::types::Uuid;

/// Caller-supplied access and refresh lifetimes, in whole seconds.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct SessionPolicy {
    pub access_ttl: u32,
    pub idle_ttl: u32,
    pub absolute_ttl: u32,
    pub grace: u32,
}

/// The reason a refresh-token family stopped being usable.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum RevocationReason {
    Logout,
    ReuseDetected,
    PasswordChanged,
    Admin,
    Expired,
}

impl RevocationReason {
    /// Returns the constrained database value for this reason.
    #[must_use]
    pub const fn as_str(self) -> &'static str {
        match self {
            Self::Logout => "logout",
            Self::ReuseDetected => "reuse_detected",
            Self::PasswordChanged => "password_changed",
            Self::Admin => "admin",
            Self::Expired => "expired",
        }
    }

    /// Parses a constrained database value.
    #[must_use]
    pub fn parse(value: &str) -> Option<Self> {
        match value {
            "logout" => Some(Self::Logout),
            "reuse_detected" => Some(Self::ReuseDetected),
            "password_changed" => Some(Self::PasswordChanged),
            "admin" => Some(Self::Admin),
            "expired" => Some(Self::Expired),
            _ => None,
        }
    }
}

/// Persisted refresh-token state used by the pure session decision function.
pub struct TokenRow {
    pub id: Uuid,
    pub family_id: FamilyId,
    pub account_id: AccountId,
    pub token_hash: [u8; 32],
    pub issued_at: Instant,
    pub idle_expires_at: Instant,
    pub used_at: Option<Instant>,
    pub replaced_by: Option<Uuid>,
}

/// Persisted refresh-token family state used by the pure session decision function.
pub struct FamilyRow {
    pub id: FamilyId,
    pub account_id: AccountId,
    pub device_label: Option<String>,
    pub mfa: bool,
    pub created_at: Instant,
    pub last_used_at: Instant,
    pub absolute_expires_at: Instant,
    pub revoked_at: Option<Instant>,
    pub revoked_reason: Option<RevocationReason>,
}

/// Result of evaluating one presented refresh token against its family.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum RefreshDecision {
    Unknown,
    Revoked,
    Expired,
    Rotate,
    Grace,
    Reuse,
}

/// Returns the idle expiry capped by the family's fixed absolute expiry.
#[must_use]
pub fn idle_expiry(now: Instant, policy: SessionPolicy, family_absolute: Instant) -> Instant {
    now.checked_add_seconds(i64::from(policy.idle_ttl))
        .unwrap_or(family_absolute)
        .min(family_absolute)
}

/// Evaluates refresh state in the same precedence as the normative algorithm.
#[must_use]
pub fn decide(
    row: &TokenRow,
    family: &FamilyRow,
    now: Instant,
    cached_present: bool,
) -> RefreshDecision {
    if family.revoked_at.is_some() {
        return RefreshDecision::Revoked;
    }
    if now >= family.absolute_expires_at || now >= row.idle_expires_at {
        return RefreshDecision::Expired;
    }
    if row.used_at.is_none() {
        return RefreshDecision::Rotate;
    }
    if cached_present {
        RefreshDecision::Grace
    } else {
        RefreshDecision::Reuse
    }
}

#[cfg(test)]
#[allow(
    clippy::expect_used,
    reason = "the decision table uses literal fixture timestamps"
)]
mod tests {
    use super::{
        FamilyRow, RefreshDecision, RevocationReason, SessionPolicy, TokenRow, decide, idle_expiry,
    };
    use kinevo_kernel::{
        id::{AccountId, FamilyId},
        time::Instant,
    };
    use sqlx::types::Uuid;

    fn instant(value: &str) -> Instant {
        Instant::parse_rfc3339(value).expect("test instant is valid")
    }

    fn token(now: Instant, idle_expires_at: Instant, used: bool) -> TokenRow {
        TokenRow {
            id: Uuid::nil(),
            family_id: FamilyId::from_uuid(Uuid::nil()),
            account_id: AccountId::from_uuid(Uuid::nil()),
            token_hash: [0; 32],
            issued_at: now,
            idle_expires_at,
            used_at: used.then_some(now),
            replaced_by: None,
        }
    }

    fn family(now: Instant, absolute_expires_at: Instant, revoked: bool) -> FamilyRow {
        FamilyRow {
            id: FamilyId::from_uuid(Uuid::nil()),
            account_id: AccountId::from_uuid(Uuid::nil()),
            device_label: None,
            mfa: false,
            created_at: now,
            last_used_at: now,
            absolute_expires_at,
            revoked_at: revoked.then_some(now),
            revoked_reason: revoked.then_some(RevocationReason::Admin),
        }
    }

    #[test]
    fn auth_01_decision_table() {
        let now = instant("2026-11-02T00:00:00Z");
        let idle = instant("2026-11-02T00:00:10Z");
        let absolute = instant("2026-11-02T00:00:20Z");
        let after = instant("2026-11-02T00:00:21Z");
        let cases = [
            (
                "revoked takes precedence",
                now,
                idle,
                true,
                false,
                true,
                RefreshDecision::Revoked,
            ),
            (
                "absolute expiry is inclusive",
                absolute,
                after,
                false,
                false,
                false,
                RefreshDecision::Expired,
            ),
            (
                "idle expiry is inclusive",
                idle,
                idle,
                false,
                true,
                false,
                RefreshDecision::Expired,
            ),
            (
                "fresh token rotates",
                now,
                idle,
                false,
                false,
                false,
                RefreshDecision::Rotate,
            ),
            (
                "unused token rotates despite cache entry",
                now,
                idle,
                false,
                false,
                true,
                RefreshDecision::Rotate,
            ),
            (
                "used token in grace is idempotent",
                now,
                idle,
                false,
                true,
                true,
                RefreshDecision::Grace,
            ),
            (
                "used token after grace is reuse",
                now,
                idle,
                false,
                true,
                false,
                RefreshDecision::Reuse,
            ),
        ];

        for (name, at, idle_expires_at, revoked, used, cached, expected) in cases {
            let token = token(now, idle_expires_at, used);
            let family = family(now, absolute, revoked);
            assert_eq!(decide(&token, &family, at, cached), expected, "{name}");
        }

        let policy = SessionPolicy {
            access_ttl: 600,
            idle_ttl: 30,
            absolute_ttl: 20,
            grace: 10,
        };
        assert_eq!(idle_expiry(now, policy, absolute), absolute);
    }
}
