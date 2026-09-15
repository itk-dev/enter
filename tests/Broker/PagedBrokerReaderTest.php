<?php

declare(strict_types=1);

namespace App\Tests\Broker;

use App\Broker\PagedBrokerReader;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PagedBrokerReaderTest extends TestCase
{
    private const string PATH = '/ngsi-ld/v1/entities';

    /**
     * The page size the reader asks for when the caller names none. A page
     * shorter than this tells it the run is over.
     */
    private const int PAGE_SIZE = 1000;

    public function testItMergesTheFeaturesOfEveryPage(): void
    {
        $client = new MockHttpClient([
            $this->geoJsonPage(self::PAGE_SIZE, hasNext: true),
            $this->geoJsonPage(345, hasNext: false),
        ]);

        $data = new PagedBrokerReader($client, new NullLogger())->readAll(self::PATH, [], []);

        $this->assertCount(1345, $data['features']);
        $this->assertSame(2, $client->getRequestsCount());
    }

    public function testItKeepsTheCollectionAroundTheMergedFeatures(): void
    {
        $client = new MockHttpClient([
            $this->geoJsonPage(self::PAGE_SIZE, hasNext: true),
            $this->geoJsonPage(1, hasNext: false),
        ]);

        $data = new PagedBrokerReader($client, new NullLogger())->readAll(self::PATH, [], []);

        $this->assertSame('FeatureCollection', $data['type']);
        $this->assertArrayHasKey('@context', $data);
    }

    public function testItMergesEntitiesReturnedAsABareList(): void
    {
        $client = new MockHttpClient([
            $this->listPage(self::PAGE_SIZE, hasNext: true),
            $this->listPage(20, hasNext: false),
        ]);

        $data = new PagedBrokerReader($client, new NullLogger())->readAll(self::PATH, [], []);

        $this->assertCount(1020, $data);
    }

    public function testItAsksForEachPageInTurn(): void
    {
        $offsets = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$offsets): MockResponse {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $offsets[] = $query['offset'] ?? null;

            return $this->geoJsonPage(count($offsets) < 3 ? self::PAGE_SIZE : 1, hasNext: count($offsets) < 3);
        });

        new PagedBrokerReader($client, new NullLogger())->readAll(self::PATH, [], []);

        $this->assertSame(['0', '1000', '2000'], $offsets);
    }

    /**
     * A broker that keeps claiming another page must not be followed for ever.
     */
    public function testItStopsFollowingAnEndlessRunOfPages(): void
    {
        $client = new MockHttpClient(fn (): MockResponse => $this->geoJsonPage(self::PAGE_SIZE, hasNext: true));

        new PagedBrokerReader($client, new NullLogger())->readAll(self::PATH, [], []);

        $this->assertSame(100, $client->getRequestsCount());
    }

    public function testItStopsOnAShortPageEvenWhenAnotherIsAnnounced(): void
    {
        $client = new MockHttpClient([
            $this->geoJsonPage(3, hasNext: true),
            $this->geoJsonPage(3, hasNext: false),
        ]);

        $data = new PagedBrokerReader($client, new NullLogger())->readAll(self::PATH, [], []);

        $this->assertCount(3, $data['features']);
        $this->assertSame(1, $client->getRequestsCount());
    }

    /**
     * Paging with a limit the caller named would stop the run wherever that
     * limit ran out, which is the truncation the reader exists to avoid.
     */
    public function testItPagesPastALimitTheCallerNamed(): void
    {
        $limits = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$limits): MockResponse {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $limits[] = $query['limit'] ?? null;

            return $this->geoJsonPage(count($limits) < 2 ? self::PAGE_SIZE : 345, hasNext: count($limits) < 2);
        });

        $data = new PagedBrokerReader($client, new NullLogger())
            ->readAll(self::PATH, ['limit' => 10, 'offset' => 40], []);

        $this->assertCount(1345, $data['features']);
        $this->assertSame(['1000', '1000'], $limits);
    }

    public function testItWarnsWhenItGivesUpOnAnIncompleteResult(): void
    {
        $logger = new class extends NullLogger {
            public int $warnings = 0;

            public function warning(string|\Stringable $message, array $context = []): void
            {
                ++$this->warnings;
            }
        };
        $client = new MockHttpClient(fn (): MockResponse => $this->geoJsonPage(self::PAGE_SIZE, hasNext: true));

        new PagedBrokerReader($client, $logger)->readAll(self::PATH, [], []);

        $this->assertSame(1, $logger->warnings);
    }

    private function geoJsonPage(int $features, bool $hasNext): MockResponse
    {
        return new MockResponse(
            json_encode([
                'type' => 'FeatureCollection',
                'features' => array_fill(0, $features, ['type' => 'Feature']),
                '@context' => 'https://uri.etsi.org/ngsi-ld/v1/ngsi-ld-core-context-v1.8.jsonld',
            ]),
            ['response_headers' => $this->headers($hasNext)]
        );
    }

    private function listPage(int $entities, bool $hasNext): MockResponse
    {
        return new MockResponse(
            json_encode(array_fill(0, $entities, ['id' => 'urn:ngsi-ld:OnStreetParking:x'])),
            ['response_headers' => $this->headers($hasNext)]
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function headers(bool $hasNext): array
    {
        // The context link is always there, so a reader looking for the next
        // page has to pick it out rather than trust that a link means more.
        $links = ['<https://uri.etsi.org/ngsi-ld/v1/ngsi-ld-core-context-v1.8.jsonld>;rel="http://www.w3.org/ns/json-ld#context"'];
        if ($hasNext) {
            $links[] = '</ngsi-ld/v1/entities?offset=1000>;rel="next";type="application/ld+json"';
        }

        return ['content-type' => ['application/json'], 'link' => $links];
    }
}
