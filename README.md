# Enter

We use [DDEV](https://ddev.com/) and [Task](https://taskfile.dev/) for development:

``` shell
task site:install

``` shell
task site:update
ddev launch
```

Run `task` to see what cool task are available. Running `ddev` can help with other stuff.

## Broker

A [Scorpio Broker](https://scorpio.readthedocs.io/) is part of the development setup.

``` shell
ddev exec "curl --silent http://scorpio.local:9090/ngsi-ld/v1/types | jq"
```

Load some example data:

``` shell name=import-toilet
ddev console app:broker:entity:delete toilet
ddev console app:broker:import:geojson toilet 'https://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=andre_toiletter'
ddev exec "curl --silent http://scorpio.local:9090/ngsi-ld/v1/entities --get --data-urlencode type=toilet" | jq '.[]|with_entries(select([.key] | inside(["id", "type", "location"])))'
```

``` shell name=import-handicapparkering
ddev console app:broker:entity:delete handicapparkering
ddev console app:broker:import:geojson handicapparkering 'https://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=invap'
ddev exec "curl --silent http://scorpio.local:9090/ngsi-ld/v1/entities --get --data-urlencode type=handicapparkering" | jq '.[]|with_entries(select([.key] | inside(["id", "type", "location"])))'
```

``` shell name=import-hundeskov
ddev console app:broker:entity:delete hundeskov
ddev console app:broker:import:geojson hundeskov 'https://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=hundeskove_friluftsliv_aarhus'
ddev exec "curl --silent http://scorpio.local:9090/ngsi-ld/v1/entities --get --data-urlencode type=hundeskov" | jq '.[]|with_entries(select([.key] | inside(["id", "type", "location", "geometry"])))'
```

### Broker API request examples

<https://scorpio.readthedocs.io/en/latest/API_walkthrough.html#entity-creation>

``` shell name=scorpio-entity-create substitutions="{«entity-type»: Room, «entity-id»: 'house2:smartrooms:room1'}"
ddev exec "curl --silent http://scorpio.local:9090/ngsi-ld/v1/entities --header 'content-type: application/json' --data @-" <<'JSON'
{
 "type": "«entity-type»",
 "id": "«entity-id»"
}
JSON


# EPSG:4326?!
ddev exec "curl --silent http://scorpio.local:9090/ngsi-ld/v1/entities/«entity-id»/attrs --header 'content-type: application/json' --data @-" <<'JSON'
{
 "location": {
  "type": "geo:json",
  "value": {
   "type": "Point",
   "coordinates": [
      10.15711687080293, 56.126271111641266
   ]
  }
 }
}
JSON


# Get the entities
ddev exec "curl --silent --header 'accept: application/ld+json' http://scorpio.local:9090/ngsi-ld/v1/entities?type=«entity-type» | jq"

# Get the entities as GeoJSON
ddev exec "curl --silent --header 'accept: application/geo+json' http://scorpio.local:9090/ngsi-ld/v1/entities?type=«entity-type» | jq"
```

> [!CAUTION]
> Excuse me what?!
>
> ``` shell
> ddev exec "curl --silent --header 'accept: application/geo+json' 'http://scorpio.local:9090/ngsi-ld/v1/entities?georel=near;maxDistance%3D%3D2000&geometry=Point&coordinates=%5B8,40%5D'"
> ddev exec "curl --silent --header 'accept: application/geo+json' 'http://scorpio.local:9090/ngsi-ld/v1/entities?georel=near;maxDistance==2000&geometry=Point&coordinates=%5B8,40%5D'"
> ddev exec "curl --silent --header 'accept: application/geo+json' 'http://scorpio.local:9090/ngsi-ld/v1/entities?georel=near;maxDistance==2000&geometry=Point&coordinates=[10,56]'"
> > ```

## GeoJSON

* "[Position](https://datatracker.ietf.org/doc/html/rfc7946#section-3.1.1)": `(longitude, latitude)`
  * "[Coordinate Reference System](https://datatracker.ietf.org/doc/html/rfc7946#section-4)"
  * But … <https://gis.stackexchange.com/a/437311>

    <https://www.opendata.dk/city-of-aarhus/toiletter-i-aarhus-kommune> → <https://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=andre_toiletter>:

    ```json
    {
      "type": "FeatureCollection",
      "crs": {
        "type": "name",
        "properties": {
          "name": "EPSG:25832"
        }
      },
      "bbox": [563262.0903961, 6209995.05346621, 579587.800694027, 6231954.36230685],
      "features": [
        {
          "type": "Feature",
          "geometry": {
            "type": "MultiPoint",
            "coordinates": [
              [576933.018139537, 6218035.22691216]
            ]
          },
          "properties": {
    …
    ```

* <https://geojson.com/>

---

* <https://http.dev/tools>
* <https://httpbin.io/>
* <https://epsg.io/transform#s_srs=4326&t_srs=25832&x=NaN&y=NaN>
* <https://fiware-datamodels.readthedocs.io/en/stable/guidelines/index.html#modelling-location>

Must `location` be a `Point` in ngsi-ld?

``` shell
ddev exec --service scorpio-db "psql ngb ngb"
```

``` sql
2026-08-30 10:26:19.789 UTC [41] LOG:  execute 0000000: WITH D0 AS (SELECT ID, ENTITY, TRUE as PARENT FROM ENTITY WHERE ST_DWithin( location::geography, ST_SetSRID(ST_GeomFromGeoJSON('{"type": "Point", "coordinates": [10.236808047161853,56.101226991176155] }'), 4326)::geography, 2000.0)  ORDER BY createdAt limit $1 offset $2) SELECT ID, ENTITY, PARENT FROM D0
```

<https://postgis.net/docs/ST_GeomFromGeoJSON.html>

``` shell
ddev exec --service scorpio-db "psql ngb ngb" <<< "SELECT id , e_types, location FROM entity;"
```

``` shell name=hmm
ddev console app:import:geojson toilet 'https://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=andre_toiletter' \
    && ddev exec --service scorpio-db "psql ngb ngb" <<< "SELECT id , e_types, ST_AsText(location) AS location FROM entity WHERE id = 'toilet:0000';" \
    && ddev exec --service scorpio-db "psql ngb ngb" <<< "SELECT temporalentity_id, ST_AsText(location) AS location, ST_asText(geovalue) AS geovalue, createdat FROM temporalentityattrinstance WHERE temporalentity_id = 'toilet:0000' ORDER BY createdat DESC LIMIT 10;"
```

<https://www.opendata.dk/search?q=res_format:GeoJSON%20organization:city-of-aarhus>

* [Public toilets in Aarhus Municipality](https://www.opendata.dk/city-of-aarhus/toiletter-i-aarhus-kommune)
  * [Offentlige
    toiletter](http://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=andre_toiletter):
    <http://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=andre_toiletter>
* [Parking in Aarhus Municipality – Zones, Permits and
  Spaces](https://www.opendata.dk/city-of-aarhus/parkering-i-aarhus-kommune)
  * [Handicapparkering](https://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=invap):
    <https://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=invap>

* <https://enter.ddev.site:33001/data/ngsi-ld/v1/types>
