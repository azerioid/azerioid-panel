#!/usr/bin/env bash
# Seed a legacy-shaped lacmp_panel MariaDB database for P7 migration testing.
# Does not modify the live panel .env — only creates the source database + rows.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PREFIX="${PREFIX:-/usr/local/lib/azerioid-panel}"
LEGACY_DB="${LEGACY_DB:-lacmp_panel}"
LEGACY_USER="${LEGACY_USER:-lacmp_panel}"
LEGACY_PASS="${LEGACY_PASS:-LegacyPanelTest!2026}"

fail() { echo "[FAIL] $*" >&2; exit 1; }
ok() { echo "[OK] $*"; }

[[ ${EUID} -eq 0 ]] || fail "Run as root."

command -v mariadb >/dev/null 2>&1 || fail "mariadb client required."
command -v php8.4 >/dev/null 2>&1 || fail "php8.4 required."

BROKER_JSON="/etc/azerioid-panel/broker.json"
[[ -f "${BROKER_JSON}" ]] || fail "broker.json not found."

readarray -t CREDS < <(python3 - "${BROKER_JSON}" <<'PY'
import json, sys
data = json.load(open(sys.argv[1]))
m = data.get("database", {}).get("mariadb", {})
print(m.get("user", ""))
print(m.get("password", ""))
print(m.get("socket", ""))
PY
)
ADMIN_USER="${CREDS[0]:-}"
ADMIN_PASS="${CREDS[1]:-}"
ADMIN_SOCKET="${CREDS[2]:-}"

[[ -n "${ADMIN_USER}" && -n "${ADMIN_PASS}" ]] || fail "MariaDB admin credentials missing from broker.json."

mysql_admin() {
    if [[ -n "${ADMIN_SOCKET}" ]]; then
        mariadb -S "${ADMIN_SOCKET}" -u "${ADMIN_USER}" -p"${ADMIN_PASS}" "$@"
    else
        mariadb -h 127.0.0.1 -u "${ADMIN_USER}" -p"${ADMIN_PASS}" "$@"
    fi
}

info() { echo "==> $*"; }

info "Dropping prior synthetic legacy database (if any)"
mysql_admin -e "DROP DATABASE IF EXISTS \`${LEGACY_DB}\`;"
mysql_admin -e "DROP USER IF EXISTS '${LEGACY_USER}'@'localhost';"
mysql_admin -e "DROP USER IF EXISTS '${LEGACY_USER}'@'127.0.0.1';"

info "Creating legacy database ${LEGACY_DB}"
mysql_admin -e "CREATE DATABASE \`${LEGACY_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql_admin -e "CREATE USER '${LEGACY_USER}'@'localhost' IDENTIFIED BY '${LEGACY_PASS}';"
mysql_admin -e "CREATE USER '${LEGACY_USER}'@'127.0.0.1' IDENTIFIED BY '${LEGACY_PASS}';"
mysql_admin -e "GRANT ALL PRIVILEGES ON \`${LEGACY_DB}\`.* TO '${LEGACY_USER}'@'localhost';"
mysql_admin -e "GRANT ALL PRIVILEGES ON \`${LEGACY_DB}\`.* TO '${LEGACY_USER}'@'127.0.0.1';"
mysql_admin -e "FLUSH PRIVILEGES;"

info "Running Laravel migrations against legacy MariaDB"
cd "${PREFIX}/web"
DB_CONNECTION=mysql \
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE="${LEGACY_DB}" \
DB_USERNAME="${LEGACY_USER}" \
DB_PASSWORD="${LEGACY_PASS}" \
php8.4 artisan migrate --force --no-interaction

info "Seeding legacy rows"
mysql_admin "${LEGACY_DB}" <<'SQL'
INSERT INTO users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, failed_logins)
VALUES
(1, 'Legacy Admin', 'legacy-admin@example.test', NULL, '$2y$12$abcdefghijklmnopqrstuv0123456789012345678901234', NULL, '2026-01-15 10:00:00', '2026-01-15 10:00:00', 0),
(2, 'Legacy Operator', 'legacy-op@example.test', NULL, '$2y$12$abcdefghijklmnopqrstuv0123456789012345678901234', NULL, '2026-01-16 11:00:00', '2026-01-16 11:00:00', 0);

INSERT INTO settings (`key`, value, created_at, updated_at) VALUES
('panel.name', 'Legacy Panel', '2026-01-15 10:00:00', '2026-01-15 10:00:00'),
('alerts.telegram_chat_id', '12345', '2026-01-15 10:00:00', '2026-01-15 10:00:00');

INSERT INTO audit_logs (user_id, action, args, ok, code, error, ip, created_at, updated_at) VALUES
(1, 'auth.login', '{"email":"legacy-admin@example.test"}', 1, 0, NULL, '127.0.0.1', '2026-01-15 10:05:00', '2026-01-15 10:05:00'),
(2, 'component.install', '{"id":"redis"}', 1, 0, NULL, '127.0.0.1', '2026-01-16 12:00:00', '2026-01-16 12:00:00');

INSERT INTO jobs (queue, payload, attempts, reserved_at, available_at, created_at) VALUES
('default', '{"job":"legacy"}', 0, NULL, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
SQL

info "Legacy row counts"
for table in users settings audit_logs jobs sessions; do
    count="$(mysql_admin -N -B -e "SELECT COUNT(*) FROM \`${LEGACY_DB}\`.\`${table}\`;" 2>/dev/null || echo 0)"
    printf '  %-16s %s\n' "${table}:" "${count}"
done

cat > /var/lib/azerioid-panel/staging/legacy-panel-seed.env <<EOF
LEGACY_DB=${LEGACY_DB}
LEGACY_USER=${LEGACY_USER}
LEGACY_PASS=${LEGACY_PASS}
EOF
chmod 0640 /var/lib/azerioid-panel/staging/legacy-panel-seed.env

ok "Legacy panel database ready at ${LEGACY_DB}"
echo "Credentials written to /var/lib/azerioid-panel/staging/legacy-panel-seed.env"
