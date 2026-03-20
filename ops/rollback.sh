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

DEPLOY_PORT="${DEPLOY_PORT:-22}"
TARGET_RELEASE="${1:-}"
SSH_TARGET="${DEPLOY_USER}@${DEPLOY_HOST}"

if [[ -n "${TARGET_RELEASE}" ]]; then
  echo "==> Rolling back to release ${TARGET_RELEASE}"
else
  echo "==> Rolling back to previous release"
fi

ssh -p "${DEPLOY_PORT}" "${SSH_TARGET}" \
  "DEPLOY_PATH=$(printf '%q' "${DEPLOY_PATH}") TARGET_RELEASE=$(printf '%q' "${TARGET_RELEASE}") bash -s" << 'EOF_REMOTE'
set -Eeuo pipefail

deploy_path="${DEPLOY_PATH}"
target_release="${TARGET_RELEASE:-}"
releases_path="$deploy_path/releases"
current_path="$deploy_path/current"
current_tmp_path="${current_path}.next"
lock_file="$deploy_path/.deploy.lock"

exec 9>"$lock_file"
if ! flock -n 9; then
  echo "ERROR: another deploy or rollback is already running"
  exit 1
fi

cleanup() {
  rm -f "$current_tmp_path"
}
trap cleanup EXIT

if [[ ! -d "$releases_path" ]]; then
  echo "ERROR: releases path does not exist: $releases_path"
  exit 1
fi

mapfile -t releases < <(ls -1dt "$releases_path"/* 2>/dev/null || true)
if ((${#releases[@]} == 0)); then
  echo "ERROR: no releases found"
  exit 1
fi

if [[ -z "$target_release" ]]; then
  if ((${#releases[@]} < 2)); then
    echo "ERROR: no previous release available"
    exit 1
  fi
  target_path="${releases[1]}"
else
  target_path="$releases_path/$target_release"
  if [[ ! -d "$target_path" ]]; then
    echo "ERROR: release not found: $target_path"
    exit 1
  fi
fi

if [[ ! -f "$target_path/public/index.php" ]]; then
  echo "ERROR: target release is missing public/index.php: $target_path"
  exit 1
fi

rm -f "$current_tmp_path"
ln -s "$target_path" "$current_tmp_path"
mv -Tf "$current_tmp_path" "$current_path"
echo "Rolled back to: $(basename "$target_path")"
EOF_REMOTE
