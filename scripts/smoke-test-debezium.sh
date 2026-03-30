#!/bin/sh

set -eu

base_url="${KONG_BASE_URL:-http://127.0.0.1:8080}"
queue_name="${RABBITMQ_QUEUE_NAME:-orders}"
wait_timeout_seconds="${DEBEZIUM_WAIT_TIMEOUT_SECONDS:-20}"
poll_interval_seconds="${DEBEZIUM_POLL_INTERVAL_SECONDS:-1}"

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

require_command curl
require_command docker
require_command php

ensure_docker_access() {
  if ! docker compose ps rabbitmq inventory-order-consumer kong >/dev/null 2>&1; then
    echo "FAIL: Docker Compose stack is not reachable. Start the flash-sale stack and retry." >&2
    exit 1
  fi
}

request_body_and_status() {
  method="$1"
  path="$2"
  body_file="$3"
  shift 3

  curl -sS -o "$body_file" -w '%{http_code}' -X "$method" "$base_url$path" "$@"
}

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

queue_depth() {
  docker compose exec -T rabbitmq rabbitmqctl list_queues -q name messages | awk -v queue="$queue_name" '$1 == queue { print $2 }'
}

db_scalar() {
  service="$1"
  database="$2"
  sql="$3"

  docker compose exec -T "$service" psql -U flash_sale -d "$database" -t -A -c "$sql" | tr -d '\r' | awk 'NF { print; exit }'
}

seed_inventory_fixture() {
  suffix="$1"

  event_id="$(db_scalar inventory-postgres inventory_service "INSERT INTO events (name, total_tickets, start_sales_at, created_at, updated_at) VALUES ('Smoke Event $suffix', 1, NOW() - INTERVAL '1 minute', NOW(), NOW()) RETURNING id;")"

  if [ -z "$event_id" ]; then
    echo "FAIL: could not seed smoke test event in inventory database." >&2
    exit 1
  fi

  ticket_id="$(db_scalar inventory-postgres inventory_service "INSERT INTO tickets (event_id, seat_number, price, status, created_at, updated_at) VALUES ($event_id, 'SMOKE-$suffix', 149.99, 'available', NOW(), NOW()) RETURNING id;")"

  if [ -z "$ticket_id" ]; then
    echo "FAIL: could not seed smoke test ticket in inventory database." >&2
    exit 1
  fi

  printf '%s %s\n' "$event_id" "$ticket_id"
}

ticket_status() {
  ticket_id="$1"
  db_scalar inventory-postgres inventory_service "SELECT status FROM tickets WHERE id = $ticket_id;"
}

ticket_user_id() {
  ticket_id="$1"
  db_scalar inventory-postgres inventory_service "SELECT COALESCE(user_id::text, '') FROM tickets WHERE id = $ticket_id;"
}

ticket_reserved_until() {
  ticket_id="$1"
  db_scalar inventory-postgres inventory_service "SELECT COALESCE(reserved_until::text, '') FROM tickets WHERE id = $ticket_id;"
}

inventory_inbox_status_for_order() {
  order_id="$1"
  db_scalar inventory-postgres inventory_service "SELECT status FROM inbox_messages WHERE event_type = 'order.created' AND payload->'order'->>'id' = '$order_id' ORDER BY id DESC LIMIT 1;"
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

ensure_docker_access
if [ -z "$(queue_depth)" ]; then
  echo "FAIL: RabbitMQ queue '$queue_name' was not found." >&2
  exit 1
fi

timestamp="$(date +%s)"
unique_suffix="${timestamp}-$$"
email="smoke+$unique_suffix@example.com"
password="Smoke1234"
register_body_file="$(mktemp)"
checkout_body_file="$(mktemp)"
order_body_file="$(mktemp)"
trap 'rm -f "$register_body_file" "$checkout_body_file" "$order_body_file"' EXIT

set -- $(seed_inventory_fixture "$unique_suffix")
event_id="$1"
seeded_ticket_id="$2"

echo "PASS: seeded event $event_id with ticket $seeded_ticket_id in inventory"

register_payload="$(printf '{"name":"Smoke Test","email":"%s","password":"%s","password_confirmation":"%s"}' "$email" "$password" "$password")"
register_status="$(request_body_and_status POST /auth/register "$register_body_file" -H 'Content-Type: application/json' -d "$register_payload")"
assert_status "auth registration succeeds through Kong" "201" "$register_status"

access_token="$(extract_json_value access_token < "$register_body_file")"
user_id="$(extract_json_value user.id < "$register_body_file")"

echo "PASS: auth registration returned an access token for user $user_id"

checkout_status="$(request_body_and_status POST "/inventory/events/$event_id/checkout" "$checkout_body_file" -H "Authorization: Bearer $access_token" -H 'Content-Type: application/json')"
assert_status "authorized reservation request succeeds through Kong" "201" "$checkout_status"

reserved_ticket_id="$(extract_json_value ticket_id < "$checkout_body_file")"
checkout_user_id="$(extract_json_value user_id < "$checkout_body_file")"
assert_status "inventory checkout returned the seeded ticket" "$seeded_ticket_id" "$reserved_ticket_id"
assert_status "gateway forwards trusted user id into inventory-service" "$user_id" "$checkout_user_id"

order_payload="$(printf '{"ticket_id":%s}' "$reserved_ticket_id")"
order_status="$(request_body_and_status POST /orders "$order_body_file" -H "Authorization: Bearer $access_token" -H 'Content-Type: application/json' -d "$order_payload")"
assert_status "authorized order request succeeds through Kong" "201" "$order_status"

order_id="$(extract_json_value id < "$order_body_file")"
order_user_id="$(extract_json_value user_id < "$order_body_file")"
assert_status "gateway forwards trusted user id into order-service" "$user_id" "$order_user_id"

elapsed=0
final_ticket_status=""
final_inbox_status=""
final_ticket_user_id=""
final_reserved_until=""

while [ "$elapsed" -lt "$wait_timeout_seconds" ]; do
  final_ticket_status="$(ticket_status "$reserved_ticket_id")"
  final_ticket_user_id="$(ticket_user_id "$reserved_ticket_id")"
  final_reserved_until="$(ticket_reserved_until "$reserved_ticket_id")"
  final_inbox_status="$(inventory_inbox_status_for_order "$order_id")"

  if [ "$final_ticket_status" = "sold" ] && [ "$final_ticket_user_id" = "$user_id" ] && [ -z "$final_reserved_until" ] && [ "$final_inbox_status" = "processed" ]; then
    echo "PASS: inventory consumer finalized ticket $reserved_ticket_id as sold for user $user_id"
    echo "PASS: inventory inbox recorded order.created for order $order_id as processed"
    echo "Debezium smoke test passed."
    exit 0
  fi

  sleep "$poll_interval_seconds"
  elapsed=$((elapsed + poll_interval_seconds))
done

after_depth="$(queue_depth)"
echo "FAIL: ticket $reserved_ticket_id was not finalized within ${wait_timeout_seconds}s." >&2
echo "Last observed state: ticket_status=${final_ticket_status:-missing} ticket_user_id=${final_ticket_user_id:-missing} reserved_until=${final_reserved_until:-missing} inbox_status=${final_inbox_status:-missing} queue_depth=${after_depth:-missing}" >&2
exit 1
