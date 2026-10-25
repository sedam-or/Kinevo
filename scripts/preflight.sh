#!/usr/bin/env bash
# Checks the machine for the stacked implementation run (BRANCHING.md section 6) and installs what it can.
# Exit 0: no hard blocker (optional gaps are printed as ENV-LIMITED). Exit 2: a hard blocker exists.
# Never prints secrets. Safe to re-run.
set -uo pipefail
cd "$(dirname "$0")/.."
export PATH="$HOME/.cargo/bin:$PATH"  # rustup and cargo-installed tools live here
HARD=0; ok() { printf 'OK        %s\n' "$*"; }; lim() { printf 'ENV-LIMITED %s\n' "$*"; }; bad() { printf 'BLOCKER   %s\n' "$*"; HARD=1; }

git rev-parse --git-dir >/dev/null 2>&1 && ok "git repository" || bad "not a git repository"
git remote get-url origin >/dev/null 2>&1 && ok "remote origin set" || bad "no origin remote"
if command -v gh >/dev/null && gh auth status >/dev/null 2>&1; then
  perm="$(gh repo view --json viewerPermission -q .viewerPermission 2>/dev/null || true)"
  case "$perm" in ADMIN|MAINTAIN|WRITE) ok "gh authenticated, permission $perm" ;; *) bad "gh authenticated but cannot write to the repository (permission: ${perm:-unknown})" ;; esac
else bad "gh missing or not authenticated (run: gh auth login)"; fi

for t in cargo rustc python3 node corepack; do command -v "$t" >/dev/null && ok "$t" || bad "$t missing"; done
if command -v corepack >/dev/null; then corepack enable >/dev/null 2>&1 || true; command -v pnpm >/dev/null && ok "pnpm $(pnpm -v 2>/dev/null)" || bad "pnpm unavailable via corepack"; fi
command -v just >/dev/null && ok "just" || { command -v cargo >/dev/null && cargo install --locked just >/dev/null 2>&1 && ok "just (installed)" || bad "just missing"; }

install_cargo_tool() { # name crate
  command -v "$1" >/dev/null 2>&1 || cargo "$1" --version >/dev/null 2>&1 && { ok "$1"; return; }
  cargo install --locked "$2" >/dev/null 2>&1 && ok "$1 (installed)" || lim "$1 could not be installed (needed by: just check)"
}
install_cargo_tool cargo-nextest cargo-nextest
install_cargo_tool cargo-deny cargo-deny
if command -v rustup >/dev/null; then
  rustup target list --installed 2>/dev/null | grep -q wasm32-unknown-unknown && ok "wasm32 target" || { rustup target add wasm32-unknown-unknown >/dev/null 2>&1 && ok "wasm32 target (installed)" || lim "wasm32 target missing (WASM parity)"; }
else lim "rustup missing: wasm32 target unavailable (WASM parity); run scripts/setup-dev.sh --rustup"; fi
command -v wasm-pack >/dev/null && ok wasm-pack || { cargo install --locked wasm-pack >/dev/null 2>&1 && ok "wasm-pack (installed)" || lim "wasm-pack missing (WASM parity)"; }

# database: Docker (testcontainers) or KINEVO_TEST_DATABASE_URL via scripts/dev-postgres.sh
if command -v docker >/dev/null && timeout 20 docker info >/dev/null 2>&1; then
  ok "docker daemon reachable"
  docker image inspect postgres:17-alpine >/dev/null 2>&1 && ok "postgres:17-alpine image present" || lim "postgres:17-alpine not pulled yet (first DB test will pull it; if the pull is slow run: docker pull postgres:17-alpine)"
elif [ -n "${KINEVO_TEST_DATABASE_URL:-}" ]; then ok "KINEVO_TEST_DATABASE_URL set"
else lim "no Docker access: try scripts/dev-postgres.sh env; otherwise DB and E2E tests are environment-limited"; fi

# labels for the issue/PR run
if [ "$HARD" -eq 0 ]; then
  gh label create task --color 1d76db --description "Implementation task" --force >/dev/null 2>&1 && ok "label task" || lim "could not create label task"
  for n in $(seq 1 11); do gh label create "milestone:M$n" --color 5319e7 --description "Milestone M$n" --force >/dev/null 2>&1 || lim "could not create label milestone:M$n"; done
  gh label create blocked --color b60205 --description "Task blocked, question open" --force >/dev/null 2>&1 || true
fi
[ -d android ] && { command -v java >/dev/null && ok "java" || lim "java missing (Android build)"; }
printf '\nPlaywright browsers and the Android SDK are installed later by their tasks (pnpm exec playwright install --with-deps; scripts/android-sdk.sh). Everything at once: scripts/setup-dev.sh --all (D-53).\n'
[ "$HARD" -eq 0 ] && { echo "PREFLIGHT: no hard blockers"; exit 0; } || { echo "PREFLIGHT: hard blockers present"; exit 2; }
