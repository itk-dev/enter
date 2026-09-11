<?php

declare(strict_types=1);

namespace App\Source\Osm;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\AbstractSource;
use App\Source\Definition;

/**
 * Disabled parking in Aarhus Municipality, as mapped in OpenStreetMap.
 *
 * The feed carries two kinds of record: single reserved bays
 * (parking_space=disabled) and parking facilities that reserve bays
 * (capacity:disabled). Both are published as one entity with the number of
 * reserved bays, so the distinction only matters for how that number is read.
 *
 * @see https://github.com/smart-data-models/dataModel.Parking/tree/master/OnStreetParking
 */
final readonly class HandicapParking extends AbstractSource
{
    public Definition $definition;

    public function __construct(
        private Wgs84Transformer $transformer,
    ) {
        $this->definition = new Definition(
            id: 'osm-handicap-parking',
            title: 'Handicapparkering (OpenStreetMap), Aarhus Kommune',
            description: 'Disabled parking mapped in OpenStreetMap within Aarhus Municipality: single reserved bays, and parking facilities that state how many of their bays are reserved. Read via the Overpass API.',
            publisher: 'OpenStreetMap contributors',

            // Community-maintained data, so no one owner answers for it; the
            // forum is where a question about a record is raised.
            contact: 'https://community.openstreetmap.org/',
            landingPage: 'https://wiki.openstreetmap.org/wiki/Tag:parking_space%3Ddisabled',

            // The Overpass QL in the URL: within Aarhus Municipality (OSM
            // relation 1784663), select every element tagged as a disabled
            // parking space (parking_space=disabled) or as reserving bays for
            // disabled parking (capacity:disabled, excluding "no" and "0").
            accessUrl: 'https://overpass-api.de/api/interpreter?data=%5Bout%3Ajson%5D%5Btimeout%3A180%5D%3Barea%283601784663%29-%3E.a%3B%28nwr%5B%22parking_space%22%3D%22disabled%22%5D%28area.a%29%3Bnwr%5B%22capacity%3Adisabled%22%5D%5B%22capacity%3Adisabled%22%21~%22%5E%28no%7C0%29%24%22%5D%28area.a%29%3B%29%3Bout%20geom%20tags%3B',
            mediaType: 'application/json',
            crs: 'EPSG:4326',
            model: 'OnStreetParking',
            contextUrl: 'https://raw.githubusercontent.com/smart-data-models/dataModel.Parking/master/context.jsonld',
            updateFrequency: 'continuous',

            // Republication must attribute "© OpenStreetMap contributors" and
            // share under the same licence.
            licence: 'https://opendatacommons.org/licenses/odbl/1-0/',

            // OpenStreetMap tagging is open-ended, so unlike a fixed-schema
            // feed this cannot list everything an element may carry. These are
            // the recurring tags in the current extract that are not
            // published; coverage figures count records carrying the tag in
            // the September 2026 extract.
            omittedFields: [
                'amenity' => 'Selector distinguishing a single bay (parking_space) from a facility (parking); the model carries no such distinction.',
                'capacity' => 'Published for single bays only; on a facility it counts all bays and would overstate the reserved capacity.',
                'parking' => 'Facility siting (street_side, surface, underground); every record is published under the one model this source names.',
                'orientation' => 'How bays lie relative to the road; the model has no counterpart.',
                'disabled' => 'Access restriction on street-side parking; redundant with the category every entity is published with.',
                'access' => 'Who may enter; mapping it onto permit attributes needs an interpretation the tag values do not support.',
                'fee:conditional' => 'Time-qualified refinement of fee; the category values the plain fee tag maps onto carry no schedule.',
                'surface' => 'Paving material; the model has no counterpart. 6% of records carry it.',
                'wheelchair' => 'Step-free access to the place, not the parking capacity. 3% of records carry it.',
                'capacity:charging' => 'Bays with charging points; a different subset than the reserved bays this data set publishes. 2% of records carry it.',
                'operator' => 'Who runs the facility; a fact about the business rather than its reserved bays. 1% of records carry it.',
                'brand' => 'Commercial brand of the facility; the name already identifies it. Under 1% of records carry it.',
            ],
        );
    }

    /**
     * Maps one feed record onto an NgsiEntity.
     *
     * @param array<string, mixed> $data Overpass JSON element
     */
    public function createNgsiEntity(array $data): ?NgsiEntity
    {
        $type = $data['type'] ?? null;
        $id = $data['id'] ?? null;

        // OSM ids are only unique per element type, so both are needed to
        // address the same object again on the next import.
        if (!\is_string($type) || !\is_int($id)) {
            return null;
        }

        $geometry = $this->geometry($data);
        if (null === $geometry) {
            return null;
        }

        $tags = \is_array($data['tags'] ?? null) ? $data['tags'] : [];

        $entity = new NgsiEntity(
            \sprintf('urn:ngsi-ld:%s:aarhus-handicap-osm-%s-%d', $this->definition->model, $type, $id),
            $this->definition->model
        );

        return $entity
            ->setProperty('name', trim((string) ($tags['name'] ?? '')))
            ->setProperty('description', trim((string) ($tags['description'] ?? '')))
            ->setProperty('category', $this->category($tags))
            ->setProperty('totalSpotNumber', $this->reservedBays($tags))
            ->setProperty('source', $this->definition->accessUrl)
            ->geoProperty('location', $this->transformer->transformGeometry($this->definition->crs, $geometry));
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
