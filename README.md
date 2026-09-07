# Enter

We use [DDEV](https://ddev.com/) and [Task](https://taskfile.dev/) for development:

``` shell
task site:install
```

``` shell
task site:update
ddev launch
```

Run `task` to see what cool task are available. Running `ddev` can help with other stuff.

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
task broker:entities -- OnStreetParking 10                 # read back what landed
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
ddev exec "curl --silent http://scorpio.local:9090/ngsi-ld/v1/types | jq"
```
