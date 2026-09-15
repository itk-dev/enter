<?php

declare(strict_types=1);

namespace App\Broker;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Reads a broker list endpoint on behalf of a client that cannot page itself.
 *
 * A map widget asks for a layer once and draws whatever comes back, so a
 * result the broker splits across pages arrives silently truncated. The
 * broker refuses a limit above its own maximum rather than returning
 * everything, which leaves following the pages as the only way to get a
 * complete set. Doing it here keeps that out of the widget configuration.
 */
final readonly class PagedBrokerReader
{
    /**
     * Comfortably within the maximum brokers tend to impose; a larger page
     * risks the 403 the broker answers an over-large limit with.
     */
    private const int PAGE_SIZE = 1000;

    /**
     * Stops a broker that keeps advertising a next page from looping forever.
     */
    private const int MAX_PAGES = 100;

    public function __construct(
        private HttpClientInterface $brokerClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Every page the broker offers, merged into one payload.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $headers
     *
     * @return array<mixed>
     */
    public function readAll(string $path, array $query, array $headers): array
    {
        // Asking for everything leaves the paging to us. A limit the caller
        // named would only decide where the run gives up, which is the silent
        // truncation this exists to avoid.
        unset($query['limit'], $query['offset']);
        $merged = null;

        for ($page = 0; $page < self::MAX_PAGES; ++$page) {
            $response = $this->brokerClient->request('GET', $path, [
                'query' => [...$query, 'limit' => self::PAGE_SIZE, 'offset' => $page * self::PAGE_SIZE],
                'headers' => $headers,
            ]);

            $data = $response->toArray();
            $merged = null === $merged ? $data : $this->merge($merged, $data);

            // A page the broker did not fill is the last one whatever its
            // headers say, so the run stops without a further request.
            if ($this->count($data) < self::PAGE_SIZE || !$this->hasNextPage($response)) {
                return $merged;
            }
        }

        // Returning a truncated set as though it were complete is the failure
        // worth being loud about; the caller cannot tell from the payload.
        $this->logger->warning('Stopped reading {path} after {pages} pages; the result is incomplete.', [
            'path' => $path,
            'pages' => self::MAX_PAGES,
        ]);

        return $merged;
    }

    /**
     * @param array<mixed> $merged
     * @param array<mixed> $page
     *
     * @return array<mixed>
     */
    private function merge(array $merged, array $page): array
    {
        // Entities come back as a bare list, GeoJSON as a FeatureCollection
        // wrapping one. Everything outside the features belongs to the
        // collection rather than the page, so the first page's copy stands.
        if (isset($merged['features'], $page['features'])) {
            $merged['features'] = [...$merged['features'], ...$page['features']];

            return $merged;
        }

        return [...$merged, ...$page];
    }

    /**
     * @param array<mixed> $data
     */
    private function count(array $data): int
    {
        return \count($data['features'] ?? $data);
    }

    /**
     * The broker announces a further page in a Link header, alongside the
     * ones it uses to point at the context.
     */
    private function hasNextPage(ResponseInterface $response): bool
    {
        foreach ($response->getHeaders()['link'] ?? [] as $link) {
            if (str_contains($link, 'rel="next"')) {
                return true;
            }
        }

        return false;
    }
}
