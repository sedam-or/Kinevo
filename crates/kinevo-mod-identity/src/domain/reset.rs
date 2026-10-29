//! Pure password reset token lifetime rules.

use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    time::Instant,
};

/// A reset token's server-side expiry instant.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct ResetToken {
    pub expires_at: Instant,
}

impl ResetToken {
    /// Creates a token lifetime using the configured whole-second policy.
    pub fn issue(now: Instant, ttl_seconds: i64) -> Result<Self, AppError> {
        let expires_at = now
            .checked_add_seconds(ttl_seconds)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        Ok(Self { expires_at })
    }

    /// Returns whether the token is still valid at the supplied instant.
    #[must_use]
    pub fn is_valid_at(self, now: Instant) -> bool {
        now.unix_millis() < self.expires_at.unix_millis()
    }
}
