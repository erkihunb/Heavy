<?php

namespace App\Http\Requests\Registrar;

use App\Data\PermitData;
use App\Data\VehicleData;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PermitType;
use App\Http\Requests\Concerns\ChoosesFeeRate;
use App\Http\Requests\Concerns\ValidatesMovementHours;
use App\Http\Requests\Concerns\ValidatesRequestLetter;
use App\Models\Branch;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * Correcting a request taken at the office. The period is a fee rate and
 * the start date, which arrives as Gregorian `Y-m-d` (the Ethiopian date
 * picker converts before sending); the end date follows from the rate. A new
 * scan of the letter is needed only when none is stored yet.
 *
 * The vehicles come either chosen from the register (`primary_vehicle_id`,
 * `trailer_vehicle_ids`) or typed as on the create form (`vehicles`: the
 * vehicle, then its trailer), which ResolveEditedVehicles turns into vehicles
 * of the register.
 */
class UpdatePermitForApplicantRequest extends FormRequest
{
    use ActsForApplicant;
    use ChoosesFeeRate;
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
            'primary_vehicle_id' => ['bail', 'required_without:vehicles', 'string', $this->ownVehicle()],
            'vehicles' => ['sometimes', 'array', 'min:1', 'max:'.(1 + (int) config('permits.max_trailers'))],
            'vehicles.*.plate' => ['bail', 'required', 'string', 'max:50', $this->wellFormedPlate(...)],
            // An active type, or the one a vehicle of the request already has.
            'vehicles.*.vehicle_type_id' => ['required', 'integer', Rule::exists(VehicleType::class, 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereIn('id', $this->permit()->vehicles()->pluck('vehicle_type_id')->all()))],
            'vehicles.*.owner_name' => ['nullable', 'string', 'max:255'],
            'vehicles.*.type_detail' => ['nullable', 'string', 'max:255'],
            'trailer_vehicle_ids' => ['nullable', 'array', 'max:'.config('permits.max_trailers')],
            'trailer_vehicle_ids.*' => ['bail', 'required', 'string', 'distinct', 'different:primary_vehicle_id', $this->ownVehicle()],
            ...$this->feeRateRules(),
            'reason' => ['required', 'string', 'max:2000'],
            // Where the vehicles may travel; left empty, the permit does not limit it.
            'route' => ['nullable', 'string', 'max:255'],
            // Empty means the head office. A closed branch takes no new applications,
            // but an application already made there may stay.
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhere('id', $this->permit()->branch_id))],
            ...$this->movementHoursRules(),
            // A vehicle of a request letter shares the letter's scan.
            ...$this->letterRules(scanRequired: $this->permit()->letter_file_path === null && $this->permit()->requestLetter?->letter_file_path === null),
        ];
    }

    /**
     * The rate is one offered for the permit type and the primary vehicle's
     * type, and the period it gives keeps to the limits (spec §4.6).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->has('vehicles')) {
                    $vehicleTypeId = $validator->errors()->has('vehicles.0.vehicle_type_id') ? null : $this->input('vehicles.0.vehicle_type_id');

                    $this->checkFeeRate($validator, is_numeric($vehicleTypeId) ? (int) $vehicleTypeId : null, ['vehicles.0.vehicle_type_id']);

                    return;
                }

                $vehicleTypeId = $validator->errors()->has('primary_vehicle_id')
                    ? null
                    : Vehicle::query()->whereKey($this->string('primary_vehicle_id')->toString())->value('vehicle_type_id');

                $this->checkFeeRate($validator, $vehicleTypeId === null ? null : (int) $vehicleTypeId, ['primary_vehicle_id']);
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
            'vehicles.*.plate' => __('vehicles.fields.plate'),
            'vehicles.*.vehicle_type_id' => __('vehicles.fields.vehicle_type'),
            'vehicles.*.owner_name' => __('vehicles.fields.owner_name'),
            ...$this->feeRateAttributes(),
            'reason' => __('permits.fields.reason'),
            'route' => __('permits.fields.route'),
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

    /**
     * The vehicles typed as on the create form, the primary one first; null
     * when they were chosen from the register instead. An owner left empty is
     * the organization that asked.
     *
     * @return list<VehicleData>|null
     */
    public function typedVehicles(): ?array
    {
        /** @var list<array{plate: string, vehicle_type_id: int|string, owner_name?: string|null, type_detail?: string|null}>|null $vehicles */
        $vehicles = $this->validated('vehicles');

        if ($vehicles === null) {
            return null;
        }

        $organization = $this->permit()->loadMissing('organization')->organization->name_am;

        return array_map(fn (array $vehicle): VehicleData => new VehicleData(
            plate: PlateNumber::parse($vehicle['plate']),
            vehicleTypeId: (int) $vehicle['vehicle_type_id'],
            ownerName: filled($vehicle['owner_name'] ?? null) ? trim((string) $vehicle['owner_name']) : $organization,
            typeDetail: filled($vehicle['type_detail'] ?? null) ? trim((string) $vehicle['type_detail']) : null,
        ), $vehicles);
    }

    /**
     * @param  array{primary: string, trailers: list<string>}|null  $resolved  the typed vehicles, as vehicles of the register
     */
    public function toData(?array $resolved = null): PermitData
    {
        /** @var array{permit_type: string, primary_vehicle_id?: string, trailer_vehicle_ids?: array<array-key, string>|null, reason: string, route?: string|null, branch_id?: int|string|null} $validated */
        $validated = $this->validated();

        return $this->withLetter(new PermitData(
            permitType: PermitType::from($validated['permit_type']),
            primaryVehicleId: $resolved['primary'] ?? (string) ($validated['primary_vehicle_id'] ?? ''),
            trailerVehicleIds: $resolved['trailers'] ?? array_values($validated['trailer_vehicle_ids'] ?? []),
            validFrom: $this->validFrom(),
            validTo: $this->validTo(),
            reason: trim($validated['reason']),
            branchId: isset($validated['branch_id']) && $validated['branch_id'] !== '' ? (int) $validated['branch_id'] : null,
            hours: $this->movementHours(),
            route: filled($validated['route'] ?? null) ? trim((string) $validated['route']) : null,
        ));
    }

    private function permit(): Permit
    {
        /** @var Permit $permit */
        $permit = $this->route('permit');

        return $permit;
    }

    /**
     * A plate written as region, code and number (who holds it is checked on saving).
     */
    private function wellFormedPlate(string $attribute, mixed $value, Closure $fail): void
    {
        if (PlateNumber::tryParse((string) $value) === null) {
            $fail(__('vehicles.errors.plate_invalid'));
        }
    }

    /**
     * Only vehicles in the register of the application's organization.
     */
    private function ownVehicle(): Exists
    {
        return Rule::exists(Vehicle::class, 'id')->where('organization_id', $this->permit()->organization_id);
    }
}
