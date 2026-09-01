#!/usr/bin/env bash
# Migrate legacy MariaDB panel database (lacmp_panel) to SQLite panel.sqlite.
# Site/application databases on MariaDB are not modified.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LIB="${ROOT}/deploy/lib"

PREFIX="${PREFIX:-/usr/local/lib/azerioid-panel}"
PANEL_PHP_VERSION="${PANEL_PHP_VERSION:-8.4}"
PANEL_DB_PATH="/var/lib/azerioid-panel/panel.sqlite"
MARKER="/var/lib/azerioid-panel/panel-db-migrated.json"
ENV_FILE="${PREFIX}/web/.env"
DRY_RUN=0
FORCE=0

usage() {
    cat <<'EOF'
Usage: sudo ./deploy/migrate.sh [options]

Copy panel state from legacy MariaDB (lacmp_panel) into SQLite. Run after
upgrading an existing LACMP Panel host and adopting MariaDB from Components.

  --prefix=<dir>   panel install prefix (default /usr/local/lib/azerioid-panel)
  --dry-run        detect + count rows only
  --force          re-run even if migration marker exists
  -h, --help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --prefix=*) PREFIX="${1#*=}"; ENV_FILE="${PREFIX}/web/.env"; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        --force) FORCE=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
    esac
done

[[ ${EUID} -eq 0 ]] || { echo "migrate.sh must run as root" >&2; exit 1; }
[[ -f "${ENV_FILE}" ]] || { echo "Panel .env not found at ${ENV_FILE}" >&2; exit 1; }

# shellcheck source=lib/common.sh
source "${LIB}/common.sh"
# shellcheck source=lib/panel-db.sh
source "${LIB}/panel-db.sh"

WEB_USER="$(detect_web_user)"
export PREFIX WEB_USER PANEL_PHP_VERSION ROOT
PHP_BIN="$(php_bin)"

read_env() {
    local key="$1" file="$2" line val
    line="$(grep -E "^${key}=" "${file}" 2>/dev/null | tail -1 || true)"
    [[ -n "${line}" ]] || return 1
    val="${line#*=}"
    val="${val%\"}"; val="${val#\"}"
    val="${val%\'}"; val="${val#\'}"
    printf '%s' "${val}"
}

if [[ -f "${MARKER}" && "${FORCE}" -eq 0 ]]; then
    migrated_at="$(python3 -c "import json; print(json.load(open('${MARKER}')).get('migrated_at',''))" 2>/dev/null || true)"
    die "Panel database already migrated (${migrated_at:-marker present}). Pass --force to re-run."
fi

LEGACY_DRIVER="$(read_env DB_CONNECTION "${ENV_FILE}" 2>/dev/null || echo mysql)"
LEGACY_HOST="$(read_env DB_HOST "${ENV_FILE}" 2>/dev/null || echo 127.0.0.1)"
LEGACY_PORT="$(read_env DB_PORT "${ENV_FILE}" 2>/dev/null || echo 3306)"
LEGACY_SOCKET="$(read_env DB_SOCKET "${ENV_FILE}" 2>/dev/null || true)"
LEGACY_DATABASE="$(read_env DB_DATABASE "${ENV_FILE}" 2>/dev/null || echo lacmp_panel)"
LEGACY_USERNAME="$(read_env DB_USERNAME "${ENV_FILE}" 2>/dev/null || echo lacmp_panel)"
LEGACY_PASSWORD="$(read_env DB_PASSWORD "${ENV_FILE}" 2>/dev/null || echo "")"

if [[ "${LEGACY_DRIVER}" == "sqlite" ]]; then
    if [[ -f "${PANEL_DB_PATH}" ]] && sqlite3 "${PANEL_DB_PATH}" "SELECT COUNT(*) FROM users;" 2>/dev/null | grep -qv '^0$'; then
        die "Panel already uses SQLite with users present. Nothing to migrate."
    fi
    die "Panel .env already points at SQLite but no legacy MariaDB credentials were found."
fi

if ! command -v mariadb >/dev/null 2>&1 && ! command -v mysql >/dev/null 2>&1; then
    die "MariaDB/MySQL client not found on PATH."
fi

mysql_client() {
    if command -v mariadb >/dev/null 2>&1; then
        command -v mariadb
    else
        command -v mysql
    fi
}

