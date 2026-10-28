#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    reason = "synthetic database fixtures fail loudly when their setup is invalid"
)]

use futures::future::BoxFuture;
use kinevo_db::{
    Tx,
    testing::{TestApp, TestIds, isolation::IsolationFixture},
};
use kinevo_kernel::id::{AccountId, IdGen};
use sqlx::types::Uuid;

pub const ISOLATION_FIXTURES: &[IsolationFixture] = &[
    IsolationFixture {
        table: "accounts",
        insert: insert_account_fixture,
        touch_sql: "SET display_name = display_name",
    },
    IsolationFixture {
        table: "refresh_token_families",
        insert: insert_family_fixture,
        touch_sql: "SET id = id",
    },
    IsolationFixture {
        table: "refresh_tokens",
        insert: insert_refresh_token_fixture,
        touch_sql: "SET id = id",
    },
    IsolationFixture {
        table: "recovery_codes",
        insert: insert_recovery_code_fixture,
        touch_sql: "SET id = id",
    },
    IsolationFixture {
        table: "password_resets",
        insert: insert_password_reset_fixture,
        touch_sql: "SET id = id",
    },
    IsolationFixture {
        table: "account_exports",
        insert: insert_account_export_fixture,
        touch_sql: "SET state = state",
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

pub async fn seed_account_with_auth_rows(app: &TestApp, account: AccountId, ordinal: u8) {
    let mut tx = Tx::begin(&app.db, account)
        .await
        .expect("account transaction should begin");
    let email = format!("account-{ordinal}@example.test");
    insert_account(&mut tx, account, &email)
        .await
        .expect("account fixture should insert");
    tx.commit()
        .await
        .expect("account transaction should commit");

    let family_id = *account.as_uuid();
    sqlx::query(
        "INSERT INTO public.refresh_token_families \
         (id, account_id, created_at, last_used_at, absolute_expires_at) \
         VALUES ($1, $2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '1 day')",
    )
    .bind(family_id)
    .bind(family_id)
    .execute(app.admin_pool())
    .await
    .expect("family fixture should insert");

    let row_id = app.ids.new_uuid_v7();
    sqlx::query(
        "INSERT INTO public.refresh_tokens \
         (id, family_id, account_id, token_hash, issued_at, idle_expires_at) \
         VALUES ($1, $2, $3, $4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '1 day')",
    )
    .bind(row_id)
    .bind(family_id)
    .bind(family_id)
    .bind(synthetic_hash(row_id, ordinal))
    .execute(app.admin_pool())
    .await
    .expect("refresh-token fixture should insert");

    let reset_id = app.ids.new_uuid_v7();
    sqlx::query(
        "INSERT INTO public.password_resets (id, account_id, token_hash, expires_at, created_at) \
         VALUES ($1, $2, $3, CURRENT_TIMESTAMP + INTERVAL '1 day', CURRENT_TIMESTAMP)",
    )
    .bind(reset_id)
    .bind(family_id)
    .bind(synthetic_hash(reset_id, ordinal.wrapping_add(1)))
    .execute(app.admin_pool())
    .await
    .expect("password-reset fixture should insert");
}

fn insert_account_fixture<'a>(
    tx: &'a mut Tx,
    ids: &'a TestIds,
) -> BoxFuture<'a, Result<(), sqlx::Error>> {
    Box::pin(async move {
        let email = format!("{}@example.test", ids.row_id);
        sqlx::query(
            "INSERT INTO public.accounts (id, email, display_name, password_hash, timezone) \
             VALUES ($1, $2, $3, $4, $5)",
        )
        .bind(ids.row_id)
        .bind(email)
        .bind("Example Account")
        .bind("synthetic-password-hash")
        .bind("UTC")
        .execute(tx.conn())
        .await?;
        Ok(())
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
        let account_id = *ids.account_id.as_uuid();
        sqlx::query(
            "INSERT INTO public.refresh_tokens \
             (id, family_id, account_id, token_hash, issued_at, idle_expires_at) \
             VALUES ($1, $2, $3, $4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '1 day')",
        )
        .bind(ids.row_id)
        .bind(account_id)
        .bind(account_id)
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
            "INSERT INTO public.password_resets (id, account_id, token_hash, expires_at, created_at) \
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

fn synthetic_hash(row_id: Uuid, salt: u8) -> [u8; 32] {
    let mut hash = [salt; 32];
    hash[..16].copy_from_slice(row_id.as_bytes());
    hash
}
