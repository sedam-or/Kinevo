use std::{fmt, sync::Arc};

use argon2::{
    Algorithm, Argon2, Params, Version,
    password_hash::{PasswordHasher as _, PasswordVerifier as _, phc::PasswordHash as PhcHash},
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::TokenGen,
    secret::Secret,
    time::Clock,
};
use tokio::sync::{OnceCell, Semaphore};
use unicode_normalization::UnicodeNormalization;
use zeroize::Zeroizing;

#[cfg(test)]
use std::sync::atomic::AtomicUsize;

const ARGON2_M_KIB: u32 = 65_536;
const ARGON2_T: u32 = 3;
const ARGON2_P: u32 = 1;
const OUTPUT_LEN: usize = 32;
const MIN_M_KIB: u32 = 19 * 1024;
const MIN_T: u32 = 2;
const DUMMY_PASSWORD: &str = "nivaras-startup-dummy-password";

/// Runtime concurrency input for password hashing.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct HasherConfig {
    /// Maximum hashing operations requested by the caller.
    pub concurrency: usize,
}

impl HasherConfig {
    /// Creates a hashing configuration; `Hasher` applies the host safety cap.
    #[must_use]
    pub const fn new(concurrency: usize) -> Self {
        Self { concurrency }
    }
}

/// A PHC-encoded Argon2id password hash.
pub struct PasswordHash(String);

impl PasswordHash {
    /// Returns the encoded PHC string for persistence.
    #[must_use]
    pub fn as_str(&self) -> &str {
        &self.0
    }

    #[cfg(test)]
    fn from_phc_for_test(phc: &str) -> Self {
        Self(phc.to_owned())
    }
}

impl fmt::Debug for PasswordHash {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("PasswordHash(<redacted>)")
    }
}

/// Result of verifying a password against a stored hash.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum VerifyOutcome {
    /// The password matched and the stored parameters are current.
    Ok,
    /// The password matched and the stored hash should be upgraded.
    OkNeedsRehash,
    /// The password did not match or the stored hash was invalid.
    Wrong,
}

/// Startup checks for configured Argon2 parameters and their self-benchmark.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct StartupCheck {
    /// Whether memory and iteration settings meet the documented minimums.
    pub parameters_meet_minimum: bool,
    /// Hash duration in integer milliseconds, if the benchmark completed.
    pub elapsed_ms: Option<u64>,
}

#[derive(Clone, Copy)]
struct HashFailure;

trait HashBackend: Send + Sync {
    fn hash(&self, password: &str, salt: &[u8], params: &Params) -> Result<String, HashFailure>;
    fn verify(&self, password: &str, phc: &str) -> Result<bool, HashFailure>;
}

struct Argon2Backend;

impl HashBackend for Argon2Backend {
    fn hash(&self, password: &str, salt: &[u8], params: &Params) -> Result<String, HashFailure> {
        let argon2 = Argon2::new(Algorithm::Argon2id, Version::V0x13, params.clone());
        argon2
            .hash_password_with_salt(password.as_bytes(), salt)
            .map(|hash| hash.to_string())
            .map_err(|_| HashFailure)
    }

    fn verify(&self, password: &str, phc: &str) -> Result<bool, HashFailure> {
        let parsed = PhcHash::new(phc).map_err(|_| HashFailure)?;
        Ok(Argon2::default()
            .verify_password(password.as_bytes(), &parsed)
            .is_ok())
    }
}

/// Argon2id implementation with a blocking-pool concurrency limit.
pub struct Hasher {
    tokens: Arc<dyn TokenGen>,
    clock: Arc<dyn Clock>,
    backend: Arc<dyn HashBackend>,
    permits: Arc<Semaphore>,
    params: Params,
    dummy_hash: OnceCell<PasswordHash>,
    #[cfg(test)]
    test_max_active: Option<Arc<AtomicUsize>>,
}

