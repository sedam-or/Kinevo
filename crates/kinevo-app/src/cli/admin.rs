use clap::{Args, Subcommand, ValueEnum};
use kinevo_db::{
    Db, DbConfig, SystemTx, TokioSleeper, Tx,
    audit::{AuditAction, AuditEntry, AuditWriter},
    outbox::SubscriberRegistry,
};
use kinevo_kernel::{
    crypto::MasterKey,
    errors::{DetailValue, Details},
    event::{EventEnumValue, EventEnvelope, EventKind, EventPayload},
    id::{AccountId, EventId, IdGen, TokenGen},
    policy::Scope,
    secret::Secret,
    time::Clock,
    validate::net::Email,
};
use kinevo_mod_admin::{
    app::{
        policies::PolicyService,
        setup::SetupService,
        templates::{TemplateRegistry, apply_plan},
    },
    domain::template::ValidationCtx,
    infra::{
        instance_state::CachedInstanceState, secret_store::rotate_master_key,
        wizard_store::WizardStore,
    },
};
use kinevo_mod_identity::{
    app::{
        login::{HasherPasswordWork, PasswordWork},
        reset::ResetService,
    },
    infra::hasher::{Hasher, HasherConfig},
};
use kinevo_ports::module::{ModuleDescriptor, ModuleKind, SwitchOutcome, plan_switch};
use serde_json::{Value, json};
use sqlx::Row;
use std::{
    collections::{BTreeSet, HashMap},
    io::Read,
    path::PathBuf,
    sync::Arc,
    time::Duration,
};
use uuid::Uuid;

#[derive(Args, Debug)]
pub struct AdminArgs {
    #[command(subcommand)]
    pub action: AdminAction,
}

#[derive(Subcommand, Debug)]
pub enum AdminAction {
    /// Rotate and display a fresh setup token while setup remains incomplete.
    SetupToken,
    /// Issue a password-reset link for an account.
    ResetPassword { email: String },
    /// List account metadata without account content.
    ListAccounts {
        /// Emit a JSON array for scripts.
        #[arg(long)]
        json: bool,
    },
    /// Re-seal stored secrets using key material read from files or stdin.
    RotateSecretKey {
        #[arg(long, value_name = "FILE|-", required = true)]
        old: PathBuf,
        #[arg(long, value_name = "FILE|-", required = true)]
        new: PathBuf,
    },
    /// Apply one embedded setup template.
    ApplyTemplate { id: String },
    /// Enable or disable a compiled module.
    Module { key: String, action: ModuleAction },
}

#[derive(Clone, Copy, Debug, Eq, PartialEq, ValueEnum)]
pub enum ModuleAction {
    On,
    Off,
}

struct Context {
    db: Db,
    public_url: String,
    setup_session_ttl: Duration,
    clock: Arc<dyn Clock>,
    ids: Arc<dyn IdGen>,
    tokens: Arc<dyn TokenGen>,
}

impl Context {
    async fn from_process() -> Result<Self, &'static str> {
        let config = crate::cli::config_from_process().map_err(|_| "config_invalid")?;
        let setup_session_ttl = config.setup.session_ttl;
        let public_url = config.public_url.as_str().to_owned();
        let db = Db::connect(
            &DbConfig {
                app_url: config.database_url,
                system_url: config.system_database_url,
                pool_max: config.pool_max,
                retries: config.db_connect_retries,
                backoff: config.db_connect_backoff,
            },
            &TokioSleeper,
        )
        .await
        .map_err(|_| "database_connect_failed")?;
        Ok(Self {
            db,
            public_url,
            setup_session_ttl,
            clock: crate::runtime::clock_from_process().map_err(|_| "clock_invalid")?,
            ids: Arc::new(crate::runtime::UuidV7Gen),
            tokens: Arc::new(crate::runtime::OsTokenGen),
        })
    }

    fn setup_service(&self) -> Result<SetupService, &'static str> {
        let policies = crate::serve::template_policies();
        let validation = ValidationCtx::new(
            crate::serve::TEMPLATE_MODULES,
            policies,
            crate::serve::TERMS,
        );
        let templates =
            Arc::new(TemplateRegistry::embedded(&validation).map_err(|_| "config_invalid")?);
        let cache_ttl = crate::serve::default_policy_seconds("modules.cache_ttl_seconds")
            .ok_or("config_invalid")?;
        let lockout_seconds = crate::serve::default_policy_seconds("auth.lockout.duration")
            .ok_or("config_invalid")?;
        let session_ttl =
            i64::try_from(self.setup_session_ttl.as_secs()).map_err(|_| "config_invalid")?;
        let instance = Arc::new(CachedInstanceState::new(
            self.db.clone(),
            Arc::clone(&self.clock),
            cache_ttl,
        ));
        Ok(SetupService::new(
            self.db.clone(),
            WizardStore::new(session_ttl, lockout_seconds, Arc::clone(&self.clock)),
            Arc::clone(&self.tokens),
            Arc::clone(&self.ids),
            Arc::clone(&self.clock),
            instance,
            templates,
            crate::serve::TEMPLATE_MODULES,
            policies,
            self.public_url.clone(),
        ))
    }

    fn subscribers() -> SubscriberRegistry {
        let mut subscribers = SubscriberRegistry::default();
        kinevo_mod_admin::infra::module_switches::register_module_subscriber(&mut subscribers);
        kinevo_mod_admin::infra::policy_reader::register_policy_subscriber(&mut subscribers);
        subscribers
    }
}

