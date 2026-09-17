<?php

declare(strict_types=1);

namespace App\Test\Map;

use App\Source\SourceInterface;
use App\Test\Source\TestDefinition;

/**
 * The widget configuration for the test map.
 *
 * The base — where the map opens, which background it draws — is read from a
 * file. The feature layers are not: there is one per test source, and which
 * sources exist is only known once the container has them.
 *
 * What those layers are is {@see MapLayers}, which the Leaflet and MapLibre
 * maps read as well; this class only says how to put it to the widget.
 */
final readonly class MapConfig
{
    /**
     * The name of the element on the page that the layer toggles render into.
     */
    public const string TOGGLES_ELEMENT = 'layers';

    public function __construct(
        private MapLayers $layers = new MapLayers(),
    ) {
    }

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

        $drawn = $this->layers->build($sources, $dataUrl);

        $shades = [];
        foreach ($drawn as $layer) {
            $shades[$layer->id] = $layer->colour;
        }

        $titles = [];
        foreach ($sources as $id => $source) {
            $titles[$id] = $source->definition->title;
        }

        foreach ($drawn as $layer) {
            $layers[] = [
                ...$this->layer($layer, $sources[$layer->id]),
                // The grouped view of the same points is drawn instead while
                // the map is far enough out for them to pile up.
                'minResolution' => 0,
                'maxResolution' => MapLayers::CLUSTER_UNTIL_RESOLUTION,
            ];
        }

        $layers[] = $this->clusterLayer($dataUrl(MapLayers::COMBINED_ID), $shades, $titles);

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
                'multifeature' => MapLayers::FEATURES_PER_CLICK,
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
                'excludeLayers' => [MapLayers::COMBINED_ID],
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
            'id' => MapLayers::COMBINED_ID,
            'name' => 'Alle datasæt',
            'type' => 'geojson',
            'features' => true,
            'features_host' => $url,
            'visible' => true,
            'srs' => 'EPSG:4326',
            'zIndex' => MapLayers::Z_POINTS,
            'minResolution' => MapLayers::CLUSTER_UNTIL_RESOLUTION,
            // A feature the popup has no template for is left out of it, and
            // a lone point far out is drawn from here rather than from its own
            // data set's layer: without this, clicking one says nothing.
            'template_info' => $this->body(self::shadeTemplate($shades), self::valueTemplate($titles, 'dataset')),
            'cluster' => [
                'distance' => MapLayers::CLUSTER_DISTANCE,
                'features_style' => [
                    'symbol' => 'circle',
                    'radius' => MapLayers::CLUSTER_RADIUS,
                    'radius_selected' => MapLayers::CLUSTER_RADIUS + 2,
                    // A group can hold more than one data set, so it is drawn
                    // in neither of their colours rather than in a colour that
                    // would claim it belongs to one of them.
                    'fillcolor' => MapLayers::MIXED_CLUSTER,
                    'fillopacity' => 0.95,
                    'strokecolor' => MapLayers::darken(MapLayers::MIXED_CLUSTER),
                    'strokewidth' => MapLayers::STROKE_WIDTH,
                ],
            ],
            'features_style' => [
                'symbol' => 'circle',
                'radius' => MapLayers::POINT_RADIUS,
                'radius_selected' => MapLayers::POINT_RADIUS + MapLayers::SELECTED_GROWTH,
                'fillcolor' => self::shadeTemplate($shades),
                'fillcolor_selected' => self::shadeTemplate($shades),
                'fillopacity' => 0.9,
                'fillopacity_selected' => 1,
                'strokecolor' => MapLayers::SELECTED_OUTLINE,
                'strokecolor_selected' => MapLayers::SELECTED_OUTLINE,
                'strokewidth' => 1,
                'strokewidth_selected' => MapLayers::SELECTED_WIDTH,
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
    private function layer(MapLayer $layer, SourceInterface $source): array
    {
        return [
            'id' => $layer->id,
            'name' => $layer->title,
            'title' => $layer->title,
            'type' => 'geojson',
            'features' => true,
            'features_host' => $layer->url,
            'visible' => true,
            'srs' => 'EPSG:4326',
            'zIndex' => $layer->zIndex(),
            'template_info' => $this->template($source, $layer->colour),
            'features_style' => [
                'symbol' => 'circle',
                'symbol_selected' => 'circle',
                'radius' => MapLayers::POINT_RADIUS,
                'radius_selected' => MapLayers::POINT_RADIUS + MapLayers::SELECTED_GROWTH,
                'fillcolor' => $layer->colour,
                'fillcolor_selected' => $layer->colour,
                'fillopacity' => $layer->fillOpacity(),
                'fillopacity_selected' => $layer->fillOpacity(selected: true),
                'strokecolor' => $layer->outline(),
                'strokecolor_selected' => MapLayers::SELECTED_OUTLINE,
                'strokeopacity' => 1,
                'strokewidth' => MapLayers::STROKE_WIDTH,
                'strokewidth_selected' => MapLayers::SELECTED_WIDTH,
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
