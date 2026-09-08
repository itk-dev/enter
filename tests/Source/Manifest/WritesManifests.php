<?php

declare(strict_types=1);

namespace App\Tests\Source\Manifest;

/**
 * Writes a throwaway manifest registering one entry per source key.
 *
 * Anything reading the manifest needs an entry for every key it will be asked
 * about, and the entries themselves carry nothing a test asserts on beyond the
 * context URL, which is derived from the key.
 */
trait WritesManifests
{
    /** @var list<string> */
    private array $manifests = [];

    protected function tearDown(): void
    {
        foreach ($this->manifests as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->manifests = [];
    }

    protected static function dataSetContext(string $key): string
    {
        return \sprintf('https://example.com/%s.jsonld', $key);
    }

    /**
     * @param list<string> $keys
     *
     * @return string path to the manifest
     */
    protected function manifestFor(array $keys): string
    {
        $entries = array_map(static fn (string $key): string => \sprintf(
            "    %s:\n        title: %s\n        access_url: https://example.com/%s.json\n        crs: EPSG:25832\n        model: Example\n        context_url: %s",
            $key,
            $key,
            $key,
            self::dataSetContext($key),
        ), $keys);

        $path = tempnam(sys_get_temp_dir(), 'sources-');

        if (false === $path) {
            $this->fail('Could not create a temporary manifest.');
        }

        file_put_contents($path, "sources:\n".implode("\n", $entries)."\n");
        $this->manifests[] = $path;

        return $path;
    }
}
