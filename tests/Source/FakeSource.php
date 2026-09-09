<?php

declare(strict_types=1);

namespace App\Tests\Source;

use App\Ngsi\NgsiEntity;
use App\Source\SourceInterface;

/**
 * A source with a fixed set of entities.
 *
 * Yields them one at a time and counts what it handed out, so a test can tell
 * whether a limit stopped the conversion or merely trimmed its result.
 */
final class FakeSource implements SourceInterface
{
    private int $produced = 0;

    /**
     * @param list<NgsiEntity> $entities
     */
    public function __construct(
        private readonly string $key,
        private readonly array $entities = [],
    ) {
    }

    /**
     * @param string ...$ids entity ids, one entity each
     */
    public static function withEntities(string $key, string ...$ids): self
    {
        return new self($key, array_map(
            static fn (string $id): NgsiEntity => new NgsiEntity($id, 'Example'),
            array_values($ids)
        ));
    }

    public function key(): string
    {
        return $this->key;
    }

    public function entities(): iterable
    {
        foreach ($this->entities as $entity) {
            ++$this->produced;

            yield $entity;
        }
    }

    /**
     * How many entities were actually pulled from this source.
     */
    public function produced(): int
    {
        return $this->produced;
    }
}
