//! Account export requests, assembly and downloads.

use std::{
    collections::BTreeSet,
    io::{Cursor, Write},
    sync::Arc,
};

use async_trait::async_trait;
use kinevo_db::{
    Db, Tx,
    audit::{AuditAction, AuditEntry, AuditTarget, AuditWriter},
    jobs::{self, JobKind, NewJob},
};
use kinevo_kernel::{
    errors::{AppError, DetailValue, Details, ErrorCode},
    id::{AccountId, IdGen},
    policy::registry::platform,
    time::{Clock, Instant},
};
use kinevo_ports::{
    module::{AccountExporter, ExportSection, check_exporters},
    platform::PolicyReader,
};
use serde::{Serialize, ser::SerializeMap};
use zip::{
    CompressionMethod, DateTime, System,
    write::{SimpleFileOptions, ZipWriter},
};

use crate::{
    domain::export::{ExportState, LocalDateStamp, export_file_name, readme_text},
    infra::exports as export_repository,
};

const ACCOUNT_SECTION: &[&str] = &["account"];
const EXPORT_FORMAT: &str = "export_v1";

/// The identity module's account-profile section.
pub struct IdentityExporter;

#[derive(Serialize)]
struct ExportProfile {
    email: String,
    display_name: String,
    locale: String,
    timezone: String,
    week_start: i16,
    totp_enabled: bool,
    created_at: String,
}

#[async_trait]
impl AccountExporter<Tx> for IdentityExporter {
    fn sections(&self) -> &'static [&'static str] {
        ACCOUNT_SECTION
    }

    async fn export(
        &self,
        tx: &mut Tx,
        account: AccountId,
    ) -> Result<Vec<ExportSection>, AppError> {
        if tx.account_id() != account {
            return Err(AppError::new(ErrorCode::Forbidden));
        }
        let row = export_repository::account_profile(tx, account).await?;
        let profile = ExportProfile {
            email: row.email,
            display_name: row.display_name,
            locale: row.locale,
            timezone: row.timezone,
            week_start: row.week_start,
            totp_enabled: row.totp_enabled,
            created_at: row.created_at,
        };
        let profile = serde_json::to_value(profile).map_err(|_| internal_error())?;
        Ok(vec![ExportSection {
            name: "account",
            rows: serde_json::json!({ "profile": profile }),
        }])
    }
}

/// Safe status representation returned by the export metadata endpoint.
#[derive(Clone, Debug, Serialize)]
pub struct ExportStatus {
    pub state: ExportState,
    pub size_bytes: Option<i64>,
    pub expires_at: Option<Instant>,
}

/// Completed archive with the response headers required by the HTTP adapter.
#[derive(Debug)]
pub struct ExportDownload {
    pub bytes: Vec<u8>,
    pub file_name: String,
    pub content_type: &'static str,
}

/// Coordinates queued exports while using the caller's account-scoped transaction.
pub struct ExportService {
    db: Db,
    ids: Arc<dyn IdGen>,
    clock: Arc<dyn Clock>,
    policies: Arc<dyn PolicyReader>,
    exporters: Vec<Arc<dyn AccountExporter<Tx>>>,
}

