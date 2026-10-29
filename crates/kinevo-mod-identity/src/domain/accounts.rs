//! Pure guards for administrator account changes.

use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
};
use kinevo_ports::platform::AccountStatus;

/// Rejects disabling the last active instance administrator.
///
/// `active_admin_count` is the locked count of active administrators including the target.
pub fn can_change_status(
    _actor: AccountId,
    _target: AccountId,
    active_admin_count: i64,
    new_status: AccountStatus,
) -> Result<(), AppError> {
    if new_status == AccountStatus::Disabled && active_admin_count <= 1 {
        return Err(AppError::new(ErrorCode::LastAdmin));
    }
    Ok(())
}
