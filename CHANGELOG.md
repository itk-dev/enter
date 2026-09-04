# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `app:import` command with `--dry-run` and `--limit`, exposed as `task import`.
- `SourceInterface`: extension point for further data sets, discovered through
  `#[AutoconfigureTag('app.source')]`.
- `Wgs84Transformer`: reprojects coordinates from any registered CRS to WGS84,
  for a single position or a whole GeoJSON geometry of any type.
- `FeedReader`: reads a feed from a filesystem path or an http(s) URL and decodes
  it, without interpreting its shape.
- `NgsiEntity`: builds normalized NGSI-LD entities.
- `NgsiLdBroker`: idempotent batch upsert to an NGSI-LD context broker.
- `task broker:entities` for reading entities back out of the broker.
- Architecture Decision Records under `docs/adr`.
- Added test suite
- `config/sources.yaml`: one record per data set, holding the feed URL, its CRS
  and the model it is published as alongside the metadata no code can state —
  owner, contact, licence, update frequency, and the source fields deliberately
  left unpublished with the reason for each. Field names follow DCAT-AP.
- `SourceCatalog` and `SourceDescriptor`: read and validate the manifest,
  failing loudly on a missing or malformed record rather than defaulting.

### Changed

- Sources read their feed URL, CRS and model from `config/sources.yaml` instead
  of holding them in an environment variable and a class constant.

### Removed

- `ENTER_MTM_SPATIALMAPS_HANDICAP_PARKING_SOURCE`, and with it the pattern of
  one environment variable per data set. Where a feed is read from is a fact
  about the data set, not about the environment.

[Unreleased]: https://github.com/itk-dev/enter
