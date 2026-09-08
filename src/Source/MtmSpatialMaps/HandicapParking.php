<?php

declare(strict_types=1);

namespace App\Source\MtmSpatialMaps;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\DataSourceReader;
use App\Source\Manifest\Catalog;
use App\Source\Manifest\Descriptor;
use App\Source\SourceInterface;

/**
 * Disabled parking bays in Aarhus Municipality.
 *
 * @see config/sources.yaml
 * @see https://github.com/smart-data-models/dataModel.Parking/tree/master/OnStreetParking
 */
final readonly class HandicapParking implements SourceInterface
{
    private const string KEY = 'mtm_spatialmaps-handicap-parking';

    public function __construct(
        private DataSourceReader $reader,
        private Wgs84Transformer $transformer,
        private Catalog $catalog,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
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
     * @param array<string, mixed> $feature GeoJSON Feature
     */
    private function toEntity(array $feature, Descriptor $source): ?NgsiEntity
    {
        // A Feature keeps its attributes under `properties` and its geometry
        // beside them, so neither is at the feature's top level.
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

        // `forDisabled` rather than ParkingGroup's `onlyDisabled`: the two
        // models have separate category enums, so values are not interchangeable.
        // `onStreet` is dropped because the entity type already states it.
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
