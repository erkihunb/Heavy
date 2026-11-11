<?php

namespace App\Exceptions;

use InvalidArgumentException;

final class InvalidPlateNumber extends InvalidArgumentException
{
    public static function malformed(string $raw): self
    {
        return new self("Plate number [{$raw}] does not match the format <region>-<code>-<number>.");
    }

    public static function unknownRegion(string $raw, string $prefix): self
    {
        return new self("Plate number [{$raw}] has an unknown region prefix [{$prefix}].");
    }
}
