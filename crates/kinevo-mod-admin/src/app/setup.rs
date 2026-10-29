//! Setup-token bootstrap and in-memory wizard use cases.

use std::{
    collections::{BTreeMap, BTreeSet},
    fmt,
    sync::Arc,
};

use kinevo_db::{
    Db, SystemTx, Tx,
    audit::{AuditAction, AuditEntry, AuditWriter},
};
use kinevo_kernel::{
    crypto::{ct_eq, hash_token},
    errors::{AppError, Details, ErrorCode, FieldCode, FieldError},
    id::{AccountId, IdGen, TokenGen},
    policy::{PolicySpec, validate_value},
    secret::Secret,
    time::Clock,
    validate::{net::HttpsUrl, tz::Locale},
};
use kinevo_ports::{
    module::{ModuleDescriptor, ModuleKind},
    platform::InstanceState,
};
use serde::{Deserialize, Serialize};
use serde_json::Value;
use sqlx::Row;

use crate::{
    app::templates::{TemplateListing, TemplateRegistry},
    domain::template::Template,
    infra::{instance_state::CachedInstanceState, wizard_store::WizardStore},
};

/// Opaque, random 128-bit wizard session identifier.
#[derive(Clone, Eq, Hash, PartialEq, Serialize, Deserialize)]
#[serde(transparent)]
pub struct SessionId(String);

impl fmt::Debug for SessionId {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("SessionId(<redacted>)")
    }
}

impl SessionId {
    /// Parses the canonical 128-bit lowercase hexadecimal representation.
    pub fn parse(raw: &str) -> Result<Self, AppError> {
        if raw.len() != 32
            || !raw
                .bytes()
                .all(|byte| byte.is_ascii_hexdigit() && !byte.is_ascii_uppercase())
        {
            return Err(AppError::new(ErrorCode::NotFound));
        }
        Ok(Self(raw.to_owned()))
    }
}

/// Validated wizard state kept only in the bounded process-local store.
#[derive(Clone, Debug, Eq, PartialEq, Serialize, Deserialize)]
pub struct WizardState {
    pub session_id: SessionId,
    pub step: u8,
    pub template_id: String,
    pub locale: String,
    pub base_url: Option<String>,
    pub modules: BTreeMap<String, bool>,
    pub policies: BTreeMap<String, Value>,
}

/// Partial wizard update. Omitted fields preserve the current validated value.
#[derive(Clone, Debug, Default, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct WizardPatch {
    pub step: u8,
    pub template_id: Option<String>,
    pub locale: Option<String>,
    pub base_url: Option<String>,
    pub modules: Option<BTreeMap<String, bool>>,
    pub policies: Option<BTreeMap<String, Value>>,
}

/// Public read-only status payload returned before setup completes.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
pub struct SetupStatus {
    pub setup_completed: bool,
    pub templates: Vec<TemplateListing>,
    pub locales: [&'static str; 2],
}

/// Setup-session creation response.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
pub struct OpenedSession {
    pub session_id: SessionId,
    pub step: u8,
}

/// Setup token lifecycle, validation, and session business rules.
pub struct SetupService {
    db: Db,
    wizard: WizardStore,
    tokens: Arc<dyn TokenGen>,
    ids: Arc<dyn IdGen>,
    clock: Arc<dyn Clock>,
    instance: Arc<CachedInstanceState>,
    templates: Arc<TemplateRegistry>,
    modules: &'static [&'static ModuleDescriptor],
    policies: &'static [&'static PolicySpec],
    setup_url_origin: String,
    audit: AuditWriter,
}

