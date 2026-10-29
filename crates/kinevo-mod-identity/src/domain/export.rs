//! Pure state transitions and names for account exports.

use kinevo_kernel::{id::AccountId, time::Instant};

/// Persisted state of one account export.
#[derive(Clone, Copy, Debug, Eq, PartialEq, serde::Serialize)]
#[serde(rename_all = "snake_case")]
pub enum ExportState {
    /// No export row exists yet.
    None,
    /// The export job is waiting to be claimed.
    Queued,
    /// A worker is building the archive.
    Running,
    /// The archive is available until `expires_at`.
    Ready,
    /// The previous build failed and can be requested again.
    Failed,
    /// The archive expired and its bytes were deleted.
    Expired,
}

impl ExportState {
    /// Returns the persisted state value. `None` has no database representation.
    #[must_use]
    pub const fn as_str(self) -> Option<&'static str> {
        match self {
            Self::None => None,
            Self::Queued => Some("queued"),
            Self::Running => Some("running"),
            Self::Ready => Some("ready"),
            Self::Failed => Some("failed"),
            Self::Expired => Some("expired"),
        }
    }
}

/// Event applied to the account-export state machine.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum ExportEvent {
    /// Create a queued export row.
    Request,
    /// A worker claimed a queued job.
    WorkerPicked,
    /// The worker lease elapsed before the build completed.
    LeaseExpired,
    /// The worker produced the archive.
    Built,
    /// The worker failed to produce the archive.
    BuildFailed,
    /// The ready archive reached its expiry instant.
    Expire,
}

/// Rejected export transition.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct TransitionError;

/// Applies one SM-21 event using only the injected clock.
pub fn transition(
    state: ExportState,
    event: ExportEvent,
    now: Instant,
    expires_at: Option<Instant>,
) -> Result<ExportState, TransitionError> {
    match (state, event) {
        (ExportState::None, ExportEvent::Request)
        | (ExportState::Running, ExportEvent::LeaseExpired) => Ok(ExportState::Queued),
        (ExportState::Queued, ExportEvent::WorkerPicked) => Ok(ExportState::Running),
        (ExportState::Running, ExportEvent::Built)
            if expires_at.is_some_and(|expiry| expiry > now) =>
        {
            Ok(ExportState::Ready)
        }
        (ExportState::Running, ExportEvent::BuildFailed) => Ok(ExportState::Failed),
        (ExportState::Ready, ExportEvent::Expire)
            if expires_at.is_some_and(|expiry| expiry <= now) =>
        {
            Ok(ExportState::Expired)
        }
        _ => Err(TransitionError),
    }
}

/// Validated UTC date stamp for an export archive filename.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct LocalDateStamp {
    year: u16,
    month: u8,
    day: u8,
}

impl LocalDateStamp {
    /// Parses the canonical `YYYYMMDD` date used by export filenames.
    pub fn parse(value: &str) -> Result<Self, TransitionError> {
        if value.len() != 8 || !value.bytes().all(|byte| byte.is_ascii_digit()) {
            return Err(TransitionError);
        }
        let year = value[0..4].parse::<u16>().map_err(|_| TransitionError)?;
        let month = value[4..6].parse::<u8>().map_err(|_| TransitionError)?;
        let day = value[6..8].parse::<u8>().map_err(|_| TransitionError)?;
        let days_in_month = match month {
            1 | 3 | 5 | 7 | 8 | 10 | 12 => 31,
            4 | 6 | 9 | 11 => 30,
            2 if year % 400 == 0 || (year % 4 == 0 && year % 100 != 0) => 29,
            2 => 28,
            _ => return Err(TransitionError),
        };
        if day == 0 || day > days_in_month || !(1970..=2100).contains(&year) {
            return Err(TransitionError);
        }
        Ok(Self { year, month, day })
    }

    /// Returns the canonical eight-digit date.
    #[must_use]
    pub fn as_str(self) -> String {
        format!("{:04}{:02}{:02}", self.year, self.month, self.day)
    }
}

/// Builds the stable filename required by the export contract.
#[must_use]
pub fn export_file_name(account: AccountId, date: LocalDateStamp) -> String {
    format!("kinevo-export-{account}-{}.zip", date.as_str())
}

/// Explains the M1 export contents and the canonical data conventions.
#[must_use]
pub fn readme_text() -> &'static str {
    "Kinevo account export format export_v1.\n\nThis archive contains data.json with your account profile and the sections contributed by the compiled Kinevo modules. IDs are preserved and timestamps use canonical UTC instants. M1 includes the account profile; calendar and canvas files are added by their owning modules in later milestones. Password hashes, tokens, recovery codes, TOTP seeds, API keys, audit entries for other accounts, and instance data are excluded.\n"
}