pub async fn run(args: AdminArgs) -> Result<(), &'static str> {
    let ctx = Context::from_process().await?;
    let (result, action) = match args.action {
        AdminAction::SetupToken => (setup_token(&ctx).await, AuditAction::CliSetupToken),
        AdminAction::ResetPassword { email } => (
            reset_password(&ctx, &email).await,
            AuditAction::CliPasswordReset,
        ),
        AdminAction::ListAccounts { json } => (
            list_accounts(&ctx, json).await,
            AuditAction::CliListAccounts,
        ),
        AdminAction::RotateSecretKey { old, new } => (
            rotate_key(&ctx, &old, &new).await,
            AuditAction::CliRotateSecretKey,
        ),
        AdminAction::ApplyTemplate { id } => (
            apply_template(&ctx, &id).await,
            AuditAction::CliApplyTemplate,
        ),
        AdminAction::Module { key, action } => (
            switch_module(&ctx, &key, action).await,
            AuditAction::CliModuleSwitch,
        ),
    };
    let audited = audit_cli(&ctx, action).await;
    result?;
    audited?;
    Ok(())
}

async fn setup_token(ctx: &Context) -> Result<(), &'static str> {
    let service = ctx.setup_service()?;
    let token = service
        .rotate_setup_token()
        .await
        .map_err(|error| error.code.as_str())?;
    println!("{}", token.expose());
    Ok(())
}

async fn reset_password(ctx: &Context, raw_email: &str) -> Result<(), &'static str> {
    let email = Email::parse(raw_email).map_err(|_| "validation_failed")?;
    let policies = PolicyService::new(
        ctx.db.clone(),
        Arc::clone(&ctx.ids),
        Arc::clone(&ctx.clock),
        SubscriberRegistry::default(),
    );
    let cache_ttl = crate::serve::default_policy_seconds("modules.cache_ttl_seconds")
        .ok_or("config_invalid")?;
    let instance = Arc::new(CachedInstanceState::new(
        ctx.db.clone(),
        Arc::clone(&ctx.clock),
        cache_ttl,
    ));
    let concurrency = std::thread::available_parallelism().map_or(1, std::num::NonZeroUsize::get);
    let hasher = Arc::new(
        Hasher::new(
            Arc::clone(&ctx.tokens),
            Arc::clone(&ctx.clock),
            HasherConfig::new(concurrency),
        )
        .map_err(|_| "config_invalid")?,
    );
    let passwords: Arc<dyn PasswordWork> = Arc::new(HasherPasswordWork::new(hasher));
    let service = ResetService::new(
        ctx.db.clone(),
        Arc::clone(&ctx.ids),
        Arc::clone(&ctx.tokens),
        passwords,
        policies.reader(),
        instance,
        Arc::new(kinevo_ports::platform::NoMailer),
        Arc::clone(&ctx.clock),
        SubscriberRegistry::default(),
    );
    let link = service
        .issue_for_email(&email)
        .await
        .map_err(|error| error.code.as_str())?;
    let token = link
        .url
        .expose()
        .rsplit_once("token=")
        .map(|(_, token)| token)
        .ok_or("reset_link_unavailable")?;
    println!("{}/reset/{token}", ctx.public_url.trim_end_matches('/'));
    Ok(())
}

