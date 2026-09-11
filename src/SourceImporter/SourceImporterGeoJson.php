<?php

namespace App\SourceImporter;

use App\Broker\NgsiLdBroker;
use App\Import\Exception\UpsertFailedException;
use App\Import\ImportResult;
use App\Ngsi\NgsiEntity;
use App\Source\SourceInterface;
use App\SourceReader\SourceReaderGeoJson;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Read data from a source and upserts in broker.
 *
 * @todo Get the importer from a factory depending
 */
class SourceImporterGeoJson implements SourceImporterInterface
{
    use LoggerAwareTrait;
    use LoggerTrait;

    /**
     * @param list<string> $contextUrls
     */
    public function __construct(
        private readonly SourceReaderGeoJson $reader,
        private readonly NgsiLdBroker $broker,
        #[Autowire(env: 'json:APP_NGSI_CONTEXT_URLS')]
        private readonly array $contextUrls,
        LoggerInterface $logger,
    ) {
        $this->setLogger($logger);
    }

    public function supports(SourceInterface $source): bool
    {
        // @todo
        return true;
    }

    public function import(SourceInterface $source): ImportResult
    {
        $this->info('Processing source {source}', ['source' => $source->__toString()]);

        $contextUrls = array_merge([$source->definition->contextUrl], $this->contextUrls);
        $payload = [];
        foreach ($this->read($source) as $entity) {
            $this->info('Building payload for {entity}', ['entity' => $entity->id()]);
            $payload[] = $entity->toPayload($contextUrls);
        }

        if (1 === count($payload)) {
            $this->info('Upserting 1 entity');
        } else {
            $this->info('Upserting {count} entities', ['count' => count($payload)]);
        }

        try {
            $status = $this->broker->upsert($payload);
        } catch (\Throwable $exception) {
            throw new UpsertFailedException($exception);
        }

        return new ImportResult(\count($payload), $status, $this->broker->brokerUrl());
    }

    /**
     * @return iterable<NgsiEntity>
     */
    private function read(SourceInterface $source): iterable
    {
        $data = $this->reader->read($source);
        foreach ($data as $item) {
            if (is_array($item)) {
                try {
                    if ($entity = $source->createNgsiEntity($item)) {
                        yield $entity;
                    }
                } catch (\Exception $e) {
                    // @todo Log in database?
                    $this->error('error: {message} ', ['message' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
