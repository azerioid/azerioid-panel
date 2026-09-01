#!/usr/bin/env bash
# Stack Manager installer — self-contained bootstrap (Caddy + PHP 8.4 + SQLite).
# Invoked by ./stack-manager.sh or directly by advanced users.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LIB="${ROOT}/deploy/lib"

# Defaults
PREFIX="${PREFIX:-/usr/local/lib/lacmp-panel}"
WWW_ROOT="${WWW_ROOT:-/data/www}"
PANEL_PHP_VERSION="${PANEL_PHP_VERSION:-8.4}"
PANEL_PORT="${PANEL_PORT:-3169}"
WEB_USER="${WEB_USER:-}"
ACCESS="${ACCESS:-tunnel}"
NON_INTERACTIVE=0
DRY_RUN=0
SKIP_CADDY=0
DO_FIREWALL=false
DO_FAIL2BAN=false
REQUIRE_TOTP=false

usage() {
    cat <<'EOF'
Usage: stack-manager.sh [options]
       deploy/install.sh [options]

Stack Manager bootstrap — installs Caddy, PHP 8.4 FPM, SQLite panel (no lcmp/lamp required).

  --non-interactive       no prompts
  --dry-run               print plan only
  --prefix=<dir>          default /usr/local/lib/lacmp-panel
  --port=<n>              panel port (default 3169)
  --web-user=<user>       default: caddy or www-data
  --access=tunnel|public  default tunnel (127.0.0.1)
  --skip-caddy            skip panel vhost snippet
  --firewall=true|false   open panel port in ufw/firewalld
  --fail2ban=true|false   install fail2ban jail
  --require-totp=true|false
  -h, --help
EOF
}

parse_bool() {
    case "$(echo "${1:-}" | tr '[:upper:]' '[:lower:]')" in
        1|true|yes|on) echo true ;;
        0|false|no|off) echo false ;;
        *) return 1 ;;
    esac
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --non-interactive) NON_INTERACTIVE=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        --prefix=*) PREFIX="${1#*=}"; shift ;;
        --port=*) PANEL_PORT="${1#*=}"; shift ;;
        --web-user=*) WEB_USER="${1#*=}"; shift ;;
        --access=*) ACCESS="${1#*=}"; shift ;;
        --skip-caddy) SKIP_CADDY=1; shift ;;
        --firewall=*) DO_FIREWALL="$(parse_bool "${1#*=}")"; shift ;;
        --fail2ban=*) DO_FAIL2BAN="$(parse_bool "${1#*=}")"; shift ;;
        --require-totp=*) REQUIRE_TOTP="$(parse_bool "${1#*=}")"; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
    esac
done

export ROOT PREFIX WWW_ROOT PANEL_PHP_VERSION PANEL_PORT ACCESS NON_INTERACTIVE DRY_RUN SKIP_CADDY
export DO_FIREWALL DO_FAIL2BAN REQUIRE_TOTP

[[ ${EUID} -eq 0 ]] || { echo "install.sh must run as root" >&2; exit 1; }

# shellcheck source=lib/common.sh
source "${LIB}/common.sh"
# shellcheck source=lib/detect-os.sh
source "${LIB}/detect-os.sh"
# shellcheck source=lib/repos.sh
source "${LIB}/repos.sh"
# shellcheck source=lib/packages.sh
source "${LIB}/packages.sh"
# shellcheck source=lib/selinux.sh
source "${LIB}/selinux.sh"
# shellcheck source=lib/fpm.sh
source "${LIB}/fpm.sh"
# shellcheck source=lib/caddy.sh
source "${LIB}/caddy.sh"
# shellcheck source=lib/panel-db.sh
source "${LIB}/panel-db.sh"
# shellcheck source=lib/queue-worker.sh
source "${LIB}/queue-worker.sh"
# shellcheck source=lib/broker-setup.sh
source "${LIB}/broker-setup.sh"
# shellcheck source=lib/security.sh
source "${LIB}/security.sh"

detect_os
WEB_USER="$(detect_web_user)"
export WEB_USER

if [[ "${DRY_RUN}" -eq 1 ]]; then
    echo "DRY-RUN — Stack Manager bootstrap"
    echo "  prefix:    ${PREFIX}"
    echo "  php:       ${PANEL_PHP_VERSION}"
    echo "  web user:  ${WEB_USER}"
    echo "  port:      ${PANEL_PORT}"
    echo "  access:    ${ACCESS}"
    exit 0
fi

[[ -d "${ROOT}/broker/src" && -f "${ROOT}/broker/broker" && -d "${ROOT}/web" ]] || {
    echo "Repo layout incomplete (need broker/ and web/)." >&2
    exit 1
}

echo "==> Stack Manager bootstrap into ${PREFIX}"

setup_repos
bootstrap_packages
apply_selinux
install_broker
install_panel_app
configure_panel_db
configure_panel_fpm
configure_panel_caddy
install_queue_worker
dispatch_ping_job
install_security
write_bootstrap_json

chmod 0751 "${PREFIX}"
chown root:root "${PREFIX}"

echo
echo "Stack Manager installed."
echo "  Panel:     http://127.0.0.1:${PANEL_PORT}"
echo "  Broker:    ${PREFIX}/broker"
echo "  Database:  /var/lib/lacmp-panel/panel.sqlite"
echo "  Queue:     systemctl status lacmp-panel-queue"
echo "  SSH tunnel: ssh -L ${PANEL_PORT}:127.0.0.1:${PANEL_PORT} user@host"
