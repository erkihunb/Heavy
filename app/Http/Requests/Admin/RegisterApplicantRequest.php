<?php

namespace App\Http\Requests\Admin;

use App\Data\OrganizationData;
use App\Data\PaymentData;
use App\Data\VehicleData;
use App\Data\WalkInPermitData;
use App\Domain\Payments\ReceiptNumber;
use App\Domain\Permits\FeeCalculator;
use App\Domain\Permits\ValidityRules;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PaymentMethod;
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
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
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

        if (is_string($this->input('receipt_number'))) {
            $this->merge(['receipt_number' => ReceiptNumber::normalize($this->input('receipt_number'))]);
        }
    }

    /**
     * Whether the receipt is taken now: only when the request is submitted.
     */
    private function paying(): bool
    {
        return $this->boolean('apply') && $this->boolean('submit') && filled($this->input('receipt_number'));
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
        // The receipt and the confirmation go with a submitted request, not with a draft.
        $pay = ['exclude_unless:apply,true', 'exclude_unless:submit,true'];

        return [
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('localization.supported')))],
            ...parent::rules(),
            // The address may be added later on the organization's profile.
            'address' => ['nullable', 'string', 'max:500'],
            'vehicles.*.type_detail' => [...$apply, 'nullable', 'string', 'max:100'],
            'amount' => [...$pay, 'bail', 'nullable', 'required_with:receipt_number,paid_at,receipt_file', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! Money::isValid($value)) {
                    $fail(__('payments.errors.amount_invalid'));
                }
            }],
            'receipt_number' => [...$pay, 'bail', 'nullable', 'required_with:amount,paid_at,receipt_file', 'string', 'max:'.ReceiptNumber::MAX_LENGTH, function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! ReceiptNumber::isValid($value)) {
                    $fail(__('payments.errors.receipt_invalid'));
                }
            }],
            'paid_at' => [...$pay, 'bail', 'nullable', 'required_with:amount,receipt_number,receipt_file', 'date_format:Y-m-d', 'before_or_equal:'.ValidityRules::today()->toDateString()],
            // The content is sniffed, not the extension the browser sent.
            'receipt_file' => [...$pay, 'nullable', 'required_with:amount,receipt_number,paid_at', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max((int) config('permits.receipt_max_kilobytes'))],
            'confirmed' => [...$pay, 'accepted'],
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
            'paid_at.before_or_equal' => __('payments.errors.paid_in_future'),
            'receipt_file.mimes' => __('payments.errors.file_type'),
            'receipt_file.max' => __('payments.errors.file_size', ['max' => intdiv((int) config('permits.receipt_max_kilobytes'), 1024)]),
            'confirmed.accepted' => __('applicants.intake.confirm_required'),
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
            // The amount paid is the fee by today's schedule, when the schedule has one.
            function (Validator $validator): void {
                if (! $this->paying() || $validator->errors()->hasAny(['amount', 'permit_type', 'valid_from', 'valid_to', 'vehicles.0.vehicle_type_id'])) {
                    return;
                }

                $from = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('valid_from')->toString(), 'UTC');
                $to = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('valid_to')->toString(), 'UTC');
                $fee = app(FeeCalculator::class)->amountFor(
                    PermitType::from($this->string('permit_type')->toString()),
                    (int) $this->input('vehicles.0.vehicle_type_id'),
                    (int) $from->diffInDays($to) + 1,
                    ValidityRules::today(),
                );

                if ($fee !== null && Money::parse($this->string('amount')->toString()) !== $fee) {
                    $validator->errors()->add('amount', __('payments.errors.amount_mismatch', ['fee' => Money::format($fee)]));
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
            'amount' => __('payments.fields.amount'),
            'receipt_number' => __('payments.fields.receipt_number'),
            'paid_at' => __('payments.fields.paid_at'),
            'receipt_file' => __('payments.fields.receipt_file'),
            'confirmed' => __('applicants.intake.confirm'),
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
     * The receipt taken with a submitted request; none with a draft.
     */
    public function payment(): ?PaymentData
    {
        if (! $this->paying()) {
            return null;
        }

        $file = $this->file('receipt_file');

        return new PaymentData(
            // The office takes bank deposit slips; the cashier sees the receipt itself.
            method: PaymentMethod::BankDeposit,
            receiptNumber: $this->string('receipt_number')->toString(),
            amountCents: Money::parse($this->string('amount')->toString()),
            paidAt: CarbonImmutable::createFromFormat('!Y-m-d', $this->string('paid_at')->toString(), 'UTC'),
            receiptFile: $file instanceof UploadedFile ? $file : null,
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
