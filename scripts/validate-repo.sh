#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "${1:-.}" && pwd)"

files=(
  README.md
  AGENTS.md
  TASK.md
  LICENSE
  CONTRIBUTING.md
  CODE_OF_CONDUCT.md
  SECURITY.md
  SUPPORT.md
  CHANGELOG.md
  CITATION.cff
  .editorconfig
  .gitattributes
  .dockerignore
  .github/dependabot.yml
  .github/CODEOWNERS
  .github/PULL_REQUEST_TEMPLATE.md
  docs/SRS.md
  docs/ux/design-system.md
  docs/architecture.md
  docs/domain-model.md
  docs/scheduling-engine.md
  docs/knowledge-layer.md
  docs/offline-sync.md
  docs/ai-architecture.md
  docs/deployment.md
  docs/environment.md
  docs/test-strategy.md
  docs/release-management.md
  docs/compatibility.md
  docs/requirements/requirements-traceability.md
  docs/api/openapi.yaml
  docs/adr/ADR-001-architecture.md
  docs/adr/ADR-002-frontend.md
  docs/adr/ADR-003-scheduler.md
  docs/adr/ADR-004-knowledge.md
  docs/adr/ADR-005-canvas.md
  docs/adr/ADR-006-ai.md
  docs/adr/ADR-007-deployment.md
  docs/third-party/licenses.md
  docs/third-party/attributions.md
  database/schema.sql
)

dirs=(
  database/migrations
  server
  resources
  tests
  infrastructure
)

errors=0
printf '%s\n' "Kinevo repository validation"
printf '%s\n' "Root: $ROOT"
printf '%s\n\n' "----------------------------------------"

for f in "${files[@]}"; do
  if [[ -f "$ROOT/$f" ]]; then
    printf '  [OK] FILE %s\n' "$f"
  else
    printf ' [MISS] FILE %s\n' "$f"
    errors=$((errors+1))
  fi
done

for d in "${dirs[@]}"; do
  if [[ -d "$ROOT/$d" ]]; then
    printf '  [OK] DIR  %s/\n' "$d"
  else
    printf ' [MISS] DIR  %s/\n' "$d"
    errors=$((errors+1))
  fi
done

if [[ -f "$ROOT/docs/SRS.md" ]]; then
  if grep -q 'personal operating system' "$ROOT/docs/SRS.md"; then
    echo "  [OK] SRS contains Kinevo identity"
  else
    echo " [WARN] SRS does not contain expected Kinevo identity"
  fi
fi

if [[ -f "$ROOT/TASK.md" ]]; then
  for s in TODO READY IN_PROGRESS BLOCKED IN_REVIEW DONE DEFERRED CANCELLED; do
    if grep -q "Status: $s" "$ROOT/TASK.md"; then
      printf '  [OK] TASK status vocabulary includes %s\n' "$s"
    fi
  done
fi

if [[ -f "$ROOT/docs/api/openapi.yaml" ]]; then
  if grep -qE '^(openapi:|swagger:)' "$ROOT/docs/api/openapi.yaml"; then
    echo "  [OK] OpenAPI header detected"
  else
    echo " [WARN] OpenAPI header not detected"
  fi
fi

if [[ "$errors" -gt 0 ]]; then
  printf '\nVALIDATION FAILED: %d required items missing.\n' "$errors"
  exit 1
fi

printf '\nVALIDATION PASSED: required repository structure exists.\n'
