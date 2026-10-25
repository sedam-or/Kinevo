//! System implementations for the kernel's injected runtime ports.

use kinevo_kernel::{
    id::{IdGen, TokenGen},
    time::{Clock, Instant},
};

pub(crate) struct SystemClock;

impl Clock for SystemClock {
    #[allow(
        clippy::disallowed_methods,
        reason = "application composition root, CQ-07"
    )]
    fn now(&self) -> Instant {
        let timestamp = jiff::Timestamp::now();
        match Instant::from_unix_millis(timestamp.as_millisecond()) {
            Ok(instant) => instant,
            Err(_) => std::process::abort(),
        }
    }
}

pub(crate) struct UuidV7Gen;

impl IdGen for UuidV7Gen {
    #[allow(
        clippy::disallowed_methods,
        reason = "application composition root, CQ-07"
    )]
    fn new_uuid_v7(&self) -> uuid::Uuid {
        uuid::Uuid::now_v7()
    }
}

pub(crate) struct OsTokenGen;

impl TokenGen for OsTokenGen {
    fn fill(&self, buffer: &mut [u8]) {
        // getrandom is the stack-approved OS CSPRNG; rand_core 0.10.1 has no OsRng type.
        if getrandom::fill(buffer).is_err() {
            std::process::abort();
        }
    }
}
