//! HS256 access-token issue and verification.

use jsonwebtoken::{
    Algorithm, DecodingKey, EncodingKey, Header, Validation, decode, decode_header, encode,
};
pub use kinevo_kernel::id::FamilyId;
use kinevo_kernel::{id::AccountId, secret::Secret, time::Instant};
use serde::{Deserialize, Serialize};
use std::{collections::HashSet, error::Error, fmt};
use uuid::Uuid;

const CLOCK_SKEW_SECONDS: u64 = 30;
const MAX_IAT_SKEW_SECONDS: i64 = 30;

/// Claims carried by a signed access token.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct Claims {
    pub sub: AccountId,
    pub sid: FamilyId,
    pub jti: Uuid,
    pub iat: Instant,
    pub exp: Instant,
    pub mfa: bool,
}

#[derive(Serialize, Deserialize)]
struct NumericClaims {
    sub: AccountId,
    sid: FamilyId,
    jti: Uuid,
    iat: i64,
    exp: i64,
    mfa: bool,
}

impl NumericClaims {
    fn from_claims(claims: &Claims) -> Self {
        Self {
            sub: claims.sub,
            sid: claims.sid,
            jti: claims.jti,
            iat: claims.iat.unix_millis().div_euclid(1_000),
            exp: claims.exp.unix_millis().div_euclid(1_000),
            mfa: claims.mfa,
        }
    }

    fn into_claims(self) -> Result<Claims, TokenError> {
        let iat = self
            .iat
            .checked_mul(1_000)
            .ok_or(TokenError::InvalidClaims)?;
        let exp = self
            .exp
            .checked_mul(1_000)
            .ok_or(TokenError::InvalidClaims)?;
        Ok(Claims {
            sub: self.sub,
            sid: self.sid,
            jti: self.jti,
            iat: Instant::from_unix_millis(iat).map_err(|_| TokenError::InvalidClaims)?,
            exp: Instant::from_unix_millis(exp).map_err(|_| TokenError::InvalidClaims)?,
            mfa: self.mfa,
        })
    }
}

/// Versioned signing and verification keys. Secrets never appear in Debug output.
pub struct KeyRing {
    current_kid: String,
    keys: Vec<(String, Secret<[u8; 32]>)>,
}

impl KeyRing {
    /// Creates a ring whose current key is used to issue tokens and all keys verify tokens.
    pub fn new(
        current_kid: impl Into<String>,
        keys: Vec<(String, Secret<[u8; 32]>)>,
    ) -> Result<Self, TokenError> {
        let current_kid = current_kid.into();
        let mut seen = HashSet::new();
        let unique = keys
            .iter()
            .all(|(kid, _)| !kid.is_empty() && seen.insert(kid.as_str()));
        if current_kid.is_empty() || !unique || !keys.iter().any(|(kid, _)| kid == &current_kid) {
            return Err(TokenError::InvalidKeyRing);
        }
        Ok(Self { current_kid, keys })
    }
}

/// Generic, non-sensitive token verification failure.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum TokenError {
    InvalidKeyRing,
    InvalidToken,
    InvalidClaims,
}

impl fmt::Display for TokenError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str(match self {
            Self::InvalidKeyRing => "access-token key ring is invalid",
            Self::InvalidToken | Self::InvalidClaims => "access token is invalid",
        })
    }
}

impl Error for TokenError {}

/// Issues access tokens with the active key and verifies tokens against the ring.
pub struct TokenCodec {
    key_ring: KeyRing,
}

impl TokenCodec {
    /// Creates a codec for the supplied versioned key ring.
    #[must_use]
    pub fn new(key_ring: KeyRing) -> Self {
        Self { key_ring }
    }

    /// Issues an HS256 compact JWS. A serialization failure produces no usable token.
    #[must_use]
    pub fn issue(&self, claims: &Claims) -> String {
        let Some((kid, key)) = self
            .key_ring
            .keys
            .iter()
            .find(|(kid, _)| kid == &self.key_ring.current_kid)
        else {
            return String::new();
        };
        let mut header = Header::new(Algorithm::HS256);
        header.kid = Some(kid.clone());
        encode(
            &header,
            &NumericClaims::from_claims(claims),
            &EncodingKey::from_secret(key.expose()),
        )
        .unwrap_or_default()
    }

    /// Verifies signature, algorithm, key id, required claims, expiry and future-issued skew.
    pub fn verify(&self, token: &str, now: Instant) -> Result<Claims, TokenError> {
        let header = decode_header(token).map_err(|_| TokenError::InvalidToken)?;
        if header.alg != Algorithm::HS256 {
            return Err(TokenError::InvalidToken);
        }
        let kid = header.kid.as_deref().ok_or(TokenError::InvalidToken)?;
        let (_, key) = self
            .key_ring
            .keys
            .iter()
            .find(|(candidate, _)| candidate == kid)
            .ok_or(TokenError::InvalidToken)?;

        let mut validation = Validation::new(Algorithm::HS256);
        validation.algorithms = vec![Algorithm::HS256];
        // Expiry is checked against the injected clock below, never the process clock.
        validation.validate_exp = false;
        validation.validate_nbf = false;
        validation.leeway = CLOCK_SKEW_SECONDS;
        validation.required_spec_claims = ["sub", "sid", "jti", "iat", "exp", "mfa"]
            .into_iter()
            .map(str::to_owned)
            .collect();

        let data =
            decode::<NumericClaims>(token, &DecodingKey::from_secret(key.expose()), &validation)
                .map_err(|_| TokenError::InvalidToken)?;
        let now_seconds = now.unix_millis().div_euclid(1_000);
        let latest_valid_exp = data
            .claims
            .exp
            .checked_add(i64::try_from(CLOCK_SKEW_SECONDS).map_err(|_| TokenError::InvalidClaims)?)
            .ok_or(TokenError::InvalidClaims)?;
        if latest_valid_exp < now_seconds {
            return Err(TokenError::InvalidToken);
        }
        let latest_iat = now_seconds
            .checked_add(MAX_IAT_SKEW_SECONDS)
            .ok_or(TokenError::InvalidClaims)?;
        if data.claims.iat > latest_iat {
            return Err(TokenError::InvalidToken);
        }
        data.claims.into_claims()
    }
}
