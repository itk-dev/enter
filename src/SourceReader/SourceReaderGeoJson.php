<?php

namespace App\SourceReader;

use App\Source\SourceInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SourceReaderGeoJson implements SourceReaderInterface
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
        $data = $this->getData($source->accessUrl);

        $features = $data['features'];

        if (!is_array($features) || !array_is_list($features)) {
            throw new \RuntimeException('Invalid features array');
        }

        return $features;
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(string $url): iterable
    {
        return $this->client->request('GET', $url)->toArray();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
