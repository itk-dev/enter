<?php

declare(strict_types=1);

namespace App\Test\Map;

use App\Broker\BrokerReader;
use App\Source\SourceInterface;

/**
 * The entities of one source, shaped for a map widget.
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
        private BrokerReader $reader,
    ) {
    }

    /**
     * @param string $type the expanded entity type, as the map asks for it
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function forSource(SourceInterface $source, string $type): array
    {
        return $this->collect([$source], $type);
    }

    /**
     * Every source's features in one collection, each saying which it is.
     *
     * Grouping coinciding points is something a layer does to its own
     * features, so points that should be counted together have to arrive
     * together. What is drawn in which colour is then decided per feature
     * rather than per layer.
     *
     * @param array<string, SourceInterface> $sources
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function forSources(array $sources, string $type): array
    {
        return $this->collect(array_values($sources), $type, array_keys($sources), asPoints: true);
    }

    /**
     * @param list<SourceInterface> $sources
     * @param list<string>|null     $ids
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private function collect(array $sources, string $type, ?array $ids = null, bool $asPoints = false): array
    {
        // Once, however many sources are being answered for: they all publish
        // into the one model, and the broker has no way to tell them apart.
        $collection = $this->reader->readAll(
            self::ENTITIES_PATH,
            ['type' => $type],
            ['accept' => 'application/geo+json'],
        );

        $owners = [];
        foreach ($sources as $position => $source) {
            $owners[$source->definition->accessUrlBase()] = $ids[$position] ?? $source->definition->id;
        }

        $features = [];
        foreach ($collection['features'] ?? [] as $feature) {
            $owner = $owners[$this->sourceOf($feature)] ?? null;
            if (null === $owner) {
                continue;
            }

            $geometry = $feature['geometry'] ?? null;

            $features[] = [
                'type' => 'Feature',
                'geometry' => $asPoints ? $this->asPoint($geometry) : $geometry,
                'properties' => ['dataset' => $owner] + $this->flatten($feature),
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $this->areasFirst($features)];
    }

    /**
     * A geometry reduced to the one point that stands for it.
     *
     * Grouping works on points, and an area has none of its own — but an area
     * left out of the count would make the count wrong. Somewhere inside it is
     * near enough for deciding what lies close to what, which is all a group
     * is: the shape itself is drawn from the data set's own layer, close in,
     * where it can be seen.
     *
     * @param array<string, mixed>|null $geometry
     *
     * @return array<string, mixed>|null
     */
    private function asPoint(?array $geometry): ?array
    {
        if (null === $geometry || 'Point' === ($geometry['type'] ?? null)) {
            return $geometry;
        }

        $coordinates = $geometry['coordinates'] ?? [];
        while (isset($coordinates[0]) && is_array($coordinates[0])) {
            if (!is_array($coordinates[0][0] ?? null)) {
                break;
            }
            $coordinates = $coordinates[0];
        }

        $points = array_values(array_filter($coordinates, static fn ($p): bool => is_array($p) && 2 <= count($p)));
        if ([] === $points) {
            return null;
        }

        return [
            'type' => 'Point',
            'coordinates' => [
                array_sum(array_column($points, 0)) / count($points),
                array_sum(array_column($points, 1)) / count($points),
            ],
        ];
    }

    /**
     * The areas before the points, since a layer draws its features in the
     * order they arrive.
     *
     * A source that publishes both — an Overpass feed answers with the
     * outline of a car park and the single bays beside it — would otherwise
     * bury its own points under its own outlines, where they can be neither
     * seen nor picked.
     *
     * @param list<array<string, mixed>> $features
     *
     * @return list<array<string, mixed>>
     */
    private function areasFirst(array $features): array
    {
        usort($features, static fn (array $a, array $b): int => (int) ('Point' === ($a['geometry']['type'] ?? null))
            <=> (int) ('Point' === ($b['geometry']['type'] ?? null)));

        return $features;
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