impl Hasher {
    /// Creates a hasher with the required Argon2id profile.
    pub fn new(
        tokens: Arc<dyn TokenGen>,
        clock: Arc<dyn Clock>,
        config: HasherConfig,
    ) -> Result<Self, AppError> {
        let params = Params::new(ARGON2_M_KIB, ARGON2_T, ARGON2_P, Some(OUTPUT_LEN))
            .map_err(|_| internal_error())?;
        Ok(Self::with_backend(
            tokens,
            clock,
            config,
            params,
            Arc::new(Argon2Backend),
            #[cfg(test)]
            None,
        ))
    }

    fn with_backend(
        tokens: Arc<dyn TokenGen>,
        clock: Arc<dyn Clock>,
        config: HasherConfig,
        params: Params,
        backend: Arc<dyn HashBackend>,
        #[cfg(test)] test_max_active: Option<Arc<AtomicUsize>>,
    ) -> Self {
        let cores = std::thread::available_parallelism().map_or(1, std::num::NonZeroUsize::get);
        let concurrency = config.concurrency.clamp(1, 4).min(cores);
        Self {
            tokens,
            clock,
            backend,
            permits: Arc::new(Semaphore::new(concurrency)),
            params,
            dummy_hash: OnceCell::new(),
            #[cfg(test)]
            test_max_active,
        }
    }

    /// Hashes a validated password as an Argon2id PHC string.
    pub async fn hash(
        &self,
        password: &crate::domain::password::PlainPassword,
    ) -> Result<PasswordHash, AppError> {
        let candidate = Zeroizing::new(password.expose().to_owned());
        let salt = Zeroizing::new(self.tokens.token_128());
        let params = self.params.clone();
        let phc = self
            .run_blocking(move |backend| backend.hash(&candidate, &salt[..], &params))
            .await
            .map_err(|_| internal_error())?;
        Ok(PasswordHash(phc))
    }

    /// Verifies a password and reports whether its stored parameters need rehashing.
    pub async fn verify(&self, candidate: &Secret<String>, hash: &PasswordHash) -> VerifyOutcome {
        let normalized = Zeroizing::new(candidate.expose().nfkc().collect::<String>());
        let needs_rehash = self.needs_rehash(hash);
        let phc = hash.0.clone();
        match self
            .run_blocking(move |backend| backend.verify(&normalized, &phc))
            .await
        {
            Ok(true) if needs_rehash => VerifyOutcome::OkNeedsRehash,
            Ok(true) => VerifyOutcome::Ok,
            Ok(false) | Err(_) => VerifyOutcome::Wrong,
        }
    }

    /// Verifies a stored PHC string without exposing it outside the hasher adapter.
    pub async fn verify_encoded(&self, candidate: &Secret<String>, encoded: &str) -> VerifyOutcome {
        self.verify(candidate, &PasswordHash(encoded.to_owned()))
            .await
    }

    /// Rehashes an already accepted login candidate using the current Argon2id profile.
    pub async fn rehash_candidate(
        &self,
        candidate: &Secret<String>,
    ) -> Result<PasswordHash, AppError> {
        let normalized = Zeroizing::new(candidate.expose().nfkc().collect::<String>());
        let salt = Zeroizing::new(self.tokens.token_128());
        let params = self.params.clone();
        let phc = self
            .run_blocking(move |backend| backend.hash(&normalized, &salt[..], &params))
            .await
            .map_err(|_| internal_error())?;
        Ok(PasswordHash(phc))
    }

    /// Performs a current-profile verification for an account that was not found.
    pub async fn verify_dummy(&self, candidate: &Secret<String>) {
        let dummy = self
            .dummy_hash
            .get_or_try_init(|| async {
                let candidate = Zeroizing::new(DUMMY_PASSWORD.to_owned());
                let salt = Zeroizing::new(self.tokens.token_128());
                let params = self.params.clone();
                self.run_blocking(move |backend| backend.hash(&candidate, &salt[..], &params))
                    .await
                    .map(PasswordHash)
            })
            .await;
        let Ok(dummy) = dummy else { return };
        let candidate = Zeroizing::new(candidate.expose().nfkc().collect::<String>());
        let phc = dummy.0.clone();
        let _ = self
            .run_blocking(move |backend| backend.verify(&candidate, &phc))
            .await;
    }

