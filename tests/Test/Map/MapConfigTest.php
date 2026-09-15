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

    /**
     * Detaching renders the toggles into an element of the page rather than
     * over the map. The widget only offers it to a control of the map, which
     * is also the only place the control can see the layers to list.
     */
    public function testItPutsTheTogglesOnThePageRatherThanOverTheMap(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);

        $this->assertSame(MapConfig::TOGGLES_ELEMENT, $this->control($config, 'layerswitch')['detach']);
    }

    /**
     * The popup is read out of a click by the map, so it has to be the map's.
     */
    public function testItLeavesThePopupWithTheMap(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);

        $this->assertArrayHasKey('info', $config['map']['controls'][0]);
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

    public function testItDrawsPointsSmallEnoughToTellApart(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);
        $style = $config['map']['layer'][1]['features_style'];

        $this->assertLessThan(5, $style['radius']);
        $this->assertGreaterThan($style['radius'], $style['radius_selected']);
    }

    /**
     * Points on the same spot are one icon until clicked, so the grid is what
     * gives each of them somewhere to be picked from.
     */
    /**
     * Laid out in a grid the widget scatters a cluster's points across the
     * map; as one marker it just says how many there are.
     */
    public function testItDrawsCoincidingPointsAsASingleMarker(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);
        $cluster = $config['map']['layer'][1]['cluster'];

        $this->assertArrayNotHasKey('grid', $cluster);
        $this->assertArrayHasKey('features_style', $cluster);
        // Close in the points stand apart on their own, and a marker saying
        // "2" tells the reader less than the two points it hides.
        $this->assertGreaterThan(0, $cluster['minResolution']);
    }

    /**
     * Clustering a feature reduces it to the point it sits at, which an area
     * does not have; handed one, the widget stops drawing the layer.
     */
    public function testItLeavesADataSetOfAreasUnclustered(): void
    {
        $config = $this->build([
            'areas' => $this->source('Areas', 'https://overpass-api.de/api/interpreter', DataType::Overpass),
        ]);

        $this->assertArrayNotHasKey('cluster', $config['map']['layer'][1]);
    }

    /**
     * A control belongs to the map: mounted beside it, it never gets hold of
     * the feature a click landed on.
     */
    public function testItDeclaresControlsWhereTheWidgetLooksForThem(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);

        $this->assertArrayNotHasKey('controls', $config);
        $this->assertNotNull($this->control($config, 'info'));
        $this->assertNotNull($this->control($config, 'layerswitch'));
    }

    /**
     * Fetching every feature for every view stops being viable as the data
     * grows, so the layer asks for the extent it is about to draw.
     */
    public function testItFetchesOnlyWhatTheViewNeeds(): void
    {
        $config = $this->build(['a' => $this->source('A', 'https://a.example/feed')]);
        $layer = $config['map']['layer'][1];

        $this->assertSame('bbox', $layer['loadingstrategy']);
        $this->assertStringContainsString('bbox=', $layer['features_host']);
    }

    public function testItPointsEachLayerAtItsOwnFeatures(): void
    {
        $config = $this->build([
            'a' => $this->source('A', 'https://a.example/feed'),
            'b' => $this->source('B', 'https://b.example/feed'),
        ]);

        $this->assertStringStartsWith('/features/a', $config['map']['layer'][1]['features_host']);
        $this->assertStringStartsWith('/features/b', $config['map']['layer'][2]['features_host']);
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
        foreach ($config['map']['controls'] as $group) {
            if (isset($group[$name])) {
                return $group[$name];
            }
        }

        return $config[$name] ?? null;
    }

    private function source(string $title, string $accessUrl, DataType $dataType = DataType::GeoJSON): SourceInterface
    {
        return FakeSource::create($title, $accessUrl, $dataType);
    }
}
