//! Database constraint mappings owned by the identity module.

use kinevo_db::errors::ConstraintMap;
use kinevo_kernel::errors::ErrorCode;

/// Maps stable identity constraint names to application error codes.
pub const CONSTRAINTS: ConstraintMap =
    ConstraintMap(&[("accounts_email_key", ErrorCode::EmailTaken)]);
