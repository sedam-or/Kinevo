//! Admin-owned database adapters.

/// Admin constraint mapping; current schema constraints are not user-visible.
pub mod constraints {
    use kinevo_db::errors::ConstraintMap;

    /// Admin constraints do not map to user-facing application errors in this task.
    pub const CONSTRAINTS: ConstraintMap = ConstraintMap(&[]);
}

/// PostgreSQL-backed AEAD storage for registered secrets.
pub mod secret_store;

/// PostgreSQL policy version queries.
pub mod policy_repo;

/// Cached policy snapshots and outbox invalidation.
pub mod policy_reader;
