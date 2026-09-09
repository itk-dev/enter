<?php

declare(strict_types=1);

namespace App\Source;

use App\Ngsi\NgsiEntity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Converts one input data set into NGSI-LD entities.
 *
 * An implementation owns its feed's origin, field names, quirks and target
 * Smart Data Model. The broker and the import know none of that, so a new
 * ENTER data set costs exactly one class.
 */
#[AutoconfigureTag('app.source')]
interface SourceInterface
{
    /**
     * Unique identifier for this source.
     */
    public function key(): string;

    /**
     * @return iterable<NgsiEntity>
     */
    public function entities(): iterable;
}
