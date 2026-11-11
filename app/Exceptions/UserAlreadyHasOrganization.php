<?php

namespace App\Exceptions;

use DomainException;

final class UserAlreadyHasOrganization extends DomainException
{
    public function __construct()
    {
        parent::__construct('The user already belongs to an organization.');
    }
}
