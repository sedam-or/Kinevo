#!/usr/bin/env bash
# Kinevo — version drift / bump validation
#
# Validates a candidate release version against the latest git tag and the
# changelog. Fails on incompatible numbers. Does NOT modify anything.
#
# Usage:
#   scripts/check-version.sh            # uses latest git tag as reference
#   scripts/check-version.sh 0.6.0      # validate a candidate version
#
# Exit codes:
#   0  OK
#   1  version format / monotonicity / changelog inconsistency
set -euo pipefail

ROOT="$(cd "${1:-$(pwd)}" && pwd)"
CHANGELOG="$ROOT/CHANGELOG.md"
CANDIDATE="${2:-}"

SEMVER_RE='^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$'

fail() { echo "ERROR: $*" >&2; exit 1; }

[[ -f "$CHANGELOG" ]] || fail "CHANGELOG.md not found at $CHANGELOG"

# Determine the current released version from the most recent tag (vX.Y.Z or X.Y.Z).
latest_tag="$(git -C "$ROOT" tag --list 'v[0-9]*.[0-9]*.[0-9]*' 2>/dev/null \
  | sort -V | tail -1 || true)"
latest_ver="${latest_tag#v}"

# If an explicit candidate is provided, validate it.
if [[ -n "$CANDIDATE" ]]; then
  [[ "$CANDIDATE" =~ $SEMVER_RE ]] \
    || fail "candidate version '$CANDIDATE' is not valid SemVer"
  echo "Candidate version: $CANDIDATE"

  if [[ -n "$latest_ver" ]]; then
    # Monotonic increase check (sort -V places the larger first if it is newer).
    greater="$(printf '%s\n%s\n' "$latest_ver" "$CANDIDATE" | sort -V | tail -1)"
    if [[ "$greater" != "$CANDIDATE" || "$CANDIDATE" == "$latest_ver" ]]; then
      fail "candidate '$CANDIDATE' is not a monotonic increase over '$latest_ver'"
    fi
    echo "  (monotonic increase over $latest_ver: OK)"
  else
    echo "  (no existing release tag found; this would be the first release)"
  fi

  # Changelog must contain an entry for the candidate (once released).
  if grep -qE "^## \[$CANDIDATE\]" "$CHANGELOG"; then
    echo "  (CHANGELOG already contains $CANDIDATE)"
  else
    # An Unreleased section must exist to stage the candidate.
    grep -q '^## \[Unreleased\]' "$CHANGELOG" \
      || fail "CHANGELOG has no '## [Unreleased]' section to stage $CANDIDATE"
    echo "  (CHANGELOG Unreleased present; $CANDIDATE not yet released)"
  fi
else
  if [[ -n "$latest_ver" ]]; then
    echo "Current released version: $latest_ver (tag $latest_tag)"
  else
    echo "No release tag found — project has not yet published a release."
  fi
fi

# Cross-check: every released version heading in the changelog should have a
# corresponding tag (warn only for historical entries, which predate the
# release-management lifecycle).
echo "Changelog release check:"
grep -oE '^## \[[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?\]' "$CHANGELOG" \
  | sed -E 's/^## \[(.*)\]/\1/' \
  | sort -uV | while read -r v; do
    if [[ "$v" != "Unreleased" ]]; then
      if git -C "$ROOT" rev-parse -q --verify "refs/tags/v$v" >/dev/null 2>&1; then
        echo "  [OK] v$v tagged"
      else
        echo "  [warn] $v in CHANGELOG but no v$v tag (historical/unreleased)"
      fi
    fi
  done

echo "Version check PASSED."
