# 002: Publication mechanism

| Field              | Value                                                  |  
|--------------------|--------------------------------------------------------|  
| **Created By**     | Jeppe Krogh                                            |  
| **Date**           | 2026-08-24                                             |  
| **Decision Maker** | ITK Dev team                                           |  
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |  
| **Status**         | Draft                                                  |  

## Resumé

This ADR serves to decide the mechanism by which data is published to consumers.  
The decision is to publish to an NGSI-LD context broker.

## Context

This project deals with a potentially large set of diverse data that is meant  
to be published publicly and used by both public and private actors. Therefore, 
it is important to consider how this publication can be made easily digestible 
by consumers.

The consumers are not necessarily known nor briefed, so the interface must 
explain itself and let data sets come and go without consumers changing anything.  

Two options has been considered: a NGSI-LD context broker or a REST API.

## Decision

Publish to an **NGSI-LD context broker, specifically Scorpio**.

The team have extensive experience with regular REST APIs, but not a lot of
experience with serving geospatial data, and working with geospatial queries.

Due to the growing interest of digital twins in the municipality, we see the 
advantages of utilizing the tools that comes with the Scorpio Broker, 
along with it being a possibility to dabble in some of the technology that
relates to digital twins.

The value taken here, generally is interoperability and querying, but since 
this project is going to touch a lot on data conflation and progressive enrichment,
the broker architecture is quite interesting.

## Consequences

### Easier

- Geospatial and attribute queries and pagination arrive as a standard interface
  rather than one we design, document and version ourselves.
- Payloads reference a shared vocabulary, so terms need no definition from us.
- Additional data sets reach every existing consumer with no integration work. 
- Subscriptions let consumers be notified when data they care about changes, 
  including within a geographic area, without polling.

### Harder

- Several services to run, patch, monitor and back up.
- Vocabulary documents are fetched over the network during writes, making
  third-party availability part of the import path.
- Upsert never removes, so records that disappear upstream persist until
  reconciliation is built.
- NGSI-LD is a learning curve, and fitting data to a shared vocabulary costs effort
  that publishing as-is would not.
