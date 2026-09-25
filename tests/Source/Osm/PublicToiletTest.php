<?php

declare(strict_types=1);

namespace App\Tests\Source\Osm;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\Osm\PublicToilet;
use PHPUnit\Framework\TestCase;

/**
 * Covers the mapping only. Reading the feed is the reader's job.
 *
 * The field mapping here is provisional: overpass-api.de was unreachable
 * when this source was written, so these elements are constructed from the
 * Overpass QL's expected "out center tags" shape rather than a captured
 * live response. Which tags a toilet actually carries in this area is
 * therefore unverified.
 */
class PublicToiletTest extends TestCase
{
    private PublicToilet $source;

    /** @var list<array<string, mixed>> */
    private array $entities;

    protected function setUp(): void
    {
        $this->source = new PublicToilet();
        $transformer = new Wgs84Transformer();
        $this->entities = array_values(array_map(
            static fn (NgsiEntity $entity): array => $entity->toPayload(['https://example.com/context.jsonld']),
            array_filter(array_map(
                fn (array $data) => $this->source->createNgsiEntity($data, $transformer),
                $this->elements()
            ))
        ));
    }

    public function testItSkipsRecordsWithoutAnIdentifierOrGeometry(): void
    {
        // Five elements, of which one has no id.
        $this->assertCount(4, $this->entities);
    }

    public function testItAddressesEntitiesByOsmTypeAndId(): void
    {
        $this->assertSame(
            \sprintf('urn:ngsi-ld:%s:aarhus-toilet-osm-node-1234567890', $this->source->definition->model),
            $this->entities[0]['id']
        );
        $this->assertSame(
            \sprintf('urn:ngsi-ld:%s:aarhus-toilet-osm-way-987654321', $this->source->definition->model),
            $this->entities[1]['id']
        );
        $this->assertSame(
            \sprintf('urn:ngsi-ld:%s:aarhus-toilet-osm-relation-555666777', $this->source->definition->model),
            $this->entities[2]['id']
        );
    }

    public function testItPublishesANodeAtItsOwnCoordinates(): void
    {
        $geometry = $this->entities[0]['location']['value'];

        $this->assertSame('Point', $geometry['type']);
        $this->assertSame([10.2134, 56.1496], $geometry['coordinates']);
    }

    public function testItPublishesAWayOrRelationAtItsCentre(): void
    {
        $geometry = $this->entities[1]['location']['value'];

        $this->assertSame('Point', $geometry['type']);
        $this->assertSame([10.2101, 56.1512], $geometry['coordinates']);
    }

    public function testItPublishesTheNameWhenOneIsMapped(): void
    {
        $this->assertSame('Offentligt toilet', $this->entities[0]['name']['value']);
        $this->assertArrayNotHasKey('name', $this->entities[1]);
    }

    public function testItPublishesTheDescriptionWhenOneIsMapped(): void
    {
        $this->assertSame('Toilet ved parken', $this->entities[1]['description']['value']);
        $this->assertArrayNotHasKey('description', $this->entities[0]);
    }

    public function testItCarriesWheelchairAccessAsAdditionalInformation(): void
    {
        $this->assertSame(
            ['wheelchair' => 'yes'],
            $this->entities[0]['additionalInformation']['value']
        );
        $this->assertSame(
            ['toiletsWheelchair' => 'yes'],
            $this->entities[1]['additionalInformation']['value']
        );
    }

    public function testItCarriesWheelchairAccessTheFeedStatesAsAbsent(): void
    {
        // The query no longer filters on access, so "no" is a real answer
        // rather than a record that would never have been selected.
        $this->assertSame(
            ['wheelchair' => 'no'],
            $this->entities[2]['additionalInformation']['value']
        );
    }

    public function testItOmitsAdditionalInformationWhenNeitherTagIsStated(): void
    {
        $this->assertArrayNotHasKey('additionalInformation', $this->entities[3]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function elements(): array
    {
        return [
            [
                'type' => 'node',
                'id' => 1234567890,
                'lat' => 56.1496,
                'lon' => 10.2134,
                'tags' => [
                    'amenity' => 'toilets',
                    'wheelchair' => 'yes',
                    'name' => 'Offentligt toilet',
                ],
            ],
            [
                'type' => 'way',
                'id' => 987654321,
                'center' => ['lat' => 56.1512, 'lon' => 10.2101],
                'tags' => [
                    'amenity' => 'toilets',
                    'toilets:wheelchair' => 'yes',
                    'description' => 'Toilet ved parken',
                ],
            ],
            [
                'type' => 'relation',
                'id' => 555666777,
                'center' => ['lat' => 56.1523, 'lon' => 10.2088],
                'tags' => [
                    'amenity' => 'toilets',
                    'wheelchair' => 'no',
                ],
            ],
            [
                'type' => 'node',
                'id' => 222333444,
                'lat' => 56.1534,
                'lon' => 10.2075,
                // Tagged as a toilet and nothing more; the query no longer
                // filters on wheelchair access, so such a record is included.
                'tags' => ['amenity' => 'toilets'],
            ],
            [
                'type' => 'node',
                'lat' => 56.16,
                'lon' => 10.22,
                'tags' => ['amenity' => 'toilets', 'wheelchair' => 'yes'],
                // No id — must be skipped.
            ],
        ];
    }
}
