#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "${1:-.}" && pwd)"
errors=0

printf '%s\n' "LIFESYNC secret scan"
printf '%s\n' "Root: $ROOT"
printf '%s\n\n' "----------------------------------------"

# 1. No real .env files may be committed (only .env.example).
if git -C "$ROOT" ls-files | grep -E '(^|/)\.env$' | grep -v '\.env\.example$' | grep -q .; then
    printf ' [FAIL] Committed .env file(s) found:\n'
    git -C "$ROOT" ls-files | grep -E '(^|/)\.env$' | grep -v '\.env\.example$' | sed 's/^/          /'
    errors=$((errors+1))
else
    echo "  [OK] No committed .env files"
fi

# 2. Example file must not contain real-looking credentials.
EXAMPLE="$ROOT/server/.env.example"
if [[ -f "$EXAMPLE" ]]; then
    if grep -qE 'APP_KEY=base64:' "$EXAMPLE"; then
        echo " [FAIL] .env.example contains a real APP_KEY"
        errors=$((errors+1))
    fi
    if grep -qE '(AI[A-Z_]*_KEY|OPENAI|ANTHROPIC|TOKEN|_SECRET_|API_KEY)=[A-Za-z0-9_-]{16,}' "$EXAMPLE"; then
        echo " [FAIL] .env.example contains a long credential-like value"
        errors=$((errors+1))
    fi
    echo "  [OK] .env.example placeholder check"
fi

# 3. Scan tracked files for obvious credential patterns.
tracked="$(git -C "$ROOT" ls-files)"
if printf '%s\n' "$tracked" | while read -r f; do
    case "$f" in
        *.lock|*.png|*.jpg|*.pdf|*.gz|*.zip|*.ico|*.svg) continue ;;
    esac
    if [[ -f "$ROOT/$f" ]] && grep -qE 'APP_KEY=base64:[A-Za-z0-9+/]{40,}' "$ROOT/$f" 2>/dev/null; then
        echo "       found in $f"
        return 1
    fi
done; then
    echo "  [OK] No committed APP_KEY credentials"
else
    echo " [FAIL] Real-looking APP_KEY committed in tracked files"
    errors=$((errors+1))
fi

if [[ "$errors" -gt 0 ]]; then
    printf '\nSECRET SCAN FAILED: %d issue(s).\n' "$errors"
    exit 1
fi

printf '\nSECRET SCAN PASSED.\n'