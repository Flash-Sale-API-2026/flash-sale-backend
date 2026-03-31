# ADR 0001: Use `FOR UPDATE SKIP LOCKED` For Ticket Reservation

- Status: Accepted
- Date: 2026-03-31

## Context

The core business problem is high-contention reservation. Many users may try to reserve a small number of tickets at the same time.

If multiple workers block on the same candidate row with naive pessimistic locking, throughput collapses and reservation latency becomes unstable.

Using a shared integer counter for "available tickets" was rejected because it turns the hottest part of the sale into a single write hotspot and makes oversell protection more fragile.

## Decision

Each ticket remains a physical row in the `tickets` table.

Reservation queries use row-level pessimistic locking with `FOR UPDATE SKIP LOCKED` against reservable tickets.

This allows concurrent workers to skip rows already locked by another transaction and continue looking for the next eligible ticket.

## Consequences

Positive:

- strongly reduces oversell risk
- preserves correctness under concurrent writes
- scales better than naive `FOR UPDATE`
- keeps the concurrency control inside PostgreSQL, where the inventory truth already lives

Trade-offs:

- implementation is PostgreSQL-oriented
- query logic is more advanced than a simple `UPDATE counter = counter - 1`
- developers need to understand why a physical-row model was chosen instead of a shared counter

## Rejected Alternatives

- Shared `available_tickets` counter in the `events` table
- Optimistic updates with retry loops as the primary concurrency mechanism
- Direct in-memory locking without PostgreSQL row locking as the source of truth
