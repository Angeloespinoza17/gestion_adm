#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

: "${DEPLOY_HOST:?Falta DEPLOY_HOST}"
: "${DEPLOY_USER:?Falta DEPLOY_USER}"
: "${DEPLOY_REMOTE_PATH:?Falta DEPLOY_REMOTE_PATH}"

DEPLOY_PORT="${DEPLOY_PORT:-22}"
DEPLOY_PHP_BIN="${DEPLOY_PHP_BIN:-php}"
DEPLOY_COMPOSER_BIN="${DEPLOY_COMPOSER_BIN:-composer}"
DEPLOY_REMOTE_OWNER="${DEPLOY_REMOTE_OWNER:-}"
DEPLOY_SSH_KEY="${DEPLOY_SSH_KEY:-}"

SSH_OPTIONS=(-p "${DEPLOY_PORT}" -o BatchMode=yes -o ConnectTimeout=15)

if [ -n "${DEPLOY_SSH_KEY}" ]; then
  SSH_OPTIONS+=(-i "${DEPLOY_SSH_KEY}" -o IdentitiesOnly=yes)
fi

SSH_COMMAND=(ssh "${SSH_OPTIONS[@]}")
RSYNC_SSH="ssh -p ${DEPLOY_PORT} -o BatchMode=yes -o ConnectTimeout=15"

if [ -n "${DEPLOY_SSH_KEY}" ]; then
  RSYNC_SSH="${RSYNC_SSH} -i ${DEPLOY_SSH_KEY} -o IdentitiesOnly=yes"
fi
REMOTE="${DEPLOY_USER}@${DEPLOY_HOST}"

cd "${ROOT_DIR}"

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  DIRTY_FILES="$(git status --porcelain --untracked-files=all)"

  if [ -n "${DIRTY_FILES}" ]; then
    echo "El deploy fue cancelado: el árbol de trabajo contiene cambios sin confirmar." >&2
    echo "Cree un commit o genere un artefacto limpio antes de desplegar." >&2
    exit 1
  fi
fi

echo "==> Build local"
npm run prod

echo "==> Enviando archivos a ${REMOTE}:${DEPLOY_REMOTE_PATH}"
rsync -az --delete \
  -e "${RSYNC_SSH}" \
  --exclude='.env' \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='storage' \
  "${ROOT_DIR}/" "${REMOTE}:${DEPLOY_REMOTE_PATH}/"

echo "==> Instalando dependencias y optimizando Laravel"
"${SSH_COMMAND[@]}" "${REMOTE}" "cd '${DEPLOY_REMOTE_PATH}' && \
${DEPLOY_COMPOSER_BIN} install --no-dev --optimize-autoloader && \
${DEPLOY_PHP_BIN} artisan config:clear && \
${DEPLOY_PHP_BIN} artisan env --no-ansi | grep -Eq 'environment([[:space:]]+is|:)[[:space:]]*\[?production\]?[[:space:].]*$' && \
${DEPLOY_PHP_BIN} artisan migrate:status --no-ansi && \
${DEPLOY_PHP_BIN} artisan backup:database --no-prune && \
${DEPLOY_PHP_BIN} artisan migrate --force --no-interaction && \
${DEPLOY_PHP_BIN} artisan route:clear && \
${DEPLOY_PHP_BIN} artisan view:clear && \
${DEPLOY_PHP_BIN} artisan config:cache && \
${DEPLOY_PHP_BIN} artisan route:cache && \
${DEPLOY_PHP_BIN} artisan view:cache && \
if [ -f public/build/.vite/manifest.json ]; then cp public/build/.vite/manifest.json public/build/manifest.json; fi && \
chmod -R 775 storage bootstrap/cache"

if [ -n "${DEPLOY_REMOTE_OWNER}" ]; then
  echo "==> Ajustando permisos"
  "${SSH_COMMAND[@]}" "${REMOTE}" "chown -R '${DEPLOY_REMOTE_OWNER}' '${DEPLOY_REMOTE_PATH}'"
fi

echo "==> Deploy completado"
