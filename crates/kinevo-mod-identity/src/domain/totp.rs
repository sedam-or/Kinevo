//! Pure RFC 6238 TOTP and recovery-code mechanics.

use std::fmt;

use kinevo_kernel::{crypto::hash_token, id::TokenGen, secret::Secret, time::Instant};
use totp_rs::{Algorithm, Builder, Secret as TotpSecret, Totp};

/// The 32-symbol recovery alphabet selected by D-107.
pub const RECOVERY_ALPHABET: &str = "ABCDEFGHJKMNPQRSTVWXYZ23456789*~";

const RECOVERY_CODE_COUNT: usize = 10;
const RECOVERY_CODE_LENGTH: usize = 10;
const RECOVERY_CODE_GROUP: usize = 5;
const TOTP_STEP_SECONDS: u64 = 30;

/// A one-time displayable recovery credential.
pub struct RecoveryCode(Secret<String>);

impl RecoveryCode {
    /// Parses and canonicalizes a recovery code, accepting lowercase and separators.
    #[must_use]
    pub fn parse(value: &str) -> Option<Self> {
        let normalized = normalize(value)?;
        Some(Self(Secret::new(format_grouped(&normalized))))
    }

    /// Returns the one-time display form, grouped into two five-character chunks.
    #[must_use]
    pub fn as_str(&self) -> &str {
        self.0.expose()
    }

    fn normalized(&self) -> String {
        self.0
            .expose()
            .bytes()
            .filter(|byte| *byte != b'-' && *byte != b' ')
            .collect::<Vec<_>>()
            .into_iter()
            .map(char::from)
            .collect()
    }
}

impl fmt::Debug for RecoveryCode {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("RecoveryCode(<redacted>)")
    }
}

/// Returns the RFC 6238 counter step containing `now`.
#[must_use]
pub fn step_for(now: Instant, period_seconds: u64) -> u64 {
    let seconds = u64::try_from(now.unix_millis().div_euclid(1_000).max(0)).unwrap_or(0);
    seconds / period_seconds.max(1)
}

/// Checks an RFC 6238 token and returns its matched step if the caller accepts that step.
#[must_use]
pub fn accepts(
    seed_base32: &str,
    code: &str,
    now: Instant,
    mut last_used_step_check: impl FnMut(u64) -> bool,
) -> Option<u64> {
    if code.len() != 6 || !code.bytes().all(|byte| byte.is_ascii_digit()) {
        return None;
    }
    let totp = build(seed_base32, None)?;
    let timestamp = u64::try_from(now.unix_millis().div_euclid(1_000)).ok()?;
    let step = totp.check(code, timestamp)?;
    last_used_step_check(step).then_some(step)
}

/// Builds an RFC 6238 provisioning URI for the account label.
#[must_use]
pub fn otpauth_uri(seed_base32: &str, account_label: &str) -> Option<String> {
    let mut uri = build(seed_base32, Some(account_label))?.to_url().ok()?;
    add_uri_parameter(&mut uri, "algorithm", "SHA1");
    add_uri_parameter(&mut uri, "digits", "6");
    add_uri_parameter(&mut uri, "period", "30");
    Some(uri)
}

/// Creates ten one-time recovery codes from injected token bytes.
#[must_use]
pub fn generate_recovery_codes(tokens: &dyn TokenGen) -> [RecoveryCode; RECOVERY_CODE_COUNT] {
    std::array::from_fn(|_| {
        let mut random = [0_u8; 7];
        tokens.fill(&mut random);
        let mut normalized = String::with_capacity(RECOVERY_CODE_LENGTH);
        let mut accumulator = 0_u8;
        let mut bits = 0_u8;
        'bytes: for byte in random {
            for shift in (0..8).rev() {
                accumulator = (accumulator << 1) | ((byte >> shift) & 1);
                bits += 1;
                if bits == 5 {
                    if let Some(symbol) = RECOVERY_ALPHABET.as_bytes().get(usize::from(accumulator))
                    {
                        normalized.push(char::from(*symbol));
                    }
                    accumulator = 0;
                    bits = 0;
                    if normalized.len() == RECOVERY_CODE_LENGTH {
                        break 'bytes;
                    }
                }
            }
        }
        RecoveryCode(Secret::new(format_grouped(&normalized)))
    })
}

/// Hashes the normalized recovery code for storage.
#[must_use]
pub fn hash(code: &RecoveryCode) -> [u8; 32] {
    hash_token(code.normalized().as_bytes())
}

/// Parses a supplied code and returns its storage hash.
#[must_use]
pub fn hash_input(value: &str) -> Option<[u8; 32]> {
    RecoveryCode::parse(value).map(|code| hash(&code))
}

fn normalize(value: &str) -> Option<String> {
    let mut normalized = Vec::with_capacity(RECOVERY_CODE_LENGTH);
    for byte in value.bytes() {
        if byte == b'-' || byte == b' ' {
            continue;
        }
        let upper = byte.to_ascii_uppercase();
        if !RECOVERY_ALPHABET.as_bytes().contains(&upper) {
            return None;
        }
        normalized.push(upper);
        if normalized.len() > RECOVERY_CODE_LENGTH {
            return None;
        }
    }
    if normalized.len() != RECOVERY_CODE_LENGTH {
        return None;
    }
    String::from_utf8(normalized).ok()
}

fn format_grouped(normalized: &str) -> String {
    let mut display = String::with_capacity(RECOVERY_CODE_LENGTH + 1);
    for (index, symbol) in normalized.chars().enumerate() {
        if index == RECOVERY_CODE_GROUP {
            display.push('-');
        }
        display.push(symbol);
    }
    display
}

fn add_uri_parameter(uri: &mut String, key: &str, value: &str) {
    let exists = uri.split_once('?').is_some_and(|(_, query)| {
        query
            .split('&')
            .any(|field| field.split('=').next() == Some(key))
    });
    if !exists {
        uri.push(if uri.contains('?') { '&' } else { '?' });
        uri.push_str(key);
        uri.push('=');
        uri.push_str(value);
    }
}

fn build(seed_base32: &str, account_label: Option<&str>) -> Option<Totp> {
    let secret = TotpSecret::try_from_base32(seed_base32).ok()?;
    let mut builder = Builder::new()
        .with_algorithm(Algorithm::SHA1)
        .with_digits(6)
        .with_step_duration(TOTP_STEP_SECONDS)
        .with_skew(1)
        .with_secret(secret);
    if let Some(account_label) = account_label {
        builder = builder
            .with_issuer(Some("Nivaras"))
            .with_account_name(account_label);
    }
    builder.build().ok()
}
