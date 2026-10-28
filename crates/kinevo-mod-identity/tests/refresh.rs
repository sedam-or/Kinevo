#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "refresh-token acceptance tests use isolated synthetic fixtures"
)]

use std::{
    collections::BTreeMap,
    fmt,
    sync::{Arc, Mutex},
};

use kinevo_db::{SystemTx, Tx, migrate::MigrationSource, testing::TestApp};
use kinevo_http::token::{KeyRing, TokenCodec};
use kinevo_kernel::{
    crypto::{decode_token, encode_token, hash_token},
    errors::ErrorCode,
    id::{AccountId, FamilyId},
    secret::Secret,
    time::{Clock, FixedClock, Instant},
};
use kinevo_mod_identity::{
    app::refresh::{Client, GraceCache, GracePair, RefreshService},
    domain::session::{RevocationReason, SessionPolicy},
    infra::sessions,
    migrations,
};
use sqlx::{PgPool, Row, types::Uuid};
use tracing::{
    Event, Subscriber,
    field::{Field, Visit},
    instrument::WithSubscriber,
    span,
};

const ACCESS_TTL: u32 = 600;
const IDLE_TTL: u32 = 14 * 24 * 60 * 60;
const ABSOLUTE_TTL: u32 = 30 * 24 * 60 * 60;
const GRACE: u32 = 10;

fn now() -> Instant {
    Instant::parse_rfc3339("2026-11-02T00:00:00.123Z").expect("fixed test instant is valid")
}

fn policy() -> SessionPolicy {
    SessionPolicy {
        access_ttl: ACCESS_TTL,
        idle_ttl: IDLE_TTL,
        absolute_ttl: ABSOLUTE_TTL,
        grace: GRACE,
    }
}

struct Harness {
    service: Arc<RefreshService>,
    app: TestApp,
    clock: Arc<FixedClock>,
}

async fn test_app() -> TestApp {
    let sources: [MigrationSource; 2] = [kinevo_db::migrations_source(), migrations()];
    TestApp::builder()
        .migrations(&sources)
        .clock_at(now())
        .build()
        .await
        .expect("disposable PostgreSQL test database should start")
}

async fn harness() -> Harness {
    let app = test_app().await;
    let clock = Arc::new(FixedClock::new(now()));
    let codec = Arc::new(TokenCodec::new(
        KeyRing::new("test", vec![("test".to_owned(), Secret::new([0x55; 32]))])
            .expect("synthetic test key ring is valid"),
    ));
    let service = Arc::new(RefreshService::new(
        app.db.clone(),
        clock.clone(),
        app.ids.clone(),
        app.tokens.clone(),
        codec,
    ));
    Harness {
        service,
        app,
        clock,
    }
}

async fn account(h: &Harness, ordinal: u8) -> AccountId {
    let account = h.app.new_account();
    let mut tx = Tx::begin(&h.app.db, account)
        .await
        .expect("account transaction should begin");
    sqlx::query(
        "INSERT INTO public.accounts (id, email, display_name, password_hash, timezone) \
         VALUES ($1, $2, $3, $4, $5)",
    )
    .bind(*account.as_uuid())
    .bind(format!("account-{ordinal}@example.test"))
    .bind("Example Account")
    .bind("synthetic-password-hash")
    .bind("UTC")
    .execute(tx.conn())
    .await
    .expect("synthetic account should insert");
    tx.commit()
        .await
        .expect("account transaction should commit");
    account
}

async fn family(h: &Harness, account: AccountId, ordinal: u8) -> sessions::NewSession {
    let mut tx = Tx::begin(&h.app.db, account)
        .await
        .expect("account transaction should begin");
    let session = sessions::create_family_with_token(
        &mut tx,
        h.app.ids.as_ref(),
        h.app.tokens.as_ref(),
        h.clock.now(),
        account,
        Some(&format!("test-device-{ordinal}")),
        false,
        policy(),
    )
    .await
    .expect("synthetic session should be created");
    tx.commit().await.expect("family transaction should commit");
    session
}

fn presented(token: &Secret<String>) -> Secret<String> {
    Secret::new(token.expose().clone())
}

async fn family_state(pool: &PgPool, family_id: FamilyId) -> (bool, Option<String>) {
    let row = sqlx::query(
        "SELECT revoked_at IS NOT NULL AS revoked, revoked_reason \
         FROM public.refresh_token_families WHERE id = $1",
    )
    .bind(*family_id.as_uuid())
    .fetch_one(pool)
    .await
    .expect("family state should be queryable");
    (
        row.try_get("revoked")
            .expect("revoked column should be boolean"),
        row.try_get("revoked_reason")
            .expect("revocation reason should be nullable text"),
    )
}

struct CapturingSubscriber(Arc<Mutex<Vec<BTreeMap<String, String>>>>);

