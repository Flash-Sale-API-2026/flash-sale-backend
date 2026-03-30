#!/bin/sh

set -eu

queue_name="${RABBITMQ_QUEUE_NAME:-orders}"

if ! docker compose ps rabbitmq >/dev/null 2>&1; then
  echo "FAIL: Docker Compose stack is not reachable. Start the flash-sale stack and retry." >&2
  exit 1
fi

docker compose exec -T rabbitmq rabbitmqctl list_queues -q name messages consumers | awk -v queue="$queue_name" '$1 == queue { print "queue=" $1 " messages=" $2 " consumers=" $3 }'
