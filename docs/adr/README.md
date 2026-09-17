# Architecture Decision Records

This directory contains Architecture Decision Records (ADRs) for this project.

See [adr.github.io](https://adr.github.io/) for background on the format.

| Number                                          | Title                                               | Status | Date       |
| ----------------------------------------------- | --------------------------------------------------- | ------ | ---------- |
| [001](001-publication-mechanism.md)             | Publication mechanism                               | Draft  | 2026-08-24 |
| [002](002-data-modelling.md)                    | Data modelling                                      | Draft  | 2026-08-31 |
| [006](006-onstreetparking-over-parkinggroup.md) | Model selection — OnStreetParking over ParkingGroup | Draft  | 2026-08-31 |
| [007](007-source-manifest.md)                   | Data set metadata — a committed source manifest     | Draft  | 2026-09-02 |

Numbering follows dependency order: each ADR relies only on lower-numbered
ones. Dates therefore do not run in the same order as numbers.

All ADRs state general policy and name no data set. Concrete per-data-set
facts are declared on the source classes themselves, alongside the mappings
they describe.
