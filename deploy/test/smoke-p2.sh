#!/usr/bin/env bash
# Phase 2 smoke: registry-driven component.list/status + Components page prerequisites.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BROKER="${BROKER:-/usr/local/lib/azerioid-panel/broker}"
REGISTRY="${REGISTRY:-/usr/local/lib/azerioid-panel/registry/components}"

fail() { echo "[FAIL] $*" >&2; exit 1; }
ok() { echo "[OK] $*"; }

[[ -x "${BROKER}" ]] || fail "broker not found at ${BROKER}"
[[ -d "${REGISTRY}" ]] || fail "registry not deployed at ${REGISTRY}"

count="$(find "${REGISTRY}" -maxdepth 1 -name '*.json' | wc -l | tr -d ' ')"
[[ "${count}" -ge 13 ]] || fail "expected >= 13 registry components, got ${count}"
ok "registry has ${count} component files"

list_json="$("${BROKER}" component.list)"
echo "${list_json}" | python3 -c 'import json,sys; d=json.load(sys.stdin); sys.exit(0 if d.get("ok") and len(d.get("data",{}).get("components",[]))>=13 else 1)' \
    || fail "component.list did not return >= 13 components"
ok "broker component.list"

redis_json="$("${BROKER}" component.status redis)"
echo "${redis_json}" | python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("data",{}); sys.exit(0 if d.get("ok") and c.get("id")=="redis" else 1)' \
    || fail "component.status redis failed"
ok "broker component.status redis"

if [[ -f /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    ok "host OS ${ID:-unknown} ${VERSION_ID:-}"
fi

ok "Phase 2 smoke passed"
