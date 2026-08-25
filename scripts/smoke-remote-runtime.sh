#!/usr/bin/env bash
# TASK-P18-017 — Remote runtime smoke test.
#
# Proves the full AI chain  Browser/HTTP → Laravel → remote OpenAI-compatible
# endpoint → successful model call  while Ollama is NOT running.
#
# Credentials are INJECTED, never stored in this repository:
#   export KINEVO_SMOKE_AI_BASE_URL="http://172.17.0.1:20128/v1"   # optional
#   export KINEVO_SMOKE_AI_MODEL="auto/best-free"                  # optional
#   export KINEVO_SMOKE_AI_API_KEY="sk-…"                          # required
#
# Usage: make up && bash scripts/smoke-remote-runtime.sh
set -euo pipefail

BASE_URL="${KINEVO_SMOKE_AI_BASE_URL:-http://127.0.0.1:8000/api/v1}"
AI_BASE="${KINEVO_SMOKE_AI_BASE_URL:-http://172.17.0.1:20128/v1}"
MODEL="${KINEVO_SMOKE_AI_MODEL:-auto/best-free}"
KEY="${KINEVO_SMOKE_AI_API_KEY:-}"

if [[ -z "$KEY" ]]; then
  echo "FAIL: set KINEVO_SMOKE_AI_API_KEY (injected credential; nothing is hardcoded here)." >&2
  exit 1
fi

step() { printf '\n== %s ==\n' "$1"; }

step "Ollama must NOT be running"
if docker ps --format '{{.Names}}' | grep -q ollama; then
  echo "FAIL: an ollama container is running. Stop it first (docker stop <name>)." >&2
  exit 1
fi
echo "OK: no ollama container."

step "Reset persisted AI settings (deterministic bootstrap)"
docker exec infrastructure-app-1 php artisan tinker --execute "
\App\Models\AiProviderConfig::query()->delete();
\App\Models\User::where('email', 'like', 'smoke-%@example.test')->delete();" >/dev/null 2>&1
echo "OK: singleton cleared."

step "Owner token"
TOKEN=$(docker exec infrastructure-app-1 php artisan tinker --execute="
\$u = \App\Models\User::factory()->create(['email' => 'smoke-'.bin2hex(random_bytes(4)).'@example.test']);
echo \$u->createToken('p18-smoke')->plainTextToken;" 2>/dev/null | tail -1)
if [[ -z "$TOKEN" ]]; then echo "FAIL: could not mint a token (is the dev stack up? make up)" >&2; exit 1; fi

cleanup() {
  docker exec infrastructure-app-1 php artisan tinker --execute "\App\Models\User::where('email', 'like', 'smoke-%@example.test')->delete();" >/dev/null 2>&1 || true
}
trap cleanup EXIT

auth=(-H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json')

step "PATCH /ai/settings → provider without credential yet"
curl -sf -X PATCH "$BASE_URL/ai/settings" "${auth[@]}" \
  -d "{\"provider\":\"openai\",\"enabled\":true,\"model\":\"$MODEL\",\"base_url\":\"$AI_BASE\"}" \
  | python3 -c "import json,sys;d=json.load(sys.stdin)['config'];assert d['configured'] is False and d['has_api_key'] is False,d;print('OK: not_configured before credential')"

step "POST /ai/settings/credential → encrypted storage, masked response"
RESP=$(curl -sf -X POST "$BASE_URL/ai/settings/credential" "${auth[@]}" -d "{\"api_key\":\"$KEY\"}")
echo "$RESP" | python3 -c "
import json,sys
d=json.load(sys.stdin)['config']
assert d['configured'] is True, d
assert d['api_key_hint'] and not d['api_key_hint'].startswith('sk'), d
raw=open('/dev/stdin').read()
assert '$KEY' not in raw, 'SECRET LEAKED IN RESPONSE'
print('OK: configured, hint only:', d['api_key_hint'])
" <<< "$RESP"

step "POST /ai/settings/test → minimal inference probe"
curl -sf --max-time 180 -X POST "$BASE_URL/ai/settings/test" "${auth[@]}" \
  | python3 -c "import json,sys;d=json.load(sys.stdin);assert d['ok'],d;print('OK:',d['message'],'('+str(d['status']['latency_ms'])+' ms)')"

step "POST /ai/generate → successful model call through Laravel"
curl -sf --max-time 240 -X POST "$BASE_URL/ai/generate" "${auth[@]}" \
  -H 'Content-Type: application/json' \
  -d '{"role":"natural_language_explanation","prompt":"Reply with exactly: SMOKE OK","max_tokens":32}' \
  | python3 -c "import json,sys;d=json.load(sys.stdin);assert d.get('text'),d;print('OK: model',d['model'],'replied in',d['latency_ms'],'ms')"

printf '\nP18-017 REMOTE RUNTIME SMOKE: PASS\n'
