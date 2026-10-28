//! Pure identity domain value types.

pub mod device;
pub mod lockout;
pub mod password;
pub mod profile;
pub mod session;

use serde::{Deserialize, Serialize};

pub use kinevo_kernel::id::{AccountId, FamilyId};

kinevo_kernel::define_id!(RefreshToken, RefreshTokenId);
kinevo_kernel::define_id!(Invite, InviteId);
kinevo_kernel::define_id!(Reset, ResetId);
kinevo_kernel::define_id!(Export, ExportId);

/// Account lifecycle status persisted by the identity module.
#[derive(Clone, Copy, Debug, Deserialize, Eq, PartialEq, Serialize)]
#[serde(rename_all = "snake_case")]
pub enum AccountStatus {
    Active,
    Disabled,
    Deleting,
}

impl AccountStatus {
    /// Returns the database representation of this status.
    #[must_use]
    pub const fn as_str(self) -> &'static str {
        match self {
            Self::Active => "active",
            Self::Disabled => "disabled",
            Self::Deleting => "deleting",
        }
    }

    /// Parses a persisted status value.
    #[must_use]
    pub fn parse(value: &str) -> Option<Self> {
        match value {
            "active" => Some(Self::Active),
            "disabled" => Some(Self::Disabled),
            "deleting" => Some(Self::Deleting),
            _ => None,
        }
    }
}

/// Reason a refresh-token family was revoked.
#[derive(Clone, Copy, Debug, Deserialize, Eq, PartialEq, Serialize)]
#[serde(rename_all = "snake_case")]
pub enum RevokeReason {
    Logout,
    ReuseDetected,
    PasswordChanged,
    Admin,
    Expired,
}

impl RevokeReason {
    /// Returns the database representation of this reason.
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
}

/// Lifecycle state for an account data export.
#[derive(Clone, Copy, Debug, Deserialize, Eq, PartialEq, Serialize)]
#[serde(rename_all = "snake_case")]
pub enum ExportState {
    Queued,
    Running,
    Ready,
    Failed,
    Expired,
}

impl ExportState {
    /// Returns the database representation of this state.
    #[must_use]
    pub const fn as_str(self) -> &'static str {
        match self {
            Self::Queued => "queued",
            Self::Running => "running",
            Self::Ready => "ready",
            Self::Failed => "failed",
            Self::Expired => "expired",
        }
    }
}
