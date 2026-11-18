<?php

namespace App\Exceptions;

/**
 * One vehicle of a request letter stopped an action taken on the letter's
 * permits together; nothing was saved. The message names the vehicle and
 * what stopped it.
 */
final class LetterVehicleRefused extends PermitException
{
    public function __construct(
        public readonly string $plate,
        public readonly PermitException $reason,
    ) {
        parent::__construct($reason->getMessage(), 0, $reason);
    }

    public function userMessage(): string
    {
        return __('letters.errors.vehicle_refused', ['plate' => $this->plate, 'reason' => $this->reason->userMessage()]);
    }
}
