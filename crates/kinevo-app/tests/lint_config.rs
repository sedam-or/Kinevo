#![forbid(unsafe_code)]
#![cfg_attr(
    test,
    allow(
        clippy::panic,
        reason = "these tests assert required lint configuration"
    )
)]

use std::{error::Error, fs, path::Path};

const WORKSPACE_ROOT: &str = concat!(env!("CARGO_MANIFEST_DIR"), "/../..");

const DISALLOWED_METHODS: [&str; 8] = [
    "std::time::SystemTime::now",
    "std::time::Instant::now",
    "uuid::Uuid::now_v7",
    "uuid::Uuid::new_v4",
    "jiff::Timestamp::now",
    "jiff::Zoned::now",
    "rand::thread_rng",
    "rand::random",
];

const CRATES: [(&str, &str); 7] = [
    (
        "crates/kinevo-kernel/Cargo.toml",
        "crates/kinevo-kernel/src/lib.rs",
    ),
    (
        "crates/kinevo-ports/Cargo.toml",
        "crates/kinevo-ports/src/lib.rs",
    ),
    ("crates/kinevo-db/Cargo.toml", "crates/kinevo-db/src/lib.rs"),
    (
        "crates/kinevo-http/Cargo.toml",
        "crates/kinevo-http/src/lib.rs",
    ),
    (
        "crates/kinevo-mod-identity/Cargo.toml",
        "crates/kinevo-mod-identity/src/lib.rs",
    ),
    (
        "crates/kinevo-mod-admin/Cargo.toml",
        "crates/kinevo-mod-admin/src/lib.rs",
    ),
    (
        "crates/kinevo-app/Cargo.toml",
        "crates/kinevo-app/src/main.rs",
    ),
];

#[test]
fn cq_07_disallowed_methods_listed() -> Result<(), Box<dyn Error>> {
    let config = fs::read_to_string(Path::new(WORKSPACE_ROOT).join("clippy.toml"))?;
    for method in DISALLOWED_METHODS {
        assert!(
            config.contains(method),
            "missing disallowed method: {method}"
        );
    }
    Ok(())
}

#[test]
fn cq_03_forbid_unsafe_everywhere() -> Result<(), Box<dyn Error>> {
    for (manifest_path, source_path) in CRATES {
        let manifest = fs::read_to_string(Path::new(WORKSPACE_ROOT).join(manifest_path))?;
        let source = fs::read_to_string(Path::new(WORKSPACE_ROOT).join(source_path))?;
        assert!(
            manifest.contains("[lints]") && manifest.contains("workspace = true"),
            "workspace lints are not enabled in {manifest_path}"
        );
        assert!(
            source.contains("#![forbid(unsafe_code)]"),
            "unsafe is not forbidden in {source_path}"
        );
    }
    Ok(())
}
