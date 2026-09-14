<?php

namespace App\SourceReader;

use App\Source\SourceInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SourceReader implements SourceReaderInterface
{
    use LoggerAwareTrait;
    use LoggerTrait;

    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
    }

    public function read(SourceInterface $source): iterable
    {
        // @todo Add some proper exception handling/logging.
        $data = $this->getData($source->definition->accessUrl);

        return $data;
    }

    /**
     * @param string|array{
     *       url: string,
     *       query: array<string, mixed>
     * } $url
     *
     * @return iterable<mixed>
     */
    private function getData(string|array $url): iterable
    {
        // @todo Cache request responses.
        $query = [];
        if (is_array($url)) {
            [
                'url' => $url,
                'query' => $query,
            ] = $url;
        }

        return $this->client->request('GET', $url, [
            'query' => $query,
        ])->toArray();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
