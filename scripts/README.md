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
