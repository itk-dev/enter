<?php

declare(strict_types=1);

namespace App\Test\Source\Osm;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\AbstractSource;
use App\Source\DataType;
use App\Test\Source\TestDefinition;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Public toilets in Aarhus Municipality.
 */
#[When('dev')]
#[When('test')]
#[TestDefinition(
    // By convention the ID as a test source must start with `test:`
    id: 'test:osm-public-toilet',
    title: 'Test: Offentlige toiletter (OpenStreetMap), Aarhus Kommune',
    accessUrl: 'http://nginx:8080/test/data/overpass-api.de/api/interpreter?osm-public-toilet',
    dataType: DataType::Overpass,
    mediaType: 'application/json',
    crs: 'EPSG:4326',
    model: 'PublicToilet',
    contextUrl: 'https://schema.org/docs/jsonldcontext.json',
    omittedFields: [
        'amenity' => 'Selector; every record is published under the one model this source names.',
        'building' => 'States that the toilet occupies a building of its own; a fact about the structure rather than the facility.',
    ],
    dataUrlBase: 'https://overpass-api.de/api/interpreter',
    dataUrlQuery: [
        'data' => <<<'DATA'
[out:json][timeout:180];
area(3601784663)->.a;
nwr["amenity"="toilets"](area.a);
out center tags;
DATA,
    ]
)]
final class TestPublicToilet extends AbstractSource
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
