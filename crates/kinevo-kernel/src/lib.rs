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

pub mod crypto;
pub mod errors;
pub mod id;
pub mod page;
pub mod secret;
pub mod time;
pub mod validate;
