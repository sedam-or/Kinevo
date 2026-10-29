//! Instance invite creation use case.

use std::sync::Arc;

use kinevo_db::{Db, Tx};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::{AccountId, IdGen, TokenGen},
    policy::registry::identity as policy,
    secret::Secret,
    time::Clock,
    validate::net::Email,
};
use kinevo_ports::platform::{InstanceState, PolicyReader};

use crate::infra::invites;

/// Creates instance-level registration links.
pub struct InviteService {
    db: Db,
    ids: Arc<dyn IdGen>,
    tokens: Arc<dyn TokenGen>,
    policies: Arc<dyn PolicyReader>,
    instance: Arc<dyn InstanceState>,
    clock: Arc<dyn Clock>,
}

impl InviteService {
    #[must_use]
    pub fn new(
        db: Db,
        ids: Arc<dyn IdGen>,
        tokens: Arc<dyn TokenGen>,
        policies: Arc<dyn PolicyReader>,
        instance: Arc<dyn InstanceState>,
        clock: Arc<dyn Clock>,
    ) -> Self {
        Self {
            db,
            ids,
            tokens,
            policies,
            instance,
            clock,
        }
    }

    /// Creates an invite after the HTTP permission gate has authorized the administrator.
    pub async fn create(
        &self,
        admin: AccountId,
        email: Option<Email>,
    ) -> Result<Secret<String>, AppError> {
        let now = self.clock.now();
        let snapshot = self.policies.snapshot_values(Some(admin), now).await?;
        let ttl = snapshot
            .get(&policy::INVITE_TTL)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        let instance = self.instance.get().await?;
        let base_url = instance
            .base_url
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        let mut tx = Tx::begin_admin(&self.db, admin).await?;
        let url = invites::create(
            &mut tx,
            self.ids.as_ref(),
            self.tokens.as_ref(),
            email.as_ref(),
            admin,
            base_url.as_str(),
            now,
            ttl.0,
        )
        .await?;
        tx.commit().await?;
        Ok(url)
    }
}
