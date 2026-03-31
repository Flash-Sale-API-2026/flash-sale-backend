# ADR 0002: Use Transactional Outbox With Debezium

- Status: Accepted
- Date: 2026-03-31

## Context

`order-service` must create an order and emit an integration event that other services can trust.

If the application writes the order to the database and then publishes directly to RabbitMQ in the same request path, a crash between those two operations creates a classic consistency gap:

- the order may exist without the event
- the downstream inventory update may never happen

Polling the outbox table with a custom worker was considered, but the project explicitly aims to demonstrate a WAL-based CDC approach.

## Decision

`order-service` writes:

- the `orders` row
- the `outbox_messages` row

inside one database transaction.

Debezium Server reads committed outbox rows from PostgreSQL WAL and publishes them to RabbitMQ using the Outbox Event Router transform.

Consumer-side delivery is treated as at-least-once. The downstream inventory side records processed event ids in `inbox_messages` for idempotency.

## Consequences

Positive:

- removes the direct DB-write-then-publish race
- keeps publisher logic simple inside the request path
- demonstrates a production-style CDC pattern instead of table polling
- makes message replay/recovery align with WAL-backed database truth

Trade-offs:

- operationally more complex than a custom polling worker
- requires logical replication configuration in PostgreSQL
- still needs idempotent consumers because delivery is at-least-once
- outbox retention/cleanup remains a separate concern

## Rejected Alternatives

- Direct RabbitMQ publish from the HTTP request transaction boundary
- Custom polling outbox worker as the primary event delivery mechanism
- Cross-service synchronous updates instead of integration events
