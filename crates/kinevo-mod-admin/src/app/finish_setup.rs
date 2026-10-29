//! First-account provisioning and instance setup completion.

use std::{collections::HashMap, sync::Arc};

use async_trait::async_trait;
use kinevo_db::{
    Db, Tx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    outbox::{self, SubscriberRegistry},
};
use kinevo_http::token::{Claims, TokenCodec};
use kinevo_kernel::{
    crypto::hash_token,
    errors::{AppError, Details, ErrorCode, FieldCode, FieldError},
    event::{EventEnvelope, EventKind, EventPayload},
    id::{AccountId, EventId, IdGen},
    policy::{PolicySpec, PolicyValue, Scope, registry::all_specs, validate_value},
    secret::Secret,
    time::{Clock, Instant},
    validate::{
        Collector, IdemKey,
        net::Email,
        text::ShortText,
        tz::{IanaTz, Locale},
    },
};
use kinevo_ports::{
    module::{ModuleDescriptor, ModuleKind, validate_graph},
    platform::{AccountCreation, AccountSessionSettings, Client, NewAccount},
};
use serde_json::Value;
use tokio::sync::Mutex;

use crate::{
    app::templates::TemplateRegistry,
    infra::{
        instance_state::CachedInstanceState,
        module_repo::{ModuleRepository, ModuleSwitchUpdate},
        policy_repo::{PolicyRepository, PolicyVersionInsert},
    },
};

/// Validates the password policy and hashes a normalized password before a transaction begins.
#[async_trait]
pub trait SetupPasswordProcessor: Send + Sync {
    /// Returns a field error for invalid input or the PHC hash for the accepted secret.
    fn validate(&self, raw: Secret<String>, minimum: usize) -> Result<Secret<String>, FieldCode>;

    /// Hashes an already validated password outside the setup transaction.
    async fn hash(&self, password: &Secret<String>) -> Result<String, AppError>;
}

/// Account details collected by the setup wizard.
pub struct NewSetupAccount {
    pub email: String,
    pub password: Secret<String>,
    pub display_name: String,
    pub timezone: String,
}

/// Validated wizard state and the first account fields needed by Finish.
pub struct FinishInput {
    pub account: NewSetupAccount,
    pub session: super::setup::WizardState,
}

/// First-account profile, access token and one-time refresh credential.
pub struct FinishOutcome {
    pub account: kinevo_ports::platform::AccountProfile,
    pub access_token: Secret<String>,
    pub expires_in: u32,
    pub refresh_token: Secret<String>,
    pub refresh_cookie_max_age: u32,
    /// True when this result was replayed for an idempotency key.
    pub replayed: bool,
}

impl FinishOutcome {
    fn duplicate(&self, replayed: bool) -> Self {
        Self {
            account: self.account.clone(),
            access_token: Secret::new(self.access_token.expose().clone()),
            expires_in: self.expires_in,
            refresh_token: Secret::new(self.refresh_token.expose().clone()),
            refresh_cookie_max_age: self.refresh_cookie_max_age,
            replayed,
        }
    }
}

impl std::fmt::Debug for FinishOutcome {
    fn fmt(&self, formatter: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        formatter
            .debug_struct("FinishOutcome")
            .field("account", &self.account)
            .field("access_token", &"<redacted>")
            .field("expires_in", &self.expires_in)
            .field("refresh_token", &"<redacted>")
            .field("refresh_cookie_max_age", &self.refresh_cookie_max_age)
            .field("replayed", &self.replayed)
            .finish()
    }
}

/// Inputs already read and validated at the composition root for SET-06 bootstrap.
pub struct SetupBootstrap {
    pub email: String,
    pub password: Secret<String>,
    pub template_id: Option<String>,
}

struct CachedFinish {
    key: IdemKey,
    request_hash: [u8; 32],
    expires_at: Instant,
    outcome: FinishOutcome,
}

