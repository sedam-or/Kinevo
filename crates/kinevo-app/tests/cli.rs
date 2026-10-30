#![allow(
    clippy::expect_used,
    clippy::panic,
    reason = "CLI acceptance fixtures should report setup failures directly"
)]

use assert_cmd::Command;
use kinevo_db::{migrate::MigrationSource, testing::TestApp};
use kinevo_kernel::{
    crypto::{KeyLabel, MasterKey, decode_token, derive_subkey, hash_token},
    id::AccountId,
    secret::Secret,
};
use kinevo_mod_admin::infra::secret_store::AdminSecretStore;
use kinevo_ports::platform::{SecretName, SecretScope, SecretStore};
use serde_json::Value;
use sqlx::{Row, types::Uuid};
use std::{
    path::{Path, PathBuf},
    process::Output,
};

const OLD_KEY: &str = "AQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQE=";
const NEW_KEY: &str = "AgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgI=";

fn migration_sources() -> [MigrationSource; 3] {
    [
        kinevo_db::migrations_source(),
        kinevo_mod_identity::migrations(),
        kinevo_mod_admin::migrations(),
    ]
}

async fn app(with_migrations: bool) -> TestApp {
    if with_migrations {
        let sources = migration_sources();
        TestApp::builder()
            .migrations(&sources)
            .build()
            .await
            .expect("shared PostgreSQL should create a disposable database")
    } else {
        TestApp::builder()
            .migrations(&[])
            .build()
            .await
            .expect("shared PostgreSQL should create a disposable database")
    }
}

fn command(app: &TestApp) -> Command {
    let mut command = Command::cargo_bin("kinevo").expect("kinevo binary should build");
    command
        .env("KINEVO_DATABASE_URL", app.app_database_url())
        .env("KINEVO_MIGRATOR_DATABASE_URL", app.migrator_database_url())
        .env("KINEVO_SYSTEM_DATABASE_URL", app.system_database_url())
        .env("KINEVO_SECRET_KEY", OLD_KEY)
        .env("KINEVO_PUBLIC_URL", "https://identity.example.test");
    command
}

fn output(app: &TestApp, args: &[&str]) -> Output {
    command(app)
        .args(args)
        .output()
        .expect("kinevo command should start")
}

fn success(app: &TestApp, args: &[&str]) -> Output {
    let output = output(app, args);
    assert!(
        output.status.success(),
        "command {args:?} failed: {}",
        String::from_utf8_lossy(&output.stderr)
    );
    output
}

async fn insert_account(app: &TestApp, id: AccountId, email: &str, is_admin: bool) {
    sqlx::query(
        "INSERT INTO public.accounts \
         (id, email, display_name, password_hash, timezone, is_instance_admin, status) \
         VALUES ($1, $2, 'Synthetic Account', 'synthetic-password-hash', 'UTC', $3, 'active')",
    )
    .bind(*id.as_uuid())
    .bind(email)
    .bind(is_admin)
    .execute(app.admin_pool())
    .await
    .expect("synthetic account should insert");
}

async fn audit_rows(app: &TestApp, action: &str) -> Vec<(Option<Uuid>, String)> {
    sqlx::query_as(
        "SELECT actor_id, details->>'via' FROM public.audit_log WHERE action=$1 ORDER BY occurred_at, id",
    )
    .bind(action)
    .fetch_all(app.admin_pool())
    .await
    .expect("CLI audit rows should be readable by the test superuser")
}

mod cli {
    use super::*;

    #[tokio::test]
    async fn set_04_cli_setup_token_rotates() {
        let app = app(true).await;
        let first = success(&app, &["admin", "setup-token"]);
        let second = success(&app, &["admin", "setup-token"]);
        let first_token = String::from_utf8(first.stdout)
            .expect("token output should be UTF-8")
            .trim()
            .to_owned();
        let second_token = String::from_utf8(second.stdout)
            .expect("token output should be UTF-8")
            .trim()
            .to_owned();
        assert_ne!(
            first_token, second_token,
            "rotation should replace the token"
        );
        let stored: Vec<u8> =
            sqlx::query_scalar("SELECT setup_token_hash FROM public.instance_settings WHERE id=1")
                .fetch_one(app.admin_pool())
                .await
                .expect("only the setup-token hash should be stored");
        assert_eq!(stored.as_slice(), hash_token(second_token.as_bytes()));
        assert_ne!(stored.as_slice(), hash_token(first_token.as_bytes()));
        assert_eq!(audit_rows(&app, "admin.cli.setup_token").await.len(), 2);
    }

