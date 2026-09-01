# 005: Vocabulary — adopt Smart Data Models

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-31                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

NGSI-LD defines how attributes are carried and how to reference a vocabulary.
It does not define entity types or attribute names. Without a vocabulary the
JSON-LD context resolves to nothing, entity types are local strings, and
consumers still need our documentation to interpret anything.

The choice also determines the cost of onboarding each data set, since mapping
a source onto an existing model takes more effort than exposing its fields
verbatim.

This ADR serves to decide which vocabulary supplies entity types and
attribute names, and the rules for mapping sources onto it.

### Drivers

- **Functional:** types and attributes interpretable without our
  documentation; expressible as a JSON-LD context a broker can resolve;
  coverage across the domains in scope.
- **Non-functional:** a vocabulary consumers plausibly already know; governed
  and maintained by someone else; mapping cost that does not dominate
  onboarding.

### Options Considered

1. **Smart Data Models.** Purpose-built for NGSI-LD and the reference
   vocabulary of that ecosystem; publishes JSON-LD context documents per
   domain; broad coverage; each model ships a JSON schema and examples, giving
   an objective conformance target; open governance. Model depth varies;
   many models assume real-time sensing, so static inventory leaves attributes
   unset; required attributes occasionally presuppose a hierarchy the source
   lacks; enum spellings sometimes disagree between a model's schema and its
   examples; versioning is loose.
2. **A vocabulary of our own, with self-hosted context documents.** Exact fit,
   no required attributes we cannot satisfy, full control of naming and
   versioning. Nobody else speaks it, so consumers return to reading our
   documentation; governance, documentation and versioning become ours
   indefinitely; no existing tooling recognises the types.
3. **A general-purpose web vocabulary.** Widely recognised, stable governance,
   adequate for names, addresses and descriptions. No NGSI-LD conventions for
   geometry or relationships, and no domain-specific terms, so the domains in
   scope would remain unmodelled.

## Decision

Adopt **Smart Data Models**, referencing the relevant domain context documents
alongside the NGSI-LD core context.

Two rules follow, and they matter more than the choice itself:

1. **Use an existing model; do not invent a type.** An imperfect standard type
   is more useful to a consumer than a perfect private one.
2. **Never fabricate a value to satisfy a model.** An absent attribute is
   honest; a fabricated one is indistinguishable from a measured one. Applied:

   - Attributes the source cannot fill are left unset, not approximated,
     defaulted or inferred.
   - Where a model *requires* an attribute the source cannot supply, choose a
     different model rather than inventing the value. Required relationships
     are the common case: inventing a related entity yields something
     schema-valid and factually wrong that must then be maintained
     indefinitely. A sibling model without the requirement is the better
     choice even if its terms are less precise.
   - In a hierarchy — a root site, subdivisions beneath it, individual units
     beneath those, each lower level requiring a relationship upward — publish
     at the highest level the source can populate. Static inventory typically
     describes a location and a count of units without describing what the
     location is part of, so the site level is usually correct.

The concrete model chosen for a given data set is recorded in its own ADR; this
one states policy only.

Rationale:

- The context must resolve to terms a consumer recognises, or publishing gains
  nothing over a file.
- Smart Data Models is the vocabulary the surrounding ecosystem uses and ships
  the context documents needed to reference it, so adoption is a URL rather
  than a project.
- Shipped schemas and examples make modelling disagreements checkable against a
  specification instead of settled by preference.
- Mandatory relationships propagate downward: choosing a subdivision level
  schedules the need for a parent rather than avoiding it, because the
  individual-unit level requires a site as well.
- The costs are asymmetric. Publishing at site level and later finding real
  sites exist means a one-off migration. Publishing at subdivision level and
  never acquiring real sites means maintaining an invented entity
  indefinitely, with every consumer that follows the relationship receiving
  something meaningless.

## Consequences

### Positive

- Types and attributes resolve to shared global identifiers.
- Consumers may already have code for the types published.
- Modelling decisions have an external reference point.
- Later data sets are likely already covered, so onboarding does not start with
  vocabulary design.
- Published entities are self-contained, with nothing invented to keep in sync.
- Finer granularity can be added later beneath what is already published.

### Negative / Trade-offs

- **Many attributes will always be empty.** Models built around real-time
  sensing carry availability, occupancy and detection attributes that static
  inventory cannot fill.
- **Model choice is embedded in entity identifiers.** Changing model later
  means deleting and re-publishing rather than updating in place, so selection
  deserves attention before a data set is first published.
- **Enum values must be read from the schema, not the examples.** Where the two
  disagree the schema is authoritative, and equivalent-looking values differ
  between sibling models, so they must not be copied across.
- **Loose versioning.** A model can change without an obvious signal.
- Mapping a source to a model takes longer than exposing its fields verbatim,
  and occasionally the fit is poor.
