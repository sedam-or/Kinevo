//! Cursor-signed pagination types.

use base64::{Engine as _, engine::general_purpose::URL_SAFE_NO_PAD};
use hmac::{Hmac, KeyInit, Mac};
use serde::Serialize;
use sha2::Sha256;

use crate::{
    errors::{AppError, DetailValue, Details, ErrorCode, FieldCode, FieldError},
    secret::Secret,
    time::Instant,
};

/// Default page size defined by API-30.
pub const DEFAULT_LIMIT: u16 = 50;
/// Maximum page size defined by API-30.
pub const MAX_LIMIT: u16 = 200;
const CURSOR_VERSION: u8 = 1;
const MAC_LEN: usize = 32;
const MAX_TEXT_SCALARS: usize = 200;

/// A typed value used as part of a keyset cursor.
#[derive(Clone, Debug, Eq, PartialEq)]
pub enum CursorPart {
    Instant(Instant),
    Id(uuid::Uuid),
    Int(i64),
    /// Plain text is visible in a signed cursor: keep it S2 and at most 200 scalars.
    Text(String),
}

/// The ordered sort-key values encoded in a cursor.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct CursorPosition(pub Vec<CursorPart>);

/// Parses a list request and verifies its optional cursor for this endpoint and sort.
#[derive(Debug, Eq, PartialEq)]
pub struct PageRequest {
    pub limit: u16,
    pub cursor: Option<CursorPosition>,
}

impl PageRequest {
    /// Parses the API-30 limit and decodes a cursor bound to the endpoint and sort.
    pub fn parse(
        limit: Option<i64>,
        cursor: Option<&str>,
        codec: &CursorCodec,
        endpoint: &str,
        sort: &str,
    ) -> Result<Self, AppError> {
        let limit = match limit {
            None => DEFAULT_LIMIT,
            Some(value) => {
                let Ok(value) = u16::try_from(value) else {
                    return Err(invalid_limit());
                };
                if !(1..=MAX_LIMIT).contains(&value) {
                    return Err(invalid_limit());
                }
                value
            }
        };
        let cursor = match cursor {
            Some(value) => Some(codec.decode(endpoint, sort, value)?),
            None => None,
        };
        Ok(Self { limit, cursor })
    }
}

/// HMAC-SHA256 cursor encoder and verifier.
pub struct CursorCodec {
    key: Secret<[u8; 32]>,
}

impl CursorCodec {
    /// Creates a codec from the derived `kinevo/cursor/v1` subkey.
    #[must_use]
    pub fn new(key: Secret<[u8; 32]>) -> Self {
        Self { key }
    }

    /// Encodes a position as version, canonical payload, and endpoint-bound MAC.
    /// An invalid position returns an empty cursor, which `decode` rejects.
    #[must_use]
    pub fn encode(&self, endpoint: &str, sort: &str, position: &CursorPosition) -> String {
        let Some(payload) = encode_position(position) else {
            return String::new();
        };
        let Some(mac) = self.sign(endpoint, sort, &payload) else {
            return String::new();
        };
        let mut bytes = Vec::with_capacity(payload.len().saturating_add(1 + MAC_LEN));
        bytes.push(CURSOR_VERSION);
        bytes.extend_from_slice(&payload);
        bytes.extend_from_slice(&mac.finalize().into_bytes());
        URL_SAFE_NO_PAD.encode(bytes)
    }

    /// Decodes a cursor; every malformed, tampered, or misbound value has one error.
    pub fn decode(
        &self,
        endpoint: &str,
        sort: &str,
        cursor: &str,
    ) -> Result<CursorPosition, AppError> {
        let bytes = URL_SAFE_NO_PAD
            .decode(cursor)
            .map_err(|_| cursor_invalid())?;
        if bytes.len() < 1 + MAC_LEN {
            return Err(cursor_invalid());
        }
        let (version_and_payload, signature) = bytes.split_at(bytes.len() - MAC_LEN);
        let Some(version) = version_and_payload.first() else {
            return Err(cursor_invalid());
        };
        let Some(payload) = version_and_payload.get(1..) else {
            return Err(cursor_invalid());
        };
        let Some(mac) = self.sign(endpoint, sort, payload) else {
            return Err(cursor_invalid());
        };
        if mac.verify_slice(signature).is_err() || *version != CURSOR_VERSION {
            return Err(cursor_invalid());
        }
        decode_position(payload).ok_or_else(cursor_invalid)
    }

