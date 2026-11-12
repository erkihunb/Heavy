<?php

namespace App\Http\Requests\Registrar;

use App\Http\Requests\Applicant\UpdatePermitRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdatePermitForApplicantRequest extends UpdatePermitRequest
{
    use ActsForApplicant;

    public function authorize(): Response
    {
        return Gate::inspect('updateFor', [$this->permit(), $this->applicant()]);
    }
}
