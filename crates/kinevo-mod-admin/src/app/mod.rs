//! Admin use cases are added by the follow-up admin tasks.

/// RLS-scoped audit list use cases.
pub mod audit;

/// Secret management use cases.
pub mod secrets;

/// Policy listing, validation, and version writes.
pub mod policies;

/// Module switches and instance summary use cases.
pub mod modules;

/// Embedded setup-template registry and application planning.
pub mod templates;

/// Atomic first-account provisioning and setup completion.
pub mod finish_setup;

/// Setup token lifecycle and the process-local wizard session.
pub mod setup;

/// Admin jobs summary and retry use cases.
pub mod jobs_admin;
