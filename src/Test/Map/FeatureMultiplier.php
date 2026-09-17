<?php

declare(strict_types=1);

namespace App\Test\Map;

/**
 * The same features over again, scattered, to draw a map far larger than the
 * one we have data for.
 *
 * Which map library to build on is a decision about the data we are heading
 * towards rather than the data we hold, and a few thousand parking bays are
 * comfortable for all of them. Copying what the broker answers with is the
 * only way to see any of them at a size that hurts before the sources that
 * would hurt them are connected.
 *
 * The scatter is worked out from the copy rather than drawn at random, so two
 * libraries asked for the same multiple are drawing the same map. Comparing
 * them on differently placed points would say very little.
 */
final readonly class FeatureMultiplier
{
    /**
     * As many copies as may be asked for. The collection is built in memory
     * and sent as one response, so the ceiling is there to keep a slip of the
     * finger from asking for a gigabyte. At fifty the two sources between them
     * come to some sixty-seven thousand features and thirty-odd megabytes,
     * which the broker, PHP and all three maps still manage.
     */
    public const int MOST = 50;

    /**
     * How far a copy may fall from the feature it was copied from, in degrees
     * of latitude — a little over a kilometre. Far enough that copies do not
     * simply pile onto the original, close enough that they stay in town.
     */
    private const float SCATTER = 0.01;

    /**
     * @param array{type: string, features: list<array<string, mixed>>} $collection
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function multiply(array $collection, int $times): array
    {
        if (2 > $times) {
            return $collection;
        }

        $features = $collection['features'];
        $copies = $features;

        for ($copy = 1; $copy < min($times, self::MOST); ++$copy) {
            foreach ($features as $position => $feature) {
                $copies[] = $this->copy($feature, $copy, $position);
            }
        }

        return ['type' => $collection['type'], 'features' => $copies];
    }

    /**
     * @param array<string, mixed> $feature
     *
     * @return array<string, mixed>
     */
    private function copy(array $feature, int $copy, int $position): array
    {
        // A whole number derived from which copy of which feature this is:
        // the same copy is in the same place every time it is asked for.
        $seed = crc32($copy.':'.$position);

        $angle = ($seed % 3600) / 3600 * 2 * M_PI;
        $distance = (intdiv($seed, 3600) % 1000) / 1000 * self::SCATTER;

        $latitude = $distance * sin($angle);
        // A degree of longitude is shorter the further north it is, so a copy
        // shifted by the same number of degrees either way would land on an
        // ellipse rather than a circle.
        $longitude = $distance * cos($angle) / cos(deg2rad(56.0));

        $feature['geometry'] = $this->shift($feature['geometry'] ?? null, $longitude, $latitude);

        if (isset($feature['properties']['id'])) {
            $feature['properties']['id'] .= '#'.$copy;
        }

        return $feature;
    }

    /**
     * A geometry moved bodily, whatever its nesting: a copied area has to keep
     * its shape, which means every one of its coordinates moves alike.
     *
     * @param array<string, mixed>|null $geometry
     *
     * @return array<string, mixed>|null
     */
    private function shift(?array $geometry, float $longitude, float $latitude): ?array
    {
        if (null === $geometry || !isset($geometry['coordinates'])) {
            return $geometry;
        }

        $geometry['coordinates'] = $this->shiftCoordinates($geometry['coordinates'], $longitude, $latitude);

        return $geometry;
    }

    /**
     * @param array<array-key, mixed> $coordinates
     *
     * @return array<array-key, mixed>
     */
    private function shiftCoordinates(array $coordinates, float $longitude, float $latitude): array
    {
        if (is_numeric($coordinates[0] ?? null)) {
            return [$coordinates[0] + $longitude, $coordinates[1] + $latitude];
        }

        return array_map(
            fn (mixed $nested): array => \is_array($nested)
                ? $this->shiftCoordinates($nested, $longitude, $latitude)
                : [],
            $coordinates
        );
    }
}
