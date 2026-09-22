# Architecture Decision Records

This directory contains Architecture Decision Records (ADRs) for this project.

See [adr.github.io](https://adr.github.io/) for background on the format.

| Number                              | Title                 | Status | Date       |
| ----------------------------------- | --------------------- | ------ | ---------- |
| [001](001-publication-mechanism.md) | Publication mechanism | Draft  | 2026-08-24 |
| [002](002-data-modelling.md)        | Data modelling        | Draft  | 2026-08-31 |
| [003](003-modeling-approach.md)     | Modeling approach     | Draft  | 2026-09-22 |
| [004](004-parking-model.md)         | Parking model         | Draft  | 2026-09-22 |

Numbering follows dependency order: each ADR relies only on lower-numbered
ones. Dates therefore do not run in the same order as numbers.

All ADRs state general policy and name no data set. Concrete per-data-set
facts are declared on the source classes themselves, alongside the mappings
they describe.