impl ExportService {
    #[allow(
        clippy::too_many_arguments,
        reason = "export uses explicit platform ports"
    )]
    pub fn new(
        db: Db,
        ids: Arc<dyn IdGen>,
        clock: Arc<dyn Clock>,
        policies: Arc<dyn PolicyReader>,
        mut exporters: Vec<Arc<dyn AccountExporter<Tx>>>,
    ) -> Result<Self, AppError> {
        let account_exporter = exporters
            .iter()
            .position(|exporter| exporter.sections().contains(&"account"))
            .ok_or_else(internal_error)?;
        exporters.swap(0, account_exporter);
        let references = exporters
            .iter()
            .map(Arc::as_ref)
            .collect::<Vec<&dyn AccountExporter<Tx>>>();
        check_exporters(&references)?;
        Ok(Self {
            db,
            ids,
            clock,
            policies,
            exporters,
        })
    }

    /// Queues one export unless the account already has a queued or running export.
    pub async fn request_export(&self, account: AccountId) -> Result<sqlx::types::Uuid, AppError> {
        let now = self.clock.now();
        let policy = self.policy_values(account, now).await?;
        let max_attempts = retry_attempts(&policy)?;
        let max_age_days = export_max_age_days(&policy)?;
        let expires_at = export_expiry(now, max_age_days)?;
        let id = self.ids.new_uuid_v7();
        let mut tx = Tx::begin(&self.db, account).await?;
        export_repository::lock_account(&mut tx, account).await?;
        let active = export_repository::active_export_id(&mut tx, account).await?;
        if let Some(active) = active {
            return Err(AppError::new(ErrorCode::ExportInProgress)
                .with_detail("export_id", DetailValue::Id(active)));
        }

        export_repository::create_queued_export(&mut tx, id, account, now, expires_at).await?;
        jobs::enqueue(
            &mut tx,
            self.ids.as_ref(),
            now,
            NewJob {
                account_id: Some(account),
                kind: JobKind::from_valid_static("account.export.build"),
                unique_key: Some(id.hyphenated().to_string()),
                payload: serde_json::json!({
                    "account_id": account.to_string(),
                    "export_id": id.hyphenated().to_string(),
                }),
                run_at: now,
                max_attempts,
            },
        )
        .await?;
        tx.commit().await?;
        Ok(id)
    }

    /// Returns export state and lazily expires a ready archive whose clock has elapsed.
    pub async fn status(
        &self,
        account: AccountId,
        id: sqlx::types::Uuid,
    ) -> Result<ExportStatus, AppError> {
        let now = self.clock.now();
        let mut tx = Tx::begin(&self.db, account).await?;
        let row = export_repository::find(&mut tx, id).await?;
        let mut state = parse_state(&row.state)?;
        if state == ExportState::Ready && row.expires_at <= now {
            export_repository::expire(&mut tx, id).await?;
            state = ExportState::Expired;
        }
        tx.commit().await?;
        Ok(ExportStatus {
            state,
            size_bytes: row.size_bytes,
            expires_at: Some(row.expires_at),
        })
    }

    /// Returns bytes only for the owning account and expires stale archives atomically.
    pub async fn download(
        &self,
        account: AccountId,
        id: sqlx::types::Uuid,
    ) -> Result<ExportDownload, AppError> {
        let now = self.clock.now();
        let mut tx = Tx::begin(&self.db, account).await?;
        let row = export_repository::find(&mut tx, id).await?;
        let state = parse_state(&row.state)?;
        if state == ExportState::Expired {
            tx.commit().await?;
            return Err(AppError::new(ErrorCode::ExportExpired));
        }
        if state != ExportState::Ready {
            tx.commit().await?;
            return Err(AppError::new(ErrorCode::ExportNotReady));
        }
        if row.expires_at <= now {
            export_repository::expire(&mut tx, id).await?;
            tx.commit().await?;
            return Err(AppError::new(ErrorCode::ExportExpired));
        }
        let bytes = row
            .bytes
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        let date = now.to_rfc3339();
        let stamp = format!("{}{}{}", &date[0..4], &date[5..7], &date[8..10]);
        let date = LocalDateStamp::parse(&stamp).map_err(|_| internal_error())?;
        let file_name = export_file_name(account, date);
        tx.commit().await?;
        Ok(ExportDownload {
            bytes,
            file_name,
            content_type: "application/zip",
        })
    }

    /// Builds a queued export, records its audit entry, and schedules per-account pruning.
    #[allow(
        clippy::too_many_lines,
        reason = "archive state, bytes, audit and prune job commit atomically"
    )]
    pub async fn build_export(
        &self,
        account: AccountId,
        id: sqlx::types::Uuid,
    ) -> Result<(), AppError> {
        let now = self.clock.now();
        let policy = self.policy_values(account, now).await?;
        let max_attempts = retry_attempts(&policy)?;
        let expires_at = export_expiry(now, export_max_age_days(&policy)?)?;
        let mut tx = Tx::begin(&self.db, account).await?;
        let state = parse_state(&export_repository::lock_state(&mut tx, account, id).await?)?;
        if state == ExportState::Ready {
            tx.commit().await?;
            return Ok(());
        }
        if !matches!(state, ExportState::Queued | ExportState::Running) {
            return Err(AppError::new(ErrorCode::InvalidTransition));
        }
        export_repository::mark_running(&mut tx, id).await?;

        let archive = match self.build_archive(&mut tx, account, now).await {
            Ok(bytes) => bytes,
            Err(error) => {
                tx.rollback().await?;
                self.mark_failed(account, id).await?;
                return Err(error);
            }
        };
        let size_bytes = i64::try_from(archive.len()).map_err(|_| internal_error())?;
        export_repository::mark_ready(&mut tx, id, &archive, size_bytes, expires_at).await?;
        AuditWriter::new(Arc::clone(&self.ids))
            .record(
                &mut tx,
                AuditEntry {
                    account_id: Some(account),
                    actor_id: None,
                    action: AuditAction::AccountExported,
                    target_type: Some(AuditTarget::ACCOUNT),
                    target_id: Some(id.hyphenated().to_string()),
                    details: Details::new().with_detail("count", DetailValue::Int(size_bytes)),
                    request_id: None,
                    occurred_at: now,
                },
            )
            .await?;
        jobs::enqueue(
            &mut tx,
            self.ids.as_ref(),
            now,
            NewJob {
                account_id: Some(account),
                kind: JobKind::from_valid_static("identity.exports.prune"),
                unique_key: Some(id.hyphenated().to_string()),
                payload: serde_json::json!({
                    "account_id": account.to_string(),
                    "export_id": id.hyphenated().to_string(),
                }),
                run_at: expires_at,
                max_attempts,
            },
        )
        .await?;
        tx.commit().await
    }

    async fn build_archive(
        &self,
        tx: &mut Tx,
        account: AccountId,
        now: Instant,
    ) -> Result<Vec<u8>, AppError> {
        let mut sections = Vec::new();
        let mut names = BTreeSet::new();
        for exporter in &self.exporters {
            let declared = exporter.sections();
            let exported = exporter.export(tx, account).await?;
            for section in exported {
                if !declared.contains(&section.name) || !names.insert(section.name) {
                    return Err(internal_error());
                }
                if has_forbidden_key(&section.rows) {
                    return Err(internal_error());
                }
                sections.push(section);
            }
        }
        if !names.contains("account") {
            return Err(internal_error());
        }
        let data = serde_json::to_vec(&ExportDocument {
            schema: EXPORT_FORMAT,
            exported_at: now.to_rfc3339(),
            sections: &sections,
        })
        .map_err(|_| internal_error())?;
        archive_bytes(now, &data)
    }

    async fn mark_failed(&self, account: AccountId, id: sqlx::types::Uuid) -> Result<(), AppError> {
        let mut tx = Tx::begin(&self.db, account).await?;
        export_repository::mark_failed(&mut tx, account, id).await?;
        tx.commit().await
    }

    async fn policy_values(
        &self,
        account: AccountId,
        now: Instant,
    ) -> Result<kinevo_ports::platform::ResolvedPolicies, AppError> {
        self.policies.snapshot_values(Some(account), now).await
    }
}

