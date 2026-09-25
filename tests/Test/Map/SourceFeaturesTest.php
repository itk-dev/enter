<?php

declare(strict_types=1);

namespace App\Tests\Test\Map;

use App\Broker\BrokerReader;
use App\Source\SourceInterface;
use App\Test\Map\SourceFeatures;
use App\Tests\Support\FakeSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SourceFeaturesTest extends TestCase
{
    private const string MINE = 'https://mine.example/feed';
    private const string THEIRS = 'https://theirs.example/feed';
    private const string CONTEXT = 'https://mine.example/context.jsonld';

    public function testItKeepsOnlyTheFeaturesOfTheSourceAsked(): void
    {
        $features = $this->read([
            $this->feature('a', self::MINE),
            $this->feature('b', self::THEIRS),
            $this->feature('c', self::MINE),
        ]);

        $this->assertSame(['a', 'c'], array_column(array_column($features, 'properties'), 'id'));
    }

    /**
     * The broker holds entities under the type the source's context expands
     * its model to, so it is asked for the model under that context.
     */
    public function testItAsksForTheModelUnderTheSourcesOwnContext(): void
    {
        $response = $this->response([]);

        new SourceFeatures(new BrokerReader(new MockHttpClient($response), 10000))
            ->forSource(FakeSource::create('Mine', self::MINE, model: 'PublicToilet', contextUrl: self::CONTEXT));

        parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);

        $this->assertSame('PublicToilet', $query['type']);
        $this->assertStringContainsString(
            '<'.self::CONTEXT.'>',
            implode(' ', $response->getRequestOptions()['normalized_headers']['link'])
        );
    }

    /**
     * The broker answers for one type at a time, so sources publishing into
     * different models are read separately, and sources sharing one are not.
     */
    public function testItReadsOncePerModel(): void
    {
        $asked = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$asked): MockResponse {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $asked[] = $query['type'];

            return $this->response(match ($query['type']) {
                'OnStreetParking' => [$this->feature('bay', self::MINE), $this->feature('other-bay', self::THEIRS)],
                'PublicToilet' => [$this->feature('toilet', 'https://toilets.example/feed')],
                default => [],
            });
        });

        $collection = new SourceFeatures(new BrokerReader($client, 10000))->forSources([
            'mine' => FakeSource::create('Mine', self::MINE, id: 'mine'),
            'theirs' => FakeSource::create('Theirs', self::THEIRS, id: 'theirs'),
            'toilets' => FakeSource::create('Toilets', 'https://toilets.example/feed', id: 'toilets', model: 'PublicToilet'),
        ]);

        $this->assertSame(['OnStreetParking', 'PublicToilet'], $asked);
        $this->assertSame(
            ['bay' => 'mine', 'other-bay' => 'theirs', 'toilet' => 'toilets'],
            array_column(array_column($collection['features'], 'properties'), 'dataset', 'id')
        );
    }

    /**
     * An attribute the source's context has no term for comes back expanded.
     */
    public function testItNamesAttributesAsTheSourceDeclaredThem(): void
    {
        $feature = $this->feature('a', self::MINE);
        $feature['properties']['https://uri.etsi.org/ngsi-ld/default-context/surface'] = ['type' => 'Property', 'value' => 'asphalt'];

        $features = $this->read([$feature]);

        $this->assertSame('asphalt', $features[0]['properties']['surface']);
        $this->assertArrayNotHasKey('https://uri.etsi.org/ngsi-ld/default-context/surface', $features[0]['properties']);
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
     * A source whose access URL carries a query stamps an object on its
     * entities rather than an address, and no data set is addressed by that.
     */
    public function testItPassesOverAFeatureWhoseSourceIsNotAnAddress(): void
    {
        $feature = $this->feature('a', self::MINE);
        $feature['properties']['source']['value'] = ['url' => self::MINE, 'query' => ['tid' => 8]];

        $this->assertSame([], $this->read([$feature]));
    }

    /**
     * @param list<array<string, mixed>> $features
     *
     * @return list<array<string, mixed>>
     */
    private function read(array $features): array
    {
        $collection = new SourceFeatures(new BrokerReader(new MockHttpClient($this->response($features)), 10000))
            ->forSource($this->source(self::MINE));

        return $collection['features'];
    }

    /**
     * What the broker answers, given the source's context: a collection whose
     * attributes carry the short names the context gives them.
     *
     * @param list<array<string, mixed>> $features
     */
    private function response(array $features): MockResponse
    {
        return new MockResponse(
            json_encode(['type' => 'FeatureCollection', 'features' => $features]),
            ['response_headers' => [
                'content-type' => ['application/geo+json'],
                'ngsild-results-count' => [(string) \count($features)],
            ]]
        );
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
                'type' => 'OnStreetParking',
                'totalSpotNumber' => ['type' => 'Property', 'value' => 6],
                'source' => ['type' => 'Property', 'value' => $source],
                'location' => ['type' => 'GeoProperty', 'value' => ['type' => 'Point', 'coordinates' => [10.2, 56.1]]],
            ],
        ];
    }

    private function source(string $accessUrl): SourceInterface
    {
        return FakeSource::create('Mine', $accessUrl);
    }
}
