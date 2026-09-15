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
    private const string SELECTED_OUTLINE = '#000000';

    /**
     * The colour of a group that may hold more than one data set.
     */
    private const string MIXED_CLUSTER = '#4a4a6a';

    /**
     * How much larger a point is drawn once selected. Enough to grow past
     * whatever it is sitting on rather than merely change colour under it.
     */
    private const int SELECTED_GROWTH = 6;

    /**
     * The name of the element on the page that the layer toggles render into.
     */
    public const string TOGGLES_ELEMENT = 'layers';

    /**
     * Stands for every data set at once, when they are asked for together.
     */
    public const string COMBINED_ID = 'all';

    /**
     * How far apart, in pixels, points have to be before they are drawn as
     * separate markers. The widget's own default of 40 leaves clusters
     * jostling and points sitting on their edges; this buys them room.
     */
    private const int CLUSTER_DISTANCE = 80;

    /**
     * The size of a cluster marker, whatever it stands for.
     */
    private const int CLUSTER_RADIUS = 13;

    /**
     * The resolution, in metres per pixel, at which points stop being grouped.
     * Zoomed in this far the bays are metres apart on screen and stand on
     * their own; a marker saying "2" tells the reader less than the two
     * points it is hiding.
     */
    private const float CLUSTER_UNTIL_RESOLUTION = 2.0;

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

        $shades = $this->shades($sources);
        $titles = [];
        foreach ($sources as $id => $source) {
            $titles[$id] = $source->definition->title;
        }

        foreach ($this->ordered($sources) as $id => $source) {
            $layers[] = [
                ...$this->layer($id, $source, $dataUrl($id), $shades[$id]),
                // The grouped view of the same points is drawn instead while
                // the map is far enough out for them to pile up.
                'minResolution' => 0,
                'maxResolution' => self::CLUSTER_UNTIL_RESOLUTION,
            ];
        }

        $layers[] = $this->clusterLayer($dataUrl(self::COMBINED_ID), $shades, $titles);

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
                // No zooming on the widget's part. It moves to a feature by
                // its own reckoning of how close is close enough, which from
                // any nearer than that means clicking a point zooms out of
                // the view the reader had. Groups are zoomed to from the
                // page, where the points being zoomed to are known.
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
                // The grouped view is how the data sets are drawn far out,
                // not a data set of its own to be switched.
                'excludeLayers' => [self::COMBINED_ID],
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
     * The colour each data set is drawn in, by id.
     *
     * @param array<string, SourceInterface> $sources
     *
     * @return array<string, string>
     */
    private function shades(array $sources): array
    {
        $shades = [];
        foreach (array_keys($this->ordered($sources)) as $position => $id) {
            $shades[$id] = self::COLOURS[$position % count(self::COLOURS)];
        }

        return $shades;
    }

    /**
     * The one layer that groups coinciding points, whichever data set they
     * came from.
     *
     * A layer groups only its own features, so counting across data sets
     * means holding them in one layer. What separates them there is the
     * attribute each feature carries rather than the layer it sits in, which
     * the style reads per feature.
     *
     * @param array<string, string> $shades
     * @param array<string, string> $titles
     *
     * @return array<string, mixed>
     */
    private function clusterLayer(string $url, array $shades, array $titles): array
    {
        return [
            'id' => self::COMBINED_ID,
            'name' => 'Alle datasæt',
            'type' => 'geojson',
            'features' => true,
            'features_host' => $url,
            'visible' => true,
            'srs' => 'EPSG:4326',
            'zIndex' => self::Z_POINTS,
            'minResolution' => self::CLUSTER_UNTIL_RESOLUTION,
            // A feature the popup has no template for is left out of it, and
            // a lone point far out is drawn from here rather than from its own
            // data set's layer: without this, clicking one says nothing.
            'template_info' => $this->body(self::shadeTemplate($shades), self::valueTemplate($titles, 'dataset')),
            'cluster' => [
                'distance' => self::CLUSTER_DISTANCE,
                'features_style' => [
                    'symbol' => 'circle',
                    'radius' => self::CLUSTER_RADIUS,
                    'radius_selected' => self::CLUSTER_RADIUS + 2,
                    // A group can hold more than one data set, so it is drawn
                    // in neither of their colours rather than in a colour that
                    // would claim it belongs to one of them.
                    'fillcolor' => self::MIXED_CLUSTER,
                    'fillopacity' => 0.95,
                    'strokecolor' => self::darken(self::MIXED_CLUSTER),
                    'strokewidth' => 1.5,
                ],
            ],
            'features_style' => [
                'symbol' => 'circle',
                'radius' => self::POINT_RADIUS,
                'radius_selected' => self::POINT_RADIUS + self::SELECTED_GROWTH,
                'fillcolor' => self::shadeTemplate($shades),
                'fillcolor_selected' => self::shadeTemplate($shades),
                'fillopacity' => 0.9,
                'fillopacity_selected' => 1,
                'strokecolor' => self::SELECTED_OUTLINE,
                'strokecolor_selected' => self::SELECTED_OUTLINE,
                'strokewidth' => 1,
                'strokewidth_selected' => 5,
            ],
        ];
    }

    /**
     * A style value worked out per feature, from the data set it names.
     *
     * @param array<string, string> $shades
     */
    private static function shadeTemplate(array $shades): string
    {
        return self::valueTemplate($shades, 'dataset');
    }

    /**
     * A value chosen per feature, from the attribute naming its data set.
     *
     * @param array<string, string> $values
     */
    private static function valueTemplate(array $values, string $attribute): string
    {
        $template = '';
        foreach ($values as $id => $value) {
            $template .= sprintf('<%% if (%s === %s) { print(%s) } %%>', $attribute, json_encode($id), json_encode($value));
        }

        return $template;
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
            'template_info' => $this->template($source, $colour),
            'features_style' => [
                'symbol' => 'circle',
                'symbol_selected' => 'circle',
                'radius' => self::POINT_RADIUS,
                'radius_selected' => self::POINT_RADIUS + self::SELECTED_GROWTH,
                'fillcolor' => $colour,
                'fillcolor_selected' => $colour,
                // A solid area would hide whatever another data set put
                // underneath it, which is exactly what we are trying to see.
                // A point hides nothing, and washing it out only makes it
                // harder to pick out against the map.
                'fillopacity' => 1 === self::drawsAreas($source) ? 0.35 : 0.9,
                'fillopacity_selected' => 1 === self::drawsAreas($source) ? 0.75 : 1,
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
                'strokewidth_selected' => 5,
                'strokeopacity_selected' => 1,
            ],
        ];
    }

    /**
     * The popup, rendered per feature by the widget's own templating.
     *
     * Which attributes an entity carries is the source's business, so the
     * template walks whatever arrived rather than naming fields. A click on
     * overlapping features answers with a section each, so every one carries
     * the colour of the layer it came from: without it the sections say which
     * data set they belong to but not which shape on the map they are.
     */
    private function template(SourceInterface $source, string $colour): string
    {
        $title = $source->definition instanceof TestDefinition
            ? $source->definition->title
            : $source->definition->id;

        return $this->body($colour, htmlspecialchars($title, ENT_QUOTES));
    }

    /**
     * The popup for one feature: a heading naming the data set, then whatever
     * attributes the feature carries.
     */
    private function body(string $colour, string $title): string
    {
        // Not sprintf: the widget's own template delimiters are per cent
        // signs, which a format string would try to read as placeholders.
        //
        // The widget hands the template its own bookkeeping alongside the
        // attributes — the geometry, the feature it was built from, an
        // internal id — all of which render as "[object Object]" and none of
        // which is anything a reader asked for. Only the attributes the
        // source published are shown; the source itself is what the heading
        // already says.
        return '<div class="widget-simple-title">'
            .'<span class="test-map-swatch" style="background:'.$colour.'"></span>'
            .$title.'</div>'
            .'<dl class="test-map-details">'
            .'<% Object.keys(obj).filter(function (key) {'
            .' return key.charAt(0) !== "_"'
            .' && ["geometry", "source", "features"].indexOf(key) === -1'
            .' && obj[key] !== null && obj[key] !== "" && obj[key] !== undefined;'
            .' }).forEach(function (key) { %>'
            .'<dt><%= key %></dt>'
            .'<dd><%= Array.isArray(obj[key]) ? obj[key].join(", ") : obj[key] %></dd>'
            .'<% }) %>'
            .'</dl>';
    }
}
