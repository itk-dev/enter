<?php

declare(strict_types=1);

namespace App\Test\Map;

/**
 * One data set, as the map draws it: what it is called, which model it
 * publishes into, which colour it carries and whether it brings areas.
 */
final readonly class MapLayer
{
    public function __construct(
        public string $id,
        public string $title,
        public string $model,
        public string $url,
        public string $colour,
        /**
         * Whether the source is expected to bring areas rather than points.
         */
        public bool $areas,
    ) {
    }

    /**
     * Two areas that touch, or lie one on the other, are a single shape
     * without an edge to tell them apart. The outline is darker than the fill
     * so it reads as a border rather than as more of the same colour.
     */
    public function outline(): string
    {
        return MapLayers::darken($this->colour);
    }

    /**
     * A solid area would hide whatever another data set put underneath it,
     * which is exactly what we are trying to see. A point hides nothing, and
     * washing it out only makes it harder to pick out against the map.
     */
    public function fillOpacity(): float
    {
        return $this->areas ? 0.35 : 0.9;
    }
}
