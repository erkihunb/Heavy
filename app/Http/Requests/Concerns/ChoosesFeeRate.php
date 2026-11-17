<?php

namespace App\Http\Requests\Concerns;

use App\Domain\Permits\FeeCalculator;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * The period of a permit registered at the office: a fee rate from the
 * schedule (`fee_schedule_id`, one of FeeCalculator::rates() for the permit
 * type and the primary vehicle's type today) and the start date. The end
 * date is not sent: the permit runs for the rate's days, counting the start
 * day, and closes by itself afterwards (permits:expire).
 *
 * @mixin FormRequest
 */
trait ChoosesFeeRate
{
    /**
     * The rate chosen, once checkFeeRate() has found it among those offered.
     *
     * @var array{id: int, min_days: int, max_days: int, days: int, amount_cents: int}|null
     */
    private ?array $feeRate = null;

    /**
     * @param  list<string>  $condition  rules put first, e.g. ['exclude_unless:apply,true']
     * @return array<string, list<string>>
     */
    protected function feeRateRules(array $condition = []): array
    {
        return [
            'fee_schedule_id' => [...$condition, 'bail', 'required', 'integer'],
            'valid_from' => [...$condition, 'bail', 'required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function feeRateAttributes(): array
    {
        return [
            'fee_schedule_id' => __('permits.rates.label'),
            'valid_from' => __('permits.fields.valid_from'),
        ];
    }

    /**
     * Whether the schedule prices the permit at all, the rate chosen is one
     * of those offered, and the period it gives keeps to the limits (spec
     * §4.6). A permit the schedule has no fee for could never be approved, so
     * it is not taken in: an Admin adds the fee first.
     *
     * @param  int|null  $vehicleTypeId  the type of the primary vehicle
     * @param  list<string>  $vehicleFields  the fields the vehicle type is read from
     */
    protected function checkFeeRate(Validator $validator, ?int $vehicleTypeId, array $vehicleFields): void
    {
        if ($validator->errors()->hasAny(['permit_type', ...$vehicleFields])) {
            return;
        }

        $fees = app(FeeCalculator::class);
        $type = PermitType::from($this->string('permit_type')->toString());
        $today = ValidityRules::today();

        if ($fees->rates($type, $vehicleTypeId, $today) === []) {
            $validator->errors()->add('permit_type', __('permits.errors.fee_not_set_for_registration'));

            return;
        }

        if (! $validator->errors()->has('fee_schedule_id')) {
            $this->feeRate = $fees->rate($type, $vehicleTypeId, $this->integer('fee_schedule_id'), $today);

            if ($this->feeRate === null) {
                $validator->errors()->add('fee_schedule_id', __('permits.errors.rate_not_offered'));
            }
        }

        $this->checkPeriod($validator);
    }

    /**
     * The limits on the period worked out from the start date and the rate;
     * a start in the past is reported even before a rate is chosen.
     */
    private function checkPeriod(Validator $validator): void
    {
        if ($validator->errors()->has('valid_from')) {
            return;
        }

        $from = $this->validFrom();
        $violation = app(ValidityRules::class)->violation($from, $this->feeRate === null ? $from : $this->validTo());

        if ($violation !== null) {
            $validator->errors()->add(
                $violation->rule === 'valid_from_in_past' ? 'valid_from' : 'fee_schedule_id',
                $violation->userMessage(),
            );
        }
    }

    protected function validFrom(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->string('valid_from')->toString(), 'UTC');
    }

    /**
     * The last day the permit is valid: the start day counts as the first of the rate's days.
     */
    protected function validTo(): CarbonImmutable
    {
        $rate = $this->feeRate ?? throw new LogicException('The fee rate is known only once the request is validated.');

        return $this->validFrom()->addDays($rate['days'] - 1);
    }
}
