//! Admin-owned database adapters.

/// Admin constraint mapping; current schema constraints are not user-visible.
pub mod constraints {
    use kinevo_db::errors::ConstraintMap;

    /// Admin constraints do not map to user-facing application errors in this task.
    pub const CONSTRAINTS: ConstraintMap = ConstraintMap(&[]);
}
