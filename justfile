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
      df -h /
      cargo install --locked cargo-deny cargo-nextest
    fi
    if command -v corepack >/dev/null 2>&1; then
      cd web
      corepack enable
      corepack pnpm install --frozen-lockfile
    else
      echo "corepack is unavailable; install the pinned Node.js LTS toolchain first."
      exit 1
    fi

build-web:
    pnpm --dir web gen:api
    df -h /
    pnpm --dir web build

fmt:
    cargo fmt --all

lint:
    cargo fmt --all -- --check
    df -h /
    cargo clippy --workspace --all-targets -- -D warnings
    df -h /
    pnpm --dir web typecheck
    df -h /
    pnpm --dir web lint

test:
    #!/usr/bin/env bash
    set -euo pipefail
    just build-web
    if cargo nextest --version >/dev/null 2>&1; then
      df -h /
      cargo nextest run --workspace --test-threads 4
    else
      echo "cargo-nextest is unavailable; falling back to cargo test --workspace."
      df -h /
      cargo test --workspace -- --test-threads 4
    fi
    df -h /
    pnpm --dir web test

test-slow:
    df -h /
    cargo nextest run --workspace --run-ignored only --test-threads 4

spec:
    python3 scripts/spec_tool.py check
    python3 scripts/contrast.py
    python3 scripts/tasks_index.py --check
    df -h /
    cargo run -q -p kinevo-app -- openapi --check contracts/openapi.json
    pnpm --dir web gen:api
    git diff --exit-code -- web/src/api/schema.d.ts

check: lint test
    cargo deny check
    just spec

dev:
    @echo "not available until T-039"
