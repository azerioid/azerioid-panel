#!/usr/bin/env bash
# SELinux contexts for EL when enforcing (A3).
set -euo pipefail

apply_selinux() {
    [[ "${DISTRO_FAMILY}" == "el" ]] || return 0
    command -v getenforce >/dev/null 2>&1 || return 0
    [[ "$(getenforce 2>/dev/null || echo Disabled)" == "Enforcing" ]] || return 0

    echo "==> Applying SELinux contexts (enforcing)"
    dnf -y install policycoreutils-python-utils >/dev/null 2>&1 || true

    semanage port -a -t http_port_t -p tcp "${PANEL_PORT}" 2>/dev/null \
        || semanage port -m -t http_port_t -p tcp "${PANEL_PORT}" 2>/dev/null || true
    semanage fcontext -a -t httpd_sys_content_t '/data/www(/.*)?' 2>/dev/null || true
    semanage fcontext -a -t httpd_sys_rw_content_t '/var/lib/azerioid-panel(/.*)?' 2>/dev/null || true
    install -d -m 0755 /data/www
    restorecon -Rv /data/www /var/lib/azerioid-panel 2>/dev/null || true
    setsebool -P httpd_can_network_connect 1 2>/dev/null || true
}
