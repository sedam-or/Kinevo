//! Acceptance tests for module graph, switching, export, and provisioning contracts.

#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "tests exercise the port contracts"
)]

use super::*;
use async_trait::async_trait;
use kinevo_kernel::{
    errors::{AppError, DetailValue, ErrorCode},
    id::AccountId,
    validate::tz::{IanaTz, Locale},
};
use std::{
    collections::BTreeSet,
    future::Future,
    sync::{Arc, Mutex},
    task::{Context, Poll, Waker},
};

const fn descriptor(
    key: &'static str,
    kind: ModuleKind,
    requires: &'static [&'static str],
) -> ModuleDescriptor {
    ModuleDescriptor {
        key,
        kind,
        requires,
        permissions: &[],
        policy_keys: &[],
        nav: &[],
        settings_panels: &[],
        message_namespaces: &[],
        jobs: &[],
        job_schedules: &[],
        subscriptions: &[],
    }
}

fn key(value: &str) -> ModuleKey {
    ModuleKey::parse(value).expect("synthetic module key should be valid")
}

#[test]
fn mod_03_graph_validation() {
    const IDENTITY: ModuleDescriptor = descriptor("identity", ModuleKind::Core, &[]);
    let workspace = descriptor("workspaces", ModuleKind::Core, &["identity"]);
    assert!(validate_graph(&[&IDENTITY, &workspace]).is_ok());

    let a = descriptor("module_a", ModuleKind::Toggleable, &["module_b"]);
    let b = descriptor("module_b", ModuleKind::Toggleable, &["module_a"]);
    let unknown = descriptor("unknown_req", ModuleKind::Toggleable, &["absent"]);
    let duplicate = descriptor("identity", ModuleKind::Core, &[]);
    assert!(validate_graph(&[&a, &b]).is_err());
    assert!(validate_graph(&[&unknown]).is_err());
    assert!(validate_graph(&[&IDENTITY, &duplicate]).is_err());

    let addon = descriptor("addon", ModuleKind::Toggleable, &[]);
    let mut core = descriptor("core_mod", ModuleKind::Core, &["addon"]);
    assert!(validate_graph(&[&addon, &core]).is_err());
    core.requires = &[];
    core.permissions = &[PermissionDef {
        key: "other.resource.read",
        owner: false,
    }];
    assert!(validate_graph(&[&core]).is_err());

    let mut repeated = descriptor("module_a", ModuleKind::Toggleable, &[]);
    repeated.permissions = &[
        PermissionDef {
            key: "module_a.resource.read",
            owner: false,
        },
        PermissionDef {
            key: "module_a.resource.read",
            owner: true,
        },
    ];
    repeated.jobs = &["module_a.job", "module_a.job"];
    assert!(validate_graph(&[&repeated]).is_err());
}

#[test]
fn mod_08_permission_keys_accept_spec_admin_segments() {
    assert!(PermissionKey::parse("identity.account.read").is_ok());
    assert!(PermissionKey::parse("identity.admin.accounts.read").is_ok());
    assert!(PermissionKey::parse("identity.admin.password_reset.issue").is_ok());
}

#[test]
fn mod_03_enable_rules() {
    let goals = descriptor("goals", ModuleKind::Core, &[]);
    let tasks = descriptor("tasks", ModuleKind::Core, &["goals"]);
    let ai = descriptor("ai", ModuleKind::Toggleable, &["goals", "tasks"]);
    let all = [&goals, &tasks, &ai];
    let enabled = BTreeSet::from([key("goals"), key("tasks")]);
    assert_eq!(
        plan_switch(&all, &enabled, "ai", true),
        Ok(SwitchOutcome::Changed)
    );

    let enabled = BTreeSet::from([key("tasks")]);
    let error = plan_switch(&all, &enabled, "ai", true).expect_err("dependency is missing");
    assert_eq!(error.code, ErrorCode::ModuleDependencyUnmet);
    assert_eq!(
        error.details.as_map().get("missing"),
        Some(&DetailValue::StrList(vec!["goals".into()]))
    );
}