    /// Returns whether a PHC string differs from this hasher's configured parameters.
    #[must_use]
    pub fn needs_rehash(&self, hash: &PasswordHash) -> bool {
        let Ok(parsed) = PhcHash::new(hash.as_str()) else {
            return true;
        };
        let current_version = parsed
            .version
            .as_ref()
            .is_some_and(|version| version.to_string() == "19");
        parsed.algorithm.as_str() != "argon2id"
            || !current_version
            || parsed.params.get_decimal("m") != Some(self.params.m_cost())
            || parsed.params.get_decimal("t") != Some(self.params.t_cost())
            || parsed.params.get_decimal("p") != Some(self.params.p_cost())
    }

    /// Validates minimum Argon2 settings and times one hash without logging.
    pub async fn self_check(&self) -> StartupCheck {
        let parameters_meet_minimum =
            self.params.m_cost() >= MIN_M_KIB && self.params.t_cost() >= MIN_T;
        let start_ms = self.clock.now().unix_millis();
        let password = Zeroizing::new(DUMMY_PASSWORD.to_owned());
        let salt = Zeroizing::new(self.tokens.token_128());
        let params = self.params.clone();
        let elapsed_ms = self
            .run_blocking(move |backend| backend.hash(&password, &salt[..], &params))
            .await
            .ok()
            .map(|_| {
                let elapsed = self
                    .clock
                    .now()
                    .unix_millis()
                    .saturating_sub(start_ms)
                    .max(0);
                u64::try_from(elapsed).unwrap_or(u64::MAX)
            });
        StartupCheck {
            parameters_meet_minimum,
            elapsed_ms,
        }
    }

    async fn run_blocking<T, F>(&self, operation: F) -> Result<T, HashFailure>
    where
        T: Send + 'static,
        F: FnOnce(&dyn HashBackend) -> Result<T, HashFailure> + Send + 'static,
    {
        let permit = self
            .permits
            .clone()
            .acquire_owned()
            .await
            .map_err(|_| HashFailure)?;
        let backend = Arc::clone(&self.backend);
        tokio::task::spawn_blocking(move || {
            let _permit = permit;
            operation(backend.as_ref())
        })
        .await
        .map_err(|_| HashFailure)?
    }

    #[cfg(test)]
    fn with_counting_backend_for_test(
        tokens: Arc<dyn TokenGen>,
        clock: Arc<dyn Clock>,
        config: HasherConfig,
        verification_count: Arc<AtomicUsize>,
    ) -> Self {
        let params = Params::new(ARGON2_M_KIB, ARGON2_T, ARGON2_P, Some(OUTPUT_LEN))
            .unwrap_or(Params::DEFAULT);
        Self::with_backend(
            tokens,
            clock,
            config,
            params,
            Arc::new(CountingBackend { verification_count }),
            None,
        )
    }

    #[cfg(test)]
    fn with_concurrency_backend_for_test(
        tokens: Arc<dyn TokenGen>,
        clock: Arc<dyn Clock>,
        config: HasherConfig,
    ) -> Self {
        let active = Arc::new(AtomicUsize::new(0));
        let maximum = Arc::new(AtomicUsize::new(0));
        let params = Params::new(ARGON2_M_KIB, ARGON2_T, ARGON2_P, Some(OUTPUT_LEN))
            .unwrap_or(Params::DEFAULT);
        Self::with_backend(
            tokens,
            clock,
            config,
            params,
            Arc::new(ConcurrencyBackend {
                active,
                maximum: Arc::clone(&maximum),
            }),
            Some(maximum),
        )
    }

    #[cfg(test)]
    fn with_memory_for_test(
        tokens: Arc<dyn TokenGen>,
        clock: Arc<dyn Clock>,
        config: HasherConfig,
        memory_kib: u32,
    ) -> Self {
        let params = Params::new(memory_kib, ARGON2_T, ARGON2_P, Some(OUTPUT_LEN))
            .unwrap_or(Params::DEFAULT);
        Self::with_backend(tokens, clock, config, params, Arc::new(Argon2Backend), None)
    }

    #[cfg(test)]
    fn max_active_for_test(&self) -> usize {
        self.test_max_active.as_ref().map_or(0, |maximum| {
            maximum.load(std::sync::atomic::Ordering::SeqCst)
        })
    }
}

