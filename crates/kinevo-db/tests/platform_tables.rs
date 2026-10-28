#![cfg(feature = "testing")]
#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "platform database acceptance checks use isolated synthetic fixtures"
)]

mod support;

use kinevo_db::{
    SystemTx, Tx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    isolation::verify_isolation,
    require_db,
    retention::{prune_audit, prune_idempotency, prune_jobs, prune_outbox},
    testing::{TestApp, isolation::run_isolation},
};
use kinevo_kernel::{
    errors::{DetailValue, Details},
    id::{AccountId, IdGen},
    time::Instant,
};
use sqlx::{AssertSqlSafe, error::DatabaseError, types::Uuid};

type ColumnSpec = (&'static str, &'static str, bool);
type TableSpec = (&'static str, &'static [ColumnSpec]);

const EXPECTED_COLUMNS: &[TableSpec] = &[
    (
        "audit_log",
        &[
            ("id", "uuid", false),
            ("account_id", "uuid", true),
            ("actor_id", "uuid", true),
            ("action", "text", false),
            ("target_type", "text", true),
            ("target_id", "text", true),
            ("details", "jsonb", false),
            ("request_id", "text", true),
            ("occurred_at", "timestamptz", false),
        ],
    ),
    (
        "outbox_events",
        &[
            ("seq", "int8", false),
            ("id", "uuid", false),
            ("account_id", "uuid", true),
            ("kind", "text", false),
            ("payload", "jsonb", false),
            ("created_at", "timestamptz", false),
        ],
    ),
    (
        "outbox_deliveries",
        &[
            ("event_id", "uuid", false),
            ("subscriber", "text", false),
            ("state", "text", false),
            ("attempts", "int4", false),
            ("next_attempt_at", "timestamptz", false),
            ("last_error_code", "text", true),
            ("delivered_at", "timestamptz", true),
        ],
    ),
    (
        "jobs",
        &[
            ("id", "uuid", false),
            ("account_id", "uuid", true),
            ("kind", "text", false),
            ("unique_key", "text", true),
            ("payload", "jsonb", false),
            ("state", "text", false),
            ("run_at", "timestamptz", false),
            ("attempts", "int4", false),
            ("max_attempts", "int4", false),
            ("locked_by", "text", true),
            ("locked_until", "timestamptz", true),
            ("last_error_code", "text", true),
            ("created_at", "timestamptz", false),
            ("finished_at", "timestamptz", true),
        ],
    ),
    (
        "idempotency_keys",
        &[
            ("account_id", "uuid", false),
            ("key", "text", false),
            ("method", "text", false),
            ("path", "text", false),
            ("request_hash", "bytea", false),
            ("status_code", "int2", true),
            ("response_body", "bytea", true),
            ("created_at", "timestamptz", false),
            ("expires_at", "timestamptz", false),
        ],
    ),
];

fn instant(value: &str) -> Instant {
    Instant::parse_rfc3339(value).expect("fixture instant should be valid")
}

fn entry(account_id: Option<AccountId>, action: AuditAction, occurred_at: Instant) -> AuditEntry {
    AuditEntry {
        account_id,
        actor_id: None,
        action,
        target_type: account_id.map(|_| AuditTarget::ACCOUNT),
        target_id: account_id.map(|id| id.to_string()),
        details: Details::new()
            .with_detail("status", DetailValue::Str("active"))
            .with_detail("count", DetailValue::Int(1)),
        request_id: None,
        occurred_at,
    }
}

async fn seed_account(app: &TestApp, account_id: AccountId, email: &str) {
    let mut tx = Tx::begin(&app.db, account_id)
        .await
        .expect("account transaction should begin");
    support::fixtures::insert_account(&mut tx, account_id, email)
        .await
        .expect("account fixture should insert");
    tx.commit()
        .await
        .expect("account transaction should commit");
}

fn is_privilege_error(error: &sqlx::Error) -> bool {
    error
        .as_database_error()
        .and_then(DatabaseError::code)
        .is_some_and(|code| code.as_ref() == "42501")
}

