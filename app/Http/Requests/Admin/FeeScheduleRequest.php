<?php

namespace App\Http\Requests\Admin;

use App\Data\FeeScheduleData;
use App\Enums\PermitType;
use App\Models\FeeSchedule;
use App\Models\VehicleType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A line of the fee schedule, added or changed by an Admin. The amount is
 * typed in birr and stored as santim.
 */
class FeeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schedule = $this->route('fee_schedule');

        return $schedule instanceof FeeSchedule
            ? (bool) $this->user()?->can('update', $schedule)
            : (bool) $this->user()?->can('create', FeeSchedule::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'permit_type' => ['required', Rule::enum(PermitType::class)],
            'vehicle_type_id' => ['nullable', 'integer', Rule::exists(VehicleType::class, 'id')],
            'min_days' => ['bail', 'required', 'integer', 'min:1', 'max:3660'],
            'max_days' => ['bail', 'required', 'integer', 'gte:min_days', 'max:3660'],
            'amount' => ['bail', 'required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! Money::isValid($value)) {
                    $fail(__('payments.errors.amount_invalid'));
                }
            }],
            'effective_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'effective_to' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'permit_type' => __('fees.fields.permit_type'),
            'vehicle_type_id' => __('fees.fields.vehicle_type'),
            'min_days' => __('fees.fields.min_days'),
            'max_days' => __('fees.fields.max_days'),
            'amount' => __('fees.fields.amount'),
            'effective_from' => __('fees.fields.effective_from'),
            'effective_to' => __('fees.fields.effective_to'),
        ];
    }

    public function toData(): FeeScheduleData
    {
        /** @var array{permit_type: string, vehicle_type_id?: int|string|null, min_days: int|string, max_days: int|string, amount: string, effective_from: string, effective_to?: string|null} $validated */
        $validated = $this->validated();

        return new FeeScheduleData(
            permitType: PermitType::from($validated['permit_type']),
            vehicleTypeId: isset($validated['vehicle_type_id']) ? (int) $validated['vehicle_type_id'] : null,
            minDays: (int) $validated['min_days'],
            maxDays: (int) $validated['max_days'],
            amountCents: Money::parse($validated['amount']),
            effectiveFrom: CarbonImmutable::createFromFormat('!Y-m-d', $validated['effective_from'], 'UTC'),
            effectiveTo: isset($validated['effective_to'])
                ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['effective_to'], 'UTC')
                : null,
        );
    }
}
