#!/usr/bin/env bash
# SQLite panel database at /var/lib/azerioid-panel/panel.sqlite
set -euo pipefail

PANEL_DB_PATH="/var/lib/azerioid-panel/panel.sqlite"

configure_panel_db() {
    echo "==> Configuring panel SQLite database"
    install -d -m 0750 -o "${WEB_USER}" -g "${WEB_USER}" /var/lib/azerioid-panel
    install -d -m 0750 -o root -g root /var/lib/azerioid-panel/staging

    if [[ ! -f "${PANEL_DB_PATH}" ]]; then
        install -m 0660 -o "${WEB_USER}" -g "${WEB_USER}" /dev/null "${PANEL_DB_PATH}"
        sqlite3 "${PANEL_DB_PATH}" "PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;"
        chown "${WEB_USER}:${WEB_USER}" "${PANEL_DB_PATH}"
        chmod 0660 "${PANEL_DB_PATH}"
    fi

    if [[ ! -f "${PREFIX}/web/.env" ]]; then
        cp "${ROOT}/web/.env.example" "${PREFIX}/web/.env"
    fi
    env_set "${PREFIX}/web/.env" DB_CONNECTION sqlite
    env_set "${PREFIX}/web/.env" DB_DATABASE "${PANEL_DB_PATH}"
    env_set "${PREFIX}/web/.env" BROKER_DRIVER sudo
    env_set "${PREFIX}/web/.env" BROKER_PATH "${PREFIX}/broker"
    env_set "${PREFIX}/web/.env" APP_ENV production
    env_set "${PREFIX}/web/.env" APP_DEBUG false
    env_set "${PREFIX}/web/.env" APP_URL "http://127.0.0.1:${PANEL_PORT}"
    env_set "${PREFIX}/web/.env" AZERIOID_WWW_ROOT "${WWW_ROOT:-/data/www}"
    env_set "${PREFIX}/web/.env" SESSION_SECURE_COOKIE false
    env_set "${PREFIX}/web/.env" PANEL_REQUIRE_TOTP "${REQUIRE_TOTP:-false}"
    env_set "${PREFIX}/web/.env" QUEUE_CONNECTION database
    chown "${WEB_USER}:${WEB_USER}" "${PREFIX}/web/.env"
    chmod 0640 "${PREFIX}/web/.env"
}
