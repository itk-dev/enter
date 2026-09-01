# Architecture Decision Records

This directory contains Architecture Decision Records (ADRs) for this project.

See [adr.github.io](https://adr.github.io/) for background on the format.

| Number                                          | Title                                                   | Status | Date       |
| ----------------------------------------------- | ------------------------------------------------------- | ------ | ---------- |
| [001](001-architecture-symfony-docker.md)       | Architecture — Symfony 8 on the ITK Dev Docker template | Draft  | 2026-08-24 |
| [002](002-publish-to-a-context-broker.md)       | Publication mechanism — publish to a context broker     | Draft  | 2026-08-24 |
| [003](003-ngsi-ld-representation.md)            | NGSI-LD representation — normalized form, batch upsert  | Draft  | 2026-08-24 |
| [004](004-coordinate-reference-system.md)       | Coordinate reference system — publish WGS84             | Draft  | 2026-08-27 |
| [005](005-smart-data-models-as-vocabulary.md)   | Vocabulary — adopt Smart Data Models                    | Draft  | 2026-08-31 |
| [006](006-onstreetparking-over-parkinggroup.md) | Model selection — OnStreetParking over ParkingGroup     | Draft  | 2026-08-31 |

Numbering follows dependency order: each ADR relies only on lower-numbered
ones. Dates therefore do not run in the same order as numbers.

All ADRs state general policy and name no data set. Concrete per-data-set
mappings are recorded in the README and in the source classes themselves.
