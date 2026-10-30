use std::{env, fs, path::PathBuf, process};

fn main() {
    let Some(manifest_dir) = env::var_os("CARGO_MANIFEST_DIR").map(PathBuf::from) else {
        eprintln!("CARGO_MANIFEST_DIR is missing; Cargo must run this build script.");
        process::exit(1);
    };
    let dist = manifest_dir.join("../../web/dist");
    let index = dist.join("index.html");
    println!("cargo:rerun-if-changed={}", dist.display());

    if env::var("PROFILE").is_ok_and(|profile| profile == "release") {
        if !index.is_file() {
            eprintln!(
                "web/dist/index.html is missing; run `just build-web` before a release build"
            );
            process::exit(1);
        }
    } else if !dist.exists()
        && let Err(error) = fs::create_dir_all(&dist)
    {
        eprintln!("failed to create debug web asset directory: {error}");
        process::exit(1);
    }
}
