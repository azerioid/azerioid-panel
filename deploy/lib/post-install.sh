#!/usr/bin/env bash
# Post-install admin bootstrap and summary helpers.
set -euo pipefail

create_install_admin() {
    case "$(echo "${CREATE_ADMIN:-0}" | tr '[:upper:]' '[:lower:]')" in
        1|true|yes|on) ;;
        *) return 0 ;;
    esac

    echo "==> Creating admin account (${ADMIN_EMAIL})"
    local php_bin allow_opt=""
    php_bin="$(php_bin)"
    [[ -n "${IP_ALLOWLIST:-}" ]] && allow_opt="--allowlist=${IP_ALLOWLIST}"
    PANEL_INSTALL_ADMIN_PASSWORD="${ADMIN_PASSWORD}" \
        run_as_web "${php_bin} artisan panel:create-admin --email='${ADMIN_EMAIL}' --name='${ADMIN_NAME}' ${allow_opt}"
}

print_install_warnings() {
    if [[ "${ACCESS:-tunnel}" == "public" && "${REQUIRE_TOTP:-false}" != "true" ]]; then
        warn "SECURITY: Public panel with TOTP disabled — password-only login on the network."
        echo "         Reinstall with --require-totp=true or enable 2FA after changing the password."
    fi

    if [[ "${ACCESS:-tunnel}" == "public" && "${INSTALL_USED_DEFAULT_ADMIN_PASSWORD:-0}" -eq 1 ]]; then
        echo ""
        echo "${C_RED}⚠ SECURITY: Default admin password is in use on a public panel.${C_RST}"
        echo "  Change it immediately after first login (Settings → Password)."
        echo "  Admin email: ${ADMIN_EMAIL}"
    fi
}
