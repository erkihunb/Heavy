<?php

namespace App\Http\Requests\Applicant;

use App\Models\Permit;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdatePermitRequest extends PermitRequest
{
    /**
     * Returning the policy's response keeps its "not found" answer for an
     * application that belongs to another organization.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->permit());
    }

    protected function permit(): Permit
    {
        /** @var Permit $permit */
        $permit = $this->route('permit');

        return $permit;
    }
}
