#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"

if [[ -f "${SCRIPT_DIR}/.env.deploy" ]]; then
  # shellcheck disable=SC1091
  source "${SCRIPT_DIR}/.env.deploy"
fi

: "${DEPLOY_HOST:?Set DEPLOY_HOST in ops/.env.deploy}"
: "${DEPLOY_USER:?Set DEPLOY_USER in ops/.env.deploy}"
: "${DEPLOY_PATH:?Set DEPLOY_PATH in ops/.env.deploy}"

DEPLOY_PORT="${DEPLOY_PORT:-22}"
DEPLOY_KEEP_RELEASES="${DEPLOY_KEEP_RELEASES:-5}"
DEPLOY_COMPOSER_BIN="${DEPLOY_COMPOSER_BIN:-composer}"
SKIP_COMPOSER="${SKIP_COMPOSER:-0}"
RSYNC_PROGRESS="${RSYNC_PROGRESS:-1}"
DEPLOY_WEB_USER="${DEPLOY_WEB_USER:-www-data}"
DEPLOY_WEB_GROUP="${DEPLOY_WEB_GROUP:-www-data}"

RELEASE_ID="$(date -u +"%Y%m%d%H%M%S")"
REMOTE_RELEASES_PATH="${DEPLOY_PATH}/releases"
REMOTE_RELEASE_PATH="${REMOTE_RELEASES_PATH}/${RELEASE_ID}"
REMOTE_SHARED_PATH="${DEPLOY_PATH}/shared"
REMOTE_CURRENT_PATH="${DEPLOY_PATH}/current"

SSH_TARGET="${DEPLOY_USER}@${DEPLOY_HOST}"
SSH_OPTS=(-p "${DEPLOY_PORT}")

echo "==> Preparing remote directories"
ssh "${SSH_OPTS[@]}" "${SSH_TARGET}" "mkdir -p '${REMOTE_RELEASE_PATH}' '${REMOTE_SHARED_PATH}/site-assets-files' '${REMOTE_SHARED_PATH}/site-assets-sessions' '${REMOTE_SHARED_PATH}/site-assets-logs'"

echo "==> Uploading release ${RELEASE_ID}"
RSYNC_OPTS=(-az --delete)
if [[ "${RSYNC_PROGRESS}" == "1" ]]; then
  RSYNC_OPTS+=(--info=progress2,stats)
fi

rsync "${RSYNC_OPTS[@]}" \
  --chown=root:${DEPLOY_WEB_GROUP} \
  --exclude '.git/' \
  --exclude '.ddev/' \
  --exclude '.vscode/' \
  --exclude '_import/' \
  --exclude '*.tar.gz' \
  --exclude '*.sql.gz' \
  --exclude 'ops/.env.deploy' \
  --exclude 'public/site/assets/files/' \
  --exclude 'public/site/assets/sessions/' \
  --exclude 'public/site/assets/cache/' \
  --exclude 'public/site/assets/logs/' \
  -e "ssh -p ${DEPLOY_PORT}" \
  "${PROJECT_ROOT}/" "${SSH_TARGET}:${REMOTE_RELEASE_PATH}/"

echo "==> Finalizing release on server"
ssh "${SSH_OPTS[@]}" "${SSH_TARGET}" \
  "DEPLOY_PATH=$(printf '%q' "${DEPLOY_PATH}") RELEASE_PATH=$(printf '%q' "${REMOTE_RELEASE_PATH}") RELEASES_PATH=$(printf '%q' "${REMOTE_RELEASES_PATH}") SHARED_PATH=$(printf '%q' "${REMOTE_SHARED_PATH}") CURRENT_PATH=$(printf '%q' "${REMOTE_CURRENT_PATH}") KEEP_RELEASES=$(printf '%q' "${DEPLOY_KEEP_RELEASES}") COMPOSER_BIN=$(printf '%q' "${DEPLOY_COMPOSER_BIN}") SKIP_COMPOSER=$(printf '%q' "${SKIP_COMPOSER}") WEB_USER=$(printf '%q' "${DEPLOY_WEB_USER}") WEB_GROUP=$(printf '%q' "${DEPLOY_WEB_GROUP}") bash -s" << 'EOF_REMOTE'