impl Subscriber for CapturingSubscriber {
    fn enabled(&self, _metadata: &tracing::Metadata<'_>) -> bool {
        true
    }

    fn new_span(&self, _span: &span::Attributes<'_>) -> span::Id {
        span::Id::from_u64(1)
    }

    fn record(&self, _span: &span::Id, _values: &span::Record<'_>) {}

    fn record_follows_from(&self, _span: &span::Id, _follows: &span::Id) {}

    fn event(&self, event: &Event<'_>) {
        let mut values = BTreeMap::new();
        event.record(&mut CapturedFields(&mut values));
        self.0
            .lock()
            .unwrap_or_else(std::sync::PoisonError::into_inner)
            .push(values);
    }

    fn enter(&self, _span: &span::Id) {}

    fn exit(&self, _span: &span::Id) {}
}

struct CapturedFields<'a>(&'a mut BTreeMap<String, String>);

impl Visit for CapturedFields<'_> {
    fn record_debug(&mut self, field: &Field, value: &dyn fmt::Debug) {
        self.0.insert(field.name().to_owned(), format!("{value:?}"));
    }

    fn record_str(&mut self, field: &Field, value: &str) {
        self.0.insert(field.name().to_owned(), value.to_owned());
    }
}

#[tokio::test]
async fn auth_01_normal_rotation() {
    let h = harness().await;
    let account = account(&h, 1).await;
    let created = family(&h, account, 1).await;
    let mut system_tx = SystemTx::begin(&h.app.db)
        .await
        .expect("system transaction should begin");
    let summaries = sessions::list_families(&mut system_tx, account)
        .await
        .expect("family summaries should be queryable");
    system_tx
        .rollback()
        .await
        .expect("read-only system transaction should roll back");
    assert_eq!(summaries.len(), 1);
    assert_eq!(summaries[0].created_at, h.clock.now());
    let old_hash = hash_token(
        &decode_token(created.refresh_token.expose()).expect("refresh token is encoded"),
    );
    h.clock.advance_seconds(1).expect("clock should advance");
    let issued_at = h.clock.now();

    let result = h
        .service
        .refresh(presented(&created.refresh_token), policy(), Client::Web)
        .await
        .expect("fresh refresh token should rotate");
    let new_hash =
        hash_token(&decode_token(result.refresh_token.expose()).expect("rotated token is encoded"));
    let row = sqlx::query(
        "SELECT old.used_at IS NOT NULL AS used, old.replaced_by, new.id AS new_id \
         FROM public.refresh_tokens old \
         JOIN public.refresh_tokens new ON old.replaced_by = new.id \
         WHERE old.token_hash = $1 AND new.token_hash = $2",
    )
    .bind(old_hash.as_slice())
    .bind(new_hash.as_slice())
    .fetch_one(h.app.admin_pool())
    .await
    .expect("rotation should persist both token rows");
    assert!(
        row.try_get::<bool, _>("used")
            .expect("used flag is boolean")
    );
    assert_eq!(
        row.try_get::<Option<Uuid>, _>("replaced_by")
            .expect("replacement id is nullable"),
        Some(row.try_get("new_id").expect("new id is a UUID")),
    );
    let last_used_matches = sqlx::query_scalar::<_, bool>(
        "SELECT last_used_at = $2::timestamptz FROM public.refresh_token_families WHERE id = $1",
    )
    .bind(*created.family_id.as_uuid())
    .bind(issued_at.to_rfc3339())
    .fetch_one(h.app.admin_pool())
    .await
    .expect("family last-used time should be queryable");
    assert!(last_used_matches);
}

#[tokio::test]
async fn id_fr_05_grace_returns_same_pair() {
    let h = harness().await;
    let account = account(&h, 2).await;
    let created = family(&h, account, 2).await;
    let first = h
        .service
        .refresh(presented(&created.refresh_token), policy(), Client::Web)
        .await
        .expect("first refresh should rotate");
    let second = h
        .service
        .refresh(presented(&created.refresh_token), policy(), Client::Web)
        .await
        .expect("duplicate refresh in grace should return cached pair");
    assert_eq!(first.access_token, second.access_token);
    assert_eq!(first.refresh_token.expose(), second.refresh_token.expose());
    assert_eq!(
        family_state(h.app.admin_pool(), created.family_id).await,
        (false, None)
    );
}