    #[tokio::test]
    async fn setup_token_refused_after_setup() {
        let app = app(true).await;
        sqlx::query("UPDATE public.instance_settings SET setup_completed=true WHERE id=1")
            .execute(app.admin_pool())
            .await
            .expect("setup should be marked completed");
        let output = output(&app, &["admin", "setup-token"]);
        assert_eq!(output.status.code(), Some(1));
        assert_eq!(
            String::from_utf8_lossy(&output.stderr).trim(),
            "setup_already_completed"
        );
        assert_eq!(audit_rows(&app, "admin.cli.setup_token").await.len(), 1);
    }

    #[tokio::test]
    async fn reset_password_prints_one_time_link() {
        let app = app(true).await;
        let account = AccountId::from_uuid(Uuid::from_u128(10));
        insert_account(&app, account, "reset@example.test", false).await;
        sqlx::query("UPDATE public.instance_settings SET base_url='https://identity.example.test' WHERE id=1")
            .execute(app.admin_pool())
            .await
            .expect("synthetic public URL should be set");

        let output = success(&app, &["admin", "reset-password", "reset@example.test"]);
        let link = String::from_utf8(output.stdout)
            .expect("reset link should be UTF-8")
            .trim()
            .to_owned();
        let token = link
            .strip_prefix("https://identity.example.test/reset/")
            .expect("reset command should print the public one-time URL");
        let token_hash = hash_token(&decode_token(token).expect("link token should decode"));
        let stored: Vec<u8> = sqlx::query_scalar(
            "SELECT token_hash FROM public.password_resets WHERE account_id=$1 AND used_at IS NULL",
        )
        .bind(*account.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("reset link should be persisted once as a hash");
        assert_eq!(stored.as_slice(), token_hash);
        assert_eq!(audit_rows(&app, "admin.cli.password_reset").await.len(), 1);
    }

    #[tokio::test]
    async fn list_accounts_no_content_fields() {
        let app = app(true).await;
        let id = AccountId::from_uuid(Uuid::from_u128(20));
        insert_account(&app, id, "list@example.test", true).await;
        let output = success(&app, &["admin", "list-accounts", "--json"]);
        let rows: Value = serde_json::from_slice(&output.stdout).expect("JSON output should parse");
        let account = rows
            .as_array()
            .and_then(|items| items.iter().find(|item| item["id"] == id.to_string()))
            .expect("the seeded account should appear");
        let fields = account
            .as_object()
            .expect("account row should be a JSON object")
            .keys()
            .map(String::as_str)
            .collect::<std::collections::BTreeSet<_>>();
        assert_eq!(
            fields,
            ["email", "id", "role", "status"].into_iter().collect()
        );
        assert_eq!(account["role"], "instance_admin");
        assert_eq!(audit_rows(&app, "admin.cli.list_accounts").await.len(), 1);
    }

    #[tokio::test]
    #[allow(
        clippy::too_many_lines,
        reason = "the acceptance case verifies recovery across committed RLS scopes"
    )]
    async fn adm_fr_06_rotate_rerun_safe() {
        let app = app(true).await;
        let first = AccountId::from_uuid(Uuid::from_u128(30));
        let second = AccountId::from_uuid(Uuid::from_u128(40));
        insert_account(&app, first, "rotate-one@example.test", false).await;
        insert_account(&app, second, "rotate-two@example.test", false).await;
        let old = MasterKey::from_base64(&Secret::new(OLD_KEY.to_owned()))
            .expect("synthetic old key should parse");
        let old_store = AdminSecretStore::new(
            app.db.clone(),
            derive_subkey(&old, KeyLabel::Secrets, 1),
            1,
            app.ids.clone(),
            app.tokens.clone(),
            app.clock.clone(),
        );
        let name = SecretName::parse("ai.api_key").expect("registered secret name should parse");
        old_store
            .put(
                SecretScope::Account(first),
                name,
                Secret::new("synthetic-first-secret".to_owned()),
            )
            .await
            .expect("first secret should be sealed");
        old_store
            .put(
                SecretScope::Account(second),
                name,
                Secret::new("synthetic-second-secret".to_owned()),
            )
            .await
            .expect("second secret should be sealed");

        let original = sqlx::query(
            "SELECT ciphertext, nonce FROM public.secrets WHERE account_id=$1 AND name='ai.api_key'",
        )
        .bind(*second.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("second ciphertext should be saved for restoration");
        let ciphertext: Vec<u8> = original.get("ciphertext");
        let nonce: Vec<u8> = original.get("nonce");
        sqlx::query(
            "UPDATE public.secrets SET ciphertext=$1 WHERE account_id=$2 AND name='ai.api_key'",
        )
        .bind([0_u8, 1, 2].as_slice())
        .bind(*second.as_uuid())
        .execute(app.admin_pool())
        .await
        .expect("test should inject a mid-rotation decryption error");

        let (old_path, new_path) = key_files();
        let failed = rotate_command(&app, &old_path, &new_path);
        assert_eq!(failed.status.code(), Some(1));
        let first_key_id: i16 = sqlx::query_scalar(
            "SELECT key_id FROM public.secrets WHERE account_id=$1 AND name='ai.api_key'",
        )
        .bind(*first.as_uuid())
        .fetch_one(app.admin_pool())
        .await
        .expect("first account key id should be readable");
        assert_eq!(
            first_key_id, 2,
            "the completed account scope remains rotated"
        );
        sqlx::query(
            "UPDATE public.secrets SET ciphertext=$1, nonce=$2 WHERE account_id=$3 AND name='ai.api_key'",
        )
        .bind(ciphertext)
        .bind(nonce)
        .bind(*second.as_uuid())
        .execute(app.admin_pool())
        .await
        .expect("the second scope ciphertext should be restored");
        let resumed = rotate_command(&app, &old_path, &new_path);
        assert!(
            resumed.status.success(),
            "rerun should resume: {}",
            String::from_utf8_lossy(&resumed.stderr)
        );

        let new = MasterKey::from_base64(&Secret::new(NEW_KEY.to_owned()))
            .expect("synthetic new key should parse");
        let new_store = AdminSecretStore::new(
            app.db.clone(),
            derive_subkey(&new, KeyLabel::Secrets, 1),
            2,
            app.ids.clone(),
            app.tokens.clone(),
            app.clock.clone(),
        );
        assert_eq!(
            new_store
                .get(SecretScope::Account(first), name)
                .await
                .expect("first secret should decrypt")
                .expect("first value should remain present")
                .expose(),
            "synthetic-first-secret"
        );
        assert_eq!(
            new_store
                .get(SecretScope::Account(second), name)
                .await
                .expect("second secret should decrypt")
                .expect("second value should remain present")
                .expose(),
            "synthetic-second-secret"
        );
        remove_key_files(&old_path, &new_path);
        assert_eq!(
            audit_rows(&app, "admin.cli.rotate_secret_key").await.len(),
            2
        );
    }

    #[tokio::test]
    async fn tpl_04_cli_apply_idempotent() {
        let app = app(true).await;
        success(&app, &["admin", "apply-template", "student"]);
        let first_count: i64 = sqlx::query_scalar("SELECT count(*) FROM public.policy_versions")
            .fetch_one(app.admin_pool())
            .await
            .expect("policy version count should be queryable");
        success(&app, &["admin", "apply-template", "student"]);
        let second_count: i64 = sqlx::query_scalar("SELECT count(*) FROM public.policy_versions")
            .fetch_one(app.admin_pool())
            .await
            .expect("policy version count should be queryable");
        assert_eq!(
            first_count, second_count,
            "second apply should append no policy versions"
        );
        assert_eq!(audit_rows(&app, "admin.cli.apply_template").await.len(), 2);
    }

    #[tokio::test]
    async fn module_switch_and_core_locked() {
        let app = app(true).await;
        success(&app, &["admin", "apply-template", "student"]);
        success(&app, &["admin", "module", "canvas", "off"]);
        let output = output(&app, &["admin", "module", "tasks", "off"]);
        assert_eq!(output.status.code(), Some(1));
        assert_eq!(
            String::from_utf8_lossy(&output.stderr).trim(),
            "module_core_locked"
        );
        assert_eq!(audit_rows(&app, "admin.cli.module_switch").await.len(), 2);
    }

    #[tokio::test]
    async fn adm_fr_09_every_action_audited() {
        let app = app(true).await;
        let account = AccountId::from_uuid(Uuid::from_u128(50));
        insert_account(&app, account, "audit@example.test", true).await;
        sqlx::query("UPDATE public.instance_settings SET base_url='https://identity.example.test' WHERE id=1")
            .execute(app.admin_pool())
            .await
            .expect("synthetic public URL should be set");

        success(&app, &["admin", "setup-token"]);
        success(&app, &["admin", "reset-password", "audit@example.test"]);
        success(&app, &["admin", "list-accounts", "--json"]);
        let (old_path, new_path) = key_files();
        let rotated = rotate_command(&app, &old_path, &new_path);
        assert!(rotated.status.success());
        success(&app, &["admin", "apply-template", "student"]);
        success(&app, &["admin", "module", "canvas", "off"]);
        remove_key_files(&old_path, &new_path);

        for action in [
            "admin.cli.setup_token",
            "admin.cli.password_reset",
            "admin.cli.list_accounts",
            "admin.cli.rotate_secret_key",
            "admin.cli.apply_template",
            "admin.cli.module_switch",
        ] {
            let rows = audit_rows(&app, action).await;
            assert_eq!(rows.len(), 1, "{action} should be recorded once");
            assert_eq!(rows.as_slice(), &[(None, "cli".to_owned())]);
        }
    }

    #[tokio::test]
    async fn d_50_bootstrap_creates_roles_and_migrates() {
        let app = app(false).await;
        let bootstrap = std::env::var("KINEVO_TEST_DATABASE_URL")
            .expect("shared test database URL should be provided by dev-postgres.sh");
        for _ in 0..2 {
            let mut command = command(&app);
            let output = command
                .env("KINEVO_BOOTSTRAP_DATABASE_URL", &bootstrap)
                .args(["migrate", "--bootstrap"])
                .output()
                .expect("migrate command should start");
            assert!(
                output.status.success(),
                "bootstrap migration failed: {}",
                String::from_utf8_lossy(&output.stderr)
            );
        }
        let role_count: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM pg_roles WHERE rolname IN ('kinevo_migrator','kinevo_app','kinevo_system') AND NOT rolsuper AND NOT rolcreatedb AND NOT rolcreaterole AND NOT rolbypassrls",
        )
        .fetch_one(app.admin_pool())
        .await
        .expect("role restrictions should be queryable");
        assert_eq!(role_count, 3);
        let migrations: i64 =
            sqlx::query_scalar("SELECT count(*) FROM _sqlx_migrations WHERE success")
                .fetch_one(app.migrator_pool())
                .await
                .expect("merged migration history should be queryable");
        assert!(migrations > 0);
        assert_eq!(audit_rows(&app, "admin.cli.list_accounts").await.len(), 0);
    }
}

fn key_files() -> (PathBuf, PathBuf) {
    let prefix = format!("kinevo-cli-key-{}", std::process::id());
    let old_path = std::env::temp_dir().join(format!("{prefix}-old"));
    let new_path = std::env::temp_dir().join(format!("{prefix}-new"));
    std::fs::write(&old_path, OLD_KEY).expect("synthetic old key file should be written");
    std::fs::write(&new_path, NEW_KEY).expect("synthetic new key file should be written");
    (old_path, new_path)
}

fn rotate_command(app: &TestApp, old_path: &Path, new_path: &Path) -> Output {
    let old_path = old_path.to_str().expect("temporary path should be UTF-8");
    let new_path = new_path.to_str().expect("temporary path should be UTF-8");
    command(app)
        .args([
            "admin",
            "rotate-secret-key",
            "--old",
            old_path,
            "--new",
            new_path,
        ])
        .output()
        .expect("rotation command should start")
}

fn remove_key_files(old_path: &Path, new_path: &Path) {
    let _ = std::fs::remove_file(old_path);
    let _ = std::fs::remove_file(new_path);
}
