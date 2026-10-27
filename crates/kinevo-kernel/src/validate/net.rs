use super::text::{is_forbidden_control, is_spoofing_character};
use crate::errors::FieldCode;
use unicode_normalization::UnicodeNormalization;
use url::{Host, Url};

// SPEC: DOM-07, VAL-02, VAL-03, CQ-06
/// An email address stored in its trimmed, lower-case NFC form.
#[derive(Clone, Eq, PartialEq)]
pub struct Email(String);

impl Email {
    /// Normalizes and validates an email address without exposing invalid input.
    pub fn parse(raw: &str) -> Result<Self, FieldCode> {
        if raw
            .chars()
            .any(|character| is_spoofing_character(character) || is_forbidden_control(character))
        {
            return Err(FieldCode::EmailInvalid);
        }
        let trimmed: String = raw.trim().nfc().collect();
        let lowered: String = trimmed.to_lowercase().nfc().collect();
        if lowered.matches('@').count() != 1 {
            return Err(FieldCode::EmailInvalid);
        }
        let (local, domain) = lowered.split_once('@').ok_or(FieldCode::EmailInvalid)?;
        if local.is_empty()
            || local.len() > 64
            || domain.is_empty()
            || lowered.len() > 254
            || local.chars().any(|character| {
                character.is_whitespace() || matches!(character, '"' | '\'' | '(' | ')')
            })
            || domain.contains('%')
        {
            return Err(FieldCode::EmailInvalid);
        }

        let parsed_host = Host::parse(domain).map_err(|_| FieldCode::EmailInvalid)?;
        let Host::Domain(ascii_domain) = parsed_host else {
            return Err(FieldCode::EmailInvalid);
        };
        let labels = ascii_domain.split('.').collect::<Vec<_>>();
        if labels.len() < 2
            || ascii_domain.len() > 253
            || labels.iter().any(|label| {
                label.is_empty()
                    || label.len() > 63
                    || label.starts_with('-')
                    || label.ends_with('-')
                    || !label
                        .bytes()
                        .all(|byte| byte.is_ascii_alphanumeric() || byte == b'-')
            })
        {
            return Err(FieldCode::EmailInvalid);
        }
        let normalized = format!("{local}@{ascii_domain}");
        if normalized.len() > 254 {
            return Err(FieldCode::EmailInvalid);
        }
        Ok(Self(normalized))
    }

    /// Borrows the normalized address.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

// SPEC: VAL-02, VAL-03, CQ-06
/// An absolute HTTPS URL, or an HTTP URL whose host is loopback.
#[derive(Clone, Eq, PartialEq)]
pub struct HttpsUrl(String);

impl HttpsUrl {
    /// Parses a URL, rejects credentials and fragments, then stores its canonical form.
    pub fn parse(raw: &str) -> Result<Self, FieldCode> {
        if raw
            .chars()
            .any(|character| is_spoofing_character(character) || is_forbidden_control(character))
        {
            return Err(FieldCode::UrlInvalid);
        }
        let trimmed = raw.trim();
        if trimmed.chars().count() > 2048 {
            return Err(FieldCode::UrlInvalid);
        }
        let parsed = Url::parse(trimmed).map_err(|_| FieldCode::UrlInvalid)?;
        let host = parsed.host().ok_or(FieldCode::UrlInvalid)?;
        let loopback = is_loopback(&host);
        let authority_has_userinfo = parsed
            .as_str()
            .split_once("://")
            .and_then(|(_, rest)| rest.split(['/', '?', '#']).next())
            .is_some_and(|authority| authority.contains('@'));
        if authority_has_userinfo
            || !parsed.username().is_empty()
            || parsed.password().is_some()
            || parsed.fragment().is_some()
            || (parsed.scheme() != "https" && !(parsed.scheme() == "http" && loopback))
        {
            return Err(FieldCode::UrlInvalid);
        }
        let mut normalized = parsed.to_string();
        if parsed.path() == "/"
            && parsed.query().is_none()
            && parsed.fragment().is_none()
            && normalized.ends_with('/')
        {
            normalized.pop();
        }
        Ok(Self(normalized))
    }

    /// Borrows the canonical URL.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

fn is_loopback(host: &Host<&str>) -> bool {
    match host {
        Host::Domain(domain) => domain.eq_ignore_ascii_case("localhost"),
        Host::Ipv4(address) => address.is_loopback(),
        Host::Ipv6(address) => address.is_loopback(),
    }
}

#[cfg(test)]
mod tests {
    use super::{Email, HttpsUrl};
    use crate::errors::FieldCode;

    #[test]
    fn dom_07_email_lowercase_nfc() {
        assert_eq!(
            Email::parse("A@B.COM").map(|value| value.as_str().to_owned()),
            Ok("a@b.com".to_owned())
        );
        assert_eq!(
            Email::parse("A\u{ff20}B.COM").err(),
            Some(FieldCode::EmailInvalid)
        );
    }

    #[test]
    fn https_only_except_loopback() {
        assert_eq!(
            HttpsUrl::parse("http://example.com").err(),
            Some(FieldCode::UrlInvalid)
        );
        assert!(HttpsUrl::parse("http://localhost:8080").is_ok());
    }
}
