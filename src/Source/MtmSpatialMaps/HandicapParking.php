<?php

declare(strict_types=1);

namespace App\Source\MtmSpatialMaps;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\FeedReader;
use App\Source\SourceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Disabled parking bays in Aarhus Municipality, exported from SpatialMap.
 * https://webkort.aarhuskommune.dk/spatialmap?page=get_geojson_opendata&datasource=invap.
 *
 * Published at site level as OnStreetParking rather than as a ParkingGroup
 * subdivision: the feed describes locations with a count of reserved bays and
 * nothing above them, and ParkingGroup requires a parent site this source does
 * not contain. See ADR 006, and ADR 005 rule 2 for the principle behind it.
 *
 * @see https://github.com/smart-data-models/dataModel.Parking/tree/master/OnStreetParking
 */
final class HandicapParking implements SourceInterface
{
    /**
     * The CRS(coordinate reference system) this feed publishes - SRID (Spatial reference identifier).
     */
    private const SOURCE_SRID = 'EPSG:25832';

    public function __construct(
        private readonly FeedReader $reader,
        private readonly Wgs84Transformer $transformer,
        #[Autowire(env: 'ENTER_MTM_SPATIALMAPS_HANDICAP_PARKING_SOURCE')]
        private readonly string $location,
    ) {
    }

    public function key(): string
    {
        return 'mtm_spatialmaps-handicap-parking';
    }

    public function entities(): iterable
    {
        // The export is a GeoJSON FeatureCollection, so the records live under
        // `features`. Iterating the document itself would walk its two
        // top-level keys instead.
        foreach ($this->reader->read($this->location)['features'] ?? [] as $feature) {
            if (\is_array($feature) && null !== $entity = $this->toEntity($feature)) {
                yield $entity;
            }
        }
    }

    /**
     * @param array<string, mixed> $feature GeoJSON Feature
     */
    private function toEntity(array $feature): ?NgsiEntity
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
            \sprintf('urn:ngsi-ld:OnStreetParking:aarhus-handicap-%s', $key),
            'OnStreetParking'
        );

        // `forDisabled` rather than ParkingGroup's `onlyDisabled`: the two
        // models have separate category enums, so values are not interchangeable.
        // `onStreet` is dropped because the entity type already states it.
        return $entity
            ->property('name', $this->address($row))
            ->property('description', trim((string) ($row['bemrk'] ?? '')))
            ->property('category', ['forDisabled'])
            ->property('totalSpotNumber', (int) ($row['invalidepladser'] ?? 0))
            ->property('source', $this->location)
            ->geoProperty('location', $this->transformer->geometry(self::SOURCE_SRID, $geometry));
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
