//! Fixed column and status parsing helpers for administrator account queries.

use kinevo_kernel::errors::{AppError, ErrorCode};
use kinevo_ports::platform::AccountStatus;

/// Parses the closed account-status enum returned by PostgreSQL.
pub fn parse_status(value: &str) -> Result<AccountStatus, AppError> {
    match value {
        "active" => Ok(AccountStatus::Active),
        "disabled" => Ok(AccountStatus::Disabled),
        "deleting" => Ok(AccountStatus::Deleting),
        _ => Err(AppError::new(ErrorCode::ServiceUnavailable)),
    }
}
