# Enter

We use [ITK-dev docker setup] or [DDEV](https://ddev.com/) and [Task](https://taskfile.dev/) for development:

``` shell
task site:install
```

Set

``` dotenv
TASK_USE_DDEV=true
```

in `.env.local` to make `task` use `ddev` rather than `docker compose` for running commands.

``` shell
task site:update
task site:open
```

Run `task` to see what cool task are available.

## Adapter

Takes an open-data set, converts it to [NGSI-LD], and upserts it into the
context broker.

``` text
source feed (JSON)
  → SourceInterface implementation   maps fields, fixes quirks, picks the data model
    → NgsiEntity                     normalized NGSI-LD: Property / GeoProperty / Relationship
      → NgsiLdBroker                 POST /ngsi-ld/v1/entityOperations/upsert
        → context broker
```

``` shell
task import                                                # list the available sources
task import -- mtm_spatialmaps-handicap-parking                        # import one
task import -- mtm_spatialmaps-handicap-parking --dry-run --limit 5    # print the payload instead
```

### Source manifest

Every data set is recorded in [config/sources.yaml](config/sources.yaml), keyed
by the identifier `app:import` takes as its argument. See [ADR 007](docs/adr/007-source-manifest.md).

Adding a data set means adding one `SourceInterface` implementation and one
manifest entry. The class is discovered through
`#[AutoconfigureTag('app.source')]` and shows up as an `app:import` argument
with no further wiring.

Design decisions are recorded in [docs/adr](docs/adr/README.md).

[NGSI-LD]: https://www.etsi.org/committee/cim

## Broker

A [Scorpio Broker](https://scorpio.readthedocs.io/) is part of the development setup.

``` shell
docker compose exec phpfpm curl --silent http://scorpio.local:9090/ngsi-ld/v1/types | jq
```

If you're using the [ITK-dev docker setup], the broker can also be
accessed on <http://scorpio.enter.local.itkdev.dk/>, e.g.

``` php
curl --silent http://scorpio.enter.local.itkdev.dk/ngsi-ld/v1/types | jq
```

[ITK-dev docker setup]: https://github.com/itk-dev/devops_itkdev-docker/