impl SetupService {
    /// Builds setup use cases with validated templates and operator-resolved limits.
    #[allow(clippy::too_many_arguments)]
    #[must_use]
    pub fn new(
        db: Db,
        wizard: WizardStore,
        tokens: Arc<dyn TokenGen>,
        ids: Arc<dyn IdGen>,
        clock: Arc<dyn Clock>,
        instance: Arc<CachedInstanceState>,
        templates: Arc<TemplateRegistry>,
        modules: &'static [&'static ModuleDescriptor],
        policies: &'static [&'static PolicySpec],
        setup_url_origin: String,
    ) -> Self {
        Self {
            db,
            wizard,
            tokens,
            ids: Arc::clone(&ids),
            clock,
            instance,
            templates,
            modules,
            policies,
            setup_url_origin,
            audit: AuditWriter::new(ids),
        }
    }

    /// Replaces the process-local store, used by a restarted process and its tests.
    #[must_use]
    pub fn with_wizard(mut self, wizard: WizardStore) -> Self {
        self.wizard = wizard;
        self
    }

    /// Provides the shared instance-state adapter for HTTP gates and setup completion.
    #[must_use]
    pub fn instance_state(&self) -> &Arc<CachedInstanceState> {
        &self.instance
    }

    /// Creates and stores a 128-bit setup token once, returning it only to first start.
    pub async fn ensure_token(&self) -> Result<Option<Secret<String>>, AppError> {
        self.store_setup_token(false).await
    }

    /// Rotates the active setup token for the operator CLI.
    pub async fn rotate_setup_token(&self) -> Result<Secret<String>, AppError> {
        self.store_setup_token(true)
            .await?
            .ok_or_else(|| AppError::new(ErrorCode::SetupAlreadyCompleted))
    }

    async fn store_setup_token(&self, rotate: bool) -> Result<Option<Secret<String>>, AppError> {
        let mut tx = Tx::begin(&self.db, self.setup_context_account()).await?;
        let row = sqlx::query(
            "SELECT setup_completed, setup_token_hash FROM public.instance_settings \
             WHERE id=1 FOR UPDATE",
        )
        .fetch_one(tx.conn())
        .await
        .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let completed = row
            .try_get::<bool, _>("setup_completed")
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        let stored_hash = row
            .try_get::<Option<Vec<u8>>, _>("setup_token_hash")
            .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        if completed {
            tx.commit().await?;
            return if rotate {
                Err(AppError::new(ErrorCode::SetupAlreadyCompleted))
            } else {
                Ok(None)
            };
        }
        if stored_hash.is_some() && !rotate {
            tx.commit().await?;
            return Ok(None);
        }

        let raw = Secret::new(self.tokens.token_128());
        let token = Secret::new(hex(raw.expose()));
        let digest = hash_token(token.expose().as_bytes());
        sqlx::query(
            "UPDATE public.instance_settings SET setup_token_hash=$1, updated_at=$2::timestamptz, \
             version=version+1 WHERE id=1",
        )
        .bind(digest.as_slice())
        .bind(self.clock.now().to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        tx.commit().await?;
        let setup_url = Secret::new(format!(
            "{}/setup?token={}",
            self.setup_url_origin.trim_end_matches('/'),
            token.expose()
        ));
        tracing::info!(target: "kinevo::setup", setup_url = %setup_url.expose(), "one-time setup URL");
        Ok(Some(token))
    }

    /// Returns status metadata for the setup start page.
    pub async fn status(&self) -> Result<SetupStatus, AppError> {
        let info = self.instance.get().await?;
        Ok(SetupStatus {
            setup_completed: info.setup_completed,
            templates: self.templates.list(),
            locales: ["en", "id"],
        })
    }

    /// Validates the setup token and creates a new step-one wizard session.
    pub async fn open_session(
        &self,
        source: &str,
        token: &Secret<String>,
    ) -> Result<WizardState, AppError> {
        if self.instance.get().await?.setup_completed {
            return Err(AppError::new(ErrorCode::SetupAlreadyCompleted));
        }
        let now = self.clock.now();
        if self.wizard.is_locked(source) {
            return Err(AppError::new(ErrorCode::RateLimited));
        }
        let mut tx = SystemTx::begin(&self.db).await?;
        let stored_hash = sqlx::query_scalar::<_, Option<Vec<u8>>>(
            "SELECT setup_token_hash FROM public.instance_settings WHERE id=1",
        )
        .fetch_one(tx.conn())
        .await
        .map_err(|_| AppError::new(ErrorCode::ServiceUnavailable))?;
        tx.commit().await?;
        let digest = hash_token(token.expose().as_bytes());
        if !stored_hash
            .as_deref()
            .is_some_and(|expected| ct_eq(expected, &digest))
        {
            let locked = self.wizard.record_failure(source)?;
            self.audit_failed_token(now).await?;
            return Err(AppError::new(if locked {
                ErrorCode::RateLimited
            } else {
                ErrorCode::SetupTokenInvalid
            }));
        }
        if !self.wizard.accept_attempt(source) {
            return Err(AppError::new(ErrorCode::RateLimited));
        }

        let template = self
            .templates
            .get("personal")
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        let session_id = SessionId(hex(&self.tokens.token_128()));
        let state = state_from_template(session_id, template);
        self.wizard.insert(state.clone())?;
        Ok(state)
    }

    /// Returns the current validated server-side wizard state.
    pub fn get(&self, id: &SessionId) -> Result<WizardState, AppError> {
        self.wizard.get(id)
    }

    /// Validates and stores a wizard update without writing business tables.
    pub async fn update(&self, id: SessionId, patch: WizardPatch) -> Result<WizardState, AppError> {
        if self.instance.get().await?.setup_completed {
            return Err(AppError::new(ErrorCode::SetupAlreadyCompleted));
        }
        let mut state = self.wizard.get(&id)?;
        if !(1..=3).contains(&patch.step) || patch.step > state.step.saturating_add(1) {
            return Err(AppError::new(ErrorCode::InvalidTransition));
        }
        let selected_template = patch.template_id.as_deref().unwrap_or(&state.template_id);
        let template = self
            .templates
            .get(selected_template)
            .ok_or_else(|| AppError::new(ErrorCode::TemplateInvalid))?;
        let template_changed = selected_template != state.template_id;
        state.step = patch.step;
        if template_changed {
            state.template_id = template.id.clone();
            state.modules = template.modules.clone();
            state.policies = template.policies.clone();
        }
        if let Some(locale) = patch.locale {
            Locale::parse(&locale).map_err(|code| validation_error("locale", code))?;
            state.locale = locale;
        }
        if let Some(base_url) = patch.base_url {
            state.base_url = Some(validate_base_url(&base_url)?);
        }
        if let Some(modules) = patch.modules {
            state.modules.extend(modules);
        }
        if let Some(policies) = patch.policies {
            state.policies.extend(policies);
        }
        validate_modules(self.modules, &state.modules)?;
        validate_policies(self.policies, &state.policies)?;
        self.wizard.update(state.clone())?;
        Ok(state)
    }

    async fn audit_failed_token(
        &self,
        occurred_at: kinevo_kernel::time::Instant,
    ) -> Result<(), AppError> {
        let mut tx = SystemTx::begin(&self.db).await?;
        self.audit
            .record(
                &mut tx,
                AuditEntry {
                    account_id: None,
                    actor_id: None,
                    action: AuditAction::SetupTokenFailed,
                    target_type: None,
                    target_id: None,
                    details: Details::new(),
                    request_id: None,
                    occurred_at,
                },
            )
            .await?;
        tx.commit().await
    }

    fn setup_context_account(&self) -> AccountId {
        // Setup changes only the singleton global settings row before an account exists.
        AccountId::from_uuid(self.ids.new_uuid_v7())
    }
}

