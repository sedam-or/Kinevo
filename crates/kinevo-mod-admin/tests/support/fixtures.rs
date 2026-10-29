#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    reason = "synthetic database fixtures fail loudly when their setup is invalid"
)]

use futures::future::BoxFuture;
use kinevo_db::{
    Tx,
    testing::{TestIds, isolation::IsolationFixture},
};
use sqlx::types::Uuid;

const FIXED_NOW: &str = "2026-11-02T00:00:00Z";

pub const ISOLATION_FIXTURES: &[IsolationFixture] = &[
    IsolationFixture {
        table: "accounts",
        insert: insert_account_fixture,
        touch_sql: "SET display_name = display_name",
        update_allowed: true,
        delete_allowed: false,
    },
    IsolationFixture {
        table: "account_exports",
        insert: insert_account_export_fixture,
        touch_sql: "SET state = state",
        update_allowed: true,
        delete_allowed: true,
    },
    IsolationFixture {
        table: "audit_log",
        insert: insert_audit_fixture,
        touch_sql: "SET action = action",
        update_allowed: false,
        delete_allowed: false,
    },
    IsolationFixture {
        table: "idempotency_keys",
        insert: insert_idempotency_fixture,
        touch_sql: "SET method = method",
        update_allowed: true,
        delete_allowed: false,
    },
    IsolationFixture {
        table: "password_resets",
        insert: insert_password_reset_fixture,
        touch_sql: "SET id = id",
        update_allowed: true,
        delete_allowed: true,
    },
    IsolationFixture {
        table: "policy_versions",
        insert: insert_policy_version_fixture,
        touch_sql: "SET value = value",
        update_allowed: false,
        delete_allowed: false,
    },
    IsolationFixture {
        table: "recovery_codes",
        insert: insert_recovery_code_fixture,
        touch_sql: "SET id = id",
        update_allowed: true,
        delete_allowed: true,
    },
    IsolationFixture {
        table: "refresh_token_families",
        insert: insert_family_fixture,
        touch_sql: "SET id = id",
        update_allowed: true,
        delete_allowed: true,
    },
    IsolationFixture {
        table: "refresh_tokens",
        insert: insert_refresh_token_fixture,
        touch_sql: "SET id = id",
        update_allowed: true,
        delete_allowed: true,
    },
    IsolationFixture {
        table: "secrets",
        insert: insert_secret_fixture,
        touch_sql: "SET last4 = last4",
        update_allowed: true,
        delete_allowed: true,
    },
];

pub async fn insert_account(tx: &mut Tx, account_id: Uuid, email: &str) -> Result<(), sqlx::Error> {
    sqlx::query(
        "INSERT INTO public.accounts (id, email, display_name, password_hash, timezone) \
         VALUES ($1, $2, $3, $4, $5)",
    )
    .bind(account_id)
    .bind(email)
    .bind("Example Account")
    .bind("synthetic-password-hash")
    .bind("UTC")
    .execute(tx.conn())
    .await?;
    Ok(())
}

fn insert_account_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        insert_account(tx, ids.row_id, &format!("{}@example.test", ids.row_id)).await
    })
}

fn insert_family_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        let account_id = *ids.account_id.as_uuid();
        sqlx::query(
            "INSERT INTO public.refresh_token_families \
             (id, account_id, created_at, last_used_at, absolute_expires_at) \
             VALUES ($1, $2, $3::timestamptz, $3::timestamptz, $3::timestamptz + INTERVAL '1 day')",
        )
        .bind(account_id)
        .bind(account_id)
        .bind(FIXED_NOW)
        .execute(tx.conn())
        .await?;
        Ok(())
    })
}

fn insert_refresh_token_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        let account_id = *ids.account_id.as_uuid();
        sqlx::query(
            "INSERT INTO public.refresh_tokens \
             (id, family_id, account_id, token_hash, issued_at, idle_expires_at) \
             VALUES ($1, $2, $2, $3, $4::timestamptz, $4::timestamptz + INTERVAL '1 day')",
        )
        .bind(ids.row_id)
        .bind(account_id)
        .bind(synthetic_hash(ids.row_id, 1).as_slice())
        .bind(FIXED_NOW)
        .execute(tx.conn())
        .await?;
        Ok(())
    })
}

fn insert_recovery_code_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        sqlx::query(
            "INSERT INTO public.recovery_codes (id, account_id, code_hash, created_at) \
             VALUES ($1, $2, $3, $4::timestamptz)",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
        .bind(synthetic_hash(ids.row_id, 2).as_slice())
        .bind(FIXED_NOW)
        .execute(tx.conn())
        .await?;
        Ok(())
    })
}

fn insert_password_reset_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        sqlx::query(
            "INSERT INTO public.password_resets (id, account_id, token_hash, expires_at, created_at) \
             VALUES ($1, $2, $3, $4::timestamptz + INTERVAL '1 day', $4::timestamptz)",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
        .bind(synthetic_hash(ids.row_id, 3).as_slice())
        .bind(FIXED_NOW)
        .execute(tx.conn())
        .await?;
        Ok(())
    })
}

fn insert_account_export_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        sqlx::query(
            "INSERT INTO public.account_exports (id, account_id, state, created_at, expires_at) \
             VALUES ($1, $2, 'queued', $3::timestamptz, $3::timestamptz + INTERVAL '1 day')",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
        .bind(FIXED_NOW)
        .execute(tx.conn())
        .await?;
        Ok(())
    })
}

fn insert_audit_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        sqlx::query(
            "INSERT INTO public.audit_log (id, account_id, action, details, occurred_at) \
             VALUES ($1, $2, 'auth.login', '{}'::jsonb, $3::timestamptz)",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
        .bind(FIXED_NOW)
        .execute(tx.conn())
        .await?;
        Ok(())
    })
}

fn insert_idempotency_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        let key = format!("fixture-{}", ids.row_id);
        sqlx::query(
            "INSERT INTO public.idempotency_keys \
             (account_id, key, method, path, request_hash, created_at, expires_at) \
             VALUES ($1, $2, 'POST', '/api/v1/account', $3, $4::timestamptz, $4::timestamptz + INTERVAL '1 day')",
        )
        .bind(*ids.account_id.as_uuid())
        .bind(key)
        .bind(synthetic_hash(ids.row_id, 4).as_slice())
        .bind(FIXED_NOW)
        .execute(tx.conn())
        .await?;
        Ok(())
    })
}

fn insert_policy_version_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        sqlx::query(
            "INSERT INTO public.policy_versions \
             (id, scope, account_id, key, value, effective_from, created_at) \
             VALUES ($1, 'account', $2, 'auth.access_token.ttl', '600'::jsonb, $3::timestamptz, $3::timestamptz)",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
        .bind(FIXED_NOW)
        .execute(tx.conn())
        .await?;
        Ok(())
    })
}

fn insert_secret_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        sqlx::query(
            "INSERT INTO public.secrets \
             (id, account_id, name, ciphertext, nonce, key_id, last4, created_at, updated_at) \
             VALUES ($1, $2, 'identity.totp_seed', $3, $4, 1, 'test', $5::timestamptz, $5::timestamptz)",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
        .bind([0xA5_u8; 32].as_slice())
        .bind([0x5A_u8; 12].as_slice())
        .bind(FIXED_NOW)
        .execute(tx.conn())
        .await?;
        Ok(())
    })
}

fn synthetic_hash(row_id: Uuid, salt: u8) -> [u8; 32] {
    let mut hash = [salt; 32];
    hash[..16].copy_from_slice(row_id.as_bytes());
    hash
}
