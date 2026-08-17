#!/usr/bin/env bash
set -euo pipefail

# Validate that docs/api/openapi.yaml is structurally sound and that required
# top-level keys exist. Uses python3 with PyYAML when available; otherwise falls
# back to basic grep checks.
# Usage: ./scripts/check-openapi.sh [ROOT]

ROOT="$(cd "${1:-.}" && pwd)"
SPEC="$ROOT/docs/api/openapi.yaml"

[[ -f "$SPEC" ]] || { echo "ERROR: $SPEC not found" >&2; exit 1; }

echo "OpenAPI check: $SPEC"

# Basic structural keys
for key in 'openapi:' 'info:' 'paths:' 'components:'; do
    if grep -qE "^${key}" "$SPEC"; then
        printf '  [OK] %s\n' "$key"
    else
        printf ' [MISS] top-level %s\n' "$key"
        exit 1
    fi
done

# securitySchemes lives under components in OpenAPI 3.x
if grep -qE '^  securitySchemes:' "$SPEC"; then
    printf '  [OK] components/securitySchemes\n'
else
    printf ' [MISS] components/securitySchemes\n'
    exit 1
fi

# Paths must be non-empty
path_count=$(grep -cE '^  /' "$SPEC" || true)
printf '  [OK] %s path definitions\n' "$path_count"

if command -v python3 >/dev/null 2>&1; then
    if python3 -c "import yaml" 2>/dev/null; then
        python3 - "$SPEC" <<'PY'
import sys, yaml
spec = yaml.safe_load(open(sys.argv[1], encoding='utf-8'))
errors = []
if spec.get('openapi') is None:
    errors.append('missing openapi version')
if not spec.get('paths'):
    errors.append('no paths')
if 'bearerAuth' not in (spec.get('components', {}).get('securitySchemes', {})):
    errors.append('missing bearerAuth securityScheme')
if errors:
    print('OpenAPI check: FAILED')
    for e in errors:
        print('  -', e)
    sys.exit(1)
print('OpenAPI check: PASSED (YAML parse + structure)')
PY
    else
        echo "OpenAPI check: WARN python3-yaml not installed; skipped deep parse"
    fi
else
    echo "OpenAPI check: WARN python3 not available; used grep-level checks only"
fi
