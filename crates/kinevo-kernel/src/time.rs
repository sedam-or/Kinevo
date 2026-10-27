//! Validated UTC instants, half-open ranges, and injected clocks.

use jiff::{Timestamp, tz::TimeZone};
use serde::{Deserialize, Deserializer, Serialize, Serializer, de};
use std::fmt::{self, Write as _};

/// A UTC instant with millisecond precision and the supported domain year range.
// SPEC: ARCH-05, D-17
#[derive(Clone, Copy, Debug, Eq, Hash, Ord, PartialEq, PartialOrd)]
pub struct Instant {
    timestamp: Timestamp,
}

impl Instant {
    pub fn parse_rfc3339(value: &str) -> Result<Self, InstantError> {
        validate_rfc3339_shape(value)?;
        let timestamp = value
            .parse::<Timestamp>()
            .map_err(|_| InstantError::invalid())?;
        Self::from_timestamp(timestamp)
    }

    #[must_use]
    pub fn to_rfc3339(&self) -> String {
        let mut result = self.timestamp.strftime("%Y-%m-%dT%H:%M:%S").to_string();
        let milliseconds = self.unix_millis().rem_euclid(1_000);
        if milliseconds != 0 {
            let _ = write!(result, ".{milliseconds:03}");
        }
        result.push('Z');
        result
    }

    pub fn from_unix_millis(milliseconds: i64) -> Result<Self, InstantError> {
        let timestamp =
            Timestamp::from_millisecond(milliseconds).map_err(|_| InstantError::invalid())?;
        Self::from_timestamp(timestamp)
    }

    #[must_use]
    pub fn unix_millis(&self) -> i64 {
        self.timestamp.as_millisecond()
    }

    #[must_use]
    pub fn checked_add_seconds(&self, seconds: i64) -> Option<Self> {
        let milliseconds = seconds.checked_mul(1_000)?;
        let result = self.unix_millis().checked_add(milliseconds)?;
        Self::from_unix_millis(result).ok()
    }

    fn from_timestamp(timestamp: Timestamp) -> Result<Self, InstantError> {
        let year = timestamp.to_zoned(TimeZone::UTC).year();
        if !(1970..=2100).contains(&year) {
            return Err(InstantError::invalid());
        }
        Ok(Self { timestamp })
    }
}

fn validate_rfc3339_shape(value: &str) -> Result<(), InstantError> {
    let zone_start = if value.ends_with('Z') {
        value
            .len()
            .checked_sub(1)
            .ok_or_else(InstantError::invalid)?
    } else {
        value
            .char_indices()
            .skip(19)
            .find_map(|(index, character)| matches!(character, '+' | '-').then_some(index))
            .ok_or_else(InstantError::invalid)?
    };

    let zone = value.get(zone_start..).ok_or_else(InstantError::invalid)?;
    let zone_valid = zone == "Z"
        || (zone.len() == 6
            && matches!(zone.get(0..1), Some("+" | "-"))
            && zone.get(3..4) == Some(":")
            && ascii_digits(zone.get(1..3).ok_or_else(InstantError::invalid)?)
            && ascii_digits(zone.get(4..6).ok_or_else(InstantError::invalid)?));
    if !zone_valid {
        return Err(InstantError::invalid());
    }

    let date_time = value.get(..zone_start).ok_or_else(InstantError::invalid)?;
    let (whole_seconds, fraction) = match date_time.split_once('.') {
        Some((whole, fraction)) => (whole, Some(fraction)),
        None => (date_time, None),
    };
    if whole_seconds.len() != 19
        || whole_seconds.get(4..5) != Some("-")
        || whole_seconds.get(7..8) != Some("-")
        || whole_seconds.get(10..11) != Some("T")
        || whole_seconds.get(13..14) != Some(":")
        || whole_seconds.get(16..17) != Some(":")
        || !ascii_digits(whole_seconds.get(0..4).ok_or_else(InstantError::invalid)?)
        || !ascii_digits(whole_seconds.get(5..7).ok_or_else(InstantError::invalid)?)
        || !ascii_digits(whole_seconds.get(8..10).ok_or_else(InstantError::invalid)?)
        || !ascii_digits(
            whole_seconds
                .get(11..13)
                .ok_or_else(InstantError::invalid)?,
        )
        || !ascii_digits(
            whole_seconds
                .get(14..16)
                .ok_or_else(InstantError::invalid)?,
        )
        || !ascii_digits(
            whole_seconds
                .get(17..19)
                .ok_or_else(InstantError::invalid)?,
        )
    {
        return Err(InstantError::invalid());
    }
    if let Some(fraction) = fraction
        && (!(1..=3).contains(&fraction.len()) || !ascii_digits(fraction))
    {
        return Err(InstantError::invalid());
    }
    Ok(())
}

fn ascii_digits(value: &str) -> bool {
    !value.is_empty() && value.bytes().all(|byte| byte.is_ascii_digit())
}

impl Serialize for Instant {
    fn serialize<S>(&self, serializer: S) -> Result<S::Ok, S::Error>
    where
        S: Serializer,
    {
        serializer.serialize_str(&self.to_rfc3339())
    }
}

