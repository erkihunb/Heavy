<?php

namespace App\Domain\Legacy;

use RuntimeException;

/**
 * A legacy row cannot be imported safely; it is recorded as an issue instead.
 */
final class RowSkipped extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        public readonly ?string $rawValue,
        string $message,
    ) {
        parent::__construct($message);
    }
}
