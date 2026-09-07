<?php

declare(strict_types=1);

namespace App\Source;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Reads the source manifest during container warm-up.
 *
 * Reading it on first use validates only the entry an import selects, so a
 * malformed record for any other data set survives on the default branch until
 * someone imports it. Warming turns every entry into a build failure instead.
 */
final readonly class SourceCatalogWarmer implements CacheWarmerInterface
{
    public function __construct(
        private SourceCatalog $catalog,
    ) {
    }

    /**
     * The manifest is not a cache that can be rebuilt on demand — the point is
     * to fail the build, so this warmer must not be skippable.
     */
    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->catalog->all();

        return [];
    }
}
