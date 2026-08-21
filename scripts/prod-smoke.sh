#!/usr/bin/env bash
#
# Kinevo — Production Smoke Test (TASK-156).
#
# Exercises the REAL production Docker path end to end:
#
#   build → deploy → migrate → health → login → goal → task → schedule → today
#        → backup → restore
#
# Secrets (APP_KEY / DB_PASSWORD) are generated at runtime and never persisted to
# disk or the repository. The journey is driven against the live reverse proxy
# (nginx + TLS, self-signed for the smoke run) so the test covers the actual
# production entry path, not an internal shortcut.
#
# Usage:
#   scripts/prod-smoke.sh                 # full build + run + tear-down
#   SERVER_NAME=kinevo.local BUILD=0 \
#     KEEP_UP=1 scripts/prod-smoke.sh     # reuse existing image, leave stack up
#
# Requirements: docker, docker compose, curl, jq, openssl.

set -euo pipefail

# --- Configuration (override via env) --------------------------------------
export COMPOSE_FILE="${COMPOSE_FILE:-infrastructure/docker-compose.prod.yml}"
export PROJECT_NAME="${PROJECT_NAME:-kinevo-prod}"
export SERVER_NAME="${SERVER_NAME:-kinevo.local}"
export APP_URL="${APP_URL:-https://${SERVER_NAME}}"
BUILD="${BUILD:-1}"            # 1 = (re)build the prod image; 0 = reuse existing
KEEP_UP="${KEEP_UP:-0}"        # 1 = leave the stack running after the test
HEALTH_URL="${HEALTH_URL:-https://127.0.0.1/api/v1/health}"
API_BASE="${API_BASE:-https://127.0.0.1/api/v1}"

# --- Runtime secrets (never written to the repo; exported so compose injects
# them into the app/queue-worker/scheduler/postgres/backup roles) -----------
export APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
export DB_PASSWORD="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 32)"

WEEK_START="2026-08-17"
WEEK_END="2026-08-23"

