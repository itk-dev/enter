<?php

declare(strict_types=1);

namespace App\Source\MtmSpatialMaps;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\AbstractSource;
use App\Source\DataSourceReader;
use App\Source\Manifest\Catalog;
use App\Source\Manifest\Descriptor;

/**
 * Disabled parking bays in Aarhus Municipality.
 */
final readonly class HandicapParking extends AbstractSource
{
    private const string KEY = 'mtm_spatialmaps-handicap-parking';

    public function __construct(
        private DataSourceReader $reader,
        private Wgs84Transformer $transformer,
        private Catalog $catalog,
    ) {
        parent::__construct(
            id: 'mtm_spatialmaps-handicap-parking',
            title: 'Handicapparkering, Aarhus Kommune',
            description: 'Disabled parking bays in Aarhus Municipality, with the number of reserved bays per location.',
            publisher: 'Aarhus Kommune',
            contact: 'ppg@aarhus.dk',
            landingPage: 'https://www.opendata.dk/city-of-aarhus/parkering-i-aarhus-kommune',
            accessUrl: 'https://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=invap',
            mediaType: 'application/geo+json',
            crs: 'EPSG:25832',
            model: 'OnStreetParking',
            contextUrl: 'https://raw.githubusercontent.com/smart-data-models/dataModel.Parking/master/context.jsonld',
            updateFrequency: 'continuous',

            omittedFields: [
                'ident' => 'Single-letter code; its meaning is not documented and not confirmed by the data owner.',
                'oprettet_af' => 'Directory username of the municipal employee who created the record.',
                'rettet_af' => 'Directory username of the municipal employee who last edited the record.',
                'oprettet_dato' => 'Describes the register record.',
                'rettet_dato' => 'Describes the register record.',
                'mi_style' => 'MapInfo rendering style, empty throughout the export.',
            ],
        );
    }

    public function entities(): iterable
    {
        $source = $this->catalog->get(self::KEY);

        // The export is a GeoJSON FeatureCollection, so the records live under
        // `features`. Iterating the document itself would walk its two
        // top-level keys instead.
        foreach ($this->reader->read($source->accessUrl)['features'] ?? [] as $feature) {
            if (\is_array($feature) && null !== $entity = $this->toEntity($feature, $source)) {
                yield $entity;
            }
        }
    }

    /**
     * Maps one feed record onto an NgsiEntity.
     *
     * @param array<string, mixed> $feature GeoJSON Feature
     */
    private function toEntity(array $feature, Descriptor $source): ?NgsiEntity
    {
        $row = $feature['properties'] ?? null;
        $geometry = $feature['geometry'] ?? null;

        if (!\is_array($row) || !\is_array($geometry)) {
            return null;
        }

        // mi_prinx is the feed's stable primary key. Without it there is no
        // way to address the same bay again on the next import, and an upsert
        // would create duplicates instead of updating.
        $key = $row['mi_prinx'] ?? null;
        if (null === $key || '' === $key) {
            return null;
        }

        $entity = new NgsiEntity(
            \sprintf('urn:ngsi-ld:%s:aarhus-handicap-%s', $source->model, $key),
            $source->model
        );

        return $entity
            ->setProperty('name', $this->address($row))
            ->setProperty('description', trim((string) ($row['bemrk'] ?? '')))
            ->setProperty('category', ['forDisabled'])
            ->setProperty('totalSpotNumber', (int) ($row['invalidepladser'] ?? 0))
            ->setProperty('source', $source->accessUrl)
            ->geoProperty('location', $this->transformer->transformGeometry($source->crs, $geometry));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function address(array $row): string
    {
        return trim(\sprintf(
            '%s %s',
            trim((string) ($row['vejnavn'] ?? '')),
            trim((string) ($row['husnnr'] ?? ''))
        ));
    }
}