struct ExportDocument<'a> {
    schema: &'static str,
    exported_at: String,
    sections: &'a [ExportSection],
}

impl Serialize for ExportDocument<'_> {
    fn serialize<S>(&self, serializer: S) -> Result<S::Ok, S::Error>
    where
        S: serde::Serializer,
    {
        let mut map = serializer.serialize_map(Some(2 + self.sections.len()))?;
        map.serialize_entry("schema", self.schema)?;
        map.serialize_entry("exported_at", &self.exported_at)?;
        for section in self.sections {
            map.serialize_entry(section.name, &section.rows)?;
        }
        map.end()
    }
}

fn archive_bytes(now: Instant, data: &[u8]) -> Result<Vec<u8>, AppError> {
    let timestamp = zip_timestamp(now)?;
    let options = SimpleFileOptions::default()
        .compression_method(CompressionMethod::Deflated)
        .last_modified_time(timestamp)
        .system(System::Unix)
        .unix_permissions(0o600);
    let cursor = Cursor::new(Vec::new());
    let mut writer = ZipWriter::new(cursor);
    writer
        .start_file("README.txt", options)
        .map_err(|_| internal_error())?;
    writer
        .write_all(readme_text().as_bytes())
        .map_err(|_| internal_error())?;
    writer
        .start_file("data.json", options)
        .map_err(|_| internal_error())?;
    writer.write_all(data).map_err(|_| internal_error())?;
    writer
        .finish()
        .map(std::io::Cursor::into_inner)
        .map_err(|_| internal_error())
}

