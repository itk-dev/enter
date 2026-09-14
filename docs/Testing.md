# Testing

## Test sources

For local testing and development we use a test controller that's only enabled in the `dev` and `test` environments.

Furthermore, we use static test sources (fetching locally stored data) for testing and development. By convention,
the ID of a test source starts with `test:`, i.e. they can be listed by running

```shell
docker compose exec phpfpm php bin/console app:source:list | grep 'test:'
```

Example: Import and show data from the test source `test:mtm_spatialmaps-handicap-parking`:

```shell
docker compose exec phpfpm php bin/console app:source:import test:mtm_spatialmaps-handicap-parking
docker compose exec phpfpm curl 'http://scorpio:9090/ngsi-ld/v1/entities?type=https://smartdatamodels.org/dataModel.Parking/OnStreetParking'
```

See the result on <https://enter.local.itkdev.dk/test>.

### Refreshing test source data

The data for test sources are stored as plain files in the [../tests/resources/data](../tests/resources/data) folder.

The data files can be updated by running

```shell
docker compose exec phpfpm php bin/console test:source:fetch-content
```

Load *all test sources* with

```shell
docker compose exec phpfpm php bin/console test:source:import
```

To empty your local broker, e.g. before loading test data, run

```shell
docker compose exec phpfpm php bin/console app:broker:entity:delete --all
```
