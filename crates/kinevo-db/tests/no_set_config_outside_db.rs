use std::{fs, path::Path};

fn visit_sources(path: &Path, findings: &mut Vec<String>) {
    let entries = fs::read_dir(path);
    assert!(entries.is_ok(), "source directory should be readable");
    let Ok(entries) = entries else {
        return;
    };
    for entry in entries {
        assert!(entry.is_ok(), "source entry should be readable");
        let Ok(entry) = entry else {
            continue;
        };
        let child = entry.path();
        if child.is_dir() {
            visit_sources(&child, findings);
        } else if child.extension().is_some_and(|extension| extension == "rs") {
            let source = fs::read_to_string(&child);
            assert!(source.is_ok(), "Rust source should be readable");
            if let Ok(source) = source
                && (source.contains("set_config(") || source.contains("SET LOCAL"))
            {
                findings.push(child.display().to_string());
            }
        }
    }
}

#[test]
fn adr_0011_only_kinevo_db_sets_context() {
    let manifest = Path::new(env!("CARGO_MANIFEST_DIR"));
    let workspace = manifest.join("../..");
    let crates = workspace.join("crates");
    let entries = fs::read_dir(&crates);
    assert!(
        entries.is_ok(),
        "workspace crates directory should be readable"
    );
    let Ok(entries) = entries else {
        return;
    };
    let mut findings = Vec::new();
    for entry in entries {
        assert!(entry.is_ok(), "crate entry should be readable");
        let Ok(entry) = entry else {
            continue;
        };
        let crate_path = entry.path();
        if crate_path
            .file_name()
            .is_some_and(|name| name != "kinevo-db")
        {
            let source = crate_path.join("src");
            if source.is_dir() {
                visit_sources(&source, &mut findings);
            }
        }
    }
    assert!(
        findings.is_empty(),
        "context setters appeared outside kinevo-db: {findings:?}"
    );
}
