# 001: Publication mechanism

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-24                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

This project deals with a potentially large set of diverse data that is meant to be
published publicly and used by both public and private actors. It is therefore
important to consider how this publication can be made easily digestible by
consumers.

The consumers are not necessarily known nor briefed, so the interface must explain
itself and let data sets come and go without consumers changing anything.

We considered two options: an NGSI-LD context broker or a REST API.

## Decision

We will publish to a **NGSI-LD context broker, specifically Scorpio**.

@TODO Vi har potentielt store mængder af data som skal behandles, og vi har brug
for noget som kan håndtere disse mængder, og som er veldokumenteret.

We have extensive experience with regular REST APIs, but not a lot of experience
with serving geospatial data and working with geospatial queries.

Given the growing interest in digital twins in the municipality, we see the
advantages of using the tools that come with the Scorpio broker, along with the
opportunity to dabble in some of the technology and methodology that relates to
digital twins.

What we take from the broker up front is interoperability and strong querying
@TODO hvorfor er en broker smart ift at outputte GEOJSON.

Data conflation and progressive enrichment will be a large part of this project,
and the broker architecture lends itself to that kind of work. 

We choose Scorpio because it is already used in other projects and its feature
coverage fits the project requirements well. 

## Consequences

### Easier

- Geospatial and attribute queries and pagination arrive as a standard interface
  rather than one we design, document and version ourselves.
- Additional data sets reach every existing consumer with no integration work.
- Subscriptions let consumers be notified when data they care about changes,
  including within a geographic area, without polling.

### Harder

- We run, patch, monitor and back up several services.
- Upsert never removes, so records that disappear upstream persist until we build
  reconciliation.
