#!/usr/bin/env bash
# Phase 4 smoke: database engines installable + db.engine broker action.
set -euo pipefail

BROKER="${BROKER:-/usr/local/lib/azerioid-panel/broker}"

fail() { echo "[FAIL] $*" >&2; exit 1; }
ok() { echo "[OK] $*"; }

[[ -x "${BROKER}" ]] || fail "broker not found"

for id in mariadb postgresql; do
    preflight="$("${BROKER}" component.preflight "${id}")"
    echo "${preflight}" | python3 -c 'import json,sys; d=json.load(sys.stdin); sys.exit(0 if d.get("ok") and d["data"].get("ok") is not None else 1)' \
        || fail "component.preflight ${id}"
    ok "component.preflight ${id}"
done

list="$("${BROKER}" component.list)"
echo "${list}" | python3 -c '
import json,sys
d=json.load(sys.stdin)
comps={c["id"]:c for c in d["data"]["components"]}
for cid in ("mariadb","postgresql"):
    if not comps.get(cid,{}).get("installable"):
        sys.exit(1)
sys.exit(0)
' || fail "mariadb/postgresql not installable"
ok "mariadb and postgresql installable"

engine="$("${BROKER}" db.engine)"
echo "${engine}" | python3 -c '
import json,sys
d=json.load(sys.stdin)
sys.exit(0 if d.get("ok") and isinstance(d["data"].get("engines"), list) else 1)
' || fail "db.engine"
ok "db.engine"

ok "Phase 4 smoke passed (install DB engine from Components, then use Databases page)"
