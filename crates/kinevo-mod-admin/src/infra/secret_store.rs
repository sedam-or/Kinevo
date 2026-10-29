//! AEAD-backed storage for the D-40 registered secrets.

use std::{collections::BTreeMap, sync::Arc};

use kinevo_db::{
    Db, SystemTx, Tx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    outbox::{self, SubscriberRegistry},
};
use kinevo_kernel::{
    crypto::{KeyLabel, MasterKey, Sealed, derive_subkey, open, seal},
    errors::{AppError, DetailValue, Details, ErrorCode, FieldError},
    event::{EventEnumValue, EventEnvelope, EventKind, EventPayload},
    id::{AccountId, Event, IdGen, TokenGen, new_id},
    secret::Secret,
    time::Clock,
    validate::text::SecretText,
};
use kinevo_ports::platform::{SecretMask, SecretName, SecretScope, SecretStore};
use sqlx::{PgConnection, Row, types::Uuid};

use crate::domain::secrets::{SecretNameKind, aad, parse_for_scope};

/// PostgreSQL implementation of the shared secret port.
pub struct AdminSecretStore {
    db: Db,
    keys: BTreeMap<u16, Secret<[u8; 32]>>,
    key_id: u16,
    ids: Arc<dyn IdGen>,
    tokens: Arc<dyn TokenGen>,
    clock: Arc<dyn Clock>,
    subscribers: SubscriberRegistry,
}

impl AdminSecretStore {
    /// Creates a store with the current key and its database key id.
    #[must_use]
    pub fn new(
        db: Db,
        key: Secret<[u8; 32]>,
        key_id: u16,
        ids: Arc<dyn IdGen>,
        tokens: Arc<dyn TokenGen>,
        clock: Arc<dyn Clock>,
    ) -> Self {
        Self {
            db,
            keys: BTreeMap::from([(key_id, key)]),
            key_id,
            ids,
            tokens,
            clock,
            subscribers: SubscriberRegistry::default(),
        }
    }

    /// Replaces the default outbox subscribers for secret changes.
    #[must_use]
    pub fn with_subscribers(mut self, subscribers: SubscriberRegistry) -> Self {
        self.subscribers = subscribers;
        self
    }

    /// Adds one old key id used to read rows during a resumable rotation.
    #[must_use]
    pub fn with_fallback_key(mut self, key_id: u16, key: Secret<[u8; 32]>) -> Self {
        if key_id != self.key_id {
            self.keys.insert(key_id, key);
        }
        self
    }

    pub(crate) fn db(&self) -> &Db {
        &self.db
    }

    pub(crate) async fn put_in_tx(
        &self,
        tx: &mut Tx,
        scope: SecretScope,
        name: SecretName,
        value: &SecretText,
    ) -> Result<(), AppError> {
        let name = parse_for_scope(name.as_str(), scope)?;
        if let SecretScope::Account(account) = scope
            && tx.account_id() != account
        {
            return Err(AppError::new(ErrorCode::Forbidden));
        }
        let key_id =
            i16::try_from(self.key_id).map_err(|_| AppError::new(ErrorCode::ValidationFailed))?;
        let now = self.clock.now().to_rfc3339();
        let sealed = seal(
            self.write_key()?,
            value.expose().as_bytes(),
            &aad(name, scope),
            self.tokens.as_ref(),
        );
        sqlx::query(
            "INSERT INTO public.secrets \
             (id, account_id, name, ciphertext, nonce, key_id, last4, created_at, updated_at) \
             VALUES ($1, $2, $3, $4, $5, $6, $7, $8::timestamptz, $8::timestamptz) \
             ON CONFLICT (account_id, name) DO UPDATE SET \
               ciphertext=EXCLUDED.ciphertext, nonce=EXCLUDED.nonce, key_id=EXCLUDED.key_id, \
               last4=EXCLUDED.last4, updated_at=EXCLUDED.updated_at",
        )
        .bind(self.ids.new_uuid_v7())
        .bind(account_uuid(scope))
        .bind(name.as_str())
        .bind(sealed.ciphertext)
        .bind(sealed.nonce.as_slice())
        .bind(key_id)
        .bind(Some(last4(value.expose())))
        .bind(now)
        .execute(tx.conn())
        .await
        .map_err(database_error)?;
        Ok(())
    }

