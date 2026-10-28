#!/usr/bin/env bash
# One shared throwaway PostgreSQL server for the test suite.
# Usage: scripts/dev-postgres.sh start|stop|env      (prints `export KINEVO_TEST_DATABASE_URL=...` for `env`)
# Exit codes: 0 ok, 3 environment-limited (no Docker access and no local PostgreSQL binaries).
set -euo pipefail
STATE_DIR="${KINEVO_DEV_PG_DIR:-${TMPDIR:-/tmp}/kinevo-dev-pg}"
NAME=kinevo-dev-pg
PW_FILE="$STATE_DIR/password"
PORT_FILE="$STATE_DIR/port"
MODE_FILE="$STATE_DIR/mode"
VERSION="${KINEVO_TEST_PG_VERSION:-17}"

have_docker() { command -v docker >/dev/null && timeout 20 docker info >/dev/null 2>&1; }
pg_bin() { # find a directory holding initdb and pg_ctl
  for d in /usr/lib/postgresql/*/bin /usr/lib/postgresql*/bin /usr/local/pgsql/bin /opt/homebrew/opt/postgresql@*/bin; do
    [ -x "$d/initdb" ] && [ -x "$d/pg_ctl" ] && { echo "$d"; return 0; }
  done
  command -v initdb >/dev/null && command -v pg_ctl >/dev/null && { dirname "$(command -v initdb)"; return 0; }
  return 1
}
free_port() { python3 - <<'PY'
import socket
s = socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1]); s.close()
PY
}

start() {
  mkdir -p "$STATE_DIR"; chmod 700 "$STATE_DIR"
  if [ -f "$MODE_FILE" ]; then echo "already started ($(cat "$MODE_FILE"))" >&2; return 0; fi
  [ -f "$PW_FILE" ] || { head -c 18 /dev/urandom | base64 | tr -d '/+=' > "$PW_FILE"; chmod 600 "$PW_FILE"; }
  local port; port="$(free_port)"; echo "$port" > "$PORT_FILE"
  if have_docker; then
    if docker image inspect "postgres:${VERSION}-alpine" >/dev/null 2>&1; then
      if docker container inspect "$NAME" >/dev/null 2>&1; then
        echo "environment-limited: container $NAME already exists without a script state file; refusing to replace it" >&2
        exit 3
      fi
      docker run --pull=never -d --name "$NAME" -e POSTGRES_PASSWORD="$(cat "$PW_FILE")" -p "127.0.0.1:${port}:5432" "postgres:${VERSION}-alpine" >/dev/null
      echo docker > "$MODE_FILE"
      for _ in $(seq 1 60); do docker exec "$NAME" pg_isready -U postgres >/dev/null 2>&1 && return 0; sleep 1; done
      echo "postgres container did not become ready" >&2; exit 1
    fi
  fi
  if bin="$(pg_bin)"; then
    local data="$STATE_DIR/data"; rm -rf "$data"
    "$bin/initdb" -D "$data" -U postgres --auth=scram-sha-256 --pwfile="$PW_FILE" >/dev/null
    "$bin/pg_ctl" -D "$data" -o "-p $port -c listen_addresses=127.0.0.1 -c unix_socket_directories=$STATE_DIR" -l "$STATE_DIR/pg.log" -w start >/dev/null
    echo local > "$MODE_FILE"; return 0
  fi
  rm -f "$PORT_FILE"
  echo "environment-limited: no existing postgres:${VERSION}-alpine image and no local PostgreSQL binaries (automatic image pulls are disabled)" >&2
  exit 3
}

stop() {
  [ -f "$MODE_FILE" ] || exit 0
  case "$(cat "$MODE_FILE")" in
    docker) docker rm -f "$NAME" >/dev/null 2>&1 || true ;;
    local) if bin="$(pg_bin)"; then "$bin/pg_ctl" -D "$STATE_DIR/data" -m fast stop >/dev/null 2>&1 || true; fi ;;
  esac
  rm -f "$MODE_FILE" "$PORT_FILE"
}

env_out() {
  [ -f "$MODE_FILE" ] || start
  printf 'export KINEVO_TEST_DATABASE_URL=%q\n' "postgres://postgres:$(cat "$PW_FILE")@127.0.0.1:$(cat "$PORT_FILE")/postgres"
}

case "${1:-}" in
  start) start ;;
  stop) stop ;;
  env) env_out ;;
  *) echo "usage: $0 start|stop|env" >&2; exit 64 ;;
esac
