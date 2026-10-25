setup:
    #!/usr/bin/env bash
    set -euo pipefail
    export PATH="$HOME/.cargo/bin:$PATH"
    if command -v rustup >/dev/null 2>&1; then
      rustup show
    else
      echo "rustup is unavailable; use the system toolchain (or scripts/setup-dev.sh --all)."
    fi
    if ! command -v cargo-deny >/dev/null 2>&1 || ! command -v cargo-nextest >/dev/null 2>&1; then
      cargo install --locked cargo-deny cargo-nextest
    fi

fmt:
    cargo fmt --all

lint:
    cargo fmt --all -- --check
    cargo clippy --workspace --all-targets -- -D warnings

test:
    #!/usr/bin/env bash
    set -euo pipefail
    if cargo nextest --version >/dev/null 2>&1; then
      cargo nextest run --workspace
    else
      echo "cargo-nextest is unavailable; falling back to cargo test --workspace."
      cargo test --workspace
    fi

spec:
    python3 scripts/spec_tool.py check
    python3 scripts/contrast.py
    python3 scripts/tasks_index.py --check

check: lint test
    cargo deny check
    just spec

dev:
    @echo "not available until T-039"
