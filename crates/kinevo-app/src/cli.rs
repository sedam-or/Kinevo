pub mod cli {
    use clap::{Parser, Subcommand};

    #[derive(Parser)]
    #[command(name = "kinevo", disable_help_subcommand = true)]
    pub struct Cli {
        #[command(subcommand)]
        pub command: Command,
    }

    #[derive(Subcommand)]
    pub enum Command {
        Version,
    }

    /// Return the stable version command output.
    pub fn version_line() -> String {
        format!("kinevo {}", env!("CARGO_PKG_VERSION"))
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
}
