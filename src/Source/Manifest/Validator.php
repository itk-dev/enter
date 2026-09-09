<?php

declare(strict_types=1);

namespace App\Source\Manifest;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Reads the whole source manifest when the application is built, so that every
 * record is validated.
 */
final readonly class Validator implements CacheWarmerInterface
{
    public function __construct(
        private Catalog $catalog,
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    /**
     * @return array<never> nothing is written, so nothing is preloaded
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->catalog->all();

        return [];
    }
}
