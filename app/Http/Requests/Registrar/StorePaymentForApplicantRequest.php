<?php

namespace App\Http\Requests\Registrar;

use App\Http\Requests\Applicant\StorePaymentRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class StorePaymentForApplicantRequest extends StorePaymentRequest
{
    use ActsForApplicant;

    public function authorize(): Response
    {
        return Gate::inspect('submitPaymentFor', [$this->route('permit'), $this->applicant()]);
    }
}
