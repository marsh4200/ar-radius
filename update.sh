#!/usr/bin/env bash
# ==============================================================================
# AR Radius - Update Script
# Pulls latest code from GitHub and applies migrations / restarts services.
# Triggered from the web GUI ("Check for Updates" button) via sudo.
# ==============================================================================
set -euo pipefail

INSTALL_DIR="/opt/ar-radius"
WEB_DIR="${INSTALL_DIR}/web"
LOG_FILE="/var/log/ar-radius-update.log"
BRANCH="main"

ts() { date '+%Y-%m-%d %H:%M:%S'; }
log() { echo "[$(ts)] $*" | tee -a "${LOG_FILE}"; }

log "=========================================="
log "AR Radius Update started"
log "=========================================="

if [[ ! -d "${INSTALL_DIR}/.git" ]]; then
    log "ERROR: ${INSTALL_DIR} is not a git repo. Cannot self-update."
    exit 2
fi

cd "${INSTALL_DIR}"

# Preserve local config
CONFIG_BACKUP=""
if [[ -f "${WEB_DIR}/includes/config.php" ]]; then
    CONFIG_BACKUP=$(mktemp)
    cp "${WEB_DIR}/includes/config.php" "${CONFIG_BACKUP}"
    log "Backed up config.php"
fi

OLD_VERSION=$(cat "${INSTALL_DIR}/VERSION" 2>/dev/null || echo "unknown")
log "Current version: ${OLD_VERSION}"

log "Fetching latest from GitHub..."
git fetch --all --tags --quiet 2>>"${LOG_FILE}" || {
    log "ERROR: git fetch failed"
    exit 3
}

log "Resetting to origin/${BRANCH}..."
git reset --hard "origin/${BRANCH}" --quiet 2>>"${LOG_FILE}" || {
    log "ERROR: git reset failed"
    exit 4
}

NEW_VERSION=$(cat "${INSTALL_DIR}/VERSION" 2>/dev/null || echo "unknown")
log "New version: ${NEW_VERSION}"

# Restore config
if [[ -n "${CONFIG_BACKUP}" ]]; then
    cp "${CONFIG_BACKUP}" "${WEB_DIR}/includes/config.php"
    rm -f "${CONFIG_BACKUP}"
    chown root:www-data "${WEB_DIR}/includes/config.php"
    chmod 640 "${WEB_DIR}/includes/config.php"
    log "Restored config.php"
fi

# Apply any new SQL migrations (idempotent)
if [[ -f "${INSTALL_DIR}/sql/schema.sql" && -f "${WEB_DIR}/includes/config.php" ]]; then
    log "Re-applying schema (CREATE TABLE IF NOT EXISTS is idempotent)..."
    DB_HOST=$(php -r '$c=include "'"${WEB_DIR}"'/includes/config.php"; echo $c["db"]["host"];')
    DB_NAME=$(php -r '$c=include "'"${WEB_DIR}"'/includes/config.php"; echo $c["db"]["name"];')
    DB_USER=$(php -r '$c=include "'"${WEB_DIR}"'/includes/config.php"; echo $c["db"]["user"];')
    DB_PASS=$(php -r '$c=include "'"${WEB_DIR}"'/includes/config.php"; echo $c["db"]["pass"];')
    mysql -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        < "${INSTALL_DIR}/sql/schema.sql" 2>>"${LOG_FILE}" || \
        log "WARN: schema re-apply produced warnings"
fi

# Fix permissions
chown -R www-data:www-data "${WEB_DIR}"
find "${WEB_DIR}" -type d -exec chmod 755 {} \;
find "${WEB_DIR}" -type f -exec chmod 644 {} \;
chown root:www-data "${WEB_DIR}/includes/config.php"
chmod 640 "${WEB_DIR}/includes/config.php"
chmod +x "${INSTALL_DIR}/update.sh" "${INSTALL_DIR}/install.sh"

log "Reloading Apache..."
systemctl reload apache2 2>>"${LOG_FILE}" || systemctl restart apache2 2>>"${LOG_FILE}" || true

log "Update finished. ${OLD_VERSION} -> ${NEW_VERSION}"
log "=========================================="
echo "OK: ${OLD_VERSION} -> ${NEW_VERSION}"
exit 0
