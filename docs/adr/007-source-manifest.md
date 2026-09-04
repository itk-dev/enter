# 007: Data set metadata — a committed source manifest

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-09-02                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

Each published data set carries facts the import needs — where the feed is read
from, the coordinate reference system its coordinates are in, and the model it
is published as — and facts only people need: who owns the data, on what terms
it may be republished, how often it changes, and which of its fields are
deliberately not published, with the reason for each.

The first group must be readable by code. The second is what a public data
portal requires at registration, and what a data owner asks for when
establishing what happened to their data. Both grow with the number of data
sets.

Recording the two groups separately produces a machine-readable value and a
written description of the same value, which can then disagree. Recording only
the first leaves the rest unwritten, and the questions it answers are then
answered from memory.

This ADR serves to decide where a data set's own facts are recorded, and which
of them the code reads.

### Drivers

- **Functional:** the code reads the facts it needs from the same record a
  person reads; a data set is registerable on a public portal without a fresh
  survey; an incomplete record fails the import that needs it rather than
  producing an incomplete publication.
- **Non-functional:** adding a data set requires no deployment change; the
  record is reviewable as a diff; no fact exists in two places.

### Options Considered

1. **One environment variable per data set.** Follows the convention that
   configuration belongs in the environment, and lets a value differ per
   environment. But variable names grow with the catalogue, so each new data
   set becomes a deployment change; the environment carries strings only,
   leaving metadata beyond an address nowhere to live; and values are invisible
   in review, so a wrong one is found by running the import.
2. **Every fact in the class that maps the data set.** Nothing can diverge,
   there being one copy, and the language enforces its presence. But metadata
   is then readable only by opening code, extracting it for portal registration
   requires writing an extractor, and correcting a licence or a contact becomes
   a code change reviewed as one.
3. **A committed manifest the code reads.** One record per data set, keyed by
   the identifier the import selects it with, holding the facts the code needs
   beside those it does not, in a shape a catalogue profile can be generated
   from. Validating the record becomes work of our own, and the values are
   identical in every environment.
4. **An external catalogue or registry service.** The eventual home of
   published metadata, with search and harvesting already built. But it has to
   be running for an import to work, it is a second system to operate, and it
   must be populated before anything can be published from it — from records
   that would have to live somewhere else in the meantime.

## Decision

Record each data set in a **committed manifest**, keyed by the identifier the
import selects it with, and read from it every fact the code needs.

Four rules follow:

1. **Record only what the code cannot state.** How a feed's fields map onto the
   model, and every quirk of its shape, stay in the class that performs the
   mapping. Restating them in the manifest recreates the divergence the
   manifest exists to prevent.
2. **A fact both the code and a reader need is read from the manifest.** It is
   not also written in code, in a comment, or in the README.
3. **An incomplete or malformed record is an error.** Fields an import cannot
   run without are required, and their absence raises rather than defaulting. A
   fact that is unknown is recorded as unknown, so the gap stays visible.
4. **Name the fields after the catalogue profile the data will be registered
   under** — DCAT-AP — so that publication is a translation rather than a
   redesign.

Rationale:

- What has to be prevented is a value diverging from its description, so the
  two belong to the same record.
- Portal registration and answering a data owner need the same fields, and
  neither can be derived from mapping code.
- A record is reviewed alongside the class that consumes it, so a reviewer sees
  the address, the reference system and the model together with the mapping
  that assumes them.
- Where a feed is read from is a fact about the data set, not about the machine
  running the import, so the environment is the wrong place for it.

## Consequences

### Positive

- One record per data set, reviewable as a diff and versioned with the code.
- Adding a data set is one class and one record, with no deployment change.
- Registration on a public portal is a translation of records that exist.
- Licence, ownership and what was withheld have a single answer, and an
  unanswered question shows as an empty value rather than as nothing at all.
- The same records can generate catalogue entities later without introducing a
  second source of truth.

### Negative / Trade-offs

- **Values are identical in every environment.** Pointing a data set at a copy
  for testing means editing a committed file. That is consistent with reading
  feeds where they live, but it removes an escape hatch the environment offered.
- **A wrong reference system or model in the manifest is as damaging as a wrong
  one in code, while looking less like code.** A wrong reference system yields
  coordinates that are well-formed and in the wrong place.
- **Changing the published model changes entity identifiers.** Editing one
  value can therefore orphan everything already published; see ADR 005 and ADR
  006.
- **Fields no code reads have nothing keeping them current.** Until catalogue
  entities are generated from them, only review does.
- Validating the record is work the environment did not require.
