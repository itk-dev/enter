<?php

declare(strict_types=1);

namespace App\Tests\Test\Map;

use App\Source\SourceInterface;
use App\Test\Map\MapLayer;
use App\Test\Map\MapLayers;
use App\Tests\Support\FakeSource;
use PHPUnit\Framework\TestCase;

class MapLayersTest extends TestCase
{
    public function testItSaysWhichModelEachDataSetPublishesInto(): void
    {
        $layers = $this->build([
            'bays' => FakeSource::create('Bays', 'https://bays.example/feed', id: 'bays', model: 'OnStreetParking'),
            'toilets' => FakeSource::create('Toilets', 'https://toilets.example/feed', id: 'toilets', model: 'PublicToilet'),
        ]);

        $this->assertSame(
            ['bays' => 'OnStreetParking', 'toilets' => 'PublicToilet'],
            array_combine(
                array_map(static fn (MapLayer $layer): string => $layer->id, $layers),
                array_map(static fn (MapLayer $layer): string => $layer->model, $layers),
            )
        );
    }

    /**
     * Two data sets are only told apart by colour, so a colour that came
     * round again would make two of them look like one.
     */
    public function testItGivesEachOfEightDataSetsAColourOfItsOwn(): void
    {
        $sources = [];
        for ($n = 1; $n <= 8; ++$n) {
            $id = sprintf('set-%d', $n);
            $sources[$id] = FakeSource::create($id, sprintf('https://%s.example/feed', $id), id: $id);
        }

        $colours = array_map(static fn (MapLayer $layer): string => $layer->colour, $this->build($sources));

        $this->assertCount(8, array_unique($colours));
    }

    /**
     * @param array<string, SourceInterface> $sources
     *
     * @return list<MapLayer>
     */
    private function build(array $sources): array
    {
        return new MapLayers()->build($sources, static fn (string $id): string => '/map/'.$id);
    }
}
