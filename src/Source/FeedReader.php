<?php

declare(strict_types=1);

namespace App\Source;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches a feed over HTTP and decodes it.
 */
final class FeedReader
{
    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
    }

    /**
     * @return array<array-key, mixed> the decoded document
     *
     * @throws \RuntimeException when the location is not an http(s) URL, cannot
     *                           be fetched, does not contain valid JSON, or
     *                           does not decode to an array
     */
    public function read(string $location): array
    {
        if (!str_starts_with($location, 'http://') && !str_starts_with($location, 'https://')) {
            throw new \RuntimeException(\sprintf('Feed location must be an http(s) URL, got "%s".', $location));
        }

        $json = $this->fetch($location);

        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(\sprintf('Invalid JSON in "%s": %s', $location, $exception->getMessage()), previous: $exception);
        }

        // A JSON document may legally be a scalar. Every feed we consume is a
        // list or an object, and a scalar here means the location is wrong
        // rather than that the feed is empty.
        if (!\is_array($decoded)) {
            throw new \RuntimeException(\sprintf('Expected a JSON array or object in "%s", got %s.', $location, get_debug_type($decoded)));
        }

        return $decoded;
    }

    private function fetch(string $url): string
    {
        try {
            return $this->client->request('GET', $url)->getContent();
        } catch (\Throwable $exception) {
            throw new \RuntimeException(\sprintf('Could not fetch "%s": %s', $url, $exception->getMessage()), previous: $exception);
        }
    }
}