#[tokio::test]
async fn sec_ac_04_reuse_revokes_family() {
    let h = harness().await;
    let account = account(&h, 3).await;
    let created = family(&h, account, 3).await;
    let first = h
        .service
        .refresh(presented(&created.refresh_token), policy(), Client::Web)
        .await
        .expect("first refresh should rotate");
    h.clock
        .advance_seconds(i64::from(GRACE))
        .expect("clock should advance past grace");
    let reused = h
        .service
        .refresh(presented(&created.refresh_token), policy(), Client::Web)
        .await
        .err()
        .expect("used token after grace should be rejected");
    assert_eq!(reused.code, ErrorCode::RefreshReuseDetected);
    assert_eq!(
        family_state(h.app.admin_pool(), created.family_id).await,
        (true, Some("reuse_detected".to_owned()))
    );
    let audit_rows = sqlx::query_scalar::<_, i64>(
        "SELECT count(*) FROM public.audit_log \
         WHERE account_id = $1 AND action = 'auth.refresh_reuse' \
           AND details->>'family_id' = $2",
    )
    .bind(*account.as_uuid())
    .bind(created.family_id.to_string())
    .fetch_one(h.app.admin_pool())
    .await
    .expect("reuse audit row should be queryable");
    assert_eq!(audit_rows, 1);
    let newest = h
        .service
        .refresh(presented(&first.refresh_token), policy(), Client::Web)
        .await
        .err()
        .expect("revoked family's newest token should no longer work");
    assert_eq!(newest.code, ErrorCode::Unauthenticated);
}

#[tokio::test]
async fn tst_20_concurrent_double_submit() {
    let h = harness().await;
    let account = account(&h, 4).await;
    let created = family(&h, account, 4).await;
    let (left, right) = tokio::join!(
        h.service
            .refresh(presented(&created.refresh_token), policy(), Client::Web),
        h.service
            .refresh(presented(&created.refresh_token), policy(), Client::Web),
    );
    let left = left.expect("first concurrent refresh should succeed");
    let right = right.expect("second concurrent refresh should succeed");
    assert_eq!(left.access_token, right.access_token);
    assert_eq!(left.refresh_token.expose(), right.refresh_token.expose());
    assert_eq!(
        family_state(h.app.admin_pool(), created.family_id).await,
        (false, None)
    );
}

#[tokio::test]
async fn auth_01_idle_and_absolute_expiry() {
    let h = harness().await;
    let account = account(&h, 5).await;
    let idle_session = family(&h, account, 5).await;
    h.clock
        .advance_seconds(i64::from(IDLE_TTL))
        .expect("clock should reach idle expiry");
    let idle = h
        .service
        .refresh(
            presented(&idle_session.refresh_token),
            policy(),
            Client::Web,
        )
        .await
        .err()
        .expect("idle-expired token should fail");
    assert_eq!(idle.code, ErrorCode::Unauthenticated);
    assert_eq!(
        family_state(h.app.admin_pool(), idle_session.family_id).await,
        (true, Some("expired".to_owned()))
    );

    h.clock.set(now());
    let absolute_session = family(&h, account, 6).await;
    let later_than_absolute = now()
        .checked_add_seconds(i64::from(ABSOLUTE_TTL) + 60)
        .expect("test instant should remain in range");
    sqlx::query(
        "UPDATE public.refresh_tokens SET idle_expires_at = $2::timestamptz WHERE family_id = $1",
    )
    .bind(*absolute_session.family_id.as_uuid())
    .bind(later_than_absolute.to_rfc3339())
    .execute(h.app.admin_pool())
    .await
    .expect("test token idle expiry should be extended beyond absolute expiry");
    h.clock.set(later_than_absolute);
    let absolute = h
        .service
        .refresh(
            presented(&absolute_session.refresh_token),
            policy(),
            Client::Web,
        )
        .await
        .err()
        .expect("absolute-expired family should fail");
    assert_eq!(absolute.code, ErrorCode::Unauthenticated);
    assert_eq!(
        family_state(h.app.admin_pool(), absolute_session.family_id).await,
        (true, Some("expired".to_owned()))
    );
}

#[tokio::test]
async fn id_fr_07_revoke_others() {
    let h = harness().await;
    let account = account(&h, 7).await;
    let first = family(&h, account, 7).await;
    let current = family(&h, account, 8).await;
    let third = family(&h, account, 9).await;
    let mut tx = SystemTx::begin(&h.app.db)
        .await
        .expect("system transaction should begin");
    sessions::revoke_all_for_account(
        &mut tx,
        account,
        RevocationReason::PasswordChanged,
        h.clock.now(),
        Some(current.family_id),
    )
    .await
    .expect("other families should revoke");
    tx.commit()
        .await
        .expect("revocation transaction should commit");
    assert_eq!(
        family_state(h.app.admin_pool(), first.family_id).await,
        (true, Some("password_changed".to_owned()))
    );
    assert_eq!(
        family_state(h.app.admin_pool(), current.family_id).await,
        (false, None)
    );
    assert_eq!(
        family_state(h.app.admin_pool(), third.family_id).await,
        (true, Some("password_changed".to_owned()))
    );
}

