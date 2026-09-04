# 001: Architecture — Symfony 8 on the ITK Dev Docker template

| Field              | Value                                  |
|--------------------|----------------------------------------|
| **Created By**     | Jeppe Krogh                            |
| **Date**           | 2026-08-24                             |
| **Decision Maker** | ITK Dev team                           |
| **Stakeholders**   | ITK Dev developers, future maintainers |
| **Status**         | Draft                                  |

## Context

The adapter reads open data sets, converts them to a standard smart-city
representation, and publishes them to a context broker. It needs a runtime, an
HTTP client, a console for running imports, and a local development environment
including a broker to import into. It has no web UI and no domain data of its
own.

ITK Dev maintains a fleet of PHP services with an established Docker-based
development convention, expressed as versioned project templates with shared CI
and coding-standards configuration. A new application either adopts that or
diverges from it.

This ADR serves to decide the runtime, framework and development
environment the application is built on.

### Drivers

- **Functional:** scheduled console commands; outbound HTTP; a local broker.
  No database and no HTTP surface of its own.
- **Non-functional:** minimal onboarding cost; shared tooling rather than
  reimplemented tooling; reproducible across developers and CI; long-term
  vendor support.

### Options Considered

1. **PHP 8.4 / Symfony 8 on the ITK Dev `symfony-8` template.** Matches the
   organisation's existing stack, so CI, coding standards and task runner come
   for free; the console component suits scheduled imports. Provisions a web
   server, database and mail catcher this application never uses, and its PHP
   version runs ahead of developer hosts, making containers mandatory.
2. **Minimal framework project without the template, run on the host.** No
   unused services, no container requirement for the application — but shared
   CI and coding-standards config would be reimplemented by hand, and a local
   broker needs containers anyway, so the dependency is moved rather than
   removed.
3. **A second entry point in an existing internal application.** One
   deployment to operate, but couples a batch importer's release cycle to a
   user-facing application and inherits dependencies it has no use for.
4. **A different language ecosystem on a bespoke setup.** Richer geospatial
   libraries in some ecosystems, but no internal expertise and no shared
   tooling. The transformations needed are available as mature libraries in the
   established stack too.

## Decision

**PHP 8.4 + Symfony 8** on the ITK Dev `symfony-8` template, as its **own
deployable service**, with a containerised broker overlay for local development.

- Standardising costs less over the application's lifetime than trimming unused
  services. A second toolchain must be learned and patched; idle containers
  cost only disk.
- A batch importer and a user-facing application have different lifecycles and
  failure modes, so they stay separate services.
- No domain persistence is needed — the broker is the system of record — so the
  template's database service is left unused rather than removed, keeping
  template updates a clean diff.
- Local development includes a real broker, so imports are verified end to end
  rather than only as serialised output.

## Consequences

### Positive

- Onboarding cost close to zero; CI and coding standards work from the first
  commit.
- No schema, no migrations, no state to keep consistent with the broker.

### Negative / Trade-offs

- **Containers are mandatory.** The template's PHP runs ahead of developer
  hosts, so dependency management, console commands and tests cannot run
  natively. Most likely source of first-run confusion.
- A web server, database and mail catcher are provisioned and never used.
- Broker images are not published for every CPU architecture, so local start-up
  may be slow under emulation.
- The application follows the template's choices; deviating later has a cost.