    pub(crate) async fn delete_in_tx(
        &self,
        tx: &mut Tx,
        scope: SecretScope,
        name: SecretName,
    ) -> Result<bool, AppError> {
        let name = parse_for_scope(name.as_str(), scope)?;
        if let SecretScope::Account(account) = scope
            && tx.account_id() != account
        {
            return Err(AppError::new(ErrorCode::Forbidden));
        }
        let deleted = sqlx::query(
            "DELETE FROM public.secrets WHERE account_id IS NOT DISTINCT FROM $1 AND name=$2",
        )
        .bind(account_uuid(scope))
        .bind(name.as_str())
        .execute(tx.conn())
        .await
        .map_err(database_error)?
        .rows_affected();
        Ok(deleted != 0)
    }

    pub(crate) async fn record_change_in_tx(
        &self,
        tx: &mut Tx,
        actor: AccountId,
        scope: SecretScope,
        name: SecretName,
        action: AuditAction,
        subscribers: &SubscriberRegistry,
    ) -> Result<(), AppError> {
        let kind = SecretNameKind::from_registered(name)
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let now = self.clock.now();
        AuditWriter::new(Arc::clone(&self.ids))
            .record(
                tx,
                AuditEntry {
                    account_id: match scope {
                        SecretScope::Instance => None,
                        SecretScope::Account(account) => Some(account),
                    },
                    actor_id: Some(*actor.as_uuid()),
                    action,
                    target_type: Some(AuditTarget::from_static("secret")),
                    target_id: Some(name.as_str().to_owned()),
                    details: Details::new().with_detail("name", DetailValue::Str(name.as_str())),
                    request_id: None,
                    occurred_at: now,
                },
            )
            .await?;
        outbox::emit(
            tx,
            self.ids.as_ref(),
            now,
            EventEnvelope {
                id: new_id::<Event>(self.ids.as_ref()),
                kind: EventKind::SecretChanged,
                v: 1,
                account_id: match scope {
                    SecretScope::Instance => None,
                    SecretScope::Account(account) => Some(account),
                },
                occurred_at: now,
                payload: EventPayload::new()
                    .with_enum("name", EventEnumValue::new(kind.event_name())),
            },
            subscribers,
        )
        .await?;
        Ok(())
    }

    fn write_key(&self) -> Result<&Secret<[u8; 32]>, AppError> {
        self.key(
            i16::try_from(self.key_id).map_err(|_| AppError::new(ErrorCode::ValidationFailed))?,
        )
    }

    fn key(&self, key_id: i16) -> Result<&Secret<[u8; 32]>, AppError> {
        u16::try_from(key_id)
            .ok()
            .and_then(|key_id| self.keys.get(&key_id))
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))
    }

    async fn instance_admin(&self) -> Result<AccountId, AppError> {
        let mut tx = SystemTx::begin(&self.db).await?;
        let id = sqlx::query_scalar::<_, Uuid>(
            "SELECT id FROM public.accounts \
             WHERE is_instance_admin AND status='active' ORDER BY id LIMIT 1",
        )
        .fetch_optional(tx.conn())
        .await
        .map_err(database_error)?;
        tx.commit().await?;
        id.map(AccountId::from_uuid)
            .ok_or_else(|| AppError::new(ErrorCode::Forbidden))
    }

    async fn read_conn(
        &self,
        conn: &mut PgConnection,
        scope: SecretScope,
        name: SecretName,
    ) -> Result<Option<Secret<String>>, AppError> {
        let name = parse_for_scope(name.as_str(), scope)?;
        let row = sqlx::query(
            "SELECT ciphertext, nonce, key_id FROM public.secrets \
             WHERE account_id IS NOT DISTINCT FROM $1 AND name=$2",
        )
        .bind(account_uuid(scope))
        .bind(name.as_str())
        .fetch_optional(conn)
        .await
        .map_err(database_error)?;
        let Some(row) = row else {
            return Ok(None);
        };
        let ciphertext: Vec<u8> = row.try_get("ciphertext").map_err(database_error)?;
        let nonce: Vec<u8> = row.try_get("nonce").map_err(database_error)?;
        let key_id: i16 = row.try_get("key_id").map_err(database_error)?;
        let nonce: [u8; 24] = nonce
            .try_into()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        let opened = open(
            self.key(key_id)?,
            &Sealed { nonce, ciphertext },
            &aad(name, scope),
        )
        .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        let value = String::from_utf8(opened.expose().clone())
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        Ok(Some(Secret::new(value)))
    }
}

