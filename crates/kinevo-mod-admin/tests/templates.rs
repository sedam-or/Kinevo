#![allow(
    clippy::expect_used,
    clippy::indexing_slicing,
    reason = "acceptance tests use validated synthetic registries and fixtures"
)]

use std::collections::BTreeSet;

use kinevo_kernel::{
    errors::ErrorCode,
    policy::{EffectiveDating, PolicySpec, PolicyType, PolicyValue, Scope},
};
use kinevo_mod_admin::{
    app::templates::{TemplateRegistry, apply_plan},
    domain::template::{ValidationCtx, parse_template},
};
use kinevo_ports::module::{ModuleDescriptor, ModuleKind, PermissionDef};
use serde_json::json;

const EMPTY_PERMISSIONS: &[PermissionDef] = &[];
const EMPTY_KEYS: &[&str] = &[];
const BOTH_SCOPES: &[Scope] = &[Scope::Instance, Scope::Account];
const MODES: &[&str] = &["open", "closed"];

const fn module(
    key: &'static str,
    kind: ModuleKind,
    requires: &'static [&'static str],
) -> ModuleDescriptor {
    ModuleDescriptor {
        key,
        kind,
        requires,
        permissions: EMPTY_PERMISSIONS,
        policy_keys: EMPTY_KEYS,
        nav: &[],
        settings_panels: &[],
        message_namespaces: &[],
        jobs: &[],
        job_schedules: &[],
        subscriptions: &[],
    }
}

static IDENTITY: ModuleDescriptor = module("identity", ModuleKind::Core, &[]);
static WORKSPACES: ModuleDescriptor = module("workspaces", ModuleKind::Core, &["identity"]);
static ADMIN: ModuleDescriptor = module("admin", ModuleKind::Core, &["identity"]);
static GOALS: ModuleDescriptor = module("goals", ModuleKind::Core, &["workspaces"]);
static TASKS: ModuleDescriptor = module("tasks", ModuleKind::Core, &["goals"]);
static LANDSCAPE: ModuleDescriptor = module("landscape", ModuleKind::Core, &["identity"]);
static SCHEDULER: ModuleDescriptor = module("scheduler", ModuleKind::Core, &["tasks", "landscape"]);
static EXECUTION: ModuleDescriptor = module("execution", ModuleKind::Core, &["tasks", "scheduler"]);
static PROGRESS: ModuleDescriptor = module("progress", ModuleKind::Core, &["tasks", "goals"]);
static SYNC: ModuleDescriptor = module("sync", ModuleKind::Toggleable, &["tasks"]);
static AI: ModuleDescriptor = module("ai", ModuleKind::Toggleable, &["goals", "tasks"]);
static KNOWLEDGE: ModuleDescriptor = module("knowledge", ModuleKind::Toggleable, &["workspaces"]);
static CANVAS: ModuleDescriptor = module("canvas", ModuleKind::Toggleable, &["workspaces"]);
static BILLING: ModuleDescriptor = module("billing", ModuleKind::Toggleable, &["identity"]);
static MODULES: &[&ModuleDescriptor] = &[
    &IDENTITY,
    &ADMIN,
    &WORKSPACES,
    &GOALS,
    &TASKS,
    &LANDSCAPE,
    &SCHEDULER,
    &EXECUTION,
    &PROGRESS,
    &SYNC,
    &AI,
    &KNOWLEDGE,
    &CANVAS,
    &BILLING,
];