/// Applies a setup template and creates the first account within one database transaction.
pub struct FinishSetup {
    db: Db,
    passwords: Arc<dyn SetupPasswordProcessor>,
    accounts: Arc<dyn AccountCreation<Tx>>,
    codec: Arc<TokenCodec>,
    ids: Arc<dyn IdGen>,
    clock: Arc<dyn Clock>,
    instance: Arc<CachedInstanceState>,
    templates: Arc<TemplateRegistry>,
    modules: &'static [&'static ModuleDescriptor],
    policies: &'static [&'static PolicySpec],
    audit: AuditWriter,
    subscribers: SubscriberRegistry,
    completed: Mutex<HashMap<super::setup::SessionId, CachedFinish>>,
}

impl FinishSetup {
    #[allow(clippy::too_many_arguments)]
    #[must_use]
    pub fn new(
        db: Db,
        passwords: Arc<dyn SetupPasswordProcessor>,
        accounts: Arc<dyn AccountCreation<Tx>>,
        codec: Arc<TokenCodec>,
        ids: Arc<dyn IdGen>,
        clock: Arc<dyn Clock>,
        instance: Arc<CachedInstanceState>,
        templates: Arc<TemplateRegistry>,
        modules: &'static [&'static ModuleDescriptor],
        policies: &'static [&'static PolicySpec],
        subscribers: SubscriberRegistry,
    ) -> Self {
        Self {
            db,
            passwords,
            accounts,
            codec,
            audit: AuditWriter::new(Arc::clone(&ids)),
            ids,
            clock,
            instance,
            templates,
            modules,
            policies,
            subscribers,
            completed: Mutex::new(HashMap::new()),
        }
    }

    /// Runs the Finish transaction without HTTP idempotency, for the SET-06 startup path.
    pub async fn run(&self, input: FinishInput, client: Client) -> Result<FinishOutcome, AppError> {
        self.complete(input, client).await
    }

    /// Serializes concurrent Finish calls and replays one identical result per wizard session.
    pub async fn run_idempotent(
        &self,
        input: FinishInput,
        client: Client,
        key: IdemKey,
    ) -> Result<FinishOutcome, AppError> {
        let request_hash = request_hash(&input, client)?;
        let session_id = input.session.session_id.clone();
        let now = self.clock.now();
        let mut completed = self.completed.lock().await;
        completed.retain(|_, result| result.expires_at > now);
        if let Some(result) = completed
            .get(&session_id)
            .filter(|result| result.key == key)
        {
            return if result.request_hash == request_hash {
                Ok(result.outcome.duplicate(true))
            } else {
                Err(AppError::new(ErrorCode::IdempotencyKeyReuse))
            };
        }

        let outcome = self.complete(input, client).await?;
        let ttl = policy_integer("api.idempotency.ttl", None, self.policies)?;
        let expires_at = self
            .clock
            .now()
            .checked_add_seconds(ttl)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        completed.insert(
            session_id,
            CachedFinish {
                key,
                request_hash,
                expires_at,
                outcome: outcome.duplicate(false),
            },
        );
        Ok(outcome)
    }

    /// Creates the first account without a setup token using values parsed by the app config.
    pub async fn bootstrap_from_env(
        &self,
        config: SetupBootstrap,
    ) -> Result<FinishOutcome, AppError> {
        let info = kinevo_ports::platform::InstanceState::get(self.instance.as_ref()).await?;
        let template_id = config.template_id.as_deref().unwrap_or("personal");
        let template = self
            .templates
            .get(template_id)
            .ok_or_else(|| AppError::new(ErrorCode::TemplateInvalid))?;
        let session = super::setup::WizardState {
            session_id: super::setup::SessionId::parse(&hex(
                &self.ids.new_uuid_v7().as_bytes()[..16]
            ))?,
            step: 3,
            template_id: template.id.clone(),
            locale: info.default_locale.as_str().to_owned(),
            base_url: info.base_url.map(|url| url.as_str().to_owned()),
            modules: template.modules.clone(),
            policies: template.policies.clone(),
        };
        let email = Email::parse(&config.email).map_err(|code| validation_error("email", code))?;
        let display_name = email
            .as_str()
            .split_once('@')
            .map(|(local, _)| local.to_owned())
            .ok_or_else(|| AppError::new(ErrorCode::ValidationFailed))?;
        self.complete(
            FinishInput {
                account: NewSetupAccount {
                    email: email.as_str().to_owned(),
                    password: config.password,
                    display_name,
                    timezone: "UTC".to_owned(),
                },
                session,
            },
            Client::Web,
        )
        .await
    }

    #[allow(
        clippy::too_many_lines,
        reason = "the setup steps must remain visible in their transaction order"
    )]
    async fn complete(
        &self,
        input: FinishInput,
        client: Client,
    ) -> Result<FinishOutcome, AppError> {
        let _template = self
            .templates
            .get(&input.session.template_id)
            .ok_or_else(|| AppError::new(ErrorCode::TemplateInvalid))?;
        validate_modules(self.modules, &input.session.modules)?;

        let mut errors = Collector::new();
        let email = errors.check("email", Email::parse(&input.account.email));
        let display_name = errors.check(
            "display_name",
            ShortText::parse(&input.account.display_name, 1, 80)
                .map(|text| text.as_str().to_owned()),
        );
        let timezone = errors.check("timezone", IanaTz::parse(&input.account.timezone));
        let minimum = policy_integer(
            "auth.password.min_length",
            input.session.policies.get("auth.password.min_length"),
            self.policies,
        )?;
        let minimum =
            usize::try_from(minimum).map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let password = errors.check(
            "password",
            self.passwords.validate(input.account.password, minimum),
        );
        errors.finish()?;

        let email = email.ok_or_else(internal_error)?;
        let display_name = display_name.ok_or_else(internal_error)?;
        let timezone = timezone.ok_or_else(internal_error)?;
        let password = password.ok_or_else(internal_error)?;
        let password_hash = self.passwords.hash(&password).await?;
        let locale = Locale::parse(&input.session.locale)
            .map_err(|code| validation_error("locale", code))?;
        let session = account_session(&input.session, self.policies, self.clock.now())?;
        let account_id = AccountId::from_uuid(self.ids.new_uuid_v7());
        let now = session.now;

        let mut tx = Tx::begin_admin(&self.db, account_id).await?;
        let setup_completed: bool = sqlx::query_scalar(
            "SELECT setup_completed FROM public.instance_settings WHERE id=1 FOR UPDATE",
        )
        .fetch_one(tx.conn())
        .await
        .map_err(database_error)?;
        if setup_completed {
            return Err(AppError::new(ErrorCode::SetupAlreadyCompleted));
        }

        let module_repository = ModuleRepository::new(self.db.clone());
        let current_modules = module_repository.load_rows_tx(&mut tx).await?;
        let policies = policy_writes(&input.session, self.policies)?;
        let policy_repository = PolicyRepository::new(self.db.clone());

        let created = self
            .accounts
            .create(
                &mut tx,
                NewAccount {
                    id: account_id,
                    email,
                    password_hash,
                    display_name,
                    timezone,
                    locale,
                    is_instance_admin: true,
                    client,
                    device_label: None,
                    session,
                },
            )
            .await?;

        for (key, enabled) in &input.session.modules {
            let Some(descriptor) = self.modules.iter().find(|item| item.key == key) else {
                return Err(AppError::new(ErrorCode::TemplateInvalid));
            };
            if descriptor.kind != ModuleKind::Toggleable {
                continue;
            }
            let current = current_modules.get(key);
            if current.is_some_and(|row| row.enabled == *enabled) {
                continue;
            }
            let expected_version = current.map_or(0, |row| row.version);
            let next_version = expected_version.checked_add(1).ok_or_else(internal_error)?;
            module_repository
                .save(
                    &mut tx,
                    ModuleSwitchUpdate {
                        key: descriptor.key,
                        enabled: *enabled,
                        expected_version,
                        next_version,
                        actor: account_id,
                        now,
                    },
                )
                .await?;
        }

        for (spec, scope, value) in policies
            .iter()
            .filter(|(_, scope, _)| *scope == Scope::Instance)
        {
            policy_repository
                .append(
                    &mut tx,
                    PolicyVersionInsert {
                        id: self.ids.new_uuid_v7(),
                        actor: account_id,
                        key: spec.key,
                        scope: *scope,
                        account: None,
                        value,
                        effective_from: now,
                        created_at: now,
                    },
                )
                .await?;
        }

        for (spec, scope, value) in policies
            .iter()
            .filter(|(_, scope, _)| *scope == Scope::Account)
        {
            policy_repository
                .append(
                    &mut tx,
                    PolicyVersionInsert {
                        id: self.ids.new_uuid_v7(),
                        actor: account_id,
                        key: spec.key,
                        scope: *scope,
                        account: Some(account_id),
                        value,
                        effective_from: now,
                        created_at: now,
                    },
                )
                .await?;
        }

        sqlx::query(
            "UPDATE public.instance_settings SET setup_completed=true, setup_token_hash=NULL, \
             template_id=$1, base_url=$2, default_locale=$3, updated_at=$4::timestamptz, \
             version=version+1 WHERE id=1",
        )
        .bind(&input.session.template_id)
        .bind(&input.session.base_url)
        .bind(locale.as_str())
        .bind(now.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(database_error)?;

        self.audit
            .record(
                &mut tx,
                AuditEntry {
                    account_id: Some(account_id),
                    actor_id: Some(*account_id.as_uuid()),
                    action: AuditAction::SetupCompleted,
                    target_type: Some(AuditTarget::ACCOUNT),
                    target_id: Some(account_id.to_string()),
                    details: Details::new(),
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
                kind: EventKind::InstanceSetupCompleted,
                v: 1,
                account_id: Some(account_id),
                occurred_at: now,
                payload: EventPayload::new().with_id("account_id", &account_id),
            },
            &self.subscribers,
        )
        .await?;

        let access_token = self.codec.issue(&Claims {
            sub: created.access_claims.account_id,
            sid: created.access_claims.family_id,
            jti: self.ids.new_uuid_v7(),
            iat: created.access_claims.issued_at,
            exp: created.access_claims.expires_at,
            mfa: created.access_claims.mfa,
        });
        if access_token.is_empty() {
            return Err(internal_error());
        }
        tx.commit().await?;
        self.instance.mark_completed();

        Ok(FinishOutcome {
            account: created.profile,
            access_token: Secret::new(access_token),
            expires_in: created.expires_in,
            refresh_token: created.refresh_token,
            refresh_cookie_max_age: created.refresh_cookie_max_age,
            replayed: false,
        })
    }
}