    fn sign(&self, endpoint: &str, sort: &str, payload: &[u8]) -> Option<Hmac<Sha256>> {
        let mut mac = Hmac::<Sha256>::new_from_slice(self.key.expose()).ok()?;
        mac.update(endpoint.as_bytes());
        mac.update(b"\0");
        mac.update(sort.as_bytes());
        mac.update(b"\0");
        mac.update(payload);
        Some(mac)
    }
}

fn encode_position(position: &CursorPosition) -> Option<Vec<u8>> {
    let mut payload = Vec::new();
    for part in &position.0 {
        let (tag, value): (u8, Vec<u8>) = match part {
            CursorPart::Instant(instant) => (1, instant.to_rfc3339().into_bytes()),
            CursorPart::Id(id) => (2, id.as_bytes().to_vec()),
            CursorPart::Int(value) => (3, value.to_be_bytes().to_vec()),
            CursorPart::Text(value) if value.chars().count() <= MAX_TEXT_SCALARS => {
                (4, value.as_bytes().to_vec())
            }
            CursorPart::Text(_) => return None,
        };
        let length = u16::try_from(value.len()).ok()?;
        payload.push(tag);
        payload.extend_from_slice(&length.to_be_bytes());
        payload.extend_from_slice(&value);
    }
    Some(payload)
}

fn decode_position(payload: &[u8]) -> Option<CursorPosition> {
    let mut offset = 0_usize;
    let mut parts = Vec::new();
    while offset < payload.len() {
        let tag = *payload.get(offset)?;
        offset = offset.checked_add(1)?;
        let length_end = offset.checked_add(2)?;
        let length_bytes: [u8; 2] = payload.get(offset..length_end)?.try_into().ok()?;
        let length = usize::from(u16::from_be_bytes(length_bytes));
        offset = length_end;
        let value_end = offset.checked_add(length)?;
        let value = payload.get(offset..value_end)?;
        let part = match tag {
            1 => {
                CursorPart::Instant(Instant::parse_rfc3339(std::str::from_utf8(value).ok()?).ok()?)
            }
            2 => CursorPart::Id(uuid::Uuid::from_slice(value).ok()?),
            3 => CursorPart::Int(i64::from_be_bytes(value.try_into().ok()?)),
            4 => {
                let text = std::str::from_utf8(value).ok()?;
                if text.chars().count() > MAX_TEXT_SCALARS {
                    return None;
                }
                CursorPart::Text(text.to_owned())
            }
            _ => return None,
        };
        parts.push(part);
        offset = value_end;
    }
    Some(CursorPosition(parts))
}

fn cursor_invalid() -> AppError {
    AppError::new(ErrorCode::CursorInvalid)
}

fn invalid_limit() -> AppError {
    AppError::validation(vec![FieldError {
        path: String::from("limit"),
        code: FieldCode::OutOfRange,
        details: Details::new()
            .with_detail("min", DetailValue::Int(1))
            .with_detail("max", DetailValue::Int(i64::from(MAX_LIMIT))),
    }])
}

/// A paginated response matching the API-30 page contract.
#[derive(Serialize)]
pub struct Page<T> {
    pub items: Vec<T>,
    pub next_cursor: Option<String>,
}

impl<T> Page<T> {
    /// Trims the extra fetched row and builds a cursor from the last returned row.
    #[must_use]
    pub fn from_rows(mut rows: Vec<T>, limit: u16, make_cursor: impl FnOnce(&T) -> String) -> Self {
        let has_more = rows.len() > usize::from(limit);
        rows.truncate(usize::from(limit));
        let next_cursor = if has_more {
            rows.last()
                .map(make_cursor)
                .filter(|cursor| !cursor.is_empty())
        } else {
            None
        };
        Self {
            items: rows,
            next_cursor,
        }
    }
}

