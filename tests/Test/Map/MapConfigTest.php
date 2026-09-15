<?php

declare(strict_types=1);

namespace App\Tests\Test\Map;

use App\Source\DataType;
use App\Source\SourceInterface;
use App\Test\Map\MapConfig;
use App\Tests\Support\FakeSource;
use PHPUnit\Framework\TestCase;

class MapConfigTest extends TestCase
{
    private const array BASE = ['map' => ['layer' => [['namedlayer' => '#septima_standard']]]];

    public function testItAddsALayerPerSourceKeepingTheBackground(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);

        $this->assertCount(2, $config['map']['layer']);
        $this->assertSame('#septima_standard', $config['map']['layer'][0]['namedlayer']);
        $this->assertSame('a', $config['map']['layer'][1]['id']);
        $this->assertSame('A', $config['map']['layer'][1]['title']);
    }

    public function testItGivesEachSourceItsOwnColour(): void
    {
        $config = $this->build([
            'a' => $this->source('A', 'https://a.example/feed'),
            'b' => $this->source('B', 'https://b.example/feed'),
        ]);

        [, $first, $second] = $config['map']['layer'];

        $this->assertNotSame($first['features_style']['fillcolor'], $second['features_style']['fillcolor']);
    }

    /**
     * The Overpass data set brings polygons; drawing it under the others is
     * what keeps their points reachable.
     */
    public function testItDrawsThePolygonSourceUnderTheRest(): void
    {
        $config = $this->build([
            'points' => $this->source('Points', 'https://webkort.example/feed'),
            'polygons' => $this->source('Polygons', 'https://overpass-api.de/api/interpreter', DataType::Overpass),
        ]);

        $ids = array_column(array_slice($config['map']['layer'], 1), 'id');

        $this->assertSame(['polygons', 'points'], $ids);
    }

    public function testItLetsOneClickRevealOverlappingFeatures(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);

        $info = $this->control($config, 'info');

        $this->assertGreaterThan(1, $info['multifeature']);
        $this->assertSame('click', $info['eventtype']);
    }

    public function testItOffersALayerSwitchForTogglingDataSets(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);

        $this->assertNotNull($this->control($config, 'layerswitch'));
    }

    /**
     * A feature the widget has no template for is skipped when it works out
     * what a click hit, so the popup depends on this being there.
     */
    public function testEveryLayerCarriesAPopupTemplate(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);

        $this->assertNotEmpty($config['map']['layer'][1]['template_info']);
    }

    public function testItPointsEachLayerAtItsOwnFeatures(): void
    {
        $config = $this->build([
            'a' => $this->source('A', 'https://a.example/feed'),
            'b' => $this->source('B', 'https://b.example/feed'),
        ]);

        $this->assertSame('/features/a', $config['map']['layer'][1]['features_host']);
        $this->assertSame('/features/b', $config['map']['layer'][2]['features_host']);
    }

    /**
     * @param array<string, SourceInterface> $sources
     *
     * @return array<string, mixed>
     */
    private function build(array $sources): array
    {
        return new MapConfig()->build(self::BASE, $sources, static fn (string $id): string => '/features/'.$id);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>|null
     */
    private function control(array $config, string $name): ?array
    {
        foreach ($config['controls'] as $control) {
            if (isset($control[$name])) {
                return $control[$name];
            }
        }

        return null;
    }

    private function source(string $title, string $accessUrl, DataType $dataType = DataType::GeoJSON): SourceInterface
    {
        return FakeSource::create($title, $accessUrl, $dataType);
    }
}
