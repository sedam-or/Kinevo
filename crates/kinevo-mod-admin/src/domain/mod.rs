//! Pure domain types for instance administration.

/// Scope used when reading or writing a policy version.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum PolicyScope {
    /// Policy applies to the whole instance.
    Instance,
    /// Policy applies to one account.
    Account,
}

/// Registered secret names supported by the shared platform port.
pub use crate::ports::SecretName as SecretNameRegistered;

/// Secret registry names and their permitted scopes.
pub mod secrets;

/// Strict setup-template schema and validation.
pub mod template;
