# Flash Sale Architecture Context

## 1. Project Goal
We are building a flash-sale ticket booking system designed for extreme write contention.
The core business constraint is that 50,000 users may try to reserve 1,000 tickets at the same second.

The system must guarantee:
- Zero overselling
- No duplicate reservation for the same user action
- No lost cross-service events when a process crashes between DB write and broker publish

This project is intentionally shaped as a microservices demo with production-style boundaries, not as a monolith.

## 2. Current Tech Stack
- PHP `8.4`
- Laravel `12`
- PostgreSQL `18.3`
- Redis `8.4`
- RabbitMQ `4.2`
- Kong Gateway `3.8` for local API gateway duties
- Debezium Server `3.4.2.Final` for WAL-based outbox delivery
- Docker Compose for local development
- Kubernetes / GitOps infrastructure in a separate infra repository

## 3. Core Architecture
- Each microservice owns its own PostgreSQL database
- Services do not share tables or foreign keys across service boundaries
- Cross-service communication is asynchronous through RabbitMQ
- Data consistency between DB writes and broker publishing is handled through the Transactional Outbox pattern
- High-contention inventory allocation is handled through row-level pessimistic locking with `SKIP LOCKED`

Current local topology:
- `auth-service`
- `inventory-service`
- `inventory-order-consumer`
- `order-service`
- `debezium`
- `kong`
- separate Postgres instance per service
- shared Redis
- shared RabbitMQ

## 4. Bounded Contexts

### Auth Service
Purpose:
- user registration
- login
- refresh token flow
- issuing access tokens and refresh tokens

Key tables:
- `users`
- `refresh_tokens`

Public routes:
- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/refresh`

### Inventory Service
Purpose:
- manage events
- manage ticket inventory
- protect reservation flow under heavy concurrency

Key tables:
- `events`
- `tickets`

Important modeling rule:
- each ticket is a physical row
- we do not use a shared counter like `available_tickets`
- this is required to avoid hotspot contention and overselling bugs

Current write route:
- `POST /inventory/events/{event}/checkout`

Current internal route:
- `POST /api/internal/tickets/{ticket}/reservation/confirm`

### Order Service
Purpose:
- create orders
- persist integration events in outbox storage for Debezium pickup

Key tables:
- `orders`
- `outbox_messages`

Current write route:
- `POST /orders`

## 5. Authentication and Gateway Contract
The project now includes a dedicated auth service and a gateway-auth pattern.

Current contract:
- client authenticates against `auth-service`
- `auth-service` issues JWT access tokens and refresh tokens
- Kong validates JWT on protected routes
- Kong forwards trusted internal identity using `X-Internal-User-Id`
- downstream services trust the gateway header, not a raw client-provided `user_id`

Access policy:
- auth endpoints are public
- write endpoints in inventory and order services require trusted identity
- public read endpoints may remain unauthenticated

This is important:
- services must never trust `user_id` from request payload
- identity for protected operations must come from the gateway contract

## 6. Concurrency and Consistency Rules

### Rule 1: Rate Limiting
Protected write endpoints must be throttled with Laravel rate limiting backed by Redis.

### Rule 2: Duplicate Click Protection
Inventory checkout must use Redis distributed locks to prevent the same user from firing overlapping reserve requests for the same event.

### Rule 3: Inventory Allocation Under Contention
Ticket reservation must use pessimistic locking with `SKIP LOCKED`.

Required shape:
```php
$ticket = Ticket::query()
    ->where('event_id', $eventId)
    ->where('status', 'available')
    ->lockForUpdate()
    ->skipLocked()
    ->first();
```

The point is:
- competing transactions do not queue on the same row
- each worker can grab the next available ticket
- throughput stays much higher than naive `FOR UPDATE`

### Rule 4: Transactional Outbox
Order creation must not directly rely on "write DB, then publish broker message".

Required flow:
1. Open `DB::transaction()`
2. Insert order row
3. Insert outbox row in the same transaction
4. Commit
5. Debezium reads the committed change from PostgreSQL WAL
6. Debezium applies the Outbox Event Router transform
7. Debezium publishes the event to RabbitMQ

Important implications:
- this project uses WAL-based CDC, not table polling
- outbox rows are not acknowledged with `processed_at`
- delivery semantics are at-least-once
- consumers must be idempotent
- `event_id` must be stable and unique for deduplication

Current outbox shape:
- `id`
- `event_id`
- `aggregate_type`
- `aggregate_id`
- `type`
- `payload`
- `created_at`

This is mandatory for the project.

Current documented contract:
- `docs/message-contracts/order.created.json`

## 7. Application Structure Conventions
These conventions are now part of the project style and should be preserved when generating new code.

- Business logic should live in Actions
- External integrations and infrastructure concerns should live in Services
- HTTP input validation should use Form Requests
- HTTP output formatting should use API Resources
- Prefer unit tests for action-level logic first, then feature tests for end-to-end request behavior

Keep controllers thin.

## 8. Local Development Conventions
This repository is now Docker-first.

Important rules:
- local development and runtime are expected to run through Docker Compose
- each app uses its own `apps/*/.env`
- tracked templates are `apps/*/.env.example`
- root `.env` is for Docker Compose and Kong-level variables, not Laravel app config

Do not reintroduce parallel env systems unless there is a strong reason.

Current local compose services:
- `auth-postgres`
- `inventory-postgres`
- `order-postgres`
- `redis`
- `rabbitmq`
- `debezium`
- `kong`
- `auth-service`
- `inventory-service`
- `inventory-order-consumer`
- `order-service`

## 9. Current Implementation Status
Implemented:
- local Docker Compose stack
- isolated Postgres per service
- Kong local gateway setup
- auth service with register / login / refresh
- inventory reservation flow with Redis lock and `SKIP LOCKED`
- order creation with transactional outbox insert
- Debezium local setup for PostgreSQL WAL -> RabbitMQ delivery
- inventory-side inbox/idempotency handling for `order.created`
- inventory RabbitMQ consumer that finalizes reserved tickets as sold
- documented `order.created` message contract
- request validation via Form Requests
- response formatting via Resources
- rate limiting on protected write routes

Still pending or future work:
- outbox cleanup / retention strategy after Debezium delivery
- richer message contracts between services
- read-side/query endpoints for public event browsing
- payment flow
- Kubernetes-native gateway and deployment config in the infra repository

## 10. AI Assistant Guardrails
When generating code for this project:
- do not replace physical ticket rows with a shared counter approach
- do not remove `SKIP LOCKED` from the reservation path
- do not replace the transactional outbox with direct broker publishing inside request flow
- do not replace Debezium WAL-based outbox delivery with polling unless explicitly requested
- do not trust client-supplied `user_id` for protected operations
- do not introduce cross-service database joins or foreign keys
- keep business logic in Actions and external concerns in Services

Follow the existing architecture instead of simplifying it into a monolith pattern.
