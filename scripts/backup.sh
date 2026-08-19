#!/usr/bin/env bash
set -euo pipefail

# Kinevo database backup (SRS §16.4, NFR-05, TASK-082).
# - dumps the canonical PostgreSQL store to a timestamped file;
# - keeps N local backups (retention);
# - optionally copies to a remote S3-compatible destination.
#
# Usage:
#   scripts/backup.sh [--keep N] [--remote-bucket s3://bucket/prefix]
#
# Env:
#   DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD (from deployment env)
#   BACKUP_DIR          local backup dir (default ./storage/backups)
#   BACKUP_KEEP         number of local backups to retain (default 7)
#   AWS_ENDPOINT_URL    S3-compatible endpoint (optional, e.g. Oracle/Cloudflare R2)
#   AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / AWS_DEFAULT_REGION / AWS_BUCKET

BACKUP_DIR="${BACKUP_DIR:-./storage/backups}"
BACKUP_KEEP="${BACKUP_KEEP:-7}"
REMOTE_BUCKET="${REMOTE_BUCKET:-}"

# --- Parse args -------------------------------------------------------------
while [[ $# -gt 0 ]]; do
    case "$1" in
        --keep) BACKUP_KEEP="$2"; shift 2 ;;
        --remote-bucket) REMOTE_BUCKET="$2"; shift 2 ;;
        *) echo "unknown option: $1" >&2; exit 2 ;;
    esac
done

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-kinevo}"
DB_USERNAME="${DB_USERNAME:-kinevo}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD is required}"

mkdir -p "$BACKUP_DIR"

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUTFILE="$BACKUP_DIR/kinevo-${DB_DATABASE}-${TIMESTAMP}.sql.gz"

echo "[backup] dumping ${DB_DATABASE} -> ${OUTFILE}"

PGPASSWORD="$DB_PASSWORD" pg_dump \
    -h "$DB_HOST" \
    -p "$DB_PORT" \
    -U "$DB_USERNAME" \
    -d "$DB_DATABASE" \
    --no-owner \
    --no-privileges \
    --format=plain \
    | gzip -9 > "$OUTFILE"

SIZE="$(du -h "$OUTFILE" | cut -f1)"
echo "[backup] ok (${SIZE})"

# --- Retention --------------------------------------------------------------
echo "[backup] pruning local backups to ${BACKUP_KEEP}"
ls -1t "$BACKUP_DIR"/kinevo-*.sql.gz 2>/dev/null | tail -n +$((BACKUP_KEEP + 1)) | xargs -r rm -f

# --- Optional remote copy ---------------------------------------------------
if [[ -n "$REMOTE_BUCKET" ]]; then
    echo "[backup] copying to ${REMOTE_BUCKET}"
    if command -v aws >/dev/null 2>&1; then
        aws s3 cp "$OUTFILE" "$REMOTE_BUCKET/" \
            --endpoint-url "${AWS_ENDPOINT_URL:-}"
    elif command -v mc >/dev/null 2>&1; then
        mc cp "$OUTFILE" "$REMOTE_BUCKET/"
    else
        echo "[backup] WARN: no aws/mc client; skipping remote copy" >&2
    fi
fi

echo "[backup] done"