set -Eeuo pipefail

LOCK_FILE="$DEPLOY_PATH/.deploy.lock"
CURRENT_TMP_PATH="${CURRENT_PATH}.next"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "ERROR: another deploy or rollback is already running"
  exit 1
fi

cleanup() {
  rm -f "$CURRENT_TMP_PATH"
}
trap cleanup EXIT

mkdir -p "$RELEASE_PATH/public/site/assets/cache" \
  "$SHARED_PATH/site-assets-files" \
  "$SHARED_PATH/site-assets-sessions" \
  "$SHARED_PATH/site-assets-logs"

rm -rf "$RELEASE_PATH/public/site/assets/files" "$RELEASE_PATH/public/site/assets/sessions" "$RELEASE_PATH/public/site/assets/logs"
ln -sfn "$SHARED_PATH/site-assets-files" "$RELEASE_PATH/public/site/assets/files"
ln -sfn "$SHARED_PATH/site-assets-sessions" "$RELEASE_PATH/public/site/assets/sessions"
ln -sfn "$SHARED_PATH/site-assets-logs" "$RELEASE_PATH/public/site/assets/logs"

chmod -R ug+rwX "$RELEASE_PATH/public/site/assets/cache" "$SHARED_PATH/site-assets-files" "$SHARED_PATH/site-assets-sessions" "$SHARED_PATH/site-assets-logs" || true
chown -R "$WEB_USER:$WEB_GROUP" "$RELEASE_PATH/public/site/assets/cache" "$SHARED_PATH/site-assets-files" "$SHARED_PATH/site-assets-sessions" "$SHARED_PATH/site-assets-logs" || true
find "$RELEASE_PATH/public/site/assets/cache" "$SHARED_PATH/site-assets-files" "$SHARED_PATH/site-assets-sessions" "$SHARED_PATH/site-assets-logs" -type d -exec chmod g+s {} + 2>/dev/null || true

if [[ "$SKIP_COMPOSER" != "1" && -f "$RELEASE_PATH/public/composer.json" ]]; then
  if command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
    (cd "$RELEASE_PATH/public" && "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader)
  else
    echo "WARN: composer not found, skipping composer install"
  fi
fi

if [[ ! -f "$RELEASE_PATH/public/index.php" ]]; then
  echo "ERROR: release is missing public/index.php: $RELEASE_PATH"
  exit 1
fi

if [[ ! -f "$RELEASE_PATH/public/site/config.php" ]]; then
  echo "ERROR: release is missing public/site/config.php: $RELEASE_PATH"
  exit 1
fi

rm -f "$CURRENT_TMP_PATH"
ln -s "$RELEASE_PATH" "$CURRENT_TMP_PATH"
mv -Tf "$CURRENT_TMP_PATH" "$CURRENT_PATH"

active_release_path="$(readlink -f "$CURRENT_PATH")"
if [[ -z "$active_release_path" || ! -d "$active_release_path" ]]; then
  echo "ERROR: failed to resolve active release after switch"
  exit 1
fi

if [[ -d "$RELEASES_PATH" ]]; then
  keep_count=0
  while IFS= read -r release_path; do
    [[ -n "$release_path" ]] || continue

    if [[ "$release_path" == "$active_release_path" ]]; then
      continue
    fi

    if ((keep_count < KEEP_RELEASES)); then
      keep_count=$((keep_count + 1))
      continue
    fi

    rm -rf "$release_path"
  done < <(find "$RELEASES_PATH" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -nr | awk '{print $2}')
fi
EOF_REMOTE

echo "==> Deploy complete"
echo "Release: ${RELEASE_ID}"
echo "Current: ${REMOTE_CURRENT_PATH}"
