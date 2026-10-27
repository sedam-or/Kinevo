use super::text::{is_forbidden_control, is_spoofing_character};
use crate::errors::FieldCode;
use jiff::tz::TimeZone;

// SPEC: ID-FR-14, CQ-06
/// An exact canonical IANA time-zone identifier from the bundled database.
#[derive(Clone, Eq, PartialEq)]
pub struct IanaTz(String);

impl IanaTz {
    /// Trims and validates a canonical IANA identifier.
    pub fn parse(raw: &str) -> Result<Self, FieldCode> {
        if raw
            .chars()
            .any(|character| is_spoofing_character(character) || is_forbidden_control(character))
        {
            return Err(FieldCode::TimezoneInvalid);
        }
        let value = raw.trim();
        let zone = TimeZone::get(value).map_err(|_| FieldCode::TimezoneInvalid)?;
        let canonical = zone.iana_name().ok_or(FieldCode::TimezoneInvalid)?;
        if canonical != value {
            return Err(FieldCode::TimezoneInvalid);
        }
        Ok(Self(canonical.to_owned()))
    }

    /// Borrows the canonical identifier.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

// SPEC: ID-FR-14, VAL-06, CQ-06
/// The closed set of locales supported by the identity module.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum Locale {
    /// English language content.
    En,
    /// Indonesian language content.
    Id,
}

impl Locale {
    /// Parses a supported locale key.
    pub fn parse(raw: &str) -> Result<Self, FieldCode> {
        match raw {
            "en" => Ok(Self::En),
            "id" => Ok(Self::Id),
            _ => Err(FieldCode::EnumInvalid),
        }
    }

    /// Returns the stable locale key.
    #[must_use]
    pub const fn as_str(self) -> &'static str {
        match self {
            Self::En => "en",
            Self::Id => "id",
        }
    }
}

#[cfg(test)]
mod tests {
    use super::{IanaTz, Locale};
    use crate::errors::FieldCode;

    #[test]
    fn id_fr_14_timezone_and_locale() {
        assert_eq!(
            IanaTz::parse("Asia/Jakarta").map(|value| value.as_str().to_owned()),
            Ok("Asia/Jakarta".to_owned())
        );
        assert_eq!(
            IanaTz::parse("UTC+7").err(),
            Some(FieldCode::TimezoneInvalid)
        );
        assert_eq!(Locale::parse("id").map(Locale::as_str), Ok("id"));
        assert_eq!(Locale::parse("fr").err(), Some(FieldCode::EnumInvalid));
    }
}
