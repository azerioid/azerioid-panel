#!/usr/bin/env bash
# Post-install admin bootstrap and summary helpers.
set -euo pipefail

ADMIN_CREATE_FAILED="${ADMIN_CREATE_FAILED:-0}"

_admin_create_manual_hint() {
    local setup_url="http://127.0.0.1:${PANEL_PORT}/setup"
    echo "  The panel stack is installed and running."
    echo "  Create an admin account using either:"
    echo "    • Browser:  ${setup_url}"
    echo "    • CLI (password via env only — not argv):"
    echo "        sudo -u ${WEB_USER} env PANEL_INSTALL_ADMIN_PASSWORD='your-password' \\"
    echo "          bash -c 'cd ${PREFIX}/web && $(php_bin) artisan panel:create-admin --email=you@example.com'"
}

_invoke_create_panel_admin() {
    local php_bin="$1"
    local allow_opt="${2:-}"
    local admin_password="$3"

    sudo -u "${WEB_USER}" -H env \
        COMPOSER_HOME="${COMPOSER_HOME:-/tmp}" \
        PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin" \
        PANEL_INSTALL_ADMIN_PASSWORD="${admin_password}" \
        bash -c "cd '${PREFIX}/web' && '${php_bin}' artisan panel:create-admin --email='${ADMIN_EMAIL}' --name='${ADMIN_NAME}' ${allow_opt}"
}

create_install_admin() {
    case "$(echo "${CREATE_ADMIN:-0}" | tr '[:upper:]' '[:lower:]')" in
        1|true|yes|on) ;;
        *) ADMIN_CREATE_FAILED=0; export ADMIN_CREATE_FAILED; return 0 ;;
    esac

    echo "==> Creating admin account (${ADMIN_EMAIL})"
    local php_bin allow_opt="" admin_password="${ADMIN_PASSWORD}" attempt=1 max_attempts=1
    php_bin="$(php_bin)"
    [[ -n "${IP_ALLOWLIST:-}" ]] && allow_opt="--allowlist=${IP_ALLOWLIST}"

    if [[ "${NON_INTERACTIVE:-0}" -eq 0 && -t 0 ]]; then
        max_attempts=2
    fi

    while [[ "${attempt}" -le "${max_attempts}" ]]; do
        if _invoke_create_panel_admin "${php_bin}" "${allow_opt}" "${admin_password}"; then
            ADMIN_CREATE_FAILED=0
            export ADMIN_CREATE_FAILED
            admin_password=""
            return 0
        fi

        ADMIN_CREATE_FAILED=1
        export ADMIN_CREATE_FAILED
        echo ""
        warn "Admin account creation failed (attempt ${attempt}/${max_attempts})."
        _admin_create_manual_hint

        if [[ "${attempt}" -ge "${max_attempts}" ]]; then
            echo ""
            warn "Continuing install without an admin account — see summary for next steps."
            admin_password=""
            return 0
        fi

        echo ""
        if [[ "${NON_INTERACTIVE:-0}" -eq 0 && -t 0 ]]; then
            ADMIN_EMAIL="$(prompt_line "Admin email" "${ADMIN_EMAIL}")"
            admin_password="$(prompt_password "Admin password" "${ADMIN_PASSWORD}")"
            ADMIN_PASSWORD="${admin_password}"
            attempt=$((attempt + 1))
        else
            admin_password=""
            return 0
        fi
    done

    admin_password=""
    return 0
}

print_install_warnings() {
    if [[ "${ACCESS:-tunnel}" == "public" && "${REQUIRE_TOTP:-false}" != "true" ]]; then
        warn "SECURITY: Public panel with TOTP disabled — password-only login on the network."
        echo "         Reinstall with --require-totp=true or enable 2FA after changing the password."
    fi

    if [[ "${ACCESS:-tunnel}" == "public" && "${INSTALL_USED_DEFAULT_ADMIN_PASSWORD:-0}" -eq 1 && "${ADMIN_CREATE_FAILED:-0}" -eq 0 ]]; then
        echo ""
        echo "${C_RED}⚠ SECURITY: Default admin password is in use on a public panel.${C_RST}"
        echo "  Change it immediately after first login (Settings → Password)."
        echo "  Admin email: ${ADMIN_EMAIL}"
    fi
}
