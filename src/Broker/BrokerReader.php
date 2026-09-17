<?php

declare(strict_types=1);

namespace App\Broker;

use App\Broker\Exception\IncompleteResultException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A class for handling broker calls.
 */
final readonly class  BrokerReader
{
    public function __construct(
        private HttpClientInterface $brokerClient,
        #[Autowire(env: 'int:APP_BROKER_MAX_RESULTS')]
        private int $maxResults,
    ) {
    }

    /**
     * @param string $path
     * @param array<string, mixed> $query
     * @param array<string, mixed> $headers
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function readAll(string $path, array $query, array $headers): array
    {
        $response = $this->brokerClient->request('GET', $path, [
            'query' => [...$query, 'limit' => $this->maxResults, 'count' => 'true'],
            'headers' => $headers,
        ]);

        $data = $response->toArray();
        $total = (int) ($response->getHeaders()['ngsild-results-count'][0] ?? 0);
        // Entities come back as a bare list, GeoJSON as a FeatureCollection
        // wrapping one.
        $received = \count($data['features'] ?? $data);

        if ($received < $total) {
            throw new IncompleteResultException($path, $received, $total);
        }

        return $data;
    }
}
