<?php

declare(strict_types=1);

namespace App\Test\Map;

use App\Broker\PagedBrokerReader;
use App\Source\SourceInterface;

/**
 * The entities of one source, shaped for a map widget.
 *
 * The broker answers with every entity of a model at once, under expanded
 * attribute names and with each value wrapped in an NGSI-LD Property. A
 * widget draws a layer from a plain FeatureCollection and reads plain field
 * names, so the two have to be reconciled somewhere; the broker will not do
 * it for attributes that were expanded against a default vocabulary, which
 * leaves the split by source and the flattening to us.
 */
final readonly class SourceFeatures
{
    private const string ENTITIES_PATH = '/ngsi-ld/v1/entities';

    /**
     * The attribute every source stamps its access URL onto, and so the only
     * thing in the payload that says which data set an entity came from.
     */
    private const string SOURCE_ATTRIBUTE = 'https://smartdatamodels.org/source';

    public function __construct(
        private PagedBrokerReader $reader,
    ) {
    }

    /**
     * @param string $type the expanded entity type, as the map asks for it
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function forSource(SourceInterface $source, string $type): array
    {
        $definition = $source->definition;
        $collection = $this->reader->readAll(
            self::ENTITIES_PATH,
            ['type' => $type],
            ['accept' => 'application/geo+json'],
        );

        $features = [];
        foreach ($collection['features'] ?? [] as $feature) {
            if ($this->sourceOf($feature) === $definition->accessUrlBase()) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $feature['geometry'] ?? null,
                    'properties' => $this->flatten($feature),
                ];
            }
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @param array<string, mixed> $feature
     */
    private function sourceOf(array $feature): ?string
    {
        $source = $feature['properties'][self::SOURCE_ATTRIBUTE] ?? null;

        return \is_array($source) ? ($source['value'] ?? null) : $source;
    }

    /**
     * The attributes under their short names, free of the Property wrapper.
     *
     * A template renders whatever it is handed, so an entity's own id travels
     * with the attributes rather than beside them.
     *
     * @param array<string, mixed> $feature
     *
     * @return array<string, mixed>
     */
    private function flatten(array $feature): array
    {
        $properties = ['id' => $feature['id'] ?? null];

        foreach ($feature['properties'] ?? [] as $name => $value) {
            // The entity type repeats what the layer already says, and the
            // geometry is carried by the feature itself.
            if ('type' === $name || 'location' === $name) {
                continue;
            }

            $properties[$this->shortName($name)] = \is_array($value) && isset($value['value'])
                ? $value['value']
                : $value;
        }

        return $properties;
    }

    /**
     * The last segment of an expanded attribute name, which is the term the
     * source declared before the broker expanded it.
     */
    private function shortName(string $name): string
    {
        $position = strrpos($name, '/');

        return false === $position ? $name : substr($name, $position + 1);
    }
}