async fn list_accounts(ctx: &Context, as_json: bool) -> Result<(), &'static str> {
    let mut tx = SystemTx::begin(&ctx.db)
        .await
        .map_err(|_| "database_query_failed")?;
    let rows = sqlx::query(
        "SELECT id, email::text AS email, status, is_instance_admin \
         FROM public.accounts ORDER BY email, id",
    )
    .fetch_all(tx.conn())
    .await
    .map_err(|_| "database_query_failed")?;
    tx.commit().await.map_err(|_| "database_query_failed")?;

    let accounts = rows
        .iter()
        .map(|row| -> Result<Value, &'static str> {
            let role = if row
                .try_get::<bool, _>("is_instance_admin")
                .map_err(|_| "database_query_failed")?
            {
                "instance_admin"
            } else {
                "account_owner"
            };
            Ok(json!({
                    "id": row.try_get::<Uuid, _>("id").map_err(|_| "database_query_failed")?.to_string(),
                    "email": row.try_get::<String, _>("email").map_err(|_| "database_query_failed")?,
                    "status": row.try_get::<String, _>("status").map_err(|_| "database_query_failed")?,
                    "role": role,
                }))
        })
        .collect::<Result<Vec<_>, _>>()?;
    if as_json {
        println!(
            "{}",
            serde_json::to_string(&accounts).map_err(|_| "output_failed")?
        );
    } else {
        for account in accounts {
            let fields = account.as_object().ok_or("output_failed")?;
            let id = fields
                .get("id")
                .and_then(Value::as_str)
                .ok_or("output_failed")?;
            let email = fields
                .get("email")
                .and_then(Value::as_str)
                .ok_or("output_failed")?;
            let status = fields
                .get("status")
                .and_then(Value::as_str)
                .ok_or("output_failed")?;
            let role = fields
                .get("role")
                .and_then(Value::as_str)
                .ok_or("output_failed")?;
            println!("{id}\t{email}\t{status}\t{role}");
        }
    }
    Ok(())
}

async fn rotate_key(
    ctx: &Context,
    old_path: &PathBuf,
    new_path: &PathBuf,
) -> Result<(), &'static str> {
    if old_path == "-" && new_path == "-" {
        return Err("rotation_key_input_invalid");
    }
    let old = read_master_key(old_path)?;
    let new = read_master_key(new_path)?;
    let new_key_id = u16::from(crate::serve::ACTIVE_SECRET_KEY_VERSION)
        .checked_add(1)
        .ok_or("rotation_key_id_exhausted")?;
    rotate_master_key(&ctx.db, &old, &new, new_key_id)
        .await
        .map_err(|error| error.code.as_str())?;
    Ok(())
}

fn read_master_key(path: &PathBuf) -> Result<MasterKey, &'static str> {
    let raw = if path == "-" {
        let mut value = String::new();
        std::io::stdin()
            .read_to_string(&mut value)
            .map_err(|_| "rotation_key_unreadable")?;
        value
    } else {
        std::fs::read_to_string(path).map_err(|_| "rotation_key_unreadable")?
    };
    let value = raw.trim_end_matches(['\r', '\n']).to_owned();
    MasterKey::from_base64(&Secret::new(value)).map_err(|_| "rotation_key_invalid")
}

