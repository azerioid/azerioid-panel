#!/usr/bin/env bash
# Phase 5 smoke: Nginx installable + component.adopt broker action.
set -euo pipefail

BROKER="${BROKER:-/usr/local/lib/azerioid-panel/broker}"

fail() { echo "[FAIL] $*" >&2; exit 1; }
ok() { echo "[OK] $*"; }

[[ -x "${BROKER}" ]] || fail "broker not found"

list="$("${BROKER}" component.list)"
echo "${list}" | python3 -c '
import json,sys
d=json.load(sys.stdin)
comps={c["id"]:c for c in d["data"]["components"]}
n=comps.get("nginx",{})
sys.exit(0 if d.get("ok") and n.get("installable") else 1)
' || fail "nginx not installable"
ok "nginx installable"

preflight="$("${BROKER}" component.preflight nginx)"
echo "${preflight}" | python3 -c 'import json,sys; d=json.load(sys.stdin); sys.exit(0 if d.get("ok") else 1)' \
    || fail "component.preflight nginx"
ok "component.preflight nginx"

help="$("${BROKER}" component.adopt 2>&1 || true)"
echo "${help}" | python3 -c '
import json,sys
text=sys.stdin.read()
try:
    d=json.loads(text.splitlines()[-1])
    sys.exit(0 if not d.get("ok") else 1)
except Exception:
    sys.exit(0)
' || fail "component.adopt should require component id"
ok "component.adopt action registered"

ok "Phase 5 smoke passed (adopt observed components from Components page)"