fn state_from_template(session_id: SessionId, template: &Template) -> WizardState {
    WizardState {
        session_id,
        step: 1,
        template_id: template.id.clone(),
        locale: "en".to_owned(),
        base_url: None,
        modules: template.modules.clone(),
        policies: template.policies.clone(),
    }
}

fn validate_base_url(raw: &str) -> Result<String, AppError> {
    let parsed = HttpsUrl::parse(raw).map_err(|code| validation_error("base_url", code))?;
    let rest = raw
        .trim()
        .split_once("://")
        .map(|(_, rest)| rest)
        .ok_or_else(|| validation_error("base_url", FieldCode::UrlInvalid))?;
    let suffix = rest
        .find(['/', '?', '#'])
        .map_or("", |index| &rest[index..]);
    if !suffix.is_empty() && suffix != "/" {
        return Err(validation_error("base_url", FieldCode::UrlInvalid));
    }
    Ok(parsed.as_str().to_owned())
}

fn validate_modules(
    descriptors: &[&ModuleDescriptor],
    modules: &BTreeMap<String, bool>,
) -> Result<(), AppError> {
    let descriptors = descriptors
        .iter()
        .map(|descriptor| (descriptor.key, *descriptor))
        .collect::<BTreeMap<_, _>>();
    let mut enabled = descriptors
        .values()
        .filter(|descriptor| descriptor.kind == ModuleKind::Core)
        .map(|descriptor| descriptor.key.to_owned())
        .collect::<BTreeSet<_>>();
    for (key, value) in modules {
        let descriptor = descriptors
            .get(key.as_str())
            .ok_or_else(|| AppError::new(ErrorCode::TemplateInvalid))?;
        if descriptor.kind == ModuleKind::Core {
            if !value {
                return Err(AppError::new(ErrorCode::ModuleCoreLocked));
            }
        } else if *value {
            enabled.insert(key.clone());
        }
    }
    for (key, value) in modules {
        if !value {
            continue;
        }
        let descriptor = descriptors
            .get(key.as_str())
            .ok_or_else(|| AppError::new(ErrorCode::TemplateInvalid))?;
        if descriptor
            .requires
            .iter()
            .any(|required| !enabled.contains(*required))
        {
            return Err(AppError::new(ErrorCode::ModuleDependencyUnmet));
        }
    }
    Ok(())
}

fn validate_policies(
    specs: &[&PolicySpec],
    policies: &BTreeMap<String, Value>,
) -> Result<(), AppError> {
    for (key, value) in policies {
        let spec = specs
            .iter()
            .find(|spec| spec.key == key)
            .ok_or_else(|| AppError::new(ErrorCode::PolicyUnknown))?;
        if spec.scopes.is_empty() {
            return Err(AppError::new(ErrorCode::PolicyValueInvalid));
        }
        validate_value(spec, value).map_err(|error| AppError::new(error.code))?;
    }
    Ok(())
}

fn validation_error(path: &str, code: FieldCode) -> AppError {
    AppError::validation(vec![FieldError {
        path: path.to_owned(),
        code,
        details: Details::new(),
    }])
}

fn hex(bytes: &[u8]) -> String {
    const HEX: &[u8; 16] = b"0123456789abcdef";
    let mut output = String::with_capacity(bytes.len().saturating_mul(2));
    for byte in bytes {
        let high = HEX
            .get(usize::from(byte >> 4))
            .copied()
            .map_or('0', char::from);
        let low = HEX
            .get(usize::from(byte & 0x0f))
            .copied()
            .map_or('0', char::from);
        output.push(high);
        output.push(low);
    }
    output
}
