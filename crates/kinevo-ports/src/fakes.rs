//! In-memory port adapters for deterministic tests.

// SPEC: CQ-12

use crate::{
    module::ModuleKey,
    platform::{
        AccountAccess, AccountAccessInfo, IdemBegin, IdempotencyPort, InstanceInfo, InstanceState,
        ModuleSwitches, PolicyReader, ResolvedPolicies, SecretMask, SecretName, SecretScope,
        SecretStore, last4, validate_secret_scope,
    },
};
use async_trait::async_trait;
use kinevo_kernel::{
    crypto::ct_eq,
    errors::{AppError, ErrorCode},
    id::AccountId,
    secret::Secret,
    time::Instant,
};
use std::{
    collections::{BTreeMap, BTreeSet},
    sync::{Mutex, RwLock},
};

/// In-memory snapshot reader for use-case tests.
#[derive(Default)]
pub struct FakePolicyReader(RwLock<ResolvedPolicies>);

impl FakePolicyReader {
    /// Creates a reader with the supplied deterministic values.
    #[must_use]
    pub fn new(values: ResolvedPolicies) -> Self {
        Self(RwLock::new(values))
    }

    /// Replaces the complete visible snapshot.
    pub fn replace(&self, values: ResolvedPolicies) -> Result<(), AppError> {
        let mut current = self
            .0
            .write()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        *current = values;
        Ok(())
    }
}

#[async_trait]
impl PolicyReader for FakePolicyReader {
    async fn snapshot_values(
        &self,
        _account: Option<AccountId>,
        _at: Instant,
    ) -> Result<ResolvedPolicies, AppError> {
        self.0
            .read()
            .map(|values| values.clone())
            .map_err(|_| AppError::new(ErrorCode::InternalError))
    }
}

/// In-memory module enabled-state cache for tests.
#[derive(Default)]
pub struct FakeModuleSwitches(RwLock<BTreeSet<ModuleKey>>);

impl FakeModuleSwitches {
    /// Creates a switch cache with the supplied enabled modules.
    #[must_use]
    pub fn new(enabled: impl IntoIterator<Item = ModuleKey>) -> Self {
        Self(RwLock::new(enabled.into_iter().collect()))
    }

    /// Sets one module's cached state.
    pub fn set_enabled(&self, key: ModuleKey, enabled: bool) -> Result<(), AppError> {
        let mut current = self
            .0
            .write()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        if enabled {
            current.insert(key);
        } else {
            current.remove(&key);
        }
        Ok(())
    }
}

#[async_trait]
impl ModuleSwitches for FakeModuleSwitches {
    fn is_enabled(&self, key: &ModuleKey) -> bool {
        self.0.read().is_ok_and(|enabled| enabled.contains(key))
    }

    async fn refresh(&self) {}
}

/// Mutable in-memory instance state for setup and HTTP tests.
pub struct FakeInstanceState(RwLock<InstanceInfo>);

impl FakeInstanceState {
    /// Creates a state adapter with an initial snapshot.
    #[must_use]
    pub fn new(info: InstanceInfo) -> Self {
        Self(RwLock::new(info))
    }

    /// Replaces the complete instance snapshot.
    pub fn replace(&self, info: InstanceInfo) -> Result<(), AppError> {
        let mut current = self
            .0
            .write()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        *current = info;
        Ok(())
    }
}

#[async_trait]
impl InstanceState for FakeInstanceState {
    async fn get(&self) -> Result<InstanceInfo, AppError> {
        self.0
            .read()
            .map(|info| info.clone())
            .map_err(|_| AppError::new(ErrorCode::InternalError))
    }
}

/// In-memory secret adapter that keeps values inside redacting wrappers.
#[derive(Default)]
pub struct FakeSecretStore(Mutex<BTreeMap<(SecretScope, SecretName), Secret<String>>>);

#[async_trait]
impl SecretStore for FakeSecretStore {
    async fn put(
        &self,
        scope: SecretScope,
        name: SecretName,
        value: Secret<String>,
    ) -> Result<(), AppError> {
        validate_secret_scope(scope, name)?;
        let mut values = self
            .0
            .lock()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        values.insert((scope, name), Secret::new(value.expose().clone()));
        Ok(())
    }

