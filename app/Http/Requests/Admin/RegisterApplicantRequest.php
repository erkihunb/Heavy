<?php

namespace App\Http\Requests\Admin;

use App\Data\VehicleData;
use App\Data\WalkInPermitData;
use App\Domain\Permits\ValidityRules;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PermitType;
use App\Exceptions\DuplicateVehiclePlate;
use App\Http\Requests\Applicant\OrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

/**
 * The office's one-page form: the contact person (name, e-mail, language),
 * the organization, and — unless the Registrar only registers the person —
 * the vehicle with its trailers and the permit asked for. Every part is
 * validated as the applicant's own forms are.
 */
class RegisterApplicantRequest extends OrganizationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registerApplicant', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['contact_email', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => mb_strtolower(trim($this->input($field)))]);
            }
        }

        // Trailer rows left empty in the form are not trailers.
        if (is_array($this->input('vehicles'))) {
            $this->merge(['vehicles' => array_values(array_filter(
                $this->input('vehicles'),
                fn (mixed $vehicle, int|string $index): bool => (int) $index === 0
                    || (is_array($vehicle) && collect($vehicle)->filter(fn (mixed $value): bool => filled($value))->isNotEmpty()),
                ARRAY_FILTER_USE_BOTH,
            ))]);
        }
    }

    protected function uniqueTin(): Unique
    {
        return Rule::unique(Organization::class, 'tin');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $apply = ['exclude_unless:apply,true'];

        return [
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('localization.supported')))],
            ...parent::rules(),
            'apply' => ['boolean'],
            'submit' => ['boolean'],
            'vehicles' => [...$apply, 'required', 'array', 'min:1', 'max:'.(1 + (int) config('permits.max_trailers'))],
            'vehicles.*.plate' => [...$apply, 'bail', 'required', 'string', 'max:50', $this->freePlate(...)],
            'vehicles.*.vehicle_type_id' => [...$apply, 'required', 'integer', Rule::exists(VehicleType::class, 'id')->where('is_active', true)],
            'vehicles.*.owner_name' => [...$apply, 'required', 'string', 'max:255'],
            'permit_type' => [...$apply, 'required', Rule::enum(PermitType::class)],
            'valid_from' => [...$apply, 'bail', 'required', 'date_format:Y-m-d'],
            'valid_to' => [...$apply, 'bail', 'required', 'date_format:Y-m-d'],
            'reason' => [...$apply, 'required', 'string', 'max:2000'],
        ];
    }

    /**
     * The limits on the period (spec §4.6), as for the applicant's form.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('apply') || $validator->errors()->hasAny(['valid_from', 'valid_to'])) {
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
            'contact_name' => __('applicants.fields.contact_name'),
            'contact_email' => __('applicants.fields.contact_email'),
            'locale' => __('applicants.fields.locale'),
            ...parent::attributes(),
            'vehicles' => __('permits.fields.vehicles'),
            'vehicles.*.plate' => __('vehicles.fields.plate'),
            'vehicles.*.vehicle_type_id' => __('vehicles.fields.vehicle_type'),
            'vehicles.*.owner_name' => __('vehicles.fields.owner_name'),
            'permit_type' => __('permits.fields.permit_type'),
            'valid_from' => __('permits.fields.valid_from'),
            'valid_to' => __('permits.fields.valid_to'),
            'reason' => __('permits.fields.reason'),
        ];
    }

    /**
     * @return list<VehicleData> the primary vehicle first, then the trailers; none when only the person is registered
     */
    public function vehicles(): array
    {
        if (! $this->boolean('apply')) {
            return [];
        }

        /** @var list<array{plate: string, vehicle_type_id: int|string, owner_name: string}> $vehicles */
        $vehicles = $this->validated('vehicles');

        return array_map(fn (array $vehicle): VehicleData => new VehicleData(
            plate: PlateNumber::parse($vehicle['plate']),
            vehicleTypeId: (int) $vehicle['vehicle_type_id'],
            ownerName: $vehicle['owner_name'],
        ), $vehicles);
    }

    public function permit(): ?WalkInPermitData
    {
        if (! $this->boolean('apply')) {
            return null;
        }

        return new WalkInPermitData(
            permitType: PermitType::from($this->string('permit_type')->toString()),
            validFrom: CarbonImmutable::createFromFormat('!Y-m-d', $this->string('valid_from')->toString(), 'UTC'),
            validTo: CarbonImmutable::createFromFormat('!Y-m-d', $this->string('valid_to')->toString(), 'UTC'),
            reason: $this->string('reason')->toString(),
            submit: $this->boolean('submit'),
        );
    }

    /**
     * A well-formed plate that is in nobody's register yet (the organization
     * is new) and is not given twice in the form.
     */
    private function freePlate(string $attribute, mixed $value, Closure $fail): void
    {
        $plate = PlateNumber::tryParse((string) $value);

        if ($plate === null) {
            $fail(__('vehicles.errors.plate_invalid'));

            return;
        }

        $others = collect((array) $this->input('vehicles'))
            ->except([(int) str($attribute)->between('vehicles.', '.plate')->toString()])
            ->map(fn (mixed $vehicle): ?PlateNumber => PlateNumber::tryParse(is_array($vehicle) ? (string) ($vehicle['plate'] ?? '') : ''))
            ->filter();

        if ($others->contains(fn (PlateNumber $other): bool => $other->equals($plate))) {
            $fail(__('permits.errors.vehicles_duplicate'));

            return;
        }

        if (Vehicle::query()->where('plate_normalized', $plate->normalized)->exists()) {
            $fail((new DuplicateVehiclePlate($plate, false))->userMessage());
        }
    }
}
