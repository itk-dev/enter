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
- Scorpio broker as a Compose service in `docker-compose.override.yml`, with a
  `docker-compose.server.override.yml` counterpart.

### Changed

- Replaced DDEV with the itk-dev Docker setup (`symfony-8` template): removed
  `.ddev/`, rewrote `Taskfile.yml` to run through `itkdev-docker-compose`, and
  updated the README accordingly.
- `APP_BROKER_BASE_URI` now points at the `scorpio` Compose service rather than
  the DDEV-only `scorpio.local` hostname.
- `task test` runs the whole suite; it previously ran only `tests/Unit`.

### Removed

- The `mariadb` service, which no part of the project uses.

[Unreleased]: https://github.com/itk-dev/enter
