## Scripts

### `smoke-test-kong.sh`

Basic local smoke test for the Kong development gateway.

What it checks:
- `/auth/login` is publicly reachable through Kong
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

### Helpful Make targets

```bash
make smoke-debezium
make queue-depth
make debezium-logs
make consumer-logs
```
