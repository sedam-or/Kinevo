//! Secret-bearing values with redacted debug output and zeroization.
//!
//! ```compile_fail
//! let secret = kinevo_kernel::secret::Secret::new(String::from("synthetic"));
//! let _copy = secret.clone();
//! ```
//!
//! ```compile_fail
//! let secret = kinevo_kernel::secret::Secret::new(String::from("synthetic"));
//! let _display = format!("{secret}");
//! ```
//!
//! ```compile_fail
//! let secret = kinevo_kernel::secret::Secret::new(String::from("synthetic"));
//! let _serialized = serde_json::to_string(&secret);
//! ```

use std::fmt;
use zeroize::Zeroize;

/// Owns a value that must not be formatted, serialized, or cloned accidentally.
pub struct Secret<T: Zeroize>(T);

impl<T: Zeroize> Secret<T> {
    /// Wraps a secret value.
    #[must_use]
    pub fn new(value: T) -> Self {
        Self(value)
    }

    /// Borrows the secret for an explicit use site.
    #[must_use]
    pub fn expose(&self) -> &T {
        &self.0
    }
}

impl<T: Zeroize> Drop for Secret<T> {
    fn drop(&mut self) {
        self.0.zeroize();
    }
}

impl<T: Zeroize> fmt::Debug for Secret<T> {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("Secret(<redacted>)")
    }
}

#[cfg(test)]
mod tests {
    use super::Secret;
    use std::sync::{
        Arc,
        atomic::{AtomicBool, Ordering},
    };
    use zeroize::Zeroize;

    struct ZeroizeProbe(Arc<AtomicBool>);

    impl Zeroize for ZeroizeProbe {
        fn zeroize(&mut self) {
            self.0.store(true, Ordering::SeqCst);
        }
    }

    #[test]
    fn cq_10_debug_is_redacted() {
        let secret = Secret::new(String::from("synthetic secret"));
        let formatted = format!("{secret:?}");

        assert_eq!(formatted, "Secret(<redacted>)");
        assert!(!formatted.contains("synthetic secret"));
    }

    #[test]
    fn sec_03_secret_zeroizes_on_drop() {
        let zeroized = Arc::new(AtomicBool::new(false));
        drop(Secret::new(ZeroizeProbe(Arc::clone(&zeroized))));

        assert!(zeroized.load(Ordering::SeqCst));
    }
}
