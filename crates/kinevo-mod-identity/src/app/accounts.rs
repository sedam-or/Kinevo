//! Transactional account creation shared by registration and the setup wizard.

use std::sync::Arc;

use kinevo_db::{
    Tx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    outbox::{self, SubscriberRegistry},
};
use kinevo_kernel::{
    errors::AppError,
    event::{EventEnvelope, EventKind, EventPayload},
    id::{EventId, IdGen, TokenGen},
};
use kinevo_ports::{
    module::{AccountProvisioner, ProvisionedAccount, run_provisioners},
    platform::{AccessClaims, AccountCreation, CreatedAccount, NewAccount},
};

use crate::{
    domain::session::SessionPolicy,
    infra::{accounts, sessions},
};

/// Creates accounts and all account-scoped initial state in the caller's transaction.
pub struct AccountCreator {
    ids: Arc<dyn IdGen>,
    tokens: Arc<dyn TokenGen>,
    provisioners: Vec<Arc<dyn AccountProvisioner<Tx>>>,
    subscribers: SubscriberRegistry,
    audit: AuditWriter,
}

impl AccountCreator {
    /// Keeps composition-root provisioner order for every account creation.
    #[must_use]
    pub fn new(
        ids: Arc<dyn IdGen>,
        tokens: Arc<dyn TokenGen>,
        provisioners: Vec<Arc<dyn AccountProvisioner<Tx>>>,
        subscribers: SubscriberRegistry,
    ) -> Self {
        Self {
            audit: AuditWriter::new(ids.clone()),
            ids,
            tokens,
            provisioners,
            subscribers,
        }
    }
}

#[async_trait::async_trait]
impl AccountCreation<Tx> for AccountCreator {
    #[allow(
        clippy::too_many_lines,
        reason = "account, provisioners, first session, audit, and outbox must share one transaction"
    )]
    async fn create(&self, tx: &mut Tx, input: NewAccount) -> Result<CreatedAccount, AppError> {
        if tx.account_id() != input.id {
            return Err(AppError::new(
                kinevo_kernel::errors::ErrorCode::InternalError,
            ));
        }

        let now = input.session.now;
        let profile = accounts::create(tx, &input, now).await?;
        let provisioned = ProvisionedAccount {
            id: input.id,
            display_name: input.display_name,
            timezone: input.timezone,
            locale: input.locale,
            is_instance_admin: input.is_instance_admin,
        };
        let provisioner_order = self
            .provisioners
            .iter()
            .map(Arc::as_ref)
            .collect::<Vec<_>>();
        run_provisioners(&provisioner_order, tx, &provisioned).await?;

        let _client = input.client;
        let session = sessions::create_family_with_token(
            tx,
            self.ids.as_ref(),
            self.tokens.as_ref(),
            now,
            input.id,
            input.device_label.as_deref(),
            false,
            SessionPolicy {
                access_ttl: input.session.access_ttl,
                idle_ttl: input.session.idle_ttl,
                absolute_ttl: input.session.absolute_ttl,
                grace: input.session.grace,
            },
        )
        .await?;
        let expires_at = now
            .checked_add_seconds(i64::from(input.session.access_ttl))
            .ok_or_else(|| AppError::new(kinevo_kernel::errors::ErrorCode::InternalError))?;

        self.audit
            .record(
                tx,
                AuditEntry {
                    account_id: Some(input.id),
                    actor_id: None,
                    action: AuditAction::AuthLogin,
                    target_type: Some(AuditTarget::ACCOUNT),
                    target_id: Some(input.id.to_string()),
                    details: kinevo_kernel::errors::Details::new(),
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
                id: EventId::from_uuid(self.ids.new_uuid_v7()),
                kind: EventKind::AccountRegistered,
                v: 1,
                account_id: Some(input.id),
                occurred_at: now,
                payload: EventPayload::new().with_id("account_id", &input.id),
            },
            &self.subscribers,
        )
        .await?;

        Ok(CreatedAccount {
            profile,
            refresh_token: session.refresh_token,
            access_claims: AccessClaims {
                account_id: input.id,
                family_id: session.family_id,
                issued_at: now,
                expires_at,
                mfa: false,
            },
            expires_in: input.session.access_ttl,
            refresh_cookie_max_age: input.session.absolute_ttl,
        })
    }
}
