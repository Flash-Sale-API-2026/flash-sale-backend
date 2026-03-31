## Scripts

### `smoke-test-kong.sh`

Basic local smoke test for the Kong development gateway.

What it checks:
- `/auth/login` is publicly reachable through Kong
- public `GET /inventory/events` is reachable without JWT
- protected inventory write route rejects requests without JWT
- protected order write route rejects requests without JWT

Usage:

```bash
./scripts/smoke-test-kong.sh
```

Optional:

```bash
KONG_BASE_URL=http://127.0.0.1:8080 ./scripts/smoke-test-kong.sh
```

### `smoke-test-debezium.sh`

End-to-end local smoke test for the authenticated order flow and Debezium delivery.

What it checks:
- registers a fresh user through Kong
- seeds a fresh event and ticket in inventory
- reserves that ticket through Kong
- places an authenticated order through Kong
- waits until the inventory consumer marks the ticket as `sold`
- verifies the `order.created` event was recorded as processed in inventory inbox

Usage:

```bash
./scripts/smoke-test-debezium.sh
```

Optional:

```bash
KONG_BASE_URL=http://127.0.0.1:8080 ./scripts/smoke-test-debezium.sh
```

### `queue-depth.sh`

Prints the current `orders` queue depth and consumer count.

Usage:

```bash
./scripts/queue-depth.sh
```

### `run-k6-checkout-load-test.sh`

Runs the repeatable reservation contention proof with `k6`.

What it does:
- seeds a fresh inventory event with a configurable number of tickets
- registers a unique authenticated user for each load-test iteration
- sends concurrent `POST /inventory/events/{event}/checkout` requests through Kong
- verifies the final event summary still matches the no-oversell expectation

Usage:

```bash
sh ./scripts/run-k6-checkout-load-test.sh
```

Optional:

```bash
LOADTEST_TICKETS=50 LOADTEST_ITERATIONS=250 LOADTEST_VUS=100 LOADTEST_SETUP_TIMEOUT=5m sh ./scripts/run-k6-checkout-load-test.sh
```

### Helpful Make targets

```bash
make smoke-debezium
make loadtest
make queue-depth
make debezium-logs
make consumer-logs
```
