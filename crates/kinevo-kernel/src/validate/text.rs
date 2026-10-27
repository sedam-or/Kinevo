#[cfg(test)]
mod tests {
    use super::{Key, SecretText, ShortText, normalize_long_text};
    use crate::errors::{DetailValue, FieldCode};
    use regex::Regex;

    #[test]
    fn val_04_counts_scalars() {
        let eighty = "😀".repeat(80);
        let eighty_one = "😀".repeat(81);

        assert!(ShortText::parse(&eighty, 1, 80).is_ok());
        let error = ShortText::parse_with_details(&eighty_one, 1, 80)
            .err()
            .unwrap();
        assert_eq!(error.0, FieldCode::TooLong);
        assert_eq!(error.1.as_map().get("max"), Some(&DetailValue::Int(80)));
    }

    #[test]
    fn val_03_zero_width_removed_or_rejected() {
        let text =
            ShortText::parse("  A\u{200b}da  ", 1, 80).map(|value| value.as_str().to_owned());
        assert_eq!(text, Ok("Ada".to_owned()));

        let pattern = Regex::new("^[a-z]+$").expect("literal test pattern compiles");
        assert_eq!(
            Key::parse("ada\u{200b}", &pattern).err(),
            Some(FieldCode::KeyInvalid)
        );
    }

    #[test]
    fn val_03_long_text_normalizes_crlf_and_tab() {
        assert_eq!(normalize_long_text("a\r\nb\tc"), Ok("a\nb\tc".to_owned()));
        assert_eq!(
            normalize_long_text("a\0b").err(),
            Some(FieldCode::InvalidCharacters)
        );
    }

    #[test]
    fn val_03_secret_text_keeps_whitespace_and_redacts_debug() {
        let value = SecretText::parse("  private  ".to_owned()).expect("within length bounds");
        assert_eq!(value.expose(), "  private  ");
        assert!(!format!("{value:?}").contains("private"));
    }
}
use crate::{
    errors::{DetailValue, Details, FieldCode},
    secret::Secret,
};
use regex::Regex;
use std::{fmt, sync::OnceLock};
use unicode_normalization::UnicodeNormalization;

// SPEC: VAL-02, VAL-03
/// Normalizes short display text and removes spoofing format characters.
pub fn normalize_short_text(raw: &str) -> Result<String, FieldCode> {
    let mut output = String::new();
    for character in raw.trim().chars() {
        if is_spoofing_character(character) {
            continue;
        }
        if is_forbidden_control(character) || matches!(character, '\n' | '\t' | '\r') {
            return Err(FieldCode::InvalidCharacters);
        }
        output.push(character);
    }
    Ok(output.nfc().collect())
}

// SPEC: VAL-02, VAL-03
/// Normalizes long text line endings while preserving permitted tabs and newlines.
pub fn normalize_long_text(raw: &str) -> Result<String, FieldCode> {
    let line_normalized = raw.replace("\r\n", "\n");
    if line_normalized.chars().any(|character| {
        is_noncharacter(character) || (character.is_control() && !matches!(character, '\n' | '\t'))
    }) {
        return Err(FieldCode::InvalidCharacters);
    }
    Ok(line_normalized.nfc().collect())
}

pub(super) fn is_spoofing_character(character: char) -> bool {
    matches!(character as u32,
        0x200B..=0x200F | 0x202A..=0x202E | 0x2066..=0x2069)
}

pub(super) fn is_forbidden_control(character: char) -> bool {
    character.is_control() || is_noncharacter(character)
}

fn is_noncharacter(character: char) -> bool {
    matches!(character as u32, 0xFFFE | 0xFFFF)
}

fn normalize_sensitive_text(raw: &str, lower_case: bool) -> Result<String, FieldCode> {
    if raw
        .chars()
        .any(|character| is_spoofing_character(character) || is_forbidden_control(character))
    {
        return Err(FieldCode::InvalidCharacters);
    }
    let normalized: String = raw.trim().nfc().collect();
    if lower_case {
        Ok(normalized.to_lowercase().nfc().collect())
    } else {
        Ok(normalized)
    }
}

// SPEC: VAL-04, CQ-06
/// A short string normalized and validated against a caller-provided scalar bound.
#[derive(Clone, Eq, PartialEq)]
pub struct ShortText(String);