fn zip_timestamp(now: Instant) -> Result<DateTime, AppError> {
    let value = now.to_rfc3339();
    let year = value[0..4].parse::<u16>().map_err(|_| internal_error())?;
    let month = value[5..7].parse::<u8>().map_err(|_| internal_error())?;
    let day = value[8..10].parse::<u8>().map_err(|_| internal_error())?;
    let hour = value[11..13].parse::<u8>().map_err(|_| internal_error())?;
    let minute = value[14..16].parse::<u8>().map_err(|_| internal_error())?;
    let second = value[17..19].parse::<u8>().map_err(|_| internal_error())?;
    DateTime::from_date_and_time(year, month, day, hour, minute, second)
        .map_err(|_| internal_error())
}

fn has_forbidden_key(value: &serde_json::Value) -> bool {
    match value {
        serde_json::Value::Object(values) => values.iter().any(|(key, value)| {
            matches!(
                key.as_str(),
                "password_hash"
                    | "token_hash"
                    | "ciphertext"
                    | "totp_seed"
                    | "recovery"
                    | "api_key"
            ) || has_forbidden_key(value)
        }),
        serde_json::Value::Array(values) => values.iter().any(has_forbidden_key),
        _ => false,
    }
}

fn parse_state(value: &str) -> Result<ExportState, AppError> {
    match value {
        "queued" => Ok(ExportState::Queued),
        "running" => Ok(ExportState::Running),
        "ready" => Ok(ExportState::Ready),
        "failed" => Ok(ExportState::Failed),
        "expired" => Ok(ExportState::Expired),
        _ => Err(internal_error()),
    }
}

fn retry_attempts(policies: &kinevo_ports::platform::ResolvedPolicies) -> Result<u8, AppError> {
    policies
        .get(&platform::JOB_RETRY_MAX_ATTEMPTS)
        .and_then(|value| u8::try_from(value).ok())
        .filter(|value| *value > 0)
        .ok_or_else(internal_error)
}

fn export_max_age_days(
    policies: &kinevo_ports::platform::ResolvedPolicies,
) -> Result<i64, AppError> {
    policies
        .get(&platform::EXPORT_MAX_AGE_DAYS)
        .filter(|value| *value > 0)
        .ok_or_else(internal_error)
}

fn export_expiry(now: Instant, days: i64) -> Result<Instant, AppError> {
    let seconds = days.checked_mul(86_400).ok_or_else(internal_error)?;
    now.checked_add_seconds(seconds).ok_or_else(internal_error)
}

fn internal_error() -> AppError {
    AppError::new(ErrorCode::InternalError)
}