impl<'de> Deserialize<'de> for Instant {
    fn deserialize<D>(deserializer: D) -> Result<Self, D::Error>
    where
        D: Deserializer<'de>,
    {
        let value = String::deserialize(deserializer)?;
        Self::parse_rfc3339(&value).map_err(de::Error::custom)
    }
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct InstantError;

impl InstantError {
    const fn invalid() -> Self {
        Self
    }
}

impl fmt::Display for InstantError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("invalid instant")
    }
}

impl std::error::Error for InstantError {}

/// A non-empty half-open time range `[start, end)`.
// SPEC: D-17
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct TimeRange {
    start: Instant,
    end: Instant,
}

impl TimeRange {
    pub fn new(start: Instant, end: Instant) -> Result<Self, RangeError> {
        if end <= start {
            return Err(RangeError);
        }
        Ok(Self { start, end })
    }

    #[must_use]
    pub fn start(&self) -> Instant {
        self.start
    }

    #[must_use]
    pub fn end(&self) -> Instant {
        self.end
    }

    #[must_use]
    pub fn contains(&self, instant: Instant) -> bool {
        self.start <= instant && instant < self.end
    }

    #[must_use]
    pub fn overlaps(&self, other: &Self) -> bool {
        self.start < other.end && other.start < self.end
    }

    #[must_use]
    pub fn duration_seconds(&self) -> i64 {
        (self.end.unix_millis() - self.start.unix_millis()) / 1_000
    }
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct RangeError;

impl fmt::Display for RangeError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("range end must be after start")
    }
}

impl std::error::Error for RangeError {}

pub trait Clock: Send + Sync {
    fn now(&self) -> Instant;
}

#[cfg(any(test, feature = "testing"))]
pub struct FixedClock {
    milliseconds: std::sync::atomic::AtomicI64,
    initial: Instant,
}

#[cfg(any(test, feature = "testing"))]
impl FixedClock {
    #[must_use]
    pub fn new(initial: Instant) -> Self {
        Self {
            milliseconds: std::sync::atomic::AtomicI64::new(initial.unix_millis()),
            initial,
        }
    }

    pub fn set(&self, instant: Instant) {
        self.milliseconds
            .store(instant.unix_millis(), std::sync::atomic::Ordering::SeqCst);
    }

    pub fn advance_seconds(&self, seconds: i64) -> Result<(), InstantError> {
        let delta = seconds
            .checked_mul(1_000)
            .ok_or_else(InstantError::invalid)?;
        self.milliseconds
            .try_update(
                std::sync::atomic::Ordering::SeqCst,
                std::sync::atomic::Ordering::SeqCst,
                |current| {
                    current
                        .checked_add(delta)
                        .filter(|next| Instant::from_unix_millis(*next).is_ok())
                },
            )
            .map(|_| ())
            .map_err(|_| InstantError::invalid())
    }
}

#[cfg(any(test, feature = "testing"))]
impl Clock for FixedClock {
    fn now(&self) -> Instant {
        match Instant::from_unix_millis(self.milliseconds.load(std::sync::atomic::Ordering::SeqCst))
        {
            Ok(instant) => instant,
            Err(_) => self.initial,
        }
    }
}

#[cfg(test)]
mod tests {
    use super::{Clock, FixedClock, Instant, TimeRange};

    fn instant(value: &str) -> Instant {
        Instant::parse_rfc3339(value).expect("test instant is valid")
    }

    #[test]
    fn parse_offset_formats_utc_z() {
        assert_eq!(
            instant("2026-11-02T12:00:00+07:00").to_rfc3339(),
            "2026-11-02T05:00:00Z"
        );
        assert_eq!(
            instant("2026-11-02T12:00:00.120+07:00").to_rfc3339(),
            "2026-11-02T05:00:00.120Z"
        );
    }

    #[test]
    fn rejects_four_fraction_digits_and_out_of_range_year() {
        assert!(Instant::parse_rfc3339("2026-11-02T05:00:00.1234Z").is_err());
        assert!(Instant::parse_rfc3339("2101-01-01T00:00:00Z").is_err());
        assert!(Instant::parse_rfc3339("1969-12-31T23:59:59Z").is_err());
    }

    #[test]
    fn half_open_overlap() {
        let first = TimeRange::new(
            instant("2026-11-02T09:00:00Z"),
            instant("2026-11-02T10:00:00Z"),
        )
        .expect("range is non-empty");
        let adjacent = TimeRange::new(
            instant("2026-11-02T10:00:00Z"),
            instant("2026-11-02T11:00:00Z"),
        )
        .expect("range is non-empty");
        let overlapping = TimeRange::new(
            instant("2026-11-02T09:00:00Z"),
            instant("2026-11-02T10:30:00Z"),
        )
        .expect("range is non-empty");

        assert!(!first.overlaps(&adjacent));
        assert!(overlapping.overlaps(&adjacent));
        assert!(first.contains(instant("2026-11-02T09:00:00Z")));
        assert!(!first.contains(instant("2026-11-02T10:00:00Z")));
        assert_eq!(overlapping.duration_seconds(), 5_400);
    }

    #[test]
    fn empty_range_rejected() {
        let start = instant("2026-11-02T09:00:00Z");
        assert!(TimeRange::new(start, start).is_err());
    }

    #[test]
    fn fixed_clock_advances() {
        let clock = FixedClock::new(instant("2026-11-02T05:00:00Z"));
        clock
            .advance_seconds(90)
            .expect("advance remains within supported range");
        assert_eq!(clock.now(), instant("2026-11-02T05:01:30Z"));
    }
}
