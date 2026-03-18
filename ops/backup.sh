#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

if [[ -f "${SCRIPT_DIR}/.env.deploy" ]]; then
  # shellcheck disable=SC1091
  source "${SCRIPT_DIR}/.env.deploy"
fi

: "${DEPLOY_HOST:?Set DEPLOY_HOST in ops/.env.deploy}"
: "${DEPLOY_USER:?Set DEPLOY_USER in ops/.env.deploy}"
: "${DEPLOY_PATH:?Set DEPLOY_PATH in ops/.env.deploy}"
: "${SKFO_DB_NAME:?Set SKFO_DB_NAME in ops/.env.deploy}"
: "${SKFO_DB_USER:?Set SKFO_DB_USER in ops/.env.deploy}"

DEPLOY_PORT="${DEPLOY_PORT:-22}"
BACKUP_KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
SSH_TARGET="${DEPLOY_USER}@${DEPLOY_HOST}"

DB_HOST="${SKFO_DB_HOST:-127.0.0.1}"
DB_PORT="${SKFO_DB_PORT:-3306}"
DB_NAME="${SKFO_DB_NAME}"
DB_USER="${SKFO_DB_USER}"
DB_PASS="${SKFO_DB_PASS:-}"

echo "==> Creating remote backup"
ssh -p "${DEPLOY_PORT}" "${SSH_TARGET}" \
  "DEPLOY_PATH=$(printf '%q' "${DEPLOY_PATH}") BACKUP_KEEP_DAYS=$(printf '%q' "${BACKUP_KEEP_DAYS}") DB_HOST=$(printf '%q' "${DB_HOST}") DB_PORT=$(printf '%q' "${DB_PORT}") DB_NAME=$(printf '%q' "${DB_NAME}") DB_USER=$(printf '%q' "${DB_USER}") DB_PASS=$(printf '%q' "${DB_PASS}") bash -s" << 'EOF_REMOTE'
set -Eeuo pipefail

deploy_path="${DEPLOY_PATH}"
backup_dir="$deploy_path/shared/backups"
keep_days="${BACKUP_KEEP_DAYS}"
timestamp="$(date -u +%Y%m%d%H%M%S)"

mkdir -p "$backup_dir"

db_dump_file="$backup_dir/db-$timestamp.sql.gz"
if [[ -n "$DB_PASS" ]]; then
  MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --quick --lock-tables=false --no-tablespaces -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip -9 > "$db_dump_file"
else
  mysqldump --single-transaction --quick --lock-tables=false --no-tablespaces -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip -9 > "$db_dump_file"
fi

files_dir="$deploy_path/shared/site-assets-files"
if [[ -d "$files_dir" ]]; then
  tar -C "$deploy_path/shared" -czf "$backup_dir/files-$timestamp.tar.gz" "site-assets-files"
fi

find "$backup_dir" -type f \( -name '*.sql.gz' -o -name '*.tar.gz' \) -mtime +"$keep_days" -delete || true

echo "Database backup: $db_dump_file"
if [[ -d "$files_dir" ]]; then
  echo "Files backup: $backup_dir/files-$timestamp.tar.gz"
fi
EOF_REMOTE
