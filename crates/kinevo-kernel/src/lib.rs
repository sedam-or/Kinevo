//! Shared domain primitives for Kinevo.

#![forbid(unsafe_code)]
#![cfg_attr(
    test,
    allow(
        clippy::unwrap_used,
        clippy::expect_used,
        clippy::indexing_slicing,
        clippy::panic,
        reason = "unit tests exercise the kernel contract"
    )
)]

pub mod errors;
pub mod id;
pub mod time;
