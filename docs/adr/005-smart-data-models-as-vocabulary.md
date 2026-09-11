# 005: Vocabulary — adopt Smart Data Models

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-31                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

NGSI-LD defines how attributes are carried and how a vocabulary is referenced,
not which entity types and attribute names exist. Without one the JSON-LD
context resolves to nothing and consumers need our documentation to interpret
anything. This ADR serves to decide which vocabulary supplies entity types and
attribute names, and the rules for mapping sources onto it.

### Drivers

- **Functional:** types and attributes interpretable without our documentation,
  expressible as a JSON-LD context a broker can resolve, covering the domains in
  scope.
- **Non-functional:** a vocabulary consumers plausibly already know, maintained
  by someone else, at a mapping cost that does not dominate onboarding.

### Options Considered

1. **Smart Data Models.** The openly governed reference vocabulary of the
   NGSI-LD ecosystem, with broad coverage, per-domain context documents, and a
   schema and examples per model to conform against. Depth varies, many models
   assume real-time sensing, required attributes can presuppose a hierarchy a
   source lacks, and versioning is loose.
2. **A vocabulary of our own, with self-hosted context documents.** Exact fit
   and full control of naming and versioning, but nobody else speaks it,
   governance and documentation stay ours indefinitely, and no existing tooling
   recognises the types.
3. **A general-purpose web vocabulary.** Widely recognised and stably governed,
   adequate for names, addresses and descriptions, but with no NGSI-LD
   conventions for geometry or relationships and no domain terms, leaving the
   domains in scope unmodelled.

## Decision

Adopt **Smart Data Models**, referencing the relevant domain context documents
alongside the NGSI-LD core context. Which model a given data set uses is
recorded in its own ADR; this one states policy.

1. **Use an existing model; do not invent a type.** An imperfect standard type
   is more useful to a consumer than a precise private one.
2. **Never fabricate a value to satisfy a model.** An absent attribute is
   honest; a fabricated one is indistinguishable from a measured one.
   - Attributes the source cannot fill are left unset, not approximated,
     defaulted or inferred.
   - Where a model *requires* an attribute the source cannot supply, choose a
     sibling model without the requirement, even if its terms are less precise.
   - In a hierarchy where each level requires a relationship upward, publish at
     the highest level the source can populate — typically the top, for a data
     set giving a location and a count of units within it.

Rationale:

- A context must resolve to terms a consumer recognises, or publishing gains
  nothing over a file, and Smart Data Models ships those documents, so adoption
  is a URL rather than a project.
- Shipped schemas and examples make modelling disagreements checkable against a
  specification instead of settled by preference.
- Required relationships propagate downward, so publishing below the top level
  defers the need for a parent rather than avoiding it, and the costs are
  asymmetric: acquiring real parents later is a one-off migration, whereas never
  acquiring them means maintaining an invented entity that every consumer
  following the relationship receives as meaningless.

## Consequences

### Positive

- Types and attributes resolve to shared global identifiers consumers may
  already have code for.
- A later data set is likely covered already, so onboarding it does not start
  with vocabulary design.
- Published entities are self-contained, with nothing invented to keep in sync,
  and finer granularity can be added beneath them later.

### Negative / Trade-offs

- Models built around real-time sensing carry attributes static inventory
  cannot fill, so many will always be empty.
- Model choice is embedded in entity identifiers, so changing model later means
  deleting and re-publishing rather than updating in place.
- Enum values must be read from the schema rather than the examples: where the
  two disagree the schema is authoritative, and equivalent-looking values differ
  between sibling models, so they cannot be copied across.
- Versioning is loose, so a model can change without an obvious signal.
- Mapping a source to a model takes longer than exposing its fields verbatim,
  and occasionally the fit is poor.
