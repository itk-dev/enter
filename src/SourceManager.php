<?php

namespace App;

use App\Source\AbstractSource;
use App\Source\SourceInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class SourceManager
{
    /**
     * Sources indexed by ID.
     *
     * @var array<string, SourceInterface>
     */
    private array $indexed;

    /**
     * @param iterable<SourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator('app.source')]
        private readonly iterable $sources,
    ) {
    }

    /**
     * Get sources indexed by ID.
     *
     * @return array<string, SourceInterface>
     */
    public function getSources(): array
    {
        if (!isset($this->indexed)) {
            $sources = [];
            foreach ($this->sources as $source) {
                if (!$source instanceof SourceInterface) {
                    throw new \InvalidArgumentException(sprintf('Invalid source class: %s (must extend %s)', $source::class, AbstractSource::class));
                }
                $id = $source->id;
                if (isset($sources[$id])) {
                    throw new \RuntimeException(sprintf('Duplicate source: %s (ID already used by %s)', $id, $sources[$id]::class));
                }
                $sources[$id] = $source;
            }
            $this->indexed = $sources;
        }

        return $this->indexed;
    }

    public function getSource(string $name): SourceInterface
    {
        $sources = $this->getSources();
        if (!array_key_exists($name, $sources)) {
            throw new \InvalidArgumentException(sprintf('Source "%s" does not exist.', $name));
        }

        return $sources[$name];
    }
}
