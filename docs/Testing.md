# Testing

For local testing and development we use a test controller that's only enabled in the `dev` and `test` environments.

Load some local test data from a test data source (only available in the `dev` and `test` environments):

```shell
docker compose exec phpfpm php bin/console app:source:import test:mtm_spatialmaps-handicap-parking
curl 'http://enter.local.itkdev.dk/data/ngsi-ld/v1/entities?type=https://smartdatamodels.org/dataModel.Parking/OnStreetParking'
```

See the result on <https://enter.local.itkdev.dk/test>.
