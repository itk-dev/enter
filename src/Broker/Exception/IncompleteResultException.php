<?php

declare(strict_types=1);

namespace App\Broker\Exception;

/**
 * The broker holds more entities than one request may return.
 */
final class IncompleteResultException extends \RuntimeException
{
    public function __construct(
        public readonly string $path,
        public readonly int $received,
        public readonly int $total,
    ) {
        parent::__construct(\sprintf(
            'Broker returned %d of %d entities for "%s"; raise its maximum result size.',
            $received,
            $total,
            $path,
        ));
    }
}
