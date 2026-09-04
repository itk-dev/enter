# 002: Publication mechanism — publish to a context broker

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-24                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

The adapter makes data available to consumers the organisation does
not control and cannot brief. The data already exists in operational systems,
with heterogeneous formats, coordinate systems and access methods. What has to
be decided is the mechanism by which it is published. The source systems remain
authoritative; the published copy is not a system of record.

This ADR serves to decide the mechanism by which data is published to
consumers.

### Drivers

- **Functional:** query by location and attribute, not bulk download only;
  several data sets reaching one consumer-facing surface; change notification;
  adding a data set without changing consumer integrations.
- **Non-functional:** interpretable by consumers we have never spoken to;
  operational cost proportionate to the data and the number of consumers;
  existing client tooling rather than clients we supply; an interface that
  outlives individual data sets.

### Options Considered

1. **An NGSI-LD context broker.** Provides geospatial and attribute queries,
   pagination, subscriptions and a temporal interface without implementing
   them; payloads carry a vocabulary reference, so they are self-describing;
   many producers converge on one consumer surface. Substantial operational
   weight — typically a database and message bus alongside the broker — and the
   strictness of a particular implementation is inherited.
2. **Static file export** on a web server or object store. Near-zero
   operational cost, trivially cacheable, readable by anything. No query, so
   consumers download everything and filter client-side; no change
   notification; conventions must be documented in prose because nothing in the
   file declares its own meaning.
3. **A bespoke REST API** over our own datastore. Exact fit, full control of
   the query surface and semantics. Every capability is ours to build and
   maintain — geo-queries, filtering, pagination, notifications, documentation,
   clients, versioning — and consumers must learn an interface that exists
   nowhere else.
4. **Direct database access or a read replica.** No API layer, powerful ad-hoc
   querying. Exposes internal schema as a public contract, requires per-consumer
   credentials and network access, and is unusable by browser-based consumers.

## Decision

Publish to an **NGSI-LD context broker**.

- Consumers of geographic data need "everything within this area" and
  "everything of this kind" more often than the whole data set. A broker
  provides that as a standard interface rather than a per-data-set feature.
- Publishing structure without a vocabulary reference requires every consumer
  to have our documentation. A broker payload carries the reference.
- For a single small data set a static export would be cheaper and better. Once
  several heterogeneous data sets must be published, the fixed operational cost
  is paid once while the per-data-set cost approaches zero, and consumers
  integrate once rather than once per source.
- New consumers require no change to the adapter, and new data sets require no
  change to consumers.
- Existing viewers, dashboards and connectors speak this interface; a bespoke
  API would mean supplying clients indefinitely.

The broker's value is interoperability and query, not storage. If no consumer
reads the data through its interface, a static export would have been the better
decision. Revisit once data sets have been published long enough for consumers
to appear.

## Consequences

### Positive

- Geospatial and attribute queries, pagination, subscriptions and a temporal
  interface, none of which are implemented here.
- Payloads reference a shared vocabulary, so they need no bespoke
  documentation.
- Additional data sets reach every existing consumer with no integration work.
- The adapter has no database and no read surface of its own.

### Negative / Trade-offs

- **Operational weight out of proportion to a small data set.** A broker
  deployment is several services to run, patch, monitor and back up. For a
  static data set a file on a web server would serve the same need.
- **Broker implementations impose constraints beyond the standard.** Those
  encountered include accepting only one spelling of a UTC timestamp while
  rejecting an equivalent one, requiring the vocabulary reference on read
  requests — with omission returning an empty success rather than an error —
  and collapsing single-element lists to scalars.
- **Vocabulary documents may be fetched over the network during writes**, so
  third-party availability becomes part of the import path.
- **No delete semantics.** Upsert creates and updates but never removes, so
  records that disappear upstream persist until reconciliation is built.
- JSON-LD is a learning curve for maintainers and consumers.
- Fitting data to a shared vocabulary costs effort that publishing as-is
  would not.