#[allow(
    clippy::too_many_lines,
    reason = "template module and policy changes share one atomic transaction"
)]
async fn apply_template(ctx: &Context, template_id: &str) -> Result<(), &'static str> {
    let policies = crate::serve::template_policies();
    let validation = ValidationCtx::new(
        crate::serve::TEMPLATE_MODULES,
        policies,
        crate::serve::TERMS,
    );
    let registry = TemplateRegistry::embedded(&validation).map_err(|_| "config_invalid")?;
    let template = registry.get(template_id).ok_or("template_not_found")?;
    let now = ctx.clock.now();
    let mut tx = Tx::begin_admin(&ctx.db, AccountId::from_uuid(Uuid::nil()))
        .await
        .map_err(|_| "database_write_failed")?;
    sqlx::query("SELECT id FROM public.instance_settings WHERE id=1 FOR UPDATE")
        .fetch_one(tx.conn())
        .await
        .map_err(|_| "database_write_failed")?;
    let rows = sqlx::query("SELECT module_key, enabled, version FROM public.instance_modules")
        .fetch_all(tx.conn())
        .await
        .map_err(|_| "database_query_failed")?;
    let mut module_rows = HashMap::new();
    for row in rows {
        let key: String = row
            .try_get("module_key")
            .map_err(|_| "database_query_failed")?;
        let enabled: bool = row
            .try_get("enabled")
            .map_err(|_| "database_query_failed")?;
        let version: i64 = row
            .try_get("version")
            .map_err(|_| "database_query_failed")?;
        module_rows.insert(key, (enabled, version));
    }
    let current_modules = crate::serve::TEMPLATE_MODULES
        .iter()
        .filter(|descriptor| {
            descriptor.kind == ModuleKind::Core
                || module_rows
                    .get(descriptor.key)
                    .is_some_and(|(enabled, _)| *enabled)
        })
        .map(|descriptor| descriptor.key.to_owned())
        .collect::<BTreeSet<_>>();
    let policy_rows = sqlx::query(
        "SELECT DISTINCT ON (key) key, value FROM public.policy_versions \
         WHERE scope='instance' AND account_id IS NULL AND effective_from <= $1::timestamptz \
         ORDER BY key, effective_from DESC, id DESC",
    )
    .bind(now.to_rfc3339())
    .fetch_all(tx.conn())
    .await
    .map_err(|_| "database_query_failed")?;
    let mut current_policies = HashMap::new();
    for row in policy_rows {
        let key: String = row.try_get("key").map_err(|_| "database_query_failed")?;
        let value: Value = row.try_get("value").map_err(|_| "database_query_failed")?;
        current_policies.insert((key, Scope::Instance), value);
    }
    let plan = apply_plan(template, &current_modules, &|key, scope| {
        current_policies.get(&(key.to_owned(), scope)).cloned()
    });
    let subscribers = Context::subscribers();

    for (key, enabled) in plan.module_changes {
        let current_version = module_rows.get(&key).map_or(0, |(_, version)| *version);
        let next_version = current_version
            .checked_add(1)
            .ok_or("database_write_failed")?;
        sqlx::query(
            "INSERT INTO public.instance_modules (module_key, enabled, updated_at, updated_by, version) \
             VALUES ($1, $2, $3::timestamptz, NULL, $4) \
             ON CONFLICT (module_key) DO UPDATE SET enabled=EXCLUDED.enabled, updated_at=EXCLUDED.updated_at, version=EXCLUDED.version",
        )
        .bind(&key)
        .bind(enabled)
        .bind(now.to_rfc3339())
        .bind(next_version)
        .execute(tx.conn())
        .await
        .map_err(|_| "database_write_failed")?;
        let module_key = crate::serve::TEMPLATE_MODULES
            .iter()
            .find(|descriptor| descriptor.key == key)
            .map(|descriptor| descriptor.key)
            .ok_or("module_not_found")?;
        emit_module_event(ctx, &mut tx, module_key, enabled, now, &subscribers).await?;
    }
    for (key, scope, value) in plan.policy_writes {
        if scope != Scope::Instance {
            continue;
        }
        sqlx::query(
            "INSERT INTO public.policy_versions (id, scope, account_id, key, value, effective_from, created_by, created_at) \
             VALUES ($1, 'instance', NULL, $2, $3::jsonb, $4::timestamptz, NULL, $4::timestamptz)",
        )
        .bind(ctx.ids.new_uuid_v7())
        .bind(key)
        .bind(value.to_string())
        .bind(now.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(|_| "database_write_failed")?;
        emit_policy_event(ctx, &mut tx, now, &subscribers).await?;
    }
    sqlx::query("UPDATE public.instance_settings SET template_id=$1, updated_at=$2::timestamptz, version=version+1 WHERE id=1")
        .bind(template_id)
        .bind(now.to_rfc3339())
        .execute(tx.conn())
        .await
        .map_err(|_| "database_write_failed")?;
    tx.commit().await.map_err(|_| "database_write_failed")?;
    println!("template_applied");
    Ok(())
}

async fn switch_module(ctx: &Context, key: &str, action: ModuleAction) -> Result<(), &'static str> {
    let descriptors = crate::serve::TEMPLATE_MODULES;
    let enabled_now = enabled_modules(&ctx.db, descriptors).await?;
    let want_enabled = action == ModuleAction::On;
    let outcome = plan_switch(descriptors, &enabled_now, key, want_enabled)
        .map_err(|error| error.code.as_str())?;
    if outcome == SwitchOutcome::Unchanged {
        println!("module_unchanged");
        return Ok(());
    }
    let descriptor = descriptors
        .iter()
        .find(|descriptor| descriptor.key == key)
        .ok_or("not_found")?;
    let mut tx = Tx::begin_admin(&ctx.db, AccountId::from_uuid(Uuid::nil()))
        .await
        .map_err(|_| "database_write_failed")?;
    sqlx::query("SELECT id FROM public.instance_settings WHERE id=1 FOR UPDATE")
        .fetch_one(tx.conn())
        .await
        .map_err(|_| "database_write_failed")?;
    let current_version: i64 =
        sqlx::query_scalar("SELECT version FROM public.instance_modules WHERE module_key=$1")
            .bind(key)
            .fetch_optional(tx.conn())
            .await
            .map_err(|_| "database_query_failed")?
            .unwrap_or(0);
    let next_version = current_version
        .checked_add(1)
        .ok_or("database_write_failed")?;
    let now = ctx.clock.now();
    sqlx::query(
        "INSERT INTO public.instance_modules (module_key, enabled, updated_at, updated_by, version) \
         VALUES ($1, $2, $3::timestamptz, NULL, $4) \
         ON CONFLICT (module_key) DO UPDATE SET enabled=EXCLUDED.enabled, updated_at=EXCLUDED.updated_at, version=EXCLUDED.version",
    )
    .bind(descriptor.key)
    .bind(want_enabled)
    .bind(now.to_rfc3339())
    .bind(next_version)
    .execute(tx.conn())
    .await
    .map_err(|_| "database_write_failed")?;
    emit_module_event(
        ctx,
        &mut tx,
        descriptor.key,
        want_enabled,
        now,
        &Context::subscribers(),
    )
    .await?;
    tx.commit().await.map_err(|_| "database_write_failed")?;
    println!("module_updated");
    Ok(())
}

