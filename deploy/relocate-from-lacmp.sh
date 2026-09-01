#!/usr/bin/env bash
# Relocate an existing lacmp-panel install to azerioid-panel paths on disk.
# Safe to run once after upgrading panel code on a host that still uses legacy paths.
set -euo pipefail

OLD_PREFIX="/usr/local/lib/lacmp-panel"
NEW_PREFIX="/usr/local/lib/azerioid-panel"
OLD_ETC="/etc/lacmp-panel"
NEW_ETC="/etc/azerioid-panel"
OLD_VAR="/var/lib/lacmp-panel"
NEW_VAR="/var/lib/azerioid-panel"
OLD_LOG="/var/log/lacmp-panel"
NEW_LOG="/var/log/azerioid-panel"

usage() {
    cat <<'EOF'
Usage: sudo ./deploy/relocate-from-lacmp.sh [--dry-run]

Moves lacmp-panel install paths to azerioid-panel equivalents and updates
systemd/sudoers/Caddy/FPM references. Does not modify site databases.

  --dry-run   print planned moves only
EOF
}

DRY_RUN=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run) DRY_RUN=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
    esac
done

[[ ${EUID} -eq 0 ]] || { echo "relocate-from-lacmp.sh must run as root" >&2; exit 1; }

move_path() {
    local from="$1" to="$2"
    if [[ ! -e "${from}" ]]; then
        return 0
    fi
    if [[ -e "${to}" ]]; then
        echo "[skip] ${to} already exists"
        return 0
    fi
    echo "[move] ${from} -> ${to}"
    if [[ "${DRY_RUN}" -eq 0 ]]; then
        mkdir -p "$(dirname "${to}")"
        mv "${from}" "${to}"
    fi
}

if [[ ! -d "${OLD_PREFIX}" && ! -d "${NEW_PREFIX}" ]]; then
    echo "Neither ${OLD_PREFIX} nor ${NEW_PREFIX} found." >&2
    exit 1
fi

if [[ -d "${NEW_PREFIX}" && ! -d "${OLD_PREFIX}" ]]; then
    echo "Already relocated (${NEW_PREFIX} present)."
    exit 0
fi

echo "==> Relocating lacmp-panel paths to azerioid-panel"
move_path "${OLD_PREFIX}" "${NEW_PREFIX}"
move_path "${OLD_ETC}" "${NEW_ETC}"
move_path "${OLD_VAR}" "${NEW_VAR}"
move_path "${OLD_LOG}" "${NEW_LOG}"

if [[ "${DRY_RUN}" -eq 1 ]]; then
    echo "Dry run complete."
    exit 0
fi

systemctl stop lacmp-panel-queue.service 2>/dev/null || true
systemctl disable lacmp-panel-queue.service 2>/dev/null || true
rm -f /etc/systemd/system/lacmp-panel-queue.service

if [[ -f /etc/caddy/conf.d/lacmp-panel.conf ]]; then
    mv /etc/caddy/conf.d/lacmp-panel.conf /etc/caddy/conf.d/azerioid-panel.conf
    systemctl reload caddy 2>/dev/null || true
fi

for pool in /etc/php/*/fpm/pool.d/lacmp-panel.conf /etc/php-fpm.d/lacmp-panel.conf; do
    [[ -f "${pool}" ]] || continue
    mv "${pool}" "$(dirname "${pool}")/azerioid-panel.conf"
done

rm -f /etc/tmpfiles.d/lacmp-panel.conf /etc/cron.d/lacmp-panel
rm -f /etc/sudoers.d/lacmp-panel
rm -f /etc/fail2ban/filter.d/lacmp-panel.conf /etc/fail2ban/jail.d/lacmp-panel.conf

for unit_dir in /etc/systemd/system/php*-fpm.service.d; do
    [[ -f "${unit_dir}/lacmp-panel.conf" ]] || continue
    mv "${unit_dir}/lacmp-panel.conf" "${unit_dir}/azerioid-panel.conf"
done

if [[ -f "${NEW_PREFIX}/web/.env" ]]; then
    python3 - "${NEW_PREFIX}/web/.env" <<'PY'
import pathlib, re, sys
path = pathlib.Path(sys.argv[1])
text = path.read_text()
replacements = {
    "/usr/local/lib/lacmp-panel": "/usr/local/lib/azerioid-panel",
    "/var/lib/lacmp-panel": "/var/lib/azerioid-panel",
    "LACMP_WWW_ROOT": "AZERIOID_WWW_ROOT",
    "LACMP_SESSION_IDLE": "AZERIOID_SESSION_IDLE",
}
for old, new in replacements.items():
    text = text.replace(old, new)
path.write_text(text)
PY
fi

if [[ -f "${NEW_ETC}/broker.json" ]]; then
    python3 - "${NEW_ETC}/broker.json" <<'PY'
import json, pathlib, sys
path = pathlib.Path(sys.argv[1])
data = json.loads(path.read_text())
paths = data.get("paths", {})
for key, val in list(paths.items()):
    if isinstance(val, str):
        paths[key] = val.replace("/usr/local/lib/lacmp-panel", "/usr/local/lib/azerioid-panel").replace("/var/lib/lacmp-panel", "/var/lib/azerioid-panel")
data["paths"] = paths
if "panel_runtime" in data:
    pr = data["panel_runtime"]
    if isinstance(pr.get("fpm_socket"), str):
        pr["fpm_socket"] = pr["fpm_socket"].replace("lacmp-panel", "azerioid-panel")
    if isinstance(pr.get("fpm_pool"), str):
        pr["fpm_pool"] = pr["fpm_pool"].replace("lacmp-panel", "azerioid-panel")
    if isinstance(pr.get("queue_unit"), str):
        pr["queue_unit"] = pr["queue_unit"].replace("lacmp-panel", "azerioid-panel")
path.write_text(json.dumps(data, indent=2) + "\n")
PY
fi

echo "Re-run stack-manager installer modules or reinstall queue unit from ${NEW_PREFIX}/deploy"
echo "  sudo ${NEW_PREFIX}/deploy/install.sh --non-interactive  # if available"
echo "Or manually: systemctl daemon-reload && systemctl enable --now azerioid-panel-queue.service"
echo "Relocate complete."