impl fmt::Debug for Hasher {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("Hasher(<redacted>)")
    }
}

fn internal_error() -> AppError {
    AppError::new(ErrorCode::InternalError)
}

#[cfg(test)]
struct CountingBackend {
    verification_count: Arc<AtomicUsize>,
}

#[cfg(test)]
impl HashBackend for CountingBackend {
    fn hash(&self, _: &str, _: &[u8], _: &Params) -> Result<String, HashFailure> {
        Ok("test-dummy-phc".to_owned())
    }

    fn verify(&self, _: &str, _: &str) -> Result<bool, HashFailure> {
        self.verification_count
            .fetch_add(1, std::sync::atomic::Ordering::SeqCst);
        Ok(true)
    }
}

#[cfg(test)]
struct ConcurrencyBackend {
    active: Arc<AtomicUsize>,
    maximum: Arc<AtomicUsize>,
}

#[cfg(test)]
impl HashBackend for ConcurrencyBackend {
    fn hash(&self, _: &str, _: &[u8], _: &Params) -> Result<String, HashFailure> {
        let active = self
            .active
            .fetch_add(1, std::sync::atomic::Ordering::SeqCst)
            + 1;
        self.maximum
            .fetch_max(active, std::sync::atomic::Ordering::SeqCst);
        std::thread::sleep(std::time::Duration::from_millis(20));
        self.active
            .fetch_sub(1, std::sync::atomic::Ordering::SeqCst);
        Ok("test-concurrency-phc".to_owned())
    }

    fn verify(&self, _: &str, _: &str) -> Result<bool, HashFailure> {
        Ok(true)
    }
}

