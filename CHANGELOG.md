# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

* [PR-9](https://github.com/itk-dev/enter/pull/9)
  Refactored source import
* [PR-7](https://github.com/itk-dev/enter/pull/7)
  Refactored source definition
* [#3](https://github.com/itk-dev/enter/pull/3)
  * Import command that reads a geospatial feed, reprojects it to WGS84 and upserts it to an NGSI-LD broker.
  * A committed record per data set — feed, CRS, model, DCAT-AP metadata.
  * An extension point for adding data sets, a test suite, and architecture decision records.
  * Source manifest validated against a Symfony config tree and read during container warm-up, so a malformed
    entry fails the build rather than the one import that selects it.

[Unreleased]: https://github.com/itk-dev/enter
