//! Admin HTTP handlers are added by the follow-up admin tasks.

/// Admin and account-owner audit routes.
pub mod audit;

/// Secret management endpoints.
pub mod secrets;

/// Policy listing and write endpoints.
pub mod policies;

/// Module listing and switch endpoints.
pub mod modules;

/// Instance summary endpoint.
pub mod instance;

/// Public setup status and wizard-session endpoints.
pub mod setup;

/// Admin job summary and outbox retry routes.
pub mod jobs;
