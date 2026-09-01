# 003: NGSI-LD representation — normalized form and batch upsert

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-24                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

ADR 002 chooses an NGSI-LD context broker. That leaves two representation
choices open: how attributes are shaped, and which write operation is used.

NGSI v2, the older FIWARE API generation, was not considered viable: it has no
`@context`, so a shared vocabulary cannot be expressed, and the
organisation operates no v2 broker.

This ADR serves to decide how attributes are shaped and which write
operation is used.

### Drivers

- **Functional:** the broker must accept the payload on write; attribute-level
  metadata must be expressible; repeated imports must not duplicate entities.
- **Non-functional:** idempotency; payload size; readability for consumers.

### Options Considered

#### Attribute form

1. **Normalized** — every attribute an object naming its kind. Verbose, but
   the form brokers accept on write and the only one carrying metadata such as
   `observedAt`.
2. **Key-values** — flat `name: value`. Much smaller and easier to read, but
   read-only and cannot carry attribute metadata.

#### Write operation

1. **Batch upsert** — creates or updates. Idempotent when identifiers are
   derived from source keys.
2. **Create** — fails with `409` for identifiers that already exist, so a
   re-import errors rather than refreshing.
3. **Batch replace** — silently drops attributes absent from the payload,
   making partial payloads destructive.

## Decision

Publish **normalized** NGSI-LD with `Content-Type: application/ld+json`, via
**batch upsert**.

- Normalized is the only form accepted on write, so key-values is not a real
  option for a producer.
- Upsert makes imports idempotent: identifiers derive from each source's
  primary key, so re-running updates in place.

## Consequences

### Positive

- Re-imports produce no duplicates and need no prior state.
- Attribute metadata remains available if a source ever supplies it.

### Negative / Trade-offs

- Payloads are considerably larger than key-values.
- Upsert never deletes, so records removed upstream persist until
  reconciliation is built (see ADR 002).
- Consumers unfamiliar with JSON-LD face a learning curve.
