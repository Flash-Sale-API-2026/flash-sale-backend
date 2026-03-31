#!/bin/sh

set -eu

base_url="${KONG_BASE_URL:-http://127.0.0.1:8080}"

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

require_command curl
require_command docker

request_status() {
  method="$1"
  path="$2"
  shift 2

  curl -sS -o /dev/null -w '%{http_code}' -X "$method" "$base_url$path" "$@"
}

assert_status() {
  label="$1"
  expected="$2"
  actual="$3"

  if [ "$actual" != "$expected" ]; then
    echo "FAIL: $label (expected $expected, got $actual)" >&2
    exit 1
  fi

  echo "PASS: $label ($actual)"
}

assert_status_in() {
  label="$1"
  actual="$2"
  shift 2

  for expected in "$@"; do
    if [ "$actual" = "$expected" ]; then
      echo "PASS: $label ($actual)"
      return
    fi
  done

  echo "FAIL: $label (expected one of: $*, got $actual)" >&2
  exit 1
}

service_running() {
  service="$1"
  docker compose ps --status running "$service" 2>/dev/null | awk 'NR > 1 { found = 1 } END { exit(found ? 0 : 1) }'
}

auth_login_status="$(request_status POST /auth/login -H 'Content-Type: application/json' -d '{"email":"nobody@example.com","password":"wrong-password"}')"
if [ "$auth_login_status" = "502" ]; then
  echo "FAIL: auth route is mapped in Kong, but auth-service is not reachable upstream." >&2
  if ! service_running auth-service; then
    echo "Current Docker Compose stack does not have a running auth-service container." >&2
  fi
  exit 1
fi

assert_status_in "auth route is publicly reachable through Kong" "$auth_login_status" "401" "422"

inventory_read_status="$(request_status GET /inventory/events)"
assert_status "inventory read route is publicly reachable through Kong" "200" "$inventory_read_status"

inventory_unauthorized_status="$(request_status POST /inventory/events/1/checkout -H 'Content-Type: application/json' -d '{}')"
assert_status "inventory write route rejects requests without JWT" "401" "$inventory_unauthorized_status"

order_unauthorized_status="$(request_status POST /orders -H 'Content-Type: application/json' -d '{}')"
assert_status "order write route rejects requests without JWT" "401" "$order_unauthorized_status"

echo "Kong smoke test passed."
