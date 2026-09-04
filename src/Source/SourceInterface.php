<?php

declare(strict_types=1);

namespace App\Source;

use App\Ngsi\NgsiEntity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One input data set, converted to NGSI-LD entities.
 *
 * Implementations own everything specific to their feed: where it comes from,
 * its field names, its quirks, and which Smart Data Model it maps onto. The
 * broker and the import command stay unaware of all of it, so adding the next
 * ENTER data set means adding one class and nothing else.
 */
#[AutoconfigureTag('app.source')]
interface SourceInterface
{
    /**
     * Identifier used to select this source on the command line.
     */
    public function key(): string;

    /**
     * @return iterable<NgsiEntity>
     */
    public function entities(): iterable;
}
