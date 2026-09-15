<?php

declare(strict_types=1);

namespace App\Tests\Test\Map;

use App\Broker\PagedBrokerReader;
use App\Source\SourceInterface;
use App\Test\Map\SourceFeatures;
use App\Tests\Support\FakeSource;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SourceFeaturesTest extends TestCase
{
    private const string TYPE = 'https://smartdatamodels.org/dataModel.Parking/OnStreetParking';
    private const string MINE = 'https://mine.example/feed';
    private const string THEIRS = 'https://theirs.example/feed';

    public function testItKeepsOnlyTheFeaturesOfTheSourceAsked(): void
    {
        $features = $this->read([
            $this->feature('a', self::MINE),
            $this->feature('b', self::THEIRS),
            $this->feature('c', self::MINE),
        ]);

        $this->assertSame(['a', 'c'], array_column(array_column($features, 'properties'), 'id'));
    }

    public function testItNamesAttributesAsTheSourceDeclaredThem(): void
    {
        $features = $this->read([$this->feature('a', self::MINE)]);

        $this->assertArrayHasKey('totalSpotNumber', $features[0]['properties']);
        $this->assertArrayNotHasKey('https://smartdatamodels.org/dataModel.Parking/totalSpotNumber', $features[0]['properties']);
    }

    /**
     * A template reads values, not the Property objects NGSI-LD wraps them in.
     */
    public function testItUnwrapsTheValueOfEachAttribute(): void
    {
        $features = $this->read([$this->feature('a', self::MINE)]);

        $this->assertSame(6, $features[0]['properties']['totalSpotNumber']);
    }

    public function testItCarriesTheGeometryThrough(): void
    {
        $features = $this->read([$this->feature('a', self::MINE)]);

        $this->assertSame('Point', $features[0]['geometry']['type']);
    }

    /**
     * The entity type is what the layer is, and the geometry already travels
     * on the feature; repeating either in the popup only adds noise.
     */
    public function testItLeavesOutWhatThePopupHasNoUseFor(): void
    {
        $features = $this->read([$this->feature('a', self::MINE)]);

        $this->assertArrayNotHasKey('type', $features[0]['properties']);
        $this->assertArrayNotHasKey('location', $features[0]['properties']);
    }

    /**
     * A layer draws its features in the order they arrive, so a point that
     * comes before an area ends up underneath it.
     */
    public function testItPutsTheAreasBeforeThePoints(): void
    {
        $features = $this->read([
            $this->feature('point-a', self::MINE),
            $this->feature('area', self::MINE, 'Polygon'),
            $this->feature('point-b', self::MINE),
        ]);

        $this->assertSame(['area', 'point-a', 'point-b'], array_column(array_column($features, 'properties'), 'id'));
    }

    public function testItReturnsAnEmptyCollectionWhenNothingIsTheSourcesOwn(): void
    {
        $features = $this->read([$this->feature('b', self::THEIRS)]);

        $this->assertSame([], $features);
    }

    /**
     * @param list<array<string, mixed>> $features
     *
     * @return list<array<string, mixed>>
     */
    private function read(array $features): array
    {
        $client = new MockHttpClient([
            new MockResponse(
                json_encode(['type' => 'FeatureCollection', 'features' => $features]),
                ['response_headers' => ['content-type' => ['application/geo+json']]]
            ),
        ]);

        $collection = new SourceFeatures(new PagedBrokerReader($client, new NullLogger()))
            ->forSource($this->source(self::MINE), self::TYPE);

        return $collection['features'];
    }

    /**
     * @return array<string, mixed>
     */
    private function feature(string $id, string $source, string $geometry = 'Point'): array
    {
        return [
            'id' => $id,
            'type' => 'Feature',
            'geometry' => ['type' => $geometry, 'coordinates' => [10.2, 56.1]],
            'properties' => [
                'type' => self::TYPE,
                'https://smartdatamodels.org/dataModel.Parking/totalSpotNumber' => ['type' => 'Property', 'value' => 6],
                'https://smartdatamodels.org/source' => ['type' => 'Property', 'value' => $source],
                'location' => ['type' => 'GeoProperty', 'value' => ['type' => 'Point', 'coordinates' => [10.2, 56.1]]],
            ],
        ];
    }

    private function source(string $accessUrl): SourceInterface
    {
        return FakeSource::create('Mine', $accessUrl);
    }
}