impl ShortText {
    /// Creates bounded short text; errors never include the input value.
    pub fn parse(raw: &str, min: usize, max: usize) -> Result<Self, FieldCode> {
        Self::parse_with_details(raw, min, max).map_err(|(code, _details)| code)
    }

    /// Creates bounded short text and returns registry-safe length details on failure.
    pub fn parse_with_details(
        raw: &str,
        min: usize,
        max: usize,
    ) -> Result<Self, (FieldCode, Details)> {
        let value = normalize_short_text(raw).map_err(|code| (code, Details::new()))?;
        let length = value.chars().count();
        if length == 0 && min > 0 {
            return Err((FieldCode::Required, Details::new()));
        }
        if length < min {
            let bound = i64::try_from(min).unwrap_or(i64::MAX);
            return Err((
                FieldCode::TooShort,
                Details::new().with_detail("min", DetailValue::Int(bound)),
            ));
        }
        if length > max {
            let bound = i64::try_from(max).unwrap_or(i64::MAX);
            return Err((
                FieldCode::TooLong,
                Details::new().with_detail("max", DetailValue::Int(bound)),
            ));
        }
        Ok(Self(value))
    }

    /// Borrows the normalized value.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

// SPEC: VAL-02, VAL-04, SEC-03, CQ-10
/// A secret string with verbatim normalization and a fixed field-kind bound.
pub struct SecretText(Secret<String>);

impl SecretText {
    /// Accepts 1–4096 Unicode scalar values without trimming or normalizing them.
    pub fn parse(raw: String) -> Result<Self, FieldCode> {
        let length = raw.chars().count();
        if length == 0 {
            return Err(FieldCode::Required);
        }
        if length > 4096 {
            return Err(FieldCode::TooLong);
        }
        Ok(Self(Secret::new(raw)))
    }

    /// Explicitly exposes the secret for a validated use site.
    #[must_use]
    pub fn expose(&self) -> &str {
        self.0.expose()
    }
}

impl fmt::Debug for SecretText {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("SecretText(<redacted>)")
    }
}

// SPEC: VAL-02, VAL-03, VAL-10, CQ-06
/// A lower-case key validated by a caller-owned linear-time regular expression.
#[derive(Clone, Eq, PartialEq)]
pub struct Key(String);

impl Key {
    /// Normalizes a key and requires the supplied pattern to match the whole value.
    pub fn parse(raw: &str, pattern: &Regex) -> Result<Self, FieldCode> {
        let value = normalize_sensitive_text(raw, true).map_err(|_| FieldCode::KeyInvalid)?;
        let full_match = pattern
            .find(&value)
            .is_some_and(|matched| matched.start() == 0 && matched.end() == value.len());
        if value.is_empty() || !full_match {
            Err(FieldCode::KeyInvalid)
        } else {
            Ok(Self(value))
        }
    }

    /// Borrows the normalized key.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

// SPEC: VAL-02, VAL-03, CQ-06
/// A fixed-length ASCII digit string.
#[derive(Clone, Eq, PartialEq)]
pub struct Digits(String);

impl Digits {
    /// Trims and validates exactly `len` ASCII digits.
    pub fn parse(raw: &str, len: usize) -> Result<Self, FieldCode> {
        let value = raw.trim();
        if value.len() != len || !value.bytes().all(|byte| byte.is_ascii_digit()) {
            return Err(FieldCode::DigitsInvalid);
        }
        Ok(Self(value.to_owned()))
    }

    /// Borrows the validated digit string.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

// SPEC: VAL-10, CQ-06
/// An opaque token containing exactly 43 URL-safe ASCII characters.
#[derive(Clone, Eq, PartialEq)]
pub struct OpaqueToken(String);

impl OpaqueToken {
    /// Validates a token without retaining any invalid input in an error.
    pub fn parse(raw: &str) -> Result<Self, FieldCode> {
        static TOKEN_PATTERN: OnceLock<Result<Regex, regex::Error>> = OnceLock::new();
        let pattern = TOKEN_PATTERN.get_or_init(|| Regex::new(r"\A[A-Za-z0-9_-]{43}\z"));
        if pattern.as_ref().is_ok_and(|pattern| pattern.is_match(raw)) {
            Ok(Self(raw.to_owned()))
        } else {
            Err(FieldCode::TokenInvalid)
        }
    }

    /// Borrows the validated token.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}
