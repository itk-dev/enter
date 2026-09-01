<?php

declare(strict_types=1);

namespace App\Tests\Source\MtmSpatialMaps;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\MtmSpatialMaps\HandicapParking;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

class HandicapParkingTest extends TestCase
{
    private const string PARKING_SITE_URN = 'urn:ngsi-ld:ParkingSite:aarhus-on-street';

    /** @var list<array<string, mixed>> */
    private array $entities;

    protected function setUp(): void
    {
        $source = new HandicapParking(
            new Wgs84Transformer(),
            // Never used: the fixture is read from disk, not over HTTP.
            new MockHttpClient(),
            'data/handicapparkering.json',
            self::PARKING_SITE_URN,
            \dirname(__DIR__, 2),
        );

        $this->entities = array_map(
            static fn (NgsiEntity $entity): array => $entity->toArray(['https://example.com/context.jsonld']),
            iterator_to_array($source->entities(), false)
        );
    }

    public function testItReadsEveryRecordInTheFixture(): void
    {
        $this->assertCount(10, $this->entities);
    }

    public function testItBuildsAParkingGroupWithAStableId(): void
    {
        $first = $this->entities[0];

        // The id is derived from mi_prinx so that re-importing upserts the
        // same entity instead of creating a duplicate.
        $this->assertSame('urn:ngsi-ld:ParkingGroup:aarhus-handicap-261', $first['id']);
        $this->assertSame('ParkingGroup', $first['type']);
    }

    public function testItJoinsStreetAndHouseNumberIntoName(): void
    {
        $this->assertSame('P.P. Ørums Gade 2', $this->entities[0]['name']['value']);
    }

    public function testItOmitsTheHouseNumberWhenBlank(): void
    {
        $brammersgade = $this->entityById('urn:ngsi-ld:ParkingGroup:aarhus-handicap-378');

        // husnnr is "" for this row, so the name must not end in a space.
        $this->assertSame('Brammersgade', $brammersgade['name']['value']);
    }

    public function testItMarksEveryGroupAsOnStreetDisabledParking(): void
    {
        foreach ($this->entities as $entity) {
            $this->assertSame(['onStreet', 'onlyDisabled'], $entity['category']['value']);
        }
    }

    public function testItCarriesTheBayCountAsTotalSpotNumber(): void
    {
        $this->assertSame(2, $this->entities[0]['totalSpotNumber']['value']);
        $this->assertSame(1, $this->entities[1]['totalSpotNumber']['value']);
    }

    /**
     * The feed stores dates as Excel serial numbers with a decimal comma
     * ("43543,6190690972"), which has to become a real timestamp.
     */
    public function testItConvertsExcelSerialDatesToIso8601(): void
    {
        $observedAt = $this->entities[0]['totalSpotNumber']['observedAt'];

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $observedAt);
        $this->assertStringStartsWith('2019-03-', $observedAt);
    }

    public function testItEmitsLocationAsAGeoProperty(): void
    {
        $location = $this->entities[0]['location'];

        $this->assertSame('GeoProperty', $location['type']);
        $this->assertSame('Point', $location['value']['type']);
    }

    public function testItDropsEmptyDescriptions(): void
    {
        // bemrk is "" for the first row and "Navitas" for mi_prinx 215.
        $this->assertArrayNotHasKey('description', $this->entities[0]);
        $this->assertSame(
            'Navitas',
            $this->entityById('urn:ngsi-ld:ParkingGroup:aarhus-handicap-215')['description']['value']
        );
    }

    public function testItRelatesEveryGroupToTheSyntheticParkingSite(): void
    {
        foreach ($this->entities as $entity) {
            $this->assertSame('Relationship', $entity['refParkingSite']['type']);
            $this->assertSame(self::PARKING_SITE_URN, $entity['refParkingSite']['object']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function entityById(string $id): array
    {
        foreach ($this->entities as $entity) {
            if ($id === $entity['id']) {
                return $entity;
            }
        }

        $this->fail(\sprintf('No entity with id "%s".', $id));
    }
}
