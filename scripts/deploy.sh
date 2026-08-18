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
DEPLOY_RBAC_RECONCILE="${DEPLOY_RBAC_RECONCILE:-false}"

if [[ "${DEPLOY_RBAC_RECONCILE}" != "true" && "${DEPLOY_RBAC_RECONCILE}" != "false" ]]; then
  echo "DEPLOY_RBAC_RECONCILE debe ser true o false." >&2
  exit 1
fi

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

restore_remote_document_root_access() {
  local remote_command

  remote_command="install -d -m 755 '${DEPLOY_REMOTE_PATH}' && chmod 755 '${DEPLOY_REMOTE_PATH}'"
  remote_command+=" && if [ -d '${DEPLOY_REMOTE_PATH}/public' ]; then chmod 755 '${DEPLOY_REMOTE_PATH}/public'; fi"

  if [ -n "${DEPLOY_REMOTE_OWNER}" ]; then
    remote_command+=" && chown '${DEPLOY_REMOTE_OWNER}' '${DEPLOY_REMOTE_PATH}'"
    remote_command+=" && if [ -d '${DEPLOY_REMOTE_PATH}/public' ]; then chown '${DEPLOY_REMOTE_OWNER}' '${DEPLOY_REMOTE_PATH}/public'; fi"
  fi

  "${SSH_COMMAND[@]}" "${REMOTE}" "${remote_command}"
}

restore_access_on_failure() {
  local deploy_status=$?

  trap - EXIT

  if [ "${deploy_status}" -ne 0 ]; then
    echo "==> El deploy falló; restaurando acceso de Apache al document root" >&2
    restore_remote_document_root_access || true
  fi

  exit "${deploy_status}"
}

trap restore_access_on_failure EXIT

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

echo "==> Verificando acceso al document root"
restore_remote_document_root_access

echo "==> Enviando archivos a ${REMOTE}:${DEPLOY_REMOTE_PATH}"
rsync -az --delete --no-owner --no-group --chmod='Du=rwx,Dgo=rx' \
  -e "${RSYNC_SSH}" \
  --exclude='.env' \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='storage' \
  --exclude='output' \
  --exclude='outputs' \
  --exclude='public/hot' \
  "${ROOT_DIR}/" "${REMOTE}:${DEPLOY_REMOTE_PATH}/"

# rsync puede interrumpirse antes de restaurar los atributos del directorio
# raíz. Se corrige inmediatamente para que Apache nunca pierda acceso al sitio.
restore_remote_document_root_access

echo "==> Instalando dependencias y optimizando Laravel"
"${SSH_COMMAND[@]}" "${REMOTE}" "export HOME=\"\$(getent passwd \$(id -u) | cut -d: -f6)\" && \
chmod 755 '${DEPLOY_REMOTE_PATH}' && \
cd '${DEPLOY_REMOTE_PATH}' && \
rm -f public/hot && \
${DEPLOY_COMPOSER_BIN} install --no-dev --optimize-autoloader && \
if [ ! -L public/storage ]; then ${DEPLOY_PHP_BIN} artisan storage:link --no-ansi; fi && \
${DEPLOY_PHP_BIN} artisan config:clear && \
${DEPLOY_PHP_BIN} artisan env --no-ansi | grep -Eq 'environment([[:space:]]+is|:)[[:space:]]*\[?production\]?[[:space:].]*$' && \
${DEPLOY_PHP_BIN} artisan migrate:status --no-ansi && \
${DEPLOY_PHP_BIN} artisan backup:database --no-prune && \
${DEPLOY_PHP_BIN} artisan migrate --force --no-interaction && \
if [ '${DEPLOY_RBAC_RECONCILE}' = 'true' ]; then \
  ${DEPLOY_PHP_BIN} artisan rbac:reconcile --no-ansi && \
  ${DEPLOY_PHP_BIN} artisan rbac:reconcile --apply --no-ansi; \
fi && \
${DEPLOY_PHP_BIN} artisan rbac:audit --strict --no-ansi && \
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

trap - EXIT
echo "==> Deploy completado"
