<?php

declare(strict_types=1);

namespace App\Tests\Source\MtmSpatialMaps;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\FeedReader;
use App\Source\MtmSpatialMaps\HandicapParking;
use App\Source\SourceCatalog;
use App\Source\SourceDescriptor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Runs against the manifest the project ships, not a fixture of one, so the
 * entry this source depends on is covered too. Only the feed is mocked.
 */
class HandicapParkingTest extends TestCase
{
    private const string KEY = 'mtm_spatialmaps-handicap-parking';

    private SourceDescriptor $source;

    private string $requestedUrl;

    /** @var list<array<string, mixed>> */
    private array $entities;

    protected function setUp(): void
    {
        $catalog = new SourceCatalog(\dirname(__DIR__, 3).'/config/sources.yaml');
        $this->source = $catalog->get(self::KEY);

        $client = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->requestedUrl = $url;

            return new MockResponse(json_encode($this->feed(), \JSON_THROW_ON_ERROR));
        });

        $source = new HandicapParking(new FeedReader($client), new Wgs84Transformer(), $catalog);

        $this->entities = array_map(
            static fn (NgsiEntity $entity): array => $entity->toArray(['https://example.com/context.jsonld']),
            iterator_to_array($source->entities(), false)
        );
    }

    public function testItReadsTheFeedTheManifestPointsAt(): void
    {
        $this->assertSame($this->source->accessUrl, $this->requestedUrl);
    }

    public function testItSkipsRecordsWithoutAnIdentifierOrGeometry(): void
    {
        // Four features, of which one has no mi_prinx and one no geometry.
        $this->assertCount(2, $this->entities);
    }

    public function testItTakesIdentifierAndTypeFromTheManifestModel(): void
    {
        $first = $this->entities[0];

        // The id is derived from mi_prinx so that re-importing upserts the
        // same bay instead of creating a duplicate.
        $this->assertSame(
            \sprintf('urn:ngsi-ld:%s:aarhus-handicap-172', $this->source->model),
            $first['id']
        );
        $this->assertSame($this->source->model, $first['type']);
    }

    public function testItJoinsStreetAndHouseNumberIntoName(): void
    {
        $this->assertSame('Domkirkeplads/Bispegade 1', $this->entities[0]['name']['value']);
    }

    public function testItOmitsTheHouseNumberWhenBlank(): void
    {
        // husnnr is "" for this row, so the name must not end in a space.
        $this->assertSame('Brammersgade', $this->entities[1]['name']['value']);
    }

    public function testItMarksEveryEntityAsDisabledParking(): void
    {
        foreach ($this->entities as $entity) {
            $this->assertSame(['forDisabled'], $entity['category']['value']);
        }
    }

    public function testItCarriesTheBayCountAsTotalSpotNumber(): void
    {
        $this->assertSame(6, $this->entities[0]['totalSpotNumber']['value']);
        $this->assertSame(1, $this->entities[1]['totalSpotNumber']['value']);
    }

    public function testItDropsEmptyDescriptions(): void
    {
        // bemrk is null for the first row and filled in for the second.
        $this->assertArrayNotHasKey('description', $this->entities[0]);
        $this->assertSame('Ved indgangen', $this->entities[1]['description']['value']);
    }

    public function testItReprojectsLocationIntoWgs84(): void
    {
        $location = $this->entities[0]['location'];

        $this->assertSame('GeoProperty', $location['type']);
        $this->assertSame('Point', $location['value']['type']);

        // The feed publishes metres in the manifest's CRS, so degrees within
        // Denmark are the evidence that the reprojection ran.
        [$longitude, $latitude] = $location['value']['coordinates'];
        $this->assertGreaterThan(8.0, $longitude);
        $this->assertLessThan(13.0, $longitude);
        $this->assertGreaterThan(54.5, $latitude);
        $this->assertLessThan(57.8, $latitude);
    }

    public function testItRecordsTheManifestUrlAsTheEntitySource(): void
    {
        $this->assertSame($this->source->accessUrl, $this->entities[0]['source']['value']);
    }

    /**
     * The first feature is a record from the live export, kept verbatim. The
     * rest are constructed to exercise a blank house number and the two guards
     * that discard a record.
     *
     * @return array<string, mixed>
     */
    private function feed(): array
    {
        return [
            'type' => 'FeatureCollection',
            'crs' => ['type' => 'name', 'properties' => ['name' => 'EPSG:25832']],
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [575153.9524951308, 6224260.609753487]],
                    'properties' => [
                        'vejnavn' => 'Domkirkeplads/Bispegade',
                        'husnnr' => '1',
                        'invalidepladser' => 6,
                        'bemrk' => null,
                        'ident' => 'P',
                        'oprettet_af' => 'ADM\\aztnbnd',
                        'oprettet_dato' => '2019-03-19 14:51:23.91',
                        'rettet_af' => 'ADM\\aztnbnd',
                        'rettet_dato' => '2019-03-19 14:51:23.91',
                        'mi_style' => null,
                        'mi_prinx' => 172,
                    ],
                ],
                [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [574000.0, 6223000.0]],
                    'properties' => [
                        'vejnavn' => 'Brammersgade',
                        'husnnr' => '',
                        'invalidepladser' => 1,
                        'bemrk' => 'Ved indgangen',
                        'mi_prinx' => 378,
                    ],
                ],
                [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [574100.0, 6223100.0]],
                    'properties' => [
                        'vejnavn' => 'Uden nøgle',
                        'husnnr' => '3',
                        'invalidepladser' => 2,
                    ],
                ],
                [
                    'type' => 'Feature',
                    'geometry' => null,
                    'properties' => [
                        'vejnavn' => 'Uden geometri',
                        'husnnr' => '5',
                        'invalidepladser' => 2,
                        'mi_prinx' => 999,
                    ],
                ],
            ],
        ];
    }
}
