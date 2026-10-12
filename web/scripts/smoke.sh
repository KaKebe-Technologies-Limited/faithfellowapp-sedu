#!/usr/bin/env bash
# Smoke test: logs in with a seeded account and checks a list of pages return the expected HTTP codes.
# Usage: scripts/smoke.sh <email> <password> <path[:expected]>...
set -u
BASE="${BASE_URL:-http://127.0.0.1:8000}"
EMAIL="$1"; PASS="$2"; shift 2
JAR="$(mktemp)"
token() { grep -o 'name="csrf-token" content="[^"]*"' | sed 's/.*content="//;s/"$//'; }

TOKEN=$(curl -s -c "$JAR" -b "$JAR" "$BASE/login" | token)
CODE=$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" -X POST "$BASE/login" \
  --data-urlencode "_token=$TOKEN" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS")
echo "login -> $CODE"

FAIL=0
for spec in "$@"; do
  path="${spec%%:*}"; expect="200"
  [[ "$spec" == *:* ]] && expect="${spec##*:}"
  got=$(curl -s -o /tmp/ff_smoke_body -w '%{http_code}' -c "$JAR" -b "$JAR" "$BASE$path")
  mark="ok"; [[ "$got" != "$expect" ]] && { mark="FAIL"; FAIL=1; }
  printf "%-4s %-60s %s (want %s)\n" "$mark" "$path" "$got" "$expect"
  if [[ "$mark" == "FAIL" && "$got" == "500" ]]; then
    grep -o '<title>[^<]*' /tmp/ff_smoke_body | head -1
    grep -o '"message":"[^"]*' /tmp/ff_smoke_body | head -1
  fi
done
rm -f "$JAR"
exit $FAIL
