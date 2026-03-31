#!/bin/sh

set -eu

base_url="${KONG_BASE_URL:-http://127.0.0.1:8080}"
ticket_count="${LOADTEST_TICKETS:-50}"
iteration_count="${LOADTEST_ITERATIONS:-200}"
vu_count="${LOADTEST_VUS:-100}"
max_duration="${LOADTEST_MAX_DURATION:-2m}"
setup_timeout="${LOADTEST_SETUP_TIMEOUT:-5m}"
sales_started_minutes_ago="${LOADTEST_SALES_STARTED_MINUTES_AGO:-5}"

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

require_command curl
require_command docker
require_command php

extract_json_value() {
  key_path="$1"

  php -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($data)) {
        fwrite(STDERR, "Invalid JSON payload\n");
        exit(1);
    }

    $path = explode(".", $argv[1]);
    $value = $data;

    foreach ($path as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            fwrite(STDERR, "Missing JSON key: " . $argv[1] . "\n");
            exit(1);
        }

        $value = $value[$segment];
    }

    if (is_bool($value)) {
        echo $value ? "true" : "false";
        exit(0);
    }

    if (is_scalar($value)) {
        echo (string) $value;
        exit(0);
    }

    fwrite(STDERR, "JSON value is not scalar: " . $argv[1] . "\n");
    exit(1);
  ' "$key_path"
}

assert_equals() {
  label="$1"
  expected="$2"
  actual="$3"

  if [ "$expected" != "$actual" ]; then
    echo "FAIL: $label (expected $expected, got $actual)" >&2
    exit 1
  fi

  echo "PASS: $label ($actual)"
}

ensure_stack_is_running() {
  if ! docker compose ps kong inventory-service auth-service >/dev/null 2>&1; then
    echo "FAIL: Docker Compose stack is not reachable. Run 'make up' first." >&2
    exit 1
  fi
}

seed_event() {
  event_name="Load Test Event $(date '+%Y-%m-%d %H:%M:%S')"

  docker compose exec -T inventory-service php artisan inventory:seed-load-test-event \
    --tickets="$ticket_count" \
    --sales-started-minutes-ago="$sales_started_minutes_ago" \
    --name="$event_name" \
    --format=json
}

seed_users() {
  prefix="k6-load-$(date '+%Y%m%d%H%M%S')"

  docker compose exec -T auth-service php artisan auth:seed-load-test-users \
    --count="$iteration_count" \
    --prefix="$prefix" \
    --format=json
}

request_body_and_status() {
  method="$1"
  path="$2"
  body_file="$3"
  shift 3

  curl -sS -o "$body_file" -w '%{http_code}' -X "$method" "$base_url$path" "$@"
}

ensure_stack_is_running

if [ "$ticket_count" -lt 1 ] || [ "$iteration_count" -lt 1 ] || [ "$vu_count" -lt 1 ]; then
  echo "FAIL: LOADTEST_TICKETS, LOADTEST_ITERATIONS and LOADTEST_VUS must all be at least 1." >&2
  exit 1
fi

seed_json="$(seed_event)"
event_id="$(printf '%s' "$seed_json" | extract_json_value event_id)"
echo "PASS: seeded load test event $event_id with $ticket_count tickets"

users_json="$(seed_users)"
users_count="$(printf '%s' "$users_json" | extract_json_value count)"
assert_equals "seeded load test users for all iterations" "$iteration_count" "$users_count"

docker compose --profile loadtest run --rm --no-deps -T \
  -e K6_BASE_URL=http://kong:8000 \
  -e K6_EVENT_ID="$event_id" \
  -e K6_VUS="$vu_count" \
  -e K6_ITERATIONS="$iteration_count" \
  -e K6_USER_COUNT="$iteration_count" \
  -e K6_MAX_DURATION="$max_duration" \
  -e K6_SETUP_TIMEOUT="$setup_timeout" \
  -e K6_USERS_JSON="$users_json" \
  k6

event_body_file="$(mktemp)"
trap 'rm -f "$event_body_file"' EXIT

event_status="$(request_body_and_status GET "/inventory/events/$event_id" "$event_body_file")"
assert_equals "public inventory event read succeeds after load test" "200" "$event_status"

total_tickets="$(extract_json_value total_tickets < "$event_body_file")"
available_tickets="$(extract_json_value available_tickets < "$event_body_file")"
reserved_tickets="$(extract_json_value reserved_tickets < "$event_body_file")"
sold_tickets="$(extract_json_value sold_tickets < "$event_body_file")"

expected_reserved="$ticket_count"
if [ "$iteration_count" -lt "$ticket_count" ]; then
  expected_reserved="$iteration_count"
fi

expected_available=$((ticket_count - expected_reserved))
occupied_tickets=$((reserved_tickets + sold_tickets))

assert_equals "seeded event kept the requested ticket count" "$ticket_count" "$total_tickets"
assert_equals "load test produced the expected number of active reservations" "$expected_reserved" "$reserved_tickets"
assert_equals "load test did not sell tickets during reservation-only scenario" "0" "$sold_tickets"
assert_equals "load test left the expected number of available tickets" "$expected_available" "$available_tickets"
assert_equals "reserved plus sold tickets matched the demand ceiling" "$expected_reserved" "$occupied_tickets"

echo "k6 checkout load test passed."