#[async_trait::async_trait]
impl SecretStore for AdminSecretStore {
    async fn put(
        &self,
        scope: SecretScope,
        name: SecretName,
        value: Secret<String>,
    ) -> Result<(), AppError> {
        let value = validate_secret_text(value.expose().clone())?;
        match scope {
            SecretScope::Account(account) => {
                let mut tx = Tx::begin(&self.db, account).await?;
                self.put_in_tx(&mut tx, scope, name, &value).await?;
                self.record_change_in_tx(
                    &mut tx,
                    account,
                    scope,
                    name,
                    AuditAction::SecretSet,
                    &self.subscribers,
                )
                .await?;
                tx.commit().await
            }
            SecretScope::Instance => {
                let actor = self.instance_admin().await?;
                let mut tx = Tx::begin_admin(&self.db, actor).await?;
                self.put_in_tx(&mut tx, scope, name, &value).await?;
                self.record_change_in_tx(
                    &mut tx,
                    actor,
                    scope,
                    name,
                    AuditAction::SecretSet,
                    &self.subscribers,
                )
                .await?;
                tx.commit().await
            }
        }
    }

    async fn get(
        &self,
        scope: SecretScope,
        name: SecretName,
    ) -> Result<Option<Secret<String>>, AppError> {
        match scope {
            SecretScope::Instance => {
                let mut tx = SystemTx::begin(&self.db).await?;
                let value = self.read_conn(tx.conn(), scope, name).await?;
                tx.commit().await?;
                Ok(value)
            }
            SecretScope::Account(account) => {
                let mut tx = Tx::begin(&self.db, account).await?;
                let value = self.read_conn(tx.conn(), scope, name).await?;
                tx.commit().await?;
                Ok(value)
            }
        }
    }

    async fn delete(&self, scope: SecretScope, name: SecretName) -> Result<bool, AppError> {
        match scope {
            SecretScope::Instance => {
                let actor = self.instance_admin().await?;
                let mut tx = Tx::begin_admin(&self.db, actor).await?;
                let deleted = self.delete_in_tx(&mut tx, scope, name).await?;
                if deleted {
                    self.record_change_in_tx(
                        &mut tx,
                        actor,
                        scope,
                        name,
                        AuditAction::SecretDeleted,
                        &self.subscribers,
                    )
                    .await?;
                }
                tx.commit().await?;
                Ok(deleted)
            }
            SecretScope::Account(account) => {
                let mut tx = Tx::begin(&self.db, account).await?;
                let deleted = self.delete_in_tx(&mut tx, scope, name).await?;
                if deleted {
                    self.record_change_in_tx(
                        &mut tx,
                        account,
                        scope,
                        name,
                        AuditAction::SecretDeleted,
                        &self.subscribers,
                    )
                    .await?;
                }
                tx.commit().await?;
                Ok(deleted)
            }
        }
    }

    async fn mask(&self, scope: SecretScope, name: SecretName) -> Result<SecretMask, AppError> {
        let name = parse_for_scope(name.as_str(), scope)?;
        match scope {
            SecretScope::Instance => {
                let mut tx = SystemTx::begin(&self.db).await?;
                let mask = mask_conn(tx.conn(), scope, name).await?;
                tx.commit().await?;
                Ok(mask)
            }
            SecretScope::Account(account) => {
                let mut tx = Tx::begin(&self.db, account).await?;
                let mask = mask_conn(tx.conn(), scope, name).await?;
                tx.commit().await?;
                Ok(mask)
            }
        }
    }
}

async fn mask_conn(
    conn: &mut PgConnection,
    scope: SecretScope,
    name: SecretName,
) -> Result<SecretMask, AppError> {
    let last4 = sqlx::query_scalar::<_, Option<String>>(
        "SELECT last4 FROM public.secrets \
         WHERE account_id IS NOT DISTINCT FROM $1 AND name=$2",
    )
    .bind(account_uuid(scope))
    .bind(name.as_str())
    .fetch_optional(conn)
    .await
    .map_err(database_error)?
    .flatten();
    Ok(SecretMask {
        is_set: last4.is_some(),
        last4,
    })
}

fn account_uuid(scope: SecretScope) -> Option<Uuid> {
    match scope {
        SecretScope::Instance => None,
        SecretScope::Account(account) => Some(*account.as_uuid()),
    }
}

