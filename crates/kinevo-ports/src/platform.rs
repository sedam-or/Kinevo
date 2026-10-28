//! Narrow application ports and plain transport DTOs.

// SPEC: D-40, D-41, CA-10, MOD-10, MOD-11, POL-02, API-12

use async_trait::async_trait;
use kinevo_kernel::{
    errors::{AppError, DetailValue, ErrorCode},
    id::{AccountId, FamilyId},
    policy::{PolicyKey, PolicyValueType, Resolved},
    secret::Secret,
    time::Instant,
    validate::{
        net::{Email, HttpsUrl},
        tz::{IanaTz, Locale},
    },
};
use std::collections::BTreeMap;

/// Typed snapshot of resolved policy values at one instant.
#[derive(Clone, Debug, Default, PartialEq)]
// SPEC: POL-02
pub struct ResolvedPolicies {
    values: BTreeMap<String, Resolved>,
}

impl ResolvedPolicies {
    /// Builds a snapshot from the resolved values keyed by their registered names.
    #[must_use]
    pub fn new(values: BTreeMap<String, Resolved>) -> Self {
        Self { values }
    }

    /// Inserts one resolved value under its stable registry key.
    pub fn insert(&mut self, key: impl Into<String>, value: Resolved) {
        self.values.insert(key.into(), value);
    }

    /// Reads only when the registered value matches the key's Rust type.
    #[must_use]
    pub fn get<T: PolicyValueType>(&self, key: &PolicyKey<T>) -> Option<T> {
        self.values
            .get(key.name)
            .and_then(|resolved| key.get(resolved))
    }
}

#[async_trait]
// SPEC: D-41, POL-02
pub trait PolicyReader: Send + Sync {
    /// Resolves a consistent typed policy snapshot for this account and instant.
    async fn snapshot_values(
        &self,
        account: Option<AccountId>,
        at: Instant,
    ) -> Result<ResolvedPolicies, AppError>;
}

#[async_trait]
// SPEC: D-41, MOD-04
pub trait ModuleSwitches: Send + Sync {
    /// Returns the current cached enabled state for a validated module key.
    fn is_enabled(&self, key: &super::module::ModuleKey) -> bool;

    /// Refreshes the local enabled-state cache from its source of truth.
    async fn refresh(&self);
}

/// Plain instance state returned to modules and the HTTP layer.
#[derive(Clone)]
// SPEC: D-41
pub struct InstanceInfo {
    /// Whether initial setup has completed.
    pub setup_completed: bool,
    /// Validated public base URL, when configured.
    pub base_url: Option<HttpsUrl>,
    /// Locale used for instance-level messages.
    pub default_locale: Locale,
}

#[async_trait]
// SPEC: D-41
pub trait InstanceState: Send + Sync {
    /// Returns the current instance setup status and public defaults.
    async fn get(&self) -> Result<InstanceInfo, AppError>;
}

/// Transport class used when a newly created account receives its first session.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum Client {
    /// Browser client that stores the refresh token in an HTTP-only cookie.
    Web,
    /// Android client that receives the refresh token in the response body.
    Android,
}

/// Safe account fields returned by authentication and account creation.
#[derive(Clone, Debug, Eq, PartialEq, serde::Serialize, utoipa::ToSchema)]
pub struct AccountProfile {
    /// Canonical account identifier.
    pub id: String,
    /// Normalized account email.
    pub email: String,
    /// Display name shown in the client.
    pub display_name: String,
    /// Supported locale key.
    pub locale: String,
    /// Canonical IANA time zone.
    pub timezone: String,
    /// First day of the account's week.
    pub week_start: i16,
    /// Whether the account has enabled TOTP.
    pub totp_enabled: bool,
    /// Whether the account has instance administrator permissions.
    pub is_instance_admin: bool,
    /// Optimistic-concurrency version.
    pub version: i64,
}

/// Session policy snapshot passed into transactional account creation.
#[derive(Clone, Copy)]
pub struct AccountSessionSettings {
    /// Injected instant for the family and token rows.
    pub now: Instant,
    /// Access-token lifetime in seconds.
    pub access_ttl: u32,
    /// Refresh-token idle lifetime in seconds.
    pub idle_ttl: u32,
    /// Refresh-family absolute lifetime in seconds.
    pub absolute_ttl: u32,
    /// Refresh-token rotation grace in seconds.
    pub grace: u32,
}

