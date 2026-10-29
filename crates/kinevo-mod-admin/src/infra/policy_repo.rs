//! RLS-scoped access to append-only policy versions.

use kinevo_db::{Db, SystemTx, Tx};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
    policy::{PolicyVersion, Scope},
    time::Instant,
};
use sqlx::{PgConnection, Row, types::Uuid};

/// PostgreSQL repository for policy version history.
pub struct PolicyRepository {
    db: Db,
}

/// Values persisted together in one policy version row.
pub(crate) struct PolicyVersionInsert<'a> {
    pub id: Uuid,
    pub actor: AccountId,
    pub key: &'static str,
    pub scope: Scope,
    pub account: Option<AccountId>,
    pub value: &'a serde_json::Value,
    pub effective_from: Instant,
    pub created_at: Instant,
}

/// Values persisted in one policy change audit row.
pub(crate) struct PolicyAuditChange<'a> {
    pub id: Uuid,
    pub actor: AccountId,
    pub key: &'static str,
    pub scope: Scope,
    pub account: Option<AccountId>,
    pub old: &'a serde_json::Value,
    pub new: &'a serde_json::Value,
    pub occurred_at: Instant,
}

impl PolicyRepository {
    /// Uses the application's restricted database pools.
    #[must_use]
    pub fn new(db: Db) -> Self {
        Self { db }
    }

    /// Loads versions visible to the account's tenant context, or instance rows for a worker.
    pub async fn load_visible(
        &self,
        account: Option<AccountId>,
        at: Instant,
    ) -> Result<Vec<PolicyVersion>, AppError> {
        if let Some(account) = account {
            let mut tx = Tx::begin(&self.db, account).await?;
            let versions = load_versions(tx.conn(), at).await?;
            tx.commit().await?;
            Ok(versions)
        } else {
            let mut tx = SystemTx::begin(&self.db).await?;
            let versions = load_versions(tx.conn(), at).await?;
            tx.commit().await?;
            Ok(versions)
        }
    }

    /// Reads policy versions inside the caller's write transaction for audit consistency.
    pub(crate) async fn load_for_update(
        &self,
        tx: &mut Tx,
        at: Instant,
    ) -> Result<Vec<PolicyVersion>, AppError> {
        load_versions(tx.conn(), at).await
    }

    /// Returns the newest effective timestamp in one scope, including future versions.
    pub(crate) async fn latest_effective(
        &self,
        tx: &mut Tx,
        key: &'static str,
        scope: Scope,
        account: Option<AccountId>,
    ) -> Result<Option<Instant>, AppError> {
        let millis = sqlx::query_scalar::<_, i64>(
            "SELECT floor(extract(epoch FROM effective_from) * 1000)::bigint \
             FROM public.policy_versions \
             WHERE key=$1 AND scope=$2 AND account_id IS NOT DISTINCT FROM $3 \
             ORDER BY effective_from DESC, id DESC LIMIT 1",
        )
        .bind(key)
        .bind(scope_name(scope))
        .bind(account.map(|id| *id.as_uuid()))
        .fetch_optional(tx.conn())
        .await
        .map_err(database_error)?;
        millis
            .map(Instant::from_unix_millis)
            .transpose()
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))
    }

    /// Appends one canonical JSON value without updating prior versions.
    pub(crate) async fn append(
        &self,
        tx: &mut Tx,
        insert: PolicyVersionInsert<'_>,
    ) -> Result<(), AppError> {
        let value = serde_json::to_string(insert.value)
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        sqlx::query(
            "INSERT INTO public.policy_versions \
             (id, scope, account_id, key, value, effective_from, created_by, created_at) \
             VALUES ($1, $2, $3, $4, $5::jsonb, $6::timestamptz, $7, $8::timestamptz)",
        )
        .bind(insert.id)
        .bind(scope_name(insert.scope))
        .bind(insert.account.map(|id| *id.as_uuid()))
        .bind(insert.key)
        .bind(value)
        .bind(insert.effective_from.to_rfc3339())
        .bind(*insert.actor.as_uuid())
        .bind(insert.created_at.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(database_error)?;
        Ok(())
    }

    /// Appends policy audit values in the same transaction as the version and outbox event.
    pub(crate) async fn audit_change(
        &self,
        tx: &mut Tx,
        change: PolicyAuditChange<'_>,
    ) -> Result<(), AppError> {
        let details = serde_json::json!({
            "key": change.key,
            "scope": scope_name(change.scope),
            "old": change.old,
            "new": change.new,
        });
        let details =
            serde_json::to_string(&details).map_err(|_| AppError::new(ErrorCode::InternalError))?;
        sqlx::query(
            "INSERT INTO public.audit_log \
             (id, account_id, actor_id, action, target_type, target_id, details, request_id, occurred_at) \
             VALUES ($1, $2, $3, 'policy.changed', 'policy', $4, $5::jsonb, NULL, $6::timestamptz)",
        )
        .bind(change.id)
        .bind(change.account.map(|id| *id.as_uuid()))
        .bind(*change.actor.as_uuid())
        .bind(change.key)
        .bind(details)
        .bind(change.occurred_at.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(database_error)?;
        Ok(())
    }
}

async fn load_versions(
    conn: &mut PgConnection,
    at: Instant,
) -> Result<Vec<PolicyVersion>, AppError> {
    let rows = sqlx::query(
        "SELECT id, scope, account_id, key, value::text AS value, \
                floor(extract(epoch FROM effective_from) * 1000)::bigint AS effective_millis \
         FROM public.policy_versions WHERE effective_from <= $1::timestamptz \
         ORDER BY key, scope, account_id, effective_from, id",
    )
    .bind(at.to_rfc3339())
    .fetch_all(conn)
    .await
    .map_err(database_error)?;

    let mut versions = Vec::with_capacity(rows.len());
    for row in rows {
        let key: String = row.try_get("key").map_err(database_error)?;
        let Some(spec) = kinevo_kernel::policy::registry::all_specs()
            .iter()
            .copied()
            .find(|spec| spec.key == key)
        else {
            continue;
        };
        let scope: String = row.try_get("scope").map_err(database_error)?;
        let scope = match scope.as_str() {
            "instance" => Scope::Instance,
            "account" => Scope::Account,
            _ => return Err(AppError::new(ErrorCode::ServiceUnavailable)),
        };
        let account_id: Option<Uuid> = row.try_get("account_id").map_err(database_error)?;
        let raw_value: String = row.try_get("value").map_err(database_error)?;
        let value = serde_json::from_str(&raw_value)
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let effective_millis: i64 = row.try_get("effective_millis").map_err(database_error)?;
        versions.push(PolicyVersion {
            id: row.try_get("id").map_err(database_error)?,
            scope,
            account_id: account_id.map(AccountId::from_uuid),
            key: spec.key,
            value: kinevo_kernel::policy::PolicyValue::Json(value),
            effective_from: Instant::from_unix_millis(effective_millis)
                .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?,
        });
    }
    Ok(versions)
}

const fn scope_name(scope: Scope) -> &'static str {
    match scope {
        Scope::Instance => "instance",
        Scope::Account => "account",
    }
}

fn database_error(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}
