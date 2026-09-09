<?php

declare(strict_types=1);

namespace App\Tests\Source\Manifest;

use App\Source\Manifest\Catalog;
use App\Source\Manifest\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
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
     * A check that can be skipped is not a check.
     */
    public function testItIsNotOptional(): void
    {
        $this->assertFalse($this->validator(\dirname(__DIR__, 3).'/config/sources.yaml')->isOptional());
    }

    /**
     * It validates rather than caches, so it leaves nothing behind to preload.
     */
    public function testItAcceptsTheShippedManifestAndWritesNothing(): void
    {
        $validator = $this->validator(\dirname(__DIR__, 3).'/config/sources.yaml');

        $this->assertSame([], $validator->warmUp(sys_get_temp_dir(), sys_get_temp_dir()));
    }

    /**
     * The reason for checking at build time: an entry no import selects still
     * fails the build rather than waiting to be discovered.
     */
    public function testItFailsOnAnEntryNoImportWouldReach(): void
    {
        $validator = $this->validator($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    crs: EPSG:25832
                    model: Example
                    context_url: https://example.com/context.jsonld
                unreached-source:
                    title: Another source
                    access_url: https://example.com/other.json
                    model: Example
                    context_url: https://example.com/context.jsonld
            YAML));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The child config "crs" under "sources.unreached-source" must be configured');

        $validator->warmUp(sys_get_temp_dir(), sys_get_temp_dir());
    }

    private function validator(string $manifest): Validator
    {
        return new Validator(new Catalog($manifest));
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
