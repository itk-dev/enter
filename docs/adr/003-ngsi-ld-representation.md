# 003: NGSI-LD representation — normalized form and batch upsert

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-24                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

ADR 002 chooses an NGSI-LD context broker, which leaves the representation
open.

This ADR serves to decide how attributes are shaped and which write operation
is used.

### Drivers

- **Functional:** the broker must accept the payload on write, attribute
  metadata must be expressible, and repeated imports must not duplicate
  entities.
- **Non-functional:** idempotency, payload size, readability for consumers.

### Options Considered

#### Attribute form

1. **Normalized.** Every attribute an object naming its kind. Verbose, but the
   form brokers accept on write and the only one carrying metadata.
2. **Key-values.** Flat `name: value`, smaller and easier to read, but
   read-only and unable to carry attribute metadata.

#### Write operation

1. **Batch upsert.** Creates or updates, so a re-import refreshes in place.
2. **Create.** Fails with `409` for an identifier that already exists, so a
   re-import errors rather than refreshing.
3. **Batch replace.** Silently drops attributes absent from the payload,
   making a partial payload destructive.

## Decision

Publish **normalized** NGSI-LD with `Content-Type: application/ld+json`, via
**batch upsert**.

- Normalized is the only form accepted on write, so key-values is not
  available to a producer.
- Identifiers derive from each data set's primary key, so upsert makes a
  re-run update in place rather than add.

## Consequences

### Positive

- Re-imports produce no duplicates and need no prior state.
- Attribute metadata stays expressible where a data set supplies it.

### Negative / Trade-offs

- Payloads are considerably larger than key-values.
- Upsert never deletes, so records removed upstream persist until
  reconciliation is built (see ADR 002).
- Consumers unfamiliar with JSON-LD face a learning curve.
