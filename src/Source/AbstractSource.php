<?php

namespace App\Source;

/**
 * Abstract source.
 */
abstract readonly class AbstractSource implements SourceInterface
{
    public function key(): string
    {
        return $this->definition->id;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->definition->title, $this->definition->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->definition->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
