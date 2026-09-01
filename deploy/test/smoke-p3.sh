#!/usr/bin/env bash
# Phase 3 smoke: Redis install machinery (broker actions + queue prerequisites).
set -euo pipefail

BROKER="${BROKER:-/usr/local/lib/azerioid-panel/broker}"

fail() { echo "[FAIL] $*" >&2; exit 1; }
ok() { echo "[OK] $*"; }

[[ -x "${BROKER}" ]] || fail "broker not found"

preflight="$("${BROKER}" component.preflight redis)"
echo "${preflight}" | python3 -c 'import json,sys; d=json.load(sys.stdin); sys.exit(0 if d.get("ok") and d["data"].get("ok") else 1)' \
    || fail "component.preflight redis"
ok "component.preflight redis"

list="$("${BROKER}" component.list)"
echo "${list}" | python3 -c '
import json,sys
d=json.load(sys.stdin)
comps={c["id"]:c for c in d["data"]["components"]}
r=comps.get("redis",{})
sys.exit(0 if d.get("ok") and r.get("installable") else 1)
' || fail "redis not marked installable"
ok "redis installable in registry"

systemctl is-active azerioid-panel-queue.service >/dev/null 2>&1 || fail "queue worker not active"
ok "queue worker active"

ok "Phase 3 smoke passed (install UI test: use dashboard Components page)"
