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
     * Small enough that neighbouring bays stay apart at street zoom, while
     * still giving a click something to land on.
     */
    private const int POINT_RADIUS = 4;

    /**
     * The outline of whatever is currently being looked at. Deliberately not
     * one of the data set colours: it has to read as "this one" whichever
     * layer the feature belongs to.
     */
    private const string SELECTED_OUTLINE = '#111111';

    /**
     * The name of the element on the page that the layer toggles render into.
     */
    public const string TOGGLES_ELEMENT = 'layers';

    /**
     * How far apart, in pixels, points have to be before they are drawn as
     * separate markers. The widget's own default of 40 leaves clusters
     * jostling and points sitting on their edges; this buys them room.
     */
    private const int CLUSTER_DISTANCE = 80;

    /**
     * Where each kind of layer sits in the stack. A point covered by an area
     * cannot be seen, let alone seen to be selected, so points are given the
     * higher place outright rather than left to the order layers arrive in.
     */
    private const int Z_AREAS = 10;
    private const int Z_POINTS = 20;

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

        // Controls belong to the map, not beside it: what reads a feature
        // out of a click is the map, and a control mounted anywhere else
        // never gets hold of one. Where a control *draws* is separate, and
        // is what detaching decides.
        //
        // The two controls sit at different levels on purpose. What reads a
        // feature out of a click is the map, so the popup has to belong to
        // it. Detaching — rendering a control into an element on the page —
        // is only offered to a control that does not, which is what puts the
        // toggles above the map rather than over it.
        $base['map']['controls'] = [[
            'info' => [
                'eventtype' => 'click',
                'type' => 'cloud',
                'multifeature' => self::FEATURES_PER_CLICK,
                'className' => 'widget-simple-popup',
            ],
            'layerswitch' => [
                'detach' => self::TOGGLES_ELEMENT,
                // The data sets and nothing else: "vectors" picks out the
                // layers drawn from features, leaving the background map —
                // which is not a data set and has no business being switched
                // off — out of the list.
                'layers' => 'vectors',
                // No header: it collapses the list behind a button, and the
                // toggles are put above the map precisely to be seen.
                'showheader' => false,
                'expanded' => true,
                // A layer the control has no legend image for is dropped from
                // the list entirely, which for layers drawn from plain GeoJSON
                // means all of them: without this the switch renders empty.
                'showLayersWithoutLegend' => true,
                // Without this the entries are rendered as plain text and
                // explicitly disabled — listed, but not something you can
                // switch. With it they take a click, and tabindex gives them
                // the keyboard and a button role as well.
                'showbuttons' => true,
                'tabindex' => true,
            ],
        ]];

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

    /**
     * A darker shade of a colour, for an edge against its own fill.
     */
    private static function darken(string $colour, float $by = 0.45): string
    {
        [$r, $g, $b] = sscanf($colour, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', (int) ($r * (1 - $by)), (int) ($g * (1 - $by)), (int) ($b * (1 - $by)));
    }

    private static function drawsAreas(SourceInterface $source): int
    {
        return (int) (DataType::Overpass === $source->definition->dataType);
    }

    /**
     * Points that land on the same spot drawn as a single marker carrying
     * their count, rather than as an unreadable pile.
     *
     * Told to lay them out in a grid the widget scatters them across the map
     * instead, which says less than the count does. What a cluster hides is
     * reached by zooming in, or by the popup taking more than one feature.
     *
     * Only for a data set of points: clustering reduces a feature to the spot
     * it sits at, and an area has no such spot — handed one, the widget stops
     * drawing the layer at all.
     *
     * @return array<string, mixed>
     */
    private function clustering(SourceInterface $source, string $colour): array
    {
        if (1 === self::drawsAreas($source)) {
            return [];
        }

        return ['cluster' => [
            'distance' => self::CLUSTER_DISTANCE,
            'features_style' => [
                'symbol' => 'circle',
                'fillcolor' => $colour,
                'fillopacity' => 0.95,
                'strokecolor' => self::darken($colour),
                'strokewidth' => 1.5,
            ],
        ]];
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
            'zIndex' => 1 === self::drawsAreas($source) ? self::Z_AREAS : self::Z_POINTS,
            'template_info' => $this->template($source),
            ...$this->clustering($source, $colour),
            'features_style' => [
                'symbol' => 'circle',
                'symbol_selected' => 'circle',
                'radius' => self::POINT_RADIUS,
                'radius_selected' => self::POINT_RADIUS + 3,
                'fillcolor' => $colour,
                'fillcolor_selected' => $colour,
                // A solid area would hide whatever another data set put
                // underneath it, which is exactly what we are trying to see.
                // A point hides nothing, and washing it out only makes it
                // harder to pick out against the map.
                'fillopacity' => 1 === self::drawsAreas($source) ? 0.35 : 0.9,
                'fillopacity_selected' => 1 === self::drawsAreas($source) ? 0.65 : 1,
                // Two areas that touch, or lie one on the other, are a single
                // shape without an edge to tell them apart. The outline is
                // darker than the fill so it reads as a border rather than
                // more of the same colour.
                'strokecolor' => self::darken($colour),
                'strokecolor_selected' => self::SELECTED_OUTLINE,
                'strokeopacity' => 1,
                'strokewidth' => 1.5,
                // What is being looked at has to stand out from its
                // neighbours, which are the same colour by definition.
                'strokewidth_selected' => 4,
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
        //
        // The widget hands the template its own bookkeeping alongside the
        // attributes — the geometry, the feature it was built from, an
        // internal id — all of which render as "[object Object]" and none of
        // which is anything a reader asked for. Only the attributes the
        // source published are shown; the source itself is what the heading
        // already says.
        return '<div class="widget-simple-title">'.htmlspecialchars($title, ENT_QUOTES).'</div>'
            .'<dl class="test-map-details">'
            .'<% Object.keys(obj).filter(function (key) {'
            .' return key.charAt(0) !== "_"'
            .' && ["geometry", "source"].indexOf(key) === -1'
            .' && obj[key] !== null && obj[key] !== "" && obj[key] !== undefined;'
            .' }).forEach(function (key) { %>'
            .'<dt><%= key %></dt>'
            .'<dd><%= Array.isArray(obj[key]) ? obj[key].join(", ") : obj[key] %></dd>'
            .'<% }) %>'
            .'</dl>';
    }
}
