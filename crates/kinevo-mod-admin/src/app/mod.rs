//! Admin use cases are added by the follow-up admin tasks.

/// Secret management use cases.
pub mod secrets;

/// Policy listing, validation, and version writes.
pub mod policies;

/// Module switches and instance summary use cases.
pub mod modules;

/// Embedded setup-template registry and application planning.
pub mod templates;

/// Setup token lifecycle and the process-local wizard session.
pub mod setup;
