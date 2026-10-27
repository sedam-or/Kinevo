//! Authenticated request context and the bounded account authorization cache.

use axum::{extract::FromRequestParts, http::request::Parts};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
};
use kinevo_ports::platform::{AccountAccess, AccountAccessInfo};
use moka::future::Cache;
use std::{
    future::{Future, ready},
    sync::Arc,
    time::Duration,
};

use crate::{problem::ApiError, token::FamilyId};

/// Authorization role derived from the loaded account row.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum Role {
    AccountOwner,
    InstanceAdmin,
}

/// Opaque proof that an authenticated context is an instance administrator.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct AdminProof(());

/// Server-derived identity for one authenticated request.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct AuthCtx {
    account_id: AccountId,
    session_id: FamilyId,
    role: Role,
    mfa: bool,
}

impl AuthCtx {
    pub(crate) fn new(account_id: AccountId, session_id: FamilyId, role: Role, mfa: bool) -> Self {
        Self {
            account_id,
            session_id,
            role,
            mfa,
        }
    }

    #[must_use]
    pub fn account_id(&self) -> AccountId {
        self.account_id
    }

    #[must_use]
    pub fn session_id(&self) -> FamilyId {
        self.session_id
    }

    #[must_use]
    pub fn role(&self) -> Role {
        self.role
    }

    #[must_use]
    pub fn mfa(&self) -> bool {
        self.mfa
    }

    #[must_use]
    pub fn is_admin(&self) -> bool {
        self.role == Role::InstanceAdmin
    }

    pub fn require_admin(&self) -> Result<AdminProof, AppError> {
        if self.is_admin() {
            Ok(AdminProof(()))
        } else {
            Err(AppError::new(ErrorCode::Forbidden))
        }
    }
}

/// Extracts the gate-inserted authorization context from request extensions.
pub struct Auth(pub AuthCtx);

impl FromRequestParts<crate::state::HttpState> for Auth {
    type Rejection = ApiError;

    fn from_request_parts(
        parts: &mut Parts,
        _state: &crate::state::HttpState,
    ) -> impl Future<Output = Result<Self, Self::Rejection>> + Send {
        ready(
            parts
                .extensions
                .get::<AuthCtx>()
                .cloned()
                .map(Self)
                .ok_or_else(|| ApiError(AppError::new(ErrorCode::InternalError))),
        )
    }
}

/// Cached account status and role flags loaded through the authorization port.
pub struct AccountCache {
    source: Arc<dyn AccountAccess>,
    entries: Cache<AccountId, AccountAccessInfo>,
}

impl AccountCache {
    /// Uses the spec's permission-set bounds: 10,000 entries and 60 seconds.
    #[must_use]
    pub fn new(source: Arc<dyn AccountAccess>) -> Self {
        Self::with_ttl(source, Duration::from_secs(60))
    }

    /// Creates a bounded cache with an explicit TTL; zero disables reuse in tests.
    #[must_use]
    pub fn with_ttl(source: Arc<dyn AccountAccess>, ttl: Duration) -> Self {
        let entries = Cache::builder()
            .max_capacity(10_000)
            .time_to_live(ttl)
            .build();
        Self { source, entries }
    }

    /// Loads a minimal account row, caching positive lookups only.
    pub async fn get(&self, id: AccountId) -> Result<Option<AccountAccessInfo>, AppError> {
        if let Some(account) = self.entries.get(&id).await {
            return Ok(Some(account));
        }
        let account = self.source.load(id).await?;
        if let Some(account) = account {
            self.entries.insert(id, account).await;
        }
        Ok(account)
    }

    /// Removes one account's cached status and role flags.
    pub async fn invalidate(&self, account_id: AccountId) {
        self.entries.invalidate(&account_id).await;
    }

    /// Removes all cached status and role flags.
    pub fn invalidate_all(&self) {
        self.entries.invalidate_all();
    }
}
