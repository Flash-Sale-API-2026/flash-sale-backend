#!/bin/sh

set -eu

base_url="${KONG_BASE_URL:-http://127.0.0.1:8080}"
auth_service_health_url="${AUTH_SERVICE_HEALTH_URL:-http://127.0.0.1:8001/up}"

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

require_command curl

is_http_ok() {
  url="$1"
  status="$(curl -sS -o /dev/null -w '%{http_code}' "$url" || true)"
  [ "$status" = "200" ]
}

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

auth_login_status="$(request_status POST /auth/login -H 'Content-Type: application/json' -d '{"email":"nobody@example.com","password":"wrong-password"}')"
if [ "$auth_login_status" = "502" ]; then
  echo "FAIL: auth route is mapped in Kong, but auth-service is not reachable upstream." >&2
  echo "Start auth-service locally and retry. Expected health URL: $auth_service_health_url" >&2
  exit 1
fi

if ! is_http_ok "$auth_service_health_url"; then
  echo "FAIL: auth-service is not running locally at $auth_service_health_url" >&2
  exit 1
fi

assert_status_in "auth route is publicly reachable through Kong" "$auth_login_status" "401" "422"

inventory_unauthorized_status="$(request_status POST /inventory/events/1/checkout -H 'Content-Type: application/json' -d '{}')"
assert_status "inventory write route rejects requests without JWT" "401" "$inventory_unauthorized_status"

order_unauthorized_status="$(request_status POST /orders -H 'Content-Type: application/json' -d '{}')"
assert_status "order write route rejects requests without JWT" "401" "$order_unauthorized_status"

echo "Kong smoke test passed."