#[tokio::test]
async fn no_oracle_between_failure_causes() {
    let h = harness().await;
    let account = account(&h, 10).await;
    let revoked_session = family(&h, account, 10).await;
    let expired_session = family(&h, account, 11).await;
    sqlx::query(
        "UPDATE public.refresh_token_families SET revoked_at = $2::timestamptz, revoked_reason = 'admin' WHERE id = $1",
    )
    .bind(*revoked_session.family_id.as_uuid())
    .bind(now().to_rfc3339())
    .execute(h.app.admin_pool())
    .await
    .expect("test family should be revoked");
    sqlx::query(
        "UPDATE public.refresh_tokens SET idle_expires_at = $2::timestamptz WHERE family_id = $1",
    )
    .bind(*expired_session.family_id.as_uuid())
    .bind(now().to_rfc3339())
    .execute(h.app.admin_pool())
    .await
    .expect("test token should expire at the injected clock");

    let unknown = Secret::new(encode_token(&[0x99; 32]));
    let unknown = h
        .service
        .refresh(unknown, policy(), Client::Web)
        .await
        .err()
        .expect("unknown token should fail");
    let revoked = h
        .service
        .refresh(
            presented(&revoked_session.refresh_token),
            policy(),
            Client::Web,
        )
        .await
        .err()
        .expect("revoked token should fail");
    let expired = h
        .service
        .refresh(
            presented(&expired_session.refresh_token),
            policy(),
            Client::Web,
        )
        .await
        .err()
        .expect("expired token should fail");
    assert_eq!(unknown, revoked);
    assert_eq!(revoked, expired);
    assert_eq!(unknown.code.http_status(), 401);
}

#[tokio::test]
async fn sec_ac_45_no_plaintext_tokens() {
    let h = harness().await;
    let account = account(&h, 12).await;
    let created = family(&h, account, 12).await;
    let old_raw = decode_token(created.refresh_token.expose()).expect("old token is encoded");
    let logs = Arc::new(Mutex::new(Vec::new()));
    let rotated = h
        .service
        .refresh(presented(&created.refresh_token), policy(), Client::Web)
        .with_subscriber(CapturingSubscriber(Arc::clone(&logs)))
        .await
        .expect("token should rotate");
    let new_raw = decode_token(rotated.refresh_token.expose()).expect("new token is encoded");
    let hashes = sqlx::query_scalar::<_, Vec<u8>>(
        "SELECT token_hash FROM public.refresh_tokens WHERE family_id = $1",
    )
    .bind(*created.family_id.as_uuid())
    .fetch_all(h.app.admin_pool())
    .await
    .expect("token hashes should be queryable");
    assert_eq!(hashes.len(), 2);
    assert!(hashes.contains(&hash_token(&old_raw).to_vec()));
    assert!(hashes.contains(&hash_token(&new_raw).to_vec()));
    assert!(!hashes.contains(&created.refresh_token.expose().as_bytes().to_vec()));
    assert!(!hashes.contains(&rotated.refresh_token.expose().as_bytes().to_vec()));
    assert!(!format!("{:?}", rotated.refresh_token).contains(rotated.refresh_token.expose()));
    let logs = logs
        .lock()
        .unwrap_or_else(std::sync::PoisonError::into_inner);
    let logs = format!("{logs:?}");
    assert!(!logs.contains(created.refresh_token.expose()));
    assert!(!logs.contains(rotated.refresh_token.expose()));
}

mod tests {
    use super::*;

    #[tokio::test]
    async fn cac_1_grace_cache_bounded() {
        let cache = GraceCache::new();
        let expires_at = now()
            .checked_add_seconds(3_600)
            .expect("expiry is in range");
        let account = AccountId::from_uuid(Uuid::nil());
        let family = FamilyId::from_uuid(Uuid::nil());
        let short_hash = [u8::MAX; 32];
        cache
            .put(
                short_hash,
                expires_at,
                std::time::Duration::from_millis(1),
                GracePair::new(
                    Secret::new("short-lived-access".to_owned()),
                    Secret::new("short-lived-refresh".to_owned()),
                    ACCESS_TTL,
                    account,
                    family,
                    expires_at,
                ),
            )
            .await;
        tokio::time::sleep(std::time::Duration::from_millis(10)).await;
        assert!(cache.get(&short_hash, now()).await.is_none());

        for value in 0_u64..10_001 {
            let mut hash = [0_u8; 32];
            hash[..8].copy_from_slice(&value.to_be_bytes());
            cache
                .put(
                    hash,
                    expires_at,
                    std::time::Duration::from_secs(3_600),
                    GracePair::new(
                        Secret::new("synthetic-access".to_owned()),
                        Secret::new("synthetic-refresh".to_owned()),
                        ACCESS_TTL,
                        account,
                        family,
                        expires_at,
                    ),
                )
                .await;
        }
        assert!(cache.entry_count().await <= 10_000);
    }
}
