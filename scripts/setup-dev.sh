#!/usr/bin/env bash
# Installs every tool the Nivaras toolchain can use (D-53). Idempotent; safe to re-run. Never prints secrets.
#   scripts/setup-dev.sh            user-level tools only (no sudo): cargo tools, wasm32 via rustup, corepack, Postgres image
#   scripts/setup-dev.sh --sudo     also system packages (pacman: shellcheck, jdk, adb, postgresql client)
#   scripts/setup-dev.sh --android  also the Android SDK under ~/Android/Sdk (cmdline-tools, platform, build-tools)
#   scripts/setup-dev.sh --browsers also Playwright browsers (needs web/ to exist)
#   scripts/setup-dev.sh --rustup   also install rustup into ~/.cargo (replaces the ~/.cargo/bin/cargo proxy; enables the wasm32 target)
#   scripts/setup-dev.sh --all      everything above
# Anything that cannot be installed is printed as ENV-LIMITED and never stops the run (D-52).
set -uo pipefail
cd "$(dirname "$0")/.."
SUDO=0; ANDROID=0; BROWSERS=0; RUSTUP=0
for a in "$@"; do case "$a" in --sudo) SUDO=1;; --android) ANDROID=1;; --browsers) BROWSERS=1;; --rustup) RUSTUP=1;; --all) SUDO=1; ANDROID=1; BROWSERS=1; RUSTUP=1;; -h|--help) sed -n '2,10p' "$0"; exit 0;; *) echo "unknown option: $a" >&2; exit 64;; esac; done
ok() { printf 'OK          %s\n' "$*"; }; lim() { printf 'ENV-LIMITED %s\n' "$*"; }
export PATH="$HOME/.cargo/bin:$PATH"

cargo_tool() { # command-name crate
  { command -v "$1" || cargo "${1#cargo-}" --version; } >/dev/null 2>&1 && { ok "$1"; return; }
  cargo install --locked "$2" >/dev/null 2>&1 && ok "$1 (installed)" || lim "$1 not installed (cargo install --locked $2)"
}

# 1. Rust: a rustup toolchain is needed for the wasm32 target (the Arch `rust` package has no rustup).
if command -v rustup >/dev/null 2>&1; then ok "rustup"
elif [ "$RUSTUP" -eq 1 ] && command -v curl >/dev/null 2>&1 && curl -fsSL https://sh.rustup.rs | sh -s -- -y --no-modify-path --profile minimal -c rustfmt -c clippy >/dev/null 2>&1; then ok "rustup (installed to ~/.cargo/bin; add: export PATH=\"\$HOME/.cargo/bin:\$PATH\")"
else lim "rustup absent: wasm32 target and the toolchain pin are unavailable (rerun with --rustup)"; fi
if command -v rustup >/dev/null 2>&1; then
  rustup show >/dev/null 2>&1 || true
  rustup target add wasm32-unknown-unknown >/dev/null 2>&1 && ok "wasm32-unknown-unknown target" || lim "wasm32 target not installed"
fi

# 2. Cargo tools
cargo_tool just just
cargo_tool cargo-nextest cargo-nextest
cargo_tool cargo-deny cargo-deny
cargo_tool cargo-audit cargo-audit
cargo_tool cargo-llvm-cov cargo-llvm-cov
cargo_tool cargo-mutants cargo-mutants
cargo_tool cargo-fuzz cargo-fuzz
cargo_tool wasm-pack wasm-pack
cargo_tool wasm-bindgen wasm-bindgen-cli
cargo_tool typos typos-cli

# 3. Node: pnpm via corepack (D-48)
if command -v corepack >/dev/null 2>&1; then corepack enable >/dev/null 2>&1; command -v pnpm >/dev/null && ok "pnpm $(pnpm -v 2>/dev/null)" || lim "pnpm unavailable via corepack"; else lim "corepack missing (install Node 22)"; fi

# 4. Docker image for database tests
if command -v docker >/dev/null && timeout 20 docker info >/dev/null 2>&1; then
  docker image inspect postgres:17-alpine >/dev/null 2>&1 && ok "postgres:17-alpine" || { timeout 600 docker pull postgres:17-alpine >/dev/null 2>&1 && ok "postgres:17-alpine (pulled)" || lim "postgres:17-alpine pull failed; scripts/dev-postgres.sh retries"; }
else lim "Docker daemon not reachable: database tests use KINEVO_TEST_DATABASE_URL or are environment-limited"; fi

# 5. System packages (needs sudo; Codex cannot do this unattended, so it is opt-in)
if [ "$SUDO" -eq 1 ]; then
  if command -v pacman >/dev/null; then
    sudo pacman -S --needed --noconfirm shellcheck jdk17-openjdk android-tools postgresql-libs base-devel pkgconf openssl && ok "pacman packages" || lim "pacman install failed"
  elif command -v apt-get >/dev/null; then
    sudo apt-get install -y shellcheck openjdk-17-jdk adb libpq-dev build-essential pkg-config libssl-dev && ok "apt packages" || lim "apt install failed"
  else lim "no supported package manager: install shellcheck, a JDK 17, adb manually"; fi
fi

# 6. Android SDK (user-level; accepts the Google SDK licences, which the maintainer authorises by passing --android)
if [ "$ANDROID" -eq 1 ]; then
  SDK="${ANDROID_HOME:-$HOME/Android/Sdk}"; TOOLS="$SDK/cmdline-tools/latest/bin/sdkmanager"
  if [ ! -x "$TOOLS" ]; then
    tmp="$(mktemp -d)"; zip="$tmp/cmdline-tools.zip"
    if curl -fsSL -o "$zip" https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip && command -v unzip >/dev/null && unzip -q "$zip" -d "$tmp/x"; then
      mkdir -p "$SDK/cmdline-tools" && rm -rf "$SDK/cmdline-tools/latest" && mv "$tmp/x/cmdline-tools" "$SDK/cmdline-tools/latest"
    else lim "Android cmdline-tools download or unzip failed"; fi
    rm -rf "$tmp"
  fi
  if [ -x "$TOOLS" ]; then
    yes | "$TOOLS" --sdk_root="$SDK" --licenses >/dev/null 2>&1
    "$TOOLS" --sdk_root="$SDK" "platform-tools" "platforms;android-35" "build-tools;35.0.0" >/dev/null 2>&1 && ok "Android SDK at $SDK (export ANDROID_HOME=$SDK)" || lim "sdkmanager install failed"
  fi
fi

# 7. Playwright browsers (after web/ exists)
if [ "$BROWSERS" -eq 1 ]; then
  if [ -f web/package.json ]; then (cd web && pnpm install --frozen-lockfile >/dev/null 2>&1; pnpm exec playwright install --with-deps chromium >/dev/null 2>&1) && ok "Playwright chromium" || lim "Playwright install failed (--with-deps may need sudo)"
  else lim "web/ not created yet: rerun with --browsers after T-043"; fi
fi
echo "SETUP-DEV: done. Re-run scripts/preflight.sh to confirm."
