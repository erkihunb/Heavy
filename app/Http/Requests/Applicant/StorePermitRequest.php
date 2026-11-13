<?php

namespace App\Http\Requests\Applicant;

use App\Models\Permit;

abstract class StorePermitRequest extends PermitRequest
{
    abstract public function authorize(): bool;

    protected function permit(): ?Permit
    {
        return null;
    }
}
