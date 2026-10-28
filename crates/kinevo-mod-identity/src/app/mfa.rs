use async_trait::async_trait;
use kinevo_kernel::{id::AccountId, secret::Secret, time::Instant};

/// A validated second factor supplied by the client.
pub enum MfaInput {
    Totp(Secret<String>),
    Recovery(Secret<String>),
}

/// Whether the supplied second factor is accepted.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum MfaResult {
    Valid,
    Invalid,
}

/// Identity-owned port implemented by the TOTP module task.
#[async_trait]
pub trait MfaVerifier: Send + Sync {
    async fn verify(&self, account: AccountId, input: &MfaInput, now: Instant) -> MfaResult;
}
