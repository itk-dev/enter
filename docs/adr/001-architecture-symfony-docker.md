# 001: Architecture — Symfony 8 on the ITK Dev Docker template

| Field              | Value                                  |
|--------------------|----------------------------------------|
| **Created By**     | Jeppe Krogh                            |
| **Date**           | 2026-08-24                             |
| **Decision Maker** | ITK Dev team                           |
| **Stakeholders**   | ITK Dev developers, future maintainers |
| **Status**         | Draft                                  |

## Context

This application reads open data sets, converts them to a standard smart-city
representation, and publishes them to a context broker. The organisation
maintains its PHP services on a versioned Docker template carrying shared CI
and coding-standards configuration. This ADR serves to decide the runtime,
framework and development environment the application is built on.

### Drivers

- **Functional:** scheduled console commands, outbound HTTP, and a local broker
  to import into. No database and no HTTP surface of its own.
- **Non-functional:** shared tooling rather than reimplemented tooling, minimal
  onboarding, reproducible across developers and CI, long-term vendor support.

### Options Considered

1. **PHP 8.4 / Symfony 8 on the maintained template.** CI, coding standards and
   task runner come for free, and its console suits scheduled imports; it
   provisions services this application never uses, and its PHP runs ahead of
   developer hosts.
2. **A minimal project on the host, without the template.** No unused services
   and no container requirement, but shared configuration is rebuilt by hand
   and a local broker needs containers anyway, moving the requirement rather
   than removing it.
3. **A second entry point in an existing internal application.** One deployment
   to operate, but couples a batch importer to a user-facing release cycle and
   inherits dependencies it has no use for.
4. **A different language ecosystem on a bespoke setup.** Richer geospatial
   libraries, but no internal expertise and no shared tooling; the needed
   transformations exist as mature libraries in the established stack.

## Decision

**PHP 8.4 + Symfony 8** on the ITK Dev Docker template, as its **own deployable
service**, with a containerised broker overlay for local development.

- Standardising costs less over the application's lifetime than trimming unused
  services: a second toolchain must be learned and patched; idle containers
  cost only disk.
- A batch importer's lifecycle and failure modes differ from a user-facing
  application's, so it stays its own service.
- No domain persistence is needed — the broker is the system of record — so the
  template's database is left unused rather than removed, keeping template
  updates a clean diff.
- Local development includes a real broker, so imports are verified end to end
  rather than only as serialised output.

## Consequences

### Positive

- Onboarding cost close to zero; CI and coding standards work from the first
  commit.
- No schema, no migrations, no state to keep consistent with the broker.

### Negative / Trade-offs

- Containers are mandatory; dependency management, console commands and tests
  cannot run natively.
- A web server, database and mail catcher are provisioned and never used.
- Broker images are not published for every CPU architecture, so local start-up
  may be slow under emulation.
- The application follows the template's choices; deviating later has a cost.
