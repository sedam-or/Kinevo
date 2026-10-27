//! Ports shared across Nivaras modules.

#![forbid(unsafe_code)]

pub mod events;
pub mod module;
pub mod platform;

#[cfg(feature = "testing")]
pub mod fakes;
