#!/usr/bin/env bash
set -euo pipefail

# Validate that relative markdown links in the repository resolve to real files.
# Usage: ./scripts/check-doc-links.sh [ROOT]
ROOT="$(cd "${1:-.}" && pwd)"

cd "$ROOT"

files=$(git ls-files '*.md' | tr '\n' ' ')
errors=0
checked=0

for file in $files; do
    # Extract relative markdown links: [text](path) where path does not start with http,#,mailto
    while IFS= read -r target; do
        [[ -z "$target" ]] && continue
        # Skip anchors-only, absolute, external, and fragment links
        case "$target" in
            http*|https*|mailto:*|tel:*|\#*) continue ;;
        esac
        path="${target%%#*}"          # strip fragment
        dir=$(dirname "$file")
        if [[ "$path" =~ ^/ ]]; then
            rel="$ROOT${path}"
        else
            rel="$ROOT/$dir/$path"
        fi
        # URL-decode percent sequences roughly (%20 etc.)
        rel="${rel//%20/ }"
        checked=$((checked+1))
        if [[ ! -e "$rel" ]]; then
            printf ' [BROKEN] %s -> %s\n' "$file" "$target"
            errors=$((errors+1))
        fi
    done < <(grep -oE '\]\(([^)]+)\)' "$file" | sed -E 's/^\]\(//; s/\)$//')
done

printf '\nDoc link check: %d links checked.\n' "$checked"
if [[ "$errors" -gt 0 ]]; then
    printf 'Doc link check: %d broken link(s).\n' "$errors"
    exit 1
fi
printf 'Doc link check: PASSED.\n'
