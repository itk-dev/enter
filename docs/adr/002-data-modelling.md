# 002: Data modelling

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-31                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

The selected context broker carries attributes and references a vocabulary,
but it does not define the data model. We have a great focus on interoperability,
so selecting a standardized model for data, seems to be the way to go.

## Decision

A few different options seem to be available, but since Smart Data Models
is the de-facto vocabulary of the NGSI-LD ecosystem, we choose to go with that.
Which model a given data set uses is a decision for that data set, and will
each include its own ADR.

Coverage is uneven, so some data sets will have no existing model to fit.
When this happens, we will draft a new model contribution, add a temporary
vocabulary document and submit it to the program.

Where a type has to come from somewhere other than Smart Data Models, only the
type comes across. Geometry, addresses, units and timestamps follow the same
conventions everywhere, so that a single query still reaches the whole estate.

## Consequences

### Easier

- Types and attributes resolve to shared global identifiers that consumers may
  already have code for.
- A later data set is likely covered by a model already, so onboarding it does
 not start with designing a vocabulary.
- What we publish stands on its own, with nothing invented to keep in sync.
- A missing model does not stall a data set, because using it and submitting it
 are separate steps.
- Contributing is cheap. The program is built for quick turnaround, so we submit
 work we had to do anyway.

### Harder

- The model is part of the entity identifier, so changing our minds later comes
  with some work.
- Versioning is loose, so a model can change without an obvious signal.
- The catalogue is uneven, so each data set costs an assessment before it costs a
  mapping: the model may be missing, shaped by an older paradigm, or simply a poor
  fit for the source.
- The linked-data annotations in the schemas are documentation, not enforcement,
  so validation stays ours to run.
