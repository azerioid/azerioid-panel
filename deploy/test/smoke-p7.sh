#!/usr/bin/env bash
# Phase 7 smoke: migrate.sh + panel:import-from-mariadb command present.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PREFIX="${PREFIX:-/usr/local/lib/azerioid-panel}"

fail() { echo "[FAIL] $*" >&2; exit 1; }
ok() { echo "[OK] $*"; }

[[ -x "${ROOT}/deploy/migrate.sh" ]] || fail "deploy/migrate.sh missing or not executable"
bash -n "${ROOT}/deploy/migrate.sh" || fail "deploy/migrate.sh syntax error"
ok "deploy/migrate.sh present"

if [[ -f "${PREFIX}/web/artisan" ]]; then
    if sudo -u "$(id -un)" "${PREFIX}/web/artisan" list 2>/dev/null | grep -q 'panel:import-from-mariadb'; then
        ok "panel:import-from-mariadb registered"
    else
        PHP_BIN="$(command -v php8.4 || command -v php || true)"
        if [[ -n "${PHP_BIN}" && -f "${ROOT}/web/artisan" ]]; then
            (cd "${ROOT}/web" && "${PHP_BIN}" artisan list 2>/dev/null | grep -q 'panel:import-from-mariadb') \
                || fail "panel:import-from-mariadb not in artisan list"
            ok "panel:import-from-mariadb registered (repo tree)"
        else
            ok "panel:import-from-mariadb (skipped artisan list — php/artisan unavailable)"
        fi
    fi
else
    [[ -f "${ROOT}/web/app/Console/Commands/ImportPanelFromMariadb.php" ]] \
        || fail "ImportPanelFromMariadb command missing"
    ok "ImportPanelFromMariadb command source present"
fi

ok "Phase 7 smoke passed (run sudo ./deploy/migrate.sh on legacy MariaDB panel hosts)"
