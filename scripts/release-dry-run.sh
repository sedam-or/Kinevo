#!/usr/bin/env bash
# Kinevo — release dry-run / readiness gate
#
# Verifies that the repository is ready to cut a release for the given (or
# current) version. It is NON-DESTRUCTIVE: it never tags, pushes, or publishes.
#
# Usage:
#   scripts/release-dry-run.sh               # no version argument (check state)
#   scripts/release-dry-run.sh 0.6.0         # validate a candidate version
#
# Outputs a final summary: READY or BLOCKED.
set -uo pipefail

ROOT="$(cd "${1:-$(pwd)}" && pwd)"
CANDIDATE="${2:-}"
cd "$ROOT"

failures=0
step() { printf '\n== %s ==\n' "$1"; }
ok()   { printf '   [OK]   %s\n' "$1"; }
bad()  { printf '   [FAIL] %s\n' "$1"; failures=$((failures+1)); }

# --- git state ---------------------------------------------------------------
step "Git state"
if [[ -n "$(git status --porcelain)" ]]; then
  bad "working tree is not clean (uncommitted changes)"
else
  ok "working tree clean"
fi
branch="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$branch" == "main" || "$branch" == "master" ]]; then
  ok "on integration branch ($branch)"
else
  bad "not on main/master (currently $branch)"
fi

# --- version ----------------------------------------------------------------
step "Version"
if [[ -n "$CANDIDATE" ]]; then
  if bash "$ROOT/scripts/check-version.sh" "$ROOT" "$CANDIDATE" >/dev/null 2>&1; then
    ok "candidate version $CANDIDATE valid & monotonic"
  else
    bad "candidate version check failed: $(bash "$ROOT/scripts/check-version.sh" "$ROOT" "$CANDIDATE" 2>&1 || true)"
  fi
else
  ok "no candidate version supplied (state check only)"
fi

# --- changelog ---------------------------------------------------------------
step "Changelog"
if bash "$ROOT/scripts/check-changelog.sh" "$ROOT" >/dev/null 2>&1; then
  ok "changelog structure valid"
else
  bad "changelog check failed: $(bash "$ROOT/scripts/check-changelog.sh" "$ROOT" 2>&1 || true)"
fi

# --- repository / docs / API / secrets --------------------------------------
step "Repository & contract checks"
run_check() {
  local name="$1"; shift
  if "$@" >/dev/null 2>&1; then ok "$name"; else bad "$name"; fi
}
run_check "repository validation" bash "$ROOT/scripts/validate-repo.sh" "$ROOT"
run_check "secret scan"           bash "$ROOT/scripts/check-secrets.sh" "$ROOT"
run_check "documentation links"   bash "$ROOT/scripts/check-doc-links.sh" "$ROOT"
run_check "OpenAPI validation"    bash "$ROOT/scripts/check-openapi.sh" "$ROOT"

# --- task eligibility -------------------------------------------------------
step "Task board"
if bash "$ROOT/scripts/status.sh" "$ROOT" >/dev/null 2>&1; then
  ok "task board parses"
else
  bad "task board did not parse"
fi

# --- summary ----------------------------------------------------------------
echo
echo "=================================================="
if [[ "$failures" -eq 0 ]]; then
  echo "RELEASE DRY-RUN: READY"
  echo "  (proceed with prepare-release only after running the full test/lint/"
  echo "   analyse/build/security gates; publishing is a deliberate manual action)"
  exit 0
else
  echo "RELEASE DRY-RUN: BLOCKED ($failures issue(s))"
  exit 1
fi
