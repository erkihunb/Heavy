<?php

namespace App\Http\Requests\Admin;

use App\Data\OrganizationData;
use App\Data\VehicleData;
use App\Data\WalkInPermitData;
use App\Domain\Permits\ValidityRules;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PermitType;
use App\Exceptions\DuplicateVehiclePlate;
use App\Http\Requests\Applicant\OrganizationRequest;
use App\Http\Requests\Concerns\ValidatesMovementHours;
use App\Http\Requests\Concerns\ValidatesRequestLetter;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\EthiopianPhone;
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
    use ValidatesMovementHours;
    use ValidatesRequestLetter;

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

        // The form asks for the applicant's e-mail once; the organization's is the same unless given.
        if (blank($this->input('email')) && is_string($this->input('contact_email'))) {
            $this->merge(['email' => $this->input('contact_email')]);
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
        // No payment is taken here: the fee is paid once the permit is approved
        // (SubmitPayment, RecordPayment).

        // An organization already on record: its person and organization are not typed again.
        $new = $this->filled('applicant_id') ? ['exclude'] : [];

        $organization = array_map(
            fn (array $rules): array => [...$new, ...$rules],
            [
                ...parent::rules(),
                // The address may be added later on the organization's profile.
                'address' => ['nullable', 'string', 'max:500'],
            ],
        );

        return [
            'applicant_id' => ['nullable', 'integer', $this->onRecord(...)],
            'contact_name' => [...$new, 'required', 'string', 'max:255'],
            'contact_email' => [...$new, 'required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            'locale' => [...$new, 'required', 'string', Rule::in(array_keys((array) config('localization.supported')))],
            ...$organization,
            'vehicles.*.type_detail' => [...$apply, 'nullable', 'string', 'max:100'],
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
            ...$this->movementHoursRules($apply),
            // Requests come in by letter: its number, date and a scanned copy.
            ...$this->letterRules(scanRequired: true, condition: $apply),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            ...$this->letterMessages(),
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
            // Someone already registered is chosen to file a request for them, not to register again.
            function (Validator $validator): void {
                if ($this->filled('applicant_id') && ! $this->boolean('apply')) {
                    $validator->errors()->add('apply', __('applicants.intake.existing_needs_request'));
                }
            },
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
            // An "other" vehicle is described in words.
            function (Validator $validator): void {
                if (! $this->boolean('apply')) {
                    return;
                }

                $other = VehicleType::query()->where('code', VehicleType::OTHER)->value('id');

                foreach ((array) $this->input('vehicles') as $row => $vehicle) {
                    if ($other !== null && is_array($vehicle) && (int) ($vehicle['vehicle_type_id'] ?? 0) === $other && blank($vehicle['type_detail'] ?? null)) {
                        $validator->errors()->add("vehicles.{$row}.type_detail", __('applicants.intake.type_detail_required'));
                    }
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
            'vehicles.*.type_detail' => __('applicants.intake.type_detail'),
            'permit_type' => __('permits.fields.permit_type'),
            'valid_from' => __('permits.fields.valid_from'),
            'valid_to' => __('permits.fields.valid_to'),
            'reason' => __('permits.fields.reason'),
            ...$this->movementHoursAttributes(),
            ...$this->letterAttributes(),
        ];
    }

    public function toData(): OrganizationData
    {
        /** @var array{name_am: string, name_en?: string|null, tin?: string|null, phone: string, email: string, address?: string|null} $validated */
        $validated = $this->validated();

        return new OrganizationData(
            nameAm: $validated['name_am'],
            nameEn: $validated['name_en'] ?? null,
            tin: $validated['tin'] ?? null,
            phone: (string) EthiopianPhone::normalize($validated['phone']),
            email: $validated['email'],
            address: $validated['address'] ?? '',
        );
    }

    /**
     * @return list<VehicleData> the primary vehicle first, then the trailers; none when only the person is registered
     */
    public function vehicles(): array
    {
        if (! $this->boolean('apply')) {
            return [];
        }

        /** @var list<array{plate: string, vehicle_type_id: int|string, owner_name: string, type_detail?: string|null}> $vehicles */
        $vehicles = $this->validated('vehicles');

        return array_map(fn (array $vehicle): VehicleData => new VehicleData(
            plate: PlateNumber::parse($vehicle['plate']),
            vehicleTypeId: (int) $vehicle['vehicle_type_id'],
            ownerName: $vehicle['owner_name'],
            typeDetail: $vehicle['type_detail'] ?? null,
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
            hours: $this->movementHours(),
            letter: $this->letter(),
        );
    }

    /**
     * The applicant on record the request is filed for, when one was chosen.
     */
    public function existingApplicant(): ?User
    {
        $id = $this->validated('applicant_id');

        return $id === null ? null : User::query()->with('organization')->find((int) $id);
    }

    /**
     * The request asked for, which a request for an organization on record
     * always has (see after()).
     */
    public function permitData(): WalkInPermitData
    {
        $permit = $this->permit();

        abort_if($permit === null, 422);

        return $permit;
    }

    /**
     * An applicant's account with an organization: the record a new request
     * of theirs is filed under.
     */
    private function onRecord(string $attribute, mixed $value, Closure $fail): void
    {
        $applicant = User::query()->find((int) $value);

        if ($applicant === null || ! $applicant->isApplicant() || $applicant->organization_id === null) {
            $fail(__('applicants.intake.existing_unknown'));
        }
    }

    /**
     * A well-formed plate that is in no other organization's register and is
     * not given twice in the form. For an organization on record a plate it
     * already has is its vehicle, used as it is.
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

        $owner = $this->filled('applicant_id')
            ? User::query()->whereKey((int) $this->input('applicant_id'))->value('organization_id')
            : null;

        $taken = Vehicle::query()
            ->where('plate_normalized', $plate->normalized)
            ->when($owner !== null, fn ($query) => $query->where('organization_id', '!=', $owner))
            ->exists();

        if ($taken) {
            $fail((new DuplicateVehiclePlate($plate, false))->userMessage());
        }
    }
}
