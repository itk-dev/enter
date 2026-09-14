<?php

declare(strict_types=1);

namespace App\Source;

/**
 * Declares the data set a source class publishes.
 *
 * The attribute is the definition rather than a description of one, so the
 * fields a source states are the fields the application reads, with nothing
 * mapping one onto the other.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsDataSource extends Definition
{
}
