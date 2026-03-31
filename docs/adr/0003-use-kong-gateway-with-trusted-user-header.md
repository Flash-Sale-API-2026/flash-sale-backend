# ADR 0003: Use Kong Gateway With Trusted User Header Propagation

- Status: Accepted
- Date: 2026-03-31

## Context

Write operations such as ticket reservation and order creation must be tied to a trusted identity.

Letting downstream services trust a raw client-provided `user_id` would be incorrect because clients can spoof it.

At the same time, forcing each microservice to implement its own JWT parsing and validation duplicates security logic and increases drift risk.

## Decision

Kong is used as the local API gateway.

The contract is:

- auth endpoints remain public
- Kong validates JWTs on protected write routes
- Kong forwards a trusted `X-Internal-User-Id` header downstream
- downstream services trust that internal header instead of a raw request payload field

Public read routes remain unauthenticated through the same gateway.

## Consequences

Positive:

- centralizes token validation at the gateway boundary
- removes spoofable `user_id` input from protected business operations
- makes the public-read / protected-write split explicit
- matches a production-style gateway pattern without putting auth logic in every service

Trade-offs:

- services are coupled to a trusted gateway contract
- direct access to downstream write endpoints must stay restricted
- claim extraction at the gateway layer is additional infrastructure logic to maintain

## Rejected Alternatives

- Let every service trust client-supplied `user_id`
- Let every service independently parse and validate access tokens
- Keep all endpoints public and move ownership checks into request payload semantics
