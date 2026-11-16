<?php

namespace App\Http\Requests\Registrar;

use App\Data\PermitData;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitType;
use App\Http\Requests\Concerns\ValidatesMovementHours;
use App\Http\Requests\Concerns\ValidatesRequestLetter;
use App\Models\Branch;
use App\Models\Permit;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * Correcting a request taken at the office. Dates arrive as Gregorian
 * `Y-m-d`; the Ethiopian date picker converts before sending. A new scan of
 * the letter is needed only when none is stored yet.
 */
class UpdatePermitForApplicantRequest extends FormRequest
{
    use ActsForApplicant;
    use ValidatesMovementHours;
    use ValidatesRequestLetter;

    /**
     * Returning the policy's response keeps its "not found" answer for an
     * application that belongs to another organization.
     */
    public function authorize(): Response
    {
        return Gate::inspect('updateFor', [$this->permit(), $this->applicant()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permit_type' => ['required', Rule::enum(PermitType::class)],
            'primary_vehicle_id' => ['bail', 'required', 'string', $this->ownVehicle()],
            'trailer_vehicle_ids' => ['nullable', 'array', 'max:'.config('permits.max_trailers')],
            'trailer_vehicle_ids.*' => ['bail', 'required', 'string', 'distinct', 'different:primary_vehicle_id', $this->ownVehicle()],
            'valid_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'valid_to' => ['bail', 'required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:2000'],
            // Empty means the head office. A closed branch takes no new applications,
            // but an application already made there may stay.
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhere('id', $this->permit()->branch_id))],
            ...$this->movementHoursRules(),
            ...$this->letterRules(scanRequired: $this->permit()->letter_file_path === null),
        ];
    }

    /**
     * The limits on the period (spec §4.6), checked once both dates are well formed.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['valid_from', 'valid_to'])) {
                    return;
                }

                $violation = app(ValidityRules::class)->violation(
                    CarbonImmutable::createFromFormat('!Y-m-d', $this->string('valid_from')->toString(), 'UTC'),
                    CarbonImmutable::createFromFormat('!Y-m-d', $this->string('valid_to')->toString(), 'UTC'),
                );

                if ($violation !== null) {
                    $validator->errors()->add(
                        $violation->rule === 'valid_from_in_past' ? 'valid_from' : 'valid_to',
                        $violation->userMessage(),
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'permit_type' => __('permits.fields.permit_type'),
            'primary_vehicle_id' => __('permits.fields.primary_vehicle'),
            'trailer_vehicle_ids' => __('permits.fields.trailers'),
            'trailer_vehicle_ids.*' => __('permits.fields.trailer'),
            'valid_from' => __('permits.fields.valid_from'),
            'valid_to' => __('permits.fields.valid_to'),
            'reason' => __('permits.fields.reason'),
            'branch_id' => __('permits.fields.branch'),
            ...$this->movementHoursAttributes(),
            ...$this->letterAttributes(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_vehicle_id.exists' => __('permits.errors.vehicles_not_owned'),
            'trailer_vehicle_ids.*.exists' => __('permits.errors.vehicles_not_owned'),
            'trailer_vehicle_ids.*.distinct' => __('permits.errors.vehicles_duplicate'),
            'trailer_vehicle_ids.*.different' => __('permits.errors.vehicles_duplicate'),
            'trailer_vehicle_ids.max' => __('permits.errors.too_many_trailers', ['max' => config('permits.max_trailers')]),
            ...$this->movementHoursMessages(),
            ...$this->letterMessages(),
        ];
    }

    public function toData(): PermitData
    {
        /** @var array{permit_type: string, primary_vehicle_id: string, trailer_vehicle_ids?: array<array-key, string>|null, valid_from: string, valid_to: string, reason: string, branch_id?: int|string|null} $validated */
        $validated = $this->validated();

        return $this->withLetter(new PermitData(
            permitType: PermitType::from($validated['permit_type']),
            primaryVehicleId: $validated['primary_vehicle_id'],
            trailerVehicleIds: array_values($validated['trailer_vehicle_ids'] ?? []),
            validFrom: CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_from'], 'UTC'),
            validTo: CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_to'], 'UTC'),
            reason: trim($validated['reason']),
            branchId: isset($validated['branch_id']) && $validated['branch_id'] !== '' ? (int) $validated['branch_id'] : null,
            hours: $this->movementHours(),
        ));
    }

    private function permit(): Permit
    {
        /** @var Permit $permit */
        $permit = $this->route('permit');

        return $permit;
    }

    /**
     * Only vehicles in the register of the application's organization.
     */
    private function ownVehicle(): Exists
    {
        return Rule::exists(Vehicle::class, 'id')->where('organization_id', $this->permit()->organization_id);
    }
}
