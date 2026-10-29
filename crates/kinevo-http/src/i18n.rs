//! Server error catalogues and locale negotiation.

use kinevo_kernel::validate::tz::Locale;
use std::collections::BTreeMap;

const EN_CATALOG: &str = include_str!("../../../web/src/i18n/en.json");
const ID_CATALOG: &str = include_str!("../../../web/src/i18n/id.json");

/// Embedded, flat message catalogues used by problem responses and module adapters.
#[derive(Clone, Debug, Default)]
pub struct Catalog {
    english: BTreeMap<String, String>,
    indonesian: BTreeMap<String, String>,
}

impl Catalog {
    /// Loads the two compile-time catalogues. Invalid files load empty and are caught by tests.
    #[must_use]
    pub fn load() -> Self {
        Self {
            english: serde_json::from_str(EN_CATALOG).unwrap_or_default(),
            indonesian: serde_json::from_str(ID_CATALOG).unwrap_or_default(),
        }
    }

    /// Finds a code title, falling back to English for an incomplete Indonesian catalogue.
    #[must_use]
    pub fn title(&self, code: &str, locale: Locale) -> Option<&str> {
        self.message(code, "title", locale)
    }

    /// Finds a code detail, falling back to English for an incomplete Indonesian catalogue.
    #[must_use]
    pub fn detail(&self, code: &str, locale: Locale) -> Option<&str> {
        self.message(code, "detail", locale)
    }

    /// Finds a message by its registered key, falling back to English for Indonesian.
    #[must_use]
    pub fn text(&self, key: &str, locale: Locale) -> Option<&str> {
        match locale {
            Locale::En => self.english.get(key),
            Locale::Id => self.indonesian.get(key).or_else(|| self.english.get(key)),
        }
        .map(String::as_str)
    }

    fn message(&self, code: &str, part: &str, locale: Locale) -> Option<&str> {
        let key = format!("error.{code}.{part}");
        self.text(&key, locale)
    }
}

/// Selects the best supported language tag, using the caller's fallback when none match.
#[must_use]
pub fn negotiate(accept_language: Option<&str>, fallback: Locale) -> Locale {
    let mut best: Option<(u16, Locale)> = None;
    for candidate in accept_language.unwrap_or_default().split(',') {
        let mut parts = candidate.trim().split(';');
        let language = parts.next().unwrap_or_default().trim();
        let locale = match language
            .split('-')
            .next()
            .unwrap_or_default()
            .to_ascii_lowercase()
            .as_str()
        {
            "en" => Locale::En,
            "id" => Locale::Id,
            _ => continue,
        };
        let quality = parts
            .find_map(|parameter| parameter.trim().strip_prefix("q="))
            .map_or(1000, parse_quality);
        if quality == 0 {
            continue;
        }
        if best.is_none_or(|(current, _)| quality > current) {
            best = Some((quality, locale));
        }
    }
    best.map_or(fallback, |(_, locale)| locale)
}

fn parse_quality(value: &str) -> u16 {
    let value = value.trim();
    if value == "1" || value == "1.0" || value == "1.00" || value == "1.000" {
        return 1000;
    }
    if value == "0" {
        return 0;
    }
    let Some(fraction) = value.strip_prefix("0.") else {
        return 0;
    };
    if fraction.is_empty()
        || fraction.len() > 3
        || !fraction.bytes().all(|byte| byte.is_ascii_digit())
    {
        return 0;
    }
    let Ok(parsed) = fraction.parse::<u16>() else {
        return 0;
    };
    match fraction.len() {
        1 => parsed * 100,
        2 => parsed * 10,
        _ => parsed,
    }
}

#[cfg(test)]
mod tests {
    use super::negotiate;
    use kinevo_kernel::validate::tz::Locale;

    #[test]
    fn negotiate_cases() {
        assert_eq!(
            negotiate(Some("id-ID,id;q=0.9,en;q=0.5"), Locale::En),
            Locale::Id
        );
        assert_eq!(negotiate(Some("en-US"), Locale::Id), Locale::En);
        assert_eq!(negotiate(Some("fr"), Locale::Id), Locale::Id);
        assert_eq!(negotiate(Some("id;q=0.4,en;q=0.8"), Locale::Id), Locale::En);
        assert_eq!(negotiate(Some("id;q=0"), Locale::En), Locale::En);
    }
}
