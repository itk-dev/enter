<?php

declare(strict_types=1);

namespace App\Test\Map;

use App\Source\DataType;
use App\Source\SourceInterface;
use App\Test\Source\TestDefinition;

/**
 * The widget configuration for the test map.
 *
 * The base — where the map opens, which background it draws — is read from a
 * file. The feature layers are not: there is one per test source, and which
 * sources exist is only known once the container has them.
 */
final readonly class MapConfig
{
    /**
     * One colour per data set, in the order the sources come. Two sources
     * describing the same bays are only told apart by colour, so these need
     * to stay clearly distinct rather than merely different.
     */
    private const array COLOURS = ['#e6194b', '#3e7bfa', '#2ca02c', '#ff7f0e', '#9467bd'];

    /**
     * How many features one click may reveal. Without it the widget shows a
     * single feature, which hides every point that happens to sit under a
     * polygon — and the two data sets overlap by design.
     */
    private const int FEATURES_PER_CLICK = 10;

    /**
     * @param array<string, mixed>           $base    the configuration read from file
     * @param array<string, SourceInterface> $sources the test sources, indexed by id
     * @param callable(string): string       $dataUrl builds the features URL for a source id
     *
     * @return array<string, mixed>
     */
    public function build(array $base, array $sources, callable $dataUrl): array
    {
        $layers = $base['map']['layer'] ?? [];
        $colour = 0;

        foreach ($this->ordered($sources) as $id => $source) {
            $layers[] = $this->layer($id, $source, $dataUrl($id), self::COLOURS[$colour % count(self::COLOURS)]);
            ++$colour;
        }

        $base['map']['layer'] = $layers;
        $base['controls'] = [
            // Expanded, because the point of it here is to show at a glance
            // which data sets are drawn rather than to tuck them away.
            ['layerswitch' => ['position' => 'left', 'showheader' => true, 'expanded' => true]],
            ['info' => [
                'eventtype' => 'click',
                'type' => 'cloud',
                'multifeature' => self::FEATURES_PER_CLICK,
                'className' => 'widget-simple-popup',
            ]],
        ];

        return $base;
    }

    /**
     * The sources that bring areas first, so the rest draw on top of them.
     *
     * A layer covers the ones before it. An Overpass feed asked for geometry
     * answers with the outline of every way it matched, and an outline laid
     * over a point from another data set takes the clicks that point should
     * have had. Ordering by the declared type is a rule of thumb — nothing
     * stops a feed from returning only nodes — but it is the only thing
     * known about the shape of the data before any of it is fetched.
     *
     * @param array<string, SourceInterface> $sources
     *
     * @return array<string, SourceInterface>
     */
    private function ordered(array $sources): array
    {
        uasort($sources, static fn (SourceInterface $a, SourceInterface $b): int => self::drawsAreas($b) <=> self::drawsAreas($a));

        return $sources;
    }

    private static function drawsAreas(SourceInterface $source): int
    {
        return (int) (DataType::Overpass === $source->definition->dataType);
    }

    /**
     * @return array<string, mixed>
     */
    private function layer(string $id, SourceInterface $source, string $url, string $colour): array
    {
        return [
            'id' => $id,
            'name' => $source->definition->title,
            'title' => $source->definition->title,
            'type' => 'geojson',
            'features' => true,
            'features_host' => $url,
            'visible' => true,
            'srs' => 'EPSG:4326',
            'template_info' => $this->template($source),
            'features_style' => [
                'fillcolor' => $colour,
                'fillcolor_selected' => $colour,
                // A solid polygon would hide whatever the other data set put
                // underneath it, which is exactly what we are trying to see.
                'fillopacity' => 0.45,
                'fillopacity_selected' => 0.7,
                'strokecolor' => $colour,
                'strokewidth' => 2,
            ],
        ];
    }

    /**
     * The popup, rendered per feature by the widget's own templating.
     *
     * Which attributes an entity carries is the source's business, so the
     * template walks whatever arrived rather than naming fields.
     */
    private function template(SourceInterface $source): string
    {
        $title = $source->definition instanceof TestDefinition
            ? $source->definition->title
            : $source->definition->id;

        // Not sprintf: the widget's own template delimiters are per cent
        // signs, which a format string would try to read as placeholders.
        return '<div class="widget-simple-title">'.htmlspecialchars($title, ENT_QUOTES).'</div>'
            .'<table class="test-map-details">'
            .'<% Object.keys(obj).forEach(function (key) { %>'
            .'<% if (obj[key] !== null && obj[key] !== "") { %>'
            .'<tr><th><%= key %></th><td><%= obj[key] %></td></tr>'
            .'<% } %>'
            .'<% }) %>'
            .'</table>';
    }
}
