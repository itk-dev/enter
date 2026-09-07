<?php

declare(strict_types=1);

namespace App\Source\Osm;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\FeedReader;
use App\Source\SourceCatalog;
use App\Source\SourceDescriptor;
use App\Source\SourceInterface;

/**
 * Disabled parking in Aarhus Municipality, as mapped in OpenStreetMap.
 *
 * The feed carries two kinds of record: single reserved bays
 * (parking_space=disabled) and parking facilities that reserve bays
 * (capacity:disabled). Both are published as one entity with the number of
 * reserved bays, so the distinction only matters for how that number is read.
 *
 * @see config/sources.yaml
 * @see https://github.com/smart-data-models/dataModel.Parking/tree/master/OnStreetParking
 */
final readonly class HandicapParking implements SourceInterface
{
    private const string KEY = 'osm-handicap-parking';

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

        // Overpass wraps the matched OSM objects in an envelope with version
        // and timestamp metadata; the records live under `elements`.
        foreach ($this->reader->read($source->accessUrl)['elements'] ?? [] as $element) {
            if (\is_array($element) && null !== $entity = $this->toEntity($element, $source)) {
                yield $entity;
            }
        }
    }

    /**
     * @param array<string, mixed> $element Overpass JSON element
     */
    private function toEntity(array $element, SourceDescriptor $source): ?NgsiEntity
    {
        $type = $element['type'] ?? null;
        $id = $element['id'] ?? null;

        // OSM ids are only unique per element type, so both are needed to
        // address the same object again on the next import.
        if (!\is_string($type) || !\is_int($id)) {
            return null;
        }

        $geometry = $this->geometry($element);
        if (null === $geometry) {
            return null;
        }

        $tags = \is_array($element['tags'] ?? null) ? $element['tags'] : [];

        $entity = new NgsiEntity(
            \sprintf('urn:ngsi-ld:%s:aarhus-handicap-osm-%s-%d', $source->model, $type, $id),
            $source->model
        );

        return $entity
            ->property('name', trim((string) ($tags['name'] ?? '')))
            ->property('description', trim((string) ($tags['description'] ?? '')))
            ->property('category', $this->category($tags))
            ->property('totalSpotNumber', $this->reservedBays($tags))
            ->property('source', $source->accessUrl)
            ->geoProperty('location', $this->transformer->geometry($source->crs, $geometry));
    }

    /**
     * Every record is disabled parking; the fee tag refines that with the
     * model's charging categories. Only its two plain values map — an
     * untagged or unrecognised value states nothing about charging rather
     * than assuming free.
     *
     * @param array<string, mixed> $tags
     *
     * @return list<string>
     */
    private function category(array $tags): array
    {
        return match ($tags['fee'] ?? null) {
            'yes' => ['forDisabled', 'feeCharged'],
            'no' => ['forDisabled', 'free'],
            default => ['forDisabled'],
        };
    }

    /**
     * Number of reserved bays the record carries.
     *
     * capacity:disabled counts them directly whatever the record is. A single
     * bay (parking_space=disabled) is reserved in its entirety, so its own
     * capacity applies — one when untagged, per the tag's definition. A
     * facility's plain capacity counts all its bays and is never used, and
     * capacity:disabled=yes states that reserved bays exist without counting
     * them, so nothing is published for it.
     *
     * @param array<string, mixed> $tags
     */
    private function reservedBays(array $tags): ?int
    {
        if (null !== $count = $this->count($tags, 'capacity:disabled')) {
            return $count;
        }

        if ('disabled' === ($tags['parking_space'] ?? null)) {
            return $this->count($tags, 'capacity') ?? 1;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $tags
     */
    private function count(array $tags, string $tag): ?int
    {
        $value = $tags[$tag] ?? null;

        return \is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return array{type: string, coordinates: mixed}|null
     */
    private function geometry(array $element): ?array
    {
        return match ($element['type'] ?? null) {
            'node' => $this->point($element['lon'] ?? null, $element['lat'] ?? null),
            'way' => $this->wayGeometry($element['geometry'] ?? null),
            // The feed's output mode carries no member geometry for
            // relations, only their bounding box, so the centre of that box
            // is the best location available.
            'relation' => $this->boundsCentre($element['bounds'] ?? null),
            default => null,
        };
    }

    /**
     * @return array{type: string, coordinates: array{float, float}}|null
     */
    private function point(mixed $longitude, mixed $latitude): ?array
    {
        if (!is_numeric($longitude) || !is_numeric($latitude)) {
            return null;
        }

        return ['type' => 'Point', 'coordinates' => [(float) $longitude, (float) $latitude]];
    }

    /**
     * @return array{type: string, coordinates: mixed}|null
     */
    private function wayGeometry(mixed $vertices): ?array
    {
        if (!\is_array($vertices)) {
            return null;
        }

        $positions = [];
        foreach ($vertices as $vertex) {
            if (!\is_array($vertex) || !is_numeric($vertex['lon'] ?? null) || !is_numeric($vertex['lat'] ?? null)) {
                return null;
            }

            $positions[] = [(float) $vertex['lon'], (float) $vertex['lat']];
        }

        // A way that returns to its first vertex outlines an area — here a
        // bay or a parking lot — so it becomes a Polygon ring rather than a
        // line along its edge. Four positions are a ring's minimum: three
        // corners plus the repeated first.
        if (\count($positions) >= 4 && $positions[0] === $positions[array_key_last($positions)]) {
            return ['type' => 'Polygon', 'coordinates' => [$positions]];
        }

        if (\count($positions) >= 2) {
            return ['type' => 'LineString', 'coordinates' => $positions];
        }

        return null;
    }

    /**
     * @return array{type: string, coordinates: array{float, float}}|null
     */
    private function boundsCentre(mixed $bounds): ?array
    {
        if (!\is_array($bounds)) {
            return null;
        }

        foreach (['minlon', 'minlat', 'maxlon', 'maxlat'] as $edge) {
            if (!is_numeric($bounds[$edge] ?? null)) {
                return null;
            }
        }

        return $this->point(
            ((float) $bounds['minlon'] + (float) $bounds['maxlon']) / 2,
            ((float) $bounds['minlat'] + (float) $bounds['maxlat']) / 2
        );
    }
}
