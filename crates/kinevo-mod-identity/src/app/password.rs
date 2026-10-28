//! Authenticated password-change use case.

use std::sync::Arc;

use kinevo_db::{
    Db,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    outbox::{self, SubscriberRegistry},
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    event::{EventEnvelope, EventKind, EventPayload},
    id::{AccountId, EventId, FamilyId, IdGen},
    policy::registry::identity as policy,
    secret::Secret,
    time::Clock,
    validate::Collector,
};
use kinevo_ports::platform::PolicyReader;

use crate::{
    app::login::PasswordWork,
    domain::{
        password::{CommonPasswords, PasswordPolicy, PlainPassword},
        session::RevocationReason,
    },
    infra::{accounts, hasher::VerifyOutcome, sessions},
};

/// Coordinates verification, password policy, session revocation, audit and outbox writes.
pub struct PasswordService {
    db: Db,
    passwords: Arc<dyn PasswordWork>,
    policies: Arc<dyn PolicyReader>,
    ids: Arc<dyn IdGen>,
    clock: Arc<dyn Clock>,
    subscribers: SubscriberRegistry,
}

impl PasswordService {
    #[allow(clippy::too_many_arguments)]
    #[must_use]
    pub fn new(
        db: Db,
        passwords: Arc<dyn PasswordWork>,
        policies: Arc<dyn PolicyReader>,
        ids: Arc<dyn IdGen>,
        clock: Arc<dyn Clock>,
        subscribers: SubscriberRegistry,
    ) -> Self {
        Self {
            db,
            passwords,
            policies,
            ids,
            clock,
            subscribers,
        }
    }

    /// Verifies the current credential and commits the new credential with revocations.
    #[allow(
        clippy::too_many_lines,
        reason = "password, family, audit and outbox updates must commit atomically"
    )]
    pub async fn change_password(
        &self,
        account: AccountId,
        current: Secret<String>,
        new: Secret<String>,
        current_family: FamilyId,
    ) -> Result<(), AppError> {
        let now = self.clock.now();
        let mut tx = kinevo_db::Tx::begin(&self.db, account).await?;
        let stored_hash = accounts::password_hash(&mut tx, account).await?;
        if !matches!(
            self.passwords.verify(&current, &stored_hash).await,
            VerifyOutcome::Ok | VerifyOutcome::OkNeedsRehash
        ) {
            return Err(AppError::new(ErrorCode::CurrentPasswordIncorrect));
        }

        let snapshot = self.policies.snapshot_values(Some(account), now).await?;
        let minimum = snapshot
            .get(&policy::PASSWORD_MIN_LENGTH)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        let minimum =
            usize::try_from(minimum).map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let mut errors = Collector::new();
        let password = errors.check(
            "new_password",
            PlainPassword::parse(
                new,
                &PasswordPolicy::new(minimum),
                &CommonPasswords::embedded(),
            ),
        );
        errors.finish()?;
        let password = password.ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let candidate = Secret::new(password.expose().to_owned());
        let new_hash = self.passwords.hash(&candidate).await?;

        accounts::update_password(&mut tx, account, &new_hash, now).await?;
        sessions::revoke_all_for_account(
            &mut tx,
            account,
            RevocationReason::PasswordChanged,
            now,
            Some(current_family),
        )
        .await?;
        AuditWriter::new(Arc::clone(&self.ids))
            .record(
                &mut tx,
                AuditEntry {
                    account_id: Some(account),
                    actor_id: Some(*account.as_uuid()),
                    action: AuditAction::AuthPasswordChanged,
                    target_type: Some(AuditTarget::ACCOUNT),
                    target_id: Some(account.to_string()),
                    details: kinevo_kernel::errors::Details::new(),
                    request_id: None,
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
                kind: EventKind::PasswordChanged,
                v: 1,
                account_id: Some(account),
                occurred_at: now,
                payload: EventPayload::new().with_id("account_id", &account),
            },
            &self.subscribers,
        )
        .await?;
        tx.commit().await
    }
}
