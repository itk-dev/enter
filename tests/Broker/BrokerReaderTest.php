<?php

declare(strict_types=1);

namespace App\Tests\Broker;

use App\Broker\BrokerReader;
use App\Broker\Exception\IncompleteResultException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class BrokerReaderTest extends TestCase
{
    private const string PATH = '/ngsi-ld/v1/entities';

    private const int MAX_RESULTS = 10000;

    public function testItReadsTheWholeResultInOneRequest(): void
    {
        $client = new MockHttpClient([$this->geoJson(1345, total: 1345)]);

        $data = new BrokerReader($client, self::MAX_RESULTS)->readAll(self::PATH, [], []);

        $this->assertCount(1345, $data['features']);
        $this->assertSame(1, $client->getRequestsCount());
    }

    /**
     * The broker states the size of the whole result only when asked to count,
     * so there would otherwise be nothing to check a short answer against.
     */
    public function testItAsksTheBrokerToCountAndNamesItsLimit(): void
    {
        $query = null;
        $client = new MockHttpClient(function (string $method, string $url) use (&$query): MockResponse {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return $this->geoJson(1, total: 1);
        });

        new BrokerReader($client, self::MAX_RESULTS)->readAll(self::PATH, ['type' => 'Parking'], []);

        $this->assertSame('true', $query['count']);
        $this->assertSame((string) self::MAX_RESULTS, $query['limit']);
        $this->assertSame('Parking', $query['type']);
    }

    /**
     * A page passed off as the whole set is the failure this exists to catch:
     * nothing in the payload tells the caller it is looking at a fraction.
     */
    public function testItRefusesAResultTheBrokerCutShort(): void
    {
        $client = new MockHttpClient([$this->geoJson(1000, total: 1345)]);

        $this->expectException(IncompleteResultException::class);
        $this->expectExceptionMessage('returned 1000 of 1345 entities');

        new BrokerReader($client, self::MAX_RESULTS)->readAll(self::PATH, [], []);
    }

    public function testItCountsEntitiesReturnedAsABareList(): void
    {
        $client = new MockHttpClient([
            new MockResponse(
                json_encode(array_fill(0, 20, ['id' => 'urn:ngsi-ld:OnStreetParking:x'])),
                ['response_headers' => $this->headers(50)]
            ),
        ]);

        $this->expectException(IncompleteResultException::class);

        new BrokerReader($client, self::MAX_RESULTS)->readAll(self::PATH, [], []);
    }

    /**
     * A broker that does not count leaves nothing to compare against, which
     * must not read as a result of nothing.
     */
    public function testItAcceptsAnAnswerTheBrokerDidNotCount(): void
    {
        $client = new MockHttpClient([
            new MockResponse(
                json_encode(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature']]]),
                ['response_headers' => ['content-type' => ['application/geo+json']]]
            ),
        ]);

        $data = new BrokerReader($client, self::MAX_RESULTS)->readAll(self::PATH, [], []);

        $this->assertCount(1, $data['features']);
    }

    private function geoJson(int $features, int $total): MockResponse
    {
        return new MockResponse(
            json_encode([
                'type' => 'FeatureCollection',
                'features' => array_fill(0, $features, ['type' => 'Feature']),
            ]),
            ['response_headers' => $this->headers($total)]
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function headers(int $total): array
    {
        return [
            'content-type' => ['application/geo+json'],
            'ngsild-results-count' => [(string) $total],
        ];
    }
}
