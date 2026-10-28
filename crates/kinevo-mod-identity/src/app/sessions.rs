//! Account-owned refresh-session management.

use std::sync::Arc;

use kinevo_db::{Db, Tx};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::{AccountId, FamilyId},
    time::{Clock, Instant},
};
use serde::Serialize;

use crate::{
    domain::session::RevocationReason,
    infra::sessions::{self, FamilySummary},
};

/// One refresh-token family without token material.
#[derive(Serialize)]
pub struct SessionView {
    pub id: FamilyId,
    pub device_label: Option<String>,
    pub created_at: Instant,
    pub last_used_at: Instant,
    pub current: bool,
}

/// Owns account-scoped session listing and revocation.
pub struct SessionService {
    db: Db,
    clock: Arc<dyn Clock>,
}

impl SessionService {
    #[must_use]
    pub fn new(db: Db, clock: Arc<dyn Clock>) -> Self {
        Self { db, clock }
    }

    /// Lists non-revoked families in the stable API order.
    pub async fn list(
        &self,
        account: AccountId,
        current: FamilyId,
    ) -> Result<Vec<SessionView>, AppError> {
        let mut tx = Tx::begin(&self.db, account).await?;
        let families = sessions::list_families(&mut tx, account).await?;
        tx.commit().await?;
        Ok(families
            .into_iter()
            .filter(|family| family.revoked_at.is_none())
            .map(|family| session_view(family, current))
            .collect())
    }

    /// Revokes one family visible through the current tenant context.
    pub async fn revoke(&self, account: AccountId, id: FamilyId) -> Result<(), AppError> {
        let now = self.clock.now();
        let mut tx = Tx::begin(&self.db, account).await?;
        let affected = sessions::revoke_family(&mut tx, id, RevocationReason::Logout, now).await?;
        if affected != 1 {
            return Err(AppError::new(ErrorCode::NotFound));
        }
        tx.commit().await
    }
}

fn session_view(family: FamilySummary, current: FamilyId) -> SessionView {
    SessionView {
        id: family.family_id,
        device_label: family.device_label,
        created_at: family.created_at,
        last_used_at: family.last_used_at,
        current: family.family_id == current,
    }
}
