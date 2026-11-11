<?php

namespace App\Exceptions;

use App\Domain\Vehicles\PlateNumber;
use DomainException;

/**
 * The plate is already in the register. It deliberately carries no detail
 * about the other vehicle beyond whether it belongs to the same organization.
 */
final class DuplicateVehiclePlate extends DomainException
{
    public function __construct(
        public readonly PlateNumber $plate,
        public readonly bool $inSameOrganization,
    ) {
        parent::__construct("Plate number [{$plate->normalized}] is already registered.");
    }

    /**
     * The message shown to the applicant.
     */
    public function userMessage(): string
    {
        return $this->inSameOrganization
            ? __('vehicles.errors.plate_duplicate_own')
            : __('vehicles.errors.plate_duplicate_other');
    }
}
