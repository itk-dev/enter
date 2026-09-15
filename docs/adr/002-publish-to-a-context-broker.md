# 002: Publication mechanism — publish to a context broker

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-24                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

This application republishes data held in operational systems, in heterogeneous
formats and coordinate systems, to consumers the organisation neither controls
nor can brief. The source systems stay authoritative; the published copy is not
a system of record. This ADR serves to decide the mechanism by which data is
published to consumers.

### Drivers

- **Functional:** query by location and attribute rather than bulk download
  only, several data sets on one consumer-facing surface, change notification,
  and adding a data set without changing consumer integrations.
- **Non-functional:** interpretable by consumers never spoken to, served through
  client tooling that already exists, operational cost proportionate to the data
  and its consumers, and an interface that outlives individual data sets.

### Options Considered

1. **An NGSI-LD context broker.** Geospatial and attribute queries, pagination,
   subscriptions and a temporal interface without implementing them, and
   payloads carrying a vocabulary reference; substantial operational weight, and
   the strictness of a particular implementation is inherited.
2. **A bespoke REST API** over a datastore of our own. Exact fit and full
   control of the query surface and its semantics; every capability from
   geo-queries to notifications, clients and versioning is ours to build, and
   consumers must learn an interface that exists nowhere else.
3. **Direct database access or a read replica.** No API layer, powerful ad-hoc
   querying; exposes internal schema as a public contract, needs per-consumer
   credentials and network access, and is unusable by browser-based consumers.

## Decision

Publish to an **NGSI-LD context broker**.

1. Consumers of geographic data ask for an area or a kind more often than for a
   whole data set, and a broker offers that as a standard interface rather than
   a per-data-set feature.
2. Structure published without a vocabulary reference obliges every consumer to
   hold separate documentation; a broker payload carries the reference.
3. A single small data set would be served better by a static export. Across
   several heterogeneous ones the fixed operational cost is paid once, the
   per-data-set cost approaches zero, and consumers integrate once rather than
   once per source.
4. New consumers require no change here, new data sets none from consumers, and
   existing viewers, dashboards and connectors already speak this interface.

The value taken is interoperability and query, not storage. Revisit once
consumers have had time to appear: if none read the data through the interface,
a static export was the better decision.

## Consequences

### Positive

- Geospatial and attribute queries, pagination, subscriptions and a temporal
  interface, none of which are implemented here.
- Payloads reference a shared vocabulary, so they need no bespoke documentation.
- Additional data sets reach every existing consumer with no integration work.
- This application keeps no database and no read surface of its own.

### Negative / Trade-offs

- Operational weight out of proportion to a small data set: several services to
  run, patch, monitor and back up.
- Broker implementations impose constraints beyond the standard. Those met
  include one accepted spelling of a UTC timestamp while an equivalent is
  rejected, the vocabulary reference required on reads where omission returns an
  empty success rather than an error, and single-element lists collapsed to
  scalars.
- Vocabulary documents may be fetched over the network during writes, making
  third-party availability part of the import path.
- Upsert creates and updates but never removes, so records that disappear
  upstream persist until reconciliation is built.
- JSON-LD is a learning curve for maintainers and consumers, and fitting data to
  a shared vocabulary costs effort that publishing as-is would not.