#[tokio::test]
#[allow(
    clippy::too_many_lines,
    reason = "the acceptance test compares all schema metadata in one named contract test"
)]
async fn dom_schema_matches_spec_ddl() {
    let sources = support::migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);

    for &(table, expected) in EXPECTED_COLUMNS {
        let actual = sqlx::query_as::<_, (String, String, bool)>(
            "SELECT column_name::text, udt_name::text, is_nullable = 'YES' \
             FROM information_schema.columns \
             WHERE table_schema = 'public' AND table_name = $1 ORDER BY ordinal_position",
        )
        .bind(table)
        .fetch_all(app.admin_pool())
        .await
        .expect("platform table columns should be queryable");
        assert_eq!(
            actual,
            expected
                .iter()
                .map(|(name, ty, nullable)| { ((*name).to_owned(), (*ty).to_owned(), *nullable) })
                .collect::<Vec<_>>(),
            "unexpected columns for {table}"
        );
    }

    let indexes = sqlx::query_scalar::<_, String>(
        "SELECT indexname::text FROM pg_indexes WHERE schemaname = 'public' \
         AND tablename IN ('audit_log', 'outbox_events', 'outbox_deliveries', 'jobs', 'idempotency_keys') \
         ORDER BY indexname",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("platform indexes should be queryable");
    assert_eq!(
        indexes,
        [
            "audit_log_by_account",
            "audit_log_pkey",
            "idempotency_keys_pkey",
            "jobs_claim",
            "jobs_kind_unique_key_key",
            "jobs_pkey",
            "outbox_deliveries_due",
            "outbox_deliveries_pkey",
            "outbox_events_id_key",
            "outbox_events_pkey",
        ]
    );

    let index_definitions = sqlx::query_as::<_, (String, String)>(
        "SELECT indexname::text, indexdef::text FROM pg_indexes WHERE schemaname = 'public' \
         AND indexname IN ('audit_log_by_account', 'outbox_deliveries_due', 'jobs_claim') \
         ORDER BY indexname",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("platform index definitions should be queryable");
    for ((name, definition), expected) in index_definitions.iter().zip([
        (
            "audit_log_by_account",
            "USING btree (account_id, occurred_at DESC)",
        ),
        (
            "jobs_claim",
            "USING btree (run_at, id) WHERE (state = 'queued'::text)",
        ),
        (
            "outbox_deliveries_due",
            "USING btree (next_attempt_at) WHERE (state = 'pending'::text)",
        ),
    ]) {
        assert_eq!(name, expected.0);
        assert!(
            definition.contains(expected.1),
            "unexpected definition for {name}: {definition}"
        );
    }

    let defaults = sqlx::query_as::<_, (String, String, String)>(
        "SELECT table_name::text, column_name::text, column_default::text \
         FROM information_schema.columns WHERE table_schema = 'public' AND table_name IN \
           ('audit_log', 'outbox_events', 'outbox_deliveries', 'jobs', 'idempotency_keys') \
           AND column_default IS NOT NULL ORDER BY table_name, column_name",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("platform column defaults should be queryable");
    let expected_defaults = [
        ("audit_log", "details", "'{}'::jsonb"),
        ("jobs", "attempts", "0"),
        ("jobs", "payload", "'{}'::jsonb"),
        ("jobs", "state", "'queued'::text"),
        ("outbox_deliveries", "attempts", "0"),
        ("outbox_deliveries", "state", "'pending'::text"),
        (
            "outbox_events",
            "seq",
            "nextval('outbox_events_seq_seq'::regclass)",
        ),
    ];
    assert_eq!(
        defaults
            .iter()
            .map(|(table, column, _)| (table.as_str(), column.as_str()))
            .collect::<Vec<_>>(),
        expected_defaults
            .iter()
            .map(|(table, column, _)| (*table, *column))
            .collect::<Vec<_>>()
    );
    for ((table, column, actual), (_, _, expected)) in defaults.iter().zip(expected_defaults) {
        assert!(
            actual.contains(expected),
            "unexpected default for {table}.{column}: {actual}"
        );
    }

    let constraints = sqlx::query_as::<_, (String, String, String)>(
        "SELECT table_name::text, constraint_name::text, constraint_type::text \
         FROM information_schema.table_constraints \
         WHERE constraint_schema = 'public' AND table_name IN \
             ('audit_log', 'outbox_events', 'outbox_deliveries', 'jobs', 'idempotency_keys') \
           AND constraint_name !~ '^[0-9]+_[0-9]+_[0-9]+_not_null$' \
         ORDER BY table_name, constraint_name",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("platform constraints should be queryable");
    assert_eq!(
        constraints,
        [
            (
                "audit_log".to_owned(),
                "audit_log_account_id_fkey".to_owned(),
                "FOREIGN KEY".to_owned()
            ),
            (
                "audit_log".to_owned(),
                "audit_log_pkey".to_owned(),
                "PRIMARY KEY".to_owned()
            ),
            (
                "idempotency_keys".to_owned(),
                "idempotency_keys_account_id_fkey".to_owned(),
                "FOREIGN KEY".to_owned()
            ),
            (
                "idempotency_keys".to_owned(),
                "idempotency_keys_key_check".to_owned(),
                "CHECK".to_owned()
            ),
            (
                "idempotency_keys".to_owned(),
                "idempotency_keys_pkey".to_owned(),
                "PRIMARY KEY".to_owned()
            ),
            (
                "jobs".to_owned(),
                "job_state_ck".to_owned(),
                "CHECK".to_owned()
            ),
            (
                "jobs".to_owned(),
                "jobs_account_id_fkey".to_owned(),
                "FOREIGN KEY".to_owned()
            ),
            (
                "jobs".to_owned(),
                "jobs_kind_unique_key_key".to_owned(),
                "UNIQUE".to_owned()
            ),
            (
                "jobs".to_owned(),
                "jobs_pkey".to_owned(),
                "PRIMARY KEY".to_owned()
            ),
            (
                "outbox_deliveries".to_owned(),
                "outbox_deliveries_event_id_fkey".to_owned(),
                "FOREIGN KEY".to_owned()
            ),
            (
                "outbox_deliveries".to_owned(),
                "outbox_deliveries_pkey".to_owned(),
                "PRIMARY KEY".to_owned()
            ),
            (
                "outbox_deliveries".to_owned(),
                "outbox_state_ck".to_owned(),
                "CHECK".to_owned()
            ),
            (
                "outbox_events".to_owned(),
                "outbox_events_account_id_fkey".to_owned(),
                "FOREIGN KEY".to_owned()
            ),
            (
                "outbox_events".to_owned(),
                "outbox_events_id_key".to_owned(),
                "UNIQUE".to_owned()
            ),
            (
                "outbox_events".to_owned(),
                "outbox_events_pkey".to_owned(),
                "PRIMARY KEY".to_owned()
            ),
        ]
    );

    let delete_rules = sqlx::query_as::<_, (String, String)>(
        "SELECT tc.constraint_name::text, rc.delete_rule::text \
         FROM information_schema.referential_constraints rc \
         JOIN information_schema.table_constraints tc \
           ON tc.constraint_catalog = rc.constraint_catalog \
          AND tc.constraint_schema = rc.constraint_schema \
          AND tc.constraint_name = rc.constraint_name \
         WHERE tc.constraint_schema = 'public' AND tc.table_name IN \
           ('audit_log', 'outbox_events', 'outbox_deliveries', 'jobs', 'idempotency_keys') \
         ORDER BY tc.constraint_name",
    )
    .fetch_all(app.admin_pool())
    .await
    .expect("platform foreign key actions should be queryable");
    assert_eq!(
        delete_rules,
        [
            (
                "audit_log_account_id_fkey".to_owned(),
                "SET NULL".to_owned()
            ),
            (
                "idempotency_keys_account_id_fkey".to_owned(),
                "CASCADE".to_owned()
            ),
            ("jobs_account_id_fkey".to_owned(), "CASCADE".to_owned()),
            (
                "outbox_deliveries_event_id_fkey".to_owned(),
                "CASCADE".to_owned()
            ),
            (
                "outbox_events_account_id_fkey".to_owned(),
                "CASCADE".to_owned()
            ),
        ]
    );

    for (table, expected) in [
        ("audit_log", true),
        ("idempotency_keys", true),
        ("jobs", false),
        ("outbox_events", false),
        ("outbox_deliveries", false),
    ] {
        let actual: (bool, bool) = sqlx::query_as(
            "SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = to_regclass($1)",
        )
        .bind(format!("public.{table}"))
        .fetch_one(app.admin_pool())
        .await
        .expect("platform RLS flags should be queryable");
        assert_eq!(
            actual,
            (expected, expected),
            "unexpected RLS state for {table}"
        );
    }
}

#[tokio::test]
async fn dom_26_audit_is_append_only_for_app_roles() {
    let sources = support::migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account = app.new_account();
    seed_account(&app, account, "append-only@example.test").await;
    let writer = AuditWriter::new(app.ids.clone());
    let mut system = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    writer
        .record(
            &mut system,
            entry(
                Some(account),
                AuditAction::AuthLogin,
                instant("2026-11-02T00:00:00Z"),
            ),
        )
        .await
        .expect("system audit insert should succeed");
    system
        .commit()
        .await
        .expect("system transaction should commit");

    let row_id: Uuid = sqlx::query_scalar(
        "SELECT id FROM public.audit_log WHERE account_id = $1 AND action = 'auth.login'",
    )
    .bind(*account.as_uuid())
    .fetch_one(app.admin_pool())
    .await
    .expect("inserted audit id should be queryable");

    for statement in [
        "UPDATE public.audit_log SET action = 'auth.login_failed' WHERE id = $1",
        "DELETE FROM public.audit_log WHERE id = $1",
    ] {
        let error = sqlx::query(AssertSqlSafe(statement))
            .bind(row_id)
            .execute(app.app_pool_for_test())
            .await
            .expect_err("app role must not mutate audit rows");
        assert!(is_privilege_error(&error));

        let mut system = SystemTx::begin(&app.db)
            .await
            .expect("system transaction should begin");
        let error = sqlx::query(AssertSqlSafe(statement))
            .bind(row_id)
            .execute(system.conn())
            .await
            .expect_err("system role must not mutate audit rows");
        assert!(is_privilege_error(&error));
    }
}

#[tokio::test]
async fn adm_fr_05_audit_visibility() {
    let sources = support::migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account_a = app.new_account();
    let account_b = app.new_account();
    seed_account(&app, account_a, "audit-a@example.test").await;
    seed_account(&app, account_b, "audit-b@example.test").await;
    let writer = AuditWriter::new(app.ids.clone());

    let mut system = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    for account in [account_a, account_b] {
        writer
            .record(
                &mut system,
                entry(
                    Some(account),
                    AuditAction::AuthLogin,
                    instant("2026-11-02T00:00:00Z"),
                ),
            )
            .await
            .expect("system audit insert should succeed");
    }
    system
        .commit()
        .await
        .expect("system transaction should commit");

    let mut tx_a = Tx::begin(&app.db, account_a)
        .await
        .expect("account A transaction should begin");
    let own_rows: i64 = sqlx::query_scalar("SELECT count(*) FROM public.audit_log")
        .fetch_one(tx_a.conn())
        .await
        .expect("account A should read audit rows");
    assert_eq!(own_rows, 1);
    tx_a.commit()
        .await
        .expect("account A transaction should commit");

    let mut tx_b = Tx::begin(&app.db, account_b)
        .await
        .expect("account B transaction should begin");
    let a_rows: i64 =
        sqlx::query_scalar("SELECT count(*) FROM public.audit_log WHERE account_id = $1")
            .bind(*account_a.as_uuid())
            .fetch_one(tx_b.conn())
            .await
            .expect("account B query should succeed");
    assert_eq!(a_rows, 0);
    tx_b.commit()
        .await
        .expect("account B transaction should commit");

    let mut admin = Tx::begin_admin(&app.db, account_b)
        .await
        .expect("admin transaction should begin");
    let all_rows: i64 = sqlx::query_scalar("SELECT count(*) FROM public.audit_log")
        .fetch_one(admin.conn())
        .await
        .expect("admin context should read all audit rows");
    assert_eq!(all_rows, 2);
    admin
        .commit()
        .await
        .expect("admin transaction should commit");
}

#[tokio::test]
async fn adr_0011_system_insert_and_with_check() {
    let sources = support::migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account_a = app.new_account();
    let account_b = app.new_account();
    seed_account(&app, account_a, "system-a@example.test").await;
    seed_account(&app, account_b, "system-b@example.test").await;
    let writer = AuditWriter::new(app.ids.clone());

    let mut system = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    writer
        .record(
            &mut system,
            entry(
                Some(account_a),
                AuditAction::AuthLoginFailed,
                instant("2026-11-02T00:00:00Z"),
            ),
        )
        .await
        .expect("system role may append an audit row for an account");
    system
        .commit()
        .await
        .expect("system transaction should commit");

    let mut tx_b = Tx::begin(&app.db, account_b)
        .await
        .expect("account B transaction should begin");
    let error = writer
        .record(
            &mut tx_b,
            entry(
                Some(account_a),
                AuditAction::AuthLoginFailed,
                instant("2026-11-02T00:00:01Z"),
            ),
        )
        .await
        .expect_err("account B must not append an audit row for account A");
    assert_eq!(
        error.code,
        kinevo_kernel::errors::ErrorCode::ServiceUnavailable
    );
}

#[tokio::test]
async fn id_fr_11_audit_survives_account_purge() {
    let sources = support::migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account = app.new_account();
    seed_account(&app, account, "purge-audit@example.test").await;
    let writer = AuditWriter::new(app.ids.clone());
    let mut system = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    writer
        .record(
            &mut system,
            entry(
                Some(account),
                AuditAction::AccountDeleted,
                instant("2026-11-02T00:00:00Z"),
            ),
        )
        .await
        .expect("system audit insert should succeed");
    system
        .commit()
        .await
        .expect("system transaction should commit");

    let mut tx = app
        .migrator_pool()
        .begin()
        .await
        .expect("migrator transaction should begin");
    sqlx::query("SELECT set_config('app.account_id', $1, true)")
        .bind(account.to_string())
        .execute(&mut *tx)
        .await
        .expect("migrator account context should set");
    sqlx::query("DELETE FROM public.accounts WHERE id = $1")
        .bind(*account.as_uuid())
        .execute(&mut *tx)
        .await
        .expect("migrator should purge the account");
    tx.commit().await.expect("migrator purge should commit");

    let subject: Option<Uuid> = sqlx::query_scalar(
        "SELECT account_id FROM public.audit_log WHERE action = 'account.deleted'",
    )
    .fetch_one(app.admin_pool())
    .await
    .expect("audit row should survive the account purge");
    assert_eq!(subject, None);
}

#[tokio::test]
#[allow(
    clippy::too_many_lines,
    reason = "the acceptance test exercises all four retention functions against one fixture database"
)]
async fn adm_fr_07_retention_functions() {
    let sources = support::migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    let account = app.new_account();
    seed_account(&app, account, "retention@example.test").await;
    let old = instant("2026-11-01T00:00:00Z");
    let cutoff = instant("2026-11-02T00:00:00Z");
    let new = instant("2026-11-03T00:00:00Z");
    let writer = AuditWriter::new(app.ids.clone());

    let mut system = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    writer
        .record(
            &mut system,
            entry(Some(account), AuditAction::AuthLogin, old),
        )
        .await
        .expect("old audit row should insert");
    writer
        .record(
            &mut system,
            entry(Some(account), AuditAction::AuthLoginFailed, new),
        )
        .await
        .expect("new audit row should insert");
    let old_job = app.ids.new_uuid_v7();
    let new_job = app.ids.new_uuid_v7();
    let queued_job = app.ids.new_uuid_v7();
    for (id, state, finished_at) in [
        (old_job, "succeeded", Some(old)),
        (new_job, "failed", Some(new)),
        (queued_job, "queued", None),
    ] {
        sqlx::query(
            "INSERT INTO public.jobs (id, kind, state, run_at, attempts, max_attempts, created_at, finished_at) \
             VALUES ($1, 'test.retention', $2, $3::timestamptz, 1, 3, $3::timestamptz, $4::timestamptz)",
        )
        .bind(id)
        .bind(state)
        .bind(old.to_rfc3339())
        .bind(finished_at.map(|at| at.to_rfc3339()))
        .execute(system.conn())
        .await
        .expect("job retention fixture should insert");
    }
    let delivered_event = app.ids.new_uuid_v7();
    let no_delivery_event = app.ids.new_uuid_v7();
    let dead_event = app.ids.new_uuid_v7();
    let recent_event = app.ids.new_uuid_v7();
    for (id, created_at) in [
        (delivered_event, old),
        (no_delivery_event, old),
        (dead_event, old),
        (recent_event, new),
    ] {
        sqlx::query(
            "INSERT INTO public.outbox_events (id, kind, payload, created_at) \
             VALUES ($1, 'TestEvent', '{}'::jsonb, $2::timestamptz)",
        )
        .bind(id)
        .bind(created_at.to_rfc3339())
        .execute(system.conn())
        .await
        .expect("outbox event fixture should insert");
    }
    for (event_id, state, delivered_at) in [
        (delivered_event, "delivered", Some(old)),
        (dead_event, "dead", None),
        (recent_event, "delivered", Some(new)),
    ] {
        sqlx::query(
            "INSERT INTO public.outbox_deliveries \
             (event_id, subscriber, state, attempts, next_attempt_at, delivered_at) \
             VALUES ($1, 'test.subscriber', $2, 1, $3::timestamptz, $4::timestamptz)",
        )
        .bind(event_id)
        .bind(state)
        .bind(new.to_rfc3339())
        .bind(delivered_at.map(|at| at.to_rfc3339()))
        .execute(system.conn())
        .await
        .expect("outbox delivery fixture should insert");
    }
    system
        .commit()
        .await
        .expect("system fixture transaction should commit");

    let mut app_tx = Tx::begin(&app.db, account)
        .await
        .expect("account transaction should begin");
    for (key, expires_at) in [("old-key-0001", old), ("new-key-0001", new)] {
        sqlx::query(
            "INSERT INTO public.idempotency_keys \
             (account_id, key, method, path, request_hash, created_at, expires_at) \
             VALUES ($1, $2, 'POST', '/api/v1/account', $3, $4::timestamptz, $5::timestamptz)",
        )
        .bind(*account.as_uuid())
        .bind(key)
        .bind([3_u8; 32].as_slice())
        .bind(old.to_rfc3339())
        .bind(expires_at.to_rfc3339())
        .execute(app_tx.conn())
        .await
        .expect("idempotency fixture should insert");
    }
    app_tx
        .commit()
        .await
        .expect("account transaction should commit");

    let mut system = SystemTx::begin(&app.db)
        .await
        .expect("system transaction should begin");
    let old_audit_rows: i64 = sqlx::query_scalar(
        "SELECT count(*) FROM public.audit_log WHERE occurred_at < $1::timestamptz",
    )
    .bind(cutoff.to_rfc3339())
    .fetch_one(app.admin_pool())
    .await
    .expect("audit fixture should be visible to the database owner");
    assert_eq!(old_audit_rows, 1);
    let old_idempotency_rows: i64 = sqlx::query_scalar(
        "SELECT count(*) FROM public.idempotency_keys WHERE expires_at < $1::timestamptz",
    )
    .bind(cutoff.to_rfc3339())
    .fetch_one(app.admin_pool())
    .await
    .expect("idempotency fixture should be visible to the database owner");
    assert_eq!(old_idempotency_rows, 1);
    assert_eq!(
        prune_audit(&mut system, cutoff)
            .await
            .expect("audit prune should run"),
        1
    );
    assert_eq!(
        prune_idempotency(&mut system, cutoff)
            .await
            .expect("idempotency prune should run"),
        1
    );
    assert_eq!(
        prune_jobs(&mut system, cutoff)
            .await
            .expect("job prune should run"),
        1
    );
    assert_eq!(
        prune_outbox(&mut system, cutoff)
            .await
            .expect("outbox prune should run"),
        3
    );
    system
        .commit()
        .await
        .expect("retention transaction should commit");

    let app_error =
        sqlx::query_scalar::<_, i64>("SELECT public.platform_prune_audit($1::timestamptz)")
            .bind(cutoff.to_rfc3339())
            .fetch_one(app.app_pool_for_test())
            .await
            .expect_err("app role must not execute retention functions");
    assert!(is_privilege_error(&app_error));

    let remaining_jobs: i64 = sqlx::query_scalar("SELECT count(*) FROM public.jobs")
        .fetch_one(app.admin_pool())
        .await
        .expect("remaining jobs should be queryable");
    assert_eq!(remaining_jobs, 2);
    let remaining_events: i64 = sqlx::query_scalar("SELECT count(*) FROM public.outbox_events")
        .fetch_one(app.admin_pool())
        .await
        .expect("remaining events should be queryable");
    assert_eq!(remaining_events, 2);
    let remaining_deliveries: i64 =
        sqlx::query_scalar("SELECT count(*) FROM public.outbox_deliveries")
            .fetch_one(app.admin_pool())
            .await
            .expect("remaining deliveries should be queryable");
    assert_eq!(remaining_deliveries, 2);
    let remaining_idempotency: i64 =
        sqlx::query_scalar("SELECT count(*) FROM public.idempotency_keys WHERE account_id = $1")
            .bind(*account.as_uuid())
            .fetch_one(app.admin_pool())
            .await
            .expect("remaining idempotency rows should be queryable");
    assert_eq!(remaining_idempotency, 1);
}

#[tokio::test]
async fn sec_ac_01_isolation_harness_clean() {
    let sources = support::migration_sources();
    require_db!(app = TestApp::builder().migrations(&sources).build().await);
    run_isolation(&app, support::fixtures::ALL_ISOLATION_FIXTURES)
        .await
        .assert_clean();
    verify_isolation(app.app_pool_for_test())
        .await
        .expect("catalog isolation checks should accept the platform tables");
}
