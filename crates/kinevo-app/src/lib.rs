//! Composition root for the Kinevo application.

#![forbid(unsafe_code)]

pub mod cli;
pub mod config;
pub mod health;
pub mod openapi;
pub mod runtime;
pub mod serve;
pub mod web_assets;
pub mod worker;
