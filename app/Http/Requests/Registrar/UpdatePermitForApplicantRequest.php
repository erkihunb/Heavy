<?php

namespace App\Http\Requests\Registrar;

use App\Data\PermitData;
use App\Http\Requests\Applicant\UpdatePermitRequest;
use App\Http\Requests\Concerns\ValidatesRequestLetter;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Correcting a request taken at the office. A new scan of the letter is
 * needed only when none is stored yet.
 */
class UpdatePermitForApplicantRequest extends UpdatePermitRequest
{
    use ActsForApplicant;
    use ValidatesRequestLetter;

    public function authorize(): Response
    {
        return Gate::inspect('updateFor', [$this->permit(), $this->applicant()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), ...$this->letterRules(scanRequired: $this->permit()->letter_file_path === null)];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...parent::attributes(), ...$this->letterAttributes()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), ...$this->letterMessages()];
    }

    public function toData(): PermitData
    {
        return $this->withLetter(parent::toData());
    }
}
