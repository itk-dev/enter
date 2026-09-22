<?php

declare(strict_types=1);

namespace App\Test\Map;

use App\Source\SourceInterface;

/**
 * The test map, described for the page to draw.
 *
 * Distances are given as ground resolutions, in metres per pixel, rather than
 * as zoom levels: how far out points are grouped and how close a group is
 * zoomed to are distances on the ground, and the map converts them to its own
 * zoom at the latitude it opens at.
 */
final readonly class MapSpec
{
    /**
     * Plain raster tiles. The background is not what the map is for; the data
     * drawn over it is.
     */
    private const array BACKGROUND = [
        'tiles' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-bidragydere',
        'maxZoom' => 19,
    ];

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
        return [
            'view' => $this->view($base),
            'background' => self::BACKGROUND,
            'featuresPerClick' => MapLayers::FEATURES_PER_CLICK,
            'point' => [
                'radius' => MapLayers::POINT_RADIUS,
                'outline' => MapLayers::POINT_OUTLINE,
                'width' => MapLayers::STROKE_WIDTH,
            ],
            'cluster' => [
                'id' => MapLayers::COMBINED_ID,
                'url' => $dataUrl(MapLayers::COMBINED_ID),
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
                    'fillOpacity' => $layer->fillOpacity(),
                ],
                $this->layers->build($sources, $dataUrl)
            ),
        ];
    }

    /**
     * Where the map opens.
     *
     * @param array<string, mixed> $base
     *
     * @return array{longitude: float, latitude: float, resolution: float}
     */
    private function view(array $base): array
    {
        $view = $base['view'] ?? [];

        return [
            'longitude' => (float) ($view['longitude'] ?? 0),
            'latitude' => (float) ($view['latitude'] ?? 0),
            'resolution' => (float) ($view['resolution'] ?? 0),
        ];
    }
}
