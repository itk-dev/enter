<?php

declare(strict_types=1);

namespace App\Test\Map;

use App\Source\DataType;
use App\Source\SourceInterface;

/**
 * What the test map draws, and how.
 *
 * Which data sets there are, in what order, in which colours and at what
 * sizes is decided here, once, and read wherever the map is described.
 */
final readonly class MapLayers
{
    /**
     * One colour per data set, in the order the sources come. Two sources
     * describing the same bays are only told apart by colour, so these need
     * to stay clearly distinct rather than merely different.
     */
    private const array COLOURS = ['#e6194b', '#3e7bfa', '#2ca02c', '#ff7f0e', '#9467bd'];

    /**
     * Stands for every data set at once, when they are asked for together.
     */
    public const string COMBINED_ID = 'all';

    /**
     * How many features one click may reveal. Without it a map shows a single
     * feature, which hides every point that happens to sit under a polygon —
     * and the two data sets overlap by design.
     */
    public const int FEATURES_PER_CLICK = 10;

    /**
     * Small enough that neighbouring bays stay apart at street zoom, while
     * still giving a click something to land on.
     */
    public const int POINT_RADIUS = 4;

    /**
     * The outline of a point drawn in the grouped view, where its fill is the
     * data set's colour and nothing else tells it from the map behind it.
     */
    public const string POINT_OUTLINE = '#000000';

    public const float STROKE_WIDTH = 1.5;

    /**
     * The colour of a group that may hold more than one data set.
     */
    public const string MIXED_CLUSTER = '#4a4a6a';

    /**
     * How far apart, in pixels, points have to be before they are drawn as
     * separate markers. Wider than the library's own default, which leaves
     * groups jostling and points sitting on their edges; this buys them room.
     */
    public const int CLUSTER_DISTANCE = 80;

    /**
     * The size of a cluster marker, whatever it stands for.
     */
    public const int CLUSTER_RADIUS = 13;

    /**
     * The resolution, in metres per pixel, at which points stop being grouped.
     * Zoomed in this far the bays are metres apart on screen and stand on
     * their own; a marker saying "2" tells the reader less than the two
     * points it is hiding.
     */
    public const float CLUSTER_UNTIL_RESOLUTION = 2.0;

    /**
     * A group of points all at one spot has no extent to speak of, so zooming
     * to one stops here rather than running to the map's own limit.
     */
    public const float CLOSEST_RESOLUTION = 0.4;

    /**
     * How much map is left around a group once zoomed to it, in pixels.
     */
    public const int ZOOM_PADDING = 90;

    /**
     * The data sets, in the order they are to be drawn.
     *
     * @param array<string, SourceInterface> $sources
     * @param callable(string): string       $dataUrl builds the features URL for a source id
     *
     * @return list<MapLayer>
     */
    public function build(array $sources, callable $dataUrl): array
    {
        $layers = [];
        $position = 0;
        foreach ($this->ordered($sources) as $id => $source) {
            $layers[] = new MapLayer(
                id: $id,
                title: $source->definition->title,
                url: $dataUrl($id),
                colour: self::COLOURS[$position % count(self::COLOURS)],
                areas: self::drawsAreas($source),
            );
            ++$position;
        }

        return $layers;
    }

    /**
     * A darker shade of a colour, for an edge against its own fill.
     */
    public static function darken(string $colour, float $by = 0.45): string
    {
        [$r, $g, $b] = sscanf($colour, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', (int) ($r * (1 - $by)), (int) ($g * (1 - $by)), (int) ($b * (1 - $by)));
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
        uasort($sources, static fn (SourceInterface $a, SourceInterface $b): int => (int) self::drawsAreas($b) <=> (int) self::drawsAreas($a));

        return $sources;
    }

    private static function drawsAreas(SourceInterface $source): bool
    {
        return DataType::Overpass === $source->definition->dataType;
    }
}
