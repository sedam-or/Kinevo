//! Admin HTTP handlers are added by the follow-up admin tasks.

/// Secret management endpoints.
pub mod secrets;

/// Policy listing and write endpoints.
pub mod policies;

/// Module listing and switch endpoints.
pub mod modules;

/// Instance summary endpoint.
pub mod instance;