/// Claims input for the first account access token.
#[derive(Clone, Copy)]
pub struct AccessClaims {
    /// New account subject.
    pub account_id: AccountId,
    /// Session family identifier.
    pub family_id: FamilyId,
    /// Injected issue instant.
    pub issued_at: Instant,
    /// Policy-derived expiry instant.
    pub expires_at: Instant,
    /// Whether the session satisfied MFA.
    pub mfa: bool,
}

/// Validated account fields and session settings supplied to the creation port.
pub struct NewAccount {
    /// Application-generated account identifier.
    pub id: AccountId,
    /// Normalized email value.
    pub email: Email,
    /// PHC password hash computed before opening the transaction.
    pub password_hash: String,
    /// Normalized display name.
    pub display_name: String,
    /// Validated canonical IANA time zone.
    pub timezone: IanaTz,
    /// Supported locale.
    pub locale: Locale,
    /// Whether the account is the first instance administrator.
    pub is_instance_admin: bool,
    /// Client class for the first session.
    pub client: Client,
    /// Safe device label, if supplied.
    pub device_label: Option<String>,
    /// Resolved time and session lifetimes.
    pub session: AccountSessionSettings,
}

/// Account creation result including the first refresh token and access claims.
pub struct CreatedAccount {
    /// Safe account profile.
    pub profile: AccountProfile,
    /// Plain refresh token returned once to the caller.
    pub refresh_token: Secret<String>,
    /// Values used to sign the first access token.
    pub access_claims: AccessClaims,
    /// Access-token lifetime in seconds.
    pub expires_in: u32,
    /// Refresh-cookie lifetime in seconds.
    pub refresh_cookie_max_age: u32,
}

/// Creates a new account and its first session inside the caller's transaction.
#[async_trait]
pub trait AccountCreation<T: Send>: Send + Sync {
    /// Inserts account-owned data, runs provisioners, and returns the first session material.
    async fn create(&self, tx: &mut T, input: NewAccount) -> Result<CreatedAccount, AppError>;
}

/// Scope of one registered secret name.
#[derive(Clone, Copy, Debug, Eq, Hash, Ord, PartialEq, PartialOrd)]
// SPEC: D-40
pub enum SecretScope {
    /// Secret belongs to the instance.
    Instance,
    /// Secret belongs to this account.
    Account(AccountId),
}

/// A secret name from the D-40 registry; arbitrary names are unrepresentable.
#[derive(Clone, Copy, Debug, Eq, Hash, Ord, PartialEq, PartialOrd)]
// SPEC: D-40
pub struct SecretName(&'static str);

const SECRET_NAMES: &[&str] = &[
    "ai.api_key",
    "billing.gateway_key",
    "billing.webhook_secret",
    "identity.totp_seed",
    "smtp.password",
];

impl SecretName {
    /// Parses one registered secret name or returns `secret_name_unknown`.
    pub fn parse(value: &str) -> Result<Self, AppError> {
        SECRET_NAMES
            .iter()
            .copied()
            .find(|allowed| *allowed == value)
            .map(Self)
            .ok_or_else(|| {
                AppError::new(ErrorCode::SecretNameUnknown).with_detail(
                    "allowed",
                    DetailValue::StrList(
                        SECRET_NAMES.iter().map(|name| (*name).to_owned()).collect(),
                    ),
                )
            })
    }

    /// Returns the stable registered secret name.
    #[must_use]
    pub const fn as_str(self) -> &'static str {
        self.0
    }

    #[cfg(feature = "testing")]
    fn allows(self, scope: SecretScope) -> bool {
        match self.0 {
            "ai.api_key" => true,
            "identity.totp_seed" => matches!(scope, SecretScope::Account(_)),
            "smtp.password" | "billing.gateway_key" | "billing.webhook_secret" => {
                matches!(scope, SecretScope::Instance)
            }
            _ => false,
        }
    }
}

/// Non-secret secret-presence metadata; it never contains the stored value.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct SecretMask {
    /// Whether a value is stored for this scope and name.
    pub is_set: bool,
    /// Final four Unicode scalar values when set; absent when no value is stored.
    pub last4: Option<String>,
}

