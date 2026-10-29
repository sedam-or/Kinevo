//! Instance secret management, audit, and outbox use cases.

use std::sync::Arc;

use kinevo_db::{Tx, audit::AuditAction, outbox::SubscriberRegistry};
use kinevo_kernel::{
    errors::{AppError, Details, ErrorCode, FieldError},
    id::AccountId,
    secret::Secret,
    validate::text::SecretText,
};
use kinevo_ports::platform::{SecretName, SecretScope, SecretStore};
use serde::Serialize;

use crate::{
    domain::secrets::{instance_names, parse_for_scope},
    infra::secret_store::AdminSecretStore,
};

/// A registry entry with no secret material.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
pub struct SecretView {
    pub name: &'static str,
    pub is_set: bool,
    pub last4: Option<String>,
}

/// Admin use cases for listing and changing instance secrets.
pub struct SecretsService {
    store: Arc<AdminSecretStore>,
    subscribers: SubscriberRegistry,
}

impl SecretsService {
    /// Creates the service with the module's injected IDs, clock and subscribers.
    #[must_use]
    pub fn new(store: Arc<AdminSecretStore>, subscribers: SubscriberRegistry) -> Self {
        Self { store, subscribers }
    }

    /// Returns the four instance registry entries with only presence and mask data.
    pub async fn list(&self) -> Result<Vec<SecretView>, AppError> {
        let mut items = Vec::with_capacity(instance_names().len());
        for kind in instance_names() {
            let name = SecretName::parse(kind.as_str())?;
            let mask = self.store.mask(SecretScope::Instance, name).await?;
            items.push(SecretView {
                name: kind.as_str(),
                is_set: mask.is_set,
                last4: mask.last4.map(|last| format!("••••{last}")),
            });
        }
        Ok(items)
    }

    /// Stores one instance secret and appends its safe audit and outbox records atomically.
    pub async fn put(
        &self,
        actor: AccountId,
        raw_name: &str,
        value: Secret<String>,
    ) -> Result<(), AppError> {
        let name = parse_for_scope(raw_name, SecretScope::Instance)?;
        let text = validate_secret_text(value.expose().clone())?;
        let mut tx = Tx::begin_admin(self.store.db(), actor).await?;
        self.store
            .put_in_tx(&mut tx, SecretScope::Instance, name, &text)
            .await?;
        self.store
            .record_change_in_tx(
                &mut tx,
                actor,
                SecretScope::Instance,
                name,
                AuditAction::SecretSet,
                &self.subscribers,
            )
            .await?;
        tx.commit().await
    }

    /// Deletes one instance secret, returning `not_found` when no row existed.
    pub async fn delete(&self, actor: AccountId, raw_name: &str) -> Result<(), AppError> {
        let name = parse_for_scope(raw_name, SecretScope::Instance)?;
        let mut tx = Tx::begin_admin(self.store.db(), actor).await?;
        if !self
            .store
            .delete_in_tx(&mut tx, SecretScope::Instance, name)
            .await?
        {
            let _ = tx.rollback().await;
            return Err(AppError::new(ErrorCode::NotFound));
        }
        self.store
            .record_change_in_tx(
                &mut tx,
                actor,
                SecretScope::Instance,
                name,
                AuditAction::SecretDeleted,
                &self.subscribers,
            )
            .await?;
        tx.commit().await
    }
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
