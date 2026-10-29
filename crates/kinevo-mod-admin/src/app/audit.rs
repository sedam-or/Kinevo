//! RLS-scoped audit listing use cases.

use kinevo_db::{
    Db, Tx,
    audit::{self, AuditCursor, AuditListFilter, AuditRecord},
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
    time::Instant,
};
use serde::Serialize;
use serde_json::Value;
use sqlx::types::Uuid;

/// Safe audit fields exposed to the HTTP list responses.
#[derive(Clone, Debug, Serialize)]
pub struct AuditView {
    pub id: Uuid,
    pub at: Instant,
    pub actor_id: Option<Uuid>,
    pub account_id: Option<Uuid>,
    pub action: String,
    pub details: Value,
}

/// Filters accepted by the audit list endpoints.
#[derive(Clone, Debug, Default)]
pub struct AuditFilter {
    pub action: Option<String>,
    pub from: Option<Instant>,
    pub to: Option<Instant>,
    pub account_id: Option<AccountId>,
    pub before: Option<AuditCursor>,
    pub limit: u16,
}

/// Reads audit rows through the app role and its transaction-scoped RLS context.
pub struct AuditService {
    db: Db,
}

impl AuditService {
    /// Uses the platform database API for audit reads.
    #[must_use]
    pub fn new(db: Db) -> Self {
        Self { db }
    }

    /// Lists the instance audit stream after the caller's admin gate has passed.
    pub async fn list_admin(
        &self,
        actor: AccountId,
        filter: AuditFilter,
    ) -> Result<Vec<AuditView>, AppError> {
        self.list(actor, filter, true).await
    }

    /// Lists only the caller's audit rows; a request account filter is not accepted here.
    pub async fn list_own(
        &self,
        account: AccountId,
        filter: AuditFilter,
    ) -> Result<Vec<AuditView>, AppError> {
        self.list(account, filter, false).await
    }

    async fn list(
        &self,
        account: AccountId,
        mut filter: AuditFilter,
        admin: bool,
    ) -> Result<Vec<AuditView>, AppError> {
        if matches!((filter.from, filter.to), (Some(from), Some(to)) if from >= to) {
            return Err(AppError::new(ErrorCode::ValidationFailed));
        }
        if !admin {
            filter.account_id = Some(account);
        }
        let mut tx = if admin {
            Tx::begin_admin(&self.db, account).await?
        } else {
            Tx::begin(&self.db, account).await?
        };
        let rows = audit::list(
            &mut tx,
            AuditListFilter {
                action: filter.action,
                from: filter.from,
                to: filter.to,
                account_id: filter.account_id,
                before: filter.before,
                limit: filter.limit.saturating_add(1),
            },
        )
        .await?;
        tx.commit().await?;
        Ok(rows.into_iter().map(audit_view).collect())
    }
}

fn audit_view(row: AuditRecord) -> AuditView {
    AuditView {
        id: row.id,
        at: row.at,
        actor_id: row.actor_id,
        account_id: row.account_id,
        action: row.action,
        details: row.details,
    }
}