static REGISTRATION: PolicySpec = PolicySpec {
    key: "auth.registration.mode",
    ty: PolicyType::Enum { allowed: MODES },
    default: PolicyValue::Str("closed"),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: false,
};
static DAY_START: PolicySpec = PolicySpec {
    key: "scheduler.day_start",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(8 * 60),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static DAY_END: PolicySpec = PolicySpec {
    key: "scheduler.day_end",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(22 * 60),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static EARLIEST_TIME: PolicySpec = PolicySpec {
    key: "scheduler.anchor.earliest_time",
    ty: PolicyType::LocalTime,
    default: PolicyValue::LocalTime(6 * 60),
    scopes: BOTH_SCOPES,
    effective_dating: EffectiveDating::Immediate,
    snapshot: true,
};
static POLICIES: &[&PolicySpec] = &[&REGISTRATION, &DAY_START, &DAY_END, &EARLIEST_TIME];
static TERMS: &[&str] = &[
    "term.goal",
    "term.milestone",
    "term.task",
    "term.workspace",
    "term.hard_landscape",
    "term.focus_session",
];

fn ctx() -> ValidationCtx<'static> {
    ValidationCtx::new(MODULES, POLICIES, TERMS)
}

fn assert_template_invalid<T: std::fmt::Debug>(
    result: Result<T, kinevo_kernel::errors::AppError>,
    path: &str,
) {
    let error = result.expect_err("template should be rejected");
    assert_eq!(error.code, ErrorCode::TemplateInvalid);
    let details = serde_json::to_value(error).expect("error serializes");
    assert_eq!(details["details"]["path"], path);
}

#[test]
fn tpl_shipped_templates_valid() {
    let validation = ctx();
    let registry = TemplateRegistry::embedded(&validation).expect("embedded templates validate");
    let ids = registry
        .list()
        .into_iter()
        .map(|template| template.id)
        .collect::<Vec<_>>();
    assert_eq!(ids, ["cloud", "personal", "professional", "student"]);
}

#[test]
fn tpl_01_unknown_key_has_path() {
    let source = include_str!("fixtures/templates/bad_unknown_policy.toml");
    assert_template_invalid(
        parse_template(source, &ctx()),
        "policies.\"scheduler.unknown\"",
    );
}

#[test]
fn tpl_01_dependency_unmet_has_path() {
    let source = include_str!("fixtures/templates/bad_dependency.toml");
    let goals = module("goals", ModuleKind::Toggleable, &["workspaces"]);
    let tasks = module("tasks", ModuleKind::Toggleable, &["goals"]);
    let ai = module("ai", ModuleKind::Toggleable, &["goals", "tasks"]);
    let goals_off = [&IDENTITY, &WORKSPACES, &goals, &tasks, &ai];
    let validation = ValidationCtx::new(&goals_off, POLICIES, TERMS);
    assert_template_invalid(parse_template(source, &validation), "modules.ai");
}

#[test]
fn tpl_02_no_code_urls_secrets() {
    for source in [
        include_str!("fixtures/templates/bad_url.toml"),
        include_str!("fixtures/templates/bad_script.toml"),
        include_str!("fixtures/templates/bad_secret.toml"),
    ] {
        let error = parse_template(source, &ctx()).expect_err("unsafe field must be rejected");
        assert_eq!(error.code, ErrorCode::TemplateInvalid);
    }
}

#[test]
fn tpl_03_terms_registry_only() {
    let registered = concat!(
        "id = \"label-test\"\n",
        "name_key = \"templates.label_test.name\"\n",
        "description_key = \"templates.label_test.description\"\n",
        "[modules]\n",
        "sync = true\n",
        "[terms]\n",
        "\"term.goal\" = \"templates.label_test.terms.goal\"\n",
    );
    let template = parse_template(registered, &ctx()).expect("registered term should pass");
    assert_eq!(
        template.terms["term.goal"],
        "templates.label_test.terms.goal"
    );
    assert_eq!(template.modules.get("sync"), Some(&true));
    assert_eq!(template.policies.len(), 0);
    assert_eq!(template.terms.len(), 1);

    let unregistered = registered.replace("term.goal", "term.unregistered");
    assert_template_invalid(
        parse_template(&unregistered, &ctx()),
        "terms.\"term.unregistered\"",
    );
}

#[test]
fn tpl_04_apply_is_idempotent() {
    let validation = ctx();
    let registry = TemplateRegistry::embedded(&validation).expect("embedded templates validate");
    let template = registry.get("personal").expect("personal template exists");
    let enabled = BTreeSet::from(["sync".to_owned(), "knowledge".to_owned()]);
    let current = |key: &str, _scope: Scope| match key {
        "auth.registration.mode" => Some(json!("closed")),
        _ => None,
    };
    let plan = apply_plan(template, &enabled, &current);
    assert_eq!(plan.module_changes, Vec::<(String, bool)>::new());
    assert_eq!(
        plan.policy_writes,
        Vec::<(String, Scope, serde_json::Value)>::new()
    );
}