    async fn get(
        &self,
        scope: SecretScope,
        name: SecretName,
    ) -> Result<Option<Secret<String>>, AppError> {
        validate_secret_scope(scope, name)?;
        let values = self
            .0
            .lock()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        Ok(values
            .get(&(scope, name))
            .map(|value| Secret::new(value.expose().clone())))
    }

    async fn delete(&self, scope: SecretScope, name: SecretName) -> Result<bool, AppError> {
        validate_secret_scope(scope, name)?;
        let mut values = self
            .0
            .lock()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        Ok(values.remove(&(scope, name)).is_some())
    }

    async fn mask(&self, scope: SecretScope, name: SecretName) -> Result<SecretMask, AppError> {
        validate_secret_scope(scope, name)?;
        let values = self
            .0
            .lock()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        Ok(match values.get(&(scope, name)) {
            Some(value) => SecretMask {
                is_set: true,
                last4: Some(last4(value.expose())),
            },
            None => SecretMask {
                is_set: false,
                last4: None,
            },
        })
    }
}

/// In-memory account authorization lookup for HTTP tests.
#[derive(Default)]
pub struct FakeAccountAccess(RwLock<BTreeMap<AccountId, AccountAccessInfo>>);

impl FakeAccountAccess {
    /// Adds or replaces the minimal authorization row for one account.
    pub fn insert(&self, account: AccountAccessInfo) -> Result<(), AppError> {
        let mut accounts = self
            .0
            .write()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        accounts.insert(account.id, account);
        Ok(())
    }
}

#[async_trait]
impl AccountAccess for FakeAccountAccess {
    async fn load(&self, id: AccountId) -> Result<Option<AccountAccessInfo>, AppError> {
        let accounts = self
            .0
            .read()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        Ok(accounts.get(&id).copied())
    }
}

#[derive(Clone)]
struct FakeIdempotencyEntry {
    method: String,
    path: String,
    request_hash: [u8; 32],
    response: Option<FakeIdempotencyResponse>,
}

#[derive(Clone)]
struct FakeIdempotencyResponse {
    status: u16,
    content_type: Option<String>,
    body: Vec<u8>,
}

/// In-memory account-scoped idempotency store for HTTP acceptance tests.
#[derive(Default)]
pub struct FakeIdempotency(Mutex<BTreeMap<(AccountId, String), FakeIdempotencyEntry>>);

#[async_trait]
impl IdempotencyPort for FakeIdempotency {
    async fn begin(
        &self,
        account: AccountId,
        key: &str,
        method: &str,
        path: &str,
        request_hash: [u8; 32],
        _ttl_seconds: i64,
    ) -> Result<IdemBegin, AppError> {
        let mut entries = self
            .0
            .lock()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        let entry_key = (account, key.to_owned());
        let Some(entry) = entries.get(&entry_key) else {
            entries.insert(
                entry_key,
                FakeIdempotencyEntry {
                    method: method.to_ascii_uppercase(),
                    path: path.to_owned(),
                    request_hash,
                    response: None,
                },
            );
            return Ok(IdemBegin::Fresh);
        };
        if entry.method != method.to_ascii_uppercase()
            || entry.path != path
            || !ct_eq(&entry.request_hash, &request_hash)
        {
            return Ok(IdemBegin::Mismatch);
        }
        Ok(match &entry.response {
            Some(response) => IdemBegin::Replay {
                status: response.status,
                content_type: response.content_type.clone(),
                body: response.body.clone(),
            },
            None => IdemBegin::InProgress,
        })
    }

    async fn finish(
        &self,
        account: AccountId,
        key: &str,
        status: u16,
        content_type: Option<String>,
        body: Option<Vec<u8>>,
    ) -> Result<(), AppError> {
        let mut entries = self
            .0
            .lock()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        if status >= 500 || status == 429 {
            entries.remove(&(account, key.to_owned()));
            return Ok(());
        }
        let entry = entries
            .get_mut(&(account, key.to_owned()))
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        entry.response = Some(FakeIdempotencyResponse {
            status,
            content_type,
            body: body.unwrap_or_default(),
        });
        Ok(())
    }

    async fn release(&self, account: AccountId, key: &str) -> Result<(), AppError> {
        let mut entries = self
            .0
            .lock()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        entries.remove(&(account, key.to_owned()));
        Ok(())
    }
}
