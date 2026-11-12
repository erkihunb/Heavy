<?php

namespace App\Http\Requests\Registrar;

use App\Data\PermitData;
use App\Http\Requests\Applicant\StorePermitRequest;
use App\Http\Requests\Concerns\ValidatesRequestLetter;

/**
 * A request an applicant brought to the office by letter: the application
 * details, plus the letter's number, date and scanned copy.
 */
class StorePermitForApplicantRequest extends StorePermitRequest
{
    use ActsForApplicant;
    use ValidatesRequestLetter;

    public function authorize(): bool
    {
        return $this->mayActForApplicant();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), ...$this->letterRules(scanRequired: true)];
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
