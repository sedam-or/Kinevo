//! Policy listing, validation, effective dating, and transactional writes.

use std::sync::Arc;

use kinevo_db::{
    Db, Tx,
    outbox::{self, Subscriber, SubscriberRegistry},
};
use kinevo_http::auth::AuthCtx;
use kinevo_kernel::{
    errors::{AppError, Details, ErrorCode, version_conflict},
    event::{EventEnumValue, EventEnvelope, EventKind, EventPayload},
    id::{EventId, IdGen},
    policy::{
        PolicyError, PolicySpec, PolicyType, PolicyValue, Scope, check_effective,
        registry::all_specs, resolve, validate_scope, validate_value,
    },
    time::{Clock, Instant},
};
use kinevo_ports::platform::PolicyReader;
use serde::Serialize;
use serde_json::Value;
use sqlx::types::Uuid;

use crate::infra::{
    policy_reader::{
        CachedPolicyReader, PolicyCache, PolicyChangedSubscriber, register_policy_subscriber,
    },
    policy_repo::{PolicyAuditChange, PolicyRepository, PolicyVersionInsert},
};

/// One registered policy with its resolved value and UI form metadata.
#[derive(Clone, Debug, PartialEq, Serialize)]
pub struct PolicyView {
    pub key: &'static str,
    #[serde(rename = "type")]
    pub type_name: &'static str,
    pub bounds: Value,
    pub label_key: String,
    pub source: &'static str,
    pub value: Value,
    pub version_id: Option<Uuid>,
    pub effective_from: Option<Instant>,
}

/// Result returned after appending one policy version.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
pub struct PolicySet {
    pub version_id: Uuid,
    pub effective_from: Instant,
}

/// Policy use cases shared by the admin HTTP surface and typed module port.
pub struct PolicyService {
    db: Db,
    repository: Arc<PolicyRepository>,
    reader: Arc<CachedPolicyReader>,
    cache: Arc<PolicyCache>,
    ids: Arc<dyn IdGen>,
    clock: Arc<dyn Clock>,
    subscribers: SubscriberRegistry,
}

impl PolicyService {
    /// Creates the repository, typed reader, and shared process cache.
    #[must_use]
    pub fn new(
        db: Db,
        ids: Arc<dyn IdGen>,
        clock: Arc<dyn Clock>,
        mut subscribers: SubscriberRegistry,
    ) -> Self {
        let repository = Arc::new(PolicyRepository::new(db.clone()));
        let cache = Arc::new(PolicyCache::new());
        let reader = Arc::new(CachedPolicyReader::new(
            Arc::clone(&repository),
            Arc::clone(&cache),
        ));
        register_policy_subscriber(&mut subscribers);
        Self {
            db,
            repository,
            reader,
            cache,
            ids,
            clock,
            subscribers,
        }
    }

    /// Shares the implementation of the `PolicyReader` port with consumers.
    #[must_use]
    pub fn reader(&self) -> Arc<dyn PolicyReader> {
        self.reader.clone()
    }

    /// Returns the cache invalidation subscriber that the application dispatcher should run.
    #[must_use]
    pub fn cache_subscriber(&self) -> Arc<dyn Subscriber> {
        Arc::new(PolicyChangedSubscriber::new(Arc::clone(&self.cache)))
    }

    /// Lists resolved registry values and stable form metadata for the requested scope.
    pub async fn list(&self, ctx: &AuthCtx, scope: Scope) -> Result<Vec<PolicyView>, AppError> {
        let account = match scope {
            Scope::Instance => None,
            Scope::Account => Some(ctx.account_id()),
        };
        let now = self.clock.now();
        let values = self.reader.snapshot_values(account, now).await?;
        let versions = self.repository.load_visible(account, now).await?;
        all_specs()
            .iter()
            .map(|spec| {
                let resolved = values
                    .resolved(spec.key)
                    .cloned()
                    .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
                let effective_from = resolved.version_id.and_then(|id| {
                    versions
                        .iter()
                        .find(|version| version.id == id)
                        .map(|version| version.effective_from)
                });
                Ok(view(spec, &resolved, effective_from))
            })
            .collect()
    }

