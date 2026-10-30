use clap::{Parser, Subcommand};
use std::{net::SocketAddr, path::Path};

pub mod admin;
pub mod migrate;

#[derive(Parser)]
#[command(name = "kinevo", disable_help_subcommand = true)]
pub struct Cli {
    #[command(subcommand)]
    pub command: Command,
}

#[derive(Subcommand)]
pub enum Command {
    /// Start the HTTP API and background worker.
    Serve,
    /// Probe the local readiness endpoint.
    Health,
    /// Print the package version.
    Version,
    /// Apply or check the merged database migrations.
    Migrate(migrate::MigrateArgs),
    /// Run an audited operator action.
    Admin(admin::AdminArgs),
}

/// Return the stable version command output.
#[must_use]
pub fn version_line() -> String {
    format!("kinevo {}", env!("CARGO_PKG_VERSION"))
}

/// Parses the process environment once at the composition boundary.
pub fn config_from_process() -> Result<crate::config::Config, Vec<crate::config::ConfigIssue>> {
    crate::config::Config::from_env(&|name| std::env::var(name).ok(), &|path: &Path| {
        std::fs::read_to_string(path)
    })
}

/// Requests `/readyz` from the configured local listener without loading server secrets.
pub async fn health_from_process() -> Result<(), &'static str> {
    use tokio::{io::AsyncReadExt, io::AsyncWriteExt, net::TcpStream};

    let address = match std::env::var("KINEVO_LISTEN") {
        Ok(value) => value
            .parse::<SocketAddr>()
            .map_err(|_| "health listener address is invalid")?,
        Err(_) => SocketAddr::from(([127, 0, 0, 1], 8080)),
    };
    let mut stream = TcpStream::connect(address)
        .await
        .map_err(|_| "local readiness endpoint is unavailable")?;
    stream
        .write_all(b"GET /readyz HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n")
        .await
        .map_err(|_| "local readiness request failed")?;
    let mut response = Vec::new();
    stream
        .read_to_end(&mut response)
        .await
        .map_err(|_| "local readiness response failed")?;
    let first_line = response
        .split(|byte| *byte == b'\n')
        .next()
        .ok_or("local readiness response was empty")?;
    if first_line.starts_with(b"HTTP/1.1 200 ") || first_line.starts_with(b"HTTP/1.0 200 ") {
        Ok(())
    } else {
        Err("local readiness endpoint is not ready")
    }
}

/// Formats parse errors without exposing the supplied configuration values.
#[must_use]
pub fn config_error(issues: &[crate::config::ConfigIssue]) -> String {
    issues
        .iter()
        .map(|issue| format!("{}: {}", issue.variable, issue.rule))
        .collect::<Vec<_>>()
        .join("; ")
}

#[cfg(test)]
mod tests {
    #[test]
    fn version_line_has_package_version() {
        assert_eq!(
            super::version_line(),
            format!("kinevo {}", env!("CARGO_PKG_VERSION"))
        );
    }
}
