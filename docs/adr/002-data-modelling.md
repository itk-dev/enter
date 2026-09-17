# 002: Data modelling

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-31                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Resumé

This ADR decides which vocabulary supplies our entity types and attribute names,
and how we map sources onto it. We will adopt Smart Data Models.

## Context

The selected context broker carries attributes and references a vocabulary, 
but it does not define 

## Decision

We will adopt **Smart Data Models**, referencing the relevant domain context
documents alongside the NGSI-LD core context. Which model a given data set uses
is a decision for that data set; this ADR states the policy.

Smart Data Models is the reference vocabulary of the NGSI-LD ecosystem. It is
openly governed, covers the domains we have in scope, and ships the context
documents we need, so adopting it is a URL rather than a project of its own. It
also ships a schema and examples for each model, which lets a disagreement about
how to model something be checked against a specification instead of settled by
preference.

We use an existing model rather than invent a type of our own. An imperfect
standard type is more useful to a consumer than a precise private one.

We never fabricate a value to satisfy a model. An absent attribute is honest,
where a fabricated one cannot be told apart from a measured one. Attributes a
source cannot fill are left unset rather than approximated, defaulted or
inferred. Where a model requires something the source does not have, we take a
sibling model without that requirement, even where its terms are less precise.

Where a model sits in a hierarchy and each level requires a relationship upward,
we publish at the highest level the source can populate — for a source that gives
a location and a count of units within it, that is the top. Publishing below it
would only defer the need for a parent rather than avoid it, and the costs are
not symmetric: acquiring real parents later is a one-off migration, whereas
inventing one means maintaining an entity that every consumer following the
relationship receives as meaningless.

## Consequences

### Easier

- Types and attributes resolve to shared global identifiers that consumers may
  already have code for.
- A later data set is likely covered by a model already, so onboarding it does
  not start with designing a vocabulary.
- What we publish stands on its own, with nothing invented to keep in sync, and
  finer granularity can be added beneath it later.

### Harder

- Many models are built around real-time sensing, so a static inventory leaves
  attributes empty that the model expects to be filled.
- The model is part of the entity identifier, so changing our minds later means
  deleting and publishing again rather than updating in place.
- Enum values have to be read from the schema rather than the examples: where the
  two disagree the schema wins, and values that look equivalent differ between
  sibling models, so they cannot be copied across.
- Versioning is loose, so a model can change without an obvious signal.
- Mapping a source onto a model takes longer than publishing its fields as they
  come, and now and then the fit is poor.
