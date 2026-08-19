#!/usr/bin/env bash
set -euo pipefail

# Kinevo database restore (SRS §16.4, NFR-05, TASK-082).
# Restores the canonical PostgreSQL store from a backup file. This DESTROYS
# current data — it is an explicit, guarded disaster-recovery operation.
#
# Usage:
#   scripts/restore.sh [BACKUP_FILE]     (default: newest local backup)
#
# Env:
#   DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD (from deployment env)
#   BACKUP_DIR        local backup dir (default ./storage/backups)
#   CONFIRM_RESTORE   must equal "yes" to proceed (safety guard)

BACKUP_DIR="${BACKUP_DIR:-./storage/backups}"

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-kinevo}"
DB_USERNAME="${DB_USERNAME:-kinevo}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD is required}"

BACKUP_FILE="${1:-}"
if [[ -z "$BACKUP_FILE" ]]; then
    BACKUP_FILE="$(ls -1t "$BACKUP_DIR"/kinevo-*.sql.gz 2>/dev/null | head -n1 || true)"
fi

if [[ -z "$BACKUP_FILE" || ! -f "$BACKUP_FILE" ]]; then
    echo "[restore] ERROR: backup file not found: ${BACKUP_FILE}" >&2
    exit 1
fi

# Validate the DB identifier to prevent injection through env-supplied values.
if [[ ! "$DB_DATABASE" =~ ^[a-zA-Z_][a-zA-Z0-9_]*$ ]]; then
    echo "[restore] ERROR: invalid DB_DATABASE identifier." >&2
    exit 1
fi

echo "[restore] this DESTROYS current ${DB_DATABASE} data and replaces it from:"
echo "          ${BACKUP_FILE}"
if [[ "${CONFIRM_RESTORE:-}" != "yes" ]]; then
    echo "[restore] ABORT: set CONFIRM_RESTORE=yes to proceed." >&2
    exit 1
fi

# Terminate active connections then recreate the database. Shell-expanded
# identifiers (validated above) avoid psql -c variable-substitution quirks.
echo "[restore] terminating active connections to ${DB_DATABASE}"
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d postgres <<SQL
SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_DATABASE' AND pid <> pg_backend_pid();
DROP DATABASE IF EXISTS "$DB_DATABASE";
CREATE DATABASE "$DB_DATABASE" OWNER "$DB_USERNAME";
SQL

echo "[restore] applying backup"
gzip -dc "$BACKUP_FILE" | PGPASSWORD="$DB_PASSWORD" psql \
    -h "$DB_HOST" \
    -p "$DB_PORT" \
    -U "$DB_USERNAME" \
    -d "$DB_DATABASE" \
    -v ON_ERROR_STOP=1

echo "[restore] done"