fn policy_writes(
    session: &super::setup::WizardState,
    policies: &'static [&'static PolicySpec],
) -> Result<Vec<(&'static PolicySpec, Scope, Value)>, AppError> {
    let mut writes = Vec::new();
    for (key, value) in &session.policies {
        let spec = policies
            .iter()
            .copied()
            .find(|spec| spec.key == key)
            .ok_or_else(|| AppError::new(ErrorCode::TemplateInvalid))?;
        validate_value(spec, value).map_err(|_| AppError::new(ErrorCode::TemplateInvalid))?;
        for scope in spec.scopes {
            writes.push((spec, *scope, value.clone()));
        }
    }
    Ok(writes)
}

fn account_session(
    session: &super::setup::WizardState,
    policies: &'static [&'static PolicySpec],
    now: Instant,
) -> Result<AccountSessionSettings, AppError> {
    let seconds = |key| -> Result<u32, AppError> {
        u32::try_from(policy_integer(key, session.policies.get(key), policies)?)
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))
    };
    Ok(AccountSessionSettings {
        now,
        access_ttl: seconds("auth.access_token.ttl")?,
        idle_ttl: seconds("auth.refresh_token.idle_ttl")?,
        absolute_ttl: seconds("auth.refresh_token.absolute_ttl")?,
        grace: seconds("auth.refresh.grace")?,
    })
}