MYSQL_BIN="$(mysql_client)"
MYSQL_ARGS=(-N -B)
if [[ -n "${LEGACY_SOCKET}" ]]; then
    MYSQL_ARGS+=(-S "${LEGACY_SOCKET}")
else
    MYSQL_ARGS+=(-h "${LEGACY_HOST}" -P "${LEGACY_PORT}")
fi
MYSQL_ARGS+=(-u "${LEGACY_USERNAME}")
if [[ -n "${LEGACY_PASSWORD}" ]]; then
    MYSQL_ARGS+=(-p"${LEGACY_PASSWORD}")
fi

if ! "${MYSQL_BIN}" "${MYSQL_ARGS[@]}" -e "USE \`${LEGACY_DATABASE}\`;" 2>/dev/null; then
    die "Legacy panel database '${LEGACY_DATABASE}' is not reachable with credentials from ${ENV_FILE}."
fi

USER_COUNT="$("${MYSQL_BIN}" "${MYSQL_ARGS[@]}" -e "SELECT COUNT(*) FROM \`${LEGACY_DATABASE}\`.users;" 2>/dev/null || echo 0)"
info "Legacy panel database ${LEGACY_DATABASE} has ${USER_COUNT} user(s)."

if [[ "${DRY_RUN}" -eq 1 ]]; then
    for table in users settings audit_logs jobs sessions; do
        count="$("${MYSQL_BIN}" "${MYSQL_ARGS[@]}" -e "SELECT COUNT(*) FROM \`${LEGACY_DATABASE}\`.\`${table}\`;" 2>/dev/null || echo 0)"
        printf '  %-24s %s\n' "${table}:" "${count}"
    done
    ok "Dry run complete (site databases on MariaDB are unchanged)."
    exit 0
fi

TS="$(date -u +%Y%m%dT%H%M%SZ)"
install -d -m 0750 -o root -g root /var/lib/azerioid-panel/staging
cp -a "${ENV_FILE}" "/var/lib/azerioid-panel/staging/.env.pre-migrate.${TS}"

if [[ "${FORCE}" -eq 1 ]]; then
    rm -f "${PANEL_DB_PATH}" "${PANEL_DB_PATH}-wal" "${PANEL_DB_PATH}-shm"
fi
if [[ -f "${PANEL_DB_PATH}" ]]; then
    cp -a "${PANEL_DB_PATH}" "/var/lib/azerioid-panel/staging/panel.sqlite.pre-migrate.${TS}"
fi

info "Stopping queue worker"
systemctl stop azerioid-panel-queue.service 2>/dev/null || true

info "Preparing SQLite panel database"
configure_panel_db
run_as_web "${PHP_BIN} artisan migrate --force --no-interaction"

info "Importing panel tables from MariaDB"
run_as_web "${PHP_BIN} artisan panel:import-from-mariadb \
    --driver=${LEGACY_DRIVER} \
    --host=${LEGACY_HOST} \
    --port=${LEGACY_PORT} \
    --database=${LEGACY_DATABASE} \
    --username=${LEGACY_USERNAME} \
    --password=${LEGACY_PASSWORD} \
    ${LEGACY_SOCKET:+--socket=${LEGACY_SOCKET}}"

python3 - "${MARKER}" "${LEGACY_DATABASE}" "${LEGACY_DRIVER}" <<'PY'
import json, pathlib, sys
from datetime import datetime, timezone
path = pathlib.Path(sys.argv[1])
payload = {
    "version": 1,
    "migrated_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "source": {
        "driver": sys.argv[3],
        "database": sys.argv[2],
    },
}
path.write_text(json.dumps(payload, indent=2) + "\n")
path.chmod(0o640)
PY
chown root:root "${MARKER}"

info "Restarting panel services"
systemctl restart azerioid-panel-queue.service 2>/dev/null || true
FPM_UNIT="$(fpm_unit)"
systemctl reload "${FPM_UNIT}.service" 2>/dev/null || systemctl restart "${FPM_UNIT}.service" 2>/dev/null || true

ok "Panel database migrated to ${PANEL_DB_PATH}"
echo "  Backup:    /var/lib/azerioid-panel/staging/.env.pre-migrate.${TS}"
echo "  Marker:    ${MARKER}"
echo "  Note:      Site databases on MariaDB are unchanged. Drop ${LEGACY_DATABASE} manually when ready."
