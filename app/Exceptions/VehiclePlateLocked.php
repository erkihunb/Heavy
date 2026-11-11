<?php

namespace App\Exceptions;

use DomainException;

/**
 * The plate of a vehicle that is on a submitted application cannot be changed:
 * that would rewrite the history of the permit.
 */
final class VehiclePlateLocked extends DomainException
{
    public function __construct(public readonly string $plate)
    {
        parent::__construct("The plate of vehicle [{$plate}] is locked because it is on a permit.");
    }

    /**
     * The message shown to the applicant.
     */
    public function userMessage(): string
    {
        return __('vehicles.errors.plate_locked');
    }
}
