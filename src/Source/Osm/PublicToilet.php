<?php

declare(strict_types=1);

namespace App\Source\Osm;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\AbstractSource;
use App\Source\DataType;
use App\Source\Definition;

/**
 * Public toilets in Aarhus Municipality.
 */
#[Definition(
    id: 'osm-public-toilet',
    title: 'Offentlige toiletter (OpenStreetMap), Aarhus Kommune',
    description: 'Public toilets mapped in OpenStreetMap within Aarhus Municipality, with the wheelchair access they state.',
    publisher: 'OpenStreetMap contributors',
    contact: 'https://community.openstreetmap.org/',
    landingPage: 'https://wiki.openstreetmap.org/wiki/Tag:amenity%3Dtoilets',

    // The Overpass QL in the URL: within Aarhus Municipality (OSM
    // relation 1784663), select every element tagged as a toilet.
    accessUrl: [
        'url' => 'https://overpass-api.de/api/interpreter',
        'query' => [
            'data' => <<<'DATA'
[out:json][timeout:180];
area(3601784663)->.a;
nwr["amenity"="toilets"](area.a);
out center tags;
DATA,
        ],
    ],
    dataType: DataType::Overpass,
    mediaType: 'application/json',
    crs: 'EPSG:4326',
    model: 'PublicToilet',
    contextUrl: 'https://schema.org/docs/jsonldcontext.json',
    updateFrequency: 'continuous',
    licence: 'https://opendatacommons.org/licenses/odbl/1-0/',

    omittedFields: [
        'amenity' => 'Selector; every record is published under the one model this source names.',
        'building' => 'States that the toilet occupies a building of its own; a fact about the structure rather than the facility.',
    ],
)]
final class PublicToilet extends AbstractSource
{
    /**
     * Maps one feed record onto an NgsiEntity.
     *
     * @param array<string, mixed> $data Overpass JSON element
     */
    public function createNgsiEntity(array $data, Wgs84Transformer $transformer): ?NgsiEntity
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
            \sprintf('urn:ngsi-ld:%s:aarhus-toilet-osm-%s-%d', $this->definition->model, $type, $id),
            $this->definition->model
        );

        return $entity
            ->setProperty('name', trim((string) ($tags['name'] ?? '')))
            ->setProperty('description', trim((string) ($tags['description'] ?? '')))
            ->setProperty('openingHours', trim((string) ($tags['opening_hours'] ?? '')))
            ->setProperty('isAccessibleForFree', $this->isAccessibleForFree($tags))
            ->setProperty('source', $this->definition->accessUrl)
            ->geoProperty('location', $transformer->transformGeometry($this->definition->crs, $geometry))

            // Facility facts the model has no attribute for, carried as the
            // feed states them. A record carries only the tags it has.
            //
            // Two of them are stated by a general tag and a toilets: prefixed
            // refinement: the general one describes the place the record sits
            // on, which may be larger than the toilet, and the refinement
            // describes the toilet itself. Both are kept, because a place and
            // the toilet within it can differ.
            ->additionalInformation([
                'wheelchair' => trim((string) ($tags['wheelchair'] ?? '')),
                'toiletsWheelchair' => trim((string) ($tags['toilets:wheelchair'] ?? '')),
                'changingTable' => trim((string) ($tags['changing_table'] ?? '')),
                'toiletsChangingTable' => trim((string) ($tags['toilets:changing_table'] ?? '')),
                'disposal' => trim((string) ($tags['toilets:disposal'] ?? '')),
                'position' => trim((string) ($tags['toilets:position'] ?? '')),
                'handwashing' => trim((string) ($tags['toilets:handwashing'] ?? '')),
                'paperSupplied' => trim((string) ($tags['toilets:paper_supplied'] ?? '')),
                'unisex' => trim((string) ($tags['unisex'] ?? '')),
                'indoor' => trim((string) ($tags['indoor'] ?? '')),
                'seasonal' => trim((string) ($tags['seasonal'] ?? '')),
                'supervised' => trim((string) ($tags['supervised'] ?? '')),
                'access' => trim((string) ($tags['access'] ?? '')),
            ]);
    }

    /**
     * The fee tag states whether using the toilet costs anything. Only its two
     * plain values map; an untagged or unrecognised value states nothing about
     * charging rather than assuming it is free.
     *
     * @param array<string, mixed> $tags
     */
    private function isAccessibleForFree(array $tags): ?bool
    {
        return match ($tags['fee'] ?? null) {
            'no' => true,
            'yes' => false,
            default => null,
        };
    }

    /**
     * The query requests "out center tags", so a way or relation carries only
     * a single representative point, never full vertex or bounds geometry.
     *
     * @param array<string, mixed> $element
     *
     * @return array{type: string, coordinates: array{float, float}}|null
     */
    private function geometry(array $element): ?array
    {
        return match ($element['type'] ?? null) {
            'node' => $this->point($element['lon'] ?? null, $element['lat'] ?? null),
            'way', 'relation' => $this->point(
                $element['center']['lon'] ?? null,
                $element['center']['lat'] ?? null
            ),
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
}