    /// Validates and appends a policy version with its audit and outbox records atomically.
    pub async fn set(
        &self,
        ctx: &AuthCtx,
        raw_key: &str,
        scope: Scope,
        value: Value,
        requested_effective_from: Option<Instant>,
    ) -> Result<PolicySet, AppError> {
        let spec = all_specs()
            .iter()
            .copied()
            .find(|spec| spec.key == raw_key)
            .ok_or_else(|| AppError::new(ErrorCode::PolicyUnknown))?;
        validate_scope(spec, scope).map_err(|error| policy_error(&error))?;

        let account = match scope {
            Scope::Instance => {
                if !ctx.is_admin() {
                    return Err(AppError::new(ErrorCode::Forbidden));
                }
                None
            }
            Scope::Account => Some(ctx.account_id()),
        };
        let validated = validate_value(spec, &value).map_err(|error| policy_error(&error))?;
        let now = self.clock.now();
        let mut tx = match scope {
            Scope::Instance => Tx::begin_admin(&self.db, ctx.account_id()).await?,
            Scope::Account => Tx::begin(&self.db, ctx.account_id()).await?,
        };
        let versions = self.repository.load_for_update(&mut tx, now).await?;
        let previous = resolve(spec, &versions, account, now);
        let latest = self
            .repository
            .latest_effective(&mut tx, spec.key, scope, account)
            .await?;
        let effective_from = check_effective(spec, now, requested_effective_from, latest)
            .map_err(|error| policy_error(&error))?;
        let old_value = policy_value_json(&previous.value);
        let new_value = policy_value_json(&validated);
        let version_id = self.ids.new_uuid_v7();
        self.repository
            .append(
                &mut tx,
                PolicyVersionInsert {
                    id: version_id,
                    actor: ctx.account_id(),
                    key: spec.key,
                    scope,
                    account,
                    value: &new_value,
                    effective_from,
                    created_at: now,
                },
            )
            .await?;
        self.repository
            .audit_change(
                &mut tx,
                PolicyAuditChange {
                    id: self.ids.new_uuid_v7(),
                    actor: ctx.account_id(),
                    key: spec.key,
                    scope,
                    account,
                    old: &old_value,
                    new: &new_value,
                    occurred_at: now,
                },
            )
            .await?;
        outbox::emit(
            &mut tx,
            self.ids.as_ref(),
            now,
            EventEnvelope {
                id: EventId::from_uuid(self.ids.new_uuid_v7()),
                kind: EventKind::PolicyChanged,
                v: 1,
                account_id: account,
                occurred_at: now,
                payload: EventPayload::new()
                    .with_enum("scope", EventEnumValue::new(scope_name(scope))),
            },
            &self.subscribers,
        )
        .await?;
        tx.commit().await?;
        self.cache.invalidate_all().await;
        Ok(PolicySet {
            version_id,
            effective_from,
        })
    }
}

fn view(
    spec: &PolicySpec,
    resolved: &kinevo_kernel::policy::Resolved,
    effective_from: Option<Instant>,
) -> PolicyView {
    PolicyView {
        key: spec.key,
        type_name: type_name(spec.ty),
        bounds: bounds(spec.ty),
        label_key: format!("admin.policy.{}", spec.key),
        source: source_name(resolved.source),
        value: policy_value_json(&resolved.value),
        version_id: resolved.version_id,
        effective_from,
    }
}

fn type_name(policy_type: PolicyType) -> &'static str {
    match policy_type {
        PolicyType::Int { .. } => "int",
        PolicyType::Bool => "bool",
        PolicyType::Enum { .. } => "enum",
        PolicyType::DurationSeconds { .. } => "duration_seconds",
        PolicyType::LocalTime => "local_time",
        PolicyType::Bp => "bp",
        PolicyType::TextKey => "text_key",
        PolicyType::Json { .. } => "json",
    }
}

fn bounds(policy_type: PolicyType) -> Value {
    match policy_type {
        PolicyType::Int { min, max } | PolicyType::DurationSeconds { min, max } => {
            serde_json::json!({ "min": min, "max": max })
        }
        PolicyType::Bool => serde_json::json!({ "type": "bool" }),
        PolicyType::Enum { allowed } => serde_json::json!({ "allowed": allowed }),
        PolicyType::LocalTime => serde_json::json!({ "format": "HH:MM" }),
        PolicyType::Bp => serde_json::json!({ "min": 0, "max": 10_000 }),
        PolicyType::TextKey => serde_json::json!({ "type": "text_key" }),
        PolicyType::Json { schema } => serde_json::json!({ "schema": schema }),
    }
}

fn source_name(source: kinevo_kernel::policy::ResolutionSource) -> &'static str {
    match source {
        kinevo_kernel::policy::ResolutionSource::Default => "default",
        kinevo_kernel::policy::ResolutionSource::Instance => "instance",
        kinevo_kernel::policy::ResolutionSource::Account => "account",
    }
}

fn scope_name(scope: Scope) -> &'static str {
    match scope {
        Scope::Instance => "instance",
        Scope::Account => "account",
    }
}

fn policy_value_json(value: &PolicyValue) -> Value {
    match value {
        PolicyValue::Int(value) | PolicyValue::Seconds(value) => serde_json::json!(value),
        PolicyValue::Bool(value) => serde_json::json!(value),
        PolicyValue::Str(value) => serde_json::json!(value),
        PolicyValue::Text(value) => serde_json::json!(value),
        PolicyValue::LocalTime(minutes) => {
            serde_json::json!(format!("{:02}:{:02}", minutes / 60, minutes % 60))
        }
        PolicyValue::Bp(value) => serde_json::json!(value),
        PolicyValue::Json(value) => value.clone(),
    }
}

fn policy_error(error: &PolicyError) -> AppError {
    let mut result = AppError::new(error.code);
    if let Some(bounds) = error.details.get("bounds") {
        let resource = version_conflict(0, bounds)
            .details
            .as_map()
            .get("current")
            .cloned();
        if let Some(resource) = resource {
            result.details = Details::new().with_detail("bounds", resource);
        }
    }
    result
}
