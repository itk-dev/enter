<?php

declare(strict_types=1);

namespace App\Source\MtmSpatialMaps;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\FeedReader;
use App\Source\SourceCatalog;
use App\Source\SourceDescriptor;
use App\Source\SourceInterface;

/**
 * Disabled parking bays in Aarhus Municipality, exported from SpatialMap.
 *
 * Where the feed is read from, the CRS it publishes and the model it is
 * published as come from this key's manifest entry; this class owns only the
 * field mapping.
 *
 * Published at site level rather than as a ParkingGroup subdivision: the feed
 * describes locations with a count of reserved bays and nothing above them,
 * and ParkingGroup requires a parent site this source does not contain. See
 * ADR 006, and ADR 005 rule 2 for the principle behind it.
 *
 * @see config/sources.yaml
 * @see https://github.com/smart-data-models/dataModel.Parking/tree/master/OnStreetParking
 */
final readonly class HandicapParking implements SourceInterface
{
    private const string KEY = 'mtm_spatialmaps-handicap-parking';

    public function __construct(
        private FeedReader $reader,
        private Wgs84Transformer $transformer,
        private SourceCatalog $catalog,
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
    private function toEntity(array $feature, SourceDescriptor $source): ?NgsiEntity
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
            ->property('name', $this->address($row))
            ->property('description', trim((string) ($row['bemrk'] ?? '')))
            ->property('category', ['forDisabled'])
            ->property('totalSpotNumber', (int) ($row['invalidepladser'] ?? 0))
            ->property('source', $source->accessUrl)
            ->geoProperty('location', $this->transformer->geometry($source->crs, $geometry));
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
