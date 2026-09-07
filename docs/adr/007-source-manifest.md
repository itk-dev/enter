# 007: Data set metadata — a committed source manifest

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-09-02                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

This application publishes data sets it does not own. Each needs a
description — where it comes from, how to read it, who owns it, on what terms
it may be republished.

This ADR serves to decide where the data sets and their descriptions live.

### Drivers

- **Functional:** each data set is listed with what is needed to read and
  republish it.
- **Non-functional:** adding a data set needs no deployment change, and its
  description is reviewable as a diff.

### Options Considered

1. **One environment variable per data set.** Variable names grow with the
   catalogue, and the environment holds only strings.
2. **Every fact in the class that maps the data set.** Nothing can diverge, but
   the description is readable only by opening code, and correcting a licence
   becomes a code change.
3. **A committed manifest the code reads.** One record per data set, holding
   the description beside the values the import needs; its shape has to be
   declared.
4. **An external catalogue or registry service.** The eventual home of
   published metadata, but a second system to operate, populated before
   anything can be published from it.

## Decision

Keep one **committed manifest** listing every data set this application
publishes, keyed by the identifier the import selects a data set by, and read
from it every fact the code needs.

1. **Record only what the code cannot state.** Field mappings and feed quirks
   stay in the class that maps the data set.
2. **A fact the manifest records is not restated elsewhere**, in code, a
   comment, or documentation.
3. **An incomplete record is an error.** Required fields raise rather than
   default, an unknown fact is recorded as unknown, and the shape is a schema
   the framework validates when the application is built.
4. **Name the fields after DCAT-AP**, the profile the data is registered under,
   so publication is a translation.

## Consequences

### Positive

- Every data set is listed in one place, reviewable as a diff and versioned
  with the code.
- Portal registration and owner questions translate records that already exist.
- A wrong record, or a field the manifest does not define, fails the build.

### Negative / Trade-offs

- Values are identical in every environment, so pointing a data set at a test
  copy means editing a committed file.
- A wrong reference system looks less like code than it is, and yields
  coordinates that are well-formed and misplaced.
- A malformed record blocks every build, not just the import that reads it.
- Fields no code reads have only review keeping them current.
