#![forbid(unsafe_code)]

use clap::Parser;
use kinevo_app::{cli, config::LogFormat};
use std::process::ExitCode;
use tracing::Level;
use tracing_subscriber::util::SubscriberInitExt;

#[tokio::main]
async fn main() -> ExitCode {
    match cli::Cli::parse().command {
        cli::Command::Version => {
            println!("{}", cli::version_line());
            ExitCode::SUCCESS
        }
        cli::Command::Migrate(args) => match cli::migrate::run(args).await {
            Ok(()) => ExitCode::SUCCESS,
            Err(code) => {
                eprintln!("{code}");
                ExitCode::FAILURE
            }
        },
        cli::Command::Admin(args) => match cli::admin::run(args).await {
            Ok(()) => ExitCode::SUCCESS,
            Err(code) => {
                eprintln!("{code}");
                ExitCode::FAILURE
            }
        },
        cli::Command::Health => match cli::health_from_process().await {
            Ok(()) => ExitCode::SUCCESS,
            Err(message) => {
                eprintln!("{message}");
                ExitCode::FAILURE
            }
        },
        cli::Command::Serve => {
            let config = match cli::config_from_process() {
                Ok(config) => config,
                Err(issues) => {
                    eprintln!("{}", cli::config_error(&issues));
                    return ExitCode::FAILURE;
                }
            };
            init_tracing(config.logging.level, config.logging.format);
            let clock = match kinevo_app::runtime::clock_from_process() {
                Ok(clock) => clock,
                Err(message) => {
                    eprintln!("{message}");
                    return ExitCode::FAILURE;
                }
            };
            match kinevo_app::serve::serve(config, clock, shutdown_signal()).await {
                Ok(()) => ExitCode::SUCCESS,
                Err(error) => {
                    eprintln!("{}", error.code());
                    ExitCode::FAILURE
                }
            }
        }
    }
}

fn init_tracing(level: kinevo_app::config::LogLevel, format: LogFormat) {
    let level = match level {
        kinevo_app::config::LogLevel::Trace => Level::TRACE,
        kinevo_app::config::LogLevel::Debug => Level::DEBUG,
        kinevo_app::config::LogLevel::Info => Level::INFO,
        kinevo_app::config::LogLevel::Warn => Level::WARN,
        kinevo_app::config::LogLevel::Error => Level::ERROR,
    };
    match format {
        LogFormat::Json => tracing_subscriber::fmt()
            .json()
            .with_max_level(level)
            .finish()
            .try_init(),
        LogFormat::Pretty => tracing_subscriber::fmt()
            .pretty()
            .with_max_level(level)
            .finish()
            .try_init(),
    }
    .ok();
}

async fn shutdown_signal() {
    #[cfg(unix)]
    {
        use tokio::signal::unix::{SignalKind, signal};

        if let Ok(mut terminate) = signal(SignalKind::terminate()) {
            tokio::select! {
                _ = tokio::signal::ctrl_c() => {},
                _ = terminate.recv() => {},
            }
            return;
        }
    }
    let _ = tokio::signal::ctrl_c().await;
}
