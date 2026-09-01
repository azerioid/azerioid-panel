#!/usr/bin/env bash
# Phase 6 smoke: remaining installable components in registry.
set -euo pipefail

BROKER="${BROKER:-/usr/local/lib/azerioid-panel/broker}"
REGISTRY="${REGISTRY:-/usr/local/lib/azerioid-panel/registry/components}"

fail() { echo "[FAIL] $*" >&2; exit 1; }
ok() { echo "[OK] $*"; }

[[ -x "${BROKER}" ]] || fail "broker not found"

for id in memcached mongodb nodejs php-8.1 php-8.2 php-8.3; do
    file="${REGISTRY}/${id}.json"
    [[ -f "${file}" ]] || fail "missing registry file ${id}"
    python3 -c "import json,sys; d=json.load(open(sys.argv[1])); sys.exit(0 if d.get('installable') else 1)" "${file}" \
        || fail "${id} not installable in registry"
    ok "${id} installable"
done

preflight="$("${BROKER}" component.preflight memcached)"
echo "${preflight}" | python3 -c 'import json,sys; d=json.load(sys.stdin); sys.exit(0 if d.get("ok") and d["data"].get("ok") else 1)' \
    || fail "component.preflight memcached"
ok "component.preflight memcached"

ok "Phase 6 smoke passed (install PHP/Node/Memcached/MongoDB from Components page)"
