<?php

declare(strict_types=1);

namespace App\Broker\Exception;

/**
 * The broker holds more entities than one request may return.
 *
 * Its maximum result size has fallen behind the data, so the answer was a
 * page rather than the whole set. Passing that on would leave whatever drew
 * it quietly wrong, which is worth failing over rather than logging.
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
