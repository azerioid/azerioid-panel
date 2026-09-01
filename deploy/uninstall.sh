#!/usr/bin/env bash
# Stack Manager uninstall — reverses bootstrap install only.
# Never touches /data/www or user data.
set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
    echo "uninstall.sh must run as root" >&2
    exit 1
fi

PREFIX="${PREFIX:-/usr/local/lib/lacmp-panel}"
PANEL_PHP_VERSION="${PANEL_PHP_VERSION:-8.4}"
DROP_DB=0
REMOVE_BOOTSTRAP_PKGS=0

usage() {
    cat <<'EOF'
Usage: uninstall.sh [--drop-db] [--remove-bootstrap-pkgs]

  --drop-db                 Remove SQLite panel database
  --remove-bootstrap-pkgs   Remove Caddy/PHP if bootstrap.json says we installed them
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --drop-db) DROP_DB=1; shift ;;
        --remove-bootstrap-pkgs) REMOVE_BOOTSTRAP_PKGS=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
    esac
done

WEB_USER=""
if [[ -f /etc/lacmp-panel/runtime.json ]]; then
    WEB_USER="$(python3 -c 'import json; print(json.load(open("/etc/lacmp-panel/runtime.json")).get("web_user",""))' 2>/dev/null || true)"
fi
[[ -n "${WEB_USER}" ]] || WEB_USER=caddy

echo "==> Stopping queue worker"
systemctl stop lacmp-panel-queue 2>/dev/null || true
systemctl disable lacmp-panel-queue 2>/dev/null || true
rm -f /etc/systemd/system/lacmp-panel-queue.service
systemctl daemon-reload

echo "==> Removing panel artifacts"
rm -f /etc/sudoers.d/lacmp-panel
rm -f /etc/cron.d/lacmp-panel
rm -f /etc/caddy/conf.d/lacmp-panel.conf
rm -f /etc/fail2ban/filter.d/lacmp-panel.conf /etc/fail2ban/jail.d/lacmp-panel.conf
rm -f /etc/tmpfiles.d/lacmp-panel.conf

POOL_DIR=""
if [[ -d "/etc/php/${PANEL_PHP_VERSION}/fpm/pool.d" ]]; then
    POOL_DIR="/etc/php/${PANEL_PHP_VERSION}/fpm/pool.d"
elif [[ -d /etc/php-fpm.d ]]; then
    POOL_DIR=/etc/php-fpm.d
fi
[[ -n "${POOL_DIR}" ]] && rm -f "${POOL_DIR}/lacmp-panel.conf"

if command -v caddy >/dev/null 2>&1; then
    systemctl reload caddy 2>/dev/null || true
fi

FPM_UNIT="php${PANEL_PHP_VERSION}-fpm"
systemctl is-active --quiet "${FPM_UNIT}" 2>/dev/null && systemctl restart "${FPM_UNIT}" || true

if [[ "${DROP_DB}" -eq 1 ]]; then
    rm -f /var/lib/lacmp-panel/panel.sqlite /var/lib/lacmp-panel/panel.sqlite-wal /var/lib/lacmp-panel/panel.sqlite-shm
fi

if [[ -f /etc/lacmp-panel/web.env ]]; then
    install -d -m 0750 /etc/lacmp-panel
    cp -a "${PREFIX}/web/.env" /etc/lacmp-panel/web.env 2>/dev/null || true
fi

rm -rf "${PREFIX}"
rm -rf /var/log/lacmp-panel

if [[ "${REMOVE_BOOTSTRAP_PKGS}" -eq 1 && -f /etc/lacmp-panel/bootstrap.json ]]; then
    installed_caddy="$(python3 -c 'import json; print(json.load(open("/etc/lacmp-panel/bootstrap.json")).get("installed_caddy",0))' 2>/dev/null || echo 0)"
    installed_php="$(python3 -c 'import json; print(json.load(open("/etc/lacmp-panel/bootstrap.json")).get("installed_php",0))' 2>/dev/null || echo 0)"
    if [[ "${installed_caddy}" == "1" ]]; then
        echo "==> Removing bootstrap-installed Caddy"
        if command -v apt-get >/dev/null 2>&1; then
            apt-get -y remove --purge caddy 2>/dev/null || true
        elif command -v dnf >/dev/null 2>&1; then
            dnf -y remove caddy 2>/dev/null || true
        fi
    fi
    if [[ "${installed_php}" == "1" ]]; then
        echo "==> Removing bootstrap-installed PHP ${PANEL_PHP_VERSION}"
        if command -v apt-get >/dev/null 2>&1; then
            apt-get -y remove --purge "php${PANEL_PHP_VERSION}-fpm" "php${PANEL_PHP_VERSION}-cli" 2>/dev/null || true
        elif command -v dnf >/dev/null 2>&1; then
            dnf -y remove php-fpm php-cli 2>/dev/null || dnf -y remove php84-php-fpm php84-php-cli 2>/dev/null || true
        fi
    fi
fi

rm -f /etc/lacmp-panel/broker.json /etc/lacmp-panel/runtime.json /etc/lacmp-panel/access.env /etc/lacmp-panel/bootstrap.json

if command -v visudo >/dev/null 2>&1; then
    visudo -c >/dev/null 2>&1 || echo "Warning: visudo -c failed after uninstall." >&2
fi

echo "Stack Manager uninstalled. User sites under /data/www were not touched."