#[cfg(test)]
mod tests {
    #![allow(
        clippy::expect_used,
        clippy::unwrap_used,
        clippy::indexing_slicing,
        clippy::panic,
        reason = "password hasher acceptance tests use deterministic fakes"
    )]

    use super::{Hasher, HasherConfig, PasswordHash, VerifyOutcome};
    use crate::domain::password::{CommonPasswords, PasswordPolicy, PlainPassword};
    use argon2::{Algorithm, Argon2, Params, Version, password_hash::PasswordHasher};
    use kinevo_kernel::{
        id::TokenGen,
        secret::Secret,
        time::{Clock, FixedClock, Instant},
    };
    use std::sync::{
        Arc,
        atomic::{AtomicUsize, Ordering},
    };
    use std::time::Instant as MonotonicInstant;

    struct FixedTokens(AtomicUsize);

    impl TokenGen for FixedTokens {
        fn fill(&self, buffer: &mut [u8]) {
            let value = u8::try_from(self.0.fetch_add(1, Ordering::SeqCst)).unwrap_or(u8::MAX);
            buffer.fill(value);
        }
    }

    fn clock() -> Arc<dyn Clock> {
        Arc::new(FixedClock::new(
            Instant::parse_rfc3339("2026-10-28T00:00:00Z").expect("valid fixed instant"),
        ))
    }

    fn password(raw: &str) -> PlainPassword {
        PlainPassword::parse(
            Secret::new(raw.to_owned()),
            &PasswordPolicy::new(12),
            &CommonPasswords::embedded(),
        )
        .expect("synthetic password parses")
    }

    #[tokio::test]
    async fn id_fr_01_phc_and_verify() {
        let hasher = Hasher::new(
            Arc::new(FixedTokens(AtomicUsize::new(1))),
            clock(),
            HasherConfig::new(1),
        )
        .expect("valid production parameters");
        let password = password("synthetic phrase 01");
        let hash = hasher.hash(&password).await.expect("hash succeeds");

        assert!(hash.as_str().starts_with("$argon2id$v=19$m=65536,t=3,p=1$"));
        assert_eq!(
            hasher
                .verify(&Secret::new("synthetic phrase 01".to_owned()), &hash)
                .await,
            VerifyOutcome::Ok
        );
        assert_eq!(
            hasher
                .verify(&Secret::new("different phrase 02".to_owned()), &hash)
                .await,
            VerifyOutcome::Wrong
        );
    }

    #[tokio::test]
    async fn rehash_on_old_parameters() {
        let hasher = Hasher::new(
            Arc::new(FixedTokens(AtomicUsize::new(1))),
            clock(),
            HasherConfig::new(1),
        )
        .expect("valid production parameters");
        let candidate = Secret::new("synthetic phrase 03".to_owned());
        let params = Params::new(19_456, 2, 1, Some(32)).expect("valid old parameters");
        let old_phc = Argon2::new(Algorithm::Argon2id, Version::V0x13, params)
            .hash_password_with_salt(b"synthetic phrase 03", b"synthetic salt 123")
            .expect("old hash succeeds")
            .to_string();
        let old_hash = PasswordHash::from_phc_for_test(&old_phc);

        assert_eq!(
            hasher.verify(&candidate, &old_hash).await,
            VerifyOutcome::OkNeedsRehash
        );
    }

    #[tokio::test]
    async fn id_fr_03_dummy_verification_does_work() {
        let count = Arc::new(AtomicUsize::new(0));
        let hasher = Hasher::with_counting_backend_for_test(
            Arc::new(FixedTokens(AtomicUsize::new(1))),
            clock(),
            HasherConfig::new(1),
            Arc::clone(&count),
        );

        hasher
            .verify_dummy(&Secret::new("synthetic unknown user".to_owned()))
            .await;
        assert_eq!(count.load(Ordering::SeqCst), 1);
    }

    #[tokio::test]
    async fn concurrency_is_capped() {
        let hasher = Arc::new(Hasher::with_concurrency_backend_for_test(
            Arc::new(FixedTokens(AtomicUsize::new(1))),
            clock(),
            HasherConfig::new(1),
        ));
        let tasks = (0..3)
            .map(|index| {
                let hasher = Arc::clone(&hasher);
                tokio::spawn(async move {
                    let password = password(&format!("synthetic concurrent phrase {index}"));
                    hasher.hash(&password).await
                })
            })
            .collect::<Vec<_>>();
        for task in tasks {
            assert!(task.await.expect("hash task joins").is_ok());
        }
        assert_eq!(hasher.max_active_for_test(), 1);
    }

    #[tokio::test]
    async fn auth_03_minimums_enforced() {
        let hasher = Hasher::with_memory_for_test(
            Arc::new(FixedTokens(AtomicUsize::new(1))),
            clock(),
            HasherConfig::new(1),
            8 * 1024,
        );
        let check = hasher.self_check().await;

        assert!(!check.parameters_meet_minimum);
        assert!(check.elapsed_ms.is_some());
    }

    #[tokio::test]
    #[ignore = "200-sample timing comparison; run with `just test-slow`"]
    #[allow(
        clippy::disallowed_methods,
        reason = "SEC-AC-03 is an ignored real-time benchmark over 200 samples"
    )]
    async fn sec_ac_03_timing_oracle() {
        let hasher = Hasher::new(
            Arc::new(FixedTokens(AtomicUsize::new(1))),
            clock(),
            HasherConfig::new(1),
        )
        .expect("valid production parameters");
        let password = password("synthetic timing phrase 01");
        let hash = hasher.hash(&password).await.expect("hash succeeds");
        let mut known: Vec<u128> = Vec::with_capacity(200);
        let mut dummy: Vec<u128> = Vec::with_capacity(200);

        for _ in 0..200 {
            let candidate = Secret::new("synthetic wrong phrase 02".to_owned());
            let start = MonotonicInstant::now();
            let _ = hasher.verify(&candidate, &hash).await;
            known.push(start.elapsed().as_nanos());

            let candidate = Secret::new("synthetic wrong phrase 02".to_owned());
            let start = MonotonicInstant::now();
            hasher.verify_dummy(&candidate).await;
            dummy.push(start.elapsed().as_nanos());
        }
        known.sort_unstable();
        dummy.sort_unstable();
        let known_median = known[known.len() / 2];
        let dummy_median = dummy[dummy.len() / 2];
        let difference = known_median.abs_diff(dummy_median);
        assert!(difference * 5 < known_median.max(dummy_median));
    }
}
