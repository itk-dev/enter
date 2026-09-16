<?php

declare(strict_types=1);

namespace App\Broker;

use App\Broker\Exception\IncompleteResultException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads a broker list endpoint whole, in one request.
 *
 * The broker answers a list request with fifty entities unless told
 * otherwise, so a client that asks once and draws what it gets is working
 * from a fraction of the data without being told so. How much may be asked
 * for at once is the broker's own maximum, raised in its configuration
 * rather than worked around here; what is left is noticing when an answer
 * came back short of what the broker says it holds.
 */
final readonly class BrokerReader
{
    public function __construct(
        private HttpClientInterface $brokerClient,
        /**
         * Has to stay within the broker's maximum, which answers a larger
         * limit with 403 TooManyResults rather than with a shorter page.
         */
        #[Autowire(env: 'int:APP_BROKER_MAX_RESULTS')]
        private int $maxResults,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $headers
     *
     * @return array<mixed>
     */
    public function readAll(string $path, array $query, array $headers): array
    {
        $response = $this->brokerClient->request('GET', $path, [
            // Counting is what makes the broker state the size of the whole
            // result; without it a page looks exactly like a complete answer.
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
