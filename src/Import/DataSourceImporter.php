<?php

declare(strict_types=1);

namespace App\Import;

use App\Broker\NgsiLdBroker;
use App\Import\Exception\EmptySourceException;
use App\Import\Exception\UnknownSourceException;
use App\Import\Exception\UpsertFailedException;
use App\Source\Manifest\Catalog;
use App\Source\SourceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Converts a registered source to NGSI-LD and upserts it into the broker.
 */
final readonly class DataSourceImporter
{
    /**
     * @param iterable<SourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator('app.source')]
        private iterable $sources,
        private Catalog $catalog,
        private NgsiLdBroker $broker,
        #[Autowire(env: 'ENTER_NGSI_CONTEXT_URLS')]
        private string $contextUrls,
    ) {
    }

    /**
     * @return list<string> every registered source key, in registration order
     */
    public function keys(): array
    {
        return array_keys($this->registry());
    }

    /**
     * Converts a source to NGSI-LD.
     *
     * @return non-empty-list<array<string, mixed>>
     *
     * @throws UnknownSourceException when no source is registered under the key
     * @throws EmptySourceException   when the source yields no entities
     */
    public function payload(string $key, ?int $limit = null): array
    {
        // Collect all dataset keys.
        $registry = $this->registry();

        // Check if requested dataset key exists.
        if (!isset($registry[$key])) {
            throw new UnknownSourceException($key, array_keys($registry));
        }

        // Define minimum limit, in case of limit defined as less than 1.
        $limit = null === $limit ? null : max(1, $limit);

        // Load context for given dataset.
        $contexts = $this->contexts($key);

        $payload = [];
        foreach ($registry[$key]->entities() as $entity) {
            $payload[] = $entity->toArray($contexts);

            // Break upon limit.
            if (null !== $limit && \count($payload) >= $limit) {
                break;
            }
        }

        if ([] === $payload) {
            throw new EmptySourceException($key);
        }

        return $payload;
    }

    /**
     * @throws UnknownSourceException when no source is registered under the key
     * @throws EmptySourceException   when the source yields no entities
     * @throws UpsertFailedException  when the broker cannot be written to
     */
    public function import(string $key, ?int $limit = null): ImportResult
    {
        // Get payload from dataset.
        $payload = $this->payload($key, $limit);

        // Try to upsert broker with payload.
        try {
            $status = $this->broker->upsert($payload);
        } catch (\Throwable $exception) {
            throw new UpsertFailedException($exception);
        }

        // Return result.
        return new ImportResult(\count($payload), $status, $this->broker->brokerUrl());
    }

    /**
     * Get list of registered datasets.
     *
     * @see config/sources.yaml
     *
     * @return array<string, SourceInterface> keyed by source key
     */
    private function registry(): array
    {
        $registry = [];

        foreach ($this->sources as $source) {
            $registry[$source->id] = $source;
        }

        return $registry;
    }

    /**
     * Return an array of contexts. Each dataset holds its own context.
     *
     * @see config/sources.yaml
     *
     * @return array<string>
     */
    private function contexts(string $key): array
    {
        return [
            $this->catalog->get($key)->contextUrl,
            ...array_values(array_filter(array_map(trim(...), explode(',', $this->contextUrls)))),
        ];
    }
}
