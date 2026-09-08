<?php

declare(strict_types=1);

namespace App\Import;

use App\Broker\NgsiLdBroker;
use App\Import\Exception\EmptySourceException;
use App\Import\Exception\UnknownSourceException;
use App\Import\Exception\UpsertFailedException;
use App\Source\SourceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Converts a registered source to NGSI-LD and upserts it into the broker.
 *
 * Owns what an import decides: which sources exist, which contexts their
 * entities carry, how many to take, and what counts as a failed run. That
 * leaves the command around it with argument parsing and exit codes, and lets
 * the decisions be exercised without a console.
 */
final readonly class DataSourceImporter
{
    /**
     * @param iterable<SourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator('app.source')]
        private iterable $sources,
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
     * Converts a source to NGSI-LD without sending anything.
     *
     * @return non-empty-list<array<string, mixed>>
     *
     * @throws UnknownSourceException when no source is registered under the key
     * @throws EmptySourceException   when the source yields no entities
     */
    public function payload(string $key, ?int $limit = null): array
    {
        $registry = $this->registry();

        if (!isset($registry[$key])) {
            throw new UnknownSourceException($key, array_keys($registry));
        }

        // A limit below 1 is a mistyped option rather than a request to import
        // nothing, and importing nothing is the one outcome this class refuses
        // to report as a success.
        $limit = null === $limit ? null : max(1, $limit);
        $contexts = $this->contexts();

        $payload = [];
        foreach ($registry[$key]->entities() as $entity) {
            $payload[] = $entity->toArray($contexts);

            // A source reads a whole feed lazily, so the limit stops the
            // conversion instead of trimming its result.
            if (null !== $limit && \count($payload) >= $limit) {
                break;
            }
        }

        // A source that yields nothing is almost always misconfigured rather
        // than genuinely empty, and it fails silently by construction: a
        // record skipped for a missing field looks exactly like a feed with no
        // records. Fail loudly so it cannot be mistaken for a successful run.
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
        $payload = $this->payload($key, $limit);

        try {
            $status = $this->broker->upsert($payload);
        } catch (\Throwable $exception) {
            // A broker that is down or rejects the batch is an operational
            // condition, not a bug in the conversion. Giving it a type of its
            // own lets a caller report it plainly while the exceptions a
            // broken feed raises — the ones worth a stack trace — pass through.
            throw new UpsertFailedException($exception);
        }

        return new ImportResult(\count($payload), $status, $this->broker->brokerUrl());
    }

    /**
     * @return array<string, SourceInterface> keyed by source key
     */
    private function registry(): array
    {
        $registry = [];

        foreach ($this->sources as $source) {
            $registry[$source->key()] = $source;
        }

        return $registry;
    }

    /**
     * @return list<string>
     */
    private function contexts(): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $this->contextUrls))));
    }
}