#[cfg(test)]
mod tests {
    use super::{CursorCodec, CursorPart, CursorPosition, Page, PageRequest};
    use crate::{
        errors::{ErrorCode, FieldCode},
        secret::Secret,
        time::Instant,
    };
    use base64::{Engine as _, engine::general_purpose::URL_SAFE_NO_PAD};
    use uuid::Uuid;

    fn codec() -> CursorCodec {
        CursorCodec::new(Secret::new([7_u8; 32]))
    }

    fn position() -> CursorPosition {
        CursorPosition(vec![
            CursorPart::Instant(Instant::parse_rfc3339("2026-10-26T00:00:00Z").unwrap()),
            CursorPart::Id(Uuid::from_bytes([3; 16])),
            CursorPart::Int(42),
            CursorPart::Text(String::from("synthetic title")),
        ])
    }

    #[test]
    fn api_30_cursor_round_trip() {
        let codec = codec();
        let expected = position();
        let encoded = codec.encode("/api/v1/tasks", "-created_at", &expected);

        assert_eq!(
            codec
                .decode("/api/v1/tasks", "-created_at", &encoded)
                .unwrap(),
            expected
        );
    }

    #[test]
    fn api_30_cursor_bound_to_endpoint_and_sort() {
        let codec = codec();
        let encoded = codec.encode("/api/v1/tasks", "-created_at", &position());

        assert_eq!(
            codec
                .decode("/api/v1/goals", "-created_at", &encoded)
                .unwrap_err()
                .code,
            ErrorCode::CursorInvalid
        );
        assert_eq!(
            codec
                .decode("/api/v1/tasks", "title", &encoded)
                .unwrap_err()
                .code,
            ErrorCode::CursorInvalid
        );
    }

    #[test]
    fn api_30_tampered_cursor_rejected() {
        let codec = codec();
        let encoded = codec.encode("/api/v1/tasks", "id", &position());
        let mut raw = URL_SAFE_NO_PAD.decode(&encoded).unwrap();
        *raw.last_mut().unwrap() ^= 1;
        let tampered = URL_SAFE_NO_PAD.encode(raw);

        assert_eq!(
            codec
                .decode("/api/v1/tasks", "id", &tampered)
                .unwrap_err()
                .code,
            ErrorCode::CursorInvalid
        );
        assert_eq!(
            codec
                .decode("/api/v1/tasks", "id", &encoded[..encoded.len() - 1])
                .unwrap_err()
                .code,
            ErrorCode::CursorInvalid
        );
    }

    #[test]
    fn cursor_text_is_limited_to_200_scalars() {
        let codec = codec();
        let boundary = CursorPosition(vec![CursorPart::Text("😀".repeat(200))]);
        let encoded = codec.encode("/api/v1/tasks", "title", &boundary);
        assert_eq!(
            codec.decode("/api/v1/tasks", "title", &encoded).unwrap(),
            boundary
        );

        let too_long = CursorPosition(vec![CursorPart::Text("😀".repeat(201))]);
        assert_eq!(codec.encode("/api/v1/tasks", "title", &too_long), "");
    }

    #[test]
    fn api_30_limit_bounds() {
        let codec = codec();
        for limit in [Some(0), Some(201)] {
            let error = PageRequest::parse(limit, None, &codec, "/api/v1/tasks", "id").unwrap_err();
            assert_eq!(error.code, ErrorCode::ValidationFailed);
            assert_eq!(error.errors[0].path, "limit");
            assert_eq!(error.errors[0].code, FieldCode::OutOfRange);
        }
        assert_eq!(
            PageRequest::parse(Some(200), None, &codec, "/api/v1/tasks", "id")
                .unwrap()
                .limit,
            200
        );
        assert_eq!(
            PageRequest::parse(None, None, &codec, "/api/v1/tasks", "id")
                .unwrap()
                .limit,
            50
        );
    }

    #[test]
    fn next_cursor_only_when_more_rows() {
        let more = Page::from_rows((1..=51).collect(), 50, |row| format!("cursor-{row}"));
        assert_eq!(more.items.len(), 50);
        assert_eq!(more.next_cursor.as_deref(), Some("cursor-50"));

        let exact = Page::from_rows((1..=50).collect(), 50, |row| format!("cursor-{row}"));
        assert_eq!(exact.items.len(), 50);
        assert_eq!(exact.next_cursor, None);
    }
}
