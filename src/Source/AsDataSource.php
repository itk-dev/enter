<?php

declare(strict_types=1);

namespace App\Source;

/**
 * Declares the data set a source class publishes.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsDataSource extends Definition
{
}
