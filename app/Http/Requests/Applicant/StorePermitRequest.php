<?php

namespace App\Http\Requests\Applicant;

use App\Models\Permit;

class StorePermitRequest extends PermitRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Permit::class);
    }

    protected function permit(): ?Permit
    {
        return null;
    }
}
