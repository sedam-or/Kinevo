//! Cryptographic helpers for secrets and opaque tokens.

use base64::{
    Engine as _,
    engine::general_purpose::{STANDARD, URL_SAFE_NO_PAD},
};
use chacha20poly1305::{
    XChaCha20Poly1305, XNonce,
    aead::{Aead, KeyInit, Payload},
};
use hkdf::Hkdf;
use sha2::{Digest, Sha256};
use std::{error::Error, fmt};
use subtle::ConstantTimeEq;
use zeroize::Zeroizing;

use crate::{id::TokenGen, secret::Secret};

/// A master key parsed from the padded standard-base64 environment value.
pub struct MasterKey(Secret<[u8; 32]>);

impl MasterKey {
    /// Parses a 32-byte master key without including its value in errors.
    pub fn from_base64(encoded: &Secret<String>) -> Result<Self, CryptoError> {
        let decoded = STANDARD
            .decode(encoded.expose())
            .map_err(|_| CryptoError::InvalidMasterKeyEncoding)?;
        let decoded = Zeroizing::new(decoded);
        if decoded.len() != 32 {
            return Err(CryptoError::InvalidMasterKeyLength {
                actual: decoded.len(),
            });
        }
        let key = Zeroizing::new(<[u8; 32]>::try_from(decoded.as_slice()).map_err(|_| {
            CryptoError::InvalidMasterKeyLength {
                actual: decoded.len(),
            }
        })?);
        Ok(Self(Secret::new(*key)))
    }
}

/// Selects one of the stable, domain-separated key derivation labels.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum KeyLabel {
    Jwt,
    Cursor,
    Secrets,
    Csrf,
}

impl KeyLabel {
    /// Returns the versioned HKDF info label for this key domain.
    #[must_use]
    pub fn info(self, version: u8) -> String {
        let domain = match self {
            Self::Jwt => "jwt",
            Self::Cursor => "cursor",
            Self::Secrets => "secrets",
            Self::Csrf => "csrf",
        };
        format!("kinevo/{domain}/v{version}")
    }
}

/// Ciphertext with its XChaCha20-Poly1305 nonce.
pub struct Sealed {
    pub nonce: [u8; 24],
    pub ciphertext: Vec<u8>,
}

/// Safe-to-display errors from key and token operations.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum CryptoError {
    InvalidMasterKeyEncoding,
    InvalidMasterKeyLength { actual: usize },
    InvalidTokenEncoding,
    InvalidTokenLength { actual: usize },
    AuthenticationFailed,
}

impl fmt::Display for CryptoError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Self::InvalidMasterKeyEncoding => {
                formatter.write_str("master key must be padded standard base64")
            }
            Self::InvalidMasterKeyLength { actual } => {
                write!(
                    formatter,
                    "master key must decode to exactly 32 bytes (got {actual})"
                )
            }
            Self::InvalidTokenEncoding => formatter.write_str("token encoding is invalid"),
            Self::InvalidTokenLength { actual } => {
                write!(
                    formatter,
                    "token must decode to exactly 32 bytes (got {actual})"
                )
            }
            Self::AuthenticationFailed => formatter.write_str("ciphertext authentication failed"),
        }
    }
}

impl Error for CryptoError {}

/// Derives a 256-bit subkey using HKDF-SHA256 and the selected domain label.
#[must_use]
pub fn derive_subkey(master: &MasterKey, label: KeyLabel, version: u8) -> Secret<[u8; 32]> {
    let hkdf = Hkdf::<Sha256>::new(None, master.0.expose());
    let mut key = Zeroizing::new([0_u8; 32]);
    if hkdf
        .expand(label.info(version).as_bytes(), key.as_mut())
        .is_err()
    {
        // The fixed 32-byte output is within HKDF-SHA256's guaranteed limit.
        std::process::abort();
    }
    Secret::new(*key)
}

/// Encrypts plaintext using a fresh nonce from the injected token generator.
/// Callers bind ciphertext to its storage context with the AAD they provide.
#[must_use]
pub fn seal(key: &Secret<[u8; 32]>, plaintext: &[u8], aad: &[u8], rng: &dyn TokenGen) -> Sealed {
    let cipher = cipher_for(key);
    let mut nonce = [0_u8; 24];
    rng.fill(&mut nonce);
    let payload = Payload {
        msg: plaintext,
        aad,
    };
    let Ok(ciphertext) = cipher.encrypt(&nonce_for(&nonce), payload) else {
        // This infallible interface has no error channel; fail closed on AEAD failure.
        std::process::abort();
    };
    Sealed { nonce, ciphertext }
}

/// Opens ciphertext only when both the key and authenticated data match.
pub fn open(
    key: &Secret<[u8; 32]>,
    sealed: &Sealed,
    aad: &[u8],
) -> Result<Secret<Vec<u8>>, CryptoError> {
    let cipher = cipher_for(key);
    let payload = Payload {
        msg: &sealed.ciphertext,
        aad,
    };
    cipher
        .decrypt(&nonce_for(&sealed.nonce), payload)
        .map(Secret::new)
        .map_err(|_| CryptoError::AuthenticationFailed)
}

fn cipher_for(key: &Secret<[u8; 32]>) -> XChaCha20Poly1305 {
    let Ok(aead_key) = chacha20poly1305::Key::try_from(key.expose().as_slice()) else {
        std::process::abort();
    };
    XChaCha20Poly1305::new(&aead_key)
}

fn nonce_for(nonce: &[u8; 24]) -> XNonce {
    let Ok(nonce) = XNonce::try_from(nonce.as_slice()) else {
        std::process::abort();
    };
    nonce
}

