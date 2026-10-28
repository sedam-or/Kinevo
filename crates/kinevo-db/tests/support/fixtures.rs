#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    reason = "database fixtures fail loudly when their synthetic setup is invalid"
)]

use futures::future::BoxFuture;
use kinevo_db::{
    Tx,
    testing::{TestIds, isolation::IsolationFixture},
};
use kinevo_kernel::{id::AccountId, time::Instant};
use sqlx::types::Uuid;

pub const ALL_ISOLATION_FIXTURES: &[IsolationFixture] = &[
    IsolationFixture {
        table: "accounts",
        insert: insert_account_fixture,
        touch_sql: "SET display_name = display_name",
        update_allowed: true,
        delete_allowed: false,
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
        table: "recovery_codes",
        insert: insert_recovery_code_fixture,
        touch_sql: "SET id = id",
        update_allowed: true,
        delete_allowed: true,
    },
    IsolationFixture {
        table: "password_resets",
        insert: insert_password_reset_fixture,
        touch_sql: "SET id = id",
        update_allowed: true,
        delete_allowed: true,
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
];

pub async fn insert_account(
    tx: &mut Tx,
    account: AccountId,
    email: &str,
) -> Result<(), sqlx::Error> {
    sqlx::query(
        "INSERT INTO public.accounts (id, email, display_name, password_hash, timezone) \
         VALUES ($1, $2, $3, $4, $5)",
    )
    .bind(*account.as_uuid())
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
        insert_account(tx, ids.account_id, &format!("{}@example.test", ids.row_id)).await
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
             VALUES ($1, $2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '1 day')",
        )
        .bind(account_id)
        .bind(account_id)
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
        sqlx::query(
            "INSERT INTO public.refresh_tokens \
             (id, family_id, account_id, token_hash, issued_at, idle_expires_at) \
             VALUES ($1, $2, $3, $4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '1 day')",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
        .bind(*ids.account_id.as_uuid())
        .bind(synthetic_hash(ids.row_id, 1))
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
             VALUES ($1, $2, $3, CURRENT_TIMESTAMP)",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
        .bind(synthetic_hash(ids.row_id, 2))
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
            "INSERT INTO public.password_resets \
             (id, account_id, token_hash, expires_at, created_at) \
             VALUES ($1, $2, $3, CURRENT_TIMESTAMP + INTERVAL '1 day', CURRENT_TIMESTAMP)",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
        .bind(synthetic_hash(ids.row_id, 3))
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
             VALUES ($1, $2, 'queued', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '1 day')",
        )
        .bind(ids.row_id)
        .bind(*ids.account_id.as_uuid())
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
        .bind(
            Instant::parse_rfc3339("2026-11-02T00:00:00Z")
                .expect("fixed fixture instant")
                .to_rfc3339(),
        )
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
        .bind("2026-11-02T00:00:00Z")
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
