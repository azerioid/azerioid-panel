#!/usr/bin/env bash
# Wait until the panel HTTP endpoint and FPM socket are actually ready.
set -euo pipefail

wait_for_panel_ready() {
    local port="${PANEL_PORT}"
    local fpm_sock="/run/php/azerioid-panel.sock"
    local queue_unit="azerioid-panel-queue.service"
    local i

    echo "==> Waiting for panel services to become ready"
    for i in $(seq 1 60); do
        if [[ -S "${fpm_sock}" ]] \
            && systemctl is-active --quiet "${queue_unit}" 2>/dev/null \
            && curl -fsSI "http://127.0.0.1:${port}/login" 2>/dev/null | head -n1 | grep -qE 'HTTP/[0-9.]+ (200|302)'; then
            return 0
        fi
        sleep 0.5
    done

    die "Panel is not reachable on 127.0.0.1:${port} (FPM socket, queue worker, or HTTP check failed)."
}

print_install_success() {
    local panel_url="http://127.0.0.1:${PANEL_PORT}"
    local setup_url="${panel_url}/setup"
    local login_url="${panel_url}/login"
    local totp_note="disabled (password-only login)"
    if [[ "${REQUIRE_TOTP:-false}" == "true" ]]; then
        totp_note="required (enroll authenticator during setup)"
    fi

    echo
    echo "Stack Manager installed."
    echo "  Panel:     ${panel_url}"
    echo "  Setup:     ${setup_url}  (create the admin account — required on first visit)"
    echo "  Sign in:   ${login_url}  (after setup completes)"
    echo "  TOTP:      ${totp_note}"
    echo "  Broker:    ${PREFIX}/broker"
    echo "  Database:  /var/lib/azerioid-panel/panel.sqlite"
    echo "  Queue:     systemctl status azerioid-panel-queue"
    echo
    echo "  SSH tunnel: ssh -L ${PANEL_PORT}:127.0.0.1:${PANEL_PORT} user@host"
    echo "              Then open ${setup_url} in your browser."
    if [[ "${ACCESS:-tunnel}" == "public" ]]; then
        local public_ip=""
        public_ip="$(detect_public_ip 2>/dev/null || true)"
        if [[ -n "${public_ip}" ]]; then
            echo "  Public:    https://${public_ip}:${PANEL_PORT}/setup  (self-signed TLS; accept the certificate warning)"
        fi
    fi
    echo
    echo "  No admin account exists yet. Complete /setup before signing in."
}
