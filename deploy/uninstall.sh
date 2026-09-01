#!/usr/bin/env bash
# Reverse Stack Manager bootstrap. Never touches /data/www or user site data.
set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
    echo "uninstall.sh must run as root" >&2
    exit 1
fi

PREFIX="${PREFIX:-/usr/local/lib/lacmp-panel}"
PANEL_PHP_VERSION="${PANEL_PHP_VERSION:-8.4}"
DROP_DB=0
REMOVE_BOOTSTRAP=0

usage() {
    cat <<'EOF'
Usage: uninstall.sh [--drop-db] [--remove-bootstrap]

  --drop-db            Remove panel SQLite and /etc/lacmp-panel secrets
  --remove-bootstrap   Remove Caddy/PHP only if bootstrap.json says installer added them
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --drop-db) DROP_DB=1; shift ;;
        --remove-bootstrap) REMOVE_BOOTSTRAP=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
    esac
done

WEB_USER=caddy
id -u caddy >/dev/null 2>&1 || WEB_USER=www-data
id -u "${WEB_USER}" >/dev/null 2>&1 || WEB_USER=apache

systemctl stop lacmp-panel-queue.service 2>/dev/null || true
systemctl disable lacmp-panel-queue.service 2>/dev/null || true
rm -f /etc/systemd/system/lacmp-panel-queue.service
systemctl daemon-reload

rm -f /etc/sudoers.d/lacmp-panel
visudo -c >/dev/null 2>&1 || echo "Warning: visudo -c failed after removing panel sudoers." >&2
rm -f /etc/cron.d/lacmp-panel

rm -f /etc/fail2ban/filter.d/lacmp-panel.conf /etc/fail2ban/jail.d/lacmp-panel.conf
systemctl reload fail2ban 2>/dev/null || true

rm -f /etc/tmpfiles.d/lacmp-panel.conf
rm -f /etc/lacmp-panel/access.env /etc/lacmp-panel/runtime.json

SNIPPET=/etc/caddy/conf.d/lacmp-panel.conf
if [[ -f "${SNIPPET}" ]]; then
    rm -f "${SNIPPET}"
    systemctl reload caddy 2>/dev/null || true
fi

POOL=""
[[ -d "/etc/php/${PANEL_PHP_VERSION}/fpm/pool.d" ]] && POOL="/etc/php/${PANEL_PHP_VERSION}/fpm/pool.d"
[[ -z "${POOL}" && -d /etc/php-fpm.d ]] && POOL=/etc/php-fpm.d
rm -f "${POOL}/lacmp-panel.conf" 2>/dev/null || true

UNIT="php${PANEL_PHP_VERSION}-fpm"
systemctl cat php-fpm.service >/dev/null 2>&1 && UNIT=php-fpm
rm -f "/etc/systemd/system/${UNIT}.service.d/lacmp-panel.conf" 2>/dev/null || true
systemctl daemon-reload
systemctl restart "${UNIT}" 2>/dev/null || true

if [[ -f "${PREFIX}/web/.env" ]]; then
    install -d -m 0750 /etc/lacmp-panel
    cp -a "${PREFIX}/web/.env" /etc/lacmp-panel/web.env
    chmod 0640 /etc/lacmp-panel/web.env
fi

rm -rf "${PREFIX}"

if [[ "${DROP_DB}" -eq 1 ]]; then
    rm -f /var/lib/lacmp-panel/panel.sqlite /var/lib/lacmp-panel/panel.sqlite-wal /var/lib/lacmp-panel/panel.sqlite-shm
    rm -rf /var/lib/lacmp-panel/staging
    rm -f /etc/lacmp-panel/broker.json /etc/lacmp-panel/web.env /etc/lacmp-panel/bootstrap.json
    rmdir /etc/lacmp-panel 2>/dev/null || true
fi

BOOTSTRAP=/etc/lacmp-panel/bootstrap.json
if [[ "${REMOVE_BOOTSTRAP}" -eq 1 && -f "${BOOTSTRAP}" ]]; then
  if python3 - "${BOOTSTRAP}" <<'PY'
import json, pathlib, sys
data = json.loads(pathlib.Path(sys.argv[1]).read_text())
sys.exit(0 if data.get("caddy") or data.get("php") else 1)
PY
  then
    echo "==> Removing bootstrap-installed Caddy/PHP (per bootstrap.json)"
    if command -v apt-get >/dev/null 2>&1; then
      apt-get -y remove --purge caddy "php${PANEL_PHP_VERSION}-fpm" "php${PANEL_PHP_VERSION}-cli" 2>/dev/null || true
    elif command -v dnf >/dev/null 2>&1; then
      dnf -y remove caddy php-fpm php-cli 2>/dev/null || true
    fi
    rm -f "${BOOTSTRAP}"
  fi
fi

echo "Stack Manager panel artifacts removed."
echo "  /data/www and user databases were not modified."
