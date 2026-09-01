#!/usr/bin/env bash
# fail2ban, ufw/firewalld — carried forward from v1 installer.
set -euo pipefail

install_security() {
    if [[ "${DO_FAIL2BAN:-false}" == "true" ]]; then
        echo "==> fail2ban jail for panel failed logins"
        case "${PKG_MGR}" in
            apt-get)
                export DEBIAN_FRONTEND=noninteractive
                apt-get -o DPkg::Lock::Timeout=120 install -y fail2ban >/dev/null
                ;;
            dnf) dnf -y install fail2ban >/dev/null 2>&1 || true ;;
        esac
        install -d -m 0755 /etc/fail2ban/filter.d /etc/fail2ban/jail.d
        install -m 0644 "${ROOT}/deploy/fail2ban/filter.d/lacmp-panel.conf" \
            /etc/fail2ban/filter.d/lacmp-panel.conf
        cat > /etc/fail2ban/jail.d/lacmp-panel.conf <<EOF
[lacmp-panel]
enabled  = true
filter   = lacmp-panel
logpath  = /var/log/lacmp-panel/auth-fail.log
backend  = auto
maxretry = 5
findtime = 600
bantime  = 3600
port     = ${PANEL_PORT}
EOF
        systemctl enable --now fail2ban 2>/dev/null || true
        systemctl reload fail2ban 2>/dev/null || systemctl restart fail2ban 2>/dev/null || true
    fi

    if [[ "${DO_FIREWALL:-false}" == "true" ]]; then
        echo "==> Firewall rule for panel port ${PANEL_PORT}"
        if command -v ufw >/dev/null 2>&1; then
            ufw allow "${PANEL_PORT}/tcp" comment 'stack-manager' >/dev/null 2>&1 || true
        elif command -v firewall-cmd >/dev/null 2>&1 && systemctl is-active firewalld >/dev/null 2>&1; then
            firewall-cmd --permanent --add-port="${PANEL_PORT}/tcp" >/dev/null
            firewall-cmd --reload >/dev/null
        fi
    fi

    install -d -m 0750 /etc/lacmp-panel
    cat > /etc/lacmp-panel/access.env <<EOF
ACCESS_MODE=${ACCESS:-tunnel}
PANEL_PORT=${PANEL_PORT}
STACK=bootstrap
WEB_SERVICE=caddy
EOF
    chmod 0640 /etc/lacmp-panel/access.env
}