# --- Helpers ---------------------------------------------------------------
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[0;33m'; BOLD='\033[1m'; NC='\033[0m'
step()  { printf '\n%s==> %s%s\n' "$BOLD" "$1" "$NC"; }
ok()    { printf '%s[PASS]%s %s\n' "$GREEN" "$NC" "$1"; }
fail()  { printf '%s[FAIL]%s %s\n' "$RED" "$NC" "$1"; }
warn()  { printf '%s[WARN]%s %s\n' "$YELLOW" "$NC" "$1"; }

CERT_DIR="$(mktemp -d)"
TOKEN=""
BACKUP_FILE=""
SCHEDULED_DAY=""

# Smoke-test-only compose override: bind-mount the generated self-signed cert
# directory into the proxy's certbot_conf volume so nginx can terminate TLS
# without depending on a separate cert-seeding container (which suffers a
# mount-propagation race). The production compose file is left unchanged.
OVERRIDE_FILE="${CERT_DIR}/compose.override.yml"
cat > "${OVERRIDE_FILE}" <<EOF
volumes:
  certbot_conf:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: ${CERT_DIR}
EOF

COMPOSE="docker compose -f ${COMPOSE_FILE} -f ${OVERRIDE_FILE} -p ${PROJECT_NAME}"

cleanup() {
    local rc=$?
    if [[ "${KEEP_UP}" != "1" ]]; then
        step "Tearing down production stack"
        docker compose -f "${COMPOSE_FILE}" -p "${PROJECT_NAME}" down -v >/dev/null 2>&1 || true
    else
        warn "KEEP_UP=1 — stack left running (project '${PROJECT_NAME}')."
    fi
    rm -rf "${CERT_DIR}"
    if [[ $rc -ne 0 ]]; then
        fail "Production smoke test FAILED (exit $rc)"
    fi
    exit $rc
}
trap cleanup EXIT

# Wait until a URL returns the expected HTTP status (curl -w code).
wait_for() {
    local url="$1" want="${2:-200}" tries="${3:-40}" delay="${4:-2}"
    for ((i=1; i<=tries; i++)); do
        local code
        code="$(curl -fsS -o /dev/null -w '%{http_code}' -k "$url" 2>/dev/null || echo 000)"
        if [[ "$code" == "$want" ]]; then return 0; fi
        sleep "$delay"
    done
    return 1
}

api() {
    # api METHOD PATH [json-file]  → prints response body, asserts 2xx via caller
    local method="$1" path="$2" body_file="${3:-}"
    local args=(-fsS -k -H "Accept: application/json" -X "$method" "${API_BASE}${path}")
    if [[ -n "$TOKEN" ]]; then args+=(-H "Authorization: Bearer ${TOKEN}"); fi
    if [[ -n "$body_file" ]]; then args+=(-H "Content-Type: application/json" --data-binary "@${body_file}"); fi
    curl "${args[@]}"
}

# --- 1. Build --------------------------------------------------------------
if [[ "${BUILD}" == "1" ]]; then
    step "build — production image (${PROJECT_NAME})"
    $COMPOSE build
    ok "image built"
else
    warn "BUILD=0 — reusing existing kinevo-app:prod image"
fi

# --- 2. Deploy -------------------------------------------------------------
step "deploy — start postgres + app roles (no reverse proxy yet)"
$COMPOSE up -d postgres app queue-worker scheduler backup
ok "core services started"

# Seed a self-signed cert into the certbot_conf volume so the reverse proxy can
# terminate TLS over the real production path (no Let's Encrypt in the smoke run).
step "deploy — provision self-signed TLS for ${SERVER_NAME}"
CERT_LIVE="${CERT_DIR}/live/${SERVER_NAME}"
mkdir -p "${CERT_LIVE}"
openssl req -x509 -nodes -newkey rsa:2048 -days 2 \
    -keyout "${CERT_LIVE}/privkey.pem" \
    -out "${CERT_LIVE}/fullchain.pem" \
    -subj "/CN=${SERVER_NAME}" >/dev/null 2>&1
if [[ ! -f "${CERT_LIVE}/fullchain.pem" ]]; then
    fail "self-signed cert generation failed"; exit 1
fi
ok "self-signed cert provisioned for ${SERVER_NAME} (bind-mounted into proxy)"

step "deploy — start reverse proxy (TLS)"
$COMPOSE up -d reverse-proxy
# The self-signed cert is seeded into the certbot_conf volume from a helper
# container; occasionally nginx wins the start race and sees an empty mount,
# exits, and docker restarts it. Tolerate that one-time race before failing.
if ! wait_for "${HEALTH_URL}" 200 20 3; then
    warn "proxy not ready on first boot (possible cert-mount race); restarting"
    docker restart "${PROJECT_NAME}-reverse-proxy-1" >/dev/null 2>&1 || true
fi
if wait_for "${HEALTH_URL}" 200 40 3; then
    ok "reverse proxy reachable (health 200)"
else
    fail "reverse proxy did not become reachable"; exit 1
fi

# --- 3. Migrate ------------------------------------------------------------
step "migrate — explicit release step"
$COMPOSE run --rm app migrate --force 2>&1 | tail -n 5
ok "migrations applied"

# --- 4. Health -------------------------------------------------------------
step "health — public readiness probe"
HEALTH_BODY="$(api GET /health)"
HEALTH_STATUS="$(printf '%s' "$HEALTH_BODY" | jq -r '.status // empty')"
if [[ "$HEALTH_STATUS" == "healthy" || "$HEALTH_STATUS" == "ok" ]]; then
    ok "health: ${HEALTH_STATUS}"
else
    fail "health returned: ${HEALTH_BODY}"; exit 1
fi

# --- 5. Login (first-setup register) ---------------------------------------
step "login — register first-setup owner + obtain token"
REG='{"name":"Smoke Owner","email":"smoke@example.com","password":"password123"}'
REG_BODY="$(api POST /auth/register <(printf '%s' "$REG"))"
TOKEN="$(printf '%s' "$REG_BODY" | jq -r '.token // empty')"
if [[ -z "$TOKEN" ]]; then fail "register did not return a token: ${REG_BODY}"; exit 1; fi
ME="$(api GET /auth/me | jq -r '.user.email // empty')"
if [[ "$ME" != "smoke@example.com" ]]; then fail "auth/me email mismatch: $ME"; exit 1; fi
ok "authenticated as ${ME}"

# --- 6. Create goal --------------------------------------------------------
step "create goal"
GOAL_BODY="$(api POST /goals <(printf '%s' '{"title":"Smoke goal","horizon":"quarterly","target_date":"2026-11-30"}'))"
GOAL_ID="$(printf '%s' "$GOAL_BODY" | jq -r '.goal.id // empty')"
[[ -n "$GOAL_ID" ]] || { fail "goal create failed: $GOAL_BODY"; exit 1; }
ok "goal ${GOAL_ID} created"

# --- 7. Create task --------------------------------------------------------
step "create task"
TASK_BODY="$(api POST /tasks <(printf '%s' "{\"title\":\"Smoke task\",\"priority_tier\":1,\"estimated_minutes\":60,\"due_at\":\"${WEEK_END}T17:00:00\",\"goal_id\":${GOAL_ID}}"))"
TASK_ID="$(printf '%s' "$TASK_BODY" | jq -r '.task.id // empty')"
[[ -n "$TASK_ID" ]] || { fail "task create failed: $TASK_BODY"; exit 1; }
ok "task ${TASK_ID} created"

# --- 8. Schedule -----------------------------------------------------------
step "schedule — draft + apply over the golden week"
DRAFT_BODY="$(api POST /schedule/draft <(printf '%s' "{\"from\":\"${WEEK_START}\",\"to\":\"${WEEK_END}\"}"))"
BASE_VERSION="$(printf '%s' "$DRAFT_BODY" | jq -r '.base_version // empty')"
# Build the apply payload directly from the draft response (jq --argfile is not
# available in all jq builds).
APPLY_PAYLOAD="$(printf '%s' "$DRAFT_BODY" | jq '{draft:.draft, base_version:.base_version}')"
APPLY_BODY="$(api POST /schedule/draft/apply <(printf '%s' "$APPLY_PAYLOAD"))"
APPLIED="$(printf '%s' "$APPLY_BODY" | jq -r '.applied // false')"
[[ "$APPLIED" == "true" ]] || { fail "schedule apply failed: $APPLY_BODY"; exit 1; }
ok "schedule applied (version $(printf '%s' "$APPLY_BODY" | jq -r '.version'))"

# Discover the scheduled day from the user-visible schedule range view.
RANGE="$(api GET "/schedule?from=${WEEK_START}&to=${WEEK_END}")"
SCHEDULED_DAY="$(printf '%s' "$RANGE" | jq -r --arg t "Smoke task" '
    [.events[] | select((.task.title // "") == $t)] | .[0].assignment.date
    // ([.events[] | select((.task.title // "") == $t)] | .[0].assignment.start_at[:10])')"
[[ -n "$SCHEDULED_DAY" && "$SCHEDULED_DAY" != "null" ]] || { fail "task not found in schedule range"; exit 1; }
ok "task scheduled on ${SCHEDULED_DAY}"

# --- 9. Today --------------------------------------------------------------
step "today — user-visible day view for ${SCHEDULED_DAY}"
TODAY="$(api GET "/today?date=${SCHEDULED_DAY}")"
TODAY_TITLE="$(printf '%s' "$TODAY" | jq -r '.events[0].task.title // empty')"
if [[ "$TODAY_TITLE" != "Smoke task" ]]; then fail "today view missing the task: $TODAY"; exit 1; fi
ok "today shows scheduled task (conflict=$(printf '%s' "$TODAY" | jq -r '.events[0].conflict // false'))"

# --- 10. Backup ------------------------------------------------------------
step "backup — on-demand production backup"
$COMPOSE run --rm --entrypoint /bin/sh backup -c "apk add --no-cache bash >/dev/null 2>&1 && bash /backup/backup.sh" 2>&1 | tail -n 3
BACKUP_FILE="$($COMPOSE run --rm --entrypoint /bin/sh backup -c 'ls -1t /backups/kinevo-*.sql.gz 2>/dev/null | head -n1')"
BACKUP_FILE="$(printf '%s' "$BACKUP_FILE" | tr -d '\r')"
[[ -n "$BACKUP_FILE" ]] || { fail "no backup file produced"; exit 1; }
ok "backup created: ${BACKUP_FILE}"

# --- 11. Restore -----------------------------------------------------------
step "restore — destructive restore from the backup (CONFIRM_RESTORE=yes)"
$COMPOSE run --rm -e CONFIRM_RESTORE=yes -e "BACKUP_FILE=${BACKUP_FILE}" \
    backup sh -c "apk add --no-cache bash >/dev/null 2>&1 && bash /backup/restore.sh \"${BACKUP_FILE}\"" 2>&1 | tail -n 3
# Post-restore verification: the scheduled task must still be visible in Today.
if wait_for "${HEALTH_URL}" 200 30 2; then
    RESTORE_TODAY="$(api GET "/today?date=${SCHEDULED_DAY}")"
    RESTORE_TITLE="$(printf '%s' "$RESTORE_TODAY" | jq -r '.events[0].task.title // empty')"
    if [[ "$RESTORE_TITLE" != "Smoke task" ]]; then
        fail "after restore, today view is missing the task"; exit 1
    fi
    ok "restore verified — data intact after recovery"
else
    fail "health degraded after restore"; exit 1
fi

step "Production smoke test PASSED"
printf '%sbuild → deploy → migrate → health → login → goal → task → schedule → today → backup → restore%s\n' "$GREEN" "$NC"
