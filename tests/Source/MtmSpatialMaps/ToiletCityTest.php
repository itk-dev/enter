<?php

declare(strict_types=1);

namespace App\Tests\Source\MtmSpatialMaps;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\MtmSpatialMaps\ToiletCity;
use PHPUnit\Framework\TestCase;

/**
 * Covers the mapping only. Reading the feed is the reader's job.
 */
class ToiletCityTest extends TestCase
{
    private ToiletCity $source;

    /** @var list<array<string, mixed>> */
    private array $entities;

    protected function setUp(): void
    {
        $this->source = new ToiletCity();
        $transformer = new Wgs84Transformer();
        $this->entities = array_values(array_map(
            static fn (NgsiEntity $entity): array => $entity->toPayload(['https://example.com/context.jsonld']),
            array_filter(array_map(
                fn (array $data) => $this->source->createNgsiEntity($data, $transformer),
                $this->features()
            ))
        ));
    }

    public function testItSkipsRecordsWithoutAPrimaryKey(): void
    {
        // Three features, one without mi_prinx.
        $this->assertCount(2, $this->entities);
    }

    public function testItAddressesEntitiesByThePrimaryKey(): void
    {
        $this->assertSame(
            \sprintf('urn:ngsi-ld:%s:aarhus-toilet-city-3', $this->source->definition->model),
            $this->entities[0]['id']
        );
    }

    public function testItFallsBackFromABlankNameToPlaceringsinfo(): void
    {
        // navn is blank; placeringsinfo names the spot.
        $this->assertSame('v/Skolebakken v/Havnens P-Plads', $this->entities[1]['name']['value']);
    }

    public function testItFallsBackToTheAddressWhenNeitherNameNorPlacementIsGiven(): void
    {
        $this->assertSame('Banegårdspladsen 4A', $this->entities[0]['name']['value']);
    }

    public function testItPublishesAPointGeometry(): void
    {
        $geometry = $this->entities[0]['location']['value'];
        $this->assertSame('Point', $geometry['type']);
    }

    public function testItPublishesAMultiPointGeometry(): void
    {
        $geometry = $this->entities[1]['location']['value'];
        $this->assertSame('MultiPoint', $geometry['type']);
    }

    public function testItPublishesNoDescriptionOrCategory(): void
    {
        // This feed carries no accessibility/category signal at all.
        $this->assertArrayNotHasKey('description', $this->entities[0]);
        $this->assertArrayNotHasKey('additionalInformation', $this->entities[0]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function features(): array
    {
        return [
            [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [574856.3599744864, 6223527.5789597845]],
                'properties' => [
                    'status' => 'Aktiv',
                    'familie' => 'Toilet',
                    'subfamilie' => 'TOI Cox',
                    'navn' => ' ',
                    'adresse' => 'Banegårdspladsen 4A',
                    'placeringsinfo' => ' ',
                    'mi_prinx' => 3,
                ],
            ],
            [
                'type' => 'Feature',
                'geometry' => ['type' => 'MultiPoint', 'coordinates' => [[575347.8648020709, 6224178.899330701]]],
                'properties' => [
                    'status' => 'Aktiv',
                    'familie' => 'Toilet',
                    'subfamilie' => 'TOI Cox',
                    'navn' => ' ',
                    'adresse' => 'Skolebakken 6H',
                    'placeringsinfo' => 'v/Skolebakken v/Havnens P-Plads',
                    'mi_prinx' => 5,
                ],
            ],
            [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [574549.8536961579, 6223567.220890785]],
                'properties' => [
                    'status' => 'Aktiv',
                    'navn' => ' ',
                    'adresse' => 'Frederiks Alle 20A',
                    // No mi_prinx — must be skipped.
                ],
            ],
        ];
    }
}
