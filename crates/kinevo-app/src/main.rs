#![forbid(unsafe_code)]
#![cfg_attr(
    test,
    allow(
        clippy::unwrap_used,
        clippy::expect_used,
        clippy::indexing_slicing,
        clippy::panic,
        clippy::todo,
        clippy::unimplemented,
        reason = "unit tests assert the CLI contract"
    )
)]

use clap::Parser;
use std::io::{self, Write};

#[allow(
    dead_code,
    reason = "T-002 wires runtime ports before later consumers are implemented"
)]
mod runtime;

#[path = "cli.rs"]
mod kinevo_app;

fn main() -> io::Result<()> {
    let cli = kinevo_app::cli::Cli::parse();
    match cli.command {
        kinevo_app::cli::Command::Version => {
            let mut output = io::stdout().lock();
            output.write_all(kinevo_app::cli::version_line().as_bytes())?;
            output.write_all(b"\n")
        }
    }
}
