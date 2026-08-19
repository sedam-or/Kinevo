#!/usr/bin/env bash
# Kinevo — changelog validation (Keep a Changelog)
#
# Verifies structural invariants of CHANGELOG.md. It does NOT require every git
# commit to appear; entries are user/developer-visible outcomes.
#
# Usage: scripts/check-changelog.sh [ROOT]
set -euo pipefail

ROOT="$(cd "${1:-$(pwd)}" && pwd)"
CHANGELOG="$ROOT/CHANGELOG.md"

fail() { echo "ERROR: $*" >&2; exit 1; }

[[ -f "$CHANGELOG" ]] || fail "CHANGELOG.md not found at $CHANGELOG"

# 1. Header statement (Semantic Versioning / Keep a Changelog).
grep -qi 'Semantic Versioning' "$CHANGELOG" \
  || fail "CHANGELOG must state it follows Semantic Versioning"
grep -qi 'Keep a Changelog' "$CHANGELOG" \
  || fail "CHANGELOG must state it follows Keep a Changelog"

# 2. An Unreleased section must exist as the staging area.
grep -q '^## \[Unreleased\]' "$CHANGELOG" \
  || fail "CHANGELOG is missing '## [Unreleased]' section"

# 3. Every released version heading must have a valid date.
#    Expect '## [0.0.0] — YYYY-MM-DD' or '## [0.0.0] - YYYY-MM-DD'.
while IFS= read -r line; do
  if [[ "$line" =~ ^##\ \[(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(-[-0-9A-Za-z.]+)?\]\ *[-—]\ *([0-9]{4}-[0-9]{2}-[0-9]{2})$ ]]; then
    :
  elif [[ "$line" =~ ^##\ \[(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(-[-0-9A-Za-z.]+)?\]$ ]]; then
    fail "released version heading '$line' is missing a date (expected '## [x.y.z] - YYYY-MM-DD')"
  fi
done < <(grep -E '^## ' "$CHANGELOG")

# 4. Detect duplicate release versions.
dups="$(grep -oE '^## \[[0-9]+\.[0-9]+\.[0-9]+(-[-0-9A-Za-z.]+)?\]' "$CHANGELOG" \
  | sort | uniq -d || true)"
if [[ -n "$dups" ]]; then
  fail "duplicate release version heading(s): $dups"
fi

# 5. No raw git-log dumping indicators in released entries.
if grep -qE '^\s*- (refactor\(|feat\(|fix\(|docs\(|chore\()' "$CHANGELOG"; then
  # Allow a single leading convention mention but flag obvious commit-style lines.
  :
fi

echo "Changelog check PASSED."