async fn emit_module_event(
    ctx: &Context,
    tx: &mut Tx,
    key: &'static str,
    enabled: bool,
    now: kinevo_kernel::time::Instant,
    subscribers: &SubscriberRegistry,
) -> Result<(), &'static str> {
    kinevo_db::outbox::emit(
        tx,
        ctx.ids.as_ref(),
        now,
        EventEnvelope {
            id: EventId::from_uuid(ctx.ids.new_uuid_v7()),
            kind: EventKind::ModuleSwitched,
            v: 1,
            account_id: None,
            occurred_at: now,
            payload: EventPayload::new()
                .with_enum("module", EventEnumValue::new(key))
                .with_enum(
                    "enabled",
                    EventEnumValue::new(if enabled { "enabled" } else { "disabled" }),
                ),
        },
        subscribers,
    )
    .await
    .map_err(|_| "outbox_write_failed")?;
    Ok(())
}

async fn emit_policy_event(
    ctx: &Context,
    tx: &mut Tx,
    now: kinevo_kernel::time::Instant,
    subscribers: &SubscriberRegistry,
) -> Result<(), &'static str> {
    kinevo_db::outbox::emit(
        tx,
        ctx.ids.as_ref(),
        now,
        EventEnvelope {
            id: EventId::from_uuid(ctx.ids.new_uuid_v7()),
            kind: EventKind::PolicyChanged,
            v: 1,
            account_id: None,
            occurred_at: now,
            payload: EventPayload::new().with_enum("scope", EventEnumValue::new("instance")),
        },
        subscribers,
    )
    .await
    .map_err(|_| "outbox_write_failed")?;
    Ok(())
}

async fn enabled_modules(
    db: &Db,
    descriptors: &[&'static ModuleDescriptor],
) -> Result<BTreeSet<String>, &'static str> {
    let mut tx = SystemTx::begin(db)
        .await
        .map_err(|_| "database_query_failed")?;
    let rows = sqlx::query("SELECT module_key, enabled FROM public.instance_modules")
        .fetch_all(tx.conn())
        .await
        .map_err(|_| "database_query_failed")?;
    tx.commit().await.map_err(|_| "database_query_failed")?;
    let mut enabled = descriptors
        .iter()
        .filter(|descriptor| descriptor.kind == ModuleKind::Core)
        .map(|descriptor| descriptor.key.to_owned())
        .collect::<BTreeSet<_>>();
    for row in rows {
        let key: String = row
            .try_get("module_key")
            .map_err(|_| "database_query_failed")?;
        let is_enabled: bool = row
            .try_get("enabled")
            .map_err(|_| "database_query_failed")?;
        if is_enabled {
            enabled.insert(key);
        } else {
            enabled.remove(&key);
        }
    }
    Ok(enabled)
}

async fn audit_cli(ctx: &Context, action: AuditAction) -> Result<(), &'static str> {
    let process_context = AccountId::from_uuid(Uuid::nil());
    let mut tx = Tx::begin_admin(&ctx.db, process_context)
        .await
        .map_err(|_| "audit_write_failed")?;
    AuditWriter::new(Arc::clone(&ctx.ids))
        .record(
            &mut tx,
            AuditEntry {
                account_id: None,
                actor_id: None,
                action,
                target_type: None,
                target_id: None,
                details: Details::new().with_detail("via", DetailValue::Str("cli")),
                request_id: None,
                occurred_at: ctx.clock.now(),
            },
        )
        .await
        .map_err(|_| "audit_write_failed")?;
    tx.commit().await.map_err(|_| "audit_write_failed")
}
