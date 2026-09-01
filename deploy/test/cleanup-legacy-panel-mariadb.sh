#!/usr/bin/env bash
# Remove synthetic lacmp_panel database after P7 migration proof.
set -euo pipefail

LEGACY_DB="${LEGACY_DB:-lacmp_panel}"
LEGACY_USER="${LEGACY_USER:-lacmp_panel}"
BROKER_JSON="/etc/azerioid-panel/broker.json"

[[ ${EUID} -eq 0 ]] || { echo "Run as root." >&2; exit 1; }
[[ -f "${BROKER_JSON}" ]] || { echo "broker.json not found." >&2; exit 1; }

readarray -t CREDS < <(python3 - "${BROKER_JSON}" <<'PY'
import json, sys
m = json.load(open(sys.argv[1])).get("database", {}).get("mariadb", {})
print(m.get("user", ""))
print(m.get("password", ""))
print(m.get("socket", ""))
PY
)

mysql_admin() {
    if [[ -n "${CREDS[2]:-}" ]]; then
        mariadb -S "${CREDS[2]}" -u "${CREDS[0]}" -p"${CREDS[1]}" "$@"
    else
        mariadb -h 127.0.0.1 -u "${CREDS[0]}" -p"${CREDS[1]}" "$@"
    fi
}

mysql_admin -e "DROP DATABASE IF EXISTS \`${LEGACY_DB}\`;"
mysql_admin -e "DROP USER IF EXISTS '${LEGACY_USER}'@'localhost';"
mysql_admin -e "DROP USER IF EXISTS '${LEGACY_USER}'@'127.0.0.1';"
rm -f /var/lib/azerioid-panel/staging/legacy-panel-seed.env
echo "[OK] Removed synthetic legacy database ${LEGACY_DB}"
