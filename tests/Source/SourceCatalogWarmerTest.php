<?php

declare(strict_types=1);

namespace App\Tests\Source;

use App\Source\SourceCatalog;
use App\Source\SourceCatalogWarmer;
use PHPUnit\Framework\TestCase;

class SourceCatalogWarmerTest extends TestCase
{
    /** @var list<string> */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->written = [];
    }

    /**
     * An optional warmer can be skipped, and a manifest that is only read when
     * an import selects it is exactly what warming exists to avoid.
     */
    public function testItIsNotOptional(): void
    {
        $this->assertFalse($this->warmer(\dirname(__DIR__, 2).'/config/sources.yaml')->isOptional());
    }

    public function testItWarmsTheShippedManifestWithoutPreloadingAnything(): void
    {
        $warmer = $this->warmer(\dirname(__DIR__, 2).'/config/sources.yaml');

        $this->assertSame([], $warmer->warmUp(sys_get_temp_dir(), sys_get_temp_dir()));
    }

    /**
     * The reason for warming at all: an entry no import selects still fails the
     * build rather than waiting to be discovered.
     */
    public function testItFailsOnAnEntryNoImportWouldReach(): void
    {
        $warmer = $this->warmer($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    crs: EPSG:25832
                    model: Example
                unreached-source:
                    title: Another source
                    access_url: https://example.com/other.json
                    model: Example
            YAML));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The child config "crs" under "sources.unreached-source" must be configured');

        $warmer->warmUp(sys_get_temp_dir(), sys_get_temp_dir());
    }

    private function warmer(string $manifest): SourceCatalogWarmer
    {
        return new SourceCatalogWarmer(new SourceCatalog($manifest));
    }

    private function manifest(string $yaml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sources-');

        if (false === $path) {
            $this->fail('Could not create a temporary manifest.');
        }

        file_put_contents($path, $yaml);
        $this->written[] = $path;

        return $path;
    }
}