#[cfg(feature = "testing")]
pub(crate) fn validate_secret_scope(scope: SecretScope, name: SecretName) -> Result<(), AppError> {
    if name.allows(scope) {
        return Ok(());
    }
    let allowed = SECRET_NAMES
        .iter()
        .copied()
        .filter(|candidate| SecretName(candidate).allows(scope))
        .map(str::to_owned)
        .collect();
    Err(AppError::new(ErrorCode::SecretNameUnknown)
        .with_detail("allowed", DetailValue::StrList(allowed)))
}

#[cfg(feature = "testing")]
pub(crate) fn last4(value: &str) -> String {
    value
        .chars()
        .rev()
        .take(4)
        .collect::<String>()
        .chars()
        .rev()
        .collect()
}

#[async_trait]
// SPEC: D-40, D-41, ADM-FR-06
pub trait SecretStore: Send + Sync {
    /// Stores a secret under a registered name and supported scope.
    async fn put(
        &self,
        scope: SecretScope,
        name: SecretName,
        value: Secret<String>,
    ) -> Result<(), AppError>;

    /// Loads a redacting secret wrapper, never a raw string.
    async fn get(
        &self,
        scope: SecretScope,
        name: SecretName,
    ) -> Result<Option<Secret<String>>, AppError>;

    /// Deletes a value and reports whether one existed.
    async fn delete(&self, scope: SecretScope, name: SecretName) -> Result<bool, AppError>;

    /// Returns only set state and the permitted final-four-character mask.
    async fn mask(&self, scope: SecretScope, name: SecretName) -> Result<SecretMask, AppError>;
}

/// Account state used by request authorization.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum AccountStatus {
    /// Account can authenticate.
    Active,
    /// Account cannot authenticate.
    Disabled,
    /// Account deletion is in progress.
    Deleting,
}

/// Minimal account row data needed by the HTTP authorization layer.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
// SPEC: API-12
pub struct AccountAccessInfo {
    /// Account identifier.
    pub id: AccountId,
    /// Current authorization status.
    pub status: AccountStatus,
    /// Whether the account has instance-admin permissions.
    pub is_instance_admin: bool,
    /// Optimistic-concurrency version for status changes.
    pub version: i64,
}

#[async_trait]
// SPEC: API-12
pub trait AccountAccess: Send + Sync {
    /// Loads authorization metadata without exposing email or password fields.
    async fn load(&self, id: AccountId) -> Result<Option<AccountAccessInfo>, AppError>;
}

/// Classification returned by the idempotency reservation port.
#[derive(Clone, Debug, Eq, PartialEq)]
pub enum IdemBegin {
    /// This request owns a new reservation and may run its handler.
    Fresh,
    /// A prior successful or deterministic response is available for replay.
    Replay {
        status: u16,
        content_type: Option<String>,
        body: Vec<u8>,
    },
    /// An equivalent request is currently running.
    InProgress,
    /// The key is already bound to a different method, path, or body.
    Mismatch,
}

/// Tenant-scoped reservation and response store for HTTP idempotency.
#[async_trait]
pub trait IdempotencyPort: Send + Sync {
    /// Reserves a normalized key or returns its existing state.
    async fn begin(
        &self,
        account: AccountId,
        key: &str,
        method: &str,
        path: &str,
        request_hash: [u8; 32],
        ttl_seconds: i64,
    ) -> Result<IdemBegin, AppError>;

    /// Stores the response metadata for a deterministic response.
    async fn finish(
        &self,
        account: AccountId,
        key: &str,
        status: u16,
        content_type: Option<String>,
        body: Option<Vec<u8>>,
    ) -> Result<(), AppError>;

    /// Releases a reservation so a later retry can run the handler.
    async fn release(&self, account: AccountId, key: &str) -> Result<(), AppError>;
}

#[cfg(test)]
mod tests {
    #![allow(
        clippy::expect_used,
        clippy::unwrap_used,
        reason = "tests exercise the port contract"
    )]

    use super::*;
    use kinevo_kernel::errors::ErrorCode;

    #[test]
    fn d_40_secret_name_registry() {
        let name = SecretName::parse("identity.totp_seed")
            .expect("the registered identity secret should be accepted");
        assert_eq!(name.as_str(), "identity.totp_seed");

        let error =
            SecretName::parse("foo.bar").expect_err("unregistered secret names must be rejected");
        assert_eq!(error.code, ErrorCode::SecretNameUnknown);
    }
}