/// Hashes an opaque raw token with SHA-256 before storage or comparison.
#[must_use]
pub fn hash_token(raw: &[u8]) -> [u8; 32] {
    Sha256::digest(raw).into()
}

/// Encodes a 32-byte token as 43 unpadded base64url characters.
#[must_use]
pub fn encode_token(raw: &[u8; 32]) -> String {
    URL_SAFE_NO_PAD.encode(raw)
}

/// Decodes a 32-byte token from unpadded base64url without exposing it in errors.
pub fn decode_token(encoded: &str) -> Result<[u8; 32], CryptoError> {
    if encoded.len() != 43 {
        return Err(CryptoError::InvalidTokenLength {
            actual: encoded.len(),
        });
    }
    let decoded = URL_SAFE_NO_PAD
        .decode(encoded)
        .map_err(|_| CryptoError::InvalidTokenEncoding)?;
    let decoded = Zeroizing::new(decoded);
    <[u8; 32]>::try_from(decoded.as_slice()).map_err(|_| CryptoError::InvalidTokenLength {
        actual: decoded.len(),
    })
}

/// Compares equal-length values in constant time.
#[must_use]
pub fn ct_eq(left: &[u8], right: &[u8]) -> bool {
    left.len() == right.len() && bool::from(left.ct_eq(right))
}

/// Masks a displayable secret, preserving no more than its last four scalars.
#[must_use]
pub fn mask_last4(value: &str) -> String {
    let mut tail = value.chars().rev().take(4).collect::<Vec<_>>();
    if tail.len() < 4 {
        return "••••".to_owned();
    }
    tail.reverse();
    format!("••••{}", tail.into_iter().collect::<String>())
}

#[cfg(test)]
mod tests {
    use super::{
        KeyLabel, MasterKey, decode_token, derive_subkey, encode_token, hash_token, mask_last4,
        open, seal,
    };
    use crate::{
        id::{SeqTokenGen, TokenGen},
        secret::Secret,
    };
    use base64::{Engine as _, engine::general_purpose::STANDARD};

    fn master_key() -> MasterKey {
        let encoded = STANDARD.encode([7_u8; 32]);
        MasterKey::from_base64(&Secret::new(encoded)).expect("32 byte test key")
    }

    #[test]
    fn adm_fr_06_random_nonce_per_seal() {
        let key = derive_subkey(&master_key(), KeyLabel::Secrets, 1);
        let rng = SeqTokenGen::new(4);
        let first = seal(&key, b"synthetic secret", b"secret:fixture:account", &rng);
        let second = seal(&key, b"synthetic secret", b"secret:fixture:account", &rng);

        assert_ne!(first.nonce, second.nonce);
        assert_ne!(first.ciphertext, second.ciphertext);
        assert_eq!(
            open(&key, &first, b"secret:fixture:account")
                .unwrap()
                .expose(),
            b"synthetic secret"
        );
        assert_eq!(
            open(&key, &second, b"secret:fixture:account")
                .unwrap()
                .expose(),
            b"synthetic secret"
        );
    }

    #[test]
    fn sec_23_seal_nonce_uses_injected_token_generator() {
        let key = derive_subkey(&master_key(), KeyLabel::Secrets, 1);
        let rng = SeqTokenGen::new(12);
        let sealed = seal(&key, b"synthetic secret", b"secret:fixture:account", &rng);
        let expected_rng = SeqTokenGen::new(12);
        let mut expected_nonce = [0_u8; 24];
        expected_rng.fill(&mut expected_nonce);

        assert_eq!(sealed.nonce, expected_nonce);
    }

    #[test]
    fn open_rejects_wrong_aad_and_tamper() {
        let key = derive_subkey(&master_key(), KeyLabel::Secrets, 1);
        let rng = SeqTokenGen::new(9);
        let sealed = seal(&key, b"synthetic secret", b"secret:fixture:account", &rng);

        assert!(open(&key, &sealed, b"secret:other:account").is_err());
        let mut tampered = seal(&key, b"synthetic secret", b"secret:fixture:account", &rng);
        if let Some(byte) = tampered.ciphertext.first_mut() {
            *byte ^= 1;
        }
        assert!(open(&key, &tampered, b"secret:fixture:account").is_err());
    }

    #[test]
    fn hkdf_labels_separate_domains() {
        let master = master_key();
        let jwt = derive_subkey(&master, KeyLabel::Jwt, 1);
        let jwt_again = derive_subkey(&master, KeyLabel::Jwt, 1);
        let secrets = derive_subkey(&master, KeyLabel::Secrets, 1);

        assert_eq!(jwt.expose(), jwt_again.expose());
        assert_ne!(jwt.expose(), secrets.expose());
    }

    #[test]
    fn master_key_length_checked() {
        let encoded = STANDARD.encode([0_u8; 31]);
        let error = MasterKey::from_base64(&Secret::new(encoded.clone()))
            .err()
            .expect("31 byte test key must be rejected");
        let message = error.to_string();

        assert!(message.contains("32"));
        assert!(!message.contains(&encoded));
    }

    #[test]
    fn d_19_mask_last4() {
        assert_eq!(mask_last4("sk-abcdef1234"), "••••1234");
        assert_eq!(mask_last4("ab"), "••••");
    }

    #[test]
    fn token_encoding_round_trip() {
        let raw = hash_token(b"synthetic token fixture");
        let encoded = encode_token(&raw);

        assert_eq!(encoded.len(), 43);
        assert_eq!(decode_token(&encoded).expect("valid token encoding"), raw);
    }
}
