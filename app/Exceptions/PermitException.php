<?php

namespace App\Exceptions;

use DomainException;

/**
 * A permit rule stopped an Action. The HTTP layer turns these into a 422 for
 * JSON clients and a flashed warning for Inertia pages (see bootstrap/app.php).
 */
abstract class PermitException extends DomainException
{
    /**
     * The message shown to the person who tried the action.
     */
    abstract public function userMessage(): string;
}
