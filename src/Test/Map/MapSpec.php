<?php

declare(strict_types=1);

namespace App\Test\Map;

use App\Geo\Wgs84Transformer;
use App\Source\SourceInterface;

/**
 * The test map described without reference to any map library.
 *
 * The widget is configured in its own vocabulary, which Leaflet and MapLibre
 * do not speak; what all three have in common is the data sets, the colours,
 * the sizes and where the map opens. Serving that once is what keeps the
 * three maps comparable — a difference between them is then a difference
 * between the libraries rather than between three configurations.
 *
 * Distances are given as ground resolutions, in metres per pixel, rather than
 * as zoom levels: a zoom level means something different on every tile scheme,
 * while a metre is a metre. Each map converts to its own zoom.
 */
final readonly class MapSpec
{
    /**
     * The coordinate system the widget's view is written in.
     */
    private const string VIEW_CRS = 'EPSG:25832';

    /**
     * Metres to the pixel at the top of the Danish standard tile matrix, the
     * scheme the background map is cut to. Each level below it is half the
     * one above, so the view's zoom level becomes a resolution the other two
     * libraries can be opened at.
     */
    private const float MATRIX_TOP_RESOLUTION = 1638.4;

    /**
     * Plain raster tiles, and the same ones for both libraries.
     *
     * The background is not what is being compared; the data drawn over it is.
     * Giving MapLibre a vector background would have it drawing the whole map
     * on the GPU while Leaflet drew images, which flatters it for reasons that
     * have nothing to do with our features.
     */
    private const array BACKGROUND = [
        'tiles' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-bidragydere',
        'maxZoom' => 19,
    ];

    public function __construct(
        private Wgs84Transformer $transformer,
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
        return [
            'view' => $this->view($base),
            'background' => self::BACKGROUND,
            'featuresPerClick' => MapLayers::FEATURES_PER_CLICK,
            'point' => [
                'radius' => MapLayers::POINT_RADIUS,
                'selectedRadius' => MapLayers::POINT_RADIUS + MapLayers::SELECTED_GROWTH,
                'outline' => MapLayers::SELECTED_OUTLINE,
                'width' => MapLayers::STROKE_WIDTH,
                'selectedWidth' => MapLayers::SELECTED_WIDTH,
            ],
            'cluster' => [
                'id' => MapLayers::COMBINED_ID,
                'url' => $dataUrl(MapLayers::COMBINED_ID),
                'title' => 'Alle datasæt',
                'distance' => MapLayers::CLUSTER_DISTANCE,
                'radius' => MapLayers::CLUSTER_RADIUS,
                'fill' => MapLayers::MIXED_CLUSTER,
                'outline' => MapLayers::darken(MapLayers::MIXED_CLUSTER),
                'untilResolution' => MapLayers::CLUSTER_UNTIL_RESOLUTION,
                'closestResolution' => MapLayers::CLOSEST_RESOLUTION,
                'padding' => MapLayers::ZOOM_PADDING,
            ],
            'layers' => array_map(
                static fn (MapLayer $layer): array => [
                    'id' => $layer->id,
                    'title' => $layer->title,
                    'url' => $layer->url,
                    'colour' => $layer->colour,
                    'outline' => $layer->outline(),
                    'areas' => $layer->areas,
                    'zIndex' => $layer->zIndex(),
                    'fillOpacity' => $layer->fillOpacity(),
                    'selectedFillOpacity' => $layer->fillOpacity(selected: true),
                ],
                $this->layers->build($sources, $dataUrl)
            ),
        ];
    }

    /**
     * Where the map opens, in the terms a web map understands.
     *
     * @param array<string, mixed> $base
     *
     * @return array{longitude: float, latitude: float, resolution: float}
     */
    private function view(array $base): array
    {
        $view = $base['map']['view'] ?? [];

        [$longitude, $latitude] = $this->transformer->toWgs84(
            self::VIEW_CRS,
            (float) ($view['x'] ?? 0),
            (float) ($view['y'] ?? 0)
        );

        return [
            'longitude' => $longitude,
            'latitude' => $latitude,
            'resolution' => self::MATRIX_TOP_RESOLUTION / 2 ** (float) ($view['zoomLevel'] ?? 0),
        ];
    }
}