#[test]
fn mod_03_disable_with_dependents() {
    let knowledge = descriptor("knowledge", ModuleKind::Toggleable, &[]);
    let canvas = descriptor("canvas", ModuleKind::Toggleable, &["knowledge"]);
    let enabled = BTreeSet::from([key("knowledge"), key("canvas")]);
    let error = plan_switch(&[&knowledge, &canvas], &enabled, "knowledge", false)
        .expect_err("enabled dependents must prevent disable");
    assert_eq!(error.code, ErrorCode::ModuleRequiredBy);
    assert_eq!(
        error.details.as_map().get("dependents"),
        Some(&DetailValue::StrList(vec!["canvas".into()]))
    );
}

#[test]
fn mod_core_locked_and_idempotent_enable() {
    let identity = descriptor("identity", ModuleKind::Core, &[]);
    let ai = descriptor("ai", ModuleKind::Toggleable, &["identity"]);
    let all = [&identity, &ai];
    let enabled = BTreeSet::from([key("identity"), key("ai")]);
    let error = plan_switch(&all, &enabled, "identity", false).expect_err("core is locked");
    assert_eq!(error.code, ErrorCode::ModuleCoreLocked);
    assert_eq!(
        plan_switch(&all, &enabled, "ai", true),
        Ok(SwitchOutcome::Unchanged)
    );
}

struct Exporter(&'static [&'static str]);

#[async_trait]
impl AccountExporter<()> for Exporter {
    fn sections(&self) -> &'static [&'static str] {
        self.0
    }
    async fn export(
        &self,
        _tx: &mut (),
        _account: AccountId,
    ) -> Result<Vec<ExportSection>, AppError> {
        Ok(Vec::new())
    }
}

#[test]
fn mod_11_duplicate_export_section() {
    let a = Exporter(&["workspaces"]);
    let b = Exporter(&["workspaces"]);
    let exporters: [&dyn AccountExporter<()>; 2] = [&a, &b];
    let error = check_exporters(&exporters).expect_err("duplicate section must fail");
    assert_eq!(error.code, ErrorCode::InternalError);
    assert_eq!(
        error.details.as_map().get("name"),
        Some(&DetailValue::Str("workspaces"))
    );
}

struct Provisioner(&'static str, bool, Arc<Mutex<Vec<&'static str>>>);

#[async_trait]
impl AccountProvisioner<()> for Provisioner {
    fn module(&self) -> &'static str {
        self.0
    }
    async fn provision(&self, _tx: &mut (), _account: &ProvisionedAccount) -> Result<(), AppError> {
        self.2.lock().expect("call log lock").push(self.0);
        if self.1 {
            Err(AppError::new(ErrorCode::InternalError))
        } else {
            Ok(())
        }
    }
}

fn block_on<F: Future>(future: F) -> F::Output {
    let mut cx = Context::from_waker(Waker::noop());
    let mut future = Box::pin(future);
    loop {
        if let Poll::Ready(output) = future.as_mut().poll(&mut cx) {
            return output;
        }
        std::thread::yield_now();
    }
}

#[test]
fn mod_10_provisioners_run_in_order_and_fail_fast() {
    let calls = Arc::new(Mutex::new(Vec::new()));
    let workspaces = Provisioner("workspaces", false, Arc::clone(&calls));
    let scheduler = Provisioner("scheduler", true, Arc::clone(&calls));
    let order: [&dyn AccountProvisioner<()>; 2] = [&workspaces, &scheduler];
    let account = ProvisionedAccount {
        id: AccountId::parse("0190b2cc-1c74-7000-8000-000000000001").expect("synthetic UUID"),
        display_name: "Example Account".into(),
        timezone: IanaTz::parse("Asia/Jakarta").expect("synthetic timezone"),
        locale: Locale::Id,
        is_instance_admin: false,
    };
    assert_eq!(
        block_on(run_provisioners(&order, &mut (), &account))
            .err()
            .map(|e| e.code),
        Some(ErrorCode::InternalError)
    );
    assert_eq!(
        *calls.lock().expect("call log lock"),
        ["workspaces", "scheduler"]
    );
}