fn policy_integer(
    key: &'static str,
    value: Option<&Value>,
    policies: &'static [&'static PolicySpec],
) -> Result<i64, AppError> {
    if let Some(value) = value {
        return value
            .as_i64()
            .ok_or_else(|| AppError::new(ErrorCode::TemplateInvalid));
    }
    let spec = policies
        .iter()
        .copied()
        .chain(all_specs().iter().copied())
        .find(|spec| spec.key == key)
        .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
    match spec.default {
        PolicyValue::Int(value) | PolicyValue::Seconds(value) => Ok(value),
        _ => Err(AppError::new(ErrorCode::ServiceUnavailable)),
    }
}

fn validate_modules(
    descriptors: &[&'static ModuleDescriptor],
    selected: &std::collections::BTreeMap<String, bool>,
) -> Result<(), AppError> {
    validate_graph(descriptors).map_err(|_| AppError::new(ErrorCode::TemplateInvalid))?;
    let mut enabled = descriptors
        .iter()
        .filter(|module| module.kind == ModuleKind::Core)
        .map(|module| module.key)
        .collect::<std::collections::BTreeSet<_>>();
    for (key, state) in selected {
        let descriptor = descriptors
            .iter()
            .find(|module| module.key == key)
            .ok_or_else(|| AppError::new(ErrorCode::TemplateInvalid))?;
        if descriptor.kind == ModuleKind::Core && !state {
            return Err(AppError::new(ErrorCode::ModuleCoreLocked));
        }
        if descriptor.kind == ModuleKind::Toggleable && *state {
            enabled.insert(descriptor.key);
        }
    }
    for descriptor in descriptors {
        if enabled.contains(descriptor.key)
            && descriptor
                .requires
                .iter()
                .any(|required| !enabled.contains(required))
        {
            return Err(AppError::new(ErrorCode::ModuleDependencyUnmet));
        }
    }
    Ok(())
}

fn request_hash(input: &FinishInput, client: Client) -> Result<[u8; 32], AppError> {
    let session = serde_json::to_string(&input.session).map_err(|_| internal_error())?;
    let account = &input.account;
    let client = match client {
        Client::Web => "web",
        Client::Android => "android",
    };
    let canonical = Secret::new(format!(
        "{}:{}:{}:{}:{}:{}:{}:{}:{}:{}:{}:{}",
        account.email.len(),
        account.email,
        account.password.expose().len(),
        account.password.expose(),
        account.display_name.len(),
        account.display_name,
        account.timezone.len(),
        account.timezone,
        session.len(),
        session,
        client.len(),
        client,
    ));
    Ok(hash_token(canonical.expose().as_bytes()))
}

fn validation_error(path: &str, code: FieldCode) -> AppError {
    AppError::validation(vec![FieldError {
        path: path.to_owned(),
        code,
        details: Details::new(),
    }])
}

fn database_error(_: sqlx::Error) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable)
}

fn internal_error() -> AppError {
    AppError::new(ErrorCode::InternalError)
}

fn hex(bytes: &[u8]) -> String {
    let mut result = String::with_capacity(bytes.len().saturating_mul(2));
    for byte in bytes {
        result.push(hex_digit(byte >> 4));
        result.push(hex_digit(byte & 0x0f));
    }
    result
}

fn hex_digit(value: u8) -> char {
    char::from(match value {
        0..=9 => b'0' + value,
        10..=15 => b'a' + (value - 10),
        _ => b'0',
    })
}