fn last4(value: &str) -> String {
    let chars = value.chars().rev().take(4).collect::<Vec<_>>();
    chars.into_iter().rev().collect()
}

fn validate_secret_text(value: String) -> Result<SecretText, AppError> {
    SecretText::parse(value).map_err(|code| {
        AppError::validation(vec![FieldError {
            path: "value".to_owned(),
            code,
            details: Details::default(),
        }])
    })
}

fn database_error(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}

/// Re-encrypts all rows per account RLS scope, then the instance scope.
pub async fn rotate_master_key(
    db: &Db,
    old: &MasterKey,
    new: &MasterKey,
    new_key_id: u16,
) -> Result<u64, AppError> {
    let new_key_sql =
        i16::try_from(new_key_id).map_err(|_| AppError::new(ErrorCode::ValidationFailed))?;
    let old_key = derive_subkey(old, KeyLabel::Secrets, 1);
    let new_key = derive_subkey(new, KeyLabel::Secrets, 1);

    let mut system = SystemTx::begin(db).await?;
    let accounts = sqlx::query_scalar::<_, Uuid>("SELECT id FROM public.accounts ORDER BY id")
        .fetch_all(system.conn())
        .await
        .map_err(database_error)?;
    system.commit().await?;

    let mut changed = 0_u64;
    for account_id in accounts {
        let account = AccountId::from_uuid(account_id);
        let mut tx = Tx::begin(db, account).await?;
        match rotate_scope(tx.conn(), Some(account_id), &old_key, &new_key, new_key_sql).await {
            Ok(count) => {
                tx.commit().await?;
                changed = changed.saturating_add(count);
            }
            Err(error) => {
                let _ = tx.rollback().await;
                return Err(error);
            }
        }
    }

    let mut tx = Tx::begin_admin(db, AccountId::from_uuid(Uuid::nil())).await?;
    match rotate_scope(tx.conn(), None, &old_key, &new_key, new_key_sql).await {
        Ok(count) => {
            tx.commit().await?;
            changed = changed.saturating_add(count);
        }
        Err(error) => {
            let _ = tx.rollback().await;
            return Err(error);
        }
    }
    Ok(changed)
}

async fn rotate_scope(
    conn: &mut PgConnection,
    account_id: Option<Uuid>,
    old_key: &Secret<[u8; 32]>,
    new_key: &Secret<[u8; 32]>,
    new_key_id: i16,
) -> Result<u64, AppError> {
    let rows = sqlx::query(
        "SELECT id, name, ciphertext, nonce FROM public.secrets \
         WHERE account_id IS NOT DISTINCT FROM $1 AND key_id <> $2 ORDER BY name FOR UPDATE",
    )
    .bind(account_id)
    .bind(new_key_id)
    .fetch_all(&mut *conn)
    .await
    .map_err(database_error)?;
    let count = u64::try_from(rows.len()).map_err(|_| AppError::new(ErrorCode::InternalError))?;

    for row in rows {
        let id: Uuid = row.try_get("id").map_err(database_error)?;
        let raw_name: String = row.try_get("name").map_err(database_error)?;
        let name = SecretName::parse(&raw_name)?;
        let ciphertext: Vec<u8> = row.try_get("ciphertext").map_err(database_error)?;
        let nonce: Vec<u8> = row.try_get("nonce").map_err(database_error)?;
        let nonce: [u8; 24] = nonce
            .try_into()
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        let scope = account_id.map_or(SecretScope::Instance, |uuid| {
            SecretScope::Account(AccountId::from_uuid(uuid))
        });
        let opened = open(old_key, &Sealed { nonce, ciphertext }, &aad(name, scope))
            .map_err(|_| AppError::new(ErrorCode::InternalError))?;
        let sealed = seal(new_key, opened.expose(), &aad(name, scope), &OsTokenGen);
        sqlx::query("UPDATE public.secrets SET ciphertext=$1, nonce=$2, key_id=$3 WHERE id=$4")
            .bind(sealed.ciphertext)
            .bind(sealed.nonce.as_slice())
            .bind(new_key_id)
            .bind(id)
            .execute(&mut *conn)
            .await
            .map_err(database_error)?;
    }
    Ok(count)
}

struct OsTokenGen;

impl TokenGen for OsTokenGen {
    fn fill(&self, buffer: &mut [u8]) {
        if getrandom::fill(buffer).is_err() {
            std::process::abort();
        }
    }
}
